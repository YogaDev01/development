<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Tambah kolom `changes` ke user_activity_logs.
|
| Menyimpan rincian perubahan nilai (dari -> menjadi) secara terstruktur,
| mis. [{"label":"Nilai Kontrak","from":"12.000.000","to":"13.000.000"}].
|
| Rincian ini bersifat CREDENTIAL: hanya ditampilkan ke pemilik log dan
| superadmin (gerbang di controller + Blade), terpisah dari `notes` yang
| merupakan catatan bebas non-sensitif. Additif murni (kolom nullable baru).
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return;
        }

        if (Schema::hasColumn('user_activity_logs', 'changes')) {
            return;
        }

        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->json('changes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_activity_logs', 'changes')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->dropColumn('changes');
            });
        }
    }
};
