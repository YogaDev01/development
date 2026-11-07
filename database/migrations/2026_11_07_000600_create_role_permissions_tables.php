<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 Security & Access Control — tabel mesin permission (additive).
 *
 *  - role_permissions        : role × permission_key (registry di config/permissions.php)
 *  - permission_shadow_logs  : selisih jawaban mesin baru vs gerbang role: lama
 *                              selama mode shadow. Sementara — dikosongkan/di-drop
 *                              manual setelah seluruh modul cutover.
 *
 * Tidak menyentuh tabel lama; tidak ada FK ke tabel keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                // Baris kewenangan ikut hilang bila role-nya dihapus (bukan data keuangan).
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->string('permission_key', 150);
                $table->timestamp('created_at')->nullable();
                // Hak dicabut administrator: baris DIPERTAHANKAN dengan tanda ini,
                // supaya sinkron seed tidak pernah menghidupkannya kembali.
                $table->timestamp('revoked_at')->nullable();

                $table->unique(['role_id', 'permission_key']);
                $table->index('permission_key');
            });
        }

        if (! Schema::hasTable('permission_shadow_logs')) {
            Schema::create('permission_shadow_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role_name', 100)->nullable();
                $table->string('permission_key', 150);
                $table->string('method', 10);
                $table->string('path', 255);
                $table->boolean('legacy_allowed');
                $table->boolean('engine_allowed');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['permission_key', 'created_at']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_shadow_logs');
        Schema::dropIfExists('role_permissions');
    }
};
