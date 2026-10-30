<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLogNote extends Model
{
    protected $table = 'request_log_notes';

    public $timestamps = false;

    protected $fillable = [
        'budget_request_id',
        'acted_by',
        'actor_name',
        'actor_role',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }
}
