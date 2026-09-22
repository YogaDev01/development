<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forecast — pindah titik beberapa informasi dari header ke detail.
 *
 * Empat perubahan yang diminta:
 *
 *  1. `source_fund_id` pindah dari HEADER ke DETAIL. Satu forecast bisa memuat
 *     rencana yang menyentuh beberapa rekening; memaksanya satu rekening di
 *     header membuat pengguna harus membuat forecast terpisah per rekening
 *     hanya karena kolomnya ada di tempat yang salah.
 *
 *  2. Kolom `date` di detail DIHAPUS. Rentang waktunya sudah ditentukan
 *     date_start/date_end di header; tanggal per baris hanya mengulang
 *     informasi yang sama dan menimbulkan pertanyaan "kalau tanggal baris di
 *     luar rentang header, yang mana yang berlaku?".
 *
 *  3. `description` di header berganti jadi `project_id` (FK ke projects).
 *     Forecast selalu dibuat untuk sebuah PO; mengetiknya sebagai teks bebas
 *     membuat forecast tidak bisa dihubungkan ke PO-nya sama sekali.
 *     Kolom teks lamanya TIDAK dihapus — dipertahankan sebagai catatan
 *     (`notes`) supaya kalimat yang sudah terlanjur diketik tidak hilang.
 *
 *  4. Detail dapat kolom `type` (IN/OUT), supaya satu forecast bisa memuat
 *     rencana penerimaan sekaligus pengeluaran. Default IN — seluruh baris
 *     yang sudah ada dibuat sebelum ada pembedaan ini, dan isinya rencana
 *     penerimaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_details', function (Blueprint $table) {
            $table->enum('type', ['IN', 'OUT'])->default('IN')->after('description');

            $table->foreignId('source_fund_id')
                ->nullable()
                ->after('type')
                ->constrained('source_funds')
                ->nullOnDelete();
        });

        // Rekening yang sudah tercatat di header diturunkan ke setiap barisnya,
        // supaya data lama tidak kehilangan informasi saat kolom header pensiun.
        DB::statement('
            UPDATE forecast_details d
            JOIN forecasts f ON f.id = d.forecast_id
            SET d.source_fund_id = f.source_fund_id
            WHERE f.source_fund_id IS NOT NULL
        ');

        Schema::table('forecast_details', function (Blueprint $table) {
            $table->dropColumn('date');
        });

        Schema::table('forecasts', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('id')
                ->constrained('projects')
                ->nullOnDelete();

            // Teks bebas lama tetap disimpan sebagai catatan.
            $table->text('notes')->nullable()->after('project_id');
        });

        DB::statement('UPDATE forecasts SET notes = description');

        /*
        | Rekening di header dan description dibiarkan ADA di tabel, hanya
        | berhenti dipakai aplikasi. Menghapus kolom berisi data historis di
        | migrasi yang sama dengan pemindahannya tidak menyisakan jalan pulang
        | kalau pemetaannya ternyata keliru; pembersihannya dilakukan terpisah
        | setelah datanya terbukti benar.
        */
        Schema::table('forecasts', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('notes');
        });

        Schema::table('forecast_details', function (Blueprint $table) {
            $table->date('date')->nullable();
            $table->dropConstrainedForeignId('source_fund_id');
            $table->dropColumn('type');
        });
    }
};
