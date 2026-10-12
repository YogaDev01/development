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
        'responded'         => 'Tiket dibaca teknisi',
        'remote_started'    => 'Ditangani remote (Non-Visit)',
        'plan_created'      => 'Plan visit dibuat',
        'plan_updated'      => 'Tanggal plan visit diubah',
        'trip_due'          => 'Masuk tahap Trip',
        'travel_started'    => 'Perjalanan dimulai',
        'travel_cancelled'  => 'Visit dibatalkan',
        'arrived_on_site'   => 'Tiba di site',
        'progress_started'  => 'Progres dimulai',
        'progress_done'     => 'Form close dikirim',
        'close_rejected'    => 'Penutupan ditolak',
        'closed'            => 'Tiket ditutup (approved)',
        // Alur lama (sebelum 12 Okt 2026) — label dipertahankan untuk riwayat.
        'plan_submitted'    => 'Plan diajukan',
        'plan_corrected'    => 'Plan dikoreksi',
        'plan_approved'     => 'Plan disetujui',
        'plan_rejected'     => 'Plan ditolak',
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
