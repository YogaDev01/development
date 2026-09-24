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
        'notes',
        'approver_notes',
        'approved_by',
        'approved_at',
        'input_by',
    ];

    protected $casts = [
        'amount'      => 'integer',
        'approved_at' => 'datetime',
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

    public function isDraft(): bool      { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool  { return $this->status === 'SUBMITTED'; }
    public function isChecked(): bool    { return $this->status === 'CHECKED'; }
    public function isVerified(): bool   { return $this->status === 'VERIFIED'; }
    public function isActive(): bool     { return $this->status === 'ACTIVE'; }
    public function isClosing(): bool    { return $this->status === 'CLOSING'; }
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

        return $this->costRequests()
            ->whereIn('status', \App\Support\BudgetConsumption::DISBURSED)
            ->sum('amount');
    }

    public function remainingAmount(): int
    {
        return max(0, ($this->amount ?? 0) - $this->usedAmount());
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
    }
}