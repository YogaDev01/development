<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'code',
        'po_id',
        'payment_term_type',
        'termin_number',
        'invoice_date',
        'due_date',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'captured_total',
        'total_deduction',
        'is_additional',
        'additional_amount',
        'remark',
        'status',
        'created_by',
        'submitted_by', 'submitted_at',
        'verified_by', 'verified_at',
        'approved_by', 'approved_at',
        'sent_by', 'sent_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'invoice_date'       => 'date',
        'due_date'           => 'date',
        'tax_rate'           => 'decimal:2',
        'subtotal'           => 'integer',
        'tax_amount'         => 'integer',
        'captured_total'     => 'integer',
        'total_deduction'    => 'integer',
        'is_additional'      => 'boolean',
        'additional_amount'  => 'integer',
        'submitted_at'       => 'datetime',
        'verified_at'        => 'datetime',
        'approved_at'        => 'datetime',
        'sent_at'            => 'datetime',
        'cancelled_at'       => 'datetime',
    ];

    public const PAYMENT_TERM_TYPES = ['Down Payment', 'Termin', 'Full Payment', 'Final Payment'];

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT',
        'PARTIALLY_PAID', 'PAID', 'CANCELLED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function po()
    {
        return $this->belongsTo(Project::class, 'po_id');
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class)->orderBy('id');
    }

    public function attachments()
    {
        return $this->hasMany(InvoiceAttachment::class);
    }

    public function cashReceipts()
    {
        return $this->hasMany(CashReceipt::class)->orderBy('received_date');
    }

    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy()  { return $this->belongsTo(User::class, 'submitted_by'); }
    public function verifiedBy()   { return $this->belongsTo(User::class, 'verified_by'); }
    public function approvedBy()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function sentBy()       { return $this->belongsTo(User::class, 'sent_by'); }
    public function cancelledBy()  { return $this->belongsTo(User::class, 'cancelled_by'); }

    /*
    |--------------------------------------------------------------------------
    | KODE INVOICE
    |--------------------------------------------------------------------------
    | Format: {urutan_global}/{initial_customer}-{urutan_per_customer}/MM/YY
    | Contoh: 0001/KPA-0001/04/26
    | Reset tiap tahun kalender (berdasar tahun invoice_date).
    */
    public static function generateCode(int $poId, ?\DateTimeInterface $date = null): string
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $mm   = $date->format('m');
        $yy   = $date->format('y');

        $po       = Project::with('customer')->findOrFail($poId);
        $customer = $po->customer;
        $initial  = $customer->initial ?: 'GEN';

        $globalCount = static::whereYear('invoice_date', $year)->count();

        $customerCount = static::whereYear('invoice_date', $year)
            ->whereHas('po', fn ($q) => $q->where('customer_id', $customer?->id))
            ->count();

        $globalSeq   = str_pad((string) ($globalCount + 1), 4, '0', STR_PAD_LEFT);
        $customerSeq = str_pad((string) ($customerCount + 1), 4, '0', STR_PAD_LEFT);

        return "{$globalSeq}/{$initial}-{$customerSeq}/{$mm}/{$yy}";
    }

    /*
    |--------------------------------------------------------------------------
    | KALKULASI
    |--------------------------------------------------------------------------
    */

    /**
     * Hitung ulang subtotal/tax/captured_total/deduction dari baris detail,
     * lalu cek apakah tagihan ini "Additional" (melebihi sisa nilai kontrak PO).
     */
    public function recalculateTotals(): void
    {
        $subtotal  = (int) $this->details()->sum('total');
        $deduction = (int) $this->details()->sum('deduction');
        $taxAmount = (int) round($subtotal * ((float) $this->tax_rate / 100));
        $captured  = $subtotal + $taxAmount;

        // Sisa nilai kontrak PO = nilai PO - total invoice lain yang tidak Cancelled (exclude diri sendiri)
        $po = $this->po ?? Project::find($this->po_id);
        $otherInvoicesTotal = static::where('po_id', $this->po_id)
            ->where('id', '!=', $this->id)
            ->where('status', '!=', 'CANCELLED')
            ->sum('captured_total');

        $remainingContract = (int) ($po->value ?? 0) - (int) $otherInvoicesTotal;
        $isAdditional       = $captured > $remainingContract;
        $additionalAmount   = $isAdditional ? ($captured - max(0, $remainingContract)) : 0;

        $this->update([
            'subtotal'          => $subtotal,
            'tax_amount'        => $taxAmount,
            'captured_total'    => $captured,
            'total_deduction'   => $deduction,
            'is_additional'     => $isAdditional,
            'additional_amount' => $additionalAmount,
        ]);
    }

    public function totalReceived(): int
    {
        return (int) $this->cashReceipts()->sum('amount');
    }

    public function remainingBalance(): int
    {
        return max(0, $this->captured_total - $this->totalReceived());
    }

    /**
     * Sinkron status Paid/Partially Paid berdasarkan akumulasi Cash Receipt.
     * Dipanggil tiap kali Cash Receipt ditambah/dihapus.
     */
    public function syncPaymentStatus(): void
    {
        if (!in_array($this->status, ['SENT', 'PARTIALLY_PAID', 'PAID'])) {
            return; // belum di tahap penagihan aktif, jangan diubah otomatis
        }

        $received = $this->totalReceived();

        $newStatus = $this->status;
        if ($received <= 0) {
            $newStatus = 'SENT';
        } elseif ($received < $this->captured_total) {
            $newStatus = 'PARTIALLY_PAID';
        } else {
            $newStatus = 'PAID';
        }

        if ($newStatus !== $this->status) {
            $this->update(['status' => $newStatus]);
        }

        // Kalau invoice ini Full Payment / Final Payment dan sudah Paid -> PO ikut jadi Paid
        if ($newStatus === 'PAID' && in_array($this->payment_term_type, ['Full Payment', 'Final Payment'])) {
            $po = $this->po ?? Project::find($this->po_id);
            if ($po && $po->status !== 'PAID') {
                $po->update(['status' => 'PAID']);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    /**
     * Status yang ditampilkan ke user — OVERDUE dihitung on-the-fly dari
     * due_date, TIDAK disimpan sebagai perubahan kolom status di database.
     */
    public function getDisplayStatusAttribute(): string
    {
        if (in_array($this->status, ['SENT', 'PARTIALLY_PAID'])
            && $this->due_date
            && $this->due_date->isPast()) {
            return 'OVERDUE';
        }

        return $this->status;
    }

    public function getPaymentTermLabelAttribute(): string
    {
        if ($this->payment_term_type === 'Termin' && $this->termin_number) {
            return 'Termin ' . $this->termin_number;
        }

        return $this->payment_term_type;
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getTaxAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->tax_amount, 0, ',', '.');
    }

    public function getCapturedTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->captured_total, 0, ',', '.');
    }

    public function getTotalDeductionFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total_deduction, 0, ',', '.');
    }

    public function getAdditionalAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->additional_amount, 0, ',', '.');
    }
}