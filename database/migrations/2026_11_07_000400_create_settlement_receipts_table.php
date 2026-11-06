<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kwitansi digital per baris settlement: ditandatangani PENERIMA UANG di layar
 * HP teknisi, seperti kwitansi kertas. Baris tidak pernah dihapus; yang berubah
 * hanya status menjadi SUPERSEDED saat nilai barisnya berubah.
 *
 * realization_id sengaja tanpa FK: baris realisasi bisa dihapus saat draft
 * disunting, sedangkan kwitansi harus tetap ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settlement_receipts')) {
            return;
        }

        Schema::create('settlement_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('trouble_ticket_id')->nullable();
            $table->unsignedBigInteger('realization_id');
            $table->unsignedBigInteger('request_detail_id')->nullable();
            $table->string('recipient_name', 150);
            $table->unsignedBigInteger('amount');
            $table->string('purpose', 500)->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('signature_path');
            $table->string('pdf_path');
            $table->timestamp('signed_at');
            $table->unsignedBigInteger('captured_by')->nullable();
            // VALID | SUPERSEDED
            $table->string('status', 12)->default('VALID');
            // Hanya terisi selagi VALID: paling banyak satu kwitansi berlaku per baris.
            $table->unsignedBigInteger('active_realization_id')
                ->nullable()
                ->storedAs("CASE WHEN status = 'VALID' THEN realization_id END");
            $table->timestamps();

            $table->unique('active_realization_id');
            $table->index('realization_id');
            $table->foreign('budget_request_id')->references('id')->on('budget_request')->restrictOnDelete();
            $table->foreign('trouble_ticket_id')->references('id')->on('trouble_tickets')->restrictOnDelete();
            $table->foreign('captured_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Kwitansi adalah catatan keuangan: tidak ikut hilang saat rollback.
        if (Schema::hasTable('settlement_receipts') && DB::table('settlement_receipts')->exists()) {
            throw new \RuntimeException(
                'Rollback dibatalkan: settlement_receipts berisi kwitansi. Arsipkan/kosongkan oleh manusia lebih dulu.'
            );
        }

        Schema::dropIfExists('settlement_receipts');
    }
};
