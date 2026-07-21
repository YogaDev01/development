<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BudgetRequest extends Model
{
    protected $table = 'budget_request';

    protected $fillable = [
        'code',
        'description',
        'amount',
        'notes',
        'applicant_id',
        'beneficiary_id',
        'type_id',
        'cluster_id',
        'budget_plan_id',
        'status',
        'submitted_at',
        'approved_at',
        'paid_at',
        'realized_at',
        'closed_at',
        'email',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
        'realized_at'  => 'datetime',
        'closed_at'    => 'datetime',
    ];

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'APPROVED',
        'VERIFIED',
        'QUEUED',
        'PAID',
        'REALIZED',
        'CLOSED',
        'REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function type()
    {
        return $this->belongsTo(RequestType::class, 'type_id');
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id');
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class, 'budget_request_id')->orderBy('id');
    }

    /**
     * Lampiran pengajuan. Tabel request_attachments memang KHUSUS lampiran
     * pengajuan (realisasi & bukti bayar punya tabelnya sendiri), jadi ini
     * identik dengan attachments() — tanpa filter kolom 'type'.
     */
    public function requestAttachments()
    {
        return $this->hasMany(RequestAttachment::class, 'budget_request_id')->orderBy('id');
    }

    public function details()
    {
        return $this->hasMany(RequestDetail::class, 'budget_request_id')->orderBy('id');
    }

    public function statusLogs()
    {
        return $this->hasMany(RequestStatusLog::class, 'budget_request_id')
            ->orderBy('created_at', 'desc');
    }

    public function dailyTransactionItems()
    {
        return $this->hasMany(DailyTransactionItem::class, 'budget_request_id');
    }

    public function realizations()
    {
        return $this->hasMany(BudgetRequestRealization::class, 'budget_request_id')->orderBy('id');
    }

    public function realizationAttachments()
    {
        return $this->hasMany(BudgetRequestRealizationAttachment::class, 'budget_request_id')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHECKS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool     { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool { return $this->status === 'SUBMITTED'; }
    public function isApproved(): bool  { return $this->status === 'APPROVED'; }
    public function isVerified(): bool  { return $this->status === 'VERIFIED'; }
    public function isQueued(): bool    { return $this->status === 'QUEUED'; }
    public function isPaid(): bool      { return $this->status === 'PAID'; }
    public function isRealized(): bool  { return $this->status === 'REALIZED'; }
    public function isClosed(): bool    { return $this->status === 'CLOSED'; }
    public function isRejected(): bool  { return $this->status === 'REJECTED'; }
    public function isEditable(): bool  { return $this->status === 'DRAFT'; }

    /** Status yang bisa direvise kembali ke DRAFT */
    public function isRevisable(): bool
    {
        return in_array($this->status, ['SUBMITTED', 'APPROVED', 'VERIFIED']);
    }

    /** Finance bisa kembalikan REALIZED → PAID */
    public function isRealizationRevisable(): bool
    {
        return $this->status === 'REALIZED';
    }

    public function getTotalRealizationAttribute(): int
    {
        return (int) $this->realizations()->sum('total');
    }

    public function getTotalRealizationFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total_realization, 0, ',', '.');
    }

    public function getAllowedNextStatuses(): array
    {
        return match($this->status) {
            'DRAFT'     => ['SUBMITTED'],
            'SUBMITTED' => ['APPROVED', 'DRAFT', 'REJECTED'],   // Head
            'APPROVED'  => ['VERIFIED', 'DRAFT', 'REJECTED'],   // Finance
            'VERIFIED'  => ['QUEUED'],                           // via Daily Transaction batch
            'QUEUED'    => ['PAID'],                             // via Daily Transaction approve
            'PAID'      => ['REALIZED'],
            'REALIZED'  => ['CLOSED'],
            default     => [],
        };
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedNextStatuses());
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Generate code: CR-{MM}{YY}{kodePO}{kodeCluster}{urutan4digit}
     */
    public static function generateCode(string $poCode, string $clusterCode): string
    {
        $month   = now()->format('m');
        $year    = now()->format('y');
        $po      = str_pad($poCode, 4, '0', STR_PAD_LEFT);
        $cluster = str_pad($clusterCode, 4, '0', STR_PAD_LEFT);
        $prefix  = 'CR-' . $month . $year . $po . $cluster;

        $count = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('code', 'like', $prefix . '%')
            ->count();

        $sequence  = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $candidate = $prefix . $sequence;

        $attempts = 0;
        while (static::where('code', $candidate)->exists() && $attempts < 100) {
            $count++;
            $sequence  = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $sequence;
            $attempts++;
        }

        return $candidate;
    }

    public function getAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->amount ?? 0, 0, ',', '.');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'DRAFT'     => 'secondary',
            'SUBMITTED' => 'warning',
            'APPROVED'  => 'info',
            'VERIFIED'  => 'primary',
            'QUEUED'    => 'warning',
            'PAID'      => 'success',
            'REALIZED'  => 'primary',
            'CLOSED'    => 'dark',
            'REJECTED'  => 'danger',
            default     => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'DRAFT'     => 'Draft',
            'SUBMITTED' => 'Submitted',
            'APPROVED'  => 'Approved',
            'VERIFIED'  => 'Verified',
            'QUEUED'    => 'Queued',
            'PAID'      => 'Paid',
            'REALIZED'  => 'Realized',
            'CLOSED'    => 'Closed',
            'REJECTED'  => 'Rejected',
            default     => $this->status,
        };
    }

    /** Role yang lihat SEMUA overhead tanpa access group */
    public const OVERHEAD_PRIVILEGED_ROLES = ['superadmin', 'approver'];

    /** Role yang boleh melihat CR PAYROLL (rahasia) */
    public const PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager'];

    public function isAccessibleBy(User $user): bool
    {
        $role = strtolower($user->role->name ?? '');

        // Superadmin & approver (CEO) lihat semua
        if (in_array($role, ['superadmin', 'approver'])) return true;

        $plan = $this->budgetPlan;

        /*
        | CR bersumber dari COST CENTER:
        |   - PAYROLL  -> role-gated (rahasia)
        |   - OVERHEAD -> privileged lihat semua; lainnya per access group cost center
        */
        if ($plan && $plan->cost_center_id !== null) {
            $type = strtoupper($plan->costCenter->type ?? 'OVERHEAD');

            if ($type === 'PAYROLL') {
                return in_array($role, self::PAYROLL_ROLES);
            }

            // OVERHEAD
            if (in_array($role, self::OVERHEAD_PRIVILEGED_ROLES)) return true;

            if (!$user->accessGroup) return false;

            return $user->accessGroup->costCenterIds()
                ->contains((int) $plan->cost_center_id);
        }

        // CR bersumber dari PROYEK: manager/head/finance per access group project
        if (!$user->accessGroup) return false;

        $projectIds = $user->accessGroup->details->pluck('project_id')->filter();
        $projectId  = $plan?->project_id
                   ?? $this->cluster?->project_id;

        return $projectId !== null && $projectIds->contains($projectId);
    }

    public function recalculateAmount(): void
    {
        $this->amount = $this->details()->sum('total');
        $this->save();
    }

    public function addLog(
        string $statusFrom,
        string $statusTo,
        string $actionType,
        ?string $notes = null,
        ?string $snapshot = null
    ): void {
        $user = Auth::user();

        $this->statusLogs()->create([
            'status_from'      => $statusFrom,
            'status_to'        => $statusTo,
            'action_type'      => $actionType,
            'notes'            => $notes,
            'acted_by'         => $user?->id,
            'actor_name'       => $user?->name ?? 'System',
            'actor_role'       => $user?->role?->name,
            'request_snapshot' => $snapshot,
        ]);
    }
}