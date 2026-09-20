<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PO ("Project") dapat kolom baru:
     *  - manager_id : Project Manager PO, kali ini disimpan sebagai FK ke tabel
     *    users (bukan lagi ditebak dari Master PIC lewat position "Project Manager").
     *  - contract   : Nilai Kontrak yang ditandatangani, terpisah dari `value`
     *    (Nilai PO dari customer).
     *  - overhead / fixed_cost : nominal manual, TERPISAH dari modul Cost Register
     *    (yang tetap dipakai lewat CostRegister type OVERHEAD/FIXED_COST).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('manager_id')
                ->nullable()
                ->after('input_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedBigInteger('contract')->nullable()->after('value');
            $table->unsignedBigInteger('overhead')->nullable()->default(0)->after('contract');
            $table->unsignedBigInteger('fixed_cost')->nullable()->default(0)->after('overhead');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['manager_id', 'contract', 'overhead', 'fixed_cost']);
        });
    }
};
