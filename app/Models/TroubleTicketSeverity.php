<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Severity Trouble Ticket. Tidak ada UI: diisi langsung di basis data (tabel
 * trouble_ticket_severities). Tiket menyimpan `code`-nya (trouble_tickets.severity,
 * FK ON UPDATE CASCADE). `level` 1 = paling parah — menentukan urutan & warna.
 */
class TroubleTicketSeverity extends Model
{
    protected $table = 'trouble_ticket_severities';

    protected $fillable = ['code', 'name', 'level', 'status'];

    protected $casts = [
        'level' => 'integer',
    ];

    /** Severity aktif, paling parah dulu. */
    public static function active()
    {
        return static::where('status', 'ACTIVE')->orderBy('level')->orderBy('name')->get();
    }
}
