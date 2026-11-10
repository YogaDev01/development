<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kekurangan bayar settlement Expense Cash Advance (realisasi > nominal yang
 * sudah dibayar). Dibuat saat CEO menyetujui settlement; dibayar HANYA lewat
 * batch Treasury — DailyTransactionController::markPaid() menjadikannya PAID
 * dan Expense induknya SETTLE_APPROVED -> CLOSED. Cermin BudgetRequestSecondPayment.
 */
class CostRegisterSecondPayment extends Model
{
    protected $fillable = [
        'cost_register_id',
        'amount',
        'status',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount'  => 'integer',
        'paid_at' => 'datetime',
    ];

    public const STATUSES = ['PENDING', 'PAID'];

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function dailyTransactionItems()
    {
        return $this->hasMany(DailyTransactionItem::class, 'cost_register_second_payment_id');
    }

    /**
     * Siap dibatch Treasury: PENDING, Expense induk SETTLE_APPROVED, dan belum
     * ada di batch aktif (termasuk Draft).
     */
    public function scopeAvailableForBatch($query)
    {
        return $query->where('status', 'PENDING')
            ->whereHas('costRegister', fn ($q) => $q->where('status', 'SETTLE_APPROVED'))
            ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn ($q2) => $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES))
            );
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }
}
