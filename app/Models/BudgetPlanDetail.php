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
        'kg', 'liter', 'meter', 'meter persegi', 'meter kubik',
        'roll', 'pcs', 'unit', 'lumpsum', 'package',
        'lot', 'ton', 'sax', 'set', 'site', 'lembar',
        'hari', 'orang', 'team', 'trip', 'minggu', 'bulan',
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
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total, 0, ',', '.');
    }

    /**
     * Nilai yang sudah terpakai (Cost Request berstatus PAID/REALIZED/CLOSED
     * yang menunjuk ke baris detail ini).
     */
    public function usedAmount(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('request_detail', 'budget_detail_id')) {
            return 0;
        }

        return (int) $this->costRequestDetails()
            ->whereHas('budgetRequest', function ($q) {
                $q->whereIn('status', ['PAID', 'REALIZED', 'CLOSED']);
            })
            ->sum('total');
    }

    public function remainingAmount(): int
    {
        return max(0, ($this->total ?? 0) - $this->usedAmount());
    }

    /**
     * Relasi ke detail Cost Request yang menunjuk ke baris budget ini
     * (dipakai setelah Cost Request dimodifikasi untuk pick sub-item budget).
     */
    public function costRequestDetails()
    {
        return $this->hasMany(RequestDetail::class, 'budget_detail_id');
    }
}