<?php

namespace App\Support;

/**
 * Satu penanda versi untuk seluruh aset statis.
 *
 * MASALAH YANG DIGANTIKAN
 *
 * Layout utama sebelumnya membubuhkan cache-buster per berkas:
 *
 *     asset('css/tesys-ui.css') . '?v=' . filemtime(public_path('css/tesys-ui.css'))
 *
 * dikalikan 25 berkas. Dua akibatnya, dan yang kedua serius:
 *
 * 1. 25 panggilan stat() ke filesystem pada SETIAP permintaan halaman, sebelum
 *    satu byte HTML terkirim. Di hosting bersama itu terukur - komentar di
 *    SecurityHeaders mencatat lonjakan CPU 100% di lingkungan seperti itu.
 *
 * 2. filemtime() melempar E_WARNING kalau berkasnya tidak ada, dan Laravel
 *    mengubah warning menjadi ErrorException. Artinya SATU berkas CSS yang
 *    luput saat deploy membuat SETIAP halaman menjadi layar putih - dan pesan
 *    galatnya menunjuk baris Blade, bukan deploy yang tidak lengkap. Kegagalan
 *    total dari sesuatu yang seharusnya sepele.
 *
 * CARA KERJA SEKARANG
 *
 * Versinya dihitung SEKALI per permintaan (memoized) dan tidak menyentuh disk:
 *
 *   - Produksi: nomor versi aplikasi dari config('changelog.current'), yang
 *     sudah dipelihara setiap rilis. Aset ikut ter-bust tepat saat rilis -
 *     yang memang satu-satunya saat isinya berubah di produksi.
 *
 *   - Non-produksi: timestamp, supaya perubahan CSS/JS saat mengembangkan
 *     langsung terlihat tanpa perlu menaikkan versi dulu.
 *
 * Kalau suatu saat aset perlu di-bust tanpa menaikkan versi aplikasi, setel
 * ASSET_VERSION di .env - nilai itu menang atas keduanya.
 */
final class AssetVersion
{
    private static ?string $versi = null;

    /** Penanda versi untuk seluruh aset pada permintaan ini. */
    public static function get(): string
    {
        if (self::$versi !== null) {
            return self::$versi;
        }

        $eksplisit = config('app.asset_version');

        if (! empty($eksplisit)) {
            return self::$versi = (string) $eksplisit;
        }

        if (app()->environment('production')) {
            return self::$versi = (string) (config('changelog.current') ?: '1');
        }

        return self::$versi = (string) time();
    }

    /**
     * URL aset lengkap dengan penanda versi.
     *
     * TIDAK memeriksa keberadaan berkas, dan itu disengaja: berkas yang hilang
     * harus menghasilkan satu 404 pada aset itu saja - terlihat jelas di tab
     * Network - bukan menjatuhkan seluruh halaman.
     */
    public static function url(string $path): string
    {
        return asset($path) . '?v=' . self::get();
    }
}
