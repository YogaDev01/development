<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sisa kolom uang yang masih INT 32-bit — dilebarkan ke BIGINT UNSIGNED.
 *
 * Lanjutan dari 2026_09_18_000005_unify_money_columns_to_integer. Migrasi itu
 * menyeragamkan tipe uang jadi bilangan bulat rupiah, tetapi daftarnya hanya
 * mencakup tiga tabel yang saat itu masih decimal: budget_plans,
 * budget_request, request_detail. Tabel yang dibuat di luar daftar itu memakai
 * $table->integer(...) — INT bertanda, dengan batas atas 2.147.483.647.
 *
 * Untuk rupiah, batas itu Rp 2,147 miliar. Itu bukan angka teoretis di ERP
 * ini: satu Cost Register atau satu rekap Cash Receipt di atas dua miliar
 * sudah menabraknya.
 *
 * Yang membuatnya lebih tajam: induk dan anak bertipe beda. cash_receipts.amount
 * sudah BIGINT UNSIGNED, sedangkan induknya cash_receipt_headers.total_amount
 * masih INT — jadi ada keadaan di mana penjumlahan anak-anaknya tidak muat di
 * kolom induknya sendiri. Hal yang sama berlaku untuk cash_flows.total_in /
 * total_out terhadap cash_flow_details.amount.
 *
 * Akibatnya bergantung sql_mode: strict mode melempar "Out of range value"
 * di tengah transaksi, non-strict DIAM-DIAM memotong nilainya ke 2.147.483.647.
 * Dua-duanya salah; yang kedua lebih berbahaya karena tidak meninggalkan jejak.
 *
 * Kolom yang TIDAK ikut diubah, supaya jelas ini bukan kelalaian:
 *   - closings.total_transaction — itu CACAH baris (details()->count()),
 *     bukan uang. INT justru tipe yang benar.
 *   - tax_rate, billing_percentage, qty — persentase & kuantitas, desimalnya
 *     memang berarti (alasan sama seperti migrasi 000005).
 *
 * UNSIGNED dipilih karena kelima kolom ini memang besaran tak bertanda: arah
 * kas disimpan terpisah di cash_flow_details.direction, bukan lewat tanda
 * minus pada amount.
 */
return new class extends Migration
{
    /** tabel => [kolom => definisi setelah tipe]. */
    private array $columns = [
        'cash_flows'           => ['total_in' => 'NOT NULL DEFAULT 0', 'total_out' => 'NOT NULL DEFAULT 0'],
        'cash_flow_details'    => ['amount' => 'NOT NULL'],
        'cash_receipt_headers' => ['total_amount' => 'NOT NULL DEFAULT 0'],
        'cost_registers'       => ['amount' => 'NOT NULL'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $col => $definisi) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }

                /*
                | INT bertanda -> BIGINT UNSIGNED melebarkan batas atas, tetapi
                | menghilangkan kemungkinan nilai negatif. Kalau ternyata ada
                | baris negatif, migrasi ini BERHENTI dan tidak menulis apa pun:
                | mengubahnya diam-diam jadi nol atau membiarkan MySQL menolak
                | di tengah jalan sama-sama meninggalkan data keuangan dalam
                | keadaan yang tidak bisa dipertanggungjawabkan.
                */
                $negatif = DB::table($table)->where($col, '<', 0)->count();

                if ($negatif > 0) {
                    Log::error('Migrasi pelebaran kolom uang dihentikan: ada nilai negatif', [
                        'table'  => $table,
                        'column' => $col,
                        'rows'   => $negatif,
                    ]);

                    throw new \RuntimeException(
                        "Tidak bisa mengubah {$table}.{$col} menjadi BIGINT UNSIGNED: "
                        . "ada {$negatif} baris bernilai negatif. Periksa dan perbaiki "
                        . "baris tersebut lebih dulu, lalu jalankan ulang migrasi ini."
                    );
                }

                DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` BIGINT UNSIGNED {$definisi}");
            }
        }
    }

    /**
     * Kembali ke INT.
     *
     * Ini bisa memotong data: nilai di atas 2.147.483.647 — persis nilai yang
     * jadi alasan migrasi ini ada — tidak muat lagi. Jadi rollback ditolak
     * kalau ada baris seperti itu, bukan dijalankan sambil merusak angka.
     */
    public function down(): void
    {
        $batasInt = 2147483647;

        foreach ($this->columns as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $col => $definisi) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }

                $terlaluBesar = DB::table($table)->where($col, '>', $batasInt)->count();

                if ($terlaluBesar > 0) {
                    throw new \RuntimeException(
                        "Tidak bisa mengembalikan {$table}.{$col} ke INT: ada "
                        . "{$terlaluBesar} baris melebihi {$batasInt} yang akan terpotong."
                    );
                }

                DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` INT {$definisi}");
            }
        }
    }
};
