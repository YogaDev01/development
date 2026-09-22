<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MN-09 — Cost Register sebagai jalur biaya NON-Cost Request menuju Treasury.
 *
 * TEMUAN AUDIT: Cost Register di aplikasi ini SUDAH bukan turunan Cost Request.
 * Isinya Fixed Cost & Overhead yang diinput manual Finance dan dialokasikan ke
 * PO — tidak ada satu pun kolom atau relasi yang menghubungkannya ke
 * budget_request. Jadi "memisahkan konsep" tidak menuntut pembongkaran; yang
 * benar-benar belum ada adalah ujung alirannya ke Treasury.
 *
 * Yang ditambahkan:
 *   - status       : alur DRAFT -> SUBMITTED -> QUEUED -> PAID (cermin alur CR,
 *                    tapi tanpa rantai approval berjenjang — biaya ini sudah
 *                    disepakati di luar sistem, seperti dicatat di controller).
 *   - beneficiary  : kepada siapa dibayarkan; tanpa ini Treasury tidak punya
 *                    tujuan transfer.
 *   - jejak batch  : batch Treasury mana yang membayarnya, dan kapan.
 *
 * Baris lama diberi status PAID: seluruh Cost Register yang sudah ada memang
 * catatan biaya yang sudah terjadi (dipakai sebagai komponen biaya PO di
 * laporan), bukan pengajuan yang menunggu dibayar. Menandainya DRAFT justru
 * akan memunculkan ratusan "tagihan" palsu di Treasury.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_registers', function (Blueprint $table) {
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'QUEUED', 'PAID', 'CANCELLED'])
                ->default('DRAFT')
                ->after('notes');

            $table->foreignId('beneficiary_id')
                ->nullable()
                ->after('status')
                ->constrained('beneficiaries')
                ->nullOnDelete();

            $table->foreignId('source_fund_id')
                ->nullable()
                ->after('beneficiary_id')
                ->constrained('source_funds')
                ->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->index('status');
        });

        // Baris existing = biaya yang sudah terjadi (lihat catatan di atas).
        \Illuminate\Support\Facades\DB::table('cost_registers')->update([
            'status'  => 'PAID',
            'paid_at' => \Illuminate\Support\Facades\DB::raw('created_at'),
        ]);

        Schema::table('daily_transaction_items', function (Blueprint $table) {
            $table->foreignId('cost_register_id')
                ->nullable()
                ->after('budget_request_id')
                ->constrained('cost_registers')
                ->cascadeOnDelete();

            $table->index('cost_register_id');
        });

        /*
        | budget_request_id dibuat nullable supaya satu baris batch bisa berisi
        | SALAH SATU dari Cost Request ATAU Cost Register. Tidak ada data lama
        | yang berubah: seluruh baris existing tetap terisi budget_request_id.
        */
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE daily_transaction_items MODIFY budget_request_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        Schema::table('daily_transaction_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_register_id');
        });

        Schema::table('cost_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('beneficiary_id');
            $table->dropConstrainedForeignId('source_fund_id');
            $table->dropColumn(['status', 'submitted_at', 'paid_at']);
        });
    }
};
