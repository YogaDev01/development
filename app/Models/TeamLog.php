<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat perubahan Team (struktur organisasi). Hanya ditambah lewat
 * Team::addLog() — tidak ada jalur update/hapus, supaya jejak perubahan
 * struktur tetap utuh untuk audit.
 */
class TeamLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'team_id',
        'action',
        'changes',
        'notes',
        'user_id',
        'user_name',
        'role',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    /** Label tampilan per action. */
    public const ACTIONS = [
        'created'      => 'Membuat team',
        'updated'      => 'Mengubah data team',
        'leader'       => 'Mengganti leader',
        'parent'       => 'Mengubah induk team',
        'access'       => 'Mengubah akses PO (Access Group)',
        'member_added' => 'Menambah anggota',
        'member_updated' => 'Mengubah anggota',
        'member_removed' => 'Menonaktifkan anggota',
        'activated'    => 'Mengaktifkan team',
        'deactivated'  => 'Menonaktifkan team',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /** Baris log tidak boleh diubah atau dihapus setelah dibuat. */
    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}
