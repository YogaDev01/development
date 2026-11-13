<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Header SLA Site: satu customer + satu periode (tahun-bulan). Detail di site_slas. */
class SiteSlaPeriod extends Model
{
    protected $table = 'site_sla_periods';

    protected $fillable = ['customer_id', 'year', 'month', 'created_by', 'updated_by'];

    protected $casts = ['year' => 'integer', 'month' => 'integer'];

    public const MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function details()
    {
        return $this->hasMany(SiteSla::class, 'site_sla_period_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function periodLabel(): string
    {
        return (self::MONTHS[$this->month] ?? $this->month) . ' ' . $this->year;
    }
}
