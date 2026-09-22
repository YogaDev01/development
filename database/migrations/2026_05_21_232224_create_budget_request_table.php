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
        Schema::create('budget_request', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | MAIN DATA
            |--------------------------------------------------------------------------
            */

            $table->string('code')->unique();

            $table->text('description');

            $table->decimal('amount', 18, 2);

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | RELATION
            |--------------------------------------------------------------------------
            */

            $table->foreignId('applicant_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('beneficiary_id')
                ->constrained('beneficiaries')
                ->cascadeOnDelete();

            $table->foreignId('type_id')
                ->constrained('request_type')
                ->cascadeOnDelete();

            $table->foreignId('cluster_id')
                ->constrained('clusters')
                ->cascadeOnDelete();

            $table->foreignId('budget_plan_id')
                ->constrained('budget_plans')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default('draft');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_request');
    }
};