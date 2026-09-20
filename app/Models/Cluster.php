<?php

namespace App\Models;

use App\Models\User;
use App\Models\ClusterGroup;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    protected $fillable = [

        'code',
        'name',
        'status',
        'project_id',
        'group_id',
        'input_by',

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION : GROUP
    |--------------------------------------------------------------------------
    */

    public function group()
    {
        return $this->belongsTo(ClusterGroup::class, 'group_id');
    }

    /*
    |--------------------------------------------------------------------------
    | RELATION : USER INPUT
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_groups'
        );
    }

    public function details()
    {
        return $this->hasMany(ProjectDetail::class)->orderBy('id');
    }

    public function monitoringLogs()
    {
        return $this->hasMany(ProjectMonitoringLog::class)->orderBy('created_at');
    }
}