<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Cost Request — keterangan settlement
|--------------------------------------------------------------------------
|
| Keterangan dari pengaju pada form Submit Settlement dulu hanya ikut ke Log
| CR. Sekarang disimpan juga di datanya (wajib bila total Settle sebuah item
| melebihi Request item itu).
|
| Aditif & nullable: CR lama tetap null.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_request')) {
            return;
        }

        Schema::table('budget_request', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_request', 'settlement_notes')) {
                $table->text('settlement_notes')->nullable()->after('settlement_signed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_request')) {
            return;
        }

        Schema::table('budget_request', function (Blueprint $table) {
            if (Schema::hasColumn('budget_request', 'settlement_notes')) {
                $table->dropColumn('settlement_notes');
            }
        });
    }
};
