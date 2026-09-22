<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('description');
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('deduction')->default(0);
            $table->unsignedBigInteger('total')->default(0); // (unit_price * qty) - deduction

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};