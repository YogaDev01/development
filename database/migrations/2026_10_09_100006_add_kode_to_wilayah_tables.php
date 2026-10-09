<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Kode wilayah Kemendagri (mis. 32.73.01.1001) sebagai kunci seeder yang
| stabil. Desa: unik (kecamatan_id, name) dilepas karena data resmi memuat
| desa & kelurahan bernama sama dalam satu kecamatan (16 kasus, Kepmendagri
| 300.2.2-2138/2025); keunikan dijaga oleh kode.
*/
return new class extends Migration
{
    private array $tables = ['provinsi', 'kota', 'kecamatan', 'desa'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'kode')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('kode', 13)->nullable()->unique()->after('id');
                });
            }
        }

        // Index biasa dibuat dulu: FK kecamatan_id butuh index yang diawali kolom itu.
        Schema::table('desa', function (Blueprint $t) {
            $t->index(['kecamatan_id', 'name'], 'desa_kecamatan_id_name_index');
            $t->dropUnique('desa_kecamatan_id_name_unique');
        });
    }

    public function down(): void
    {
        // Gagal bila sudah ada nama desa kembar dalam satu kecamatan (data seeder).
        Schema::table('desa', function (Blueprint $t) {
            $t->unique(['kecamatan_id', 'name']);
            $t->dropIndex('desa_kecamatan_id_name_index');
        });

        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'kode')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropUnique($table . '_kode_unique');
                    $t->dropColumn('kode');
                });
            }
        }
    }
};
