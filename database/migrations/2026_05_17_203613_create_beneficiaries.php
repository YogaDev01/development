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
        Schema::create('beneficiaries', function (Blueprint $table) {

            $table->id();

            $table->string('nickname');

            $table->string('complete_name');

            $table->string('contact')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | BANK
            |--------------------------------------------------------------------------
            */

            $table->foreignId('bank_id')
                ->nullable()
                ->constrained('banks')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ACCOUNT
            |--------------------------------------------------------------------------
            */

            $table->string('account_number')
                ->nullable();

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
        Schema::dropIfExists('beneficiaries');
    }
};
