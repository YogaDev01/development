<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rincian per item Cost Register (header: CostRegister). Uang = integer rupiah. */
class CostRegisterDetail extends Model
{
    protected $fillable = [
        'cost_register_id',
        'account_id',
        'description',
        'qty',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'integer',
        'total'      => 'integer',
    ];

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
