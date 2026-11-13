<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash & Bank — saldo awal rekening. Satu baris per rekening (unique), dengan
 * maker-checker: yang membuat tidak boleh memverifikasi. Setelah APPROVED saldo
 * awal tidak diubah langsung; koreksi lewat Cash Adjustment (Fase 2).
 * Saldo awal bisa negatif (overdraft) sehingga bigInteger bertanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_opening_balances')) {
            return;
        }

        Schema::create('cash_opening_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_fund_id')->unique()
                ->constrained('source_funds')->restrictOnDelete();

            $table->bigInteger('amount');
            $table->date('effective_date');
            $table->string('status', 20)->default('PENDING'); // PENDING | APPROVED | REJECTED
            $table->string('supporting_document')->nullable(); // disk secure
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('reject_reason')->nullable();

            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_opening_balances');
    }
};
