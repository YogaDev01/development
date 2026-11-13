<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['candidate_id', 'user_id', 'description'];

    public function user() { return $this->belongsTo(User::class); }
}
