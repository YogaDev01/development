<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plan_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('budget_plan_id')
                ->constrained('budget_plans')
                ->cascadeOnDelete();

            // submit | approve | reject | extend_request | extend_approve | extend_reject
            $table->string('action');

            $table->string('role')->nullable();   // role user yang melakukan action

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();    // catatan reject / komentar

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_logs');
    }
};