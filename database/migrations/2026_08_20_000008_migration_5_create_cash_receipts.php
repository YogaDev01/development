<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->date('received_date');
            $table->unsignedBigInteger('amount');
            $table->foreignId('source_fund_id')->nullable()->constrained('source_funds')->nullOnDelete();

            $table->string('proof_file')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_receipts');
    }
};