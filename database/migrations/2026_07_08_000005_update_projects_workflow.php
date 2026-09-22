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
        | 1. Update status di projects — VARCHAR sudah ada, update data lama
        |----------------------------------------------------------------------
        */

        // Mapping status lama → baru
        DB::statement("UPDATE projects SET status = 'ACTIVE' WHERE status IN ('ACTIVE','On Progress','active')");
        DB::statement("UPDATE projects SET status = 'DRAFT'  WHERE status NOT IN ('DRAFT','SUBMITTED','WAITING_PO','ACTIVE','CLOSED')");

        /*
        |----------------------------------------------------------------------
        | 2. Tambah kolom baru ke projects
        |----------------------------------------------------------------------
        */
        Schema::table('projects', function (Blueprint $table) {

            if (!Schema::hasColumn('projects', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('projects', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('projects', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('projects', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()->after('closed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'closed_by')) {
                $table->foreignId('closed_by')
                    ->nullable()->after('approved_by')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'closing_notes')) {
                $table->text('closing_notes')->nullable()->after('closed_by');
            }
        });

        /*
        |----------------------------------------------------------------------
        | 3. Buat tabel project_logs
        |----------------------------------------------------------------------
        */
        Schema::create('project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('status_from', 50)->nullable();
            $table->string('status_to', 50)->nullable();
            $table->string('action', 50);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('role', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_logs');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'submitted_at', 'approved_at', 'closed_at',
                'approved_by', 'closed_by', 'closing_notes',
            ]);
        });
    }
};