<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetInspection extends Model
{
    public const RESULTS = [
        'PASSED'        => 'Lulus',
        'MINOR_FINDING' => 'Temuan Minor',
        'MAJOR_FINDING' => 'Temuan Mayor',
    ];

    protected $fillable = [
        'number', 'asset_id', 'inspection_date', 'inspector_name', 'condition',
        'result', 'findings', 'follow_up', 'created_by',
    ];

    protected $casts = ['inspection_date' => 'date'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }
}
