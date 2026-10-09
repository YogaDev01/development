<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provinsi extends Model
{
    protected $table = 'provinsi';

    protected $fillable = ['pulau_id', 'name'];

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function kota()
    {
        return $this->hasMany(Kota::class, 'provinsi_id');
    }

    public function sites()
    {
        return $this->hasMany(Site::class, 'provinsi_id');
    }
}
