<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_role')->nullable();
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question')->nullable();
            $table->string('function_name')->nullable();
            $table->json('function_params')->nullable();
            $table->string('model')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_code')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
    }
};
