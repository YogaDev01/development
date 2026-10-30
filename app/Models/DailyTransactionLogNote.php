<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTransactionLogNote extends Model
{
    protected $table = 'daily_transaction_log_notes';

    public $timestamps = false;

    protected $fillable = [
        'daily_transaction_id',
        'acted_by',
        'actor_name',
        'actor_role',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function dailyTransaction()
    {
        return $this->belongsTo(DailyTransaction::class, 'daily_transaction_id');
    }
}
