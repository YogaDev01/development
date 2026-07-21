<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | DAILY TRANSACTIONS — header batch harian
        |----------------------------------------------------------------------
        */
        Schema::create('daily_transactions', function (Blueprint $table) {
            $table->id();

            // Format: 202606070140001
            // YYYY + MM + DD + jumlah_cr(3digit) + urutan_bulan(4digit)
            $table->string('code', 20)->unique();

            $table->date('date'); // tanggal batch dibuat

            $table->enum('status', [
                'PENDING_APPROVER', // menunggu review CEO
                'APPROVED',         // CEO approve semua → CR jadi PENDING_PAYMENT
                'REJECTED',         // CEO reject seluruh batch
            ])->default('PENDING_APPROVER');

            $table->text('notes')->nullable(); // catatan finance saat buat batch

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });

        /*
        |----------------------------------------------------------------------
        | DAILY TRANSACTION ITEMS — detail CR dalam batch
        |----------------------------------------------------------------------
        */
        Schema::create('daily_transaction_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_transaction_id')
                ->constrained('daily_transactions')
                ->cascadeOnDelete();

            $table->foreignId('budget_request_id')
                ->constrained('budget_request')
                ->cascadeOnDelete();

            $table->enum('status', [
                'INCLUDED', // masih dalam batch
                'REJECTED', // di-takeout oleh CEO
            ])->default('INCLUDED');

            $table->text('notes')->nullable(); // alasan CEO reject item ini

            $table->timestamps();

            // Satu CR hanya boleh ada 1 kali di seluruh batch yang aktif
            // Enforce di application level, bukan DB constraint
            // karena CR bisa masuk batch baru setelah di-takeout
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_transaction_items');
        Schema::dropIfExists('daily_transactions');
    }
};