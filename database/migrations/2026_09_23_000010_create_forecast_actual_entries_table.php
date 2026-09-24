<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Realisasi forecast dicatat sebagai ENTRI BERULANG, bukan satu angka yang
 * ditimpa.
 *
 * Sebelumnya `forecast_details.actual_amount` diisi langsung lewat satu form:
 * mengisi ulang berarti menghapus angka sebelumnya tanpa jejak, dan tidak ada
 * tempat untuk menjelaskan realisasi itu berasal dari mana. Selama forecast
 * ACTIVE realisasi memang datang bertahap (satu rencana bisa dibayar beberapa
 * kali), jadi yang disimpan adalah setiap kejadiannya; `actual_amount` di
 * baris detail tinggal jumlah dari entri-entri ini.
 *
 * `notes` sengaja NOT NULL: keterangan adalah syarat penambahan realisasi,
 * bukan pelengkap.
 *
 * hasTable() dijaga karena tabel ini sempat ada lebih dulu di basis data
 * pengembangan (dari migrasi yang kemudian hilang dari repo). Melewatinya
 * lebih baik daripada menjatuhkan baris yang sudah tercatat di sana.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forecast_actual_entries')) {
            return;
        }

        Schema::create('forecast_actual_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_detail_id')->constrained('forecast_details')->cascadeOnDelete();
            // Selalu positif — arahnya (IN/OUT) sudah melekat di baris detail,
            // jadi menyimpan tandanya di sini hanya membuat dua sumber
            // kebenaran untuk satu hal yang sama.
            $table->unsignedBigInteger('amount');
            $table->string('notes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_actual_entries');
    }
};
