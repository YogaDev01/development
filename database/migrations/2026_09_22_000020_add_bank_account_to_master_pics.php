<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Master PIC — rekening milik PIC itu sendiri.
|
| Sampai sekarang rekening hanya melekat pada Beneficiary. Akibatnya detail
| seorang PIC tidak bisa menampilkan rekeningnya sendiri: yang terlihat hanya
| rekening orang-orang yang ia tangani, padahal PIC juga bisa menjadi pihak
| yang dibayar langsung.
|
| bank_id memakai FK ke `banks` dan bukan teks bebas, supaya namanya tidak
| lahir dalam belasan ejaan berbeda ("BCA", "Bca", "B.C.A") yang tidak bisa
| dikelompokkan saat dilaporkan. nullOnDelete: menghapus satu bank tidak boleh
| ikut menghapus baris PIC-nya.
|
| Keduanya NULLABLE — 55 PIC yang sudah ada tidak punya nilai ini, dan
| memaksanya terisi berarti migrasi harus mengarang nomor rekening.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->foreignId('bank_id')
                ->nullable()
                ->after('position')
                ->constrained('banks')
                ->nullOnDelete();

            $table->string('account_number')->nullable()->after('bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->dropColumn('account_number');
            $table->dropConstrainedForeignId('bank_id');
        });
    }
};
