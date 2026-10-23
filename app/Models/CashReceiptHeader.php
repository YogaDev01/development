<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * M-03 — Cash Receipt Header.
 *
 * Satu bukti penerimaan uang dari satu customer, yang alokasinya bisa menutup
 * beberapa invoice. Baris alokasinya adalah CashReceipt (tabel lama) — lihat
 * penjelasan lengkap di migrasi 2026_09_20_000005.
 */
class CashReceiptHeader extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'received_date',
        'customer_id',
        'source_fund_id',
        'reference_number',
        'proof_file',
        'total_amount',
        'status',
        'notes',
        'created_by',
        'allocated_by', 'allocated_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_amount'  => 'integer',
        'allocated_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public const STATUSES = ['DRAFT', 'ALLOCATED', 'CANCELLED'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /** Baris alokasi ke invoice. */
    public function details()
    {
        return $this->hasMany(CashReceipt::class, 'cash_receipt_header_id')->orderBy('id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /*
    |--------------------------------------------------------------------------
    | KODE & ALOKASI
    |--------------------------------------------------------------------------
    */

    /** CR/2026/09/0001 */
    public static function generateCode(\Carbon\Carbon $date): string
    {
        $year = $date->format('Y');
        $mm   = $date->format('m');

        $no = DocumentSequence::next(
            "cash_receipt_header:{$year}",
            fn () => static::withTrashed()->whereYear('received_date', $year)->count(),
        );

        return sprintf('CR/%s/%s/%s', $year, $mm, str_pad((string) $no, 4, '0', STR_PAD_LEFT));
    }

    /**
     * Total yang sudah dialokasikan ke invoice — termasuk PPh yang di-settle,
     * karena keduanya mengurangi tagihan customer.
     */
    public function allocatedAmount(): int
    {
        $details = $this->relationLoaded('details') ? $this->details : $this->details()->get();

        return (int) $details->sum('amount') + (int) $details->sum('withholding_tax');
    }

    /**
     * Sisa uang yang belum dipasangkan ke invoice mana pun. Angka ini yang
     * membuat header berguna: uang masuk bisa dicatat lebih dulu, alokasinya
     * menyusul, dan selisihnya tidak hilang dari pandangan.
     */
    public function unallocatedAmount(): int
    {
        // Penerimaan yang dibatalkan tidak punya sisa untuk dialokasikan.
        if ($this->isCancelled()) {
            return 0;
        }

        return (int) $this->total_amount - $this->allocatedAmount();
    }

    public function isFullyAllocated(): bool
    {
        return $this->unallocatedAmount() <= 0;
    }

    public function isDraft(): bool     { return $this->status === 'DRAFT'; }
    public function isCancelled(): bool { return $this->status === 'CANCELLED'; }

    /** Status ALLOCATED ditegakkan dari kenyataan alokasi, bukan diinput. */
    public function syncStatus(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $new = $this->isFullyAllocated() && $this->allocatedAmount() > 0 ? 'ALLOCATED' : 'DRAFT';

        if ($new !== $this->status) {
            $this->update([
                'status'       => $new,
                'allocated_by' => $new === 'ALLOCATED' ? auth()->id() : null,
                'allocated_at' => $new === 'ALLOCATED' ? now() : null,
            ]);
        }
    }
}
