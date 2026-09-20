<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur "Ajukan Done" (manager) -> "ACC" (head). Status milestone baru
     * benar-benar pindah ke DONE setelah di-ACC; sebelum itu statusnya tetap
     * ON_GOING dengan flag submitted_done_at terisi (menunggu approval).
     */
    public function up(): void
    {
        Schema::table('project_details', function (Blueprint $table) {
            $table->foreignId('submitted_done_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_done_at')->nullable()->after('submitted_done_by');
        });
    }

    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_done_by');
            $table->dropColumn('submitted_done_at');
        });
    }
};
