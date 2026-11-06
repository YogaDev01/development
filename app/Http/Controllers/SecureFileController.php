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

        $this->recordDownload($request, $path);

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

    /** Folder yang dimuat otomatis di banyak halaman (bukan dokumen bisnis). */
    private const UNLOGGED_FOLDERS = ['avatars', 'covers'];

    /** Satu catatan per user + berkas per jendela ini (detik). */
    private const DOWNLOAD_WINDOW = 600;

    /**
     * Jejak audit buka/unduh lampiran (D18, disetujui pemilik 2026-11-07).
     *
     * Foto profil & sampul dilewati: tampil di daftar, notifikasi, dan bagan
     * organisasi, jadi mencatatnya berarti puluhan baris per halaman. Berkas
     * yang sama oleh user yang sama dicatat paling banyak sekali per 10 menit.
     * Gagal-aman: pencatatan tidak pernah menggagalkan pengiriman berkas.
     */
    private function recordDownload(Request $request, string $path): void
    {
        try {
            $user   = $request->user();
            $folder = explode('/', $path)[0] ?? '';

            if (! $user || in_array($folder, self::UNLOGGED_FOLDERS, true)) {
                return;
            }

            if (! \Illuminate\Support\Facades\Cache::add('secdl:' . sha1($user->getKey() . '|' . $path), 1, self::DOWNLOAD_WINDOW)) {
                return;
            }

            \App\Models\UserActivityLog::record(
                'file',
                'download',
                'Membuka lampiran ' . mb_substr(basename($path), 0, 120),
                null,
                $folder,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat unduhan lampiran', ['message' => $e->getMessage()]);
        }
    }
}
