<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_request_realizations', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_request_realizations', 'request_detail_id')) {
                $table->foreignId('request_detail_id')
                    ->nullable()
                    ->after('budget_request_id')
                    ->constrained('request_detail')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_request_realizations', function (Blueprint $table) {
            $table->dropForeign(['request_detail_id']);
            $table->dropColumn('request_detail_id');
        });
    }
};