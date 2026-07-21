<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashReceipt extends Model
{
    protected $fillable = [
        'invoice_id',
        'received_date',
        'amount',
        'source_fund_id',
        'proof_file',
        'notes',
        'input_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'amount'        => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function getAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Setelah Cash Receipt disimpan/dihapus, sinkron ulang status invoice.
     */
    protected static function booted()
    {
        static::saved(function (CashReceipt $cr) {
            $cr->invoice?->syncPaymentStatus();
        });

        static::deleted(function (CashReceipt $cr) {
            $cr->invoice?->syncPaymentStatus();
        });
    }
}