<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('request_detail', 'budget_detail_id')) {
                $table->foreignId('budget_detail_id')
                    ->nullable()
                    ->after('budget_request_id')
                    ->constrained('budget_plan_details')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_detail', function (Blueprint $table) {
            $table->dropForeign(['budget_detail_id']);
            $table->dropColumn('budget_detail_id');
        });
    }
};