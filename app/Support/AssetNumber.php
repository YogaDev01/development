<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen modul Asset di atas DocumentSequence (bukan max()+1).
 *
 *   Kode aset   : AST-{KODE KATEGORI}-{TAHUN PEROLEHAN}-{0001}
 *   Dokumen lain: {PREFIX}/{TAHUN}/{0001}  (BAST-AST, TRF-AST, MR-AST, ...)
 *
 * Seed membaca nomor terakhir yang sudah terbit dengan pola yang sama, jadi
 * penomoran tetap melanjutkan bila penghitung dibuat ulang.
 */
final class AssetNumber
{
    public static function assetCode(string $categoryCode, int $year): string
    {
        $prefix = 'AST-' . strtoupper($categoryCode) . '-' . $year . '-';

        $n = DocumentSequence::next('asset:' . strtoupper($categoryCode) . ':' . $year,
            fn () => self::lastSuffix('assets', 'code', $prefix));

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function document(string $table, string $prefix, ?int $year = null): string
    {
        $year   = $year ?? (int) now()->format('Y');
        $awalan = $prefix . '/' . $year . '/';

        $n = DocumentSequence::next('asset-doc:' . $prefix . ':' . $year,
            fn () => self::lastSuffix($table, 'number', $awalan));

        return $awalan . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private static function lastSuffix(string $table, string $column, string $prefix): int
    {
        $values = DB::table($table)->where($column, 'like', $prefix . '%')->pluck($column);

        return (int) $values->map(fn ($v) => (int) substr($v, strlen($prefix)))->max();
    }
}
