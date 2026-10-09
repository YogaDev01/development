<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penugasan (Assignment) — tugas bebas yang diberikan satu user ke user lain.
 * Muncul di Todo List penerima. Setiap perubahan dicatat di AssignmentLog,
 * termasuk komentar (action='comment').
 *
 * Alur status (satu arah, kecuali reopen oleh pemberi tugas):
 *   PENDING  -> penerima belum membuka detail tugas
 *   READ     -> penerima sudah membuka detail (otomatis, lihat
 *               AssignmentController::logs())
 *   ON_PROGRESS -> penerima menekan "Mulai Dikerjakan"
 *   CLOSED   -> ditandai selesai (penerima/pemberi/superadmin)
 *   CANCELLED -> dibatalkan (pemberi/superadmin)
 *
 * CLOSED & CANCELLED bersifat akhir: komentar dimatikan (lihat
 * AssignmentController::comment()), kecuali pemberi tugas membuka kembali
 * (reopen) ke PENDING.
 */
class Assignment extends Model
{
    protected $table = 'assignments';

    protected $fillable = [
        'title',
        'description',
        'assigner_id',
        'assignee_id',
        'due_date',
        'priority',
        'status',
        'read_at',
        'started_at',
        'done_at',
        'cancelled_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'read_at'      => 'datetime',
        'started_at'   => 'datetime',
        'done_at'      => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUSES   = ['PENDING', 'READ', 'ON_PROGRESS', 'CLOSED', 'CANCELLED'];
    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH'];

    /** Status yang masih bisa ditindaklanjuti (bukan status akhir). */
    public const OPEN_STATUSES = ['PENDING', 'READ', 'ON_PROGRESS'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssignmentLog::class)->orderBy('created_at')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'PENDING'     => 'Pending',
            'READ'        => 'Read',
            'ON_PROGRESS' => 'On Progress',
            'CLOSED'      => 'Closed',
            'CANCELLED'   => 'Cancelled',
            default       => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'PENDING'     => 'secondary',
            'READ'        => 'info',
            'ON_PROGRESS' => 'warning',
            'CLOSED'      => 'success',
            'CANCELLED'   => 'dark',
            default       => 'secondary',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'LOW'    => 'Rendah',
            'HIGH'   => 'Tinggi',
            default  => 'Sedang',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'HIGH'  => 'danger',
            'LOW'   => 'secondary',
            default => 'info',
        };
    }

    /** Lewat tenggat & masih berstatus terbuka (belum Closed/Cancelled). */
    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true)
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    /** Komentar dimatikan setelah tugas berstatus akhir (CLOSED/CANCELLED). */
    public function getCanCommentAttribute(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
