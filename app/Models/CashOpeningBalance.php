<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo awal rekening kas/bank (satu per rekening). Hanya yang APPROVED yang
 * dihitung CashBalanceService. Setelah APPROVED tidak boleh diubah langsung —
 * koreksi memakai Cash Adjustment (fase berikutnya).
 */
class CashOpeningBalance extends Model
{
    public const PENDING  = 'PENDING';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';

    protected $fillable = [
        'source_fund_id', 'amount', 'effective_date', 'status', 'supporting_document',
        'notes', 'created_by', 'verified_by', 'verified_at', 'reject_reason',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'effective_date' => 'date',
        'verified_at'    => 'datetime',
    ];

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }
}
