<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 Security & Access Control — batas data per role × modul × dimensi
 * (registry config/data_scopes.php). Additive; tidak menyentuh tabel lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_data_scopes')) {
            return;
        }

        Schema::create('role_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('dimension', 40);
            $table->string('scope', 30);
            $table->timestamps();

            $table->unique(['role_id', 'module', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_data_scopes');
    }
};
