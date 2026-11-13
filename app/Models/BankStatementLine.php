<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu baris rekening koran. debit = uang keluar, credit = uang masuk. */
class BankStatementLine extends Model
{
    protected $fillable = [
        'reconciliation_id', 'import_id', 'source_fund_id', 'txn_date', 'description', 'reference',
        'debit', 'credit', 'balance', 'dedupe_hash', 'investigating', 'note',
    ];

    protected $casts = [
        'txn_date'      => 'date',
        'debit'         => 'integer',
        'credit'        => 'integer',
        'balance'       => 'integer',
        'investigating' => 'boolean',
    ];

    public function matches()
    {
        return $this->hasMany(CashReconciliationMatch::class, 'bank_line_id');
    }

    /** Arah menurut TESYS: credit bank = IN, debit bank = OUT. */
    public function getDirectionAttribute(): string
    {
        return $this->credit > 0 ? 'IN' : 'OUT';
    }

    public function getAmountAttribute(): int
    {
        return (int) max($this->credit, $this->debit);
    }
}
