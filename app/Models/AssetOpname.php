<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stock opname aset per lokasi: COUNTING -> COMPLETED. */
class AssetOpname extends Model
{
    public const STATUSES = ['COUNTING', 'COMPLETED'];

    protected $fillable = [
        'number', 'asset_location_id', 'opname_date', 'status', 'notes',
        'created_by', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'opname_date'  => 'date',
        'completed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetOpnameItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
