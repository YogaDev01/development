<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetRequestRealization extends Model
{
    protected $table = 'budget_request_realizations';

    protected $fillable = [
        'budget_request_id',
        'request_detail_id',
        'budget_detail_id',
        'description',
        'account_id',
        'amount',
        'qty',
        'total',
        'notes',
        'change_note',
        'transaction_date',
        'vendor_name',
    ];

    protected $casts = [
        'amount' => 'integer',
        'qty'    => 'integer',
        'total'  => 'integer',
        'transaction_date' => 'date',
    ];

    /** Kolom tanggal transaksi & vendor belum tentu ada (migrasi menyusul di host). */
    public static function hasTransactionColumns(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('budget_request_realizations', 'transaction_date');
    }

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** Item CR (request_detail) yang direalisasikan */
    public function requestDetail()
    {
        return $this->belongsTo(RequestDetail::class, 'request_detail_id');
    }

    /**
     * Sub COA (sub-item Budget Plan) yang dipilih langsung pada baris
     * settlement ini. NULL pada baris lama (sebelum Settlement Custom COA,
     * 25 Okt 2026) — pakai effectiveBudgetDetailId() untuk nilai yang berlaku.
     */
    public function budgetDetail()
    {
        return $this->belongsTo(BudgetPlanDetail::class, 'budget_detail_id');
    }

    /**
     * Sub-item Budget Plan yang BERLAKU untuk baris ini:
     * COALESCE(budget_detail_id baris, budget_detail_id item asal).
     *
     * $origin boleh dikirim pemanggil yang sudah memegang item asalnya
     * (mis. dari koleksi details CR yang sudah dimuat) supaya tidak memicu
     * query relasi requestDetail per baris.
     */
    public function effectiveBudgetDetailId(?RequestDetail $origin = null): ?int
    {
        if ($this->budget_detail_id) {
            return (int) $this->budget_detail_id;
        }

        if (! $this->request_detail_id) {
            return null;
        }

        $origin ??= $this->requestDetail;

        return $origin?->budget_detail_id ? (int) $origin->budget_detail_id : null;
    }

    /** Memo per request — lihat hasBudgetDetailColumn(). */
    private static ?bool $hasBudgetDetailColumn = null;

    /**
     * Kolom budget_detail_id sudah ada? Jaga-jaga bila kode terunggah lebih
     * dulu daripada migrasi 2026_10_25_000200 dijalankan di server: tanpa ini
     * setiap query yang menyebut kolomnya gagal 500. Dicek sekali per request.
     */
    public static function hasBudgetDetailColumn(): bool
    {
        return self::$hasBudgetDetailColumn ??= \Illuminate\Support\Facades\Schema::hasColumn(
            'budget_request_realizations', 'budget_detail_id'
        );
    }

    /** Lampiran KHUSUS baris realisasi ini (beda dengan lampiran global Settlement). */
    public function attachments()
    {
        return $this->hasMany(RealizationDetailAttachment::class, 'realization_id')->orderBy('id');
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total ?? 0, 0, ',', '.');
    }
}