<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_funds', function (Blueprint $table) {
            $table->boolean('is_main_account')->default(false)->after('status');
        });

        // Backfill: kalau sudah ada Source Fund bernama persis "MAIN ACCOUNT",
        // otomatis jadikan itu sebagai Main Account (hanya 1 baris, yang paling lama).
        $mainId = DB::table('source_funds')
            ->whereRaw("UPPER(TRIM(name)) = 'MAIN ACCOUNT'")
            ->orderBy('id')
            ->value('id');

        if ($mainId) {
            DB::table('source_funds')->where('id', $mainId)->update(['is_main_account' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('source_funds', function (Blueprint $table) {
            $table->dropColumn('is_main_account');
        });
    }
};
