<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = [

        'nickname',
        'complete_name',
        'category',
        'pic_id',
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

    /** MN-02 — kategori beneficiary. */
    public const CATEGORIES = ['INTERNAL', 'EXTERNAL', 'BILLING'];

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /** PIC penanggung jawab (Master PIC). */
    public function pic()
    {
        return $this->belongsTo(MasterPic::class, 'pic_id');
    }
}