<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu catatan Preventive Maintenance untuk satu site. */
class SiteMaintenance extends Model
{
    protected $table = 'site_maintenances';

    protected $fillable = ['site_id', 'performed_at', 'performed_by', 'notes', 'input_by'];

    protected $casts = ['performed_at' => 'date'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function items()
    {
        return $this->hasMany(SiteMaintenanceItem::class, 'maintenance_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
