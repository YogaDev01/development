<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan bebas (gaya chat) pada tab Log Cost Request. Dipisah dari
 * request_status_logs supaya tidak mengganggu pembacaan transisi status
 * (aging, statistik Todo List, banner revisi). Immutable: hanya created_at,
 * aktor disimpan sebagai snapshot nama + role.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_log_notes')) {
            return;
        }

        Schema::create('request_log_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained('budget_request')->restrictOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 60)->nullable();
            $table->text('notes');
            $table->timestamp('created_at')->useCurrent();

            $table->index('budget_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_log_notes');
    }
};
