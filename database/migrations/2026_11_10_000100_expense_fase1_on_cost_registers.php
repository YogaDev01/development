<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense (Fase 1) — modul Cost Register berganti nama menjadi Expense dan
 * tetap memakai tabel cost_registers (rencana: docs/plans/2026-11-10-expense-module.md).
 *
 * Perubahan skema (TANPA mengubah isi data):
 *
 * cost_registers
 *   + expense_no           nomor dokumen Expense (DocumentSequence); data lama NULL.
 *   ~ project_id           NULLABLE. Expense baru menaruh tujuan (PO / Cost Center)
 *                          per baris rincian, header tidak lagi terikat satu PO.
 *                          FK cascadeOnDelete -> RESTRICT (CLAUDE.md §5: FK ke
 *                          catatan keuangan tidak boleh cascade).
 *   ~ type                 NULLABLE. Data lama tetap FIXED_COST/OVERHEAD; Expense
 *                          baru NULL karena kategori biaya ikut akun per rincian.
 *
 * cost_register_details
 *   + project_id           tujuan PO (FK RESTRICT)          } tepat satu terisi
 *   + cost_center_id       tujuan Cost Center (FK RESTRICT) } untuk Expense baru
 *   + cost_category        snapshot kategori akun saat disimpan (Variable Cost /
 *                          Fixed Cost / Overhead), supaya laporan PO tidak
 *                          berubah bila kategori akun di master diganti.
 *
 * daily_transaction_items.cost_register_id : FK cascadeOnDelete -> RESTRICT.
 *   Hanya Expense DRAFT yang boleh dihapus dan DRAFT tidak pernah masuk batch,
 *   jadi tidak ada alur yang terhalang.
 *
 * down(): menolak berjalan bila sudah ada Expense tanpa project_id (Expense
 * baru), karena mengembalikan NOT NULL akan gagal / kehilangan data.
 */
return new class extends Migration
{
    /** FK hanya dilepas bila memang ada, supaya down() bisa diulang setelah gagal di tengah. */
    private function punyaForeignKey(string $tabel, string $kolom): bool
    {
        foreach (Schema::getForeignKeys($tabel) as $fk) {
            if (in_array($kolom, array_map('strtolower', $fk['columns'] ?? []), true)) {
                return true;
            }
        }

        return false;
    }

    public function up(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        if (! Schema::hasColumn('cost_registers', 'expense_no')) {
            Schema::table('cost_registers', function (Blueprint $table) {
                $table->string('expense_no', 30)->nullable()->unique()->after('id');
            });
        }

        // project_id: lepas FK cascade, jadikan nullable, pasang lagi sebagai RESTRICT.
        Schema::table('cost_registers', function (Blueprint $table) {
            if ($this->punyaForeignKey('cost_registers', 'project_id')) {
                $table->dropForeign(['project_id']);
            }
        });
        DB::statement('ALTER TABLE cost_registers MODIFY project_id BIGINT UNSIGNED NULL');
        Schema::table('cost_registers', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE cost_registers MODIFY type ENUM('FIXED_COST','OVERHEAD') NULL");

        Schema::table('cost_register_details', function (Blueprint $table) {
            if (! Schema::hasColumn('cost_register_details', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('account_id')
                    ->constrained('projects')->restrictOnDelete();
            }
            if (! Schema::hasColumn('cost_register_details', 'cost_center_id')) {
                $table->foreignId('cost_center_id')->nullable()->after('project_id')
                    ->constrained('cost_centers')->restrictOnDelete();
            }
            if (! Schema::hasColumn('cost_register_details', 'cost_category')) {
                $table->string('cost_category', 20)->nullable()->after('cost_center_id');
            }
        });

        if (Schema::hasColumn('daily_transaction_items', 'cost_register_id')) {
            Schema::table('daily_transaction_items', function (Blueprint $table) {
                if ($this->punyaForeignKey('daily_transaction_items', 'cost_register_id')) {
                    $table->dropForeign(['cost_register_id']);
                }
                $table->foreign('cost_register_id')->references('id')->on('cost_registers')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        // Baris Expense baru (project_id/type NULL) menghalangi pengembalian kolom ke NOT NULL.
        // Pesan menyebut barisnya supaya jelas data mana yang harus dibereskan lebih dulu.
        $penghalang = DB::table('cost_registers')
            ->where(fn ($q) => $q->whereNull('project_id')->orWhereNull('type'))
            ->orderBy('id')
            ->get(['id', 'expense_no', 'status']);

        if ($penghalang->isNotEmpty()) {
            $daftar = $penghalang->map(
                fn ($r) => "#{$r->id} " . ($r->expense_no ?: '(tanpa nomor)') . " [{$r->status}]"
            )->implode(', ');

            throw new \RuntimeException(
                "Rollback Expense Fase 1 dibatalkan: {$penghalang->count()} baris cost_registers masih memakai "
                . 'skema Expense (project_id/type NULL), sehingga kolom tidak bisa dikembalikan ke NOT NULL '
                . "tanpa kehilangan data. Baris: {$daftar}. Hapus atau isi project_id + type pada baris "
                . 'tersebut lebih dulu, lalu jalankan rollback kembali.'
            );
        }

        if (Schema::hasColumn('daily_transaction_items', 'cost_register_id')) {
            Schema::table('daily_transaction_items', function (Blueprint $table) {
                if ($this->punyaForeignKey('daily_transaction_items', 'cost_register_id')) {
                    $table->dropForeign(['cost_register_id']);
                }
                $table->foreign('cost_register_id')->references('id')->on('cost_registers')->cascadeOnDelete();
            });
        }

        Schema::table('cost_register_details', function (Blueprint $table) {
            foreach (['cost_center_id', 'project_id'] as $kolom) {
                if (! Schema::hasColumn('cost_register_details', $kolom)) {
                    continue;
                }
                if ($this->punyaForeignKey('cost_register_details', $kolom)) {
                    $table->dropForeign([$kolom]);
                }
                $table->dropColumn($kolom);
            }
            if (Schema::hasColumn('cost_register_details', 'cost_category')) {
                $table->dropColumn('cost_category');
            }
        });

        DB::statement("ALTER TABLE cost_registers MODIFY type ENUM('FIXED_COST','OVERHEAD') NOT NULL");

        Schema::table('cost_registers', function (Blueprint $table) {
            if ($this->punyaForeignKey('cost_registers', 'project_id')) {
                $table->dropForeign(['project_id']);
            }
        });
        DB::statement('ALTER TABLE cost_registers MODIFY project_id BIGINT UNSIGNED NOT NULL');
        Schema::table('cost_registers', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        if (Schema::hasColumn('cost_registers', 'expense_no')) {
            Schema::table('cost_registers', function (Blueprint $table) {
                if (Schema::hasIndex('cost_registers', 'cost_registers_expense_no_unique')) {
                    $table->dropUnique('cost_registers_expense_no_unique');
                }
                $table->dropColumn('expense_no');
            });
        }
    }
};
