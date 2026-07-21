<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = [
        'code',
        'name',
        'po_number',
        'done_by',
        'value',
        'term_of_payment',
        'customer_id',
        'input_by',
        'status',
        'notes',
        'submitted_at',
        'approved_at',
        'closed_at',
        'approved_by',
        'closed_by',
        'closing_notes',
    ];

    protected $casts = [
        'value'            => 'integer',
        'nilai_bast'       => 'integer',
        'submitted_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'closed_at'        => 'datetime',
        'bast_submitted_at'=> 'datetime',
        'tanggal_bast'     => 'date',
    ];

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'WAITING_PO',
        'ACTIVE',
        'PROGRESS_DONE',
        'BILLED',
        'PAID',
        'CLOSING',
        'CLOSED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function details()
    {
        return $this->hasMany(ProjectDetail::class, 'project_id')->orderBy('id');
    }

    /**
     * PIC (Internal maupun Customer) yang terhubung ke PO ini,
     * diambil dari Master PIC lewat pivot table project_pic_links.
     */
    public function pics()
    {
        return $this->belongsToMany(MasterPic::class, 'project_pic_links', 'project_id', 'master_pic_id')
            ->withTimestamps();
    }

    public function picsInternal()
    {
        return $this->pics()->where('type', 'internal');
    }

    public function picsCustomer()
    {
        return $this->pics()->where('type', 'customer');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_id')->orderBy('id');
    }

    public function closingAttachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_id')
            ->where('type', 'closing')
            ->orderBy('id');
    }

    public function logs()
    {
        return $this->hasMany(ProjectLog::class, 'project_id')
            ->orderBy('created_at', 'desc');
    }

    public function budgetPlans()
    {
        return $this->hasMany(BudgetPlan::class, 'project_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'po_id');
    }

    /**
     * Sisa nilai kontrak yang belum ditagihkan (dipakai untuk cek flag "Additional" di Invoice).
     */
    public function remainingContractValue(): int
    {
        $invoiced = (int) $this->invoices()->where('status', '!=', 'CANCELLED')->sum('captured_total');
        return (int) ($this->value ?? 0) - $invoiced;
    }

    public function bastSubmittedBy()
    {
        return $this->belongsTo(User::class, 'bast_submitted_by');
    }

    public function bastAttachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_id')
            ->where('type', 'bast')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHECKS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool        { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool    { return $this->status === 'SUBMITTED'; }
    public function isWaitingPo(): bool    { return $this->status === 'WAITING_PO'; }
    public function isActive(): bool       { return $this->status === 'ACTIVE'; }
    public function isProgressDone(): bool { return $this->status === 'PROGRESS_DONE'; }
    public function isBilled(): bool       { return $this->status === 'BILLED'; }
    public function isPaid(): bool         { return $this->status === 'PAID'; }
    public function isClosing(): bool      { return $this->status === 'CLOSING'; }
    public function isClosed(): bool       { return $this->status === 'CLOSED'; }

    /** Head bisa edit hanya saat DRAFT */
    public function isEditableByHead(): bool { return $this->status === 'DRAFT'; }

    /** Finance bisa edit saat DRAFT, SUBMITTED, atau WAITING_PO */
    public function isEditableByFinance(): bool
    {
        return in_array($this->status, ['DRAFT', 'SUBMITTED', 'WAITING_PO']);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getValueFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->value ?? 0, 0, ',', '.');
    }

    /**
     * Total nilai Budget Plan yang terhubung ke PO ini.
     * Hanya budget berstatus ACTIVE atau CLOSED yang dihitung
     * (budget yang masih DRAFT/SUBMITTED/REJECTED belum dianggap final).
     */
    public function budgetPlanTotal(): int
    {
        return (int) $this->budgetPlans
            ->whereIn('status', ['ACTIVE', 'CLOSED'])
            ->sum('amount');
    }

    public function grossProfit(): int
    {
        return (int) ($this->value ?? 0) - $this->budgetPlanTotal();
    }

    public function grossMargin(): float
    {
        $value = (int) ($this->value ?? 0);
        return $value > 0 ? round(($this->grossProfit() / $value) * 100, 1) : 0.0;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'         => 'secondary',
            'SUBMITTED'     => 'warning',
            'WAITING_PO'    => 'info',
            'ACTIVE'        => 'success',
            'PROGRESS_DONE' => 'primary',
            'BILLED'        => 'info',
            'PAID'          => 'success',
            'CLOSING'       => 'warning',
            'CLOSED'        => 'dark',
            default         => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'         => 'Draft',
            'SUBMITTED'     => 'Submitted',
            'WAITING_PO'    => 'Waiting PO',
            'ACTIVE'        => 'Active',
            'PROGRESS_DONE' => 'Progress Done',
            'BILLED'        => 'Billed',
            'PAID'          => 'Paid',
            'CLOSING'       => 'Closing',
            'CLOSED'        => 'Closed',
            default         => $this->status,
        };
    }

    /**
     * Generate kode unik 4 digit zero-padded
     */
    public static function generateNextCode(): string
    {
        $last = static::whereNotNull('code')
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        $next = $last ? ((int) $last + 1) : 1;
        return str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function getNilaiBastFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->nilai_bast ?? 0, 0, ',', '.');
    }

    public function addLog(
        string $from,
        string $to,
        string $action,
        ?string $notes = null
    ): void {
        $user = Auth::user();

        $this->logs()->create([
            'status_from' => $from,
            'status_to'   => $to,
            'action'      => $action,
            'notes'       => $notes,
            'user_id'     => $user?->id,
            'user_name'   => $user?->name ?? 'System',
            'role'        => $user?->role?->name,
        ]);
    }
}