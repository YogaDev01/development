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
        Schema::create('cluster_groups', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | CODE
            |--------------------------------------------------------------------------
            */

            $table->string('code')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | NAME
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default('ACTIVE');

            /*
            |--------------------------------------------------------------------------
            | USER INPUT
            |--------------------------------------------------------------------------
            */

            $table->foreignId('input_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cluster_groups');
    }
};