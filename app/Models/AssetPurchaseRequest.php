<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Permintaan pembelian aset: PENDING -> APPROVED/REJECTED -> RECEIVED. */
class AssetPurchaseRequest extends Model
{
    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'RECEIVED'];

    protected $fillable = [
        'number', 'item_name', 'asset_category_id', 'department_id', 'qty',
        'estimated_unit_cost', 'needed_date', 'justification', 'status',
        'requested_by', 'approved_by', 'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'qty'                 => 'integer',
        'estimated_unit_cost' => 'integer',
        'needed_date'         => 'date',
        'approved_at'         => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(AssetReceiving::class);
    }

    public function totalEstimate(): int
    {
        return (int) $this->qty * (int) $this->estimated_unit_cost;
    }

    /** Unit yang sudah diterima dari PR ini. */
    public function receivedQty(): int
    {
        return (int) $this->receivings->sum('qty');
    }
}
