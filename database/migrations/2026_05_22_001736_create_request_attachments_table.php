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
        Schema::create('request_attachments', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATION
            |--------------------------------------------------------------------------
            */

            $table->foreignId('budget_request_id')
                ->constrained('budget_request')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | FILE INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->string('original_name');

            $table->string('stored_name');

            $table->string('path');

            $table->string('mime_type');

            $table->bigInteger('size');

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMP
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_attachments');
    }
};