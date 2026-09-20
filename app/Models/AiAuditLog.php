<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_role',
        'ai_conversation_id',
        'question',
        'function_name',
        'function_params',
        'model',
        'tokens_used',
        'execution_time_ms',
        'success',
        'error_code',
        'created_at',
    ];

    protected $casts = [
        'function_params' => 'array',
        'success'         => 'boolean',
        'created_at'      => 'datetime',
    ];
}
