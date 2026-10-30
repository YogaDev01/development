<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan bebas (gaya chat) pada tab Log Budget Plan. Pola sama dengan
 * request_log_notes (Cost Request): terpisah dari budget_plan_logs supaya
 * riwayat workflow tidak tercampur, immutable (hanya created_at), aktor
 * disimpan sebagai snapshot nama + role.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_plan_log_notes')) {
            return;
        }

        Schema::create('budget_plan_log_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained('budget_plans')->restrictOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 60)->nullable();
            $table->text('notes');
            $table->timestamp('created_at')->useCurrent();

            $table->index('budget_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_log_notes');
    }
};
