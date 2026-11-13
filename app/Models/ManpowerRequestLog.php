<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManpowerRequestLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['manpower_request_id', 'user_id', 'is_system', 'event', 'text'];

    protected $casts = ['is_system' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
