<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    protected $fillable = [
        // PO/Project TIDAK di sini: satu forecast adalah satu rentang waktu yang
        // isinya bisa menyentuh beberapa PO sekaligus (atau tidak sama sekali,
        // untuk rencana overhead). FK-nya melekat di ForecastDetail.
        'notes',
        'date_start',
        'date_end',
        'forecast_amount',
        'actual_amount',
        'status',
        'created_by',
        'activated_at',
        'closed_at',
    ];

    protected $casts = [
        'date_start'      => 'date',
        'date_end'        => 'date',
        'forecast_amount' => 'integer',
        'actual_amount'   => 'integer',
        'activated_at'    => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public const STATUSES = ['DRAFT', 'ACTIVE', 'CLOSED'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        // Kolom `date` per baris sudah dihapus (rentangnya ada di header),
        // jadi urutannya mengikuti urutan input.
        return $this->hasMany(ForecastDetail::class)->orderBy('id');
    }

    /**
     * PO yang tersentuh forecast ini — dikumpulkan dari DETAIL, karena di
     * situlah PO sekarang melekat. Satu forecast bisa menyentuh beberapa PO.
     */
    public function projects()
    {
        return $this->hasManyThrough(
            Project::class,
            ForecastDetail::class,
            'forecast_id',
            'id',
            'id',
            'project_id'
        );
    }

    /**
     * Rekening yang tersentuh forecast ini — dikumpulkan dari DETAIL, karena
     * di situlah rekening sekarang melekat. Satu forecast bisa menyentuh
     * beberapa rekening sekaligus.
     */
    public function sourceFunds()
    {
        return $this->hasManyThrough(
            SourceFund::class,
            ForecastDetail::class,
            'forecast_id',
            'id',
            'id',
            'source_fund_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * forecast_amount = rencana MASUK - rencana KELUAR.
     *
     * Sejak baris forecast punya arah (IN/OUT), menjumlah semuanya begitu saja
     * akan menghasilkan angka yang tidak berarti apa-apa: rencana pengeluaran
     * akan menaikkan "nilai forecast". Yang bermakna adalah selisihnya.
     */
    public function syncForecastAmount(): void
    {
        $sum = (int) $this->details()->where('type', 'IN')->sum('amount')
             - (int) $this->details()->where('type', 'OUT')->sum('amount');
        if ($sum !== (int) $this->forecast_amount) {
            $this->update(['forecast_amount' => $sum]);
        }
    }

    /** actual_amount = SUM(details.actual_amount) — diinput per item, bukan header. */
    public function syncActualAmount(): void
    {
        $sum = (int) $this->details()->where('type', 'IN')->sum('actual_amount')
             - (int) $this->details()->where('type', 'OUT')->sum('actual_amount');
        if ($sum !== (int) $this->actual_amount) {
            $this->update(['actual_amount' => $sum]);
        }
    }

    public function getVarianceAttribute(): int
    {
        return (int) $this->forecast_amount - (int) $this->actual_amount;
    }

    public function getAchievementPctAttribute(): float
    {
        return $this->forecast_amount > 0
            ? round(($this->actual_amount / $this->forecast_amount) * 100, 1)
            : 0.0;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'  => 'secondary',
            'ACTIVE' => 'success',
            'CLOSED' => 'dark',
            default  => 'secondary',
        };
    }

    public function getForecastAmountFormattedAttribute(): string
    {
        return number_format($this->forecast_amount, 0, ',', '.');
    }

    public function getActualAmountFormattedAttribute(): string
    {
        return number_format($this->actual_amount, 0, ',', '.');
    }

    public function getVarianceFormattedAttribute(): string
    {
        return number_format($this->variance, 0, ',', '.');
    }

    /**
     * ACTIVE = locked total (header & detail) di backend — bukan cuma UI.
     * Aksi yang tersedia hanya Close & update Actual Amount.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, ['ACTIVE', 'CLOSED'], true);
    }

    /**
     * Judul yang ditampilkan: catatannya, karena itulah yang membedakan satu
     * forecast dari yang lain sekarang ("Overhead Sep 2026 — Week 4"). PO tidak
     * lagi bisa dipakai sebagai judul: satu forecast bisa memuat beberapa PO.
     */
    public function getLabelAttribute(): string
    {
        return $this->notes ?: ('Forecast #' . $this->id);
    }

    /**
     * Ringkasan PO yang tersentuh, untuk ditampilkan di daftar/detail:
     * "0070" untuk satu PO, "0070 +2 PO" kalau lebih, "-" kalau tidak ada.
     * Membaca dari relasi `details` yang sudah dimuat; tidak query sendiri.
     */
    public function getProjectSummaryAttribute(): string
    {
        $codes = $this->details
            ->map(fn ($d) => $d->project?->code)
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) return '-';
        if ($codes->count() === 1) return $codes->first();

        return $codes->first() . ' +' . ($codes->count() - 1) . ' PO';
    }
}
