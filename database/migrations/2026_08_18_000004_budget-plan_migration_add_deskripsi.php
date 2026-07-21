<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plan_details', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_plan_details', 'deskripsi')) {
                $table->string('deskripsi')->nullable()->after('account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_plan_details', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });
    }
};