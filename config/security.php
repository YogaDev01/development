<?php

/*
|--------------------------------------------------------------------------
| Security & Access Control (docs/plans/2026-11-06-security-access-control.md)
|--------------------------------------------------------------------------
| permission_mode:
|   off    : registry permission tidak dievaluasi sama sekali (bawaan).
|   shadow : mesin permission baru (App\Support\Authorizer) dihitung di
|            samping gerbang role: lama. Gerbang lama TETAP yang memutuskan;
|            setiap jawaban yang berbeda dicatat ke permission_shadow_logs
|            dan tampil di Konsol Keamanan, tab "Shadow Permission".
|
| Mode "enforce" belum ada — cutover per modul adalah fase berikutnya dan
| butuh persetujuan pemilik (masa uji: 2 minggu CR & Treasury, 1 minggu
| modul lain, tanpa selisih).
|
| Nyalakan shadow HANYA setelah migrasi 2026_11_07_000600/000700 dijalankan:
| SECURITY_PERMISSION_MODE=shadow di .env, lalu php artisan config:clear.
*/

return [

    'permission_mode' => env('SECURITY_PERMISSION_MODE', 'off'),

    // Satu catatan selisih per (user, permission, jawaban) per jendela ini (detik).
    'shadow_log_window' => 3600,

];
