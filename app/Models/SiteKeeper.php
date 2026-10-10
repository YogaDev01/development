<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Penjaga site; diisi bebas lewat form site, boleh lebih dari satu. */
class SiteKeeper extends Model
{
    protected $table = 'site_keepers';

    protected $fillable = ['site_id', 'name', 'phone'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
