<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTransactionItem extends Model
{
    protected $fillable = [
        'daily_transaction_id',
        'budget_request_id',
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

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isIncluded(): bool { return $this->status === 'INCLUDED'; }
    public function isRejected(): bool { return $this->status === 'REJECTED'; }
}