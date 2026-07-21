<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDetail extends Model
{
    protected $fillable = [
        'project_id',
        'cluster_id',
        'stage_id',
        'plan_start',
        'plan_end',
        'actual_start',
        'actual_end',
        'notes',
    ];

    protected $casts = [
        'plan_start'   => 'date',
        'plan_end'     => 'date',
        'actual_start' => 'date',
        'actual_end'   => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
}