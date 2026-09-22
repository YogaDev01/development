<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-01 — Cash Management / Cash Flow.
 *
 * KEPUTUSAN RANCANGAN YANG PENTING DIBACA:
 *
 * Tabel ini TIDAK menyalin transaksi dari modul lain. Kas yang bergerak di
 * TESYS sudah punya catatan sendiri yang sah:
 *
 *     uang masuk  -> cash_receipts   (pelunasan invoice)
 *     uang keluar -> daily_transactions (Treasury: batch pembayaran & top-up)
 *
 * Kalau baris-baris itu digandakan ke sini, setiap perubahan di modul asalnya
 * harus disinkronkan, dan cepat atau lambat dua angka untuk satu kejadian yang
 * sama akan berbeda. Karena itu buku besar kas disusun di CashFlowService
 * sebagai gabungan (union) sumber-sumber existing + tabel ini.
 *
 * Yang DISIMPAN di sini hanya pergerakan kas yang TIDAK punya rumah di modul
 * lain: setoran modal, pendapatan lain-lain, biaya bank, koreksi/penyesuaian
 * kas, dan sejenisnya. Kolom source_module/source_id/reference_number tetap
 * disediakan supaya entri manual yang memang merujuk dokumen lain bisa
 * ditelusuri balik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();
            $table->date('date');
            $table->string('description');

            // Rekening/kas perusahaan yang terpengaruh.
            $table->foreignId('source_fund_id')
                ->nullable()
                ->constrained('source_funds')
                ->nullOnDelete();

            // Total dihitung ulang dari baris detail (lihat CashFlow::recalculateTotals).
            $table->integer('total_in')->default(0);
            $table->integer('total_out')->default(0);

            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT');

            /*
            | Penelusuran balik ke dokumen asal, kalau entri manual ini memang
            | mewakili sesuatu dari modul lain. Sengaja TIDAK memakai foreign
            | key: source_id bisa menunjuk tabel yang berbeda-beda.
            */
            $table->string('source_module', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_number')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();
            // Catatan keuangan ditandai, bukan dilenyapkan — pola sama dengan
            // migrasi 2026_09_18_000002 untuk tabel finansial lain.
            $table->softDeletes();

            $table->index(['date', 'status']);
            $table->index(['source_module', 'source_id']);
            $table->index('source_fund_id');
        });

        Schema::create('cash_flow_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_flow_id')
                ->constrained('cash_flows')
                ->cascadeOnDelete();

            // Arah ditaruh di DETAIL, bukan header: satu dokumen penyesuaian kas
            // sering memuat masuk dan keluar sekaligus (mis. koreksi rekening
            // koran), dan memaksanya jadi dua dokumen hanya memecah jejak audit.
            $table->enum('direction', ['IN', 'OUT']);

            $table->string('category', 60);
            $table->string('description');
            $table->integer('amount');

            $table->string('source_module', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_number')->nullable();

            $table->timestamps();

            $table->index(['cash_flow_id', 'direction']);
            $table->index(['source_module', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_details');
        Schema::dropIfExists('cash_flows');
    }
};
