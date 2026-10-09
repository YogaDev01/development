<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kecamatan extends Model
{
    protected $table = 'kecamatan';

    protected $fillable = ['kota_id', 'name'];

    public function kota()
    {
        return $this->belongsTo(Kota::class, 'kota_id');
    }

    public function desa()
    {
        return $this->hasMany(Desa::class, 'kecamatan_id');
    }

    public function sites()
    {
        return $this->hasMany(Site::class, 'kecamatan_id');
    }
}
