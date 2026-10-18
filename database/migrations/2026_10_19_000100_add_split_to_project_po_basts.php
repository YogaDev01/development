<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penagihan Invoice per BAST — Fase 1b (pemecahan BAST sintetis), murni additive.
 *
 * - project_po_basts.origin: asal baris (BACKFILL = hasil migrasi 2026_10_18_000100,
 *   SPLIT_INVOICE / SPLIT_SISA = hasil pemecahan, NULL = submit normal).
 * - project_po_basts.split_from_bast_id / split_run_id: BAST induk & run pemecahannya.
 * - bast_split_log: jejak tiap penulisan command bast:mark-backfill dan
 *   invoice:split-synthetic-bast (dasar --rollback).
 *
 * Tidak ada kolom/tabel/baris yang dihapus atau diubah nilainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_po_basts')) {
            throw new \RuntimeException(
                'Tabel project_po_basts tidak ada. Jalankan migrasi 2026_10_18_000100 dan 2026_10_18_000200 dulu.'
            );
        }

        if (!Schema::hasColumn('project_po_basts', 'origin')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                $table->string('origin', 20)->nullable();
            });
        }

        if (!Schema::hasColumn('project_po_basts', 'split_run_id')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                $table->string('split_run_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('project_po_basts', 'split_from_bast_id')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                $table->unsignedBigInteger('split_from_bast_id')->nullable()->index();
                $table->foreign('split_from_bast_id')->references('id')->on('project_po_basts')->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('bast_split_log')) {
            Schema::create('bast_split_log', function (Blueprint $table) {
                $table->id();
                $table->string('run_id')->index();
                $table->unsignedBigInteger('project_po_number_id')->index();
                $table->unsignedBigInteger('parent_bast_id');
                $table->unsignedBigInteger('new_bast_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('old_invoice_bast_id')->nullable();
                // CREATE_SPLIT | CREATE_SISA | LINK_INVOICE | LINK_HISTORY | TRASH_PARENT | MARK_BACKFILL
                $table->string('action', 30);
                $table->unsignedBigInteger('nilai')->nullable();
                $table->string('flag')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Hanya dihapus bila kosong: jejak pemecahan dan BAST pecahan tidak boleh ikut hilang.
        $adaLog = Schema::hasTable('bast_split_log') && DB::table('bast_split_log')->exists();
        $adaTanda = Schema::hasColumn('project_po_basts', 'origin')
            && DB::table('project_po_basts')->where(function ($q) {
                $q->whereNotNull('origin')->orWhereNotNull('split_from_bast_id')->orWhereNotNull('split_run_id');
            })->exists();
        if ($adaLog || $adaTanda) {
            throw new \RuntimeException(
                'Rollback migrasi dibatalkan: masih ada baris bast_split_log atau BAST bertanda origin/pecahan. '
                . 'Jalankan "php artisan invoice:split-synthetic-bast --rollback=<run_id> --apply" dulu; '
                . 'jejak audit harus diarsipkan/dikosongkan oleh manusia.'
            );
        }

        Schema::dropIfExists('bast_split_log');

        if (Schema::hasColumn('project_po_basts', 'split_from_bast_id')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                $table->dropForeign(['split_from_bast_id']);
                $table->dropColumn('split_from_bast_id');
            });
        }
        if (Schema::hasColumn('project_po_basts', 'split_run_id')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                $table->dropColumn('split_run_id');
            });
        }
        if (Schema::hasColumn('project_po_basts', 'origin')) {
            Schema::table('project_po_basts', function (Blueprint $table) {
                $table->dropColumn('origin');
            });
        }
    }
};
