<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Timeline tiket: pesan chat (type MESSAGE) dan kejadian workflow
 * (type EVENT, kode di kolom `event`). Ini jejak audit, bukan sekadar chat:
 *
 *   - immutable — hanya created_at, tidak ada route ubah/hapus;
 *   - pelaku selalu user login, ditulis server (tidak bisa dipalsukan form);
 *   - kejadian ditulis DI DALAM transaksi transisi statusnya, jadi status dan
 *     jejaknya selalu konsisten (gagal salah satu = batal keduanya). Berbeda
 *     dari AssignmentLog yang sengaja gagal-aman.
 */
class TroubleTicketMessage extends Model
{
    protected $table = 'trouble_ticket_messages';

    public const UPDATED_AT = null;

    public const TYPE_MESSAGE = 'MESSAGE';
    public const TYPE_EVENT = 'EVENT';

    /** Kode kejadian => label timeline. */
    public const EVENTS = [
        'created'           => 'Tiket dibuat',
        'assigned'          => 'Teknisi ditugaskan',
        'reassigned'        => 'Teknisi dialihkan',
        'responded'         => 'Teknisi merespons',
        'plan_submitted'    => 'Plan diajukan',
        'plan_corrected'    => 'Plan dikoreksi',
        'plan_approved'     => 'Plan disetujui',
        'plan_rejected'     => 'Plan ditolak',
        'progress_started'  => 'Troubleshooting dimulai',
        'closed'            => 'Tiket ditutup',
    ];

    protected $fillable = [
        'trouble_ticket_id', 'user_id', 'actor_name', 'actor_role',
        'type', 'event', 'body', 'metadata', 'client_token',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TroubleTicketAttachment::class, 'message_id');
    }

    public function getIsEventAttribute(): bool
    {
        return $this->type === self::TYPE_EVENT;
    }

    public function getEventLabelAttribute(): string
    {
        return self::EVENTS[$this->event] ?? (string) $this->event;
    }
}
