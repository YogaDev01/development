<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran pengajuan biaya Trouble Ticket. Berkas di disk "secure"; diunduh
 * lewat TroubleTicketController::downloadRequestAttachment() yang memeriksa
 * keterlihatan tiketnya. `item_id` NULL = lampiran umum pengajuan.
 *
 * Lampiran tidak pernah dihapus berkasnya: revisi yang mempertahankan sebuah
 * lampiran menulis baris baru yang menunjuk path yang sama.
 */
class TroubleTicketRequestAttachment extends Model
{
    protected $fillable = [
        'trouble_ticket_request_id', 'item_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketRequest::class, 'trouble_ticket_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketRequestItem::class, 'item_id');
    }
}
