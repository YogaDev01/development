<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Cost Request yang bersumber dari cost center (overhead/payroll) TIDAK
    | terhubung ke cluster (Project). Kolom cluster_id harus boleh NULL.
    |
    | Pakai SQL mentah (MySQL) agar tidak butuh doctrine/dbal.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE budget_request MODIFY cluster_id BIGINT UNSIGNED NULL');
        }
        // SQLite (dev): kolom umumnya sudah nullable; SQLite tak dukung MODIFY.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Kosongkan dulu yang null agar tidak gagal saat dikembalikan NOT NULL
            DB::statement('UPDATE budget_request SET cluster_id = 0 WHERE cluster_id IS NULL');
            DB::statement('ALTER TABLE budget_request MODIFY cluster_id BIGINT UNSIGNED NOT NULL');
        }
    }
};