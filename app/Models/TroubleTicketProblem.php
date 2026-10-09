<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dropdown Problem Trouble Ticket. Tidak ada UI: diisi langsung di basis
 * data (tabel trouble_ticket_problems). Hanya status ACTIVE yang ditawarkan.
 */
class TroubleTicketProblem extends Model
{
    protected $table = 'trouble_ticket_problems';

    protected $fillable = ['name', 'requires_evidence', 'status'];

    protected $casts = [
        'requires_evidence' => 'boolean',
    ];
}
