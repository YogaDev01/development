<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectLogNote extends Model
{
    protected $table = 'project_log_notes';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'acted_by',
        'actor_name',
        'actor_role',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
