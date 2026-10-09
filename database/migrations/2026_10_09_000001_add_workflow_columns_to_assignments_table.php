<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur status Assignment diperluas dari OPEN/DONE/CANCELLED menjadi
 * PENDING/READ/ON_PROGRESS/CLOSED/CANCELLED — lihat App\Models\Assignment.
 * Additive: 2026_10_08_000001 SUDAH berjalan (lokal & kemungkinan host), jadi
 * tidak diedit, ditambah di sini sesuai aturan migrasi proyek.
 *
 * done_at tetap dipakai sebagai timestamp CLOSED (makna "ditutup", bukan cuma
 * "selesai") — tidak perlu kolom baru untuk itu. read_at/started_at baru
 * dibutuhkan untuk menandai transisi PENDING->READ->ON_PROGRESS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('assignments', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('read_at');
            }
        });

        // Backfill nilai status lama (dibuat sebelum alur ini ada) ke nama baru.
        // done_at sudah terisi untuk baris lama berstatus DONE, jadi langsung
        // terbaca sebagai closed_at tanpa perlu diubah.
        DB::table('assignments')->where('status', 'OPEN')->update(['status' => 'PENDING']);
        DB::table('assignments')->where('status', 'DONE')->update(['status' => 'CLOSED']);
    }

    public function down(): void
    {
        DB::table('assignments')->where('status', 'PENDING')->update(['status' => 'OPEN']);
        DB::table('assignments')->where('status', 'CLOSED')->update(['status' => 'DONE']);

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'started_at')) {
                $table->dropColumn('started_at');
            }
            if (Schema::hasColumn('assignments', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });
    }
};
