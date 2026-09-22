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

        return $response;
    }
}
