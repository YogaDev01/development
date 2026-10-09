<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteMaterial extends Model
{
    protected $table = 'site_material';

    protected $fillable = ['site_id', 'material_id'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function material()
    {
        return $this->belongsTo(MasterMaterial::class, 'material_id');
    }
}
