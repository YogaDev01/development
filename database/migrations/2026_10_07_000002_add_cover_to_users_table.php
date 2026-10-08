<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Foto sampul profil (cover). Aditif & nullable, disimpan di disk 'secure'
| folder 'covers' - sama polanya dengan 'avatar'. Lihat
| ProfileController::uploadCover() dan FileAccess ('covers').
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'cover')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('cover')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'cover')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cover');
        });
    }
};
