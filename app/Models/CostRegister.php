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
    ];

    protected $casts = [
        'amount'       => 'integer',
        'period'       => 'date',
        'submitted_at' => 'datetime',
        'paid_at'      => 'datetime',
    ];

    public const TYPES = ['FIXED_COST', 'OVERHEAD'];

    /**
     * MN-09 — alur biaya NON-Cost Request:
     *
     *     DRAFT -> SUBMITTED -> QUEUED -> PAID
     *
     * Sengaja TANPA rantai approval berjenjang seperti Cost Request: nominal
     * Fixed Cost/Overhead sudah disepakati lewat meeting + approval CEO di luar
     * sistem (lihat CostRegisterController), jadi menambahkan approval di sini
     * hanya menduplikasi keputusan yang sudah diambil.
     *
     *   DRAFT     : baru diinput, belum diajukan ke Treasury.
     *   SUBMITTED : siap dimasukkan ke batch Treasury.
     *   QUEUED    : sudah masuk batch Treasury yang aktif.
     *   PAID      : batch-nya sudah dibayar.
     *   CANCELLED : dibatalkan sebelum dibayar.
     */
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'QUEUED', 'PAID', 'CANCELLED'];

    /** Status yang boleh dipilih Treasury saat menyusun batch. */
    public const TREASURY_READY_STATUS = 'SUBMITTED';

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

    public function isDraft(): bool     { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool { return $this->status === 'SUBMITTED'; }
    public function isPaid(): bool      { return $this->status === 'PAID'; }

    /** Masih boleh diubah/dihapus selama belum masuk antrean pembayaran. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['DRAFT', 'SUBMITTED'], true);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'     => 'secondary',
            'SUBMITTED' => 'warning',
            'QUEUED'    => 'info',
            'PAID'      => 'success',
            'CANCELLED' => 'danger',
            default     => 'secondary',
        };
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
