<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastDetail extends Model
{
    protected $fillable = [
        'forecast_id',
        'description',
        // PO melekat di BARIS, bukan header — satu forecast (satu rentang waktu)
        // bisa memuat rencana untuk beberapa PO, atau tanpa PO sama sekali.
        'project_id',
        // Arah rencana: satu forecast bisa memuat penerimaan dan pengeluaran.
        'type',
        // Rekening melekat di baris, bukan di header — satu forecast bisa
        // menyentuh beberapa rekening.
        'source_fund_id',
        'amount',
        'actual_amount',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'actual_amount' => 'integer',
    ];

    public const TYPES = ['IN' => 'Masuk', 'OUT' => 'Keluar'];

    protected static function booted(): void
    {
        static::saved(function (ForecastDetail $d) {
            $d->forecast?->syncForecastAmount();
            $d->forecast?->syncActualAmount();
        });
        static::deleted(function (ForecastDetail $d) {
            $d->forecast?->syncForecastAmount();
            $d->forecast?->syncActualAmount();
        });
    }

    public function forecast()
    {
        return $this->belongsTo(Forecast::class);
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return $this->type === 'IN' ? 'success' : 'danger';
    }

    /** Nilai bertanda — dipakai saat menjumlah rencana bersih. */
    public function getSignedAmountAttribute(): int
    {
        return $this->type === 'IN' ? $this->amount : -$this->amount;
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }

    public function getActualAmountFormattedAttribute(): string
    {
        return number_format($this->actual_amount, 0, ',', '.');
    }
}
