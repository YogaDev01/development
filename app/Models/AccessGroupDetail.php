<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\Models\Project;

class AccessGroupDetail extends Model
{
    protected $table = 'access_group_details';

    protected $fillable = [

        'access_group_id',
        'project_id',
        'cost_center_id'

    ];

    /*
    |--------------------------------------------------------------------------
    | ACCESS GROUP
    |--------------------------------------------------------------------------
    */

    public function accessGroup()
    {
        return $this->belongsTo(

            AccessGroup::class,
            'access_group_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROJECT (PO)
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /*
    |--------------------------------------------------------------------------
    | COST CENTER (Overhead)
    |--------------------------------------------------------------------------
    */

    public function costCenter()
    {
        return $this->belongsTo(\App\Models\CostCenter::class, 'cost_center_id');
    }
}