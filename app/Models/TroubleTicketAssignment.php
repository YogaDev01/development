<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat penugasan teknisi. Baris aktif = unassigned_at NULL (satu per
 * tiket, dijaga TroubleTicketWorkflow di bawah row lock tiketnya).
 */
class TroubleTicketAssignment extends Model
{
    protected $table = 'trouble_ticket_assignments';

    public const UPDATED_AT = null;

    protected $fillable = [
        'trouble_ticket_id', 'assigned_to', 'assigned_by', 'assigned_at',
        'responded_at', 'unassigned_at', 'reason',
    ];

    protected $casts = [
        'assigned_at'   => 'datetime',
        'responded_at'  => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
