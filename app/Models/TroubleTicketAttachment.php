<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran Trouble Ticket. Berkas di disk "secure" (folder
 * trouble_ticket_attachments) dengan nama acak; diunduh lewat
 * TroubleTicketController::download() — atau /storage/{path} — yang
 * keduanya memeriksa keterlihatan tiketnya.
 */
class TroubleTicketAttachment extends Model
{
    protected $table = 'trouble_ticket_attachments';

    public const UPDATED_AT = null;

    public const FOLDER = 'trouble_ticket_attachments';

    /** Batas ukuran per berkas (KB) — dicek juga di browser. */
    public const MAX_KB = 10240;

    /** Paling banyak berkas per kirim. */
    public const MAX_FILES = 10;

    /**
     * Ekstensi yang diterima (keputusan 9 Okt 2026: allowlist umum).
     * Divalidasi dua kali: ekstensi nama berkas DAN tipe hasil deteksi isi
     * (finfo) — salah satunya saja tidak cukup.
     */
    public const EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'mp4', 'mov', 'zip',
    ];

    /**
     * Tipe hasil deteksi ISI berkas (finfo) yang diterima — pemeriksaan
     * terakhir di TroubleTicketWorkflow sebelum berkas disimpan. HTML, SVG,
     * skrip, dan executable tidak ada di sini sehingga ditolak walau namanya
     * berakhiran .pdf/.jpg.
     */
    public const DETECTED_MIME = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
        'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/csv',
        'video/mp4', 'video/quicktime',
        'application/zip', 'application/x-zip-compressed',
    ];

    /** Tipe yang aman dibuka langsung di peramban; sisanya dipaksa unduh. */
    public const INLINE_MIME = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4',
    ];

    protected $fillable = [
        'trouble_ticket_id', 'message_id', 'uploaded_by', 'original_name',
        'storage_path', 'mime_type', 'size_bytes', 'file_hash',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    /** Aturan validasi satu berkas. */
    public static function rules(): array
    {
        $ext = implode(',', self::EXTENSIONS);

        return ['file', 'max:' . self::MAX_KB, 'mimes:' . $ext, 'extensions:' . $ext];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsImageAttribute(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    public function getIsInlineAttribute(): bool
    {
        return in_array($this->mime_type, self::INLINE_MIME, true);
    }

    public function getUrlAttribute(): string
    {
        return route('trouble-ticket.attachment', $this);
    }

    public function getSizeLabelAttribute(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb >= 1024
            ? number_format($kb / 1024, 1, ',', '.') . ' MB'
            : number_format(max(1, $kb), 0, ',', '.') . ' KB';
    }
}
