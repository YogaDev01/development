<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Penerimaan aset; setiap unit yang diterima langsung didaftarkan sebagai aset. */
class AssetReceiving extends Model
{
    protected $fillable = [
        'number', 'asset_purchase_request_id', 'item_name', 'asset_category_id',
        'asset_location_id', 'supplier', 'po_number', 'received_date', 'qty',
        'unit_cost', 'notes', 'received_by',
    ];

    protected $casts = [
        'qty'           => 'integer',
        'unit_cost'     => 'integer',
        'received_date' => 'date',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(AssetPurchaseRequest::class, 'asset_purchase_request_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
