<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashReceipt extends Model
{
    /*
    | Penghapusan catatan keuangan ditandai, bukan dilenyapkan - lihat migrasi
    | 2026_09_18_000002. Baris yang ditandai otomatis keluar dari semua query
    | (termasuk penjumlahan pembayaran), tapi tetap ada untuk ditelusuri.
    */
    use SoftDeletes;

    protected $fillable = [
        'deleted_by',
        'delete_reason',
        // M-03 — induk bukti terima, kalau pembayaran ini bagian dari satu
        // transfer yang menutup beberapa invoice. NULL untuk pembayaran
        // langsung per invoice (perilaku lama, tetap sah).
        'cash_receipt_header_id',
        'invoice_id',
        'received_date',
        'amount',
        'withholding_tax',
        'source_fund_id',
        'proof_file',
        'notes',
        'input_by',
    ];

    protected $casts = [
        'received_date'   => 'date',
        'amount'          => 'integer',
        'withholding_tax' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function header()
    {
        return $this->belongsTo(CashReceiptHeader::class, 'cash_receipt_header_id');
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
        return number_format($this->amount, 0, ',', '.');
    }

    public function getWithholdingTaxFormattedAttribute(): string
    {
        return number_format($this->withholding_tax, 0, ',', '.');
    }

    /**
     * Setelah Cash Receipt disimpan/dihapus, sinkron ulang status invoice.
     */
    protected static function booted()
    {
        static::saved(function (CashReceipt $cr) {
            $cr->invoice?->syncPaymentStatus();
            // Status header ditegakkan dari kenyataan alokasinya (M-03), jadi
            // ia ikut disinkronkan di titik yang sama dengan status invoice —
            // bukan diserahkan ke masing-masing controller yang bisa lupa.
            $cr->header?->syncStatus();
        });

        static::deleted(function (CashReceipt $cr) {
            $cr->invoice?->syncPaymentStatus();
            $cr->header?->syncStatus();
        });
    }
}