<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lokasi fisik aset (kantor, gudang, site, kantor proyek). */
class AssetLocation extends Model
{
    public const TYPES = [
        'OFFICE'    => 'Kantor',
        'BRANCH'    => 'Kantor Cabang',
        'PROJECT'   => 'Kantor Proyek',
        'SITE'      => 'Site',
        'WAREHOUSE' => 'Gudang',
    ];

    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    protected $fillable = ['code', 'name', 'type', 'address', 'pic_name', 'status'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'ACTIVE');
    }
}
