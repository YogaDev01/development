<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrasi 2026_08_20_000011 sempat dijalankan di server saat kolom
     * monitoring_bast_status/monitoring_invoice_status masih 2-state
     * (NOT_YET, DONE) — sebelum diubah jadi 3-state agar sama seperti
     * monitoring_payment_status. Karena migrasi yang sudah pernah dijalankan
     * tidak akan diulang otomatis meski isi file-nya diedit, migrasi baru
     * ini memaksa ALTER enum-nya secara eksplisit.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'monitoring_bast_status')) {
            return;
        }

        DB::statement("ALTER TABLE projects MODIFY monitoring_bast_status ENUM('NOT_YET','ON_GOING','DONE') NOT NULL DEFAULT 'NOT_YET'");
        DB::statement("ALTER TABLE projects MODIFY monitoring_invoice_status ENUM('NOT_YET','ON_GOING','DONE') NOT NULL DEFAULT 'NOT_YET'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects MODIFY monitoring_bast_status ENUM('NOT_YET','DONE') NOT NULL DEFAULT 'NOT_YET'");
        DB::statement("ALTER TABLE projects MODIFY monitoring_invoice_status ENUM('NOT_YET','DONE') NOT NULL DEFAULT 'NOT_YET'");
    }
};
