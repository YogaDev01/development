<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jadwal maintenance berulang per aset (interval hari). */
class AssetMaintenanceSchedule extends Model
{
    /** Hari sebelum jatuh tempo yang dianggap "Due Soon". */
    public const DUE_SOON_DAYS = 14;

    protected $fillable = [
        'asset_id', 'title', 'maintenance_type', 'interval_days', 'next_due_date',
        'last_done_date', 'vendor', 'status', 'created_by',
    ];

    protected $casts = [
        'interval_days'  => 'integer',
        'next_due_date'  => 'date',
        'last_done_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /** OVERDUE / DUE_SOON / SCHEDULED / INACTIVE — presentasi, dihitung dari tanggal. */
    public function dueState(): string
    {
        if ($this->status !== 'ACTIVE') {
            return 'INACTIVE';
        }
        $sisa = now()->startOfDay()->diffInDays($this->next_due_date, false);

        return $sisa < 0 ? 'OVERDUE' : ($sisa <= self::DUE_SOON_DAYS ? 'DUE_SOON' : 'SCHEDULED');
    }
}
