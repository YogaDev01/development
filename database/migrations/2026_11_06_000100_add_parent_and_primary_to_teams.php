<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Team — induk (struktur organisasi) & team primary anggota.
|
| Additive & nullable/default — tidak ada baris yang diubah.
|
| - teams.parent_team_id : HANYA untuk tampilan struktur organisasi
|   (blueprint docs/plans/2026-11-06-team-akses-organisasi.md, K3). Relasi
|   induk TIDAK PERNAH memberi atau menurunkan akses data. FK RESTRICT supaya
|   team induk tidak bisa terhapus diam-diam.
| - team_members.is_primary : team utama user (maksimal satu membership
|   ACTIVE primary per user, divalidasi di TeamController). Default false —
|   penetapan primary dilakukan manusia (K8), tidak ditebak migrasi.
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teams') && ! Schema::hasColumn('teams', 'parent_team_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_team_id')->nullable()->after('department_id');
                $table->foreign('parent_team_id')->references('id')->on('teams')->restrictOnDelete();
                $table->index('parent_team_id');
            });
        }

        if (Schema::hasTable('team_members') && ! Schema::hasColumn('team_members', 'is_primary')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('level');
                $table->index(['user_id', 'status'], 'team_members_user_status_index');
                $table->index(['team_id', 'status'], 'team_members_team_status_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('team_members') && Schema::hasColumn('team_members', 'is_primary')) {
            Schema::table('team_members', function (Blueprint $table) {
                // FK team_id/user_id punya index tunggal sendiri (dibuat
                // foreignId()), jadi index komposit ini aman dibuang.
                $table->dropIndex('team_members_user_status_index');
                $table->dropIndex('team_members_team_status_index');
                $table->dropColumn('is_primary');
            });
        }

        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'parent_team_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropForeign(['parent_team_id']);
                $table->dropIndex(['parent_team_id']);
                $table->dropColumn('parent_team_id');
            });
        }
    }
};
