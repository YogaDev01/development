<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Aplikasi default per-user: menu/sidebar mana yang aktif saat login.
| Aditif & nullable - null berarti ikut config('apps.default') (finance).
| Lihat App\Models\User::defaultApp() dan route '/' di routes/web.php.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'default_app')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('default_app', 30)->nullable()->after('notification_prefs');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'default_app')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_app');
        });
    }
};
