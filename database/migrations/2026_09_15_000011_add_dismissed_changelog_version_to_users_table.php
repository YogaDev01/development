<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Menyimpan versi changelog terakhir yang SUDAH ditutup user lewat modal
| "What's New" (checkbox "jangan tampilkan lagi") — bukan boolean seen/
| unseen biasa, supaya begitu ada versi BARU dirilis, modal otomatis
| tampil lagi meski user pernah menutup versi sebelumnya.
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dismissed_changelog_version')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dismissed_changelog_version');
        });
    }
};
