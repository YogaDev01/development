<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Sumber akses PO ketiga: team_id — pola sama seperti cost_center_id
| (2026_08_17_000001). Tiap baris access_group_details mengisi SALAH SATU
| dari project_id / cost_center_id / team_id. Team -> Access Group -> PO
| menggantikan assignment akses PO per-user manual (lihat dokumen Major A).
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_group_details', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('cost_center_id');

            $table->foreign('team_id')
                ->references('id')->on('teams')
                ->cascadeOnDelete();

            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('access_group_details', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
