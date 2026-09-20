<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessGroup extends Model
{
    protected $table = 'access_groups';

    protected $fillable = [

        'name'

    ];

    /*
    |--------------------------------------------------------------------------
    | DETAILS
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(

            AccessGroupDetail::class,
            'access_group_id',
            'id'

        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROJECTS
    |--------------------------------------------------------------------------
    */

    public function projects()
    {
        return $this->hasManyThrough(

            Project::class,
            AccessGroupDetail::class,
            'access_group_id',
            'id',
            'id',
            'project_id'

        );
    }

    /*
    |--------------------------------------------------------------------------
    | USERS
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(

            User::class,
            'access_group_id',
            'id'

        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS — id yang bisa diakses group ini
    |--------------------------------------------------------------------------
    */

    /** ID project (PO) yang diakses group ini */
    public function projectIds()
    {
        return $this->details
            ->pluck('project_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** ID cost center (overhead) yang diakses group ini */
    public function costCenterIds()
    {
        return $this->details
            ->pluck('cost_center_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** ID Team yang di-assign ke group ini (akses PO lewat Team -> Access Group -> PO) */
    public function teamIds()
    {
        return $this->details
            ->pluck('team_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}