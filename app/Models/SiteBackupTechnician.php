<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Teknisi cadangan sebuah site; dipilih dari user teknisi customer yang sama. */
class SiteBackupTechnician extends Model
{
    protected $table = 'site_backup_technicians';

    protected $fillable = ['site_id', 'user_id'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
