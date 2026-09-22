<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlanDetail extends Model
{
    protected $fillable = [
        'budget_plan_id',
        'type',
        'category',
        'account_id',
        'deskripsi',
        'amount',
        'qty',
        'unit',
        'total',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'qty'    => 'decimal:2',
        'total'  => 'integer',
    ];

    public const TYPES = [
        'main',
        'additional',
    ];

    public const CATEGORIES = [
        'Variable Cost',
        'Fixed Cost',
        'Overhead',
    ];

    public const UNITS = [
        'bulan', 'hari', 'kg', 'lembar', 'liter', 'lot', 'lumpsum',
        'meter', 'meter kubik', 'meter persegi', 'minggu', 'orang',
        'package', 'pcs', 'roll', 'sax', 'set', 'site', 'team',
        'ton', 'trip', 'unit',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total, 0, ',', '.');
    }

    /** Cache in-memory per instance — lihat catatan di usedAmount(). */
    private ?int $usedAmountCache = null;

    /**
     * Nilai yang sudah terpakai, dihitung dari Cost Request berstatus PAID/REALIZED/CLOSED
     * yang menunjuk ke baris detail ini: pakai nilai realisasi bila sudah ada,
     * jika belum ada realisasi pakai nilai pengajuan (request).
     *
     * Dipanggil per SUB-ITEM di halaman breakdown Chart of Account (Todo List,
     * Daily Transaction, Cost Request, dst) — bisa puluhan kali per satu page
     * load. Di-cache per instance supaya remainingAmount() (yang memanggil
     * usedAmount() lagi) tidak menjalankan query yang sama dua kali. Dulu di
     * sini juga ada Schema::hasColumn() yang menjalankan query ke
     * information_schema pada SETIAP pemanggilan — dihapus karena kolom
     * budget_detail_id sudah permanen ada sejak migration lama, jadi
     * pengecekan itu cuma query terbuang yang ikut menyumbang slow query.
     */
    public function usedAmount(): int
    {
        if ($this->usedAmountCache !== null) {
            return $this->usedAmountCache;
        }

        return $this->usedAmountCache = (int) $this->costRequestDetails()
            ->whereHas('budgetRequest', function ($q) {
                $q->whereIn('status', \App\Support\BudgetConsumption::DISBURSED);
            })
            ->with('realization')
            ->get()
            ->sum(fn ($d) => $d->realization->total ?? $d->total);
    }

    /**
     * Variance (Budget - Used). Sengaja TIDAK dibatasi ke 0 supaya kondisi NOB
     * (terpakai melebihi budget) tampil sebagai variance negatif yang jujur.
     */
    public function remainingAmount(): int
    {
        return ($this->total ?? 0) - $this->usedAmount();
    }

    /**
     * Relasi ke detail Cost Request yang menunjuk ke baris budget ini
     * (dipakai setelah Cost Request dimodifikasi untuk pick sub-item budget).
     * Item yang di-takeout salah satu approval TIDAK dihitung — tidak menyumbang
     * ke "Used" maupun daftar riwayat CR pada breakdown Chart of Account.
     */
    public function costRequestDetails()
    {
        return $this->hasMany(RequestDetail::class, 'budget_detail_id')
            ->where('request_detail.status', '!=', 'TAKEOUT');
    }
}