<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = [

        'nickname',
        'complete_name',
        'contact',
        'bank_id',
        'account_number',
        'status',
        'input_by',

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION
    |--------------------------------------------------------------------------
    */

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}