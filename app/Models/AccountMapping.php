<?php

namespace App\Models;

use App\Support\AccountingCatalog;
use Illuminate\Database\Eloquent\Model;

class AccountMapping extends Model
{
    protected $fillable = [
        'source_module', 'transaction_type', 'match_key', 'debit_account_id', 'credit_account_id',
        'debit_is_bank', 'credit_is_bank', 'effective_from', 'effective_to', 'status',
        'description', 'created_by',
    ];

    protected $casts = [
        'debit_is_bank'  => 'boolean',
        'credit_is_bank' => 'boolean',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    public function debitAccount()
    {
        return $this->belongsTo(CoaAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(CoaAccount::class, 'credit_account_id');
    }

    public function getTransactionLabelAttribute(): string
    {
        return AccountingCatalog::transactionLabel($this->source_module, $this->transaction_type);
    }

    /**
     * Mapping lain (aktif) yang rentang tanggalnya beririsan untuk kunci yang sama
     * = ambigu/konflik. Dipakai saat simpan agar tidak ada dua mapping berlaku
     * bersamaan.
     */
    public function conflictingQuery()
    {
        return static::query()
            ->where('status', 'ACTIVE')
            ->where('source_module', $this->source_module)
            ->where('transaction_type', $this->transaction_type)
            ->where(function ($q) {
                $this->match_key === null || $this->match_key === ''
                    ? $q->whereNull('match_key')
                    : $q->where('match_key', $this->match_key);
            })
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
            ->where(function ($q) {
                // beririsan: mulai lawan <= akhir kita (atau kita tanpa akhir) DAN akhir lawan >= mulai kita (atau lawan tanpa akhir)
                if ($this->effective_to) {
                    $q->whereDate('effective_from', '<=', $this->effective_to);
                }
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->effective_from);
            });
    }
}
