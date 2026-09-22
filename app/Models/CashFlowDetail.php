<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M-01 — baris Cash Flow. Arah (IN/OUT) ada di sini, bukan di header, supaya
 * satu dokumen koreksi kas bisa memuat pergerakan dua arah sekaligus.
 */
class CashFlowDetail extends Model
{
    protected $fillable = [
        'cash_flow_id',
        'direction',
        'category',
        'description',
        'amount',
        'source_module',
        'source_id',
        'reference_number',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public const DIRECTIONS = ['IN', 'OUT'];

    public function cashFlow()
    {
        return $this->belongsTo(CashFlow::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return CashFlow::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getSignedAmountAttribute(): int
    {
        return $this->direction === 'IN' ? $this->amount : -$this->amount;
    }
}
