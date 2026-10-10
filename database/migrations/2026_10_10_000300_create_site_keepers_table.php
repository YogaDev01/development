<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Site Keeper: penjaga site yang datanya diisi bebas di form site (nama + no
| HP), boleh lebih dari satu per site. Bukan `master_pics` — penjaga tidak
| dipakai lintas site dan tidak perlu masuk master PIC.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_keepers')) {
            return;
        }

        Schema::create('site_keepers', function (Blueprint $table) {
            $table->id();
            // Ikut terhapus bersama site: penjaga site tidak punya arti di luar
            // site-nya dan bukan data finansial.
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_keepers');
    }
};
