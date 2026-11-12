<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Riwayat satu aset. Immutable: dibuat lewat AssetLog::record(), tidak pernah
 * diubah/dihapus dari UI. Pola sama dengan AssignmentLog ($timestamps=false).
 */
class AssetLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['asset_id', 'user_id', 'action', 'description', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Asset $asset, string $action, ?string $description = null): self
    {
        return self::create([
            'asset_id'    => $asset->id,
            'user_id'     => Auth::id(),
            'action'      => $action,
            'description' => $description !== null ? mb_substr($description, 0, 1000) : null,
            'created_at'  => now(),
        ]);
    }
}
