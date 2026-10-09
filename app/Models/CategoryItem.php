<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryItem extends Model
{
    protected $table = 'category_item';

    public const TYPES = [
        'MATERIAL'    => 'Material',
        'ACCESSORIES' => 'Accessories',
        'TOOLS'       => 'Tools',
    ];

    // Tipe kategori yang boleh dipakai tiap katalog.
    public const MATERIAL_TYPES    = ['MATERIAL', 'TOOLS'];
    public const ACCESSORIES_TYPES = ['ACCESSORIES'];

    protected $fillable = ['type', 'category_name', 'merk'];

    public function materials()
    {
        return $this->hasMany(MasterMaterial::class, 'category_id');
    }

    public function accessories()
    {
        return $this->hasMany(Accessory::class, 'category_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->category_name . ' — ' . $this->merk;
    }
}
