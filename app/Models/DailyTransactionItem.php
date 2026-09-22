<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTransactionItem extends Model
{
    protected $fillable = [
        'daily_transaction_id',
        // Satu baris batch memuat salah satu sumber biaya berikut:
        //   budget_request_id -> biaya lewat Cost Request (alur lama)
        //   cost_register_id  -> biaya Non-CR lewat Cost Register (MN-09)
        //   second_payment_id -> kekurangan bayar hasil Settlement Cash Advance;
        //      baris ini SEKALIGUS mengisi budget_request_id (CR induknya),
        //      jadi cek jenisnya selalu lewat isSecondPayment(), bukan lewat
        //      ada/tidaknya budget_request_id.
        'budget_request_id',
        'cost_register_id',
        'second_payment_id',
        'status',
        'notes',
    ];

    public const STATUSES = [
        'INCLUDED',
        'REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function dailyTransaction()
    {
        return $this->belongsTo(DailyTransaction::class);
    }

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class, 'cost_register_id');
    }

    public function secondPayment()
    {
        return $this->belongsTo(BudgetRequestSecondPayment::class, 'second_payment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SUMBER BIAYA — antarmuka seragam
    |--------------------------------------------------------------------------
    | Batch Treasury kini bisa memuat dua jenis baris. Supaya penjumlahan,
    | tampilan, dan ekspor tidak perlu bercabang di banyak tempat, perbedaannya
    | diselesaikan sekali di sini.
    */

    public function isCostRegister(): bool
    {
        return $this->cost_register_id !== null;
    }

    /** Baris kekurangan bayar Settlement — nilainya dari Second Payment,
     *  identitas & penerimanya tetap dari CR induk. */
    public function isSecondPayment(): bool
    {
        return $this->second_payment_id !== null;
    }

    public function sourceAmount(): int
    {
        if ($this->isSecondPayment()) {
            return (int) ($this->secondPayment->amount ?? 0);
        }

        return $this->isCostRegister()
            ? (int) ($this->costRegister->amount ?? 0)
            : (int) ($this->budgetRequest->amount ?? 0);
    }

    public function sourceCode(): string
    {
        if ($this->isSecondPayment()) {
            // Sengaja memakai kode CR yang sama: Second Payment bukan CR baru,
            // melainkan kekurangan bayar atas CR tersebut.
            return ($this->budgetRequest->code ?? '-') . ' (2nd)';
        }

        return $this->isCostRegister()
            ? ('CREG-' . str_pad((string) $this->cost_register_id, 5, '0', STR_PAD_LEFT))
            : ($this->budgetRequest->code ?? '-');
    }

    public function sourceDescription(): string
    {
        if ($this->isSecondPayment()) {
            return 'KEKURANGAN BAYAR SETTLEMENT — ' . (string) ($this->budgetRequest->description ?? '-');
        }

        return $this->isCostRegister()
            ? (string) ($this->costRegister->notes ?? '-')
            : (string) ($this->budgetRequest->description ?? '-');
    }

    public function sourceBeneficiaryName(): string
    {
        return $this->isCostRegister()
            ? (string) ($this->costRegister->beneficiary->nickname ?? '-')
            : (string) ($this->budgetRequest->beneficiary->nickname ?? '-');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isIncluded(): bool { return $this->status === 'INCLUDED'; }
    public function isRejected(): bool { return $this->status === 'REJECTED'; }
}