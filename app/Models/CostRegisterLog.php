<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat workflow + catatan chat Cost Register. Immutable (hanya created_at);
 * action 'note' = catatan bebas tanpa perpindahan status.
 */
class CostRegisterLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cost_register_id',
        'action',
        'status_from',
        'status_to',
        'notes',
        'user_id',
        'user_name',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'created'  => 'Dibuat',
        'verified' => 'Diajukan',
        'approved' => 'Disetujui',
        'revised'  => 'Dikembalikan untuk Revisi',
        'cancelled' => 'Dibatalkan',
        'batched'  => 'Dimasukkan ke Batch',
        'unbatched' => 'Dikeluarkan dari Batch',
        'paid'     => 'Telah Dibayar',
        'note'     => 'Catatan',
    ];

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'created'              => 'secondary',
            'verified'             => 'info',
            'approved', 'paid'     => 'success',
            'revised', 'cancelled', 'unbatched' => 'danger',
            'batched'              => 'dark',
            default                => 'secondary',
        };
    }
}
