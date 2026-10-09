<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Master wilayah berjenjang: pulau -> provinsi -> kota -> kecamatan -> desa.
| Dipakai alamat Site dan Region. Hapus induk dibatasi (RESTRICT) supaya
| wilayah yang masih punya turunan / dipakai Site tidak hilang diam-diam.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulau')) {
            Schema::create('pulau', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->timestamps();
            });
        }

        $levels = [
            'provinsi'  => 'pulau',
            'kota'      => 'provinsi',
            'kecamatan' => 'kota',
            'desa'      => 'kecamatan',
        ];

        foreach ($levels as $tableName => $parent) {
            if (Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table) use ($parent) {
                $table->id();
                $table->foreignId($parent . '_id')->constrained($parent)->restrictOnDelete();
                $table->string('name', 150);
                $table->timestamps();

                $table->unique([$parent . '_id', 'name']);
                $table->index('name');
            });
        }
    }

    public function down(): void
    {
        foreach (['desa', 'kecamatan', 'kota', 'provinsi', 'pulau'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
