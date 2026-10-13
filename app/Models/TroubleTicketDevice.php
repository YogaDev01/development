<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daftar perangkat yang bisa dipilih di tiket. Menggantikan isian bebas
 * trouble_tickets.device.
 */
class TroubleTicketDevice extends Model
{
    protected $table = 'trouble_ticket_devices';

    protected $fillable = ['name', 'status'];

    public static function active()
    {
        return static::where('status', 'ACTIVE')->orderBy('name')->get();
    }
}
