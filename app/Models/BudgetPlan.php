<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BudgetPlan extends Model
{
    protected $fillable = [
        'code',
        'project_id',
        'cost_center_id',
        'description',
        'type',
        'amount',
        'status',
        'plan_finish',
        'closing_submitted_at',
        'notes',
        'approver_notes',
        'approved_by',
        'approved_at',
        'input_by',
        'via_po_approval',
    ];

    protected $casts = [
        'amount'               => 'integer',
        'via_po_approval'      => 'boolean',
        'approved_at'          => 'datetime',
        'plan_finish'          => 'date',
        'closing_submitted_at' => 'datetime',
    ];

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'CHECKED',
        'VERIFIED',
        'ACTIVE',
        'CLOSING',
        'CLOSED',
        'REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | GUARD: tepat satu sumber dana
    |--------------------------------------------------------------------------
    | Mencegah data korup (dua-duanya terisi, atau dua-duanya kosong) sekalipun
    | ada controller/seeder yang lalai.
    */
    protected static function booted(): void
    {
        static::saving(function (BudgetPlan $plan) {
            $hasProject    = !is_null($plan->project_id);
            $hasCostCenter = !is_null($plan->cost_center_id);

            if ($hasProject && $hasCostCenter) {
                throw new \LogicException(
                    'Budget plan tidak boleh memiliki project_id dan cost_center_id sekaligus.'
                );
            }

            if (!$hasProject && !$hasCostCenter) {
                throw new \LogicException(
                    'Budget plan harus memiliki salah satu: project_id atau cost_center_id.'
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SUMBER DANA (funding source)
    |--------------------------------------------------------------------------
    | Budget plan bersumber dari SALAH SATU:
    |   - Project    (PO customer)  -> variable cost
    |   - CostCenter (internal)     -> overhead / payroll
    */

    /** Budget yang bersumber dari PO customer */
    public function isProjectBudget(): bool
    {
        return !is_null($this->project_id);
    }

    /** Budget internal (overhead / payroll) */
    public function isCostCenterBudget(): bool
    {
        return !is_null($this->cost_center_id);
    }

    /** Nama sumber dana, apa pun jenisnya — pakai null-safe (?->) karena
     *  project_id/cost_center_id bisa saja menunjuk baris yang sudah terhapus
     *  (data yatim), bukan cuma null id-nya. */
    public function getSourceNameAttribute(): string
    {
        if ($this->isProjectBudget()) {
            return $this->project?->name ?? '-';
        }

        if ($this->isCostCenterBudget()) {
            return $this->costCenter?->name ?? '-';
        }

        return '-';
    }

    /** 'PROJECT' | 'OVERHEAD' | 'PAYROLL' */
    public function getSourceTypeAttribute(): string
    {
        if ($this->isProjectBudget()) {
            return 'PROJECT';
        }

        if ($this->isCostCenterBudget()) {
            return strtoupper($this->costCenter?->type ?? 'OVERHEAD');
        }

        return '-';
    }

    /** Hanya budget yang bersumber dari PO */
    public function scopeFromProject($query)
    {
        return $query->whereNotNull('project_id');
    }

    /** Hanya budget internal (overhead/payroll) */
    public function scopeFromCostCenter($query)
    {
        return $query->whereNotNull('cost_center_id');
    }

    public function details()
    {
        return $this->hasMany(BudgetPlanDetail::class)->orderBy('id');
    }

    public function attachments()
    {
        return $this->hasMany(BudgetPlanAttachment::class)->orderBy('id');
    }

    public function logs()
    {
        return $this->hasMany(BudgetPlanLog::class)->orderBy('created_at', 'desc');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function costRequests()
    {
        return $this->hasMany(BudgetRequest::class, 'budget_plan_id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHECKS
    |--------------------------------------------------------------------------
    */

    /** Budget yang dibuat dari form PO: status digerakkan approval PO, bukan workflow Budget Plan. */
    public function isBundled(): bool    { return (bool) $this->via_po_approval; }

    public function isDraft(): bool      { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool  { return $this->status === 'SUBMITTED'; }
    public function isChecked(): bool    { return $this->status === 'CHECKED'; }
    public function isVerified(): bool   { return $this->status === 'VERIFIED'; }
    public function isActive(): bool     { return $this->status === 'ACTIVE'; }
    public function isClosing(): bool    { return $this->status === 'CLOSING'; }

    /** CLOSING yang berkas closing-nya belum diajukan manager (sisa data lama dari
     *  auto-closing yang sudah dihapus) — tugasnya masih di manager, bukan Finance. */
    public function isClosingPending(): bool { return $this->status === 'CLOSING' && is_null($this->closing_submitted_at); }

    /** CLOSING yang sudah diajukan manager — menunggu verifikasi Finance. */
    public function isClosingSubmitted(): bool { return $this->status === 'CLOSING' && !is_null($this->closing_submitted_at); }

    public function isClosed(): bool     { return $this->status === 'CLOSED'; }
    public function isRejected(): bool   { return $this->status === 'REJECTED'; }
    public function isEditable(): bool   { return $this->status === 'DRAFT'; }
    public function isSubmittable(): bool { return $this->status === 'DRAFT' && $this->details()->count() > 0; }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount ?? 0, 0, ',', '.');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'DRAFT'     => 'secondary',
            'SUBMITTED' => 'warning',
            'CHECKED'   => 'info',
            'VERIFIED'  => 'primary',
            'ACTIVE'    => 'success',
            'CLOSING'   => 'info',
            'CLOSED'    => 'dark',
            'REJECTED'  => 'danger',
            default     => 'secondary',
        };
    }

    public function usedAmount(): int
    {
        // Diisi massal oleh withSum() di halaman daftar (BudgetPlanController::index)
        // supaya tidak satu query per baris; kondisinya sama dengan query di bawah.
        if (array_key_exists('used_amount_sum', $this->attributes)) {
            return (int) $this->attributes['used_amount_sum'];
        }

        // Effective cost (BudgetRequest::effectiveCostSql()), bukan nilai
        // pengajuan: CA yang di-settle 0 tidak lagi memakan budget.
        return (int) $this->costRequests()
            ->disbursed()
            ->sum(\Illuminate\Support\Facades\DB::raw(BudgetRequest::effectiveCostSql()));
    }

    public function remainingAmount(): int
    {
        return max(0, ($this->amount ?? 0) - $this->usedAmount());
    }

    /*
    |--------------------------------------------------------------------------
    | NOB — apakah total melebihi sisa budget
    |--------------------------------------------------------------------------
    | Satu-satunya sumber rumus NOB: dipakai form Cost Request
    | (BudgetRequestController) dan pembentukan CR dari pengajuan Trouble Ticket.
    */

    /**
     * Pemakaian anggaran yang SUDAH TERIKAT (BudgetConsumption::COMMITTED),
     * dihitung dari effective cost. Daftar statusnya diambil dari
     * BudgetConsumption::COMMITTED, bukan ditulis ulang: versi lama lupa
     * 'CHECKED' - CR yang sudah lolos Head tapi belum diverifikasi Finance tidak
     * terhitung memakan anggaran, sehingga peringatan NOB terlambat muncul.
     */
    private function committedAmount(?int $excludeCrId = null)
    {
        return BudgetRequest::where('budget_plan_id', $this->id)
            ->whereIn('status', \App\Support\BudgetConsumption::COMMITTED)
            ->when($excludeCrId, fn ($q) => $q->where('id', '!=', $excludeCrId))
            ->sum(\Illuminate\Support\Facades\DB::raw(BudgetRequest::effectiveCostSql()));
    }

    /**
     * Project (cluster) yang bisa dipilih pada Cost Request budget PROYEK: cluster yang terhubung ke
     * milestone project (project_details.cluster_id) — sumber yang sama dengan dropdown Project di
     * form Cost Request, BUKAN clusters.project_id. Kosong untuk budget cost center.
     *
     * @return \Illuminate\Support\Collection<int, Cluster>
     */
    public function projectClusters(): \Illuminate\Support\Collection
    {
        if (! $this->project_id) {
            return collect();
        }

        return Cluster::whereIn('id', ProjectDetail::where('project_id', $this->project_id)->whereNotNull('cluster_id')->select('cluster_id'))
            ->orderBy('name')
            ->get();
    }

    public function isNob(int $amount, ?int $excludeCrId = null): bool
    {
        return ($this->committedAmount($excludeCrId) + $amount) > ($this->amount ?? 0);
    }

    /**
     * Berapa rupiah pengajuan ini melampaui sisa anggaran (0 bila tidak).
     *
     * Disimpan sebagai angka, bukan disisipkan ke teks: angka bisa dijumlahkan
     * pada laporan, teks tidak.
     */
    public function nobOverage(int $amount, ?int $excludeCrId = null): int
    {
        return (int) max(0, ($this->committedAmount($excludeCrId) + $amount) - ($this->amount ?? 0));
    }

    /**
     * Semua id sub-item yang dikirim benar-benar milik Budget Plan ini (cegah
     * memilih sub-item dari budget lain). Daftar kosong dianggap sah.
     *
     * @param  iterable<int|string|null>  $detailIds
     */
    public function ownsDetails(iterable $detailIds): bool
    {
        $ids = collect($detailIds)->filter()->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return true;
        }

        $validIds = $this->details()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id);

        return $ids->diff($validIds)->isEmpty();
    }

    public function recalculateAmount(): void
    {
        $total = $this->details()->sum('total');
        $this->update(['amount' => $total]);
    }

    public function addLog(string $from, string $to, string $action, ?string $notes = null): void
    {
        $user = Auth::user();
        $this->logs()->create([
            'budget_plan_id' => $this->id,
            'status_from'    => $from,
            'status_to'      => $to,
            'action'         => $action,
            'notes'          => $notes,
            'user_id'        => $user?->id,
            'user_name'      => $user?->name ?? 'System',
            'role'           => $user?->role?->name,
        ]);

        /*
        | Audit trail terpusat (UserActivityLog). Titik tunggal semua transisi
        | status Budget Plan. Gagal-aman (record() dibungkus try/catch).
        */
        $labels = [
            'created'           => 'Membuat',
            'submitted'         => 'Mengajukan',
            'head_approved'     => 'Menyetujui (Head)',
            'head_rejected'     => 'Menolak (Head)',
            'finance_verified'  => 'Memverifikasi (Finance)',
            'finance_rejected'  => 'Menolak (Finance)',
            'ceo_approved'      => 'Menyetujui (CEO)',
            'ceo_rejected'      => 'Menolak (CEO)',
            'revised'           => 'Merevisi',
            'closing_submitted' => 'Mengajukan closing',
            'closing_approved'  => 'Menyetujui closing',
            'closing_rejected'  => 'Menolak closing',
            'finance_closed'    => 'Menutup',
        ];
        $actionKey = match ($action) {
            'created'                          => 'create',
            'submitted', 'closing_submitted'   => 'submit',
            'head_approved'                    => 'check',
            'finance_verified'                 => 'verify',
            'ceo_approved', 'closing_approved' => 'approve',
            'head_rejected', 'finance_rejected',
            'ceo_rejected', 'closing_rejected' => 'reject',
            'revised'                          => 'revise',
            'finance_closed'                   => 'close',
            default                            => 'update',
        };
        $verb = $labels[$action] ?? ucfirst($action);
        UserActivityLog::record(
            'budget_plan',
            $actionKey,
            $verb . ' Budget Plan: ' . ($this->description ?? '#' . $this->id),
            $this,
            $notes
        );
    }
}