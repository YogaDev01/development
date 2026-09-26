<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom uang yang masih INT — batas Rp 2.147.483.647.
 *
 * Migrasi 2026_09_18_000005 menyatakan niatnya dengan jelas: satu tipe untuk
 * semua kolom uang, bilangan bulat rupiah. Ia menyeragamkan budget_plans,
 * budget_request dan request_detail ke BIGINT UNSIGNED. Tapi dua tabel dibuat
 * SETELAH migrasi itu — cost_registers (31 Agu) dan cash_flows (20 Sep) — dan
 * keduanya memakai `integer`, jadi tidak ikut terseragamkan.
 *
 * Kenapa itu bukan sekadar tidak rapi: INT bertanda berhenti di
 * 2.147.483.647. Dalam rupiah itu Rp 2,14 miliar. Karena config/database.php
 * memakai 'strict' => true, MySQL TIDAK memotong nilai yang kelebihan — ia
 * melempar error 1264 Out of range, yang keluar ke pengguna sebagai HTTP 500
 * tanpa petunjuk apa pun tentang sebabnya.
 *
 * Untuk ERP yang menangani PO bernilai miliaran, satu Fixed Cost atau satu
 * entri penyesuaian kas di atas Rp 2,14 M bukan kasus ekstrem. cash_flows
 * lebih rawan lagi: total_in/total_out adalah AKUMULASI dari baris detail
 * (lihat CashFlow::recalculateTotals), jadi batasnya tercapai jauh lebih cepat
 * daripada nilai transaksi tunggal.
 *
 * Kolom persentase dan qty TIDAK disentuh — sama seperti migrasi 000005,
 * keduanya memang bukan uang.
 */
return new class extends Migration
{
    /** tabel => kolom uang yang masih INT. */
    private array $columns = [
        'cost_registers'    => ['amount'],
        'cash_flows'        => ['total_in', 'total_out'],
        'cash_flow_details' => ['amount'],
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

                /*
                | Nilai negatif dicatat lebih dulu. UNSIGNED menolaknya, dan
                | kalau ada yang tersimpan (mis. koreksi kas yang dimasukkan
                | sebagai angka minus alih-alih direction=OUT), ALTER-nya akan
                | gagal. Lebih baik ketahuan lewat log daripada lewat deploy
                | yang berhenti di tengah.
                */
                $negatif = DB::table($table)->where($col, '<', 0)->count();

                if ($negatif > 0) {
                    Log::warning('Kolom uang berisi nilai negatif sebelum diubah ke UNSIGNED', [
                        'table'  => $table,
                        'column' => $col,
                        'rows'   => $negatif,
                    ]);
                }

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
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` INT NOT NULL DEFAULT 0");
                }
            }
        }
    }
};
