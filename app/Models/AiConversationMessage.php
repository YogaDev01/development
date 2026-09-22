<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversationMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'function_name',
        'function_args',
        'cr_links',
        'batch_links',
    ];

    protected $casts = [
        'function_args' => 'array',
        'cr_links'       => 'array',
        'batch_links'    => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
