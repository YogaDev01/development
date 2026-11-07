<?php

use App\Support\DataScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — isi awal role_data_scopes dari config/data_scopes.php (parity
 * dengan aturan scope di kode hari ini). Hanya menambah baris yang belum ada.
 * Tidak mengubah perilaku: mesin DataScope belum dipasang ke controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_data_scopes') || ! Schema::hasTable('roles')) {
            return;
        }

        if (! config('data_scopes.modules')) {
            Log::warning('Seed role_data_scopes DILEWATI: config/data_scopes.php kosong. '
                . 'Unggah berkasnya, config:clear, lalu php artisan security:sync-permissions --apply.');

            return;
        }

        Log::info('Seed role_data_scopes (parity) selesai', DataScope::syncSeed(true));
    }

    public function down(): void
    {
        if (Schema::hasTable('role_data_scopes')) {
            DB::table('role_data_scopes')->delete();
        }
    }
};
