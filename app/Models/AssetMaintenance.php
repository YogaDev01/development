<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pekerjaan maintenance aset. IN_PROGRESS/WAITING_PARTS menahan aset di
 * status MAINTENANCE; COMPLETED/CANCELLED mengembalikan status sebelumnya
 * (asset_status_before). Lihat AssetOperationController.
 */
class AssetMaintenance extends Model
{
    public const TYPES = [
        'PREVENTIVE'  => 'Preventive',
        'CORRECTIVE'  => 'Corrective',
        'CALIBRATION' => 'Calibration',
        'INSPECTION'  => 'Inspection',
        'OVERHAUL'    => 'Overhaul',
    ];

    public const STATUSES = ['IN_PROGRESS', 'WAITING_PARTS', 'COMPLETED', 'CANCELLED'];

    public const OPEN_STATUSES = ['IN_PROGRESS', 'WAITING_PARTS'];

    protected $fillable = [
        'number', 'asset_id', 'asset_maintenance_schedule_id', 'maintenance_type', 'problem',
        'vendor', 'estimated_cost', 'actual_cost', 'start_date', 'end_date', 'status',
        'asset_status_before', 'result_notes', 'created_by',
    ];

    protected $casts = [
        'estimated_cost' => 'integer',
        'actual_cost'    => 'integer',
        'start_date'     => 'date',
        'end_date'       => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceSchedule::class, 'asset_maintenance_schedule_id');
    }
}
