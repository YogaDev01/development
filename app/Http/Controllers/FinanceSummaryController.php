<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Project;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\RequestDetail;
use App\Models\RequestStatusLog;
use App\Models\Account;
use App\Models\CashReceipt;
use App\Models\DailyTransaction;
use App\Models\Invoice;

class FinanceSummaryController extends Controller
{
    /** Status CR yang dianggap sudah membebani anggaran (selaras BudgetPlan::usedAmount()) */
    /** Arti tiap kelompok status ada di App\Support\BudgetConsumption. */
    private const USED_STATUSES = \App\Support\BudgetConsumption::REPORTED;

    /** Role yang boleh melihat nilai PO */
    private const PO_VALUE_ROLES = ['superadmin', 'approver', 'finance', 'finance_manager', 'head', 'guest'];

    /**
     * Jendela tahun untuk grafik & breakdown bulanan di tab Executive.
     *
     * Grafik tren dan tooltip breakdown sebelumnya tidak dibatasi apa pun:
     * setiap pemuatan tab memindai seluruh riwayat projects, cash_receipts dan
     * daily_transactions, sehingga biayanya naik terus dan tidak pernah
     * membaik. Lima tahun jauh melebihi horizon yang benar-benar dilihat
     * manajemen di grafik tren, dan membuat penyaringannya bisa memakai indeks
     * tanggal yang sudah ada.
     */
    private const CHART_TAHUN_KE_BELAKANG = 5;

    /** Role yang boleh melihat tab Summary Executive (C-Level). */
    private const EXECUTIVE_ROLES = ['superadmin', 'finance', 'finance_manager', 'approver', 'guest'];

    /** Role yang melihat SEMUA project (tidak dibatasi access group). */
    private const FULL_SCOPE_ROLES = ['superadmin', 'finance', 'finance_manager', 'approver', 'admin', 'cost_control', 'treasury', 'collection', 'accounting', 'guest'];

    /** Role yang boleh melihat tab Summary Collection (piutang/penagihan). */
    private const COLLECTION_ROLES = ['superadmin', 'finance', 'finance_manager', 'approver', 'collection', 'guest'];

    /**
     * GET /finance-summary DAN /finance-summary-executive — satu halaman,
     * tiga tab. Tab Budget selalu dihitung; tab Executive & Collection hanya
     * dihitung (dan dikirim ke view) kalau role user berhak, supaya tidak ada
     * data yang bocor walau URL-nya diakses langsung.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');
        $canViewExecutive  = in_array($role, self::EXECUTIVE_ROLES, true);
        $canViewCollection = in_array($role, self::COLLECTION_ROLES, true);

        $data = $this->budgetData($request);

        if ($canViewExecutive) {
            $data = array_merge($data, $this->executiveData());
        }

        /*
        | Active Cash Advance ada di tab Budget yang bisa dibuka SEMUA role,
        | jadi datanya dihitung tanpa gerbang EXECUTIVE_ROLES. Dulu ikut
        | $canViewExecutive sehingga role seperti Head membuka halamannya tapi
        | tabelnya hilang sama sekali.
        */
        $data = array_merge($data, $this->activeCashAdvanceData($request));

        /*
        | TIDAK ADA gerbang menu untuk membuka detail CR dari kartu ini.
        | Dulu kode CR ditautkan ke halaman Cost Request, yang digerbang
        | 'menu:finance-cost-request' — sehingga perlu diperiksa dulu supaya
        | role lain tidak menabrak 403. Sekarang detailnya dibuka sebagai modal
        | di halaman ini juga, memakai endpoint budget-request/{id}/detail-json
        | yang memang tidak bergerbang menu dan menjaga dirinya sendiri per
        | dokumen (canViewCr). Daftar di kartu ini pun sudah disaring
        | scopedProjectIds(), jadi baris yang terlihat memang boleh dibuka.
        */
        if ($canViewCollection) {
            /*
            | P-02: SATU filter context untuk SEMUA panel Collection. Dulu
            | poSummaryData() query Project-nya sendiri tanpa filter, sehingga
            | DataTable ikut filter tapi PO Summary tetap seluruh customer.
            | Sekarang filter di-parse sekali di sini lalu diturunkan ke setiap
            | konsumen: DataTable, Summary Card, PO Summary, Progress Summary.
            */
            $filters = $this->collectionFilters($request);

            $data = array_merge($data, $this->collectionData($request, $filters));
            $data = array_merge($data, $this->poSummaryData($filters));
            $data = array_merge($data, $this->progressSummaryData($filters));
            $data['collectionFilterActive'] = (bool) ($filters['manager'] || $filters['customer'] || $filters['month']);
        }

        $data['canViewExecutive']  = $canViewExecutive;
        $data['canViewCollection'] = $canViewCollection;
        $data['initialTab'] = $canViewExecutive ? 'executive' : ($canViewCollection ? 'collection' : 'budget');

        // Setelah submit filter Collection, pertahankan tab yang aktif.
        $requestedTab = $request->query('tab');
        if (in_array($requestedTab, ['budget', 'executive', 'collection'], true)) {
            if (($requestedTab === 'executive' && $canViewExecutive)
                || ($requestedTab === 'collection' && $canViewCollection)
                || $requestedTab === 'budget') {
                $data['initialTab'] = $requestedTab;
            }
        }

