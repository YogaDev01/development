<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Detail SLA Site: nilai SLA (persen 0–100) satu site pada satu periode. */
class SiteSla extends Model
{
    protected $table = 'site_slas';

    protected $fillable = ['site_sla_period_id', 'site_id', 'sla_percent'];

    protected $casts = ['sla_percent' => 'decimal:2'];

    public function period()
    {
        return $this->belongsTo(SiteSlaPeriod::class, 'site_sla_period_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Matriks SLA satu tahun untuk Summary: [site_id => [bulan => float]].
     * Opsional dibatasi satu customer. Satu query.
     */
    public static function matrix(int $year, ?int $customerId = null): array
    {
        $rows = static::query()
            ->join('site_sla_periods as p', 'p.id', '=', 'site_slas.site_sla_period_id')
            ->where('p.year', $year)
            ->when($customerId, fn ($q, $v) => $q->where('p.customer_id', $v))
            ->get(['site_slas.site_id', 'p.month', 'site_slas.sla_percent']);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->site_id][(int) $r->month] = (float) $r->sla_percent;
        }

        return $out;
    }

    /** Rata-rata bulan yang terisi; null bila belum ada data. */
    public static function average(array $months): ?float
    {
        return $months ? round(array_sum($months) / count($months), 2) : null;
    }
}
