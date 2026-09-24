<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk seluruh respons HTML.
 *
 * Tidak satu pun dari ini menggantikan perbaikan di sisi aplikasi; fungsinya
 * mempersempit akibat kalau ada yang lolos. Content-Security-Policy sengaja
 * BELUM dipasang: halaman-halaman TESYS masih memakai ribuan atribut style
 * inline dan 176 blok <script> inline, jadi CSP yang benar (tanpa
 * 'unsafe-inline') akan mematikan sebagian besar layar. Itu pekerjaan Phase 4
 * setelah gaya/skrip inline dipindahkan ke berkas.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Aplikasi keuangan tidak punya alasan untuk ditanam di iframe situs
        // lain — ini yang menutup clickjacking.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Jangan biarkan peramban menebak tipe berkas; unggahan yang dikirim
        // sebagai gambar tidak boleh tiba-tiba dieksekusi sebagai HTML.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // URL internal (yang memuat id dokumen) tidak ikut bocor ke situs luar
        // saat pengguna mengklik tautan keluar.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Tidak ada fitur perangkat yang dipakai aplikasi ini.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS hanya masuk akal saat benar-benar dilayani lewat HTTPS; di
        // lingkungan lokal (http) header ini justru mengunci peramban.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        /*
        | Lapisan cache Hostinger (LiteSpeed Cache Manager / hcdn) TIDAK boleh
        | menyimpan halaman aplikasi. 23 Sep 2026: Dashboard & Treasury terus
        | 500 untuk satu pengguna — padahal laravel.log & log error hPanel
        | kosong, dan Incognito (cookie lain) bisa membuka — tanda respons
        | error disajikan dari cache di depan aplikasi, bukan dari Laravel.
        |
        | X-LiteSpeed-Cache-Control dibaca langsung oleh LiteSpeed. Bawaan
        | Laravel "no-cache, private" diperketat jadi no-store; respons yang
        | sudah menetapkan cache sendiri (mis. lampiran di SecureFileController,
        | "private, max-age=...") dibiarkan.
        */
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');

        $cache = (string) $response->headers->get('Cache-Control');
        if (! str_contains($cache, 'max-age=') || str_contains($cache, 'max-age=0')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $response;
    }
}
