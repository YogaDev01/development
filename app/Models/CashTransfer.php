<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Transfer antar rekening perusahaan. Baru memengaruhi saldo saat APPROVED:
 * Cash Out di rekening asal, Cash In di rekening tujuan (konsolidasi tetap),
 * dan biaya transfer sebagai pengeluaran terpisah dari rekening asal.
 */
class CashTransfer extends Model
{
    public const PENDING   = 'PENDING';
    public const APPROVED  = 'APPROVED';
    public const REJECTED  = 'REJECTED';
    public const CANCELLED = 'CANCELLED';

    protected $fillable = [
        'transfer_number', 'transfer_date', 'from_fund_id', 'to_fund_id', 'amount', 'fee',
        'description', 'attachment', 'status', 'created_by', 'approved_by', 'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'amount'        => 'integer',
        'fee'           => 'integer',
        'approved_at'   => 'datetime',
    ];

    public function fromFund()  { return $this->belongsTo(SourceFund::class, 'from_fund_id'); }
    public function toFund()    { return $this->belongsTo(SourceFund::class, 'to_fund_id'); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }
}
