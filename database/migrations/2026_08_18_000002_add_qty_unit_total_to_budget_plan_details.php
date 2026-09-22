<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plan_details', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_plan_details', 'qty')) {
                $table->decimal('qty', 12, 2)->default(1)->after('account_id');
            }
            if (!Schema::hasColumn('budget_plan_details', 'unit')) {
                $table->enum('unit', [
                    'kg', 'liter', 'meter', 'meter persegi', 'meter kubik',
                    'roll', 'pcs', 'unit', 'lumpsum', 'package',
                    'lot', 'ton', 'sax', 'set', 'site', 'lembar',
                ])->nullable()->after('qty');
            }
            if (!Schema::hasColumn('budget_plan_details', 'total')) {
                $table->unsignedBigInteger('total')->default(0)->after('unit');
            }
        });

        // Backfill data lama: qty = 1, total = amount (amount lama = harga total per baris,
        // jadi diperlakukan sebagai harga satuan dengan qty 1 supaya total tetap sama).
        DB::statement('UPDATE budget_plan_details SET qty = 1 WHERE qty IS NULL OR qty = 0');
        DB::statement('UPDATE budget_plan_details SET total = amount WHERE total = 0');
    }

    public function down(): void
    {
        Schema::table('budget_plan_details', function (Blueprint $table) {
            $table->dropColumn(['qty', 'unit', 'total']);
        });
    }
};