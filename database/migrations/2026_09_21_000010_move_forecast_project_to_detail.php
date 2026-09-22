<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Forecast — PO/Project pindah dari HEADER ke DETAIL.
|
| Migrasi 2026_09_20_000013 menaruh project_id di header dengan alasan
| "forecast selalu dibuat untuk sebuah PO". Itu keliru: satu forecast adalah
| satu RENTANG WAKTU (mis. "Overhead Sep 2026 — Week 4") yang isinya bisa
| menyentuh beberapa PO sekaligus, atau tidak menyentuh PO sama sekali
| (rencana overhead). Memaksanya satu PO di header membuat pengguna harus
| memecah forecast per PO hanya karena kolomnya ada di tempat yang salah —
| persis masalah yang sama yang dulu membuat source_fund_id ikut turun ke
| detail.
|
| project_id di header TIDAK dihapus di sini, hanya berhenti dipakai aplikasi
| — pola yang sama dengan `description`/`source_fund_id` pada migrasi
| sebelumnya: menghapus kolom berisi data historis di migrasi yang sama
| dengan pemindahannya tidak menyisakan jalan pulang kalau pemetaannya keliru.
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_details', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('description')
                ->constrained('projects')
                ->nullOnDelete();
        });

        // PO yang sudah tercatat di header diturunkan ke setiap barisnya, supaya
        // forecast lama tidak kehilangan kaitannya ke PO.
        if (Schema::hasColumn('forecasts', 'project_id')) {
            DB::statement('
                UPDATE forecast_details d
                JOIN forecasts f ON f.id = d.forecast_id
                SET d.project_id = f.project_id
                WHERE f.project_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('forecast_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
