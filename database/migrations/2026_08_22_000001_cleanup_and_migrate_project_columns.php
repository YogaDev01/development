<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | 1) Hapus kolom BAST level Project — sudah dipindah ke project_po_numbers
    |    (Fase 2). Dicek dulu, tidak ada satu baris pun yang terisi.
    | 2) done_by ("Implement By": TEREX/HANASTA/SUBCON) diganti dengan
    |    financing ("Financing": PL/IL/NL — PO Loan/Invoice Loan/Non Loan).
    |--------------------------------------------------------------------------
    */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['bast_submitted_by']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'no_bast', 'tanggal_bast', 'nilai_bast', 'bast_notes',
                'bast_submitted_by', 'bast_submitted_at',
            ]);

            $table->string('financing', 2)->default('NL')->after('done_by');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('done_by');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('done_by')->nullable()->after('po_number');
            $table->dropColumn('financing');

            $table->string('no_bast')->nullable();
            $table->date('tanggal_bast')->nullable();
            $table->unsignedBigInteger('nilai_bast')->nullable();
            $table->text('bast_notes')->nullable();
            $table->foreignId('bast_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bast_submitted_at')->nullable();
        });
    }
};
