<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Second Payment — dibuat OTOMATIS oleh sistem saat Settlement (realisasi
    | Cash Advance) sudah SETTLE_VERIFIED tapi nilainya melebihi Original
    | Approved Amount (selisih > 0). CR baru boleh CLOSED setelah baris ini
    | PAID. Tetap satu nomor CR (bukan CR baru) — lihat
    | BudgetRequestController::financeVerifySettlement()/markSecondPaymentPaid().
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::create('budget_request_second_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained('budget_request')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['PENDING', 'PAID'])->default('PENDING');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_request_second_payments');
    }
};
