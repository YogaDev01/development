<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceFund extends Model
{
    // Tipe rekening Cash & Bank. Dua tipe pertama dihitung sebagai "Cash on Hand".
    public const TYPES = [
        'BANK'                  => 'Bank Account',
        'PETTY_CASH'            => 'Petty Cash',
        'CASH_ON_HAND'          => 'Cash on Hand',
        'OTHER_CASH_EQUIVALENT' => 'Other Cash Equivalents',
    ];

    protected $fillable = [
        'code',
        'account_type',
        'account_holder',
        'branch',
        'currency',
        'description',
        'name',
        'no_account',
        'bank_id',
        'status',
        'is_main_account',
        'input_by',
    ];

    protected $casts = [
        'is_main_account' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function openingBalance()
    {
        return $this->hasOne(CashOpeningBalance::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->account_type] ?? $this->account_type;
    }

    /** Nomor rekening disamarkan: hanya 4 digit terakhir yang terlihat. */
    public function getMaskedAccountAttribute(): string
    {
        $no = (string) $this->no_account;
        if ($no === '') {
            return '-';
        }

        return mb_strlen($no) <= 4 ? $no : str_repeat('•', mb_strlen($no) - 4) . mb_substr($no, -4);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /** Rekening Main Account — asal dana untuk Top Up. */
    public function scopeMainAccount($query)
    {
        return $query->where('is_main_account', true);
    }

    /** Rekening operasional (bukan Main Account) — dipakai Cash Out & tujuan Top Up. */
    public function scopeOperational($query)
    {
        return $query->where('is_main_account', false);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getLabelAttribute(): string
    {
        $bankName = $this->bank->name ?? '-';
        return $this->name . ' — ' . $bankName . ' (' . ($this->no_account ?? '-') . ')';
    }
}