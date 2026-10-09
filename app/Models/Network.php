<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    protected $table = 'network';

    protected $fillable = ['penyedia_lc', 'hub', 'satelit', 'gateway', 'spotbeam'];

    public function sites()
    {
        return $this->hasMany(Site::class, 'network_id');
    }

    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->penyedia_lc, $this->satelit, $this->hub]);

        return $parts ? implode(' / ', $parts) : 'Network #' . $this->id;
    }
}
