<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDetail extends Model
{
    protected $fillable = [
        'project_id',
        'cluster_id',
        'stage_id',
        'weight',
        'status',
        'submitted_done_by',
        'submitted_done_at',
        'plan_start',
        'plan_end',
        'actual_start',
        'actual_end',
        'notes',
    ];

    protected $casts = [
        'weight'            => 'integer',
        'plan_start'        => 'date',
        'plan_end'          => 'date',
        'actual_start'      => 'date',
        'actual_end'        => 'date',
        'submitted_done_at' => 'datetime',
    ];

    public const STATUSES = ['NOT_STARTED', 'ON_GOING', 'DONE'];

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

    public function monitoringLogs()
    {
        return $this->hasMany(ProjectMonitoringLog::class)->orderBy('created_at');
    }

    public function submittedDoneBy()
    {
        return $this->belongsTo(User::class, 'submitted_done_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'NOT_STARTED' => 'secondary',
            'ON_GOING'    => $this->submitted_done_at ? 'info' : 'warning',
            'DONE'        => 'success',
            default       => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'ON_GOING' && $this->submitted_done_at) {
            return 'Menunggu ACC';
        }
        return str_replace('_', ' ', $this->status);
    }
}