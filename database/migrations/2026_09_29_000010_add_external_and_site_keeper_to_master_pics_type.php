<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Master PIC — dua jenis baru: External & Site Keeper.
|
| Kolom `type` dibuat sebagai ENUM di migrasi 2026_08_18_000001, jadi nilai
| baru harus didaftarkan di tingkat basis data dulu; tanpa ini MySQL menolak
| (atau, pada mode longgar, diam-diam menyimpan string kosong).
|
| Ditulis sebagai ALTER mentah, bukan lewat Blueprint: mengubah enum dengan
| Blueprint menuntut doctrine/dbal yang tidak dipasang di proyek ini.
|
| Turun (down) hanya mengembalikan daftar enum ke dua nilai lama. Baris yang
| sudah terlanjur memakai jenis baru dikembalikan ke 'internal' lebih dulu,
| supaya ALTER-nya tidak gagal atau mengosongkan nilainya.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE master_pics MODIFY type
             ENUM('customer','internal','external','site_keeper')
             NOT NULL DEFAULT 'internal'"
        );
    }

    public function down(): void
    {
        DB::table('master_pics')
            ->whereIn('type', ['external', 'site_keeper'])
            ->update(['type' => 'internal']);

        DB::statement(
            "ALTER TABLE master_pics MODIFY type
             ENUM('customer','internal')
             NOT NULL DEFAULT 'internal'"
        );
    }
};
