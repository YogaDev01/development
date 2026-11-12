<?php

namespace App\Models;

use App\Support\AssetDepreciation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Aset tetap perusahaan.
 *
 * Status (perpindahannya hanya di controller modul Asset, selalu di dalam
 * transaksi dengan row lock):
 *   AVAILABLE   tersedia di lokasi, tanpa pemegang
 *   ASSIGNED    diserahterimakan ke karyawan (HANDOVER)
 *   IN_USE      terpasang/dipakai tanpa pemegang perorangan (infrastruktur)
 *   CHECKED_OUT dipinjam sementara (CHECKOUT)
 *   MAINTENANCE sedang maintenance
 *   DISPOSED    sudah dihapus (DISPOSAL disetujui) — final
 */
class Asset extends Model
{
    use SoftDeletes;

    public const STATUSES = ['AVAILABLE', 'ASSIGNED', 'IN_USE', 'CHECKED_OUT', 'MAINTENANCE', 'DISPOSED'];

    /** Status awal yang boleh dipilih saat registrasi. */
    public const INITIAL_STATUSES = ['AVAILABLE', 'IN_USE'];

    public const CONDITIONS = [
        'EXCELLENT' => 'Sangat Baik',
        'GOOD'      => 'Baik',
        'FAIR'      => 'Cukup',
        'POOR'      => 'Kurang',
        'DAMAGED'   => 'Rusak',
    ];

    public const ACQUISITION_TYPES = [
        'PURCHASE'    => 'Pembelian',
        'TRANSFER_IN' => 'Transfer Masuk',
        'DONATION'    => 'Hibah',
        'LEASE'       => 'Sewa Guna',
    ];

    protected $fillable = [
        'code', 'name', 'asset_category_id', 'type', 'brand', 'model', 'serial_number',
        'asset_location_id', 'department_id', 'employee_id', 'custodian_name',
        'acquisition_date', 'acquisition_type', 'cost', 'residual_value', 'life_years',
        'depreciation_method', 'condition', 'status', 'supplier', 'po_number',
        'asset_receiving_id', 'warranty_end', 'specs', 'description', 'disposed_at',
        'created_by', 'updated_by', 'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'warranty_end'     => 'date',
        'disposed_at'      => 'date',
        'cost'             => 'integer',
        'residual_value'   => 'integer',
        'life_years'       => 'integer',
        'specs'            => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class)->orderByDesc('id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->orderByDesc('id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class)->orderByDesc('id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(AssetInspection::class)->orderByDesc('id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AssetMaintenanceSchedule::class);
    }

    /** Aset yang belum dihapus (status bukan DISPOSED). */
    public function scopeLive($q)
    {
        return $q->where('status', '!=', 'DISPOSED');
    }

    public function isDisposed(): bool
    {
        return $this->status === 'DISPOSED';
    }

    /** Nilai buku — satu sumber: App\Support\AssetDepreciation. */
    public function bookValue(?\DateTimeInterface $at = null): int
    {
        return AssetDepreciation::bookValue($this, $at);
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? (string) $this->condition;
    }

    public function holderName(): string
    {
        return $this->employee?->name ?? ($this->custodian_name ?: '-');
    }
}
