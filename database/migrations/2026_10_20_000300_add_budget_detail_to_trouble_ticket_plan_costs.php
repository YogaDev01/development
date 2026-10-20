<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Plan cost — sub-item Budget Plan
|--------------------------------------------------------------------------
|
| Keputusan 20 Okt 2026 (alur TT x Finance). Rincian Cost Request digantung
| ke SUB-ITEM budget plan (`request_details.budget_detail_id`), bukan ke COA
| langsung. Agar draft CR bisa dibentuk dari estimasi biaya plan tanpa
| menebak, tiap baris biaya menyimpan sub-item asalnya; COA-nya ikut dari
| sub-item itu.
|
| Aditif & nullable: baris lama (bila ada) tetap terbaca lewat account_id.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_ticket_plan_costs')
            || Schema::hasColumn('trouble_ticket_plan_costs', 'budget_detail_id')) {
            return;
        }

        Schema::table('trouble_ticket_plan_costs', function (Blueprint $table) {
            $table->foreignId('budget_detail_id')
                ->nullable()
                ->after('trouble_ticket_id')
                ->constrained('budget_plan_details')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_ticket_plan_costs')
            || ! Schema::hasColumn('trouble_ticket_plan_costs', 'budget_detail_id')) {
            return;
        }

        Schema::table('trouble_ticket_plan_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_detail_id');
        });
    }
};
