<?php

namespace App\Http\Controllers;

use App\Services\FileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu-satunya pintu keluar berkas lampiran.
 *
 * URL-nya sengaja tetap /storage/{path} — bentuk yang sama dengan sebelumnya —
 * supaya ~50 tautan yang sudah tersebar di Blade dan JavaScript tidak perlu
 * diubah satu per satu (setiap satu yang terlewat akan jadi tautan mati di
 * halaman yang jarang dibuka, dan itu baru ketahuan berbulan-bulan kemudian).
 * Yang berubah adalah siapa yang melayani: dulu web server langsung dari
 * public/storage, sekarang Laravel — dengan sesi dan hak akses diperiksa dulu.
 */
class SecureFileController extends Controller
{
    /** Tipe yang aman ditampilkan inline di peramban; sisanya dipaksa unduh. */
    private const INLINE_MIME = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm',
    ];

    public function show(Request $request, string $path, FileAccess $access): StreamedResponse
    {
        /*
        | Path traversal. Tanda ".." tidak pernah sah di sini: seluruh path
        | dibuat oleh Storage::store() dan hanya berisi nama folder + nama
        | berkas acak. Ditolak lebih dulu, sebelum menyentuh disk.
        */
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '.')) {
            abort(404);
        }

        $disk = Storage::disk('secure');

        if (! $disk->exists($path)) {
            /*
            | Lampiran yang diunggah SEBELUM pindah ke disk privat masih tinggal
            | di storage/app/public (disk "public"), sementara path di basis
            | datanya sama. Tanpa jalur ini semuanya 404 (ditemukan 23 Sep 2026
            | pada lampiran PO lama). Pemeriksaan akses di bawah tetap berlaku;
            | "php artisan files:pindah-lampiran-lama" memindahkannya permanen.
            */
            $disk = Storage::disk('public');

            if (! $disk->exists($path)) {
                abort(404);
            }
        }

        if (! $access->allows($path)) {
            Log::warning('Akses berkas ditolak', [
                'path'    => $path,
                'user_id' => $request->user()?->id,
                'ip'      => $request->ip(),
            ]);

            abort(403, 'Anda tidak memiliki akses ke berkas ini.');
        }

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        /*
        | Berkas yang tidak dikenali dipaksa unduh, bukan ditampilkan. HTML atau
        | SVG yang tampil inline berjalan pada origin aplikasi — artinya berkas
        | unggahan bisa menjalankan skrip di sesi pengguna lain. Daftar putih di
        | atas menutup itu tanpa mengganggu PDF dan gambar yang memang perlu
        | dibuka langsung.
        */
        $disposition = in_array($mime, self::INLINE_MIME, true) ? 'inline' : 'attachment';

        return $disk->response($path, basename($path), [
            'Content-Type'           => $mime,
            'X-Content-Type-Options' => 'nosniff',
            // "private": tidak boleh mengendap di cache bersama (proxy/CDN).
            // max-age: nama berkas acak dari Storage::store() dan isinya tak
            // pernah ditimpa, jadi aman disimpan di peramban pemilik sesi.
            // Dulu max-age=0 membuat setiap buka halaman mengunduh ulang semua
            // gambar lewat PHP+MySQL — pemicu lonjakan CPU 100% di Hostinger.
            'Cache-Control'          => 'private, max-age=604800',
        ], $disposition);
    }
}
