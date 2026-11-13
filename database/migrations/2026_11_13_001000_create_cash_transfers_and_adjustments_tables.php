<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash & Bank Fase 2 — Internal Transfer dan Cash Adjustment.
 *
 * Dokumen ini BUKAN ledger kedua: baru memengaruhi saldo saat status APPROVED,
 * dan dibaca CashFlowService bersama sumber kas lain. Nominal bigInteger
 * (rupiah) positif; arah adjustment disimpan di kolom `direction`.
 * Tidak ada soft delete/hapus: dokumen yang salah di-CANCEL / REJECT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_transfers')) {
            Schema::create('cash_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number', 30)->unique();
                $table->date('transfer_date');
                $table->foreignId('from_fund_id')->constrained('source_funds')->restrictOnDelete();
                $table->foreignId('to_fund_id')->constrained('source_funds')->restrictOnDelete();
                $table->unsignedBigInteger('amount');
                $table->unsignedBigInteger('fee')->default(0);
                $table->string('description')->nullable();
                $table->string('attachment')->nullable();
                $table->string('status', 20)->default('PENDING'); // PENDING | APPROVED | REJECTED | CANCELLED
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('reject_reason')->nullable();
                $table->timestamps();
                $table->index(['status', 'transfer_date']);
            });
        }

        if (! Schema::hasTable('cash_adjustments')) {
            Schema::create('cash_adjustments', function (Blueprint $table) {
                $table->id();
                $table->string('adjustment_number', 30)->unique();
                $table->foreignId('source_fund_id')->constrained('source_funds')->restrictOnDelete();
                $table->date('effective_date');
                // OPENING_CORRECTION | BANK_CHARGE | INTEREST_INCOME | PETTY_CASH | OTHER
                $table->string('type', 30);
                $table->string('direction', 3); // IN | OUT
                $table->unsignedBigInteger('amount');
                $table->string('reason', 500);
                $table->string('attachment')->nullable();
                $table->string('status', 20)->default('PENDING'); // PENDING | APPROVED | REJECTED | CANCELLED
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('reject_reason')->nullable();
                $table->timestamps();
                $table->index(['status', 'effective_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_adjustments');
        Schema::dropIfExists('cash_transfers');
    }
};
