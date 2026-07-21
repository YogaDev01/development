<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plan_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('budget_plan_id')
                ->constrained('budget_plans')
                ->cascadeOnDelete();

            // main = budget awal, additional = extend
            $table->enum('type', ['main', 'additional'])->default('main');

            $table->enum('category', [
                'Variable Cost',
                'Fixed Cost',
                'Overhead',
            ]);

            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->unsignedBigInteger('amount')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_details');
    }
};