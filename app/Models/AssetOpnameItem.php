<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetOpnameItem extends Model
{
    public const RESULTS = [
        'MATCHED'     => 'Sesuai',
        'MISSING'     => 'Tidak Ditemukan',
        'DISCREPANCY' => 'Selisih Data',
    ];

    protected $fillable = ['asset_opname_id', 'asset_id', 'result', 'condition', 'notes'];

    public function opname(): BelongsTo
    {
        return $this->belongsTo(AssetOpname::class, 'asset_opname_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }
}
