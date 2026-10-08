<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Riwayat satu Assignment. Immutable: dibuat lewat AssignmentLog::record(),
 * tidak pernah di-update/dihapus dari UI. Actor selalu dari user login.
 * Pola sama dengan RequestStatusLog/UserActivityLog ($timestamps=false).
 */
class AssignmentLog extends Model
{
    protected $table = 'assignment_logs';

    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'action',
        'actor_id',
        'actor_name',
        'actor_role',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'create' => 'Dibuat',
        'update' => 'Diubah',
        'done'   => 'Ditandai Selesai',
        'cancel' => 'Dibatalkan',
        'reopen' => 'Dibuka Kembali',
    ];

    /**
     * Catat satu kejadian. Gagal-aman: kalau pencatatan error, aksi bisnis
     * TIDAK ikut gagal — error hanya ditulis ke log aplikasi.
     */
    public static function record(Assignment $assignment, string $action, ?string $notes = null): ?self
    {
        try {
            $user = Auth::user();

            return self::create([
                'assignment_id' => $assignment->getKey(),
                'action'        => $action,
                'actor_id'      => $user?->getKey(),
                'actor_name'    => $user?->name,
                'actor_role'    => $user?->role?->name,
                'notes'         => $notes !== null ? mb_substr($notes, 0, 500) : null,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat AssignmentLog', [
                'assignment_id' => $assignment->getKey(),
                'action'        => $action,
                'message'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create' => 'info',
            'done'   => 'success',
            'cancel' => 'danger',
            'reopen' => 'warning',
            default  => 'secondary',
        };
    }
}
