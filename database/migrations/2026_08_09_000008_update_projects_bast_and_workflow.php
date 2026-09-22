<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | 1. Tambah kolom BAST ke projects
        |----------------------------------------------------------------------
        */
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'no_bast')) {
                $table->string('no_bast')->nullable()->after('closing_notes');
            }
            if (!Schema::hasColumn('projects', 'tanggal_bast')) {
                $table->date('tanggal_bast')->nullable()->after('no_bast');
            }
            if (!Schema::hasColumn('projects', 'nilai_bast')) {
                $table->unsignedBigInteger('nilai_bast')->nullable()->after('tanggal_bast');
            }
            if (!Schema::hasColumn('projects', 'bast_notes')) {
                $table->text('bast_notes')->nullable()->after('nilai_bast');
            }
            if (!Schema::hasColumn('projects', 'bast_submitted_by')) {
                $table->foreignId('bast_submitted_by')
                    ->nullable()->after('bast_notes')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'bast_submitted_at')) {
                $table->timestamp('bast_submitted_at')->nullable()->after('bast_submitted_by');
            }
        });

        /*
        |----------------------------------------------------------------------
        | 2. Update status lama → baru
        |    Tambah: PROGRESS_DONE, BILLED, CLOSING
        |    (PAID dan CLOSED sudah ada)
        |----------------------------------------------------------------------
        */
        // Tidak perlu ALTER ENUM karena status sudah VARCHAR
        // Hanya mapping data lama jika ada
        DB::statement("
            UPDATE projects
            SET status = 'ACTIVE'
            WHERE status IN ('On Progress', 'on_progress', 'ACTIVE')
        ");
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['bast_submitted_by']);
            $table->dropColumn([
                'no_bast', 'tanggal_bast', 'nilai_bast',
                'bast_notes', 'bast_submitted_by', 'bast_submitted_at',
            ]);
        });
    }
};