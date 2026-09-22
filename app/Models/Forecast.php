<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Hitung ulang kedua angka header dari detailnya.
     *
     *   forecast_amount = rencana MASUK - rencana KELUAR
     *   actual_amount   = realisasi MASUK - realisasi KELUAR
     *
     * Sejak baris forecast punya arah (IN/OUT), menjumlah semuanya begitu saja
     * akan menghasilkan angka yang tidak berarti apa-apa: rencana pengeluaran
     * akan menaikkan "nilai forecast". Yang bermakna adalah selisihnya — dan
     * selisih itu boleh negatif, untuk forecast yang isinya memang pengeluaran
     * (lihat migrasi 2026_09_22_000010 yang membuat kolomnya bertanda).
     *
     * Keduanya sengaja dihitung bersamaan dalam SATU update: nilainya berasal
     * dari kumpulan baris yang sama, jadi memisahnya hanya berarti dua kali
     * tulis untuk satu perubahan yang sama.
     */
    public function syncAmounts(): void
    {
        // CASE ... END memberi tanda pada nilai per baris (cerminan
        // ForecastDetail::getSignedAmountAttribute()) sehingga satu SUM sudah
        // menghasilkan selisihnya, tanpa query terpisah per arah.
        $forecast = (int) $this->details()->sum(
            DB::raw("CASE WHEN `type` = 'IN' THEN `amount` ELSE -`amount` END")
        );
        $actual = (int) $this->details()->sum(
            DB::raw("CASE WHEN `type` = 'IN' THEN `actual_amount` ELSE -`actual_amount` END")
        );

        if ($forecast === (int) $this->forecast_amount && $actual === (int) $this->actual_amount) {
            return;
        }

        $this->update([
            'forecast_amount' => $forecast,
            'actual_amount'   => $actual,
        ]);
    }

    public function getVarianceAttribute(): int
    {
        return (int) $this->forecast_amount - (int) $this->actual_amount;
    }

    public function getAchievementPctAttribute(): float
    {
        // Pembaginya boleh negatif. Forecast yang isinya rencana pengeluaran
        // bernilai bersih negatif, dan realisasi -8jt atas rencana -10jt tetap
        // berarti 80% tercapai — syarat `> 0` yang lama membuat seluruh
        // forecast pengeluaran dilaporkan 0%. Yang benar-benar tidak bisa
        // dibagi hanya nol.
        return (int) $this->forecast_amount !== 0
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
     * Forecast yang tidak boleh lagi diubah isinya (header & detail).
     *
     * Hanya CLOSED. Penutupan memang bersifat final — lihat
     * ForecastController::close().
     *
     * ACTIVE sempat ikut terkunci di sini, dan akibatnya satu klik Activate
     * mengunci isi forecast selamanya: tidak ada jalur ACTIVE -> DRAFT, jadi
     * salah input yang baru ketahuan setelah aktivasi tidak bisa diperbaiki
     * sama sekali. Selama periodenya masih berjalan, rencananya masih boleh
     * dikoreksi.
     *
     * Dipakai bersama oleh controller (edit/update/destroy) dan tampilan
     * (tombol di index & detail), supaya tombol yang muncul selalu tombol yang
     * memang diterima backend.
     */
    public function isLocked(): bool
    {
        return $this->status === 'CLOSED';
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
