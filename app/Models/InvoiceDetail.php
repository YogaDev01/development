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
        'unit',
        'deduction',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'qty'        => 'integer',
        'deduction'  => 'integer',
        'total'      => 'integer',
    ];

    /** "2 Lumpsum" — dipakai di lembar cetak, qty & satuan digabung. */
    public function getQtyUnitLabelAttribute(): string
    {
        return trim($this->qty . ($this->unit ? ' ' . ucfirst($this->unit) : ''));
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getUnitPriceFormattedAttribute(): string
    {
        return number_format($this->unit_price, 0, ',', '.');
    }

    public function getDeductionFormattedAttribute(): string
    {
        return number_format($this->deduction, 0, ',', '.');
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total, 0, ',', '.');
    }
}