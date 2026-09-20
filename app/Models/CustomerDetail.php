<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDetail extends Model
{
    protected $fillable = [
        'customer_id',
        'pic',
        'position',
        'contact',
        'remark',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}