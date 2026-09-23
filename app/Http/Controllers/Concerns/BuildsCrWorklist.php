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
            ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', ['SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED', 'QUEUED']))
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
        $disbursed = \App\Models\RequestDetail::whereIn('budget_detail_id', $detailIds)
            ->where('request_detail.status', '!=', 'TAKEOUT')
            ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', \App\Support\BudgetConsumption::DISBURSED))
            ->with(['budgetRequest.beneficiary', 'realization'])
            ->get()
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
     * Approved = CR yang sudah lolos ketiga tahap approval (APPROVED) ATAU
     * sudah QUEUED (tinggal menunggu diproses/dibayar) — dua-duanya dianggap
     * "selesai proses approval", cuma beda status transient menuju pembayaran.
     * Setiap CR hanya berstatus satu hal di satu waktu, jadi kedua bucket ini
     * otomatis mutually exclusive (tidak ada double counting).
     */
    private function buildReviewCoaBreakdown(BudgetPlan $bp, $crs, string $requestStatus = ''): \Illuminate\Support\Collection
    {
        $bp->loadMissing('details.account');

        $detailIds = $bp->details->pluck('id')->filter()->values();

        [$pipelineByDetailId, $disbursedByDetailId] = $this->coaRowsFor($detailIds);

        $requestStatuses = in_array($requestStatus, ['SUBMITTED', 'CHECKED', 'VERIFIED'], true)
            ? [$requestStatus]
            : ['SUBMITTED', 'CHECKED', 'VERIFIED'];

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
            'is_history'       => false,
        ];

        return $bp->details
            ->groupBy(fn ($d) => $d->account?->name ?? '-')
            ->map(function ($details, $accName) use ($pipelineByDetailId, $disbursedByDetailId, $toCrItem, $requestStatuses) {
                $subItems = $details->map(function ($d) use ($pipelineByDetailId, $disbursedByDetailId, $toCrItem, $requestStatuses) {
                    $disbursedRows = $disbursedByDetailId->get($d->id, collect());
                    $used = (int) $disbursedRows->sum(fn ($r) => $r->realization->total ?? $r->total);

                    $pipelineRows = $pipelineByDetailId->get($d->id, collect());

                    // Baris takeout: tampil saja, TIDAK pernah masuk hitungan.
                    $activeRows  = $pipelineRows->where('status', '!=', 'TAKEOUT');
                    $takeoutRows = $pipelineRows->where('status', '=', 'TAKEOUT');

                    $requestRows  = $activeRows->filter(fn ($p) => in_array($p->budgetRequest->status, $requestStatuses, true));
                    $approvedRows = $activeRows->filter(fn ($p) => in_array($p->budgetRequest->status, ['APPROVED', 'QUEUED'], true));

                    $requestAmount  = (int) $requestRows->sum('total');
                    $approvedAmount = (int) $approvedRows->sum('total');
                    $remaining      = (int) $d->total - $used - $requestAmount - $approvedAmount;

                    // Riwayat CR yang sudah PAID/REALIZED/CLOSED untuk sub-item ini
                    // (di luar CR yang lagi jalan di pipeline), supaya list CR di
                    // modal juga menunjukkan dari mana nilai "Used" berasal.
                    $historyItems = $disbursedRows
                        ->whereNotIn('id', $pipelineRows->pluck('id')->all())
                        ->map(function ($od) {
                            $cr     = $od->budgetRequest;
                            $amount = (int) ($od->realization?->total ?? $od->total);

                            return [
                                'detail_id'        => $od->id,
                                'is_takeout'       => false,
                                'takeout_notes'    => null,
                                'takeout_stage_label' => null,
                                'cr_id'            => $cr->id,
                                'cr_code'          => $cr->code,
                                'status'           => $cr->status,
                                'stage'            => 'history',
                                'date_raw'         => $cr->created_at,
                                'date_formatted'   => $cr->created_at?->format('d M Y H:i') ?? '-',
                                'description'      => $od->notes ?: ($od->description ?: '-'),
                                'beneficiary'      => $cr->beneficiary?->nickname ?? '-',
                                'price_formatted'  => number_format((int) $od->amount, 0, ',', '.'),
                                'qty'              => $od->qty,
                                'amount_formatted' => number_format($amount, 0, ',', '.'),
                                'is_history'       => true,
                            ];
                        });

                    return [
                        'deskripsi'             => $d->deskripsi,
                        'qty'                   => $d->qty,
                        'unit'                  => $d->unit,
                        'total'                 => (int) $d->total,
                        'total_formatted'       => number_format($d->total, 0, ',', '.'),
                        'used'                  => $used,
                        'used_formatted'        => number_format($used, 0, ',', '.'),
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
