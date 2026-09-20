<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M-04 — satu baris rekap closing, menunjuk balik ke dokumen sumbernya.
 */
class ClosingDetail extends Model
{
    protected $fillable = [
        'closing_id',
        'source_module',
        'source_id',
        'reference_number',
        'transaction_date',
        'description',
        'status_at_closing',
        'amount',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'integer',
    ];

    public function closing()
    {
        return $this->belongsTo(Closing::class);
    }

    /** Tautan ke dokumen asal, supaya rekap bisa ditelusuri dari layar. */
    public function getSourceUrlAttribute(): ?string
    {
        return match ($this->source_module) {
            'TREASURY'       => url('/daily-transaction/' . $this->source_id),
            'INVOICE',
            'COLLECTION'     => url('/invoice-cash-receipt/' . $this->source_id),
            // source_id menunjuk baris CashReceipt (alokasi), yang tidak punya
            // halaman sendiri — arahkan ke invoice yang dilunasinya.
            'CASH_RECEIPT'   => ($invoiceId = CashReceipt::withTrashed()->where('id', $this->source_id)->value('invoice_id'))
                ? url('/invoice-cash-receipt/' . $invoiceId)
                : null,
            'COST_REQUEST'   => url('/budget-request/' . $this->source_id),
            // source_id menunjuk ProjectPoNumber; halaman detailnya ada di
            // level project, jadi project-nya dicari lewat relasi.
            'PURCHASE_ORDER' => ($projectId = ProjectPoNumber::where('id', $this->source_id)->value('project_id'))
                ? url('/project/' . $projectId)
                : null,
            default          => null,
        };
    }
}
