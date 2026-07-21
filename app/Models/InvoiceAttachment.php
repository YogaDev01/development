<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceAttachment extends Model
{
    protected $fillable = [
        'invoice_id',
        'title',
        'file_path',
        'file_name',
        'file_type',
        'input_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}