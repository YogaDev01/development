<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetRequestSecondPayment extends Model
{
    protected $fillable = [
        'budget_request_id',
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

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** Baris batch Treasury yang memuat Second Payment ini. Pembayarannya
     *  hanya lewat Treasury — lihat DailyTransactionController::markPaid(). */
    public function dailyTransactionItems()
    {
        return $this->hasMany(DailyTransactionItem::class, 'second_payment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ELIGIBLE UNTUK BATCH
    |--------------------------------------------------------------------------
    | Statusnya tetap dua tahap (PENDING -> PAID). Yang membedakan "belum
    | dibatch" dari "sedang menunggu bayar di batch" bukan status, melainkan
    | ada/tidaknya baris INCLUDED pada batch yang masih aktif — aturan yang
    | sama persis dengan Cost Request & Cost Register.
    */
    public function scopeAvailableForBatch($query)
    {
        return $query->where('status', 'PENDING')
            /*
            | Expense Claim baru boleh masuk batch Treasury setelah disetujui
            | dua lapis: Settle Approve (Finance Manager) lalu Second Approve
            | (Approver). Approval terakhir itulah yang memindahkan CR induk
            | ke SECOND_APPROVED — status "menunggu Buat Batch Baru".
            */
            ->whereHas('budgetRequest', fn ($q) => $q->where('status', 'SECOND_APPROVED'))
            ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn ($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                  )
            );
    }

    public function isPaid(): bool
    {
        return $this->status === 'PAID';
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }
}
