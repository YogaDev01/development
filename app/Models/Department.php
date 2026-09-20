<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status === 'ACTIVE' ? 'success' : 'secondary';
    }
}
