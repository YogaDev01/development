<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;

class DailyTransaction extends Model
{
    protected $fillable = [
        'code',
        'date',
        'description',
        'type',
        'source_fund_id',
        'to_source_fund_id',
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
        'DONE',
        'PAID',
        'REJECTED',
    ];

    /**
     * CASH_OUT = batch berbasis Cost Request (perilaku lama).
     * TOP_UP   = transfer antar rekening perusahaan sendiri, tanpa CR/item.
     */
    public const TYPES = ['CASH_OUT', 'TOP_UP'];

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

    /** Rekening tujuan — hanya dipakai untuk type TOP_UP. */
    public function toSourceFund()
    {
        return $this->belongsTo(SourceFund::class, 'to_source_fund_id');
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

        // Urutan batch bulan ini - penghitung terkunci, lihat
        // App\Support\DocumentSequence.
        $no = DocumentSequence::next(
            "daily-transaction:{$year}{$month}",
            fn () => static::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count(),
        );

        $sequence = str_pad((string) $no, 4, '0', STR_PAD_LEFT);

        return $year . $month . $day . $count . $sequence;
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
            'DONE'             => 'primary',
            'PAID'             => 'success',
            'REJECTED'         => 'danger',
            default            => 'secondary',
        };
    }

    public function getTotalAmountAttribute(): int
    {
        // TOP_UP tidak punya item — nilainya langsung dari captured_amount (diisi saat dibuat).
        if ($this->type === 'TOP_UP') {
            return (int) ($this->captured_amount ?? 0);
        }

        // MN-09: satu batch bisa memuat Cost Request DAN Cost Register.
        // Nilainya diambil lewat DailyTransactionItem::sourceAmount() supaya
        // tidak ada penjumlahan yang diam-diam melewatkan salah satu jenis.
        return $this->includedItems()
            ->with(['budgetRequest', 'costRegister'])
            ->get()
            ->sum(fn ($item) => $item->sourceAmount());
    }

    public function getTotalAmountFormattedAttribute(): string
    {
        return number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Sinkron ulang kolom captured_amount dari total nilai CR yang sedang
     * INCLUDED — dipanggil tiap kali item batch berubah (tambah/takeout).
     * Tidak berlaku untuk TOP_UP (nilainya langsung diinput saat dibuat, bukan
     * turunan dari item — batch TOP_UP memang tidak punya item sama sekali).
     */
    public function recalculateCapturedAmount(): void
    {
        if ($this->type === 'TOP_UP') {
            return;
        }

        $this->update(['captured_amount' => $this->total_amount]);
    }

    public function getChargeFormattedAttribute(): string
    {
        return number_format($this->charge ?? 0, 0, ',', '.');
    }

    public function getCapturedAmountFormattedAttribute(): string
    {
        return number_format($this->captured_amount ?? 0, 0, ',', '.');
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
        return number_format($this->grand_total, 0, ',', '.');
    }

    public function isPendingApprover(): bool { return $this->status === 'PENDING_APPROVER'; }
    public function isDone(): bool            { return $this->status === 'DONE'; }
    public function isPaid(): bool            { return $this->status === 'PAID'; }
    public function isRejected(): bool        { return $this->status === 'REJECTED'; }

    public function isTopUp(): bool   { return $this->type === 'TOP_UP'; }
    public function isCashOut(): bool { return $this->type !== 'TOP_UP'; }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'TOP_UP' ? 'Top Up' : 'Cash Out';
    }

    public function getTypeColorAttribute(): string
    {
        return $this->type === 'TOP_UP' ? 'info' : 'secondary';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabelFor($this->status);
    }

    /**
     * Kata untuk sebuah kode status, versi statis — dipakai juga oleh
     * App\Support\WorkflowStatus supaya istilahnya tidak bercabang
     * (PENDING_APPROVER tetap disebut SUBMITTED seperti selama ini).
     * Pola yang sama dengan BudgetRequest::statusLabelFor().
     */
    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            'PENDING_APPROVER' => 'SUBMITTED',
            'DONE'             => 'DONE',
            'PAID'             => 'PAID',
            'REJECTED'         => 'REJECTED',
            default            => $status ?? '-',
        };
    }
}