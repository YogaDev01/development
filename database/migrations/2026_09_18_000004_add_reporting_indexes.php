<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks yang cocok dengan cara data ini benar-benar dicari.
 *
 * Tabel transaksi sudah punya indeks pada 'status' saja, padahal hampir semua
 * layar menyaring dua hal sekaligus: status DAN rentang tanggal, lalu
 * mengurutkan menurut tanggal. Indeks satu kolom hanya menolong separuh
 * pekerjaan; sisanya tetap memindai baris.
 *
 * Pasangan (status, created_at) melayani ketiganya — saring status, saring
 * rentang, urutkan — dalam satu penelusuran indeks. Indeks 'created_at'
 * sendiri dipasang untuk layar yang menyaring tanggal tanpa status.
 *
 * Baru bermanfaat setelah whereDate() diganti perbandingan langsung pada
 * kolom (DATE(created_at) mematikan pemakaian indeks); keduanya dikerjakan
 * bersamaan.
 */
return new class extends Migration
{
    /** tabel => nama kolom tanggal utama yang dipakai menyaring. */
    private array $targets = [
        'budget_request'     => 'created_at',
        'invoices'           => 'invoice_date',
        'budget_plans'       => 'created_at',
        'daily_transactions' => 'created_at',
        'projects'           => 'created_at',
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $dateColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $dateColumn) {
                $t->index(['status', $dateColumn], "{$table}_status_{$dateColumn}_idx");
                $t->index($dateColumn, "{$table}_{$dateColumn}_idx");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $dateColumn) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $dateColumn) {
                $t->dropIndex("{$table}_status_{$dateColumn}_idx");
                $t->dropIndex("{$table}_{$dateColumn}_idx");
            });
        }
    }
};
