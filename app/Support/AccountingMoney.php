<?php

namespace App\Support;

/**
 * Satu-satunya tempat konversi uang modul Accounting.
 *
 * Nominal di tabel akuntansi = BIGINT SEN (rupiah x 100). Semua fungsi memakai
 * integer/string — tanpa float — supaya hasilnya sama persis dengan rekening koran.
 */
final class AccountingMoney
{
    /** Batas digit bagian rupiah: aman untuk int 64-bit setelah dikali 100. */
    private const MAX_INT_DIGITS = 15;

    /** Rupiah bulat (sumber Treasury/Cash Receipt) -> sen. */
    public static function fromRupiah(int $rupiah): int
    {
        return $rupiah * 100;
    }

    /**
     * Teks input format Indonesia -> sen. KETAT: titik hanya pemisah ribuan
     * (kelompok 3 digit), koma satu-satunya pemisah desimal (maks. 2 digit).
     *   "12.345,67" ok · "1000" ok · "1.000,5" ok · ",50" ok · "Rp 10.000.000" ok
     *   "1000.50" TOLAK · "1.5" TOLAK · "1,234" TOLAK · "1,000.50" TOLAK
     * Mengembalikan null bila tidak valid (pemanggil wajib menolak, bukan menganggap 0).
     */
    public static function parse(?string $input): ?int
    {
        $s = trim((string) $input);
        $s = preg_replace('/^Rp\s*/i', '', $s);
        if (! preg_match('/^(-?)((?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?)|(?:,\d{1,2}))$/', $s, $m)) {
            return null;
        }

        $negative = $m[1] === '-';
        [$int, $dec] = array_pad(explode(',', $m[2], 2), 2, '');
        $int = str_replace('.', '', $int);
        if (strlen($int) > self::MAX_INT_DIGITS) {
            return null;
        }

        $sen = ((int) ($int === '' ? '0' : $int)) * 100 + (int) str_pad($dec, 2, '0');

        return $negative ? -$sen : $sen;
    }

    /** Sen -> "12345.67" (untuk CSV/Excel: tanpa pemisah ribuan, titik desimal, tanpa float). */
    public static function plain(int $sen): string
    {
        $abs = abs($sen);

        return ($sen < 0 ? '-' : '') . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Sen -> "12.345,67" (selalu dua digit, cocok dengan rekening koran). */
    public static function format(int $sen, bool $dashWhenZero = false): string
    {
        if ($sen === 0 && $dashWhenZero) {
            return '-';
        }

        $neg = $sen < 0;
        $abs = abs($sen);
        $out = number_format(intdiv($abs, 100), 0, ',', '.') . ',' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return $neg ? '-' . $out : $out;
    }
}
