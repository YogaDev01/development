<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetRequestRealization extends Model
{
    protected $table = 'budget_request_realizations';

    protected $fillable = [
        'budget_request_id',
        'request_detail_id',
        'description',
        'account_id',
        'amount',
        'qty',
        'total',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'qty'    => 'integer',
        'total'  => 'integer',
    ];

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