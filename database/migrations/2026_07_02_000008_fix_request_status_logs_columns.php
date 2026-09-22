<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE request_status_logs
            MODIFY COLUMN status_from VARCHAR(50) NULL DEFAULT NULL,
            MODIFY COLUMN status_to   VARCHAR(50) NULL DEFAULT NULL,
            MODIFY COLUMN action_type VARCHAR(50) NULL DEFAULT NULL
        ");
    }

    public function down(): void
    {
        // Tidak di-rollback karena data existing mungkin tidak kompatibel dengan ENUM lama
    }
};