        return view('finance-summary.index', $data);
    }

    /**
     * Hitung jumlah & total per status dalam SATU query agregat.
     *
     * Sebelumnya setiap blok di budgetData() menarik seluruh isi tabelnya ke
     * memori lalu memanggil ->count() dan ->sum() per status di atas koleksi
     * itu - 30+ iterasi atas data yang sama. Itu pekerjaan yang memang
     * dirancang untuk dilakukan basis data, dan pola yang benar sudah dipakai
     * di tempat lain dalam proyek ini (crStatusCounts(), ProjectController::index()).
     *
     * @param  string|null  $sumColumn  kolom yang dijumlahkan, null kalau hanya menghitung.
     * @return array{counts: \Illuminate\Support\Collection, sums: \Illuminate\Support\Collection}
     */
    private function agregatPerStatus($query, ?string $sumColumn = null): array
    {
        $pilih = ['status', DB::raw('COUNT(*) as jumlah')];

        if ($sumColumn) {
            $pilih[] = DB::raw('COALESCE(SUM(' . $sumColumn . '), 0) as total');
        }

        $baris = (clone $query)->select($pilih)->groupBy('status')->get();

        return [
            'counts' => $baris->pluck('jumlah', 'status')->map(fn ($v) => (int) $v),
            'sums'   => $sumColumn
                ? $baris->pluck('total', 'status')->map(fn ($v) => (int) $v)
                : collect(),
        ];
    }

    private function budgetData(Request $request): array
    {
        /*
        |--------------------------------------------------------------------------
        | SCOPE AKSES
        |--------------------------------------------------------------------------
        */
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        // Manager tidak boleh melihat informasi PO sama sekali di Finance Summary.
        $hidePo = $role === 'manager';

        $scopedProjectIds = $this->scopedProjectIds();

        /*
        |--------------------------------------------------------------------------
        | CATATAN PERUBAHAN 26 Sep 2026 - agregasi di basis data
        |--------------------------------------------------------------------------
        | Versi sebelumnya memuat SELURUH isi projects, budget_plans dan
        | budget_request ke memori PHP (masing-masing ->get()), lalu menghitung
        | jumlah & total per status di atas koleksi itu. Untuk halaman yang
        | paling sering dibuka manajemen, itu berarti halaman ini yang pertama
        | mati begitu data tumbuh: pada ~100.000 Cost Request saja sudah ratusan
        | MB memori sebelum satu angka pun tampil.
        |
        | Dua hal yang membuat rows itu perlu - $poList dan $crList - ternyata
        | TIDAK DIPAKAI di mana pun: keduanya disusun, dikirim ke view, dan
        | tidak pernah dibaca (komentarnya masih menyebut "modal timeline",
        | padahal modal itu sekarang memakai endpoint budget-request/{id}/detail-json,
        | seperti dijelaskan di index()). Dengan keduanya dihapus, tidak ada lagi
        | alasan memuat baris sama sekali - semuanya angka agregat.
        |
        | Angka yang dihasilkan sama persis; yang berubah hanya di mana ia
        | dihitung.
        */

        /*
        |--------------------------------------------------------------------------
        | PO (Project)
        |--------------------------------------------------------------------------
        */
        $poQuery = Project::query();
        if (is_array($scopedProjectIds)) {
            $poQuery->whereIn('id', $scopedProjectIds ?: [0]);
        }

        $poAgg = $this->agregatPerStatus($poQuery, 'value');

        $po = [
            'total'         => (int) $poAgg['counts']->sum(),
            'total_value'   => (int) $poAgg['sums']->sum(),
            'draft'         => $poAgg['counts']['DRAFT'] ?? 0,
            'submitted'     => $poAgg['counts']['SUBMITTED'] ?? 0,
            'verified'      => $poAgg['counts']['VERIFIED'] ?? 0,
            'early_start'   => $poAgg['counts']['EARLY_START'] ?? 0,
            'waiting_po'    => $poAgg['counts']['WAITING_PO'] ?? 0,
            'active'        => $poAgg['counts']['ACTIVE'] ?? 0,
            'progress_done' => $poAgg['counts']['PROGRESS_DONE'] ?? 0,
            'partially_invoiced' => $poAgg['counts']['PARTIALLY_INVOICED'] ?? 0,
            'partially_paid'     => $poAgg['counts']['PARTIALLY_PAID'] ?? 0,
            'billed'        => $poAgg['counts']['BILLED'] ?? 0,
            'paid'          => $poAgg['counts']['PAID'] ?? 0,
            'closing'       => $poAgg['counts']['CLOSING'] ?? 0,
            'closed'        => $poAgg['counts']['CLOSED'] ?? 0,
        ];

        // PRIVASI: nilai PO di-null-kan di lapisan data bila role tak berhak.
        $canSeePoValue = in_array($role, self::PO_VALUE_ROLES);
        if (!$canSeePoValue) {
            $po['total_value'] = null;
        }
        $po['can_see_value'] = $canSeePoValue;

        /*
        |--------------------------------------------------------------------------
        | BUDGETING (BudgetPlan)
        |--------------------------------------------------------------------------
        */
        $bpQuery = BudgetPlan::query();
        if (is_array($scopedProjectIds)) {
            $bpQuery->whereIn('project_id', $scopedProjectIds ?: [0]);
        }

        $bpAgg = $this->agregatPerStatus($bpQuery, 'amount');

        $bp = [
            'total'         => (int) $bpAgg['counts']->sum(),
            'total_amount'  => (int) $bpAgg['sums']->sum(),
            'active_amount' => $bpAgg['sums']['ACTIVE'] ?? 0,
            'draft'         => $bpAgg['counts']['DRAFT'] ?? 0,
            'submitted'     => $bpAgg['counts']['SUBMITTED'] ?? 0,
            'checked'       => $bpAgg['counts']['CHECKED'] ?? 0,
            'verified'      => $bpAgg['counts']['VERIFIED'] ?? 0,
            'active'        => $bpAgg['counts']['ACTIVE'] ?? 0,
            'closing'       => $bpAgg['counts']['CLOSING'] ?? 0,
            'closed'        => $bpAgg['counts']['CLOSED'] ?? 0,
            'rejected'      => $bpAgg['counts']['REJECTED'] ?? 0,
        ];

        /*
        |--------------------------------------------------------------------------
        | COST REQUEST (BudgetRequest)
        |--------------------------------------------------------------------------
        | Scoping-nya DIPERTAHANKAN apa adanya: whereHas('budgetPlan.project')
        | memang tidak menyertakan CR yang budget plan-nya tanpa project
        | (overhead / payroll lewat cost center). Itu perilaku sebelumnya dan
        | bukan bagian dari perubahan ini.
        */
        $crQuery = BudgetRequest::query();
        if (is_array($scopedProjectIds)) {
            $ids = $scopedProjectIds ?: [0];
            $crQuery->whereHas('budgetPlan.project', fn ($q) => $q->whereIn('id', $ids));
        }

        $crAgg = $this->agregatPerStatus($crQuery, 'amount');

        $cr = [
            'total'        => (int) $crAgg['counts']->sum(),
            'total_amount' => (int) $crAgg['sums']->sum(),
            'draft'        => $crAgg['counts']['DRAFT'] ?? 0,
            'submitted'    => $crAgg['counts']['SUBMITTED'] ?? 0,
            'approved'     => $crAgg['counts']['APPROVED'] ?? 0,
            'verified'     => $crAgg['counts']['VERIFIED'] ?? 0,
            'queued'       => $crAgg['counts']['QUEUED'] ?? 0,
            'paid'         => $crAgg['counts']['PAID'] ?? 0,
            'realized'     => $crAgg['counts']['REALIZED'] ?? 0,
            'closed'       => $crAgg['counts']['CLOSED'] ?? 0,
            'rejected'     => $crAgg['counts']['REJECTED'] ?? 0,
        ];

        /*
        | Total realisasi GLOBAL (semua CR dalam scope akses, TIDAK terikat
        | filter budget plan di panel kanan) - dipakai card KPI "Total Realisasi".
        |
        | Memakai SUBQUERY, bukan whereIn() atas daftar id yang ditarik ke PHP.
        | Versi sebelumnya mengirim seluruh id CR kembali ke basis data sebagai
        | satu klausa IN raksasa; pada puluhan ribu id, panjang teks query-nya
        | menembus max_allowed_packet dan permintaannya gagal dengan galat yang
        | tidak menunjuk sebab sebenarnya.
        */
        $cr['realized_amount'] = (int) BudgetRequestRealization::whereIn(
            'budget_request_id',
            (clone $crQuery)->select('id')
        )->sum('total');

        /*
        |--------------------------------------------------------------------------
        | SERAPAN ANGGARAN - dengan filter budget plan
        |--------------------------------------------------------------------------
        | Pilihan: 'all' (semua budget ACTIVE) atau satu budget plan ACTIVE.
        |
        | Hanya budget plan ACTIVE yang diambil barisnya - itu yang mengisi
        | dropdown, jadi memang perlu. Jumlahnya per definisi kecil.
        */
        $activeBudgetPlans = (clone $bpQuery)
            ->where('status', 'ACTIVE')
            ->orderBy('code')
            ->get(['id', 'code', 'description', 'amount']);

        $selectedBudgetId = $request->query('budget_plan_id', 'all');

        $selectedPlan = null;
        if ($selectedBudgetId !== 'all') {
            $selectedPlan = $activeBudgetPlans->firstWhere('id', (int) $selectedBudgetId);
            if (!$selectedPlan) {
                $selectedBudgetId = 'all';   // id tak valid -> fallback ke semua
            }
        }

        $targetPlans   = $selectedPlan ? collect([$selectedPlan]) : $activeBudgetPlans;
        $targetPlanIds = $targetPlans->pluck('id');

        $totalBudget = (int) $targetPlans->sum('amount');

        // CR yang MENYERAP anggaran pada budget plan terpilih. Dipakai tiga kali
        // di bawah (jumlah terpakai, realisasi, rincian per account) sebagai
        // SUBQUERY, jadi tidak ada baris yang keluar ke PHP.
        $usedCrQuery = (clone $crQuery)
            ->whereIn('budget_plan_id', $targetPlanIds->all() ?: [0])
            ->whereIn('status', self::USED_STATUSES);

        $usedAmount = (int) (clone $usedCrQuery)->sum('amount');

        $realizedAmount = (int) BudgetRequestRealization::whereIn(
            'budget_request_id',
            (clone $usedCrQuery)->select('id')
        )->sum('total');

        $remainingBudget = max(0, $totalBudget - $usedAmount);

        $absorptionPct = $totalBudget > 0 ? round($usedAmount / $totalBudget * 100, 1) : 0;
        $remainingPct  = $totalBudget > 0 ? round($remainingBudget / $totalBudget * 100, 1) : 0;

        /*
        |--------------------------------------------------------------------------
        | RINCIAN TERSERAP PER ACCOUNT (Akomodasi, Transport, dll)
        |--------------------------------------------------------------------------
        | Dijumlahkan di basis data lalu namanya dilekatkan. Sebelumnya seluruh
        | baris request_detail milik CR terpakai ditarik ke memori hanya untuk
        | dikelompokkan per account_id - jumlah barisnya kelipatan dari jumlah
        | CR, jadi ini blok paling berat di halaman ini.
        */
        $breakdownRows = RequestDetail::whereIn('budget_request_id', (clone $usedCrQuery)->select('id'))
            ->select('account_id', DB::raw('COALESCE(SUM(total), 0) as total'))
            ->groupBy('account_id')
            ->orderByDesc('total')
            ->get();

        $accountNames = $breakdownRows->pluck('account_id')->filter()->unique()->values()->isEmpty()
            ? collect()
            : Account::whereIn('id', $breakdownRows->pluck('account_id')->filter()->unique()->values())
                ->pluck('name', 'id');

        $accountBreakdown = $breakdownRows->map(fn ($row) => [
            'name'  => $accountNames[$row->account_id] ?? 'Tanpa Account',
            'total' => (int) $row->total,
        ])->values();

        $breakdownTotal = (int) $accountBreakdown->sum('total');

        $realizedOfUsedPct = $usedAmount > 0 ? round(($realizedAmount / $usedAmount) * 100, 1) : 0.0;

        $finance = [
            'total_budget'         => $totalBudget,
            'used'                 => $usedAmount,
            'remaining'            => $remainingBudget,
            'realized'             => $realizedAmount,
            'absorption_pct'       => $absorptionPct,
            'remaining_pct'        => $remainingPct,
            'realized_of_used_pct' => $realizedOfUsedPct,
        ];

        return compact(
            'po',
            'bp',
            'cr',
            'finance',
            'activeBudgetPlans',
            'selectedBudgetId',
            'accountBreakdown',
            'breakdownTotal',
            'hidePo'
        );
    }

    /**
     * Rollup 1 baris per PO — status kerja/dokumen + margin + nilai, untuk
     * tab Summary Executive (Superadmin/Finance/Approver saja).
     */
    private function executiveData(): array
    {
        /*
         | Kenapa relasi `details` ikut di-eager-load dan dua peta di bawah ada:
         | map() di bawah dulu memanggil implementationProgress(),
         | costRealizationTotal(), paymentTotal(), dan projectMargin() PER
         | PROJECT. Masing-masing mengeluarkan query sendiri, sehingga satu kali
         | membuka Finance Summary — tab apa pun, karena data ini selalu dirakit —
         | menghasilkan ratusan query. Angkanya tidak berubah sedikit pun di sini;
         | yang berubah cuma cara mengambilnya: sekali untuk semua project.
         */
        $projects = Project::with([
            'customer:id,name,initial', 'manager', 'budgetPlans', 'poNumbers',
            'details:id,project_id,weight,status',
        ])->get();

        $paidDppMap  = $this->paidDppByProject();
        $costRealMap = $this->costRealizationByProject();

        $rows = $projects
            ->sortBy(fn ($p) => $p->customer->name ?? '')
            ->values()
            ->map(function ($p) use ($paidDppMap, $costRealMap) {
                // Rumusnya disalin apa adanya dari Project::projectMargin(),
                // cuma memakai angka yang sudah dihitung massal di atas.
                $budget    = $p->budgetPlanTotal();
                $realisasi = (int) ($costRealMap[$p->id] ?? 0);
                $projectMargin = $budget > 0
                    ? round((($budget - $realisasi) / $budget) * 100, 1)
                    : 0.0;

                return [
                    'id'                    => $p->id,
                    'code'                  => $p->code,
                    'name'                  => $p->name,
                    'customer'              => $p->customer->name ?? '-',
                    'customer_initial'      => $p->customer->initial ?? '-',
                    'pm_internal'           => $p->pm_internal_name ?: '-',
                    'status'                => $p->status,
                    'status_label'          => $p->status_label,
                    'status_color'          => $p->status_color,
                    'implementation'        => $p->implementationProgress(),  // relasi `details` sudah ter-eager-load
                    'po_received_status'    => $p->monitoring_po_received_status,
                    'bast_status'           => $p->monitoring_bast_status,
                    'invoice_status'        => $p->monitoring_invoice_status,
                    'payment_status'        => $p->monitoring_payment_status,
                    'work_badge'            => Project::progressBadge($p->workProgressStatus()),
                    'billing_badge'         => Project::progressBadge($p->billingProgressStatus()),
                    'payment_badge'         => Project::progressBadge($p->paymentProgressStatus()),
                    'project_margin'        => $projectMargin,
                    'plan_gross_margin'     => $p->grossMargin(),
                    'po_value'              => (int) ($p->value ?? 0),
                    'contract'              => (int) ($p->contract ?? 0),
                    'budget_value'          => $budget,
                    'realization_value'     => $realisasi,
                    'payment_value'         => (int) ($paidDppMap[$p->id] ?? 0),
                    'overhead'              => (int) ($p->overhead ?? 0),
                    'fixed_cost'            => (int) ($p->fixed_cost ?? 0),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 6 KPI CARD — Contract -> Released PO -> Invoice -> Paid.
        | Waiting PO = Contract - Released PO (PO yang belum rilis nominalnya).
        | Total Unpaid = Total Invoiced - Total Paid. Invoice DRAFT/CANCELLED
        | tidak dihitung (belum/tidak jadi tagihan nyata ke customer) — supaya
        | tidak double counting dengan Collection tab.
        |--------------------------------------------------------------------------
        */
        $execTotalContract = (int) $projects->sum(fn ($p) => (int) ($p->contract ?? 0));
        $execReleasedPo    = (int) $rows->sum('po_value');
        $execWaitingPo     = max(0, $execTotalContract - $execReleasedPo);

        $execInvoices      = Invoice::whereNotIn('status', ['DRAFT', 'CANCELLED'])->get(['status', 'captured_total']);
        $execTotalInvoiced = (int) $execInvoices->sum('captured_total');
        $execTotalPaid     = (int) $execInvoices->where('status', 'PAID')->sum('captured_total');
        $execTotalUnpaid   = max(0, $execTotalInvoiced - $execTotalPaid);

        $kpiExecutive = [
            'total_contract'   => $execTotalContract,
            'released_po'      => $execReleasedPo,
            'waiting_po'       => $execWaitingPo,
            'total_invoiced'   => $execTotalInvoiced,
            'total_paid'       => $execTotalPaid,
            'total_unpaid'     => $execTotalUnpaid,
            // Progress indicator Contract -> PO -> Invoice -> Paid, relatif ke Contract.
            'pct_po'      => $execTotalContract > 0 ? min(100, round($execReleasedPo / $execTotalContract * 100)) : 0,
            'pct_invoice' => $execTotalContract > 0 ? min(100, round($execTotalInvoiced / $execTotalContract * 100)) : 0,
            'pct_paid'    => $execTotalContract > 0 ? min(100, round($execTotalPaid / $execTotalContract * 100)) : 0,
        ];

        /*
        |--------------------------------------------------------------------------
        | CHART DATA
        |--------------------------------------------------------------------------
        */

        // 1) Distribusi PO per status, berdasarkan total NILAI PO (bukan qty),
        // urutan sesuai siklus status (bukan alfabet). CLOSED disembunyikan
        // default di frontend, datanya tetap dikirim supaya toggle-able.
        $statusGroups = $rows->groupBy('status');
        $statusChart = collect(Project::STATUSES)
            ->filter(fn ($s) => $statusGroups->has($s))
            ->map(fn ($s) => [
                'status' => $s,
                'label'  => $statusGroups[$s]->first()['status_label'],
                'count'  => $statusGroups[$s]->count(),
                'value'  => $statusGroups[$s]->sum('po_value'),
            ])
            ->values();

        // 2) Progress dokumen (R/B/I/P) — disatukan ke 3 bucket yang sama:
        // Not Started / Partial / Done (label progressBadge() dipakai sebagai acuan).
        $monToBucket = ['NOT_YET' => 'Not Started', 'ON_GOING' => 'Partial', 'DONE' => 'Done'];
        $buckets = ['Not Started', 'Partial', 'Done'];
        $progressChart = collect(['R', 'B', 'I', 'P'])->mapWithKeys(fn ($k) => [$k => array_fill_keys($buckets, 0)])->toArray();

        foreach ($rows as $r) {
            $progressChart['R'][$monToBucket[$r['po_received_status']] ?? 'Not Started']++;
            $progressChart['B'][$r['work_badge']['label']]++;
            $progressChart['I'][$r['billing_badge']['label']]++;
            $progressChart['P'][$r['payment_badge']['label']]++;
        }

        // 3) Top Customer — 4 metrik sekaligus dikirim, dropdown di frontend
        // yang memilih mana yang ditampilkan/diurutkan (tanpa reload).
        $topCustomers = $rows->groupBy('customer')
            ->map(function ($g, $name) {
                return [
                    'customer'        => $name,
                    'customer_initial'=> $g->first()['customer_initial'],
                    'po_value'        => $g->sum('po_value'),
                    'gross_profit'    => (int) round($g->sum(fn ($r) => $r['po_value'] * $r['plan_gross_margin'] / 100)),
                    'project_profit'  => (int) round($g->sum(fn ($r) => $r['po_value'] * $r['project_margin'] / 100)),
                    'outstanding'     => $g->sum('po_value') - $g->sum('payment_value'),
                ];
            })
            ->values();

        // 4) Revenue Value — total nilai PO per bulan (berdasarkan created_at PO),
        // per tahun, supaya bisa gonta-ganti tahun di frontend tanpa reload.
        /*
        | Ketiga rangkaian bulanan DIBATASI jendela tahun.
        |
        | GROUP BY di sini memang harus memakai YEAR()/MONTH() - itu bentuk
        | datanya. Yang bisa diperbaiki adalah PENYARINGANNYA: dengan
        | menambahkan perbandingan langsung pada kolom tanggalnya, indeks
        | (projects_created_at_idx, cash_receipts received_date, daily_transactions date)
        | menyaring lebih dulu, dan fungsi tanggal hanya bekerja pada baris yang
        | lolos. Tanpa itu, membuka tab Executive berarti tiga pemindaian tabel
        | PENUH - dan biayanya naik terus karena riwayatnya tidak pernah
        | dibatasi apa pun.
        |
        | Jendelanya CHART_TAHUN_KE_BELAKANG tahun; dropdown tahun di frontend
        | mengikuti apa yang dikirim, jadi ia otomatis menampilkan rentang yang
        | sama.
        */
        $batasChart = now()->startOfYear()->subYears(self::CHART_TAHUN_KE_BELAKANG - 1)->format('Y-m-d H:i:s');

        $revenueChart = $this->monthlySeriesByYear(
            Project::where('created_at', '>=', $batasChart)
                ->selectRaw('YEAR(created_at) as yr, MONTH(created_at) as mo, COALESCE(SUM(value),0) as total')
        );

        // 5) Treasury — Cash Receipt (masuk) vs Cash Out/Daily Transaction PAID (keluar), per bulan per tahun.
        $cashInChart = $this->monthlySeriesByYear(
            CashReceipt::where('received_date', '>=', $batasChart)
                ->selectRaw('YEAR(received_date) as yr, MONTH(received_date) as mo, COALESCE(SUM(amount),0) as total')
        );
        $cashOutChart = $this->monthlySeriesByYear(
            DailyTransaction::where('status', 'PAID')
                ->where('date', '>=', $batasChart)
                ->selectRaw('YEAR(date) as yr, MONTH(date) as mo, COALESCE(SUM(captured_amount),0) as total')
        );

        // 6) Breakdown per customer/entitas untuk tooltip Revenue Value & Treasury.
        $revenueRawBreakdown = [];
        foreach ($projects as $p) {
            if (!$p->created_at) continue;
            $y = (int) $p->created_at->format('Y');
            $m = (int) $p->created_at->format('n');
            $cust = $p->customer->name ?? 'Unknown';
            $revenueRawBreakdown[$y][$m][$cust] = ($revenueRawBreakdown[$y][$m][$cust] ?? 0) + (int) ($p->value ?? 0);
        }
        $revenueBreakdown = $this->topBreakdown($revenueRawBreakdown);

        /*
        | Breakdown Cash In - DIJUMLAHKAN DI BASIS DATA.
        |
        | Versi sebelumnya: CashReceipt::with('invoice.po.customer')->get() -
        | SELURUH cash receipt yang pernah ada, dengan tiga tingkat relasi,
        | ditarik ke memori hanya untuk dikelompokkan per tahun/bulan/customer.
        | Tidak ada filter tanggal sama sekali, jadi biayanya tumbuh selamanya.
        |
        | Sekarang satu query agregat dengan LEFT JOIN, dibatasi jendela tahun
        | yang sama dengan chart-nya. Nama customer yang kosong (invoice tanpa
        | PO, atau PO tanpa customer) tetap jatuh ke 'Unknown' seperti
        | sebelumnya, lewat COALESCE.
        */
        $cashInRawBreakdown = [];
        CashReceipt::query()
            ->where('cash_receipts.received_date', '>=', $batasChart)
            /*
            | deleted_at dijadikan SYARAT JOIN, bukan where.
            |
            | invoices memakai SoftDeletes, jadi versi relasi sebelumnya
            | (->with('invoice.po.customer')) mengembalikan null untuk invoice
            | yang ada di recycle bin, dan baris itu jatuh ke 'Unknown'.
            | Menaruh kondisinya di dalam ON menjaga perilaku itu; menaruhnya
            | di where() akan mengubah LEFT JOIN jadi INNER JOIN secara efektif
            | dan menghilangkan baris cash receipt tanpa invoice sama sekali.
            */
            ->leftJoin('invoices', function ($j) {
                $j->on('invoices.id', '=', 'cash_receipts.invoice_id')
                  ->whereNull('invoices.deleted_at');
            })
            ->leftJoin('projects', 'projects.id', '=', 'invoices.po_id')
            ->leftJoin('customers', 'customers.id', '=', 'projects.customer_id')
            ->selectRaw("YEAR(cash_receipts.received_date) as yr, MONTH(cash_receipts.received_date) as mo, COALESCE(customers.name, 'Unknown') as label, COALESCE(SUM(cash_receipts.amount),0) as total")
            ->groupBy('yr', 'mo', 'label')
            ->get()
            ->each(function ($row) use (&$cashInRawBreakdown) {
                $cashInRawBreakdown[(int) $row->yr][(int) $row->mo][$row->label] = (int) $row->total;
            });
        $cashInBreakdown = $this->topBreakdown($cashInRawBreakdown);

        /*
        | Breakdown Cash Out - DIJUMLAHKAN DI BASIS DATA.
        |
        | Versi sebelumnya memuat SELURUH batch Treasury berstatus PAID dengan
        | eager load EMPAT tingkat
        | (includedItems.budgetRequest.budgetPlan.project.customer), tanpa
        | filter tanggal, lalu mengelompokkannya di PHP. Itu blok terberat di
        | tab Executive: jumlah baris yang dihidrasi adalah jumlah item di
        | seluruh batch yang pernah dibayar, dan setiap satu membangun rantai
        | objek Eloquent sepanjang empat model.
        |
        | Label-nya dipertahankan persis: nama customer bila budget plan-nya
        | budget proyek, "Overhead: <cost center>" bila budget cost center,
        | 'Unknown' bila keduanya kosong. CONCAT menghasilkan NULL kalau nama
        | cost center-nya NULL, sehingga COALESCE jatuh ke 'Unknown' - sama
        | dengan rantai ?? di versi lama.
        |
        | budget_request memakai SoftDeletes; kondisinya ditaruh di ON, karena
        | relasi budgetRequest pada versi lama mengembalikan null untuk CR yang
        | terhapus dan item itu di-skip (continue).
        */
        $cashOutRawBreakdown = [];
        DailyTransaction::query()
            ->where('daily_transactions.status', 'PAID')
            ->where('daily_transactions.date', '>=', $batasChart)
            ->join('daily_transaction_items', function ($j) {
                $j->on('daily_transaction_items.daily_transaction_id', '=', 'daily_transactions.id')
                  ->where('daily_transaction_items.status', '=', 'INCLUDED');
            })
            ->join('budget_request', function ($j) {
                $j->on('budget_request.id', '=', 'daily_transaction_items.budget_request_id')
                  ->whereNull('budget_request.deleted_at');
            })
            ->leftJoin('budget_plans', 'budget_plans.id', '=', 'budget_request.budget_plan_id')
            ->leftJoin('projects', 'projects.id', '=', 'budget_plans.project_id')
            ->leftJoin('customers', 'customers.id', '=', 'projects.customer_id')
            ->leftJoin('cost_centers', 'cost_centers.id', '=', 'budget_plans.cost_center_id')
            ->selectRaw("YEAR(daily_transactions.date) as yr, MONTH(daily_transactions.date) as mo, COALESCE(customers.name, CONCAT('Overhead: ', cost_centers.name), 'Unknown') as label, COALESCE(SUM(budget_request.amount),0) as total")
            ->groupBy('yr', 'mo', 'label')
            ->get()
            ->each(function ($row) use (&$cashOutRawBreakdown) {
                $cashOutRawBreakdown[(int) $row->yr][(int) $row->mo][$row->label] = (int) $row->total;
            });
        $cashOutBreakdown = $this->topBreakdown($cashOutRawBreakdown);

        return compact(
            'rows', 'statusChart', 'progressChart', 'topCustomers',
            'revenueChart', 'cashInChart', 'cashOutChart',
            'revenueBreakdown', 'cashInBreakdown', 'cashOutBreakdown',
            'kpiExecutive'
        );
    }

    /**
     * Data tab "Summary Executive" — Active Cash Advance, grouped per
     * Manager -> Beneficiary, dari relasi Manager -> PO -> Cost Request ->
     * Beneficiary -> Cash Advance.
     *
     * MN-10C — DEFINISI DIPERSEMPIT. Sebelumnya himpunannya "semua CA yang
     * belum CLOSED" (QUEUED, PAID, REALIZED, SETTLE_CHECKED, SETTLE_VERIFIED).
     * Sekarang HANYA:
     *
     *     Type = Cash Advance  AND  Status = PAID
     *
     * Alasannya:
     *   - QUEUED  : uangnya belum keluar sama sekali, jadi belum ada uang muka
     *               yang "dipegang" siapa pun — dihitung sebagai CA aktif
     *               membuat angka ini melebihi kas yang benar-benar keluar.
     *   - REALIZED / SETTLE_CHECKED / SETTLE_VERIFIED : realisasinya sudah
     *               diserahkan dan sedang dipertanggungjawabkan, jadi bukan
     *               lagi uang muka yang menganggur.
     *
     * Efeknya: angka Active Cash Advance akan LEBIH KECIL dari sebelumnya.
     * Ini perubahan definisi yang disengaja, bukan bug.
     *
     * Satu Beneficiary bisa punya beberapa CR aktif dalam satu PO, dijumlah
     * (bukan dihitung dobel per baris CR) supaya "CA Value" mencerminkan total
     * kewajiban CA yang masih berjalan, bukan jumlah transaksi.
     */
    /** Status yang dianggap Cash Advance masih BERJALAN (belum tuntas). */
    private const ACTIVE_CA_STATUSES = ['PAID', 'SETTLE_CHECKED', 'SETTLE_VERIFIED'];

    /** Kunci grup untuk CR Cash Advance milik Cost Center (bukan PO). */
    private const ACA_NON_PO_KEY = 'non_po';

    /**
     * Pilihan urutan tabel Active Cash Advance.
     *
     * Urutannya DIPUTUSKAN DI SINI, bukan di DataTable, karena tabel ini pohon
     * tiga tingkat (Manager > Beneficiary > Cost Request) yang barisnya dilipat.
     * Penyortir sisi-klien mengurutkan baris <tr> sebagai daftar datar, sehingga
     * baris anak lepas dari induknya dan subtotal per manager jadi tidak
     * berarti. Di sini tiap tingkat diurutkan di dalam induknya, jadi
     * hierarkinya utuh.
     *
     * Kunci dipakai apa adanya di URL (?aca_sort=), jadi urutan yang dipilih
     * ikut terbawa saat halaman dimuat ulang atau tautannya dibagikan.
     */
    public const ACA_SORTS = [
        'value_desc' => 'Nilai: Besar → Kecil',
        'value_asc'  => 'Nilai: Kecil → Besar',
        'name_asc'   => 'PIC: A → Z',
        'name_desc'  => 'PIC: Z → A',
    ];

    /** Urutan bawaan — sama dengan perilaku sebelum pilihan ini ada. */
    public const ACA_SORT_DEFAULT = 'value_desc';

    /**
     * Ember umur CA, dipakai filter "Aging" di kartu Active Cash Advance.
     *
     * [min, max] dalam hari; max null = tanpa batas atas. Dibuat konstan supaya
     * label di tombol filter dan rentang yang disaring tidak bisa berbeda —
     * kalau keduanya ditulis terpisah, mengubah salah satu diam-diam membuat
     * filter menyaring rentang yang tidak sama dengan yang tertulis.
     */
    public const ACA_AGING_BUCKETS = [
        '1_7'   => ['label' => '1 – 7 hari',   'min' => 0,  'max' => 7],
        '8_14'  => ['label' => '8 – 14 hari',  'min' => 8,  'max' => 14],
        '15_30' => ['label' => '15 – 30 hari', 'min' => 15, 'max' => 30],
        '31_60' => ['label' => '31 – 60 hari', 'min' => 31, 'max' => 60],
        '60_up' => ['label' => '> 60 hari',    'min' => 61, 'max' => null],
    ];

    /**
     * Label tahap khusus tabel ini.
     *
     * BudgetRequest::statusLabelFor() memberi SETTLE_CHECKED label "Checked" dan
     * SETTLE_VERIFIED label "Verified" — sama persis dengan tahap approval
     * CHECKED/VERIFIED. Di layar lain itu tidak membingungkan karena
     * konteksnya sudah jelas (daftarnya memang daftar settlement). Di tabel ini
     * ketiga status tampil berdampingan, jadi "Checked" akan terbaca sebagai
     * tahap approval — padahal uangnya sudah keluar.
     *
     * Peta ini sengaja LOKAL: mengubah statusLabelFor() akan mengganti label di
     * seluruh aplikasi (Todo List, index CR, riwayat log) demi satu tabel.
     */
    private const ACTIVE_CA_STAGE = [
        'PAID'            => ['label' => 'Paid',            'color' => 'success'],
        'SETTLE_CHECKED'  => ['label' => 'Settle Checked',  'color' => 'info'],
        'SETTLE_VERIFIED' => ['label' => 'Settle Verified', 'color' => 'primary'],
    ];

    /**
     * Project id yang boleh dilihat user, atau null kalau role-nya melihat
     * semua. Ekstraksi dari budgetData() supaya aturan scope-nya satu sumber.
     */
    private function scopedProjectIds(): ?array
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        if (in_array($role, self::FULL_SCOPE_ROLES, true)) {
            return null;
        }

        return $user->accessGroup
            ? $user->accessGroup->details->pluck('project_id')->all()
            : [];
    }

    /**
     * Umur CA dalam hari sejak dibayar.
     *
     * Baris di tabel ini semuanya BELUM closed (lihat ACTIVE_CA_STATUSES),
     * jadi ujung penghitungnya hari ini - bukan closed_at. Begitu CR ditutup
     * ia keluar dari tabel, sehingga angka di sini selalu berarti "sudah
     * sekian hari berjalan dan belum tuntas".
     *
     * @return int|null null kalau paid_at belum terisi (data lama sebelum
     *                  kolom ini diisi konsisten) - ditampilkan sebagai "-",
     *                  bukan 0, supaya tidak terbaca sebagai baru dibayar.
     */
    private static function acaAging($paidAt): ?int
    {
        return $paidAt
            ? (int) $paidAt->copy()->startOfDay()->diffInDays(now()->startOfDay(), false)
            : null;
    }

    private function activeCashAdvanceData(Request $request): array
    {
        $role = strtolower(Auth::user()->role->name ?? '');

        /*
        | Urutan dibaca dari URL dan divalidasi terhadap daftar tetap di atas.
        | Nilai asing jatuh ke bawaan, bukan diteruskan ke sortBy() — kunci yang
        | tidak dikenal akan membuat seluruh tabel tampil tanpa urutan yang
        | disengaja, dan itu terbaca sebagai bug, bukan sebagai masukan salah.
        */
        $sort = (string) $request->query('aca_sort', self::ACA_SORT_DEFAULT);
        if (! array_key_exists($sort, self::ACA_SORTS)) {
            $sort = self::ACA_SORT_DEFAULT;
        }

        $urutNama  = in_array($sort, ['name_asc', 'name_desc'], true);
        $menurun   = in_array($sort, ['value_desc', 'name_desc'], true);

        /*
        | SORT_NATURAL|SORT_FLAG_CASE, bukan perbandingan string biasa: nickname
        | penerima dana ditulis campur huruf besar-kecil ("Abe", "DEVASYA"),
        | dan urutan ASCII menaruh SEMUA huruf besar sebelum huruf kecil —
        | "DEVASYA" akan mendahului "Abe" pada A→Z. Itu bukan urutan abjad yang
        | dimaksud siapa pun.
        */
        $bandingNama = SORT_NATURAL | SORT_FLAG_CASE;

        /*
         | "Aktif" = uangnya sudah keluar tapi pertanggungjawabannya belum
         | tuntas. Karena itu bukan cuma PAID: settlement yang sudah dicek Head
         | (SETTLE_CHECKED) maupun sudah diverifikasi Cost Control
         | (SETTLE_VERIFIED) masih kewajiban terbuka — baru CLOSED yang selesai.
         | Dulu hanya PAID yang dihitung, sehingga CA yang sedang dalam proses
         | settlement hilang dari daftar padahal justru itu yang perlu dikejar.
         */
        /*
        | SCOPE: role tanpa akses menyeluruh (mis. Head, Manager) hanya boleh
        | melihat CA dari project yang ada di access group-nya — aturan yang
        | sama dipakai budgetData(). Tanpa ini, membuka kartu ini untuk semua
        | role berarti membocorkan CA seluruh perusahaan.
        */
        $scopedProjectIds = $this->scopedProjectIds();

        $crs = BudgetRequest::whereIn('status', self::ACTIVE_CA_STATUSES)
            ->whereHas('type', fn ($q) => $q->whereRaw('LOWER(name) IN (?, ?, ?)', ['cash advance', 'cash-advance', 'cashadvance']))
            ->when(is_array($scopedProjectIds), fn ($q) => $q->whereHas(
                'budgetPlan',
                fn ($bp) => $bp->whereIn('project_id', $scopedProjectIds ?: [0])
            ))
            ->with(['beneficiary.pic', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'type'])

            ->orderByDesc('amount')
            ->get()
            /*
            | Dua sumber: CR milik PO (dikelompokkan per Manager PO) dan CR milik
            | Cost Center (tanpa PO, tanpa manager) yang dikumpulkan di satu grup
            | "NON PO". CR cost center HANYA tampil untuk role beraksesi
            | menyeluruh: akses group dibatasi per project, jadi untuk role
            | terbatas tidak ada dasar menentukan CR cost center mana yang boleh
            | dilihat (sama dengan whereHas project di atas yang sudah
            | menyingkirkannya).
            */
            ->filter(fn ($cr) => $cr->budgetPlan?->project?->manager
                || ($scopedProjectIds === null && $cr->budgetPlan?->cost_center_id));

        /*
        | FALLBACK TANGGAL PAID. paid_at tidak selalu terisi - CR yang dibayar
        | lewat jalur lama, atau yang sempat bolak-balik Paid -> Realized ->
        | Paid, bisa meninggalkan kolom itu kosong sehingga kolom Tanggal &
        | Aging tampil "-" padahal uangnya jelas sudah keluar (statusnya PAID).
        |
        | Sumber cadangannya jejak status: log 'paid' PERTAMA milik CR itu -
        | yang dicari adalah kapan uang pertama kali keluar, bukan pembayaran
        | kedua atau perjalanan ulang setelah realisasi ditolak.
        */
        $paidLogMap = RequestStatusLog::whereIn('budget_request_id', $crs->pluck('id'))
            ->where(fn ($q) => $q->where('action_type', 'paid')->orWhere('status_to', 'PAID'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('budget_request_id')
            ->map(fn ($logs) => $logs->first()->created_at);

        $crs->each(function ($cr) use ($paidLogMap) {
            if ($cr->paid_at) {
                return;
            }

            /*
            | Urutan cadangan: jejak log 'paid' dulu, baru updated_at.
            |
            | updated_at dipakai sebagai cadangan TERAKHIR karena baris di tabel
            | ini pasti sudah lewat pembayaran (lihat ACTIVE_CA_STATUSES) - jadi
            | perubahan terakhirnya adalah perkiraan terdekat kapan status itu
            | dipasang. CR yang baru masuk Paid hari ini karena itu langsung
            | tampil bertanggal hari ini dengan aging 0, bukan "-" yang terbaca
            | seolah datanya rusak. Perkiraan ini bisa meleset kalau dokumennya
            | disunting lagi setelah dibayar, tapi meleset beberapa hari lebih
            | berguna daripada kolom kosong.
            */
            $cr->paid_at = $paidLogMap[$cr->id] ?? $cr->updated_at;
        });

        /*
        | Pilihan untuk filter PIC di kartu ini. PENYARINGANNYA SENDIRI dikerjakan
        | di browser (lihat acaTerapkanFilter di view) supaya memilih PIC tidak
        | memuat ulang halaman. Yang dihitung ulang di sana bukan cuma baris yang
        | terlihat, tapi juga subtotal PIC, Manager, dan Grand Total — tanpa itu
        | angkanya tetap menghitung baris yang sudah disembunyikan.
        |
        | Daftar di sini sengaja LENGKAP dan tidak ikut menyusut saat filter
        | aktif: semua barisnya memang sudah ter-render, jadi tidak ada yang
        | perlu disembunyikan dari daftar pilihan.
        */
        $acaPicOptions = $crs
            ->map(fn ($cr) => $cr->beneficiary?->pic?->pic ?: ($cr->beneficiary?->nickname ?? null))
            ->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($n) => ['value' => $n, 'label' => $n])
            ->values();

        /*
        | Pilihan filter PO. Nilainya KODE PO (bukan nama) karena itu yang
        | dibawa tiap baris CR lewat data-po dan yang tetap sama walau nama
        | pekerjaannya diubah; labelnya tetap memakai nama supaya bisa dikenali.
        */
        $acaPoOptions = $crs
            ->map(fn ($cr) => $cr->budgetPlan?->project)
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->map(fn ($p) => ['value' => $p->code, 'label' => '[' . $p->code . '] ' . $p->name])
            ->values();

        $groups = $crs->groupBy(fn ($cr) => $cr->budgetPlan->project
                ? $cr->budgetPlan->project->manager_id
                : self::ACA_NON_PO_KEY)
            ->map(function ($managerCrs, $key) use ($urutNama, $menurun, $bandingNama) {
                $isNonPo = $key === self::ACA_NON_PO_KEY;
                $manager = $isNonPo ? null : $managerCrs->first()->budgetPlan->project->manager;

                /*
                | Lapis 2 dikelompokkan per PIC, bukan per Beneficiary. Satu PIC
                | memegang beberapa penerima dana, dan yang dikejar saat CA
                | menggantung adalah ORANGNYA - bukan rekening tujuannya. Dulu
                | pengajuan satu PIC terpecah ke beberapa baris beneficiary
                | sehingga beban sebenarnya per PIC tidak pernah terlihat.
                |
                | Beneficiary yang belum punya PIC tidak dilebur jadi satu grup
                | kosong: kuncinya jatuh ke id beneficiary-nya sendiri supaya
                | pengajuan milik orang berbeda tidak tercampur hanya karena
                | sama-sama belum dipetakan.
                */
                $beneficiaries = $managerCrs
                    ->groupBy(fn ($cr) => $cr->beneficiary?->pic_id
                        ? 'pic:' . $cr->beneficiary->pic_id
                        : 'ben:' . ($cr->beneficiary_id ?? 0))
                    ->map(function ($beneficiaryCrs) use ($urutNama, $menurun, $bandingNama) {
                        $ben = $beneficiaryCrs->first()->beneficiary;

                        // Lapis ketiga: CR-nya sendiri. Tanpa ini, angka per
                        // beneficiary tidak bisa ditelusuri ke pengajuan mana.
                        // Pada urutan abjad, lapis ini ikut diurut berdasarkan
                        // kode CR — semua CR di sini milik penerima dana yang
                        // sama, jadi namanya tidak lagi membedakan apa pun.
                        $items = $beneficiaryCrs
                            ->sortBy(
                                fn ($cr) => $urutNama ? (string) $cr->code : (int) $cr->amount,
                                $urutNama ? $bandingNama : SORT_NUMERIC,
                                $menurun
                            )
                            ->map(fn ($cr) => [
                                'id'           => $cr->id,
                                'code'         => $cr->code,
                                'description'  => $cr->description ?: '-',
                                'amount'       => (int) $cr->amount,
                                'status'       => $cr->status,
                                'status_label' => self::ACTIVE_CA_STAGE[$cr->status]['label'] ?? $cr->status_label,
                                'status_color' => self::ACTIVE_CA_STAGE[$cr->status]['color'] ?? $cr->status_color,
                                /*
                                | "Tanggal" = kapan uangnya BENAR-BENAR keluar
                                | (paid_at), bukan created_at. Umur CA dihitung
                                | sejak pembayaran; tanggal pengajuan dibuat
                                | tidak menanggung apa pun.
                                */
                                'paid_date'    => $cr->paid_at?->format('d/m/Y'),
                                'aging'        => self::acaAging($cr->paid_at),
                                /*
                                | PO Reference = PO tempat Budget Plan CA ini
                                | bergantung. Hanya diisi di lapis CR: satu PIC
                                | bisa memegang CA dari beberapa PO, jadi menulis
                                | satu nama PO di baris grup akan menyesatkan.
                                */
                                'po_code'      => $cr->budgetPlan?->project?->code,
                                // CR cost center: kolom PO Reference memuat nama
                                // cost center-nya (tanpa kode PO / tautan PO).
                                'po_name'      => $cr->budgetPlan?->project?->name
                                    ?? $cr->budgetPlan?->costCenter?->name,
                            ])
                            ->values();

                        /*
                        | Tanggal & aging tingkat grup memakai pembayaran
                        | TERTUA di dalamnya - itu CA yang paling lama
                        | menggantung, dan itulah yang menentukan apakah grup
                        | ini perlu dikejar. Memakai yang terbaru justru
                        | menyembunyikan tunggakan lama di balik pembayaran
                        | baru.
                        */
                        $paidTertua = $beneficiaryCrs->pluck('paid_at')->filter()->min();

                        /*
                        | Halaman Cost Request membuka dirinya pada rentang bulan
                        | berjalan. CA aktif bisa jauh lebih tua dari itu, jadi
                        | tautan dari sini membawa date_from sendiri - kalau
                        | tidak, tab yang terbuka tampil kosong justru untuk
                        | baris yang barusan diklik.
                        */
                        $crTertua = $beneficiaryCrs->pluck('created_at')->filter()->min();

                        return [
                            'beneficiary' => $ben?->pic?->pic ?: ($ben?->nickname ?? '-'),
                            'date_from'   => $crTertua?->format('Y-m-d'),
                            // "Quantity CA" = jumlah DOKUMEN CR milik PIC ini.
                            'qty'         => $beneficiaryCrs->count(),
                            'value'       => (int) $beneficiaryCrs->sum('amount'),
                            'paid_date'   => $paidTertua?->format('d/m/Y'),
                            'aging'       => self::acaAging($paidTertua),
                            'items'       => $items,
                        ];
                    })
                    ->sortBy(
                        fn ($b) => $urutNama ? (string) $b['beneficiary'] : (int) $b['value'],
                        $urutNama ? $bandingNama : SORT_NUMERIC,
                        $menurun
                    )
                    ->values();

                $paidTertuaMgr = $managerCrs->pluck('paid_at')->filter()->min();
                $crTertuaMgr   = $managerCrs->pluck('created_at')->filter()->min();

                return [
                    'manager'        => $isNonPo ? 'NON PO' : strtoupper($manager->username ?? '-'),
                    // Grup NON PO tidak punya manager: tautannya tidak membawa
                    // filter Manager, hanya daftar PIC-nya.
                    'is_non_po'      => $isNonPo,
                    'date_from'      => $crTertuaMgr?->format('Y-m-d'),
                    /*
                    | Daftar PIC di bawah manager ini, dibawa tautan "value" ke
                    | halaman Cost Request supaya chip PIC di sana menyebut
                    | namanya satu per satu - filter Manager saja memang sudah
                    | mempersempit barisnya, tapi tidak memberi tahu siapa saja
                    | yang tercakup tanpa menyisir daftarnya.
                    */
                    'pics'           => $beneficiaries->pluck('beneficiary')
                        ->filter(fn ($n) => $n !== '-')->unique()->values()->implode(','),
                    'beneficiaries'  => $beneficiaries,
                    // Total CR dari seluruh PIC di bawah manager ini.
                    'qty'            => $beneficiaries->sum('qty'),
                    'value'          => $beneficiaries->sum('value'),
                    'paid_date'      => $paidTertuaMgr?->format('d/m/Y'),
                    'aging'          => self::acaAging($paidTertuaMgr),
                ];
            })
            /*
            | Lapis MANAGER sengaja TIDAK ikut diurutkan, selalu menurut nilai
            | terbesar. Yang dicari orang di kartu ini adalah "penerima dana
            | mana yang paling besar / paling awal abjadnya" — manager di sini
            | fungsinya map, bukan data yang dibandingkan. Kalau blok manager
            | ikut berpindah tiap kali urutan diubah, pembacanya harus mencari
            | ulang letak managernya sebelum bisa membandingkan isinya, dan
            | manager yang sedang dibuka tiba-tiba melompat ke tempat lain.
            */
            ->sortByDesc('value')
            ->values();

        return [
            'activeCashAdvance'  => $groups,
            'activeCaGrandQty'   => $groups->sum('qty'),
            /*
            | Dibawa tautan "value" ke halaman Cost Request supaya tab yang
            | terbuka memuat HIMPUNAN YANG SAMA dengan kartu ini - tanpa ini,
            | daftar di sana ikut memuat CR Draft/Approved dan tipe non-CA, dan
            | totalnya tidak akan pernah cocok dengan angka yang barusan diklik.
            */
            'acaPicOptions'      => $acaPicOptions,
            'acaPoOptions'       => $acaPoOptions,
            /*
            | Kode PO baru bisa diklik untuk membuka modul Purchase Order oleh
            | role yang memang berkepentingan atas PO-nya. Role lain tetap
            | MELIHAT kodenya - yang dicabut hanya tautannya, bukan informasinya,
            | karena kode itu konteks yang membuat baris CA ini bisa dibaca.
            */
            'acaCanOpenPo'       => in_array($role, ['superadmin', 'approver', 'finance_manager'], true),
            'acaAgingOptions'    => collect(self::ACA_AGING_BUCKETS)
                ->map(fn ($e, $k) => ['value' => $k, 'label' => $e['label']])->values(),
            'acaLinkStatuses'    => implode(',', self::ACTIVE_CA_STATUSES),
            'acaLinkType'        => $crs->first()?->type?->name,
            'activeCaGrandValue' => $groups->sum('value'),
            // Hanya kunci yang aktif yang dikirim ke view — judul kolomnya
            // sendiri yang jadi tombol urut, jadi view tidak perlu daftar
            // pilihan. ACA_SORTS tetap dipakai di atas sebagai daftar sah.
            'acaSort'            => $sort,
        ];
    }

    /**
     * Data tab "Summary Collection" — piutang & penagihan dari modul Invoice &
     * Cash Receipt. "Piutang" cuma dihitung dari invoice yang sudah SENT ke
     * customer dan belum lunas (SENT/RECEIVED/PARTIALLY_PAID) — sebelum SENT
     * itu masih proses internal, belum jadi tagihan nyata; PAID/CANCELLED
     * sudah tidak lagi jadi piutang.
     */
    /**
     * Parsing filter Collection sekali saja (P-02). Filter bisa multi-pilih —
     * terima array (col_manager[]) maupun string tunggal.
     *
     * @return array{manager: string[], customer: string[], month: string[]}
     */
    private function collectionFilters(Request $request): array
    {
        $toList = fn ($v) => collect(is_array($v) ? $v : ($v === null || $v === '' ? [] : [$v]))
            ->map(fn ($x) => (string) $x)->filter(fn ($x) => $x !== '')->values()->all();

        return [
            'manager'  => $toList($request->query('col_manager')),
            'customer' => $toList($request->query('col_customer')),
            'month'    => $toList($request->query('col_month')),   // daftar "YYYY-MM"
        ];
    }

    private function collectionData(Request $request, ?array $filters = null): array
    {
        $filters ??= $this->collectionFilters($request);

        $today = today();

        // Kandidat piutang: invoice yang sudah dikirim & belum lunas.
        $unpaidStatuses = ['SENT', 'RECEIVED', 'PARTIALLY_PAID'];

        // OVERDUE = kandidat piutang yang sudah lewat actual_due_date (BUKAN
        // due_date cetak) — invoice tanpa received_at (belum ada tanggal
        // terima) tidak pernah dianggap overdue, lihat Invoice::isOverdue().
        $isOverdue = fn ($inv) => $inv->isOverdue();

        // OUTSTANDING = "Unpaid" + "Overdue":
        //  - Unpaid  : sudah dikonfirmasi diterima customer (RECEIVED) atau dibayar
        //              sebagian (PARTIALLY_PAID), belum jatuh tempo;
        //  - Overdue : semua kandidat piutang yang lewat tempo.
        // Invoice yang baru berstatus SENT tapi BELUM jatuh tempo TIDAK dihitung
        // sebagai piutang (belum jadi tagihan terkonfirmasi).
        $isOutstanding = fn ($inv) => in_array($inv->status, ['RECEIVED', 'PARTIALLY_PAID'], true)
            || $isOverdue($inv);

        $allInvoices = Invoice::with(['po.customer', 'po.manager', 'cashReceipts'])
            ->where('status', '!=', 'DRAFT')
            ->get()
            // totalReceived()/remainingBalance() bawaan model query ulang cashReceipts()
            // tiap dipanggil (oke untuk 1 invoice di halaman show, tapi N+1 kalau dipakai
            // di loop semua invoice seperti di sini) — hitung sekali dari relasi yang
            // sudah di-eager-load, simpan sebagai atribut dinamis, dipakai ulang di bawah.
            ->each(function ($inv) {
                $received = Invoice::hitungDiterima(
                    (int) $inv->cashReceipts->sum('amount'),
                    (int) $inv->cashReceipts->sum('withholding_tax'),
                    (int) $inv->withholding_tax,
                );
                $inv->computed_received = $received;
                $inv->computed_balance  = max(0, $inv->captured_total - $received);
            });

        /*
        |--------------------------------------------------------------------------
        | FILTER (Manager & Customer) — opsi diambil dari SELURUH data sebelum
        | difilter, angka KPI/chart dihitung dari data yang sudah difilter.
        |--------------------------------------------------------------------------
        */
        $managerOptions = $allInvoices
            ->map(fn ($inv) => $inv->po?->manager)
            ->filter()
            ->unique('id')
            ->sortBy(fn ($m) => strtolower($m->username))
            ->map(fn ($m) => ['id' => $m->id, 'name' => strtoupper($m->username)])
            ->values();

        $customerOptions = $allInvoices
            ->map(fn ($inv) => $inv->po?->customer)
            ->filter()
            ->unique('id')
            ->sortBy(fn ($c) => strtolower($c->name))
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        // Opsi bulan diambil dari invoice_date SELURUH invoice (terbaru di atas).
        $idMonths = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                     7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $monthOptions = $allInvoices
            ->map(fn ($inv) => $inv->invoice_date?->format('Y-m'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($ym) => [
                'value' => $ym,
                'label' => $idMonths[(int) substr($ym, 5, 2)] . ' ' . substr($ym, 0, 4),
            ]);

        // P-02: filter dipakai bersama dengan PO Summary & Progress Summary.
        $filterManager  = $filters['manager'];
        $filterCustomer = $filters['customer'];
        $filterMonth    = $filters['month'];

        $invoices = $allInvoices
            ->when($filterManager, fn ($c) => $c->filter(fn ($inv) => in_array((string) $inv->po?->manager_id, $filterManager, true)))
            ->when($filterCustomer, fn ($c) => $c->filter(fn ($inv) => in_array((string) $inv->po?->customer_id, $filterCustomer, true)))
            ->when($filterMonth, fn ($c) => $c->filter(fn ($inv) => $inv->invoice_date && in_array($inv->invoice_date->format('Y-m'), $filterMonth, true)))
            ->values();

        $outstanding = $invoices->filter($isOutstanding)->values();
        $overdue     = $invoices->filter($isOverdue)->values();

        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */
        $paidInvoices      = $invoices->where('status', 'PAID');
        $totalInvoiceCount = $invoices->count();
        $totalInvoiceValue = (int) $invoices->sum('captured_total');
        $paidCount         = $paidInvoices->count();
        $paidValue         = (int) $paidInvoices->sum('captured_total');
        $outstandingCount  = $outstanding->count();
        $totalOutstanding  = (int) $outstanding->sum('computed_balance');
        $overdueValue      = (int) $overdue->sum('computed_balance');
        $overdueCount      = $overdue->count();
        // Collection Rate: persentase nilai invoice yang sudah LUNAS (status PAID),
        // konsisten dengan angka di card "Paid" — "tertagih" = nilai invoice PAID.
        // (Dulu "100% - proporsi outstanding" → keliru: invoice terkirim & belum
        // jatuh tempo dianggap tertagih padahal belum ada uang masuk.)
        $collectedValue = $paidValue;
        $collectionRate = $totalInvoiceValue > 0
            ? round(($collectedValue / $totalInvoiceValue) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | KOMPARASI BULAN SEBELUMNYA — hanya kalau tepat 1 bulan invoice dipilih.
        | Filter Manager/Customer yang sama ikut diterapkan; aging tetap vs hari ini.
        |--------------------------------------------------------------------------
        */
        $colCompare = null;
        if (count($filterMonth) === 1) {
            $curYm  = $filterMonth[0];
            $prevYm = \Carbon\Carbon::createFromFormat('Y-m', $curYm)->subMonthNoOverflow()->format('Y-m');

            $prevInvoices = $allInvoices
                ->when($filterManager, fn ($c) => $c->filter(fn ($inv) => in_array((string) $inv->po?->manager_id, $filterManager, true)))
                ->when($filterCustomer, fn ($c) => $c->filter(fn ($inv) => in_array((string) $inv->po?->customer_id, $filterCustomer, true)))
                ->filter(fn ($inv) => $inv->invoice_date && $inv->invoice_date->format('Y-m') === $prevYm)
                ->values();

            $pOut          = $prevInvoices->filter($isOutstanding);
            $pOvd          = $prevInvoices->filter($isOverdue);
            $pPaid         = $prevInvoices->where('status', 'PAID');
            $pCount        = $prevInvoices->count();
            $pValue        = (int) $prevInvoices->sum('captured_total');
            $pPaidCount    = $pPaid->count();
            $pPaidValue    = (int) $pPaid->sum('captured_total');
            $pOutCount     = $pOut->count();
            $pOutstanding  = (int) $pOut->sum('computed_balance');
            $pOverdueValue = (int) $pOvd->sum('computed_balance');
            $pOverdueCount = $pOvd->count();
            $pRate         = $pValue > 0 ? round(($pPaidValue / $pValue) * 100) : 0;

            // % perubahan; null kalau bulan sebelumnya nol (tak bisa dibandingkan).
            $pct = fn ($cur, $prev) => $prev == 0 ? null : round((($cur - $prev) / abs($prev)) * 100, 1);
            // arah + apakah "baik": naik baik utk count/value/rate, buruk utk outstanding/overdue.
            $mk = function ($cur, $prev, bool $upIsGood, string $type, bool $pp = false) use ($pct) {
                $delta = $cur - $prev;
                return [
                    'prev'   => $prev,
                    'type'   => $type,
                    'dir'    => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
                    'good'   => $delta == 0 ? null : (($delta > 0) === $upIsGood),
                    'pct'    => $pp ? null : $pct($cur, $prev),
                    'pp'     => $pp ? ($cur - $prev) : null,
                ];
            };

            $colCompare = [
                'prev_label'    => $idMonths[(int) substr($prevYm, 5, 2)] . ' ' . substr($prevYm, 0, 4),
                'prev_has_data' => $pCount > 0,
                'total_invoice' => ['qty' => $mk($totalInvoiceCount, $pCount, true, 'int'),        'amount' => $mk($totalInvoiceValue, $pValue, true, 'rp')],
                'paid'          => ['qty' => $mk($paidCount, $pPaidCount, true, 'int'),             'amount' => $mk($paidValue, $pPaidValue, true, 'rp')],
                'outstanding'   => ['qty' => $mk($outstandingCount, $pOutCount, false, 'int'),      'amount' => $mk($totalOutstanding, $pOutstanding, false, 'rp')],
                'overdue'       => ['qty' => $mk($overdueCount, $pOverdueCount, false, 'int'),      'amount' => $mk($overdueValue, $pOverdueValue, false, 'rp')],
                'rate'          => $mk($collectionRate, $pRate, true, 'pct', true),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | AGING PIUTANG — bucket berdasarkan keterlambatan dari due_date
        |--------------------------------------------------------------------------
        */
        $agingBuckets = ['1–14 hari' => 0, '15–21 hari' => 0, '22–30 hari' => 0, '> 30 hari' => 0];
        $agingCounts  = ['1–14 hari' => 0, '15–21 hari' => 0, '22–30 hari' => 0, '> 30 hari' => 0];
        foreach ($overdue as $inv) {
            $daysLate = $inv->actual_due_date->diffInDays($today);
            $bucket = match (true) {
                $daysLate <= 14 => '1–14 hari',
                $daysLate <= 21 => '15–21 hari',
                $daysLate <= 30 => '22–30 hari',
                default => '> 30 hari',
            };
            $agingBuckets[$bucket] += $inv->computed_balance;
            $agingCounts[$bucket]++;
        }

        /*
        |--------------------------------------------------------------------------
        | UPCOMING DUE — kumulatif 7/14/30 hari ke depan (belum jatuh tempo)
        |--------------------------------------------------------------------------
        */
        $notYetDue = $outstanding->filter(fn ($inv) => $inv->actual_due_date && $inv->actual_due_date->gte($today));
        $upcomingDue = collect([7, 14, 30])->mapWithKeys(function ($days) use ($notYetDue, $today) {
            $inRange = $notYetDue->filter(fn ($inv) => $inv->actual_due_date->lte($today->copy()->addDays($days)));
            return ["Next {$days} Days" => ['count' => $inRange->count(), 'value' => (int) $inRange->sum('computed_balance')]];
        });

        /*
        |--------------------------------------------------------------------------
        | STATUS INVOICE (nilai) — 3 kelompok: Paid / Not Due / Overdue, jumlahnya
        | sama persis dengan Total Invoice Value (semua invoice non-draft).
        |--------------------------------------------------------------------------
        */
        $statusChart = [
            'Paid'     => $paidValue,
            'Overdue'  => $overdueValue,
            'Not Due'  => max(0, $totalInvoiceValue - $paidValue - $overdueValue),
        ];

        /*
        |--------------------------------------------------------------------------
        | TOP CUSTOMER OVERDUE — customer dengan invoice overdue, diurutkan dari
        | nilai overdue terbesar. "Total Outstanding" mencakup SEMUA piutang
        | customer itu (termasuk yang belum jatuh tempo), "Overdue" cuma bagian
        | yang sudah lewat tempo. Status badge dari umur keterlambatan terlama.
        |--------------------------------------------------------------------------
        */
        // Collection Performance — SATU tabel flat per (Customer, Manager),
        // bukan lagi nested/toggle grouping (lihat buildCollectionFlatRows()).
        $custKey = fn ($inv) => $inv->po?->customer?->name ?? 'Tanpa Customer';
        $mgrKey  = fn ($inv) => strtoupper($inv->po?->manager?->username ?? 'Tidak diketahui');

        $collectionRows = $this->buildCollectionFlatRows($invoices, $custKey, $mgrKey, $isOutstanding, $isOverdue);
        $collectionManagers = $collectionRows->pluck('manager')->unique()->sort()->values();

        /*
        |--------------------------------------------------------------------------
        | DETAIL PER BUCKET — rincian di balik tiap chart (customer, manager, nilai),
        | dikelompokkan per customer+manager, ditampilkan sebagai tabel collapsible.
        |--------------------------------------------------------------------------
        */
        $overdueByBucket = $overdue->groupBy(function ($inv) use ($today) {
            $d = $inv->actual_due_date->diffInDays($today);
            return match (true) {
                $d <= 14 => '1–14 hari',
                $d <= 21 => '15–21 hari',
                $d <= 30 => '22–30 hari',
                default  => '> 30 hari',
            };
        });
        $agingDetail = [];
        foreach (array_keys($agingBuckets) as $b) {
            $agingDetail[$b] = $this->collectionDetailRows($overdueByBucket->get($b) ?? collect(), fn ($inv) => $inv->computed_balance);
        }

        $statusDetail = [
            'Paid'    => $this->collectionDetailRows($invoices->where('status', 'PAID'), fn ($inv) => $inv->captured_total),
            'Not Due' => $this->collectionDetailRows($notYetDue, fn ($inv) => $inv->computed_balance),
            'Overdue' => $this->collectionDetailRows($overdue, fn ($inv) => $inv->computed_balance),
        ];

        $upcomingDetail = collect([7, 14, 30])->mapWithKeys(function ($days) use ($notYetDue, $today) {
            $inRange = $notYetDue->filter(fn ($inv) => $inv->actual_due_date->lte($today->copy()->addDays($days)));
            return ["Next {$days} Days" => $this->collectionDetailRows($inRange, fn ($inv) => $inv->computed_balance)];
        });

        return [
            'colKpiTotalInvoiceCount' => $totalInvoiceCount,
            'colKpiTotalInvoiceValue' => $totalInvoiceValue,
            'colKpiPaidCount'         => $paidCount,
            'colKpiPaidValue'         => $paidValue,
            'colKpiOutstandingCount'  => $outstandingCount,
            'colKpiOutstanding'       => $totalOutstanding,
            'colKpiOverdueValue'      => $overdueValue,
            'colKpiOverdueCount'      => $overdueCount,
            'colKpiCollectionRate'    => $collectionRate,
            'colKpiCollectedValue'    => $collectedValue,
            'colAgingChart'           => $agingBuckets,
            'colAgingCounts'          => $agingCounts,
            'colUpcomingDue'          => $upcomingDue,
            'colStatusChart'          => $statusChart,
            'collectionRows'          => $collectionRows,
            'collectionManagers'      => $collectionManagers,
            'colAgingDetail'          => $agingDetail,
            'colStatusDetail'         => $statusDetail,
            'colUpcomingDetail'       => $upcomingDetail,
            'colManagerOptions'       => $managerOptions,
            'colCustomerOptions'      => $customerOptions,
            'colMonthOptions'         => $monthOptions,
            'colFilterManager'        => $filterManager,
            'colFilterCustomer'       => $filterCustomer,
            'colFilterMonth'          => $filterMonth,
            'colCompare'              => $colCompare,
        ];
    }

    /**
     * PO Summary (Minor 1.4) — grouped Customer -> Manager. VAR (Qty & Value)
     * dihitung di FRONTEND dari 3 metrik dasar yang dikirim di sini (PO/PO
     * Receive/Payment), sesuai formula yang dipilih user di dropdown — supaya
     * ganti formula tidak perlu reload halaman.
     */
    /**
     * Himpunan Project yang lolos filter Collection (P-02) — dipakai bersama
     * oleh PO Summary dan Progress Summary supaya keduanya PASTI berangkat dari
     * dataset yang sama dengan DataTable/Summary Card.
     *
     * Filter Manager & Customer bekerja langsung di kolom Project. Filter Bulan
     * adalah bulan INVOICE, jadi diterapkan sebagai "project yang punya minimal
     * satu invoice pada bulan tersebut" — dan daftar invoice bulan itu ikut
     * dikembalikan supaya angka Payment/Progress tidak menghitung invoice di
     * luar periode terpilih.
     *
     * @return array{projects: \Illuminate\Support\Collection, invoiceIds: ?array}
     */
    private function filteredProjectScope(array $filters): array
    {
        $projects = Project::with(['customer:id,name', 'manager:id,username', 'poNumbers'])
            ->when($filters['customer'], fn ($q) => $q->whereIn('customer_id', $filters['customer']))
            ->when($filters['manager'], fn ($q) => $q->whereIn('manager_id', $filters['manager']))
            ->get()
            ->filter(fn ($p) => $p->customer);

        $invoiceIds = null;

        if ($filters['month']) {
            $monthInvoices = Invoice::select('id', 'po_id', 'invoice_date')
                ->where('status', '!=', 'DRAFT')
                ->whereIn('po_id', $projects->pluck('id'))
                ->get()
                ->filter(fn ($inv) => $inv->invoice_date
                    && in_array($inv->invoice_date->format('Y-m'), $filters['month'], true));

            $invoiceIds = $monthInvoices->pluck('id')->all();
            $poIds      = $monthInvoices->pluck('po_id')->unique()->all();
            $projects   = $projects->filter(fn ($p) => in_array($p->id, $poIds, true));
        }

        return ['projects' => $projects->values(), 'invoiceIds' => $invoiceIds];
    }

    private function poSummaryData(array $filters = ['manager' => [], 'customer' => [], 'month' => []]): array
    {
        $scope    = $this->filteredProjectScope($filters);
        $projects = $scope['projects'];

        // Uang yang BENAR-BENAR diterima per PO (porsi DPP, tanpa PPN) — dihitung
        // massal supaya tidak N+1 seperti Project::paymentTotal() kalau dipanggil
        // per project. Logikanya identik dengan method tersebut.
        $paidDppByProject = $this->paidDppByProject($scope['invoiceIds']);
        $paidPoNumberIds  = $this->paidPoNumberIds($scope['invoiceIds']);

        $buildRow = function ($group) use ($paidDppByProject, $paidPoNumberIds) {
            // Qty = jumlah No. PO (project_po_numbers), BUKAN jumlah project/scope
            // of work. Satu project bisa punya banyak No. PO; Qty mengikuti satuan
            // yang diterbitkan/ditagih. Value tetap dihitung per project (lihat
            // komentar di bawah), jadi Qty & Value di tiap tahap memakai basis
            // yang sama dengan tahapnya, tidak dicampur.
            $poNumbersOf = fn ($projects) => $projects->flatMap(fn ($p) => $p->poNumbers);
            $allPoNumbers = $poNumbersOf($group);

            // Total PO = NILAI KONTRAK (projects.contract) — nilai yang
            // disepakati di kontrak, terlepas dari No. PO-nya sudah terbit atau
            // belum. PO Received = PO Value (projects.value = SUM nominal No.
            // PO), yaitu bagian kontrak yang PO resminya sudah benar-benar
            // diterima. Selisih keduanya = kontrak yang PO-nya masih ditunggu.
            $contractValue = (int) $group->sum(fn ($p) => (int) ($p->contract ?? 0));
            $poValue       = (int) $group->sum(fn ($p) => (int) ($p->value ?? 0));

            // "PO Received" = No. PO yang sudah benar-benar terbit, jadi
            // himpunannya PO yang punya minimal satu No. PO (value > 0) —
            // BUKAN workProgressStatus() seperti sebelumnya, yang sebetulnya
            // mengukur progres BAST/Invoice/Payment sehingga PO yang No. PO-nya
            // sudah ada tapi BAST-nya belum ikut tidak terhitung. Totalnya kini
            // sama persis dengan footer PO Value di halaman Purchase Order.
            $receiveGroup = $group->filter(fn ($p) => (int) ($p->value ?? 0) > 0);

            // Waiting PO = sisa kontrak yang No. PO-nya BELUM terbit, yaitu
            // Total PO − PO Received. Dulu status-based (status = WAITING_PO)
            // dan menjumlahkan kontrak PENUH, jadi tidak pernah rekonsiliasi:
            // PO yang No. PO-nya sudah terbit sebagian ikut dihitung utuh, dan
            // sisa kontrak pada PO berstatus lain (mis. ACTIVE) tidak terhitung
            // sama sekali. Qty = jumlah PO yang masih punya sisa kontrak,
            // supaya Qty dan Value menggambarkan himpunan yang sama.
            $waitingGroup = $group->filter(fn ($p) => (int) ($p->contract ?? 0) > (int) ($p->value ?? 0));

            // Payment = akumulasi penerimaan riil. Dulu dihitung "nilai muka PO dari
            // project yang SEMUA No. PO-nya sudah PAID" — all-or-nothing, sehingga PO
            // yang baru terbayar sebagian dihitung 0 dan Payment Rate jadi
            // menyesatkan. Qty = jumlah PO yang sudah ada penerimaannya, supaya Qty
            // dan Value menggambarkan himpunan yang sama.
            $paymentGroup = $group->filter(fn ($p) => ($paidDppByProject[$p->id] ?? 0) > 0);
            $paymentValue = (int) $paymentGroup->sum(fn ($p) => $paidDppByProject[$p->id] ?? 0);
            $paymentQty   = $allPoNumbers->filter(fn ($pn) => isset($paidPoNumberIds[$pn->id]))->count();
            // Denominator = PO Received (PO Value), bukan nilai kontrak: uang cuma
            // bisa ditagih atas No. PO yang sudah terbit, jadi rate-nya mengukur
            // penerimaan terhadap PO yang benar-benar ada.
            $paymentRate  = $poValue > 0 ? round(($paymentValue / $poValue) * 100) : 0;

            return [
                'po_qty'        => $allPoNumbers->count(),
                'po_value'      => $contractValue,
                'receive_qty'   => $poNumbersOf($receiveGroup)->count(),
                'receive_value' => $poValue,
                'payment_qty'   => $paymentQty,
                'payment_value' => $paymentValue,
                'waiting_qty'   => $waitingGroup->count(),
                // Selisih di level grup (bukan sum per-PO yang di-clamp), supaya
                // Total PO − PO Received = Waiting PO selalu rekonsiliasi di
                // setiap baris & Grand Total. Bisa minus kalau No. PO yang terbit
                // melebihi nilai kontrak (mis. ada PO tambahan) — itu memang
                // sinyal data yang perlu kelihatan, bukan disembunyikan.
                'waiting_value' => $contractValue - $poValue,
                'payment_rate'  => $paymentRate,
            ];
        };

        // PO Summary — FLAT, satu baris per kombinasi (Customer, Manager),
        // sama pola dengan Collection Performance (buildCollectionFlatRows).
        $poRows = $projects
            ->groupBy(fn ($p) => $p->customer->name . '||' . strtoupper($p->manager->username ?? '-'))
            ->map(function ($group) use ($buildRow) {
                $first = $group->first();
                return array_merge([
                    'customer' => $first->customer->name,
                    'manager'  => strtoupper($first->manager->username ?? '-'),
                ], $buildRow($group));
            })
            ->sortBy('customer')
            ->values();

        $poManagers = $poRows->pluck('manager')->unique()->sort()->values();

        return [
            'poRows'     => $poRows,
            'poManagers' => $poManagers,
        ];
    }

    /**
     * MN-10B — PROGRESS SUMMARY: Customer -> PO -> No. PO.
     *
     * Kolomnya ANTREAN (backlog), bukan pencapaian kumulatif. Tiap No. PO
     * jatuh ke SATU kolom saja — tahap tempat ia sedang tertahan:
     *
     *   Waiting PO      : sisa nilai kontrak yang No. PO-nya belum terbit
     *                     (tingkat PO, bukan No. PO — belum ada barisnya)
     *   Waiting BAST    : nominal No. PO yang belum di-BAST
     *   Waiting Invoice : sudah di-BAST, belum ditagihkan
     *   Waiting Paid    : sudah ditagihkan, uangnya belum masuk
     *   Closed          : nilai yang uangnya sudah masuk - tidak mengantre lagi,
     *                     tapi tetap diberi kolom supaya Contract = jumlah
     *                     seluruh kolom di kanannya. Tanpa kolom ini, PO yang
     *                     sudah lunas tampil berkontrak sekian tapi nol di
     *                     semua kolom, dan barisnya terbaca seperti data hilang.
     *
     * Tahapnya DITENTUKAN OLEH STATUS No. PO, bukan diterka dari ada/tidaknya
     * baris invoice. Modul Purchase Order memakai status yang sama persis untuk
     * badge & agregatnya (Project::workProgressStatus(), billingProgressStatus(),
     * paymentProgressStatus(), waitingInvoicePoCount() — yang terakhir literal
     * "No. PO berstatus BAST_SUBMITTED"). Sebelumnya tabel ini menyimpulkan
     * tahap dari kombinasi status + keberadaan invoice, sehingga No. PO yang
     * sudah punya baris invoice tapi statusnya masih BAST_SUBMITTED dihitung
     * "menunggu pembayaran" di sini, padahal halaman Purchase Order menandainya
     * "Waiting Invoice" — dua halaman menyebut tahap berbeda untuk PO yang sama.
     *
     * Versi sebelumnya progresif ("sudah sampai tahap ini"), sehingga satu No.
     * PO terhitung di semua tahap yang sudah dilewatinya. Itu menjawab "sudah
     * sejauh mana", bukan "apa yang macet" — dan yang dikejar orang dari tabel
     * ini adalah yang kedua.
     *
     * Contract = nilai kontrak PO-nya. Ia TIDAK persis sama dengan jumlah empat
     * kolom Waiting: yang sudah tuntas (invoice lunas) tidak lagi mengantre di
     * mana pun, jadi selisihnya justru bagian yang sudah selesai.
     *
     * Tidak ada tabel/kolom baru: semua diturunkan dari relasi existing.
     *
     * Dataset-nya sama persis dengan DataTable & Summary Card (P-02).
     */
    private function progressSummaryData(array $filters = ['manager' => [], 'customer' => [], 'month' => []]): array
    {
        $scope    = $this->filteredProjectScope($filters);
        $projects = $scope['projects'];

        if ($projects->isEmpty()) {
            return ['progressRows' => collect(), 'progressTotals' => $this->emptyProgressTotals()];
        }

        /*
        | Invoice & cash receipt dibaca lagi - tapi sebagai PEMBAGI NILAI, bukan
        | penentu tahap. Tahapnya tetap dari status No. PO (itu yang menyamakan
        | halaman ini dengan modul Purchase Order); angka invoice & penerimaan
        | dipakai untuk memecah nominal No. PO ke beberapa kolom saat baru
        | sebagian ditagih atau dibayar.
        |
        | Diambil SEKALI (bukan per No. PO) supaya tidak N+1.
        */
        $invoicesByPoNumber = Invoice::select('id', 'po_id', 'project_po_number_id', 'status', 'captured_total', 'withholding_tax')
            ->where('status', '!=', 'DRAFT')
            ->whereIn('po_id', $projects->pluck('id'))
            ->when($scope['invoiceIds'] !== null, fn ($q) => $q->whereIn('id', $scope['invoiceIds']))
            ->with('cashReceipts:id,invoice_id,amount,withholding_tax')
            ->get()
            ->groupBy('project_po_number_id');

        $rows = $projects
            ->sortBy(fn ($p) => strtolower($p->customer->name) . '|' . strtolower($p->name ?? ''))
            ->map(function ($project) use ($invoicesByPoNumber) {
                $poNumberRows = collect($project->poNumbers)
                    ->sortBy('po_number')
                    ->map(function ($poNumber) use ($invoicesByPoNumber) {
                        $nominal = (int) $poNumber->nominal;

                        $poInvoices = $invoicesByPoNumber->get($poNumber->id, collect());
                        $invoiced   = (int) $poInvoices->sum('captured_total');
                        $paid       = (int) $poInvoices->sum(fn ($inv) => Invoice::hitungDiterima(
                            (int) $inv->cashReceipts->sum('amount'),
                            (int) $inv->cashReceipts->sum('withholding_tax'),
                            (int) $inv->withholding_tax));

                        /*
                        | NILAI MENGALIR BERTAHAP, tidak lagi pindah utuh.
                        |
                        | Dulu seluruh nominal No. PO duduk di satu kolom sesuai
                        | statusnya. Akibatnya No. PO 50 juta yang baru ditagih 20
                        | juta tetap tampil 50 juta di Waiting BAST dan 0 di kolom
                        | berikutnya — penagihan yang sudah berjalan tidak terlihat
                        | sama sekali. Sekarang tiap kolom memuat SELISIH antara
                        | "sudah sampai tahap ini" dan "sudah lanjut ke tahap
                        | berikutnya":
                        |
                        |   Waiting BAST    = nominal      - nilai BAST
                        |   Waiting Invoice = nilai BAST   - nilai invoice
                        |   Waiting Paid    = nilai invoice - nilai dibayar
                        |   Closed          = nilai dibayar
                        |
                        | Jumlah keempatnya selalu = nominal, jadi Contract tetap
                        | rekonsiliasi. Qty = 1 di setiap kolom yang masih bernilai,
                        | sehingga satu No. PO memang bisa terhitung di dua kolom
                        | ketika ia terbelah — itulah yang dimaksud "qty di BAST
                        | masih 1, sisanya pindah ke kolom berikutnya".
                        */
                        $bastMentah = (int) ($poNumber->nilai_bast
                            ?: (in_array($poNumber->status, ['BAST_SUBMITTED', 'INVOICED', 'PAID'], true) ? $nominal : 0));

                        /*
                        | Dijaga menaik dan tidak melampaui tahap sebelumnya.
                        | Tanpa ini satu kolom bisa negatif dan diam-diam
                        | mengurangi kolom lain di baris yang sama: invoice bisa
                        | terbit mendahului nilai BAST yang tercatat, pembayaran
                        | bisa melebihi invoice karena pembulatan, dan nilai BAST
                        | bisa melebihi nominal kalau ada revisi.
                        */
                        $nilaiBast    = min($nominal, max($bastMentah, $invoiced, $paid));
                        $nilaiInvoice = min($nilaiBast, max($invoiced, $paid));
                        $nilaiPaid    = min($nilaiInvoice, $paid);

                        $waitBastValue    = $nominal - $nilaiBast;
                        $waitInvoiceValue = $nilaiBast - $nilaiInvoice;
                        $waitPaidValue    = $nilaiInvoice - $nilaiPaid;

                        return [
                            'po_number'     => $poNumber->po_number,
                            'status'        => $poNumber->status,
                            'nominal'       => $nominal,

                            'wait_bast_qty'      => $waitBastValue > 0 ? 1 : 0,
                            'wait_bast_value'    => $waitBastValue,

                            'wait_invoice_qty'   => $waitInvoiceValue > 0 ? 1 : 0,
                            'wait_invoice_value' => $waitInvoiceValue,

                            'wait_payment_qty'   => $waitPaidValue > 0 ? 1 : 0,
                            'wait_payment_value' => $waitPaidValue,

                            'closed_qty'         => $nilaiPaid > 0 ? 1 : 0,
                            'closed_value'       => $nilaiPaid,
                        ];
                    })
                    ->values();

                $sum = fn (string $key) => (int) $poNumberRows->sum($key);

                $kontrak = (int) ($project->contract ?? 0);
                /*
                | Bisa negatif kalau No. PO yang terbit melampaui nilai kontrak
                | (mis. ada addendum yang belum tercatat). Dipotong di 0: antrean
                | negatif tidak berarti apa-apa, dan kalau dijumlahkan ia
                | mengurangi antrean PO lain yang nyata.
                */
                $sisaKontrak = max(0, $kontrak - (int) $poNumberRows->sum('nominal'));

                return [
                    'customer'      => $project->customer->name,
                    'manager'       => strtoupper($project->manager->username ?? '-'),
                    'project'       => $project->name ?? '-',
                    'project_code'  => $project->code ?? '-',
                    'contract'      => (int) ($project->contract ?? 0),
                    'po_value'      => (int) ($project->value ?? 0),
                    'po_numbers'    => $poNumberRows,
                    'po_number_qty' => $poNumberRows->count(),

                    /*
                    | Contract & Waiting PO hidup di tingkat PO, bukan No. PO:
                    | yang ditunggu justru No. PO yang BELUM terbit, sehingga
                    | tidak punya baris untuk dihitung. Sisanya = nilai kontrak
                    | dikurangi seluruh No. PO yang sudah terbit.
                    */
                    'contract_qty'  => 1,
                    'contract_value' => $kontrak,
                    'wait_po_qty'   => $sisaKontrak > 0 ? 1 : 0,
                    'wait_po_value' => $sisaKontrak,

                    'wait_bast_qty'      => $sum('wait_bast_qty'),
                    'wait_bast_value'    => $sum('wait_bast_value'),
                    'wait_invoice_qty'   => $sum('wait_invoice_qty'),
                    'wait_invoice_value' => $sum('wait_invoice_value'),
                    'wait_payment_qty'   => $sum('wait_payment_qty'),
                    'wait_payment_value' => $sum('wait_payment_value'),
                    'closed_qty'         => $sum('closed_qty'),
                    'closed_value'       => $sum('closed_value'),
                ];
            })
            ->values();

        // Grouping tampilan: Customer -> PO (project) -> No. PO.
        $grouped = $rows
            ->groupBy('customer')
            ->map(fn ($projectRows, $customer) => [
                'customer'      => $customer,
                'projects'      => $projectRows->values(),
                'po_qty'        => $projectRows->count(),
                'po_number_qty' => (int) $projectRows->sum('po_number_qty'),
                'contract'      => (int) $projectRows->sum('contract'),
                'contract_qty'       => (int) $projectRows->sum('contract_qty'),
                'contract_value'     => (int) $projectRows->sum('contract_value'),
                'wait_po_qty'        => (int) $projectRows->sum('wait_po_qty'),
                'wait_po_value'      => (int) $projectRows->sum('wait_po_value'),
                'wait_bast_qty'      => (int) $projectRows->sum('wait_bast_qty'),
                'wait_bast_value'    => (int) $projectRows->sum('wait_bast_value'),
                'wait_invoice_qty'   => (int) $projectRows->sum('wait_invoice_qty'),
                'wait_invoice_value' => (int) $projectRows->sum('wait_invoice_value'),
                'wait_payment_qty'   => (int) $projectRows->sum('wait_payment_qty'),
                'wait_payment_value' => (int) $projectRows->sum('wait_payment_value'),
                'closed_qty'         => (int) $projectRows->sum('closed_qty'),
                'closed_value'       => (int) $projectRows->sum('closed_value'),
            ])
            ->sortBy('customer')
            ->values();

        $totals = [
            'customer_qty'  => $grouped->count(),
            'po_qty'        => (int) $grouped->sum('po_qty'),
            'po_number_qty' => (int) $grouped->sum('po_number_qty'),
            'contract'      => (int) $grouped->sum('contract'),
            'contract_qty'       => (int) $grouped->sum('contract_qty'),
            'contract_value'     => (int) $grouped->sum('contract_value'),
            'wait_po_qty'        => (int) $grouped->sum('wait_po_qty'),
            'wait_po_value'      => (int) $grouped->sum('wait_po_value'),
            'wait_bast_qty'      => (int) $grouped->sum('wait_bast_qty'),
            'wait_bast_value'    => (int) $grouped->sum('wait_bast_value'),
            'wait_invoice_qty'   => (int) $grouped->sum('wait_invoice_qty'),
            'wait_invoice_value' => (int) $grouped->sum('wait_invoice_value'),
            'wait_payment_qty'   => (int) $grouped->sum('wait_payment_qty'),
            'wait_payment_value' => (int) $grouped->sum('wait_payment_value'),
            'closed_qty'         => (int) $grouped->sum('closed_qty'),
            'closed_value'       => (int) $grouped->sum('closed_value'),
        ];

        return ['progressRows' => $grouped, 'progressTotals' => $totals];
    }

    private function emptyProgressTotals(): array
    {
        return [
            'customer_qty' => 0, 'po_qty' => 0, 'po_number_qty' => 0, 'contract' => 0,
            'contract_qty' => 0, 'contract_value' => 0,
            'wait_po_qty' => 0, 'wait_po_value' => 0,
            'wait_bast_qty' => 0, 'wait_bast_value' => 0,
            'wait_invoice_qty' => 0, 'wait_invoice_value' => 0,
            'wait_payment_qty' => 0, 'wait_payment_value' => 0,
            'closed_qty' => 0, 'closed_value' => 0,
        ];
    }

    /**
     * [project_id => rupiah] — versi massal dari Project::costRealizationTotal()
     * (2 query untuk semua project, bukan 1 query per project). Aturannya sama
     * persis: Cost Request berstatus PAID/CLOSED atas Budget Plan milik PO itu.
     */
    private function costRealizationByProject(): array
    {
        $totalPerPlan = \App\Models\BudgetRequest::selectRaw('budget_plan_id, SUM(amount) as total')
            ->whereIn('status', ['PAID', 'CLOSED'])
            ->whereNotNull('budget_plan_id')
            ->groupBy('budget_plan_id')
            ->pluck('total', 'budget_plan_id');

        $projectPerPlan = \App\Models\BudgetPlan::whereNotNull('project_id')
            ->pluck('project_id', 'id');

        $map = [];
        foreach ($totalPerPlan as $planId => $total) {
            $projectId = $projectPerPlan[$planId] ?? null;
            if ($projectId) {
                $map[$projectId] = ($map[$projectId] ?? 0) + (int) $total;
            }
        }

        return $map;
    }

    /**
     * [project_id => rupiah diterima] — porsi DPP (tanpa PPN), versi massal dari
     * Project::paymentTotal() (2 query untuk semua project, bukan 2 query/project).
     * Aturannya persis sama: invoice PAID dihitung billed_amount penuh; invoice
     * lain dihitung proporsional dari cash receipt yang sudah masuk (cash receipt
     * tercatat termasuk PPN, jadi dikonversi ke porsi DPP).
     */
    private function paidDppByProject(?array $onlyInvoiceIds = null): array
    {
        $receivedByInvoice = CashReceipt::selectRaw('invoice_id, SUM(amount) as total')
            ->when($onlyInvoiceIds !== null, fn ($q) => $q->whereIn('invoice_id', $onlyInvoiceIds))
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id');

        $map = [];

        $invoiceQuery = Invoice::select('id', 'po_id', 'status', 'billed_amount', 'captured_total')
            ->when($onlyInvoiceIds !== null, fn ($q) => $q->whereIn('id', $onlyInvoiceIds));

        foreach ($invoiceQuery->get() as $invoice) {
            if (!$invoice->po_id) {
                continue;
            }

            $received = (int) ($receivedByInvoice[$invoice->id] ?? 0);

            if ($invoice->status === 'PAID') {
                $value = (int) $invoice->billed_amount;
            } else {
                $value = $invoice->captured_total > 0
                    ? (int) round($received * $invoice->billed_amount / $invoice->captured_total)
                    : $received;
            }

            $map[$invoice->po_id] = ($map[$invoice->po_id] ?? 0) + $value;
        }

        return $map;
    }

    /**
     * [project_po_number_id => true] — No. PO yang sudah punya penerimaan (cash
     * receipt > 0, atau invoice-nya berstatus PAID). Dipakai untuk Qty Payment
     * di Summary PO. Invoice lama tanpa project_po_number_id tidak bisa dikaitkan
     * ke No. PO, jadi tidak ikut dihitung di Qty (nilainya tetap ikut di Value).
     */
    private function paidPoNumberIds(?array $onlyInvoiceIds = null): array
    {
        $receivedInvoiceIds = CashReceipt::query()
            ->when($onlyInvoiceIds !== null, fn ($q) => $q->whereIn('invoice_id', $onlyInvoiceIds))
            ->groupBy('invoice_id')
            ->havingRaw('SUM(amount) > 0')
            ->pluck('invoice_id');

        $ids = Invoice::query()
            ->whereNotNull('project_po_number_id')
            ->when($onlyInvoiceIds !== null, fn ($q) => $q->whereIn('id', $onlyInvoiceIds))
            ->where(fn ($q) => $q->where('status', 'PAID')->orWhereIn('id', $receivedInvoiceIds))
            ->pluck('project_po_number_id')
            ->unique();

        return array_fill_keys($ids->map(fn ($v) => (int) $v)->all(), true);
    }

    /**
     * Baris tabel Collection Performance — FLAT, satu baris per kombinasi
     * (Customer, Manager), bukan nested/toggle grouping. Kolom: Total Invoice,
     * Paid, Outstanding, Overdue, Collection Rate, Aging.
     */
    private function buildCollectionFlatRows($invoices, callable $custKeyFn, callable $mgrKeyFn, callable $isOutstanding, callable $isOverdue)
    {
        $metrics = function ($group) use ($isOutstanding, $isOverdue) {
            $os   = $group->filter($isOutstanding);
            $ov   = $group->filter($isOverdue);
            $paid = $group->where('status', 'PAID');

            $totalInvoice   = (int) $group->sum('captured_total');
            $outstanding    = (int) $os->sum('computed_balance');
            $collectionRate = $totalInvoice > 0 ? round((($totalInvoice - $outstanding) / $totalInvoice) * 100) : 0;

            // Aging = rata-rata umur keterlambatan (hari) invoice yang Overdue
            // di grup ini, dihitung dari actual_due_date (bukan due_date cetak).
            // Dihitung manual (bukan Collection::avg(closure)) supaya gampang
            // dilacak kalau ada invoice tanpa actual_due_date yang lolos filter.
            $agingDaysSum = 0;
            $agingDaysN   = 0;
            foreach ($ov as $ovInv) {
                if (!$ovInv->actual_due_date) {
                    continue;
                }
                // abs() supaya tidak tergantung arah/urutan argumen diffInDays —
                // yang dibutuhkan cuma jumlah hari terlambat, bukan tanda +/-.
                $agingDaysSum += abs($ovInv->actual_due_date->diffInDays(now()));
                $agingDaysN++;
            }
            $avgAging = $agingDaysN > 0 ? (int) round($agingDaysSum / $agingDaysN) : 0;

            // Status tindak lanjut berbasis umur keterlambatan rata-rata (aging).
            $agingStatus = match (true) {
                $avgAging <= 0  => null,
                $avgAging <= 14 => 'Follow Up',
                $avgAging <= 21 => 'Priority',
                $avgAging <= 30 => 'Escalation',
                default         => 'Critical',
            };

            return [
                'total_invoice_count' => $group->count(),
                'total_invoice'       => $totalInvoice,
                'paid_count'          => $paid->count(),
                'paid'                => (int) $paid->sum('captured_total'),
                'outstanding_count'   => $os->count(),
                'outstanding'         => $outstanding,
                'overdue_count'       => $ov->count(),
                'overdue'             => (int) $ov->sum('computed_balance'),
                'collection_rate'     => $collectionRate,
                'avg_aging_days'      => $avgAging,
                'aging_status'        => $agingStatus,
            ];
        };

        return collect($invoices)
            ->groupBy(fn ($inv) => $custKeyFn($inv) . '||' . $mgrKeyFn($inv))
            ->map(function ($group) use ($custKeyFn, $mgrKeyFn, $metrics) {
                $first = $group->first();
                return array_merge([
                    'customer' => $custKeyFn($first),
                    'manager'  => $mgrKeyFn($first),
                ], $metrics($group));
            })
            ->sortByDesc('total_invoice')
            ->values();
    }

    /**
     * Rincian invoice dikelompokkan per customer + manager, dipakai untuk tabel
     * detail collapsible di bawah tiap chart Collection.
     */
    private function collectionDetailRows($invoices, callable $amountFn)
    {
        return collect($invoices)
            ->groupBy(fn ($inv) => ($inv->po?->customer?->name ?? 'Tanpa Customer') . '||' . strtoupper($inv->po?->manager?->username ?? '-'))
            ->map(function ($g) use ($amountFn) {
                $first = $g->first();
                return [
                    'customer' => $first->po?->customer?->name ?? 'Tanpa Customer',
                    'manager'  => strtoupper($first->po?->manager?->username ?? '-'),
                    'count'    => $g->count(),
                    'amount'   => (int) $g->sum($amountFn),
                ];
            })
            ->filter(fn ($r) => $r['amount'] != 0)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Ubah { yr: { mo: { label: total, ... } } } jadi { yr: { mo: [{label,value}, ...] } }
     * terurut desc, dibatasi top-N, dipakai untuk tooltip breakdown per bulan.
     */
    private function topBreakdown(array $breakdown, int $limit = 5): array
    {
        $result = [];
        foreach ($breakdown as $y => $months) {
            foreach ($months as $m => $entries) {
                arsort($entries);
                $result[$y][$m] = collect($entries)
                    ->take($limit)
                    ->map(fn ($v, $k) => ['label' => $k, 'value' => $v])
                    ->values()
                    ->all();
            }
        }
        return $result;
    }

    /**
     * Bentuk umum: query dengan select YEAR(..) as yr, MONTH(..) as mo, SUM(..) as total,
     * dikelompokkan jadi { "2026": [jan..des 12 angka], ... }. Tahun berjalan selalu ada
     * meski datanya kosong, supaya dropdown tahun di frontend tidak pernah kosong.
     */
    private function monthlySeriesByYear($query): array
    {
        $raw = $query->groupBy('yr', 'mo')->get();

        $byYear = [];
        foreach ($raw as $row) {
            $byYear[(int) $row->yr][(int) $row->mo] = (int) $row->total;
        }

        $years = array_keys($byYear);
        $years[] = (int) now()->year;
        $years = array_unique($years);
        sort($years);

        $series = [];
        foreach ($years as $y) {
            $series[$y] = [];
            for ($m = 1; $m <= 12; $m++) {
                $series[$y][] = $byYear[$y][$m] ?? 0;
            }
        }

        return $series;
    }
}