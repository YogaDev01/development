<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat per-assignment (create/update/done/cancel/reopen). Immutable, pola
 * sama dengan request_status_logs: timestamps=false, hanya created_at, actor
 * dicatat sebagai snapshot (nama + role) agar tetap terbaca walau user berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignment_logs')) {
            return;
        }

        Schema::create('assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('action', 20); // create | update | done | cancel | reopen
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 60)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_logs');
    }
};
