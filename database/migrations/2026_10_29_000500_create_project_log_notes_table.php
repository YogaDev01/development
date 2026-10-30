<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan bebas (gaya chat) pada tab Log Purchase Order. Pola sama dengan
 * request_log_notes (Cost Request): terpisah dari project_logs supaya riwayat
 * workflow tidak tercampur, immutable (hanya created_at), aktor disimpan
 * sebagai snapshot nama + role.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_log_notes')) {
            return;
        }

        Schema::create('project_log_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 60)->nullable();
            $table->text('notes');
            $table->timestamp('created_at')->useCurrent();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_log_notes');
    }
};
