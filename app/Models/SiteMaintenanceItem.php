<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Perangkat yang dicatat dalam satu Preventive Maintenance (SN sebelum/sesudah). */
class SiteMaintenanceItem extends Model
{
    protected $table = 'site_maintenance_items';

    protected $fillable = ['maintenance_id', 'material_id', 'device_name', 'sn_before', 'sn_after'];

    public function maintenance()
    {
        return $this->belongsTo(SiteMaintenance::class, 'maintenance_id');
    }

    public function material()
    {
        return $this->belongsTo(MasterMaterial::class, 'material_id');
    }
}
