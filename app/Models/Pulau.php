<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pulau extends Model
{
    protected $table = 'pulau';

    protected $fillable = ['name'];

    public function provinsi()
    {
        return $this->hasMany(Provinsi::class, 'pulau_id');
    }

    public function regions()
    {
        return $this->hasMany(Region::class, 'pulau_id');
    }

    public function sites()
    {
        return $this->hasMany(Site::class, 'pulau_id');
    }
}
