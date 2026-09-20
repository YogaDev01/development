<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log/chat Project Monitoring sekarang bisa terhubung ke Budget Plan
     * (bukan cuma milestone/project_detail), untuk media follow-up Cost Request.
     */
    public function up(): void
    {
        Schema::table('project_monitoring_logs', function (Blueprint $table) {
            $table->foreignId('budget_plan_id')->nullable()->after('project_detail_id')
                ->constrained('budget_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_monitoring_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_plan_id');
        });
    }
};
