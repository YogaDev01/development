<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master Source Power. `code` adalah nilai yang disimpan di sites.source_power
 * (bukan FK id) — lihat migrasi 2026_11_09_000200.
 */
class SiteSourcePower extends Model
{
    protected $table = 'site_source_powers';

    protected $fillable = ['code', 'name'];

    public function sites()
    {
        return $this->hasMany(Site::class, 'source_power', 'code');
    }
}
