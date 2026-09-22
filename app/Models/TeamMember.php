<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'position',
        'level',
        'ca_limit',
        'join_at',
        'left_at',
        'status',
    ];

    protected $casts = [
        'level'    => 'integer',
        'ca_limit' => 'integer',
        'join_at'  => 'date',
        'left_at'  => 'date',
    ];

    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCaLimitFormattedAttribute(): ?string
    {
        return $this->ca_limit === null ? null : number_format($this->ca_limit, 0, ',', '.');
    }
}
