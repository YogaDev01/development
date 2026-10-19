<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penagihan Invoice per BAST — Fase 1 (skema saja, murni additive).
 *
 * - invoices.project_po_bast_id: tautan invoice ke BAST yang ditagih. Nullable;
 *   invoice lama tetap memakai project_po_number_id (kolom lama tidak disentuh).
 * - project_po_basts: jejak hapus (soft delete) seperti tabel keuangan lain.
 * - invoice_bast_links_log: jejak setiap tautan yang ditulis command
 *   `invoice:link-bast`, dasar untuk --rollback=<run_id>.
 *
 * Tidak ada kolom/tabel/baris yang dihapus atau diubah nilainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jangan dilewati diam-diam: tanpa tabel ini kolom/FK tidak bisa dibuat dan Fase 1 tidak lengkap.
        foreach (['invoices', 'project_po_basts'] as $tabel) {
            if (!Schema::hasTable($tabel)) {
                throw new \RuntimeException(
                    "Tabel {$tabel} tidak ada. Jalankan migrasi 2026_10_18_000100_create_project_po_basts_table dulu "
                    . '(dan pastikan migrasi tabel invoices sudah berjalan), lalu ulangi migrasi ini.'
                );
            }
        }

        if (!Schema::hasColumn('invoices', 'project_po_bast_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('project_po_bast_id')->nullable()->after('project_po_number_id');
                $table->foreign('project_po_bast_id')->references('id')->on('project_po_basts')->restrictOnDelete();
            });
        }

        Schema::table('project_po_basts', function (Blueprint $table) {
            if (!Schema::hasColumn('project_po_basts', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('project_po_basts', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('project_po_basts', 'delete_reason')) {
                $table->text('delete_reason')->nullable();
            }
        });

        if (!Schema::hasTable('invoice_bast_links_log')) {
            Schema::create('invoice_bast_links_log', function (Blueprint $table) {
                $table->id();
                $table->string('run_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('project_po_number_id');
                $table->unsignedBigInteger('project_po_bast_id');
                $table->string('rule');
                $table->string('flag')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Tabel/kolom hanya dihapus bila kosong: tidak ada data tautan, jejak, atau BAST terhapus yang ikut hilang.
        $terTaut = Schema::hasColumn('invoices', 'project_po_bast_id')
            && DB::table('invoices')->whereNotNull('project_po_bast_id')->exists();
        $adaLog = Schema::hasTable('invoice_bast_links_log')
            && DB::table('invoice_bast_links_log')->exists();
        if ($terTaut || $adaLog) {
            throw new \RuntimeException(
                'Rollback migrasi dibatalkan: masih ada invoice yang tertaut ke BAST atau baris di invoice_bast_links_log. '
                . 'Jalankan "php artisan invoice:link-bast --rollback=<run_id> --apply" dulu untuk mengosongkan tautan; '
                . 'tabel jejak (invoice_bast_links_log) berisi data audit dan harus diarsipkan/dikosongkan oleh manusia.'
            );
        }
        if (Schema::hasColumn('project_po_basts', 'deleted_at')
            && DB::table('project_po_basts')->whereNotNull('deleted_at')->exists()) {
            throw new \RuntimeException(
                'Rollback migrasi dibatalkan: ada BAST yang sudah di-soft-delete; menghapus kolom deleted_at akan menghidupkannya kembali.'
            );
        }

        Schema::dropIfExists('invoice_bast_links_log');

        if (Schema::hasTable('project_po_basts')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                if (Schema::hasColumn('project_po_basts', 'deleted_by')) {
                    $table->dropConstrainedForeignId('deleted_by');
                }
                if (Schema::hasColumn('project_po_basts', 'delete_reason')) {
                    $table->dropColumn('delete_reason');
                }
                if (Schema::hasColumn('project_po_basts', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'project_po_bast_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['project_po_bast_id']);
                $table->dropColumn('project_po_bast_id');
            });
        }
    }
};
