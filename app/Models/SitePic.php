<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePic extends Model
{
    protected $table = 'site_pics';

    protected $fillable = ['site_id', 'pic_id'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function pic()
    {
        return $this->belongsTo(MasterPic::class, 'pic_id');
    }
}
