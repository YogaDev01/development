<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAccessory extends Model
{
    protected $table = 'site_accessories';

    protected $fillable = ['site_id', 'accessories_id', 'qty'];

    protected $casts = ['qty' => 'integer'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function accessory()
    {
        return $this->belongsTo(Accessory::class, 'accessories_id');
    }
}
