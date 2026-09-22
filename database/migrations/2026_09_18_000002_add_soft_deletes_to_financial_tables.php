<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan keuangan tidak boleh hilang tanpa jejak.
 *
 * Sebelum ini tidak ada satu pun model yang memakai soft delete, sementara
 * beberapa aksi menghapus baris keuangan secara permanen — yang paling tajam
 * destroyCashReceipt(): satu pemanggilan menghapus catatan uang masuk beserta
 * berkas buktinya, tanpa alasan, tanpa pencatat, dan status invoice diam-diam
 * turun kembali. Setelah itu tidak ada cara mengetahui bahwa pembayaran itu
 * pernah ada.
 *
 * Kolom di bawah membuat penghapusan menjadi penandaan: barisnya tetap ada
 * (dan tetap bisa diaudit), tetapi tidak lagi ikut perhitungan. Siapa yang
 * menghapus dan alasannya ikut tersimpan di baris yang sama.
 */
return new class extends Migration
{
    /** Tabel yang menyimpan angka uang dan karenanya wajib punya jejak hapus. */
    private array $tables = [
        'cash_receipts',
        'invoices',
        'budget_request',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $t->softDeletes();
                }
                if (! Schema::hasColumn($table, 'deleted_by')) {
                    $t->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($table, 'delete_reason')) {
                    $t->string('delete_reason', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'deleted_by')) {
                    $t->dropConstrainedForeignId('deleted_by');
                }
                if (Schema::hasColumn($table, 'delete_reason')) {
                    $t->dropColumn('delete_reason');
                }
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $t->dropSoftDeletes();
                }
            });
        }
    }
};
