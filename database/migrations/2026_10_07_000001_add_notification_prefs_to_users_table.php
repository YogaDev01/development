<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Preferensi notifikasi per-user. Aditif & nullable: selama null, perilaku
| notifikasi sama persis seperti sebelumnya (semua kategori & WhatsApp aktif).
| Lihat App\Models\User::wantsNotification() dan App\Notifications\AppNotification::via().
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'notification_prefs')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable()->after('dismissed_changelog_version');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'notification_prefs')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_prefs');
        });
    }
};
