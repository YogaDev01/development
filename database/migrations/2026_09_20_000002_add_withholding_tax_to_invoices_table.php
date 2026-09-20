<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MN-07 — Withholding Tax (PPh) di level Invoice.
 *
 * KONTEKS PENTING sebelum membaca kolom ini:
 *
 * Aplikasi ini SUDAH punya withholding_tax, tapi di tabel `cash_receipts` —
 * yaitu PPh yang baru diketahui & di-settle SAAT pembayaran masuk (customer
 * memotong, selisihnya ditutup lewat menu "Settle PPh" di halaman invoice).
 *
 * Kolom baru di `invoices` ini untuk kasus yang BERBEDA: PPh yang sudah
 * diketahui dan dipotong SEJAK invoice diterbitkan, sehingga ikut mengurangi
 * nilai yang ditagihkan:
 *
 *     captured_total = billed_amount + tax_amount - withholding_tax
 *
 * Keduanya tidak saling menghitung dobel: potongan di muka mengecilkan
 * captured_total (target pembayaran), sedangkan potongan di belakang menutup
 * sisa target lewat cash receipt.
 *
 * Default 0 = perilaku lama persis. Tidak ada backfill dan tidak ada nilai
 * historis yang berubah; invoice lama tetap captured_total = DPP + PPN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('withholding_tax')
                ->default(0)
                ->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('withholding_tax');
        });
    }
};
