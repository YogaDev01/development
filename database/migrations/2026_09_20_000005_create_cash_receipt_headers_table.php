<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-03 — Cash Receipt Header + Detail.
 *
 * KONFLIK DENGAN STRUKTUR EXISTING, DAN CARA MENYELESAIKANNYA:
 *
 * Tabel `cash_receipts` SUDAH ADA dan sudah dipakai penuh: satu baris = satu
 * pembayaran atas satu invoice, dan seluruh perhitungan pelunasan (Invoice::
 * totalReceived, syncPaymentStatus, Collection Summary, Cash Management) sudah
 * membaca tabel itu. Membuat tabel "cash receipt" yang baru dari nol berarti
 * dua tempat menyimpan pembayaran, dan setiap laporan harus memilih salah satu
 * — persis jenis duplikasi yang dilarang.
 *
 * Jadi struktur Header/Detail dibangun DI ATAS yang sudah ada:
 *
 *     cash_receipt_headers  (baru — satu bukti terima dari satu customer)
 *              |
 *              v
 *     cash_receipts         (tabel LAMA, kini berperan sebagai DETAIL —
 *                            satu baris per invoice yang dialokasikan)
 *
 * `cash_receipts.cash_receipt_header_id` nullable: baris lama tidak punya
 * header dan tetap sah (pembayaran langsung per invoice). Tidak ada data yang
 * dipindah, diubah, atau dihapus; tidak ada satu pun query existing yang
 * berubah hasilnya.
 *
 * Header berguna untuk kasus yang selama ini tidak terwakili: satu transfer
 * dari customer yang menutup beberapa invoice sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_receipt_headers', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();          // Receipt number
            $table->date('received_date');

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('source_fund_id')
                ->nullable()
                ->constrained('source_funds')
                ->nullOnDelete();

            $table->string('reference_number')->nullable(); // No. bukti transfer
            $table->string('proof_file')->nullable();

            // Nilai yang benar-benar diterima menurut bukti transfer. Total
            // alokasi ke invoice dijumlah dari detail dan dibandingkan dengan
            // angka ini — selisihnya yang jadi "belum teralokasi".
            $table->integer('total_amount')->default(0);

            $table->enum('status', ['DRAFT', 'ALLOCATED', 'CANCELLED'])->default('DRAFT');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['received_date', 'status']);
            $table->index('customer_id');
        });

        Schema::table('cash_receipts', function (Blueprint $table) {
            $table->foreignId('cash_receipt_header_id')
                ->nullable()
                ->after('id')
                ->constrained('cash_receipt_headers')
                // Menghapus header TIDAK boleh menghapus pembayarannya: itu
                // akan diam-diam mengubah status pelunasan invoice. Header
                // dibatalkan, bukan dihapus.
                ->nullOnDelete();

            $table->index('cash_receipt_header_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_receipt_header_id');
        });

        Schema::dropIfExists('cash_receipt_headers');
    }
};
