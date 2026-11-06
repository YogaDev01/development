<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'menu' => \App\Http\Middleware\CheckMenu::class,
        ]);

        // Header keamanan dipasang ke seluruh respons web (lihat
        // App\Http\Middleware\SecurityHeaders untuk alasan tiap header).
        //
        // EnsureUserActive memutus sesi yang sedang berjalan begitu akunnya
        // dinonaktifkan - pasangan dari pemeriksaan status di
        // AuthController::login(), yang sendirian hanya menutup pintu masuk
        // dan membiarkan sesi lama hidup sampai kedaluwarsa.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureUserActive::class,
            // Jejak audit export/cetak (D18) & akses ditolak (D11). Hanya
            // mengamati respons, tidak mengubahnya — lihat kelasnya.
            \App\Http\Middleware\RecordSecurityEvents::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        | Konteks otomatis pada setiap laporan exception.
        |
        | Sebelumnya blok ini kosong, sehingga laporan galat tiba tanpa
        | keterangan siapa yang mengalaminya. Untuk aplikasi keuangan yang
        | dipakai puluhan orang dengan peran berbeda, "500 di halaman Cost
        | Request" tanpa identitas pengguna nyaris tidak bisa ditelusuri —
        | peran menentukan data mana yang dimuat, jadi tanpa itu tidak ada cara
        | mereproduksi keadaannya.
        |
        | Hanya id, username dan peran yang dicatat. TIDAK ada isi request,
        | karena badan permintaan di aplikasi ini rutin memuat nominal,
        | lampiran dan - pada form User - password.
        */
        $exceptions->context(function (): array {
            $user = auth()->user();

            if (! $user) {
                return [];
            }

            return [
                'user_id'  => $user->id,
                'username' => $user->username,
                'role'     => $user->role->name ?? null,
            ];
        });
    })->create();
