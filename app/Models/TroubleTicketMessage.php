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

    /**
     * Kode kejadian => label timeline.
     *
     * Label log sengaja berbahasa Inggris (permintaan 13 Okt 2026) — satu-satunya
     * bagian antarmuka Trouble Ticket yang tidak berbahasa Indonesia.
     */
    public const EVENTS = [
        'created'           => 'Ticket created',
        // Hanya dipakai riwayat lama: sejak 13 Okt 2026 penugasan awal tidak
        // lagi ditulis ke log (teknisi sudah terbaca di kepala tiket).
        'assigned'          => 'Technician assigned',
        // Dinamai seperti tombolnya di layar (permintaan 16 Okt 2026), jadi
        // satu-satunya label kejadian yang berbahasa Indonesia.
        'reassigned'        => 'Change Teknisi',
        'responded'         => 'Ticket read',
        // Teknisi membuka tiket = tiket masuk tahap Remote (19 Okt 2026):
        // satu kejadian, bukan 'responded' + 'remote_started' berurutan.
        'responded_remote'  => 'Ticket read — Remote',
        // Dipakai riwayat lama (tombol Remote, sebelum 19 Okt 2026).
        'remote_started'    => 'Remote',
        'plan_created'      => 'Plan visit created',
        'plan_updated'      => 'Plan visit date changed',
        // Approval plan & biaya oleh supervisor (20 Okt 2026). Approve
        // membuat Cost Request berstatus Draft; nomornya ada di metadata.
        'plan_budget_approved' => 'Plan & budget approved',
        'plan_budget_rejected' => 'Plan & budget rejected',
        // Pencairan CR yang lahir dari plan visit (20 Okt 2026).
        'cost_request_paid' => 'Cost Request paid',
        // Dipakai riwayat lama: sejak 20 Okt 2026 tahap Trip dimulai bersama
        // keberangkatan ('travel_started'), tidak ada kejadian tersendiri.
        'trip_due'          => 'Moved to Trip',
        'travel_started'    => 'Travel started',
        'travel_cancelled'  => 'Visit cancelled',
        // Fitur Stop Chat dihapus 19 Okt 2026; labelnya tetap ada supaya log
        // tiket yang terlanjur mencatat kejadian ini masih terbaca.
        'chat_stopped'      => 'Diskusi dihentikan',
        'chat_reopened'     => 'Diskusi dibuka kembali',
        'arrived_on_site'   => 'Arrived on site',
        'progress_started'  => 'Progress started',
        'progress_done'     => 'Close form submitted',
        'close_rejected'    => 'Close rejected',
        'closed'            => 'Closed',
        // Alur lama (sebelum 12 Okt 2026) — tetap terbaca di riwayat.
        'plan_submitted'    => 'Plan submitted',
        'plan_corrected'    => 'Plan corrected',
        'plan_approved'     => 'Plan approved',
        'plan_rejected'     => 'Plan rejected',
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

    /**
     * Label kejadian. Penutupan lewat jalur Remote ditulis "Closed - Remote":
     * jalurnya dibaca dari metadata kejadian, dan untuk log lama (sebelum 16
     * Okt 2026) dari tiketnya bila relasinya sudah dimuat.
     */
    public function getEventLabelAttribute(): string
    {
        $label = self::EVENTS[$this->event] ?? (string) $this->event;

        if ($this->event === 'closed') {
            $jalur = $this->metadata['visit_decision']
                ?? ($this->relationLoaded('ticket') ? $this->ticket?->visit_decision : null);

            if ($jalur === TroubleTicket::NON_VISIT) {
                $label .= ' - Remote';
            }
        }

        return $label;
    }
}
