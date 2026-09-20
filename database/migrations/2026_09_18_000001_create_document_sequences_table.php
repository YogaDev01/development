<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor urut dokumen yang tahan balapan.
 *
 * Semua penomoran (Invoice, Cost Request, Daily Transaction, Surat Jalan)
 * sebelumnya dibuat dengan menghitung baris yang sudah ada lalu menambah satu.
 * Dua pengguna yang menyimpan pada detik yang sama sama-sama membaca hitungan
 * yang sama, sehingga keduanya memakai nomor yang sama — satu berhasil, satu
 * gagal dengan galat duplikat (kolom code unik), dan pengguna melihat error
 * 500 tanpa penjelasan. Penghapusan baris juga membuat hitungan mundur,
 * sehingga nomor bisa terpakai ulang.
 *
 * Tabel ini menyimpan penghitung per rangkaian (mis. "invoice:2026"). Nilainya
 * dinaikkan di dalam transaksi dengan baris terkunci, jadi dua permintaan
 * bersamaan dijamin mendapat dua nomor berbeda, dan nomor tidak pernah mundur
 * walau datanya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();

            // mis. "invoice:2026", "invoice:2026:customer:12", "cr:2609:0001:0003"
            $table->string('key')->unique();

            $table->unsignedBigInteger('counter')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
