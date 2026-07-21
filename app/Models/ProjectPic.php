<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPic extends Model
{
    protected $fillable = [
        'project_id',
        'pic',
        'position',
        'contact',
        'remark',
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
}