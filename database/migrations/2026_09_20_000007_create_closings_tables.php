<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-04 — Closing (tutup periode finance).
 *
 * Menggantikan halaman Closing yang selama ini MOCKUP (angkanya ditulis
 * tangan di ClosingController, tidak tersambung ke data mana pun).
 *
 * Yang disimpan di sini adalah REKAP, bukan salinan transaksi. Detail closing
 * menunjuk balik ke dokumen sumbernya (source_module + source_id) sehingga:
 *   - tidak ada transaksi yang tercatat dua kali;
 *   - setiap baris rekap bisa ditelusuri ke dokumen aslinya;
 *   - transaksi yang terlewat ketahuan, karena rekap dibangun dari query atas
 *     seluruh dokumen dalam periode, bukan dari input manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closings', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();

            // Periode "YYYY-MM" disimpan eksplisit supaya gampang dicari, dan
            // batas tanggalnya tetap tersimpan terpisah untuk periode yang
            // tidak persis satu bulan kalender.
            $table->string('period', 7);
            $table->date('start_date');
            $table->date('end_date');

            $table->enum('status', ['DRAFT', 'CLOSED', 'REOPENED'])->default('DRAFT');

            $table->integer('total_transaction')->default(0);
            $table->bigInteger('total_value')->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->string('reopen_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Satu periode hanya boleh punya satu closing aktif.
            $table->unique(['period', 'deleted_at'], 'closings_period_unique');
            $table->index('status');
        });

        Schema::create('closing_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('closing_id')
                ->constrained('closings')
                ->cascadeOnDelete();

            // Modul sumber: TREASURY, INVOICE, COLLECTION, CASH_RECEIPT,
            // COST_REQUEST, PURCHASE_ORDER. Tanpa foreign key karena source_id
            // menunjuk tabel yang berbeda-beda.
            $table->string('source_module', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('reference_number')->nullable();

            $table->date('transaction_date')->nullable();
            $table->string('description')->nullable();
            $table->string('status_at_closing', 40)->nullable();
            $table->bigInteger('amount')->default(0);

            $table->timestamps();

            $table->index(['closing_id', 'source_module']);
            $table->index(['source_module', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_details');
        Schema::dropIfExists('closings');
    }
};
