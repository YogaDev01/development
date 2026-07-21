<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTransaction extends Model
{
    protected $fillable = [
        'code',
        'date',
        'description',
        'source_fund_id',
        'charge',
        'captured_amount',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'payment_notes',
    ];

    protected $casts = [
        'date'            => 'date',
        'charge'          => 'integer',
        'captured_amount' => 'integer',
        'approved_at'     => 'datetime',
        'paid_at'         => 'datetime',
    ];

    public const STATUSES = [
        'PENDING_APPROVER',
        'APPROVED',
        'PAID',
        'REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(DailyTransactionItem::class)->orderBy('id');
    }

    public function includedItems()
    {
        return $this->hasMany(DailyTransactionItem::class)
            ->where('status', 'INCLUDED')
            ->orderBy('id');
    }

    public function rejectedItems()
    {
        return $this->hasMany(DailyTransactionItem::class)
            ->where('status', 'REJECTED')
            ->orderBy('id');
    }

    public function attachments()
    {
        return $this->hasMany(DailyTransactionAttachment::class)->orderBy('id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Generate code: {YYYY}{MM}{DD}{count3digit}{seq4digit}
     * count = jumlah CR dalam batch (dinamis)
     * seq   = urutan batch bulan ini (reset tiap bulan)
     */
    public static function generateCode(int $crCount): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $day   = now()->format('d');
        $count = str_pad($crCount, 3, '0', STR_PAD_LEFT);

        // Urutan batch bulan ini
        $seq = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        $sequence  = str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
        $candidate = $year . $month . $day . $count . $sequence;

        // Pastikan tidak duplikat
        $attempts = 0;
        while (static::where('code', $candidate)->exists() && $attempts < 100) {
            $seq++;
            $sequence  = str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
            $candidate = $year . $month . $day . $count . $sequence;
            $attempts++;
        }

        return $candidate;
    }

    /**
     * Update code saat jumlah CR berubah (item ditambah/diremove)
     */
    public function refreshCode(): void
    {
        $count    = $this->includedItems()->count();
        $padded   = str_pad($count, 3, '0', STR_PAD_LEFT);

        // Ganti bagian count (posisi 8-10) di code existing
        $newCode  = substr($this->code, 0, 8) . $padded . substr($this->code, 11);
        $this->update(['code' => $newCode]);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'PENDING_APPROVER' => 'warning',
            'APPROVED'         => 'primary',
            'PAID'             => 'success',
            'REJECTED'         => 'danger',
            default            => 'secondary',
        };
    }

    public function getTotalAmountAttribute(): int
    {
        return $this->includedItems()
            ->with('budgetRequest')
            ->get()
            ->sum(fn($item) => $item->budgetRequest->amount ?? 0);
    }

    public function getTotalAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Sinkron ulang kolom captured_amount dari total nilai CR yang sedang
     * INCLUDED — dipanggil tiap kali item batch berubah (tambah/takeout).
     */
    public function recalculateCapturedAmount(): void
    {
        $this->update(['captured_amount' => $this->total_amount]);
    }

    public function getChargeFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->charge ?? 0, 0, ',', '.');
    }

    public function getCapturedAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->captured_amount ?? 0, 0, ',', '.');
    }

    /**
     * Kalkulasi visual saja (tidak disimpan): Nilai Batch (captured_amount) + Charge.
     */
    public function getGrandTotalAttribute(): int
    {
        return (int) ($this->captured_amount ?? 0) + (int) ($this->charge ?? 0);
    }

    public function getGrandTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->grand_total, 0, ',', '.');
    }

    public function isPendingApprover(): bool { return $this->status === 'PENDING_APPROVER'; }
    public function isApproved(): bool        { return $this->status === 'APPROVED'; }
    public function isPaid(): bool            { return $this->status === 'PAID'; }
    public function isRejected(): bool        { return $this->status === 'REJECTED'; }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'PENDING_APPROVER' => 'SUBMITTED',
            'APPROVED'         => 'APPROVED',
            'PAID'             => 'PAID',
            'REJECTED'         => 'REJECTED',
            default            => $this->status,
        };
    }
}