<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterSignature extends Model
{
    protected $fillable = ['letter_id', 'signer_id', 'sign_as', 'signed_at', 'signed_by'];

    protected $casts = ['signed_at' => 'datetime'];

    public function signer()
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}
