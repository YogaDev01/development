<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'unit_price',
        'qty',
        'deduction',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'qty'        => 'integer',
        'deduction'  => 'integer',
        'total'      => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getUnitPriceFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->unit_price, 0, ',', '.');
    }

    public function getDeductionFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->deduction, 0, ',', '.');
    }

    public function getTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total, 0, ',', '.');
    }
}