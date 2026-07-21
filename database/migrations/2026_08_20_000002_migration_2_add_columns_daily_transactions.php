<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_transactions', 'description')) {
                $table->string('description')->nullable()->after('date');
            }
            if (!Schema::hasColumn('daily_transactions', 'source_fund_id')) {
                $table->foreignId('source_fund_id')->nullable()->after('description')
                    ->constrained('source_funds')->nullOnDelete();
            }
            if (!Schema::hasColumn('daily_transactions', 'charge')) {
                $table->unsignedBigInteger('charge')->default(0)->after('source_fund_id');
            }
            if (!Schema::hasColumn('daily_transactions', 'captured_amount')) {
                // Snapshot / cache nilai total CR dalam batch — disinkron ulang tiap
                // item ditambah/di-takeout, supaya tidak perlu hitung ulang tiap load.
                $table->unsignedBigInteger('captured_amount')->default(0)->after('charge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_transactions', function (Blueprint $table) {
            $table->dropForeign(['source_fund_id']);
            $table->dropColumn(['description', 'source_fund_id', 'charge', 'captured_amount']);
        });
    }
};