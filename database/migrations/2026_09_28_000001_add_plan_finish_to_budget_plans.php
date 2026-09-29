<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget Plan mendapat tanggal rencana selesai (plan_finish). Saat tanggal itu
 * terlewati, budget yang masih ACTIVE dipindahkan otomatis ke CLOSING oleh
 * perintah budget-plan:auto-closing.
 *
 * closing_submitted_at memisahkan dua arti CLOSING yang sebelumnya menyatu:
 *   - NULL      -> jatuh tempo, manager belum mengajukan berkas closing
 *   - berisi    -> manager sudah mengajukan, giliran Finance memverifikasi
 * Tanpa pemisah ini, budget yang baru saja auto-closing akan langsung muncul
 * di worklist Finance padahal berkasnya belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->date('plan_finish')->nullable()->after('status');
            $table->timestamp('closing_submitted_at')->nullable()->after('plan_finish');
        });

        // Budget yang sudah berstatus CLOSING sebelum migrasi ini pasti berasal
        // dari pengajuan manual manager, jadi tandai sebagai sudah diajukan
        // supaya tidak jatuh ke worklist manager.
        \Illuminate\Support\Facades\DB::table('budget_plans')
            ->where('status', 'CLOSING')
            ->whereNull('closing_submitted_at')
            ->update(['closing_submitted_at' => \Illuminate\Support\Facades\DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_finish', 'closing_submitted_at']);
        });
    }
};
