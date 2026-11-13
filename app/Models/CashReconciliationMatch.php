<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pasangan baris rekening koran ↔ baris ledger TESYS. */
class CashReconciliationMatch extends Model
{
    protected $fillable = [
        'reconciliation_id', 'source_fund_id', 'bank_line_id', 'ledger_key', 'ledger_module',
        'ledger_source_id', 'ledger_direction', 'ledger_amount', 'ledger_date', 'ledger_reference',
        'method', 'created_by',
    ];

    protected $casts = [
        'ledger_date'   => 'date',
        'ledger_amount' => 'integer',
    ];

    public function bankLine()
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_line_id');
    }
}
