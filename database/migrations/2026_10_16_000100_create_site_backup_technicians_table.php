<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Teknisi Backup site: daftar teknisi cadangan yang disiapkan untuk masuk ke
| pilihan Change Teknisi pada tiket site ini. Dipilih dari user teknisi milik
| customer yang sama dengan sitenya, boleh lebih dari satu.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_backup_technicians')) {
            return;
        }

        Schema::create('site_backup_technicians', function (Blueprint $table) {
            $table->id();
            // Ikut terhapus bersama site: daftar cadangan tidak punya arti di
            // luar sitenya dan bukan data finansial.
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_backup_technicians');
    }
};
