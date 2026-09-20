<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MN-02 — Beneficiary: tambah `category` dan `pic_id`.
 *
 * Catatan soal "hapus email": tabel `beneficiaries` TIDAK PERNAH punya kolom
 * email (lihat migrasi pembuatannya). Yang ada hanyalah satu input sisa di
 * form modal yang tidak pernah tersimpan ke mana pun — itu dibersihkan di
 * Blade, bukan lewat migrasi. Jadi tidak ada kolom yang dihapus di sini, dan
 * tidak ada data yang hilang.
 *
 * `category` default EXTERNAL: mayoritas beneficiary existing adalah vendor /
 * pihak luar, dan default ini membuat baris lama tetap valid tanpa backfill
 * yang menebak-nebak. Silakan dikoreksi per baris lewat form Edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->enum('category', ['INTERNAL', 'EXTERNAL', 'BILLING'])
                ->default('EXTERNAL')
                ->after('complete_name');

            // PIC penanggung jawab di Master PIC. nullOnDelete: menghapus PIC
            // tidak boleh ikut menghapus beneficiary yang dipakai Cost Request.
            $table->foreignId('pic_id')
                ->nullable()
                ->after('category')
                ->constrained('master_pics')
                ->nullOnDelete();

            // Dipakai untuk filter listing.
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropIndex(['category', 'status']);
            $table->dropConstrainedForeignId('pic_id');
            $table->dropColumn('category');
        });
    }
};
