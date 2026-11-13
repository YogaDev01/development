<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateDocument extends Model
{
    protected $fillable = ['candidate_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];
}
