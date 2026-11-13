<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterAttachment extends Model
{
    protected $fillable = ['letter_id', 'path', 'original_name', 'mime', 'size', 'kind', 'uploaded_by'];
}
