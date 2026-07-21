<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plan_logs', function (Blueprint $table) {
            // Cek dan tambah kolom yang belum ada
            if (!Schema::hasColumn('budget_plan_logs', 'status_from')) {
                $table->string('status_from', 50)->nullable()->after('budget_plan_id');
            }
            if (!Schema::hasColumn('budget_plan_logs', 'status_to')) {
                $table->string('status_to', 50)->nullable()->after('status_from');
            }
            if (!Schema::hasColumn('budget_plan_logs', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('budget_plan_logs', 'role')) {
                $table->string('role', 50)->nullable()->after('user_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_plan_logs', function (Blueprint $table) {
            $table->dropColumn(['status_from', 'status_to', 'user_name', 'role']);
        });
    }
};