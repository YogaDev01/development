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
        'input_by',
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
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getLabelAttribute(): string
    {
        $bankName = $this->bank->name ?? '-';
        return $this->name . ' — ' . $bankName . ' (' . ($this->no_account ?? '-') . ')';
    }
}