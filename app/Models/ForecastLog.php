<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Jejak aktivitas satu Forecast. Polanya sama dengan DeliveryNoteLog:
 * nama pelaku ikut disalin supaya riwayat tetap terbaca kalau penggunanya
 * kelak dihapus.
 */
class ForecastLog extends Model
{
    protected $fillable = [
        'forecast_id',
        'action',
        'actor_id',
        'actor_name',
        'notes',
    ];

    public const ACTIONS = [
        'CREATED'        => 'Dibuat',
        'UPDATED'        => 'Diubah',
        'ACTIVATED'      => 'Diaktifkan',
        'ACTUAL_ADDED'   => 'Realisasi Ditambah',
        'ACTUAL_REMOVED' => 'Realisasi Dihapus',
        'CLOSED'         => 'Ditutup',
        'AUTO_CLOSED'    => 'Ditutup Otomatis',
    ];

    public function forecast()
    {
        return $this->belongsTo(Forecast::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Pencatat tunggal, supaya setiap pemanggil tidak perlu mengulang cara
     * mengambil pelaku. Auth::user() boleh kosong (jalur console
     * `forecast:auto-close`), dan itu memang dicatat sebagai sistem.
     */
    public static function record(int $forecastId, string $action, ?string $notes = null): self
    {
        $user = Auth::user();

        return self::create([
            'forecast_id' => $forecastId,
            'action'      => $action,
            'actor_id'    => $user?->id,
            'actor_name'  => $user?->name ?? 'Sistem',
            'notes'       => $notes,
        ]);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'CREATED'                  => 'secondary',
            'UPDATED'                  => 'info',
            'ACTIVATED', 'ACTUAL_ADDED' => 'success',
            'ACTUAL_REMOVED'           => 'danger',
            'CLOSED', 'AUTO_CLOSED'    => 'dark',
            default                    => 'secondary',
        };
    }
}
