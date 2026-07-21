<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | MAIN INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->text('description');

            $table->decimal('amount', 18, 2);

            /*
            |--------------------------------------------------------------------------
            | RELATION
            |--------------------------------------------------------------------------
            */

            $table->foreignId('project_id')
                ->constrained()
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->foreignId('input_by')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_plans');
    }
};