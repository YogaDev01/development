<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting — perbaikan hasil review (2026-11-14). Aditif, dua kolom nullable:
 *   journal_sources.source_snapshot : sidik nilai sumber saat jurnal dibuat; dibanding ulang
 *                                     saat Post untuk mendeteksi sumber yang berubah.
 *   journals.last_edited_by         : editor terakhir Draft; pemosting tidak boleh sama dengan
 *                                     pembuat maupun editor terakhir (pemisahan tugas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_sources') && ! Schema::hasColumn('journal_sources', 'source_snapshot')) {
            Schema::table('journal_sources', function (Blueprint $table) {
                $table->string('source_snapshot', 191)->nullable()->after('source_number');
            });
        }
        if (Schema::hasTable('journals') && ! Schema::hasColumn('journals', 'last_edited_by')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->foreignId('last_edited_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journals', 'last_edited_by')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropConstrainedForeignId('last_edited_by');
            });
        }
        if (Schema::hasColumn('journal_sources', 'source_snapshot')) {
            Schema::table('journal_sources', function (Blueprint $table) {
                $table->dropColumn('source_snapshot');
            });
        }
    }
};
