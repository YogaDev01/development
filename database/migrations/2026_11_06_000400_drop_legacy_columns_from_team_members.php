<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| DESTRUKTIF — drop team_members.ca_limit, join_at, left_at.
|
| Keputusan pemilik 2026-11-06 (blueprint K10): langsung drop, tanpa backup.
|   - ca_limit : tidak dipakai perhitungan apa pun; limit CA yang berlaku
|                dibaca dari Master PIC (token/nominal).
|   - join_at / left_at : akan digantikan Master Employee.
|
| Sebelum drop, jumlah baris yang terisi per kolom dicatat ke log aplikasi.
| down() hanya menambah kembali kolomnya dalam keadaan KOSONG — nilai lama
| TIDAK dapat dipulihkan.
|
| Urutan deploy WAJIB: upload kode baru (form Team tidak lagi menulis kolom
| ini) -> migrate -> optimize:clear (config + route + view). Bila migrate jalan lebih dulu, form Team
| versi lama gagal dengan "Unknown column".
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    private const COLUMNS = ['ca_limit', 'join_at', 'left_at'];

    public function up(): void
    {
        if (! Schema::hasTable('team_members')) {
            return;
        }

        $ada = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('team_members', $c)));
        if ($ada === []) {
            return;
        }

        $terisi = [];
        foreach ($ada as $kolom) {
            $terisi[$kolom] = DB::table('team_members')->whereNotNull($kolom)->count();
        }
        Log::info('Migrasi drop kolom team_members: jumlah baris terisi sebelum drop', $terisi);

        Schema::table('team_members', function (Blueprint $table) use ($ada) {
            $table->dropColumn($ada);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('team_members')) {
            return;
        }

        Schema::table('team_members', function (Blueprint $table) {
            if (! Schema::hasColumn('team_members', 'ca_limit')) {
                $table->unsignedBigInteger('ca_limit')->nullable();
            }
            if (! Schema::hasColumn('team_members', 'join_at')) {
                $table->date('join_at')->nullable();
            }
            if (! Schema::hasColumn('team_members', 'left_at')) {
                $table->date('left_at')->nullable();
            }
        });
    }
};
