<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\User;
use App\Models\Cluster;

class ClusterGroup extends Model
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'cluster_groups';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'code',
        'name',
        'input_by',

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION : CLUSTERS
    |--------------------------------------------------------------------------
    */

    public function clusters()
    {
        return $this->hasMany(Cluster::class, 'group_id');
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
}