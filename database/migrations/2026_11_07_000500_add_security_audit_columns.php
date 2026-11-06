<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 Security & Access Control (docs/plans/2026-11-06-security-access-control.md).
 *
 * Additive & nullable, tanpa backfill:
 *  - user_activity_logs.user_agent   : perangkat/peramban saat aksi (dipotong 255)
 *  - user_activity_logs.session_hash : sha256 id sesi — menghubungkan jejak audit
 *                                      dengan sesi aktif TANPA menyimpan id sesi mentah
 *  - users.last_login_at / last_login_ip : login terakhir, untuk tinjauan akses
 *
 * Baris log lama tetap NULL. Kode membaca kolom ini hanya bila sudah ada,
 * jadi urutan upload kode -> migrate aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_activity_logs') && ! Schema::hasColumn('user_activity_logs', 'user_agent')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->string('user_agent', 255)->nullable()->after('ip_address');
                $table->char('session_hash', 64)->nullable()->after('user_agent');
                $table->index('session_hash');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable()->after('status');
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_activity_logs', 'user_agent')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->dropIndex(['session_hash']);
                $table->dropColumn(['user_agent', 'session_hash']);
            });
        }

        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['last_login_at', 'last_login_ip']);
            });
        }
    }
};
