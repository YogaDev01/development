<?php

use App\Support\Authorizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 Security & Access Control — isi awal role_permissions (parity).
 *
 * Menyalin isi gerbang role: saat ini (config/permissions.php → seed_roles)
 * ke role_permissions untuk role yang ADA di tabel roles. Hanya menambah
 * baris; tidak mengubah tabel lain dan tidak mengubah perilaku aplikasi
 * (mesin baru baru dievaluasi bila SECURITY_PERMISSION_MODE=shadow).
 *
 * Role yang disebut gerbang tetapi belum ada di tabel roles dicatat di log;
 * bila role itu dibuat belakangan, jalankan: php artisan security:sync-permissions --apply
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        if (! Authorizer::registry()) {
            // config/permissions.php tidak terunggah atau config cache basi.
            Log::warning('Seed role_permissions DILEWATI: registry config/permissions.php kosong. '
                . 'Unggah berkasnya, jalankan config:clear, lalu php artisan security:sync-permissions --apply.');

            return;
        }

        $hasil = Authorizer::syncSeed(true);

        Log::info('Seed role_permissions (parity) selesai', $hasil);
        if (($hasil['rows'] ?? 0) === 0) {
            Log::warning('Seed role_permissions menulis 0 baris — periksa tabel roles & config/permissions.php.', $hasil);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            // Mengembalikan ke keadaan sebelum migrasi ini: tabel kosong.
            DB::table('role_permissions')->delete();
        }
    }
};
