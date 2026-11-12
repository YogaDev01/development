<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lampiran aset (faktur, foto, garansi) — disk 'secure', dibuka lewat FileAccess. */
class AssetAttachment extends Model
{
    protected $fillable = ['asset_id', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
