<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAttachment extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'file_path',
        'file_name',
        'file_type',
        'input_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}