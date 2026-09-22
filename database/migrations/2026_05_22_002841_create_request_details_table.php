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
        Schema::create('request_detail', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELATION
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('budget_request_id');

            $table->foreign('budget_request_id')
                ->references('id')
                ->on('budget_request')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | DETAIL INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->text('description');

            $table->decimal('amount', 18, 2);

            $table->bigInteger('qty');

            $table->decimal('total', 18, 2);

            $table->text('notes')->nullable();

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
        Schema::dropIfExists('request_detail');
    }
};