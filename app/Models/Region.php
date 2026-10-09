<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'region';

    protected $fillable = ['region_name', 'pulau_id'];

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function homebases()
    {
        return $this->hasMany(Homebase::class, 'region_id');
    }
}
