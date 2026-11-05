<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Konteks organisasi pelaku pada log status dokumen.
|
| Additive & nullable. Diisi oleh addLog() (Fase 3) saat baris log dibuat,
| berisi team primary / department / posisi pelaku PADA SAAT AKSI. Baris log
| lama TIDAK di-backfill (tetap NULL) — histori tidak ditulis ulang.
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    private const TABLES = ['request_status_logs', 'project_logs', 'budget_plan_logs'];

    public function up(): void
    {
        foreach (self::TABLES as $tabel) {
            if (Schema::hasTable($tabel) && ! Schema::hasColumn($tabel, 'actor_org_context')) {
                Schema::table($tabel, function (Blueprint $table) {
                    $table->json('actor_org_context')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tabel) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'actor_org_context')) {
                Schema::table($tabel, function (Blueprint $table) {
                    $table->dropColumn('actor_org_context');
                });
            }
        }
    }
};
