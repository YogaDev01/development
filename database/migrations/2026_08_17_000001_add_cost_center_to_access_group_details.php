<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Access group kini bisa memberi akses ke DUA jenis sumber:
    |   - project_id     : proyek (PO)   — sudah ada
    |   - cost_center_id : cost center (OVERHEAD) — baru
    |
    | Tiap baris detail mengisi SALAH SATU. Ini membuat akses overhead
    | ditentukan per-orang (seperti PO), bukan otomatis per-role.
    |
    | project_id dibuat nullable karena baris cost center tidak punya project.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::table('access_group_details', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_center_id')->nullable()->after('project_id');

            $table->foreign('cost_center_id')
                ->references('id')->on('cost_centers')
                ->cascadeOnDelete();

            $table->index('cost_center_id');
        });

        // project_id jadi nullable (MySQL). SQLite dilewati (tak dukung MODIFY).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE access_group_details MODIFY project_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::table('access_group_details', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE access_group_details SET project_id = 0 WHERE project_id IS NULL');
            DB::statement('ALTER TABLE access_group_details MODIFY project_id BIGINT UNSIGNED NOT NULL');
        }
    }
};