<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceFund extends Model
{
    protected $fillable = [
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