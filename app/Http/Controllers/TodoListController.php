<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCrWorklist;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\ProjectPoNumber;
use App\Models\RequestStatusLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TodoListController extends Controller
{
    use BuildsCrWorklist;

    /** Role yang lihat semua PO tanpa dibatasi Access Group — dipakai section reminder Milestone (sama seperti ProjectMonitoringController::isPrivileged()). */
    private const PROJECT_PRIVILEGED_ROLES = ['superadmin', 'admin', 'approver', 'finance', 'finance_manager', 'cost_control', 'treasury', 'collection', 'accounting', 'guest'];

    /**
     * Todo List Request — worklist lintas modul per role: Cost Request,
     * Budgeting, Treasury (Daily Transaction), Invoice & Cash Receipt, dan
     * reminder Milestone (Project Monitoring, yang tidak punya alur approval
     * jadi bukan worklist approve/reject seperti modul lain). Accounting/
     * Collection belum punya modul nyata untuk di-wire — sengaja tetap kosong
     * (lihat blok "Coming Soon" di bawah).
     */
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $data = $this->buildWorklistData($user);
        } catch (\Throwable $e) {
            // Jangan sampai satu baris data yang cacat (mis. relasi yatim —
            // project_id/beneficiary_id menunjuk baris yang sudah terhapus)
            // membuat SELURUH halaman gagal render (pernah kejadian: response
            // HTML terpotong di tengah karena fatal error, semua script di
            // bawahnya jadi tidak pernah terkirim ke browser). Kalau ini
            // terjadi, log detailnya lalu tampilkan halaman kosong + banner
            // error yang jelas, bukan halaman putih/rusak.
            \Illuminate\Support\Facades\Log::error('TodoListController::index gagal membangun worklist', [
                'user_id' => $user->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);
            $data = $this->emptyWorklistData();
            $data['loadError'] = true;
        }

        return view('todo-list.index', $data);
    }

    /**
     * GET /todo-list/fetch — payload ringkas untuk bell "Todo" di navbar
     * (dropdown popup + badge count), reuse logika yang sama persis dengan
     * index() supaya angkanya selalu konsisten dengan halaman Todo List
     * penuh. Item diambil dari union $allTasks (task approval nyata) +
     * $milestoneOverdue (reminder), diurutkan dari yang paling lama
     * menunggu, dibatasi 15 item teratas — badge count tetap dari total
     * asli (bukan dibatasi 15) supaya tidak menyesatkan.
     */
    /**
     * Angka badge lonceng — SATU-SATUNYA yang dipanggil berulang oleh polling.
     *
     * Kenapa tidak menulis query COUNT sendiri: jumlah ini harus SELALU sama
     * dengan isi halaman Todo List. Definisi tiap antrean (gerbang role, scope
     * Access Group, status mana yang dihitung) ada di buildWorklistData().
     * Menyalinnya ke sini sebagai belasan COUNT terpisah berarti dua definisi
     * untuk satu angka, dan cepat atau lambat keduanya berbeda — badge bilang
     * 12, halamannya menampilkan 9.
     *
     * Yang dilakukan justru dua hal lain:
     *
     *   1. Rincian anggaran per CoA dilewati (denganCoa: false). Itu bagian
     *      termahal dan tidak dipakai untuk menghitung apa pun.
     *   2. Hasilnya disimpan sebentar per user. Polling datang dari SETIAP tab
     *      yang terbuka; tanpa ini, enam tab berarti enam kali kerja yang sama
     *      dalam hitungan detik. Umurnya sengaja pendek — badge yang telat
     *      setengah menit tidak merugikan siapa pun, badge yang salah iya.
     */
    public const BELL_CACHE_TTL = 30; // detik

    public function countBell()
    {
        /** @var User $user */
        $user = Auth::user();

        $kunci = 'todo-bell-count:' . $user->id;

        try {
            $jumlah = \Illuminate\Support\Facades\Cache::remember(
                $kunci,
                self::BELL_CACHE_TTL,
                function () use ($user) {
                    $data = $this->buildWorklistData($user, denganCoa: false);

                    return $data['allTasks']->count() + $data['milestoneOverdue']->count();
                }
            );

            return response()->json(['count' => $jumlah]);
        } catch (\Throwable $e) {
            // Sama seperti fetchBell(): badge yang gagal tidak boleh merusak
            // topbar di seluruh halaman.
            \Illuminate\Support\Facades\Log::error('TodoListController::countBell gagal', [
                'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);

            return response()->json(['count' => 0]);
        }
    }

    /**
     * Isi popup lonceng. Dipanggil saat dropdown DIBUKA, bukan tiap polling —
     * badge-nya memakai countBell() yang jauh lebih murah.
     */
    public function fetchBell()
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            // Popup hanya menampilkan judul/status/aging per baris; rincian
            // anggaran per CoA tidak dipakai sama sekali di sini.
            $data = $this->buildWorklistData($user, denganCoa: false);

            $items = $data['allTasks']
                ->map(fn ($item) => $this->bellItemPayload($item))
                ->concat($data['milestoneOverdue']->map(fn ($item) => $this->bellItemPayload($item)))
                ->sortByDesc(fn ($i) => $i['aging_minutes'])
                ->values();

            return response()->json([
                'count' => $items->count(),
                'items' => $items->take(15)->map(fn ($i) => collect($i)->except('aging_minutes'))->values(),
            ]);
        } catch (\Throwable $e) {
            // Sama seperti index() — satu baris cacat tidak boleh membuat
            // bell di navbar rusak di SEMUA halaman lain. Diam-diam kosong,
            // tapi tetap dicatat di log untuk ditelusuri.
            \Illuminate\Support\Facades\Log::error('TodoListController::fetchBell gagal', [
                'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);

            return response()->json(['count' => 0, 'items' => []]);
        }
    }


    /**
     * Antrean "Perlu Tindakan" untuk blok ringkas di Dashboard.
     *
     * Sengaja memanggil buildWorklistData() yang sama dengan halaman Todo List
     * dan bell navbar: gerbang role, scope Access Group, dan definisi tiap
     * antrean tidak diulang di sini — kalau sebuah koleksi terisi, berarti
     * gerbangnya sudah lolos di sana. Method ini hanya memetakan hasilnya
     * menjadi baris siap tampil (label, jumlah, aging tertua, tujuan).
     *
     * Antrean yang kosong tidak dikembalikan: dashboard hanya menampilkan
     * pekerjaan yang benar-benar ada, bukan daftar nol.
     */
    public function actionQueues(User $user): array
    {
        try {
            // Blok antrean Dashboard hanya butuh jumlah & aging tertua per
            // antrean — bukan rincian anggaran per CoA.
            $data = $this->buildWorklistData($user, denganCoa: false);
        } catch (\Throwable $e) {
            // Sama seperti index()/fetchBell(): satu baris data cacat tidak
            // boleh membuat dashboard gagal render seluruhnya.
            \Illuminate\Support\Facades\Log::error('TodoListController::actionQueues gagal', [
                'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);

            return ['queues' => [], 'total' => 0, 'maxAging' => '0 hari', 'completedToday' => 0, 'gagal' => true];
        }

        // MN-03: grup approval kini dua lapis (PO -> Budget Plan -> CR).
        $rata = fn ($grup) => collect($grup)
            ->flatMap(fn ($po) => collect($po['plans'])->flatMap(fn ($g) => $g['items']));

        /*
          Halaman Todo List digerbangi middleware menu:todo-list, sedangkan
          antrean di sini bisa terisi untuk role yang tidak punya akses menu
          itu. Pengecekannya disalin dari CheckMenu (bukan aturan baru) supaya
          baris tanpa akses tampil tanpa tautan, bukan mengirim ke halaman 403.
        */
        $role      = $user->role;
        $bolehTodo = $role && (strtolower($role->name) === 'superadmin' || $role->hasMenu('todo-list'));
        $todoUrl   = $bolehTodo ? route('todo-list.index') : null;
        $antrean   = [];

        /*
          Urutan mengikuti perjalanan dokumen (pengajuan → approval → pembayaran
          → settlement → penutupan), bukan besarnya angka, supaya posisi baris
          tidak berpindah-pindah setiap jumlahnya berubah.
        */
        $peta = [
            ['key' => 'head',            'label' => 'Approval Head',             'icon' => 'fas fa-user-check',       'tone' => 'attention', 'items' => isset($data['sections']['head']) ? $rata($data['sections']['head']['groups']) : collect()],
            ['key' => 'cost_control_checked',    'label' => 'Verifikasi Cost Control',   'icon' => 'fas fa-search-dollar',    'tone' => 'attention', 'items' => isset($data['sections']['cost_control_checked']) ? $rata($data['sections']['cost_control_checked']['groups']) : collect()],
            ['key' => 'ceo',             'label' => 'Approval CEO',              'icon' => 'fas fa-stamp',            'tone' => 'attention', 'items' => isset($data['sections']['ceo']) ? $rata($data['sections']['ceo']['groups']) : collect()],
            ['key' => 'bp-head',         'label' => 'Budgeting — Approval Head', 'icon' => 'fas fa-coins',            'tone' => 'attention', 'items' => $data['budgetingHeadCheck']],
            ['key' => 'bp-verify',       'label' => 'Budgeting — Verifikasi',    'icon' => 'fas fa-coins',            'tone' => 'attention', 'items' => $data['budgetingVerify']],
            ['key' => 'bp-ceo',          'label' => 'Budgeting — Approval CEO',  'icon' => 'fas fa-coins',            'tone' => 'attention', 'items' => $data['budgetingCeoApprove']],
            ['key' => 'bp-rejected',     'label' => 'Budgeting Ditolak',         'icon' => 'fas fa-undo',             'tone' => 'danger',    'items' => $data['budgetingRejected']],
            ['key' => 'reject',          'label' => 'Tindak Lanjut CR Ditolak',  'icon' => 'fas fa-undo',             'tone' => 'danger',    'items' => $data['managerRejected']],
            ['key' => 'batch-create',    'label' => 'Buat Batch Pembayaran',     'icon' => 'fas fa-layer-group',      'tone' => 'progress',  'items' => $data['treasuryBatchCreate']],
            ['key' => 'batch-process',   'label' => 'Proses Batch',              'icon' => 'fas fa-money-check-alt',  'tone' => 'progress',  'items' => $data['treasuryProcess']],
            ['key' => 'batch-pay',       'label' => 'Tandai Batch Paid',         'icon' => 'fas fa-money-bill-wave',  'tone' => 'progress',  'items' => $data['treasuryReadyToPay']],
            ['key' => 'second-payment',  'label' => 'Pembayaran Termin Kedua',   'icon' => 'fas fa-hand-holding-usd', 'tone' => 'progress',  'items' => $data['secondPaymentPending']],
            ['key' => 'settlement',      'label' => 'Kirim Settlement',          'icon' => 'fas fa-receipt',          'tone' => 'progress',  'items' => $data['managerPaid']],
            ['key' => 'settle-check',    'label' => 'Settlement — Head Check',   'icon' => 'fas fa-clipboard-check',  'tone' => 'progress',  'items' => $rata($data['headSettlementCheck'])],
            ['key' => 'settle-verify',   'label' => 'Settlement — Verifikasi',   'icon' => 'fas fa-clipboard-check',  'tone' => 'progress',  'items' => $rata($data['financeSettlementVerify'])],
            ['key' => 'settle-close',    'label' => 'Settlement — Penutupan',    'icon' => 'fas fa-lock',             'tone' => 'progress',  'items' => $rata($data['ceoSettlementClose'])],
            ['key' => 'po-submitted',    'label' => 'PO — Approval Finance',     'icon' => 'fas fa-file-signature',   'tone' => 'attention', 'items' => $data['poSubmitted']],
            ['key' => 'collection',      'label' => 'Menunggu Invoice Ditagih',  'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['collectionWaitingInvoice']],
            ['key' => 'invoice-verify',  'label' => 'Invoice — Verifikasi',      'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['invoiceVerify']],
            ['key' => 'invoice-approve', 'label' => 'Invoice — Approval',        'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['invoiceApprove']],
        ];

        foreach ($peta as $baris) {
            $items = collect($baris['items']);
            if ($items->isEmpty()) {
                continue;
            }

            // Item tertua dipilih dari timestamp mentah, bukan hasil diff, agar
            // tidak bergantung pada arah/konvensi tanda method diff Carbon —
            // sama seperti perhitungan $maxAging di buildWorklistData().
            $tertua = $items->sortBy(fn ($i) => $i->aging_since)->first();

            $antrean[] = [
                'key'   => $baris['key'],
                'label' => $baris['label'],
                'icon'  => $baris['icon'],
                'tone'  => $baris['tone'],
                'count' => $items->count(),
                'aging' => $tertua?->aging_label ?? '-',
                'url'   => $todoUrl ? $todoUrl . '#tl-' . $baris['key'] : null,
            ];
        }

        /*
          Reminder Milestone tidak punya alur approval sehingga tidak ikut
          $totalPending di buildWorklistData(). Tetap ditampilkan karena tetap
          menuntut tindakan, tapi memakai overdue_label, bukan aging_label.
        */
        $milestone = collect($data['milestoneOverdue']);
        if ($milestone->isNotEmpty()) {
            $terlama = $milestone->sortByDesc(fn ($m) => $m->overdue_days)->first();
            $antrean[] = [
                'key'   => 'milestone',
                'label' => 'Milestone Lewat Tenggat',
                'icon'  => 'fas fa-bell',
                'tone'  => 'danger',
                'count' => $milestone->count(),
                'aging' => $terlama?->overdue_label ?? '-',
                'url'   => $todoUrl ? $todoUrl . '#tl-milestone' : null,
            ];
        }

        return [
            'queues'         => $antrean,
            'total'          => $data['totalPending'],
            'maxAging'       => $data['maxAging'],
            'completedToday' => $data['completedToday'],
            'gagal'          => false,
        ];
    }

    /** Struktur data kosong untuk todo-list.index — dipakai fallback saat
     *  buildWorklistData() gagal (lihat index()), supaya view tidak
     *  kehilangan variabel yang dibutuhkannya. */
    private function emptyWorklistData(): array
    {
        return [
            'sections' => [], 'managerRejected' => collect(), 'managerPaid' => collect(),
            'headSettlementCheck' => collect(), 'financeSettlementVerify' => collect(),
            'ceoSettlementClose' => collect(), 'secondPaymentPending' => collect(),
            'budgetingHeadCheck' => collect(), 'budgetingVerify' => collect(),
            'budgetingCeoApprove' => collect(), 'budgetingRejected' => collect(),
            'treasuryBatchCreate' => collect(), 'batchSourceFunds' => collect(),
            'treasuryProcess' => collect(), 'treasuryReadyToPay' => collect(),
            'poSubmitted' => collect(),
            'invoiceVerify' => collect(), 'invoiceApprove' => collect(), 'milestoneOverdue' => collect(),
            'collectionWaitingInvoice' => collect(),
            'comingSoonRoles' => [], 'totalPending' => 0, 'maxAging' => '0 hari', 'completedToday' => 0,
            'hasAnyTask' => false, 'hasAnySection' => false,
            'summaryTable' => ['columns' => [], 'rows' => [], 'columnTotals' => [], 'grandTotal' => 0, 'flat' => []],
            'allTasks' => collect(),
        ];
    }

    /**
     * Bangun judul/url/ikon/warna/aging siap-tampil untuk satu item worklist,
     * dipakai bell dropdown — instanceof karena $allTasks/$milestoneOverdue
     * berisi campuran beberapa jenis model (BudgetRequest, BudgetPlan,
     * DailyTransaction, Invoice, ProjectDetail).
     */
    private function bellItemPayload($item): array
    {
        if ($item instanceof BudgetRequest) {
            return [
                'title'         => $item->code . ' — ' . \Illuminate\Support\Str::limit($item->description, 60),
                'status_label'  => $item->status,
                'status_color'  => $this->statusBadgeColor($item->status),
                'date'          => $item->aging_since->format('d M Y H:i'),
                'aging_label'   => $item->aging_label,
                'aging_minutes' => now()->diffInMinutes($item->aging_since, true),
                'icon'          => 'fa-file-invoice-dollar',
                'color'         => 'warning',
            ];
        }

        if ($item instanceof BudgetPlan) {
            return [
                'title'         => $item->code . ' — ' . \Illuminate\Support\Str::limit($item->description, 60),
                'status_label'  => $item->status,
                'status_color'  => $this->statusBadgeColor($item->status),
                'date'          => $item->aging_since->format('d M Y H:i'),
                'aging_label'   => $item->aging_label,
                'aging_minutes' => now()->diffInMinutes($item->aging_since, true),
                'icon'          => 'fa-coins',
                'color'         => 'info',
            ];
        }

        if ($item instanceof DailyTransaction) {
            return [
                'title'         => 'Batch ' . $item->code,
                'status_label'  => $item->status_label,
                'status_color'  => $this->statusBadgeColor($item->status),
                'date'          => $item->aging_since->format('d M Y H:i'),
                'aging_label'   => $item->aging_label,
                'aging_minutes' => now()->diffInMinutes($item->aging_since, true),
                'icon'          => 'fa-money-check-alt',
                'color'         => 'secondary',
            ];
        }

        if ($item instanceof Invoice) {
            return [
                'title'         => $item->code . ' — ' . ($item->po?->name ?? '-'),
                'status_label'  => $item->status,
                'status_color'  => $this->statusBadgeColor($item->status),
                'date'          => $item->aging_since->format('d M Y H:i'),
                'aging_label'   => $item->aging_label,
                'aging_minutes' => now()->diffInMinutes($item->aging_since, true),
                'icon'          => 'fa-file-invoice',
                'color'         => 'primary',
            ];
        }

        if ($item instanceof Project) {
            return [
                'title'         => $item->code . ' — ' . \Illuminate\Support\Str::limit($item->name, 60),
                'status_label'  => $item->status,
                'status_color'  => $this->statusBadgeColor($item->status),
                'date'          => $item->aging_since->format('d M Y H:i'),
                'aging_label'   => $item->aging_label,
                'aging_minutes' => now()->diffInMinutes($item->aging_since, true),
                'icon'          => 'fa-file-signature',
                'color'         => 'warning',
            ];
        }

        if ($item instanceof ProjectPoNumber) {
            return [
                'title'         => $item->po_number . ' — ' . ($item->project?->name ?? '-'),
                'status_label'  => $item->status_label,
                'status_color'  => $this->statusBadgeColor($item->status),
                'date'          => $item->aging_since->format('d M Y H:i'),
                'aging_label'   => $item->aging_label,
                'aging_minutes' => now()->diffInMinutes($item->aging_since, true),
                'icon'          => 'fa-file-invoice-dollar',
                'color'         => 'purple',
            ];
        }

        // ProjectDetail (reminder Milestone) — pakai overdue_label, bukan aging_label;
        // "date"-nya target selesai (plan_end), bukan sejak kapan berstatus.
        return [
            'title'         => ($item->project?->name ?? '-') . ' — ' . ($item->stage?->name ?? '-'),
            'status_label'  => str_replace('_', ' ', $item->status),
            'status_color'  => $this->statusBadgeColor($item->status),
            'date'          => 'Target ' . $item->plan_end->format('d M Y'),
            'aging_label'   => $item->overdue_label,
            'aging_minutes' => $item->overdue_days * 1440,
            'icon'          => 'fa-bell',
            'color'         => 'danger',
        ];
    }

    /** Warna badge Bootstrap untuk satu kode status — dipakai bell dropdown, satu
     *  map dipakai bersama lintas modul (CR/Budget Plan/Treasury/Invoice/Milestone)
     *  karena kosakata status-nya sebagian besar sama & warnanya konsisten dengan
     *  yang sudah dipakai di masing-masing halaman index-nya. */
    private function statusBadgeColor(string $status): string
    {
        return [
            'DRAFT'            => 'secondary',
            'SUBMITTED'        => 'warning',
            'CHECKED'          => 'info',
            'VERIFIED'         => 'primary',
            'APPROVED'         => 'success',
            'ACTIVE'           => 'success',
            'QUEUED'           => 'warning',
            'PENDING_APPROVER' => 'warning',
            'DONE'             => 'info',
            'PAID'             => 'success',
            'REALIZED'         => 'primary',
            'SETTLE_CHECKED'   => 'info',
            'SETTLE_VERIFIED'  => 'primary',
            'CLOSED'           => 'dark',
            'REJECTED'         => 'danger',
            'CANCELLED'        => 'dark',
            'NOT_STARTED'      => 'secondary',
            'ON_GOING'         => 'warning',
        ][$status] ?? 'secondary';
    }

    /**
     * Semua data yang dibutuhkan halaman Todo List (index) maupun bell
     * dropdown (fetchBell) — dipisah ke sini supaya keduanya selalu
     * konsisten, satu sumber logika.
     */
    /**
     * @param bool $denganCoa  Sertakan rincian anggaran per Chart of Account.
     *                         Halaman Todo List butuh (modal View Budget);
     *                         lonceng navbar & Dashboard tidak — lihat catatan
     *                         di buildStatusGroups().
     */
    private function buildWorklistData(User $user, bool $denganCoa = true): array
    {
        $sections = [];

        // Head — SUBMITTED, grouped per Budget Plan, bulk approve/reject.
        if ($user->hasRole(['superadmin', 'head'])) {
            $sections['head'] = [
                'title'        => 'Menunggu Approval Head',
                'status_label' => 'Submitted',
                'groups'       => $this->buildPoGroups('SUBMITTED', $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // Cost Control — verifikasi CHECKED, grouped per Budget Plan, bulk approve/reject.
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $sections['cost_control_checked'] = [
                'title'        => 'Menunggu Verifikasi Cost Control',
                'status_label' => 'Checked',
                'groups'       => $this->buildPoGroups('CHECKED', $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // CEO / Approver — VERIFIED, grouped per Budget Plan, bulk approve/reject.
        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $sections['ceo'] = [
                'title'        => 'Menunggu Approval CEO',
                'status_label' => 'Verified',
                'groups'       => $this->buildPoGroups('VERIFIED', $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // Manager/Supervisor — Reject Handling (Revise/Cancel) + Submit Realization.
        $managerRejected = collect();
        $managerPaid     = collect();
        $budgetPlanRelations = ['budgetPlan.project.customer', 'budgetPlan.costCenter'];

        if ($user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            $rejectedQuery = BudgetRequest::with(array_merge(['type', 'beneficiary', 'statusLogs'], $budgetPlanRelations))
                ->where('status', 'REJECTED');
            $this->scopeByRole($rejectedQuery);
            $managerRejected = $rejectedQuery->latest()->get()
                ->map(function ($cr) {
                    $cr->rejection_log      = $cr->latestRejectionLog();
                    $cr->budget_plan_label  = $this->budgetPlanLabel($cr->budgetPlan);
                    $cr->customer_label     = $this->customerLabel($cr->budgetPlan);
                    $cr->po_label           = $this->poLabel($cr->budgetPlan);
                    return $cr;
                })
                ->sortBy('aging_since')->values();

            $paidQuery = BudgetRequest::with(array_merge(['type', 'beneficiary', 'statusLogs'], $budgetPlanRelations))
                ->where('status', 'PAID');
            $this->scopeByRole($paidQuery);
            $managerPaid = $paidQuery->latest()->get()
                ->map(function ($cr) {
                    $cr->budget_plan_label = $this->budgetPlanLabel($cr->budgetPlan);
                    $cr->customer_label    = $this->customerLabel($cr->budgetPlan);
                    $cr->po_label          = $this->poLabel($cr->budgetPlan);
                    return $cr;
                })
                ->sortBy('aging_since')->values();
        }

        // Settlement Cash Advance — 3 tahap approval berjenjang, grouped per
        // Budget Plan (pola sama seperti "Verifikasi Cost Control"), dengan kolom
        // Refund = nilai pengajuan (amount) - nilai realisasi (total_realization)
        // dihitung di sini supaya subtotal per grup & grand total tinggal dijumlah
        // di Blade tanpa query ulang. Refund negatif = Settlement melebihi
        // pengajuan (Second Payment otomatis dibuat saat tahap Finance Verify).
        // MN-03: strukturnya kini dua lapis (PO -> Budget Plan -> CR), jadi
        // penelusurannya ikut dua lapis.
        $addRefund = function ($poGroups) {
            foreach ($poGroups as $po) {
                foreach ($po['plans'] as $group) {
                foreach ($group['items'] as $cr) {
                    // Layar approval settlement: yang ditampilkan adalah refund
                    // USULAN (belum settle), sengaja tidak memakai refundAmount()
                    // yang sudah settle-gated (P-01).
                    $refund = $cr->proposedRefundAmount();
                    $cr->proposed_refund           = $refund;
                    $cr->proposed_refund_formatted = number_format($refund, 0, ',', '.');
                }
                }
            }
            return $poGroups;
        };

        // Head — Settlement Check (REALIZED -> SETTLE_CHECKED).
        $headSettlementCheck = collect();
        if ($user->hasRole(['superadmin', 'head'])) {
            $headSettlementCheck = $addRefund($this->buildPoGroups('REALIZED', $denganCoa));
        }

        // Finance/Cost Control — Settlement Verify (SETTLE_CHECKED -> SETTLE_VERIFIED).
        $financeSettlementVerify = collect();
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $financeSettlementVerify = $addRefund($this->buildPoGroups('SETTLE_CHECKED', $denganCoa));
        }

        // Finance Manager — Settlement Close (SETTLE_VERIFIED -> CLOSED). Diblokir
        // di backend (ceoCloseSettlement()) selama masih ada Second Payment
        // PENDING — ditandai di sini supaya Blade bisa tampilkan alasannya.
        $ceoSettlementClose = collect();
        if ($user->hasRole(['superadmin', 'finance_manager'])) {
            $ceoSettlementClose = $addRefund($this->buildPoGroups('SETTLE_VERIFIED', $denganCoa));
            foreach ($ceoSettlementClose as $po) {
                foreach ($po['plans'] as $group) {
                    foreach ($group['items'] as $cr) {
                        $cr->pending_second_payment = $cr->pendingSecondPayment();
                    }
                }
            }
        }

        /*
        | Treasury/Finance — Second Payment yang belum lunas. Bagian ini
        | INFORMATIF saja: pembayarannya dilakukan lewat batch Treasury
        | (Daily Transaction), bukan dengan menandai PAID dari sini. Tombol
        | lama dihapus karena ia melunasi tanpa meninggalkan baris kas,
        | bukti transfer, maupun jejak di riwayat CR.
        |
        | batch_code diisi supaya terlihat mana yang sudah diambil Treasury
        | dan mana yang masih menunggu dimasukkan ke batch.
        */
        $secondPaymentPending = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $secondPaymentPending = BudgetRequest::with([
                    'beneficiary',
                    'secondPayments.dailyTransactionItems.dailyTransaction',
                ])
                ->whereHas('secondPayments', fn ($q) => $q->where('status', 'PENDING'))
                ->get()
                ->map(function ($cr) {
                    $payment = $cr->secondPayments->firstWhere('status', 'PENDING');
                    $cr->pending_payment = $payment;

                    $activeItem = $payment?->dailyTransactionItems
                        ->first(fn ($i) => $i->status === 'INCLUDED'
                            && in_array($i->dailyTransaction?->status, ['PENDING_APPROVER', 'DONE']));

                    $cr->second_payment_batch      = $activeItem?->dailyTransaction;
                    $cr->second_payment_batch_code = $activeItem?->dailyTransaction?->code;

                    return $cr;
                })
                ->sortBy('aging_since')->values();
        }

        // Budgeting — 4 tahap sama seperti Cost Request: Manager submit -> Head
        // check -> Finance verify -> CEO approve (Active). Flat list (bukan
        // grouped+bulk seperti CR) karena BudgetPlanController cuma punya
        // endpoint single-record per tahap.
        $budgetingHeadCheck = collect();
        if ($user->hasRole(['superadmin', 'head'])) {
            $budgetingHeadCheck = BudgetPlan::with(['project.customer', 'costCenter'])
                ->where('status', 'SUBMITTED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        $budgetingVerify = collect();
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            $budgetingVerify = BudgetPlan::with(['project.customer', 'costCenter'])
                ->where('status', 'CHECKED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        $budgetingCeoApprove = collect();
        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $budgetingCeoApprove = BudgetPlan::with(['project.customer', 'costCenter'])
                ->where('status', 'VERIFIED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Manager — Budgeting: Reject Handling (REJECTED -> DRAFT via Revise).
        $budgetingRejected = collect();
        if ($user->hasRole(['superadmin', 'manager'])) {
            $budgetingRejected = BudgetPlan::with(['project.customer', 'costCenter'])
                ->where('status', 'REJECTED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Treasury — Menunggu Proses (PENDING_APPROVER, ditandai "Submitted" di
        // model -> jadi "Approved/Done" setelah diproses CEO) & Siap Dibayar
        // (DONE, tinggal ditandai Paid oleh Treasury). Flat list per batch —
        // DailyTransactionController juga cuma punya endpoint single-record.
        $treasuryProcess = collect();
        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $treasuryProcess = DailyTransaction::with('sourceFund')->withCount('includedItems')
                ->where('status', 'PENDING_APPROVER')
                ->where('type', 'CASH_OUT')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        $treasuryReadyToPay = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $treasuryReadyToPay = DailyTransaction::with('sourceFund')->withCount('includedItems')
                ->where('status', 'DONE')
                ->where('type', 'CASH_OUT')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->approved_at ?? $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        // Treasury — Buat Batch Pembayaran: CR APPROVED (sudah lolos CEO) yang
        // belum masuk batch aktif manapun, tapi TIDAK muncul sebagai task
        // actionable di worklist manapun di atas (hanya kelihatan di Summary
        // Task sebagai angka, lihat buildSummaryTable()) — akibatnya Treasury
        // tidak punya pengingat nyata untuk mulai membuat batch. Query sama
        // persis dengan DailyTransactionController::create() supaya jumlahnya
        // konsisten dengan CR yang benar-benar muncul di form pembuatan batch.
        $treasuryBatchCreate = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $batchCreateQuery = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.costCenter'])
                ->where('status', 'APPROVED')
                ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                    $q->where('status', 'INCLUDED')
                      ->whereHas('dailyTransaction', fn ($q2) =>
                          $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                      )
                );
            $this->scopeByRole($batchCreateQuery);
            $treasuryBatchCreate = $batchCreateQuery->latest()->get()
                ->map(function ($cr) {
                    $cr->budget_plan_label = $this->budgetPlanLabel($cr->budgetPlan);
                    $cr->customer_label    = $this->customerLabel($cr->budgetPlan);
                    $cr->po_label          = $this->poLabel($cr->budgetPlan);
                    return $cr;
                })
                ->sortBy('aging_since')->values();
        }

        /*
         | Todo #5 — batch pembayaran bisa dibuat langsung dari Todo List,
         | tidak perlu pindah ke halaman Treasury. Rekeningnya dibatasi sama
         | persis dengan DailyTransactionController::create(): hanya rekening
         | operasional yang AKTIF. Main Account sengaja TIDAK ikut — itu khusus
         | sumber dana Top Up, dan storeCashOut() memang menolaknya.
         |
         | Daftar ini hanya perlu disiapkan kalau bagian "Buat Batch" memang
         | tampil untuk user ini.
         */
        /*
         | Daftar yang sama, dikelompokkan per PO untuk dirender (lihat
         | groupCrsByPo()). Koleksi datarnya TETAP dipakai untuk jumlah &
         | subtotal section supaya kedua angka itu tidak pernah dihitung dari
         | himpunan yang berbeda dengan yang tampil.
         */
        $treasuryBatchGroups = $this->groupCrsByPo($treasuryBatchCreate);

        $batchSourceFunds = $treasuryBatchCreate->isNotEmpty()
            ? \App\Models\SourceFund::with('bank')
                ->where('status', 'ACTIVE')
                ->operational()
                ->orderBy('name')
                ->get()
            : collect();

        /*
         | Purchase Order — Menunggu Approval Finance (SUBMITTED -> WAITING_PO
         | atau ACTIVE lewat ProjectController::financeApprove(), atau balik ke
         | DRAFT lewat financeRevise()).
         |
         | Definisi "siapa yang menangani PO SUBMITTED" sudah ada di
         | TodoService::build() (dipakai widget Todo di Dashboard): role
         | superadmin/finance/finance_manager, link ke project.index?open=<id>.
         | Role & tautannya disamakan persis dengan itu supaya Dashboard dan
         | halaman Todo List tidak pernah menampilkan daftar yang berbeda.
         |
         | Ini reminder + link-out, bukan approve/reject inline seperti section
         | Cost Request — sama alasannya dengan "Menunggu Invoice Ditagih" di
         | bawah: financeApprove() menuntut pilihan approval_type (Waiting PO
         | vs Active) dan menolak Active kalau No. PO masih kosong, sedangkan
         | financeRevise() mewajibkan catatan. Tidak ada satu pun aksi yang bisa
         | diputuskan tanpa melihat isi PO-nya, jadi tombolnya membuka PO itu
         | sendiri, tidak menyalin form approval-nya ke sini.
         */
        $poSubmitted = collect();
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            $poSubmitted = Project::with(['customer', 'manager'])
                ->withCount('poNumbers')
                ->where('status', 'SUBMITTED')
                ->latest()
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->submitted_at ?? $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Invoice & Cash Receipt — Menunggu Verifikasi (SUBMITTED -> VERIFIED)
        // & Menunggu Approval (VERIFIED -> APPROVED). Kedua tahap milik Finance
        // Manager; lihat catatan di $invoiceApprove.
        $invoiceVerify = collect();
        if ($user->hasRole(['superadmin', 'finance_manager'])) {
            $invoiceVerify = Invoice::with(['po.customer'])
                ->where('status', 'SUBMITTED')
                ->latest()
                ->get()
                ->map(fn ($inv) => $this->withAging($inv, $inv->submitted_at ?? $inv->updated_at))
                ->sortBy('aging_since')->values();
        }

        /*
         | Approval invoice (VERIFIED -> APPROVED) milik FINANCE MANAGER, bukan
         | Approver.
         |
         | Alur yang berlaku memang sudah begitu — lihat catatan di atas route
         | invoice-cash-receipt.verify: "collection submit -> accounting
         | verifikasi -> finance_manager approve". Yang tertinggal hanya
         | gerbang di sini, sehingga tugasnya muncul di Todo List Approver
         | sementara Finance Manager yang seharusnya mengerjakannya tidak
         | pernah melihatnya sama sekali.
         |
         | `guest` ikut lepas: di controller ini guest selalu berpasangan
         | dengan approver sebagai peran pengintip, jadi ia mengikuti ke mana
         | pun tugasnya berada.
         */
        $invoiceApprove = collect();
        if ($user->hasRole(['superadmin', 'finance_manager'])) {
            $invoiceApprove = Invoice::with(['po.customer'])
                ->where('status', 'VERIFIED')
                ->latest()
                ->get()
                ->map(fn ($inv) => $this->withAging($inv, $inv->verified_at ?? $inv->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Collection — Waiting Invoice: No. PO yang BAST-nya sudah di-upload
        // (PARTIAL/BAST_SUBMITTED, sama seperti daftar pilihan No. PO di form
        // "Buat Invoice" — lihat InvoiceCashReceiptController::INVOICEABLE_PO_STATUSES)
        // tapi masih ada sisa nominal yang belum ditagihkan sama sekali. Ini
        // reminder buat Collection segera buat invoice, bukan approval — jadi
        // flat list, link-out ke form Buat Invoice, tanpa aksi approve/reject.
        $collectionWaitingInvoice = collect();
        if ($user->hasRole(['superadmin', 'collection'])) {
            $collectionWaitingInvoice = ProjectPoNumber::with(['project.customer'])
                ->whereIn('status', ['PARTIAL', 'BAST_SUBMITTED'])
                ->latest('bast_submitted_at')
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->bast_submitted_at ?? $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Project Monitoring — TIDAK punya alur approval sama sekali (manager
        // bebas ubah status milestone kapan saja, lihat komentar di
        // ProjectMonitoringController::setMilestoneStatus()). Jadi ini bukan
        // worklist approve/reject seperti section lain, melainkan REMINDER buat
        // Manager (PM Internal) — yang bertanggung jawab update status milestone
        // — bukan Approver/CEO yang sama sekali tidak menyentuh update-nya.
        // Milestone yang masih NOT_STARTED/ON_GOING tapi sudah lewat target
        // selesai (plan_end). Scoping akses PO-nya meniru
        // ProjectMonitoringController::accessibleProjectIds().
        $milestoneOverdue = collect();
        if ($user->hasRole(['superadmin', 'manager'])) {
            $milestoneProjectIds = $user->hasRole(self::PROJECT_PRIVILEGED_ROLES)
                ? null
                : $user->accessibleProjectIds()->all();

            $milestoneOverdue = ProjectDetail::with(['project.customer', 'stage'])
                ->whereIn('status', ['NOT_STARTED', 'ON_GOING'])
                ->whereNotNull('plan_end')
                ->where('plan_end', '<', today())
                ->when(is_array($milestoneProjectIds), fn ($q) => $q->whereHas('project', fn ($p) => $p->whereIn('id', $milestoneProjectIds)))
                ->get()
                ->map(function ($detail) {
                    $overdueDays = (int) $detail->plan_end->diffInDays(today());
                    $detail->overdue_days  = $overdueDays;
                    $detail->overdue_label = $overdueDays . ' hari';
                    return $detail;
                })
                ->sortByDesc('overdue_days')
                ->values();
        }

        // Accounting — belum ada modul nyata yang di-wire. Collection sudah
        // di-wire ke section Waiting Invoice di atas.
        $comingSoonRoles = [];
        if ($user->hasRole(['superadmin', 'accounting'])) $comingSoonRoles[] = 'Accounting';

        // Semua task yang tampil di halaman ini (flat), dipakai untuk kartu
        // ringkasan (Max Aging) & export — union dari seluruh section grouped +
        // flat di atas. Milestone reminder SENGAJA tidak ikut union ini — bukan
        // "task menunggu tindakan" (approval), jadi tidak realistis dibandingkan
        // aging-nya dengan CR/Batch/Invoice yang punya tenggat approval nyata.
        // MN-03: grouping kini dua lapis (PO -> Budget Plan -> CR), jadi
        // perataan ke daftar datar ikut menembus dua lapis. Kalau tidak, kartu
        // "Max Aging" dan ekspor akan kehilangan seluruh task approval.
        $flattenPoGroups = fn ($poGroups) => $poGroups
            ->flatMap(fn ($po) => $po['plans']->flatMap(fn ($g) => $g['items']));

        $allTasks = collect($sections)->flatMap(fn ($s) => $flattenPoGroups($s['groups']))
            ->concat($managerRejected)->concat($managerPaid)
            ->concat($flattenPoGroups($headSettlementCheck))
            ->concat($flattenPoGroups($financeSettlementVerify))
            ->concat($flattenPoGroups($ceoSettlementClose))
            ->concat($secondPaymentPending)
            ->concat($budgetingHeadCheck)->concat($budgetingVerify)
            ->concat($budgetingCeoApprove)->concat($budgetingRejected)
            ->concat($treasuryBatchCreate)
            ->concat($treasuryProcess)->concat($treasuryReadyToPay)
            ->concat($poSubmitted)
            ->concat($invoiceVerify)->concat($invoiceApprove)
            ->concat($collectionWaitingInvoice);

        $totalPending = $allTasks->count();

        // CR yang paling lama berada di status-nya sekarang (aging_since paling
        // awal — BUKAN created_at, supaya CR yang baru saja pindah status tidak
        // ikut dianggap "lama" hanya karena diajukan lama). Diurutkan dari
        // timestamp mentah (bukan hasil diffInMinutes) supaya tidak tergantung
        // arah/konvensi tanda dari method diff Carbon; label-nya diambil dari
        // aging_label (accessor model) yang otomatis turun ke jam/menit kalau
        // belum sampai 1 hari.
        $oldestTask = $allTasks->sortBy(fn ($cr) => $cr->aging_since)->first();
        $maxAging   = $oldestTask ? $oldestTask->aging_label : '0 hari';

        // "Completed Today" — jumlah transisi status CR yang benar-benar dieksekusi
        // user ini hari ini (approve/reject/revise/cancel/close/dst), dari log audit
        // yang sudah ada (request_status_logs) — tanpa perlu tabel/counter baru.
        $completedToday = RequestStatusLog::where('acted_by', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $hasAnyTask = $totalPending > 0 || $milestoneOverdue->isNotEmpty();
        $hasAnySection = !empty($sections) || $managerRejected->isNotEmpty() || $managerPaid->isNotEmpty()
            || $headSettlementCheck->isNotEmpty() || $financeSettlementVerify->isNotEmpty()
            || $ceoSettlementClose->isNotEmpty() || $secondPaymentPending->isNotEmpty()
            || !empty($comingSoonRoles)
            || $budgetingHeadCheck->isNotEmpty() || $budgetingVerify->isNotEmpty()
            || $budgetingCeoApprove->isNotEmpty() || $budgetingRejected->isNotEmpty()
            || $treasuryBatchCreate->isNotEmpty()
            || $treasuryProcess->isNotEmpty() || $treasuryReadyToPay->isNotEmpty()
            || $poSubmitted->isNotEmpty()
            || $invoiceVerify->isNotEmpty() || $invoiceApprove->isNotEmpty() || $milestoneOverdue->isNotEmpty()
            || $collectionWaitingInvoice->isNotEmpty();

        $summaryTable = $this->buildSummaryTable();

        return compact(
            'sections', 'managerRejected', 'managerPaid',
            'headSettlementCheck', 'financeSettlementVerify', 'ceoSettlementClose', 'secondPaymentPending',
            'budgetingHeadCheck', 'budgetingVerify', 'budgetingCeoApprove', 'budgetingRejected',
            'treasuryBatchCreate', 'treasuryBatchGroups', 'batchSourceFunds', 'treasuryProcess', 'treasuryReadyToPay', 'poSubmitted',
            'invoiceVerify', 'invoiceApprove', 'milestoneOverdue', 'collectionWaitingInvoice',
            'comingSoonRoles', 'totalPending', 'maxAging', 'completedToday', 'hasAnyTask', 'hasAnySection',
            'summaryTable', 'allTasks'
        );
    }

    /**
     * Tempel aging_since/aging_days/aging_label sebagai atribut dinamis ke
     * model $row, dihitung dari timestamp $since (fallback ke now() kalau
     * null) — meniru pola BudgetRequest::getAgingLabelAttribute() tapi untuk
     * model yang tidak punya accessor aging bawaan (BudgetPlan, DailyTransaction,
     * Invoice). Dipakai supaya baris section baru bisa ikut union $allTasks
     * (butuh aging_since) & ditampilkan aging_label di Blade, konsisten
     * dengan section Cost Request.
     */
    private function withAging($row, $since)
    {
        $since = $since ?? now();
        $days  = (int) floor($since->diffInDays(now()));

        if ($days >= 1) {
            $label = $days . ' hari';
        } else {
            $hours = (int) floor($since->diffInHours(now()));
            $label = $hours >= 1 ? $hours . ' jam' : (int) floor($since->diffInMinutes(now())) . ' menit';
        }

        $row->aging_since = $since;
        $row->aging_days  = $days;
        $row->aging_label = $label;

        return $row;
    }

    /**
     * Matrix "Summary Task": baris = TAHAP alur kerja (Role + nama tahap),
     * kolom = MODUL. Satu tahap hanya punya angka pada modul tempat ia hidup;
     * sel modul lain sengaja dibiarkan kosong, bukan nol, supaya "tidak
     * berlaku" tidak terbaca sebagai "tidak ada antrean".
     *
     * Tahap dengan Role + nama yang sama pada modul berbeda (mis. "Approval
     * Head" di Budgeting dan Cost Request) DIGABUNG jadi satu baris — di situlah
     * bentuk matrix ini berguna: beban satu peran pada tahap yang sama bisa
     * dibandingkan antar modul dengan menyusuri satu baris.
     *
     * Catatan angka: Cost Request di-scope lewat scopeByRole() (mengikuti
     * hak akses access group user, sama seperti section-section di halaman
     * ini). Modul lain dihitung global karena memang tidak punya konsep
     * access-group per PO pada tahap yang dihitung di sini.
     */
    private function buildSummaryTable(): array
    {
        $crCount = function (string $status): int {
            $query = BudgetRequest::where('status', $status);
            $this->scopeByRole($query);
            return $query->count();
        };

        $modules = ['Purchase Order', 'Budgeting', 'Cost Request', 'Treasury', 'Invoice & Cash Receipt'];

        /*
         | Katalog tahap, ditulis mengikuti urutan alur kerja perusahaan —
         | dari pembuatan dokumen, approval, pembayaran, penagihan, sampai
         | settlement & closing. Urutan baris pada tabel mengikuti urutan
         | kemunculan pertama di daftar ini.
         |
         | [role, tahap, modul, jumlah, url]
         */
        $stages = [
            // ---- Purchase Order
            ['Head',            'Submit (Draft)',          'Purchase Order', Project::where('status', 'DRAFT')->count(),        url('/project?status=DRAFT')],
            ['Finance',         'Approval Finance',        'Purchase Order', Project::where('status', 'SUBMITTED')->count(),    url('/project?status=SUBMITTED')],
            ['Finance',         'Aktivasi PO',             'Purchase Order', Project::where('status', 'WAITING_PO')->count(),   url('/project?status=WAITING_PO')],

            // ---- Budgeting
            ['Manager',         'Submit (Draft)',          'Budgeting',      BudgetPlan::where('status', 'DRAFT')->count(),     url('/budget-plan')],
            ['Head',            'Approval Head',           'Budgeting',      BudgetPlan::where('status', 'SUBMITTED')->count(), url('/budget-plan')],
            ['Cost Control',    'Verifikasi Cost Control', 'Budgeting',      BudgetPlan::where('status', 'CHECKED')->count(),   url('/budget-plan')],
            ['CEO',             'Approval CEO',            'Budgeting',      BudgetPlan::where('status', 'VERIFIED')->count(),  url('/budget-plan')],

            // ---- Cost Request
            ['Manager',         'Submit (Draft)',          'Cost Request',   $crCount('DRAFT'),     url('/budget-request?status=DRAFT')],
            ['Head',            'Approval Head',           'Cost Request',   $crCount('SUBMITTED'), url('/budget-request?status=SUBMITTED')],
            ['Cost Control',    'Verifikasi Cost Control', 'Cost Request',   $crCount('CHECKED'),   url('/budget-request?status=CHECKED')],
            ['CEO',             'Approval CEO',            'Cost Request',   $crCount('VERIFIED'),  url('/budget-request?status=VERIFIED')],

            // ---- Treasury (pembayaran CR yang sudah lolos approval)
            ['Treasury',        'Buat Batch Pembayaran',   'Treasury',       $crCount('APPROVED'),  url('/budget-request?status=APPROVED')],
            ['CEO',             'Approval Batch',          'Treasury',       DailyTransaction::where('status', 'PENDING_APPROVER')->where('type', 'CASH_OUT')->count(), url('/daily-transaction')],
            ['Treasury',        'Tandai Paid',             'Treasury',       DailyTransaction::where('status', 'DONE')->where('type', 'CASH_OUT')->count(),             url('/daily-transaction')],

            // ---- Invoice & Cash Receipt
            ['Collection',      'Buat Invoice',            'Invoice & Cash Receipt', ProjectPoNumber::whereIn('status', ['PARTIAL', 'BAST_SUBMITTED'])->count(), url('/invoice-cash-receipt')],
            ['Finance Manager', 'Verifikasi Invoice',      'Invoice & Cash Receipt', Invoice::where('status', 'SUBMITTED')->count(), url('/invoice-cash-receipt?status=SUBMITTED')],
            ['Finance Manager', 'Approval Invoice',        'Invoice & Cash Receipt', Invoice::where('status', 'VERIFIED')->count(),  url('/invoice-cash-receipt?status=VERIFIED')],
            ['Collection',      'Catat Pembayaran',        'Invoice & Cash Receipt', Invoice::whereIn('status', Invoice::UNPAID_STATUSES)->count(), url('/invoice-cash-receipt?statuses=' . implode(',', Invoice::UNPAID_STATUSES))],

            // ---- Settlement (Cost Request) & Closing
            ['Manager',         'Settlement',              'Cost Request',   $crCount('PAID'),            url('/budget-request?status=PAID')],
            ['Head',            'Settlement Check',        'Cost Request',   $crCount('REALIZED'),        url('/budget-request?status=REALIZED')],
            ['Cost Control',    'Settlement Verify',       'Cost Request',   $crCount('SETTLE_CHECKED'),  url('/budget-request?status=SETTLE_CHECKED')],
            ['Finance Manager', 'Settlement Close',        'Cost Request',   $crCount('SETTLE_VERIFIED'), url('/budget-request?status=SETTLE_VERIFIED')],

            ['Head',            'Submit Closing',          'Purchase Order', Project::where('status', 'PAID')->count(),     url('/project?status=PAID')],
            ['Finance',         'Verifikasi Closing',      'Purchase Order', Project::where('status', 'CLOSING')->count(),  url('/project?status=CLOSING')],
            ['Finance',         'Verifikasi Closing',      'Budgeting',      BudgetPlan::where('status', 'CLOSING')->count(), url('/budget-plan')],
        ];

        $rows = [];
        foreach ($stages as [$role, $label, $module, $count, $url]) {
            $key = $role . '|' . $label;

            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'role'   => $role,
                    'label'  => $label,
                    'cells'  => array_fill_keys($modules, null),
                    'total'  => 0,
                ];
            }

            $rows[$key]['cells'][$module] = ['count' => (int) $count, 'url' => $url];
            $rows[$key]['total'] += (int) $count;
        }

        $rows = array_values($rows);

        $moduleTotals = array_fill_keys($modules, 0);
        foreach ($rows as $row) {
            foreach ($row['cells'] as $module => $cell) {
                if ($cell) $moduleTotals[$module] += $cell['count'];
            }
        }

        return [
            'modules'      => $modules,
            'rows'         => $rows,
            'moduleTotals' => $moduleTotals,
            'grandTotal'   => array_sum($moduleTotals),
        ];
    }

    /**
     * GET /todo-list/export — export Excel dari seluruh task yang tampil untuk
     * user yang login saat ini (union semua section, sama seperti index()).
     */
    public function exportList(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        /*
         | Export mengikuti pencarian/penyaringan yang sedang aktif di halaman:
         | browser mengirim id CR dari baris yang masih terlihat (lihat
         | public/js/tesys-export-filtered.js). Penyaringan dilakukan SETELAH
         | union antar-section dirakit, supaya gerbang peran tiap section tetap
         | berlaku — daftar id dari browser tidak bisa dipakai untuk menarik CR
         | yang memang tidak boleh dilihat user ini.
         */
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        $rows = collect();

        $stageOf = function (BudgetRequest $cr): string {
            return match ($cr->status) {
                'SUBMITTED'        => 'Menunggu Approval Head',
                'CHECKED'          => 'Menunggu Verifikasi Cost Control',
                'VERIFIED'         => 'Menunggu Approval CEO',
                'REJECTED'         => 'Reject Handling',
                'PAID'             => 'Submit Settlement',
                'REALIZED'         => 'Settlement — Head Check',
                'SETTLE_CHECKED'   => 'Settlement — Finance Verify',
                'SETTLE_VERIFIED'  => 'Settlement — Finance Manager Close',
                default            => $cr->status,
            };
        };

        foreach (['SUBMITTED', 'CHECKED', 'VERIFIED'] as $status) {
            $allowed = match ($status) {
                'SUBMITTED' => $user->hasRole(['superadmin', 'head']),
                'CHECKED'   => $user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control']),
                'VERIFIED'  => $user->hasRole(['superadmin', 'approver', 'guest']),
            };
            if (!$allowed) continue;

            $query = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])
                ->where('status', $status);
            $this->scopeByRole($query);
            $rows = $rows->concat($query->get());
        }

        if ($user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            $q1 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'REJECTED');
            $this->scopeByRole($q1);
            $rows = $rows->concat($q1->get());

            $q2 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'PAID');
            $this->scopeByRole($q2);
            $rows = $rows->concat($q2->get());
        }

        if ($user->hasRole(['superadmin', 'head'])) {
            $q3 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'REALIZED');
            $this->scopeByRole($q3);
            $rows = $rows->concat($q3->get());
        }

        if ($user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $q4 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'SETTLE_CHECKED');
            $this->scopeByRole($q4);
            $rows = $rows->concat($q4->get());
        }

        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $q5 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'SETTLE_VERIFIED');
            $this->scopeByRole($q5);
            $rows = $rows->concat($q5->get());
        }

        // Penyaringan dilakukan di sini — setelah seluruh gerbang peran di atas
        // dilewati — sehingga id dari browser hanya bisa MEMPERSEMPIT hasil,
        // tidak pernah memperluasnya.
        if ($ids->isNotEmpty()) {
            $rows = $rows->whereIn('id', $ids)->values();
        }

        $columns = ['Stage', 'CR Number', 'Budget Plan', 'Manager', 'Description', 'Beneficiary', 'Amount', 'Status', 'Aging'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Todo List');
        $sheet->fromArray($columns, null, 'A1');
        foreach (range('A', 'I') as $col) {
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($rows as $cr) {
            $sheet->fromArray([
                $stageOf($cr),
                $cr->code,
                $this->budgetPlanLabel($cr->budgetPlan),
                $cr->pm_internal_name,
                $cr->description ?? '-',
                $cr->beneficiary?->nickname ?? '-',
                (int) $cr->amount,
                $cr->status,
                $cr->aging_label,
            ], null, 'A' . $row);
            $row++;
        }

        foreach (['A'=>26,'B'=>18,'C'=>36,'D'=>20,'E'=>40,'F'=>20,'G'=>16,'H'=>12,'I'=>12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'todo_list_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Bangun worklist CR grouped per Budget Plan untuk satu status (SUBMITTED/
     * CHECKED/VERIFIED), sama persis dengan pola yang dulu ada di
     * BudgetRequestController::index() (section "Review Cost Request").
     */
    private function buildStatusGroups(string $status, bool $denganCoa = true): \Illuminate\Support\Collection
    {
        $query = BudgetRequest::with([
            'type', 'beneficiary', 'applicant', 'details', 'statusLogs', 'realizations',
            'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.costCenter',
        ])->where('status', $status);
        $this->scopeByRole($query);

        return $query->get()
            ->groupBy(fn ($cr) => $cr->budget_plan_id ?? 0)
            ->map(function ($items) use ($status, $denganCoa) {
                // Aging tertua di atas — baik urutan CR di dalam grup maupun
                // urutan grup itu sendiri (lihat sortBy di akhir method ini).
                $items = $items->sortBy('aging_since')->values();
                $bp = $items->first()->budgetPlan;

                /*
                 | Rincian per Chart of Account adalah bagian TERMAHAL di sini:
                 | ia menelusuri baris anggaran satu per satu beserta
                 | realisasinya, sehingga satu pemanggilan bisa menghasilkan
                 | ribuan query. Yang membutuhkannya hanya HALAMAN Todo List
                 | (modal "View Budget"); lonceng navbar dan blok antrean di
                 | Dashboard cuma perlu tahu ADA BERAPA dan YANG MANA.
                 |
                 | Karena itu rinciannya dibuat opsional, bukan dijadikan query
                 | hitungan terpisah: definisi tiap antrean tetap satu-satunya
                 | di sini, jadi angka di lonceng tidak mungkin berbeda dari isi
                 | halamannya.
                 */
                $coaGroups = ($denganCoa && $bp)
                    ? $this->buildReviewCoaBreakdown($bp, $items, $status)
                    : collect();

                // Merah = kondisi tersendiri yang lebih berat: total keseluruhan Budget
                // Plan ini (semua CoA + semua request yang sedang direview) sudah
                // terlampaui — berlaku untuk semua CR di dalam plan ini. Kuning = NOB
                // yang lebih sempit, baru di level CoA/sub-item tertentu.
                $planNobFull = $coaGroups->sum('remaining') < 0;

                return [
                    'label'       => $this->budgetPlanLabel($bp),
                    'customer'    => $this->customerLabel($bp),
                    'po'          => $this->poLabel($bp),
                    'pm'          => strtoupper($items->first()->pm_internal_name),
                    'po_code'     => $items->first()->po_code,
                    'items'       => $items,
                    'coa_groups'  => $coaGroups,
                    'is_nob_full' => $planNobFull,
                    'cr_nob'      => $this->buildCrNobMap($coaGroups, $planNobFull),
                ];
            })
            ->sortBy(fn ($g) => $g['items']->first()->aging_since)
            ->values();
    }

    /**
     * MN-03 — grouping Todo List per PURCHASE ORDER.
     *
     * Grouping per Budget Plan (buildStatusGroups di atas) tetap dipertahankan
     * sebagai lapis KEDUA, karena rincian anggaran per CoA memang hidup di
     * level Budget Plan — kalau plan digabung paksa ke satu tumpukan PO,
     * rincian itu kehilangan acuannya dan angka sisa anggaran jadi tidak bisa
     * dibaca.
     *
     * Jadi hasilnya dua lapis:
     *
     *     PO  ->  Budget Plan  ->  Cost Request
     *
     * Pengelompokan dikerjakan di SINI (backend), bukan di Blade: subtotal
     * per PO, jumlah CR, dan urutannya ikut terhitung dari himpunan yang sama
     * dengan yang dirender, sehingga tetap konsisten saat disaring atau dicari.
     */
    private function buildPoGroups(string $status, bool $denganCoa = true): \Illuminate\Support\Collection
    {
        return $this->buildStatusGroups($status, $denganCoa)
            // PO yang sama bisa punya beberapa Budget Plan; kunci grupnya
            // memakai label PO supaya CR dari plan berbeda tapi PO sama
            // berkumpul di satu tempat.
            ->groupBy(fn ($g) => $g['po'] ?: '— Tanpa PO —')
            ->map(function ($plans, $poLabel) {
                $items = $plans->flatMap(fn ($g) => $g['items']);

                return [
                    'po'        => $poLabel,
                    'po_code'   => $plans->first()['po_code'] ?? null,
                    'customer'  => $plans->first()['customer'] ?? '-',
                    'pm'        => $plans->first()['pm'] ?? '-',
                    'plans'     => $plans->values(),
                    'qty'       => $items->count(),
                    'amount'    => (int) $items->sum('amount'),
                    // Aging tertua di dalam PO ini — dipakai untuk mengurutkan
                    // PO, supaya yang paling lama menunggu selalu di atas.
                    'oldest'    => $items->min('aging_since'),
                    'is_nob'    => $plans->contains(fn ($g) => $g['is_nob_full']),
                ];
            })
            ->sortBy('oldest')
            ->values();
    }
}
