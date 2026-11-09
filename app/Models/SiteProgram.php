<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master Program Site. `code` adalah nilai yang disimpan di sites.program
 * (bukan FK id) — lihat migrasi 2026_11_09_000200.
 */
class SiteProgram extends Model
{
    protected $table = 'site_programs';

    protected $fillable = ['code', 'name'];

    public function sites()
    {
        return $this->hasMany(Site::class, 'program', 'code');
    }
}
