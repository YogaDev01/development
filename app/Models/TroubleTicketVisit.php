<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sesi kunjungan ke site. Skema mendukung banyak sesi per tiket; MVP
 * membatasi satu sesi (lihat TroubleTicketWorkflow::depart()). Satu sesi
 * AKTIF per tiket dijaga unique index active_ticket_id di basis data.
 */
class TroubleTicketVisit extends Model
{
    protected $table = 'trouble_ticket_visits';

    protected $fillable = [
        'trouble_ticket_id', 'technician_id', 'departed_at', 'arrived_at', 'travel_seconds',
        'departure_latitude', 'departure_longitude', 'arrival_latitude', 'arrival_longitude', 'notes',
    ];

    protected $casts = [
        'departed_at'    => 'datetime',
        'arrived_at'     => 'datetime',
        'travel_seconds' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
