<?php

namespace App\Support;

class Terbilang
{
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
        'sepuluh', 'sebelas',
    ];

    /**
     * Konversi angka bulat (rupiah, tanpa desimal) ke terbilang bahasa Indonesia.
     */
    public static function convert(int $number): string
    {
        if ($number < 0) {
            return 'minus ' . self::convert(abs($number));
        }

        if ($number < 12) {
            return trim(self::SATUAN[$number]);
        }

        if ($number < 20) {
            return trim(self::convert($number - 10) . ' belas');
        }

        if ($number < 100) {
            return trim(self::convert(intdiv($number, 10)) . ' puluh ' . self::convert($number % 10));
        }

        if ($number < 200) {
            return trim('seratus ' . self::convert($number - 100));
        }

        if ($number < 1000) {
            return trim(self::convert(intdiv($number, 100)) . ' ratus ' . self::convert($number % 100));
        }

        if ($number < 2000) {
            return trim('seribu ' . self::convert($number - 1000));
        }

        if ($number < 1000000) {
            return trim(self::convert(intdiv($number, 1000)) . ' ribu ' . self::convert($number % 1000));
        }

        if ($number < 1000000000) {
            return trim(self::convert(intdiv($number, 1000000)) . ' juta ' . self::convert($number % 1000000));
        }

        if ($number < 1000000000000) {
            return trim(self::convert(intdiv($number, 1000000000)) . ' miliar ' . self::convert($number % 1000000000));
        }

        return trim(self::convert(intdiv($number, 1000000000000)) . ' triliun ' . self::convert($number % 1000000000000));
    }

    /**
     * Terbilang + akhiran "Rupiah", huruf awal tiap kata kapital, siap tampil.
     */
    public static function rupiah(int $number): string
    {
        if ($number === 0) {
            return 'Nol Rupiah';
        }
        $words = preg_replace('/\s+/', ' ', self::convert($number));
        return ucwords(trim($words)) . ' Rupiah';
    }
}
