<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tipe untuk semua kolom uang: bilangan bulat rupiah.
 *
 * Aplikasi ini memakai dua tipe berbeda untuk hal yang sama. Sebagian kolom
 * uang adalah decimal(18,2) — budget_plans.amount, budget_request.amount,
 * request_detail.amount/total — sementara sisanya (rincian budget, realisasi,
 * cash receipt, seluruh angka invoice) sudah unsignedBigInteger rupiah bulat.
 *
 * Kenapa itu masalah, bukan sekadar tidak rapi: setiap penjumlahan yang
 * melintasi kedua kelompok membandingkan nilai berkoma dengan bilangan bulat.
 * Rupiah tidak punya sen, jadi dua desimal di sana tidak pernah membawa
 * informasi — yang dibawanya hanya peluang selisih pembulatan pada
 * perbandingan "sisa anggaran" dan "sudah terpakai", persis pemeriksaan yang
 * menentukan sebuah pengajuan ditandai NOB atau tidak.
 *
 * Kolom persentase (tax_rate, billing_percentage) dan qty TIDAK diubah: itu
 * memang bukan uang dan desimalnya berarti.
 *
 * Nilai yang punya pecahan dibulatkan ke rupiah terdekat dan dicatat ke log
 * lebih dulu, supaya kalau ada yang berubah, ada catatannya.
 */
return new class extends Migration
{
    /** tabel => kolom uang yang masih decimal. */
    private array $columns = [
        'budget_plans'   => ['amount'],
        'budget_request' => ['amount'],
        'request_detail' => ['amount', 'total'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }

                // Catat dulu baris yang akan berubah nilainya karena pembulatan.
                $berpecahan = DB::table($table)
                    ->whereRaw("{$col} <> ROUND({$col})")
                    ->count();

                if ($berpecahan > 0) {
                    Log::warning('Migrasi tipe uang membulatkan nilai berpecahan', [
                        'table'   => $table,
                        'column'  => $col,
                        'rows'    => $berpecahan,
                    ]);
                }

                DB::statement("UPDATE `{$table}` SET `{$col}` = ROUND(`{$col}`)");
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` BIGINT UNSIGNED NOT NULL DEFAULT 0");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $col) {
                if (Schema::hasColumn($table, $col)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` DECIMAL(18,2) NOT NULL DEFAULT 0");
                }
            }
        }
    }
};
