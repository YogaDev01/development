<?php

namespace App\Support;

use App\Models\Asset;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Penyusutan aset — SATU-SATUNYA tempat rumus penyusutan.
 *
 * Sifatnya INFORMASIONAL (laporan & nilai buku di halaman aset). Tidak ada
 * jurnal, Closing, atau Cash Flow yang membaca angka ini. Bila kelak
 * penyusutan harus diposting, rumus di sini wajib ditinjau Finance dulu.
 *
 * Aturan (dikonfirmasi lewat permintaan implementasi mockup 2026-11-12):
 *   - Basis bulanan: penyusutan dihitung per bulan penuh sejak bulan perolehan.
 *   - STRAIGHT_LINE    : (harga - residu) / (umur x 12) per bulan.
 *   - DECLINING_BALANCE: saldo menurun ganda, tarif 2 / (umur x 12) per bulan
 *                        dari nilai buku, tidak pernah di bawah residu.
 *   - Setelah umur habis nilai buku = residu (garis lurus) / sisa saldo.
 *   - Aset DISPOSED bernilai buku 0 sejak tanggal disposal.
 *   - Semua angka integer rupiah (dibulatkan ke bawah untuk garis lurus,
 *     dibulatkan normal per bulan untuk saldo menurun).
 */
final class AssetDepreciation
{
    /** Jumlah bulan penuh yang sudah lewat sejak bulan perolehan sampai $at. */
    public static function monthsElapsed(Asset $asset, CarbonInterface $at): int
    {
        $acq = Carbon::parse($asset->acquisition_date)->startOfMonth();
        $now = Carbon::parse($at)->startOfMonth();

        if ($now->lte($acq)) {
            return 0;
        }

        return (int) $acq->diffInMonths($now);
    }

    public static function bookValue(Asset $asset, ?\DateTimeInterface $at = null): int
    {
        $at = $at ? Carbon::parse($at) : now();

        if ($asset->disposed_at && $at->gte(Carbon::parse($asset->disposed_at)->startOfDay())) {
            return 0;
        }

        $cost     = (int) $asset->cost;
        $residual = min((int) $asset->residual_value, $cost);
        $n        = max(1, (int) $asset->life_years) * 12;
        $m        = min(self::monthsElapsed($asset, $at), $n);

        if ($asset->depreciation_method === 'DECLINING_BALANCE') {
            $bv = $cost;
            for ($i = 0; $i < $m; $i++) {
                $bv = max($residual, $bv - (int) round($bv * 2 / $n));
            }

            return $bv;
        }

        return $cost - intdiv(($cost - $residual) * $m, $n);
    }

    /** Akumulasi penyusutan; untuk aset DISPOSED berhenti di hari sebelum disposal. */
    public static function accumulated(Asset $asset, ?\DateTimeInterface $at = null): int
    {
        $at = $at ? Carbon::parse($at) : now();
        if ($asset->disposed_at && $at->gte(Carbon::parse($asset->disposed_at)->startOfDay())) {
            $at = Carbon::parse($asset->disposed_at)->subDay();
        }

        return max(0, (int) $asset->cost - self::bookValue($asset, $at));
    }

    /** Penyusutan dalam satu rentang (nilai buku awal - nilai buku akhir). */
    public static function between(Asset $asset, CarbonInterface $from, CarbonInterface $to): int
    {
        // Disposal tidak dihitung sebagai penyusutan: nilai buku sesaat sebelum
        // tanggal disposal dipakai sebagai batas akhir.
        if ($asset->disposed_at && $to->gte(Carbon::parse($asset->disposed_at))) {
            $to = Carbon::parse($asset->disposed_at)->subDay();
        }
        if ($to->lt($from)) {
            return 0;
        }

        return max(0, self::bookValue($asset, $from) - self::bookValue($asset, $to));
    }

    /**
     * Jadwal penyusutan per tahun umur aset.
     *
     * @return array<int, array{year:int, period_end:string, depreciation:int, accumulated:int, book_value:int}>
     */
    public static function schedule(Asset $asset): array
    {
        $rows  = [];
        $start = Carbon::parse($asset->acquisition_date)->startOfMonth();
        $prev  = (int) $asset->cost;
        $tmp   = clone $asset;
        $tmp->disposed_at = null;

        for ($y = 1; $y <= max(1, (int) $asset->life_years); $y++) {
            $end = $start->copy()->addMonths(12 * $y);
            $bv  = self::bookValue($tmp, $end);
            $rows[] = [
                'year'         => $y,
                'period_end'   => $end->copy()->subDay()->toDateString(),
                'depreciation' => $prev - $bv,
                'accumulated'  => (int) $asset->cost - $bv,
                'book_value'   => $bv,
            ];
            $prev = $bv;
        }

        return $rows;
    }
}
