<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penugasan (Assignment) — tugas bebas yang diberikan satu user ke user lain.
 * Muncul di Todo List penerima; bisa ditandai selesai, diubah, atau dibatalkan
 * oleh pemberinya. Setiap perubahan dicatat di AssignmentLog.
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
        'done_at',
        'cancelled_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'done_at'      => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUSES  = ['OPEN', 'DONE', 'CANCELLED'];
    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH'];

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
        return $this->hasMany(AssignmentLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'OPEN'      => 'Terbuka',
            'DONE'      => 'Selesai',
            'CANCELLED' => 'Dibatalkan',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DONE'      => 'success',
            'CANCELLED' => 'secondary',
            default     => 'warning',
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

    /** Lewat tenggat & masih terbuka. */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'OPEN'
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }
}
