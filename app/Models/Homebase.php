<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Homebase extends Model
{
    protected $table = 'homebase';

    protected $fillable = ['address', 'latitude', 'longitude', 'region_id'];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function details()
    {
        return $this->hasMany(HomebaseDetail::class, 'hb_id');
    }

    public function pics()
    {
        return $this->belongsToMany(MasterPic::class, 'homebase_detail', 'hb_id', 'pic_id')->withTimestamps();
    }
}
