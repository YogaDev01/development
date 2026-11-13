<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['letter_id', 'user_id', 'action', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
