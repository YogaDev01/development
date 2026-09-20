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
        Schema::create('clusters', function (Blueprint $table) {

            $table->id();

            $table->string('code');

            $table->string('name');

            $table->string('status')
                ->default('ACTIVE');

            /*
            |--------------------------------------------------------------------------
            | RELATION
            |--------------------------------------------------------------------------
            */

            // CUSTOMER
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            // GROUP
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('cluster_groups')
                ->nullOnDelete();

            // USER INPUT
            $table->foreignId('input_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | OTHER
            |--------------------------------------------------------------------------
            */

            $table->string('process_code')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
