<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penyesuaian saldo terkontrol (pengganti "edit saldo langsung" yang tidak ada).
 * Baru memengaruhi saldo saat APPROVED.
 */
class CashAdjustment extends Model
{
    public const PENDING   = 'PENDING';
    public const APPROVED  = 'APPROVED';
    public const REJECTED  = 'REJECTED';
    public const CANCELLED = 'CANCELLED';

    /** type => [label, arah tetap atau null bila dipilih pembuat] */
    public const TYPES = [
        'OPENING_CORRECTION' => ['Koreksi Saldo Awal', null],
        'BANK_CHARGE'        => ['Biaya Bank', 'OUT'],
        'INTEREST_INCOME'    => ['Bunga / Pendapatan Bank', 'IN'],
        'PETTY_CASH'         => ['Penyesuaian Petty Cash', null],
        'OTHER'              => ['Penyesuaian Lain (berizin)', null],
    ];

    protected $fillable = [
        'adjustment_number', 'source_fund_id', 'effective_date', 'type', 'direction', 'amount',
        'reason', 'attachment', 'status', 'created_by', 'approved_by', 'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'amount'         => 'integer',
        'approved_at'    => 'datetime',
    ];

    public function sourceFund() { return $this->belongsTo(SourceFund::class); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }
}
