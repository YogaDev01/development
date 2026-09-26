<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen keuangan tidak boleh ikut terhapus bersama data master.
 *
 * budget_request punya lima foreign key dan KELIMANYA ON DELETE CASCADE:
 * applicant_id (users), beneficiary_id, type_id (request_type), cluster_id,
 * budget_plan_id. daily_transactions.created_by juga cascade ke users.
 *
 * Artinya: menghapus satu baris master menghapus permanen seluruh Cost Request
 * yang menunjuknya, beserta request_detail, realisasi, dan request_status_logs
 * yang ikut cascade di belakangnya.
 *
 * Aplikasi sudah menambalnya dengan penjaga manual di controller —
 * UserController, BeneficiaryController, ClusterController masing-masing punya
 * blok BudgetRequest::withTrashed()->where(...)->exists(). Tiga penjaga itu
 * benar dan tetap dipertahankan. Tapi mereka bukan tempat yang benar untuk
 * aturan ini: setiap jalur hapus BARU — route baru, command artisan, perbaikan
 * manual lewat tinker, seeder — melewatinya tanpa halangan apa pun. Komentar di
 * ketiga penjaga itu bahkan menjelaskan sendiri bahwa CASCADE bekerja di level
 * MySQL dan tidak mengenal soft delete; itu pengakuan bahwa desain FK-nya yang
 * salah, bukan penjaganya yang kurang.
 *
 * Setelah migrasi ini, database yang menolak. Penjaga di controller berubah
 * peran menjadi pemberi pesan yang bisa dimengerti ("Nonaktifkan saja")
 * alih-alih satu-satunya pertahanan.
 *
 * Tidak ada risiko baris yatim: FK-nya sudah ada sejak awal, jadi setiap baris
 * yang ada sekarang pasti punya induk yang sah.
 */
return new class extends Migration
{
    /**
     * tabel => [kolom => tabel tujuan]
     */
    private array $keys = [
        'budget_request' => [
            'applicant_id'   => 'users',
            'beneficiary_id' => 'beneficiaries',
            'type_id'        => 'request_type',
            'cluster_id'     => 'clusters',
            'budget_plan_id' => 'budget_plans',
        ],
        'daily_transactions' => [
            'created_by' => 'users',
        ],
    ];

    public function up(): void
    {
        $this->rebuild('restrict');
    }

    public function down(): void
    {
        $this->rebuild('cascade');
    }

    private function rebuild(string $onDelete): void
    {
        foreach ($this->keys as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $column => $target) {
                if (! Schema::hasColumn($table, $column) || ! Schema::hasTable($target)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($column, $target, $onDelete) {
                    // MySQL tidak bisa mengubah aturan ON DELETE sebuah foreign
                    // key di tempat; ia harus dilepas lalu dipasang ulang.
                    $t->dropForeign([$column]);

                    $fk = $t->foreign($column)->references('id')->on($target);

                    $onDelete === 'restrict'
                        ? $fk->restrictOnDelete()
                        : $fk->cascadeOnDelete();
                });
            }
        }
    }
};
