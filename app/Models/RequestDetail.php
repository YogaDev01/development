<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestDetail extends Model
{
    protected $table = 'request_detail';

    protected $fillable = [
        'budget_request_id',
        'budget_detail_id',
        'description',
        'amount',
        'qty',
        'total',
        'notes',
        'account_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'qty'    => 'integer',
        'total'  => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** Sub-item Budget Plan yang dipilih untuk baris pengajuan ini */
    public function budgetDetail()
    {
        return $this->belongsTo(BudgetPlanDetail::class, 'budget_detail_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total ?? 0, 0, ',', '.');
    }
}