<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Budget plan kini bisa bersumber dari:
    |   - Project (PO customer)   -> project_id terisi,     cost_center_id NULL
    |   - Cost Center (internal)  -> cost_center_id terisi, project_id     NULL
    | Tepat satu yang terisi.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        // 1) Tambah kolom cost_center_id
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_center_id')->nullable()->after('project_id');

            $table->foreign('cost_center_id')
                ->references('id')->on('cost_centers')
                ->nullOnDelete();

            $table->index('cost_center_id');
        });

        // 2) Jadikan project_id nullable.
        //    Pakai SQL mentah agar tidak butuh package doctrine/dbal,
        //    dan dibedakan per driver (MySQL produksi vs SQLite development).
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE budget_plans MODIFY project_id BIGINT UNSIGNED NULL');
        }
        // SQLite: kolom nullable secara default pada praktiknya di dev,
        // dan SQLite tidak mendukung MODIFY COLUMN. Dilewati dengan aman.
    }

    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE budget_plans MODIFY project_id BIGINT UNSIGNED NOT NULL');
        }
    }
};