<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | BAST sekarang disubmit per No. PO (bukan per item Scope of Work lagi).
    | Field-nya sama seperti BAST level Project yang sudah ada (no_bast,
    | tanggal_bast, nilai_bast, bast_notes, bast_submitted_by/at), dipindah
    | granularitasnya ke sini. Kolom BAST di tabel projects TIDAK dihapus —
    | data lama tetap ada, cuma tidak dipakai lagi untuk alur baru.
    |--------------------------------------------------------------------------
    */
    public function up(): void
    {
        Schema::table('project_po_numbers', function (Blueprint $table) {
            $table->string('no_bast')->nullable()->after('status');
            $table->date('tanggal_bast')->nullable()->after('no_bast');
            $table->unsignedBigInteger('nilai_bast')->nullable()->after('tanggal_bast');
            $table->text('bast_notes')->nullable()->after('nilai_bast');
            $table->unsignedBigInteger('bast_submitted_by')->nullable()->after('bast_notes');
            $table->timestamp('bast_submitted_at')->nullable()->after('bast_submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('project_po_numbers', function (Blueprint $table) {
            $table->dropColumn(['no_bast', 'tanggal_bast', 'nilai_bast', 'bast_notes', 'bast_submitted_by', 'bast_submitted_at']);
        });
    }
};
