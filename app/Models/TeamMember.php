<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    /*
    | ca_limit, join_at, left_at dihapus 2026-11-06 (migrasi
    | 2026_11_06_000400): limit CA ada di Master PIC, tanggal masuk/keluar
    | akan mengikuti Master Employee. Riwayat keanggotaan kini dijaga lewat
    | baris yang tidak pernah dihapus (keluar = INACTIVE) + team_logs.
    */
    protected $fillable = [
        'team_id',
        'user_id',
        'position',
        'level',
        'is_primary',
        'status',
    ];

    protected $casts = [
        'level'      => 'integer',
        'is_primary' => 'boolean',
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
}
