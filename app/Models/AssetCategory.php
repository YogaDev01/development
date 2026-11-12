<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kategori aset + parameter penyusutan default (umur, metode, % residu). */
class AssetCategory extends Model
{
    public const METHODS = [
        'STRAIGHT_LINE'     => 'Garis Lurus',
        'DECLINING_BALANCE' => 'Saldo Menurun Ganda',
    ];

    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    protected $fillable = [
        'code', 'name', 'life_years', 'depreciation_method', 'residual_percent',
        'account', 'description', 'status',
    ];

    protected $casts = [
        'life_years'       => 'integer',
        'residual_percent' => 'float',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'ACTIVE');
    }
}
