<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master Tipe Site. `code` adalah nilai yang disimpan di sites.type
 * (bukan FK id) — lihat migrasi 2026_11_09_000200.
 */
class SiteType extends Model
{
    protected $table = 'site_types';

    protected $fillable = ['code', 'name'];

    public function sites()
    {
        return $this->hasMany(Site::class, 'type', 'code');
    }
}
