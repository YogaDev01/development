<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostRegister extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'amount',
        'period',
        'notes',
        'batch_code',
        'input_by',
        // MN-09 — alur Non-CR menuju Treasury.
        'status',
        'beneficiary_id',
        'source_fund_id',
        'submitted_at',
        'paid_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'period'       => 'date',
        'submitted_at' => 'datetime',
        'paid_at'      => 'datetime',
        'verified_at'  => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public const TYPES = ['FIXED_COST', 'OVERHEAD'];

    /**
     * Alur Cost Register = alur Cost Payment (Non-Cash Advance) di Cost Request,
     * tanpa tahap Checked:
     *
     *     DRAFT -> VERIFIED -> APPROVED -> QUEUED -> CLOSED
     *
     *   DRAFT     : baru diinput, belum diajukan.
     *   VERIFIED  : diajukan (Draft langsung Verified); label tampilan "Submitted".
     *               Menunggu persetujuan Approver (CEO).
     *   APPROVED  : disetujui; siap dimasukkan ke batch Treasury.
     *   QUEUED    : sudah masuk batch Treasury yang aktif (label "Process").
     *   CLOSED    : batch-nya sudah dibayar.
     *   CANCELLED : dibatalkan sebelum masuk batch.
     *
     * Perpindahan status milik CostRegisterController (DRAFT/VERIFIED/APPROVED)
     * dan DailyTransactionController (QUEUED/CLOSED).
     */
    public const STATUSES = ['DRAFT', 'VERIFIED', 'APPROVED', 'QUEUED', 'CLOSED', 'CANCELLED'];

    /** Status yang boleh dipilih Treasury saat menyusun batch. */
    public const TREASURY_READY_STATUS = 'APPROVED';

    /** Peran per tahap (dipakai route, controller, dan Blade — satu sumber). */
    public const SUBMIT_ROLES  = ['superadmin', 'finance', 'finance_manager'];
    public const APPROVE_ROLES = ['superadmin', 'approver'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments()
    {
        return $this->hasMany(CostRegisterAttachment::class)->orderBy('id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function details()
    {
        return $this->hasMany(CostRegisterDetail::class)->orderBy('id');
    }

    public function logs()
    {
        return $this->hasMany(CostRegisterLog::class)->orderByDesc('id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Baris batch Treasury yang memuat Cost Register ini. */
    public function dailyTransactionItems()
    {
        return $this->hasMany(DailyTransactionItem::class, 'cost_register_id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool    { return $this->status === 'DRAFT'; }
    public function isVerified(): bool { return $this->status === 'VERIFIED'; }
    public function isApproved(): bool { return $this->status === 'APPROVED'; }
    public function isClosed(): bool   { return $this->status === 'CLOSED'; }

    /** Hanya DRAFT yang boleh dihapus (Verified/Approved dibatalkan, bukan dihapus). */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** Boleh dibatalkan selama belum masuk antrean pembayaran. */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['DRAFT', 'VERIFIED', 'APPROVED'], true);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'    => 'secondary',
            'VERIFIED' => 'warning',
            'APPROVED' => 'primary',
            'QUEUED'   => 'info',
            'CLOSED'   => 'success',
            'CANCELLED' => 'danger',
            default    => 'secondary',
        };
    }

    /**
     * Label tampilan. VERIFIED tampil sebagai "Submitted" (Draft langsung Verified,
     * tahap Checked dilewati) dan QUEUED sebagai "Process", seperti Cost Request.
     */
    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            'DRAFT'     => 'Draft',
            'VERIFIED'  => 'Submitted',
            'APPROVED'  => 'Approved',
            'QUEUED'    => 'Process',
            'CLOSED'    => 'Closed',
            'CANCELLED' => 'Cancelled',
            default     => $status ?? '-',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabelFor($this->status);
    }

    /** Catat transisi status / catatan ke cost_register_logs (aktor dari user login). */
    public function addLog(?string $from, ?string $to, string $action, ?string $notes = null): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $this->logs()->create([
            'action'      => $action,
            'status_from' => $from,
            'status_to'   => $to,
            'notes'       => $notes,
            'user_id'     => $user?->id,
            'user_name'   => $user?->name ?? 'System',
            'role'        => $user?->role?->name,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'FIXED_COST' => 'Fixed Cost',
            'OVERHEAD'   => 'Overhead',
            default      => $this->type,
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'FIXED_COST' => 'warning',
            'OVERHEAD'   => 'info',
            default      => 'secondary',
        };
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }
}
