<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BudgetPlan;
use App\Models\CostCenter;
use Illuminate\Support\Facades\Auth;

/**
 * Helper bersama untuk membangun worklist Cost Request (approval per Budget
 * Plan, breakdown Chart of Account, NOB coloring) & scoping akses berbasis
 * role/access group. Dipakai oleh BudgetRequestController (create/index CR
 * milik sendiri) DAN TodoListController (worklist approval lintas role) agar
 * tidak duplikasi logika.
 */
trait BuildsCrWorklist
{
    /** Role yang lihat SEMUA overhead tanpa access group */
    private const OVERHEAD_PRIVILEGED_ROLES = ['superadmin', 'approver', 'finance_manager', 'guest'];

    /** Role yang boleh melihat CR PAYROLL (rahasia) */
    private const PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager', 'guest'];

    /**
     * Batasi $query BudgetRequest sesuai hak akses role/access group user
     * yang login. Role privileged (termasuk 'cost_control', yang mengambil
     * alih step verifikasi Finance) melihat semua CR tanpa batasan.
     */
    private function scopeByRole($query): void
    {
        $user = Auth::user();
        // effectiveRole() (bukan $user->role langsung) supaya scoping ikut
        // "View As" saat superadmin mencoba UI sebagai role lain.
        $role = strtolower($user->effectiveRole()?->name ?? '');

        // Role istimewa melihat semuanya tanpa dibatasi access group (PO) —
        // Treasury/Cost Control/Collection/Accounting butuh lihat semua CR
        // lintas PO sama seperti Approver/Finance, bukan cuma yang ada di
        // access group mereka.
        if (in_array($role, [
            'superadmin', 'approver', 'admin', 'finance', 'finance_manager', 'cost_control',
            'treasury', 'collection', 'accounting', 'guest',
        ])) return;

        $canOverhead = $this->canSeeOverhead();
        $canPayroll  = $this->canSeePayroll();

        $overheadPrivileged = $this->overheadPrivileged();
        $overheadCcIds      = $this->accessibleOverheadCostCenterIds();

        $projectIds = $user->accessibleProjectIds();

        /*
        | Tiga jalur visibilitas, digabung OR:
        |   1) CR proyek   -> access group (atau CR milik sendiri bila tanpa group)
        |   2) CR overhead -> privileged lihat semua; lainnya per access group cost center
        |   3) CR payroll  -> berdasarkan role (rahasia)
        | CR yang tidak masuk salah satu jalur TIDAK terlihat.
        */
        $query->where(function ($q) use (
            $user, $projectIds, $canOverhead, $canPayroll, $overheadPrivileged, $overheadCcIds
        ) {

            // 1) CR yang bersumber dari budget PROYEK
            $q->where(function ($qq) use ($user, $projectIds) {
                if ($projectIds->isNotEmpty()) {
                    $qq->whereHas('budgetPlan.project', fn ($p) => $p->whereIn('id', $projectIds));
                } else {
                    // Tanpa access group project: hanya CR miliknya sendiri
                    $qq->where('applicant_id', $user->id)
                       ->whereHas('budgetPlan', fn ($b) => $b->whereNotNull('project_id'));
                }
            });

            // 2) CR OVERHEAD (dan tipe cost center non-payroll lain — Tax/Asset/
            // Liability/Capital/Adjustment — yang diperlakukan sama seperti Overhead)
            if ($overheadPrivileged) {
                // Privileged: semua tipe overhead-like
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) => $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES));
            } elseif ($overheadCcIds->isNotEmpty()) {
                // Lainnya: hanya cost center overhead-like di access group
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) =>
                    $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->whereIn('id', $overheadCcIds)
                );
            }

            // 3) CR PAYROLL
            if ($canPayroll) {
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });
    }

    /** Role yang lihat SEMUA overhead tanpa access group */
    private function overheadPrivileged(): bool
    {
        return in_array(strtolower(Auth::user()->effectiveRole()?->name ?? ''), self::OVERHEAD_PRIVILEGED_ROLES);
    }

    /** Apakah user boleh melihat overhead sama sekali (privileged ATAU punya cost center di access group) */
    private function canSeeOverhead(): bool
    {
        if ($this->overheadPrivileged()) return true;

        return $this->accessibleOverheadCostCenterIds()->isNotEmpty();
    }

    /** ID cost center overhead yang boleh diakses user (kosong = tak ada) */
    private function accessibleOverheadCostCenterIds(): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        return $user->accessGroup ? $user->accessGroup->costCenterIds() : collect();
    }

    private function canSeePayroll(): bool
    {
        return in_array(strtolower(Auth::user()->effectiveRole()?->name ?? ''), self::PAYROLL_ROLES);
    }

    /**
     * Label grup worklist: "{PO Code} - {deskripsi}" untuk budget proyek (PO
     * jadi acuan grouping, bukan nama customer), atau "{Cost Center} -
     * {deskripsi}" untuk budget overhead/payroll (tidak ada konsep PO di sana).
     */
    private function budgetPlanLabel(?BudgetPlan $bp): string
    {
        if (!$bp) return '— Tanpa Budget Plan —';

        // ?-> (bukan cuma ??) karena project_id/cost_center_id bisa menunjuk
        // baris yang sudah terhapus (data yatim) — id-nya tidak null, tapi
        // relasinya null.
        $prefix = $bp->isProjectBudget()
            ? ($bp->project?->code ?? null)
            : ($bp->costCenter?->name ?? null);

        return $prefix ? $prefix . ' - ' . $bp->description : $bp->description;
    }

    /** Nama Customer (budget proyek) atau Cost Center (budget overhead/payroll). */
    private function customerLabel(?BudgetPlan $bp): string
    {
        if (!$bp) return '-';

        return $bp->isProjectBudget()
            ? ($bp->project?->customer?->name ?? '-')
            : ($bp->costCenter?->name ?? '-');
    }

    /**
     * Label PO: "[0000] SCOPE OF WORK" — dipakai filter "PO" di Todo List.
     * Cuma berlaku untuk budget proyek (ada konsep PO); budget overhead/
     * payroll/cost center tidak punya PO sama sekali, jadi baliknya string
     * kosong (baris itu otomatis tidak ikut opsi filter PO).
     */
    private function poLabel(?BudgetPlan $bp): string
    {
        if (!$bp || !$bp->isProjectBudget() || !$bp->project) return '';

        return '[' . $bp->project->code . '] ' . $bp->project->name;
    }

    /**
     * Kelompokkan koleksi Cost Request apa pun menjadi dua lapis
     * PO -> Budget Plan -> CR, bentuknya sama persis dengan buildPoGroups()
     * di TodoListController (kunci: po, po_code, customer, pm, qty, amount,
     * plans[{label, pm, po_code, items}]) — tapi TANPA rincian Chart of
     * Account, karena pemakainya (daftar "Buat Batch Pembayaran") tidak punya
     * tombol View Budget dan breakdown CoA adalah bagian termahal di modul ini.
     *
     * Dipakai supaya daftar yang sumber query-nya berbeda (mis. CR APPROVED
     * yang belum masuk batch) tetap tampil dengan pengelompokan yang sama
     * dengan worklist approval, tanpa menyalin logika pengelompokannya.
     */
    private function groupCrsByPo($crs): \Illuminate\Support\Collection
    {
        return collect($crs)
            ->groupBy(fn ($cr) => $this->poLabel($cr->budgetPlan) ?: '— Tanpa PO —')
            ->map(function ($crsPerPo, $poLabel) {
                $plans = $crsPerPo
                    ->groupBy(fn ($cr) => $cr->budget_plan_id ?? 0)
                    ->map(function ($items) {
                        $items = $items->sortBy('aging_since')->values();
                        $bp    = $items->first()->budgetPlan;

                        return [
                            'label'   => $this->budgetPlanLabel($bp),
                            'pm'      => strtoupper($items->first()->pm_internal_name),
                            'po_code' => $items->first()->po_code,
                            'items'   => $items,
                        ];
                    })
                    ->sortBy(fn ($g) => $g['items']->first()->aging_since)
                    ->values();

                $items = $plans->flatMap(fn ($g) => $g['items']);

                return [
                    'po'       => $poLabel,
                    'po_code'  => $plans->first()['po_code'] ?? null,
                    'customer' => $this->customerLabel($items->first()->budgetPlan),
                    'pm'       => $plans->first()['pm'] ?? '-',
                    'plans'    => $plans,
                    'qty'      => $items->count(),
                    'amount'   => (int) $items->sum('amount'),
                ];
            })
            ->sortBy(fn ($g) => $g['plans']->first()['items']->first()->aging_since)
            ->values();
    }

    /*
     | Cache baris CR per sub-item budget untuk satu request. Todo List membangun
     | breakdown untuk puluhan Budget Plan; tanpa cache, tiap plan = 2 query +
     | eager load (±10 query/plan). preloadCoaRows() memuat semuanya sekaligus,
     | coaRowsFor() lalu cukup mengambil dari sini.
     */
    private array $coaCoveredDetailIds = [];
    private ?\Illuminate\Support\Collection $coaPipelineCache = null;
    private ?\Illuminate\Support\Collection $coaDisbursedCache = null;

    /** Muat baris CR untuk semua Budget Plan di $bps dalam satu putaran. */
    private function preloadCoaRows($bps): void
    {
        $bps = new \Illuminate\Database\Eloquent\Collection(collect($bps)->filter()->unique('id')->values()->all());
        if ($bps->isEmpty()) {
            return;
        }

        $bps->loadMissing('details.account');

        $detailIds = $bps->flatMap(fn ($bp) => $bp->details->pluck('id'))
            ->filter()
            ->reject(fn ($id) => isset($this->coaCoveredDetailIds[$id]))
            ->unique()->values();
        if ($detailIds->isEmpty()) {
            return;
        }

        [$pipeline, $disbursed] = $this->queryCoaRows($detailIds);

        // Dikelompokkan per budget_detail_id, dan id detail unik lintas plan,
        // jadi cache dari beberapa putaran aman digabung.
        $this->coaPipelineCache  = ($this->coaPipelineCache ?? collect())->union($pipeline);
        $this->coaDisbursedCache = ($this->coaDisbursedCache ?? collect())->union($disbursed);
        foreach ($detailIds as $id) {
            $this->coaCoveredDetailIds[$id] = true;
        }
    }

    /** [pipelineByDetailId, disbursedByDetailId] — dari cache bila sudah dimuat. */
    private function coaRowsFor(\Illuminate\Support\Collection $detailIds): array
    {
        $tercakup = $detailIds->every(fn ($id) => isset($this->coaCoveredDetailIds[$id]));

        if ($tercakup && $this->coaPipelineCache !== null) {
            return [$this->coaPipelineCache, $this->coaDisbursedCache];
        }

        return $this->queryCoaRows($detailIds);
    }

    /**
     * Status CR yang barisnya dibaca untuk kolom Used / Expense Claim /
     * Approved (leg kedua). DISBURSED ditambah:
     *   - QUEUED          : sudah diproses Treasury (masuk batch) -> Used.
     *   - SETTLE_APPROVED : expense claim masih direview Approver.
     *   - SECOND_APPROVED : expense claim sudah di-approve, belum dibatch.
     * Dua status settlement terakhir dulu tidak ada di DISBURSED, sehingga
     * CR yang sampai ke tahap itu hilang sama sekali dari hitungan.
     */
    private const COA_SETTLED_ROW_STATUSES = [
        'QUEUED', 'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED',
        'SETTLE_APPROVED', 'SECOND_APPROVED', 'CLOSED',
    ];

    /** Status settlement yang expense claim-nya masih direview (belum Second Approved). */
    private const COA_CLAIM_REVIEW_STATUSES = ['REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'SETTLE_APPROVED'];

    /**
     * Bagi satu baris CR yang sudah cair ke kolom modal. Mengembalikan
     * [used, expenseClaim, approved] — ketiganya saling eksklusif sehingga
     * Variance = Budget - Used - Request - Expense Claim - Approved tidak
     * pernah menghitung satu rupiah dua kali.
     *
     *   base  = nilai yang diajukan & dicairkan pada baris ini
     *   real  = nilai settlement baris ini (null kalau belum settle)
     *   lebih = bagian settlement di atas base (expense claim)
     *
     * "Baris" di sini adalah unit BudgetRequest::costUnits() — per (item
     * asal, Sub COA efektif) sejak Settlement Custom COA (25 Okt 2026). Untuk
     * data satu baris settlement per item, unitnya = item pengajuan itu
     * sendiri, jadi hasilnya sama persis dengan perhitungan per item dulu.
     * Baris settlement yang dipindah/ditambahkan ke sub-item ini ber-base 0,
     * sehingga seluruh nilainya terbaca sebagai kelebihan di sub-item tujuan.
     *
     * @param  array<string, mixed>  $row  unit costUnits() + 'budget_request'
     */
    private function coaSettledSplit(array $row, callable $isClaimLeg): array
    {
        $status = $row['budget_request']->status;
        $base   = (int) $row['base'];
        $real   = $row['real'];
        $lebih  = $real === null ? 0 : max(0, (int) $real - $base);
        $dalam  = $real === null ? $base : min((int) $real, $base);

        /*
         | Expense Claim adalah selisih di tingkat CR (total settlement -
         | total pengajuan), BUKAN jumlah kelebihan tiap baris.
         |
         | Satu CR biasanya berisi baris yang melebihi pagu DAN baris yang
         | hemat; yang ditagihkan ke perusahaan hanya selisih bersihnya.
         | Menjumlahkan kelebihan per baris mengabaikan baris yang hemat,
         | sehingga modal View Budget menampilkan angka lebih besar daripada
         | kolom Expense Claim di daftar worklist untuk CR yang sama.
         |
         | Kelebihan tiap baris karena itu diperkecil sebanding (lihat
         | crClaimFactor()): jumlah seluruh bagiannya kembali sama dengan
         | expense claim CR, tapi tetap menempel pada baris yang memang
         | menyebabkannya.
         */
        $tahapKlaim = in_array($status, self::COA_CLAIM_REVIEW_STATUSES, true)
            || $status === 'SECOND_APPROVED';

        // Faktornya memuat relasi CR, jadi hanya dihitung untuk baris yang
        // memang memakainya — CR PAID/QUEUED/CLOSED tidak perlu.
        $bagianKlaim = ($lebih > 0 && $tahapKlaim)
            ? (int) round($lebih * $this->crClaimFactor($row['budget_request']))
            : 0;

        return match (true) {
            // Batch Treasury: leg pertama (pencairan awal) atau leg kedua (expense claim).
            $status === 'QUEUED'  => [$isClaimLeg($row['budget_request']) ? $dalam + $lebih : $base, 0, 0],
            $status === 'PAID'    => [$base, 0, 0],
            // Settlement berjalan: kelebihannya adalah pengajuan ke-2 yang sedang direview.
            in_array($status, self::COA_CLAIM_REVIEW_STATUSES, true) => [$dalam + $lebih - $bagianKlaim, $bagianKlaim, 0],
            // Expense claim sudah di-approve, belum diproses ke Treasury.
            $status === 'SECOND_APPROVED' => [$dalam + $lebih - $bagianKlaim, 0, $bagianKlaim],
            // CLOSED: seluruh settlement (termasuk kelebihan yang sudah dibayar).
            default => [(int) ($real ?? $base), 0, 0],
        };
    }

    /** Memo faktor klaim per CR — satu CR dipakai berkali-kali per halaman. */
    private array $crClaimFactorMemo = [];

    /**
     * Pembagi untuk menurunkan kelebihan per baris menjadi bagian dari Expense
     * Claim CR: expense_claim CR / total kelebihan seluruh barisnya.
     *
     * Nilainya 0 kalau CR tidak punya kelebihan sama sekali, dan dibatasi 1
     * supaya tidak pernah membesarkan angka (bisa terjadi kalau header amount
     * dan jumlah baris tidak sinkron pada data lama).
     */
    private function crClaimFactor(\App\Models\BudgetRequest $cr): float
    {
        if (array_key_exists($cr->id, $this->crClaimFactorMemo)) {
            return $this->crClaimFactorMemo[$cr->id];
        }

        $cr->loadMissing(['type', 'details', 'realizations']);

        // Kelebihan per unit (item asal x Sub COA efektif) — rumus sama dengan
        // coaSettledSplit(); untuk data satu baris per item = kelebihan per item.
        $kelebihanBaris = $cr->costUnits()
            ->sum(fn ($u) => $u['real'] === null ? 0 : max(0, (int) $u['real'] - (int) $u['base']));

        return $this->crClaimFactorMemo[$cr->id] = $kelebihanBaris > 0
            ? min(1.0, $cr->expense_claim / $kelebihanBaris)
            : 0.0;
    }

    private function queryCoaRows(\Illuminate\Support\Collection $detailIds): array
    {
        /*
         | Baris TAKEOUT ikut diambil (dulu dibuang di query) supaya modal
         | "View Budget" bisa menampilkannya tercoret + tombol Restore, sama
         | seperti drawer detail CR. Yang TIDAK boleh berubah: baris takeout
         | tidak pernah ikut penjumlahan Request/Approved — itu dijaga lewat
         | $activeRows di bawah, bukan lewat query, karena query yang sama
         | juga dipakai untuk menentukan baris mana yang sudah "di pipeline"
         | (dan karenanya tidak boleh dihitung dua kali sebagai history).
         */
        $pipeline = \App\Models\RequestDetail::whereIn('budget_detail_id', $detailIds)
            ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', ['SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED']))
            ->with('budgetRequest.beneficiary')
            ->get()
            ->groupBy('budget_detail_id');

        /*
         | CR yang uangnya sudah keluar (DISBURSED) per sub-item — sumber "Used"
         | SEKALIGUS daftar riwayat di modal. Dulu keduanya di-query per baris
         | budget (usedAmount() + costRequestDetails() di dalam loop), sehingga
         | Todo List menembak ±2.000 query per buka halaman. Sekarang satu query
         | per Budget Plan; hasil hitungannya identik (filter TAKEOUT & status
         | sama dengan BudgetPlanDetail::usedAmount()).
         */
        /*
         | Sejak Settlement Custom COA (25 Okt 2026) satu item bisa pecah ke
         | beberapa Sub COA, jadi barisnya diambil per CR lalu dipecah lewat
         | BudgetRequest::costUnits() (sumber tunggal nilai per sub-item),
         | bukan per request_detail. CR ikut bila punya item di sub-item ini
         | ATAU baris settlement yang dipindah/ditambahkan ke sini.
         */
        $ids = $detailIds->map(fn ($id) => (int) $id)->values()->all();

        $disbursed = \App\Models\BudgetRequest::whereIn('status', self::COA_SETTLED_ROW_STATUSES)
            ->where(function ($q) use ($ids) {
                $q->whereHas('details', fn ($d) => $d->whereIn('request_detail.budget_detail_id', $ids)
                    ->where('request_detail.status', '!=', 'TAKEOUT'));

                if (\App\Models\BudgetRequestRealization::hasBudgetDetailColumn()) {
                    $q->orWhereHas('realizations', fn ($r) => $r->whereIn('budget_request_realizations.budget_detail_id', $ids));
                }
            })
            // type: dipakai menentukan Cash Advance, yang menentukan rincian
            // mana yang ditampilkan (settlement atau pengajuan).
            ->with(['beneficiary', 'type', 'details', 'realizations'])
            ->get()
            ->flatMap(fn ($cr) => $cr->costUnits()
                ->filter(fn ($u) => $u['budget_detail_id'] && in_array($u['budget_detail_id'], $ids, true))
                ->map(fn ($u) => $u + ['budget_request' => $cr]))
            ->groupBy('budget_detail_id');

        return [$pipeline, $disbursed];
    }

    /**
     * Breakdown Chart of Account (Budget/Used/Request/Approved/Variance) untuk
     * satu Budget Plan — dipakai untuk modal "View Budget" di worklist Todo List,
     * supaya approver bisa melihat konteks budget sekaligus approve/reject
     * langsung dari list CR di modal.
     *
     * Request DINAMIS mengikuti section Todo List mana yang membuka modal ini
     * ($requestStatus, lihat pemanggil di buildStatusGroups()):
     *   - Head        -> SUBMITTED
     *   - Cost Control -> CHECKED
     *   - CEO         -> VERIFIED
     * (di luar 3 tahap approval itu, mis. dibuka dari section Settlement,
     * fallback ke ketiganya sekaligus supaya tidak salah makna).
     * Expense Claim = pengajuan ke-2 (kelebihan settlement Cash Advance) yang
     * masih direview: REALIZED s/d SETTLE_APPROVED.
     * Approved = sudah lolos approval tapi BELUM diproses ke Treasury:
     * pengajuan APPROVED + expense claim SECOND_APPROVED. QUEUED (sudah masuk
     * batch Treasury) dihitung di Used. Semua kolom saling eksklusif — lihat
     * coaSettledSplit().
     */
    /**
     * @param string|string[] $requestStatus satu status, atau beberapa
     *        sekaligus saat section-nya menggabungkan beberapa antrean.
     */
    private function buildReviewCoaBreakdown(BudgetPlan $bp, $crs, string|array $requestStatus = ''): \Illuminate\Support\Collection
    {
        $bp->loadMissing('details.account');

        $detailIds = $bp->details->pluck('id')->filter()->values();

        [$pipelineByDetailId, $disbursedByDetailId] = $this->coaRowsFor($detailIds);

        // Section yang menggabungkan beberapa status mengirim array; yang
        // dipakai hanya status yang memang salah satu dari tiga tahap
        // approval, sisanya jatuh ke fallback "ketiganya sekaligus".
        $diminta = array_intersect((array) $requestStatus, ['SUBMITTED', 'CHECKED', 'VERIFIED']);

        $requestStatuses = $diminta ? array_values($diminta) : ['SUBMITTED', 'CHECKED', 'VERIFIED'];

        // QUEUED bisa leg pertama atau leg kedua (expense claim); dicek sekali per CR.
        $claimLegMemo = [];
        $isClaimLeg = function ($cr) use (&$claimLegMemo) {
            return $claimLegMemo[$cr->id] ??= $cr->isExpenseClaimLeg();
        };

        $toCrItem = fn ($p, string $stage) => [
            'detail_id'        => $p->id,
            'is_takeout'       => $p->status === 'TAKEOUT',
            'takeout_notes'    => $p->takeout_notes,
            'takeout_stage_label' => $p->takeout_stage
                ? (\App\Models\RequestDetail::STAGE_LABELS[$p->takeout_stage] ?? $p->takeout_stage)
                : null,
            'cr_id'            => $p->budgetRequest->id,
            'cr_code'          => $p->budgetRequest->code,
            'status'           => $p->budgetRequest->status,
            'stage'            => $stage,
            'date_raw'         => $p->budgetRequest->created_at,
            'date_formatted'   => $p->budgetRequest->created_at?->format('d M Y H:i') ?? '-',
            'description'      => $p->notes ?: ($p->description ?: '-'),
            'beneficiary'      => $p->budgetRequest->beneficiary?->nickname ?? '-',
            'price_formatted'  => number_format((int) $p->amount, 0, ',', '.'),
            'qty'              => $p->qty,
            'amount_formatted' => number_format((int) $p->total, 0, ',', '.'),
            'is_settlement'    => false,
            'claim_amount'     => 0,
            'approved_claim_amount' => 0,
            'is_history'       => false,
        ];

        return $bp->details
            ->groupBy(fn ($d) => $d->account?->name ?? '-')
            ->map(function ($details, $accName) use ($pipelineByDetailId, $disbursedByDetailId, $toCrItem, $requestStatuses, $isClaimLeg) {
                $subItems = $details->map(function ($d) use ($pipelineByDetailId, $disbursedByDetailId, $toCrItem, $requestStatuses, $isClaimLeg) {
                    $disbursedRows = $disbursedByDetailId->get($d->id, collect());

                    $used = 0; $expenseClaim = 0; $approvedClaim = 0;
                    // Bagian klaim per BARIS disimpan supaya daftar CR di modal
                    // bisa menandai baris mana yang memunculkan Expense Claim —
                    // tanpa menghitung ulang pembagiannya di Blade.
                    $klaimPerBaris = [];
                    foreach ($disbursedRows as $r) {
                        [$u, $e, $a] = $this->coaSettledSplit($r, $isClaimLeg);
                        $used += $u; $expenseClaim += $e; $approvedClaim += $a;
                        $klaimPerBaris[$r['budget_request']->id . '|' . $r['key']] = ['claim' => $e, 'approved_claim' => $a];
                    }

                    $pipelineRows = $pipelineByDetailId->get($d->id, collect());

                    // Baris takeout: tampil saja, TIDAK pernah masuk hitungan.
                    $activeRows  = $pipelineRows->where('status', '!=', 'TAKEOUT');
                    $takeoutRows = $pipelineRows->where('status', '=', 'TAKEOUT');

                    $requestRows  = $activeRows->filter(fn ($p) => in_array($p->budgetRequest->status, $requestStatuses, true));
                    // Approved = lolos approval, BELUM diproses ke Treasury:
                    // pengajuan APPROVED + expense claim SECOND_APPROVED.
                    $approvedRows = $activeRows->filter(fn ($p) => $p->budgetRequest->status === 'APPROVED');

                    $requestAmount  = (int) $requestRows->sum('total');
                    $approvedAmount = (int) $approvedRows->sum('total') + $approvedClaim;
                    $remaining      = (int) $d->total - $used - $requestAmount - $expenseClaim - $approvedAmount;

                    // Riwayat CR yang sudah PAID/REALIZED/CLOSED untuk sub-item ini
                    // (di luar CR yang lagi jalan di pipeline), supaya list CR di
                    // modal juga menunjukkan dari mana nilai "Used" berasal.
                    $pipelineIds  = $pipelineRows->pluck('id')->all();
                    $historyItems = $disbursedRows
                        ->reject(fn ($u) => $u['request_detail']
                            && in_array($u['request_detail']->id, $pipelineIds))
                        // Item yang seluruh baris settlement-nya dipindah ke Sub
                        // COA lain tidak punya apa pun untuk ditampilkan di sini.
                        ->reject(fn ($u) => $u['real'] !== null && $u['realizations']->isEmpty())
                        ->map(function ($u) use ($klaimPerBaris) {
                            $cr     = $u['budget_request'];
                            $od     = $u['request_detail'];
                            $rows   = $u['realizations'];
                            $real   = $rows->count() === 1 ? $rows->first() : null;
                            $amount = (int) $u['value'];

                            /*
                             | Cash Advance baru diketahui rinciannya setelah
                             | settlement — yang berlaku di situ adalah baris
                             | settlement, bukan baris pengajuan. Kalau
                             | settlement-nya belum ada, baris pengajuan yang
                             | masih mewakili. Non Cash Advance selalu memakai
                             | baris pengajuan. Satu unit bisa berisi beberapa
                             | baris settlement (item dipecah di Sub COA yang
                             | sama): deskripsinya digabung, harga = nilainya.
                             */
                            $dariSettlement = $rows->isNotEmpty() && $cr->isCashAdvance();

                            $klaim = $klaimPerBaris[$cr->id . '|' . $u['key']] ?? ['claim' => 0, 'approved_claim' => 0];

                            $descSettle = $rows->map(fn ($r) => $r->notes ?: $r->description)->filter()->implode('; ');

                            return [
                                'detail_id'        => $od?->id,
                                'is_takeout'       => false,
                                'takeout_notes'    => null,
                                'takeout_stage_label' => null,
                                'cr_id'            => $cr->id,
                                'cr_code'          => $cr->code,
                                'status'           => $cr->status,
                                'stage'            => 'history',
                                'is_settlement'    => $dariSettlement,
                                // Baris settlement tambahan / pindah Sub COA.
                                'is_added'         => (bool) $u['is_added'],
                                'is_moved'         => (bool) $u['is_moved'],
                                'claim_amount'     => (int) $klaim['claim'],
                                'approved_claim_amount' => (int) $klaim['approved_claim'],
                                'date_raw'         => $cr->created_at,
                                'date_formatted'   => $cr->created_at?->format('d M Y H:i') ?? '-',
                                'description'      => $dariSettlement
                                    ? ($descSettle ?: ($od?->notes ?: ($od?->description ?: '-')))
                                    : ($od?->notes ?: ($od?->description ?: '-')),
                                'beneficiary'      => $cr->beneficiary?->nickname ?? '-',
                                'price_formatted'  => number_format((int) ($dariSettlement
                                    ? ($real ? $real->amount : $amount)
                                    : ($od?->amount ?? $amount)), 0, ',', '.'),
                                'qty'              => $dariSettlement ? ($real ? $real->qty : 1) : ($od?->qty ?? 1),
                                'amount_formatted' => number_format($amount, 0, ',', '.'),
                                'is_history'       => true,
                            ];
                        })
                        ->values();

                    return [
                        'deskripsi'             => $d->deskripsi,
                        'qty'                   => $d->qty,
                        'unit'                  => $d->unit,
                        'total'                 => (int) $d->total,
                        'total_formatted'       => number_format($d->total, 0, ',', '.'),
                        'used'                  => $used,
                        'used_formatted'        => number_format($used, 0, ',', '.'),
                        'expense_claim'         => $expenseClaim,
                        'expense_claim_formatted' => $expenseClaim > 0 ? number_format($expenseClaim, 0, ',', '.') : null,
                        'remaining'             => $remaining,
                        'remaining_formatted'   => number_format($remaining, 0, ',', '.'),
                        'request_amount'        => $requestAmount,
                        'request_formatted'     => $requestAmount > 0 ? number_format($requestAmount, 0, ',', '.') : null,
                        'approved_amount'       => $approvedAmount,
                        'approved_formatted'    => $approvedAmount > 0 ? number_format($approvedAmount, 0, ',', '.') : null,
                        'cr_items'              => $requestRows->map(fn ($p) => $toCrItem($p, 'request'))
                            ->concat($approvedRows->map(fn ($p) => $toCrItem($p, 'approved')))
                            ->concat($takeoutRows->map(fn ($p) => $toCrItem($p, 'takeout')))
                            ->concat($historyItems)
                            ->sortByDesc('date_raw')
                            ->values(),
                    ];
                })->values();

                return [
                    'account'   => $accName,
                    'items'     => $subItems,
                    'budget'    => $subItems->sum('total'),
                    'used'      => $subItems->sum('used'),
                    'expense_claim' => $subItems->sum('expense_claim'),
                    'request'   => $subItems->sum('request_amount'),
                    'approved'  => $subItems->sum('approved_amount'),
                    'remaining' => $subItems->sum('remaining'),
                    'has_nob'   => $subItems->contains(fn ($i) => $i['used'] > $i['total']),
                ];
            })
            ->sortBy('account')
            ->values();
    }

    /**
     * Petakan status NOB per CR dari hasil buildReviewCoaBreakdown(), supaya
     * baris CR pada flat list worklist bisa diberi warna tanpa perlu iterasi
     * ulang struktur CoA di Blade. Key = cr_id, value:
     * ['full' => kondisi tersendiri yang lebih berat — total keseluruhan
     *            Budget Plan ini sudah terlampaui, berlaku sama untuk semua
     *            CR di plan ini (merah),
     *  'item' => baru sub-item yang disentuh CR ini sendiri yang over,
     *            terlepas dari kondisi total plan (kuning)].
     *
     * Dihitung dari 'remaining' (bukan 'used') karena 'used' hanya menghitung
     * CR yang sudah PAID/REALIZED/CLOSED — CR yang sedang direview (SUBMITTED/
     * CHECKED/VERIFIED) tidak akan pernah muncul di situ. 'remaining' sudah
     * memperhitungkan nilai pengajuan yang sedang di-review (request_amount),
     * jadi mencerminkan kondisi budget SEANDAINYA CR ini disetujui.
     */
    private function buildCrNobMap(\Illuminate\Support\Collection $coaGroups, bool $planNobFull): array
    {
        $map = [];

        foreach ($coaGroups as $coa) {
            foreach ($coa['items'] as $subItem) {
                $itemNob = $subItem['remaining'] < 0;

                foreach ($subItem['cr_items'] as $cri) {
                    if ($cri['is_history']) continue;
                    // Item yang sudah di-takeout tidak ikut diproses, jadi ia
                    // juga tidak boleh menyeret CR-nya ikut ditandai NOB.
                    if ($cri['is_takeout'] ?? false) continue;

                    $crId = $cri['cr_id'];
                    if (!isset($map[$crId])) {
                        $map[$crId] = ['full' => $planNobFull, 'item' => false];
                    }
                    if ($itemNob) $map[$crId]['item'] = true;
                }
            }
        }

        return $map;
    }
}
