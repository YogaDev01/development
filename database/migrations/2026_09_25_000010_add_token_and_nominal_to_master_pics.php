<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Master PIC — token & nominal.
|
| Dua keterangan tambahan yang dipegang per PIC dan diisi langsung dari
| Daftar PIC.
|
| `token` disimpan sebagai string, bukan angka: isinya penanda/kode yang bisa
| memuat huruf, tanda hubung, atau nol di depan — menyimpannya sebagai bilangan
| akan memotong nol itu.
|
| `nominal` decimal(18,2) mengikuti kolom uang lain di basis data ini, supaya
| pembulatan float tidak muncul saat dijumlahkan. Keduanya NULLABLE — baris PIC
| yang sudah ada belum punya nilainya, dan memaksanya terisi berarti migrasi
| harus mengarang angka.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->string('token', 100)->nullable()->after('email');
            $table->decimal('nominal', 18, 2)->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->dropColumn(['token', 'nominal']);
        });
    }
};
