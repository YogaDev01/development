<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perpindahan aset. Satu tabel untuk lima jenis:
 *   HANDOVER  serah terima ke karyawan   -> COMPLETED (langsung)
 *   RETURN    pengembalian dari karyawan -> COMPLETED (langsung)
 *   TRANSFER  pindah lokasi/pemegang     -> PENDING -> APPROVED | REJECTED
 *   CHECKOUT  pinjam sementara           -> CHECKED_OUT -> RETURNED
 *   DISPOSAL  penghapusan aset           -> PENDING -> APPROVED | REJECTED
 */
class AssetMovement extends Model
{
    public const KINDS = [
        'HANDOVER' => 'Serah Terima',
        'RETURN'   => 'Pengembalian',
        'TRANSFER' => 'Transfer',
        'CHECKOUT' => 'Check-out',
        'DISPOSAL' => 'Disposal',
    ];

    /** Prefix penomoran per jenis. */
    public const PREFIX = [
        'HANDOVER' => 'BAST-AST',
        'RETURN'   => 'RTN-AST',
        'TRANSFER' => 'TRF-AST',
        'CHECKOUT' => 'CO-AST',
        'DISPOSAL' => 'DSP-AST',
    ];

    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'CHECKED_OUT', 'RETURNED'];

    public const DISPOSAL_METHODS = [
        'SOLD'        => 'Dijual',
        'SCRAPPED'    => 'Dibuang / Scrap',
        'DONATED'     => 'Dihibahkan',
        'WRITTEN_OFF' => 'Dihapusbukukan',
    ];

    protected $fillable = [
        'number', 'kind', 'asset_id', 'status', 'asset_status_before',
        'from_location_id', 'to_location_id', 'from_employee_id', 'to_employee_id',
        'movement_date', 'expected_return_date', 'returned_date',
        'condition_before', 'condition_after', 'reason', 'notes',
        'disposal_method', 'disposal_value', 'requested_by', 'approved_by',
        'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'movement_date'        => 'date',
        'expected_return_date' => 'date',
        'returned_date'        => 'date',
        'approved_at'          => 'datetime',
        'disposal_value'       => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'to_location_id');
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Check-out yang melewati tanggal kembali (presentasi). */
    public function isOverdue(): bool
    {
        return $this->kind === 'CHECKOUT'
            && $this->status === 'CHECKED_OUT'
            && $this->expected_return_date
            && $this->expected_return_date->lt(now()->startOfDay());
    }
}
