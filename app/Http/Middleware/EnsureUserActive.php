<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

/**
 * Cabut sesi yang sedang berjalan begitu akunnya dinonaktifkan.
 *
 * Memeriksa status di AuthController::login() saja tidak cukup. Sesi berumur
 * SESSION_LIFETIME (120 menit) dan "remember me" jauh lebih panjang, jadi
 * seseorang yang dinonaktifkan sementara ia sedang bekerja tetap memegang
 * sesinya - lengkap dengan wewenang approval - sampai sesi itu kedaluwarsa
 * sendiri. Untuk sistem yang menyetujui pembayaran, jeda itu tidak bisa
 * diterima: justru pada saat penonaktifan mendadak (pemutusan hubungan kerja,
 * dugaan penyalahgunaan) pencabutan harus berlaku detik itu juga.
 *
 * Biayanya nol query tambahan: $user->status sudah termuat bersama model user
 * yang di-hydrate guard session pada setiap request.
 */
class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->status !== 'ACTIVE') {
            // Ditinggalkan jejaknya: tanpa ini tidak ada cara mengetahui
            // sebuah sesi diputus karena penonaktifan, dan keluhan
            // "tiba-tiba ter-logout" tidak bisa ditelusuri.
            Log::warning('Sesi diputus - akun tidak aktif', [
                'user_id'  => $user->id,
                'username' => $user->username,
                'status'   => $user->status,
                'ip'       => $request->ip(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            /*
            | Permintaan AJAX (lonceng notifikasi, tab Cost Request, pencarian
            | Ctrl+K) dibalas 401 JSON, bukan redirect. Redirect ke halaman
            | login pada pemanggilan AJAX akan masuk sebagai HTML ke pengurai
            | JSON dan muncul sebagai error yang tidak berhubungan.
            */
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Akun Anda telah dinonaktifkan.',
                ], 401);
            }

            return redirect('/login')->withErrors([
                'login' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
            ]);
        }

        return $next($request);
    }
}
