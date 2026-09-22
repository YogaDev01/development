<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Takeout / reject per sub-item Cost Request.
 *
 * Setiap baris request_detail punya status:
 *   - INCLUDED (default) : ikut diproses, dihitung ke nilai pengajuan CR
 *   - TAKEOUT            : dikeluarkan salah satu approval (Head/Finance/CEO),
 *                          tetap tampil di detail (dicoret) tapi tidak dibawa proses
 *
 * Kolom takeout_* menyimpan jejak audit siapa/di tahap apa item dikeluarkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_detail', function (Blueprint $table) {
            $table->string('status', 20)->default('INCLUDED')->after('account_id');
            $table->unsignedBigInteger('takeout_by')->nullable()->after('status');
            $table->string('takeout_by_name')->nullable()->after('takeout_by');
            $table->string('takeout_role')->nullable()->after('takeout_by_name');
            // Status CR saat item dikeluarkan (SUBMITTED/CHECKED/VERIFIED) — menandai
            // "approval mana" yang men-takeout item ini.
            $table->string('takeout_stage', 20)->nullable()->after('takeout_role');
            $table->text('takeout_notes')->nullable()->after('takeout_stage');
            $table->timestamp('takeout_at')->nullable()->after('takeout_notes');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('request_detail', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status', 'takeout_by', 'takeout_by_name',
                'takeout_role', 'takeout_stage', 'takeout_notes', 'takeout_at',
            ]);
        });
    }
};
