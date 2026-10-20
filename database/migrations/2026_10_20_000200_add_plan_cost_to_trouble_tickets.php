<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trouble Ticket — sumber dana & total estimasi biaya plan
|--------------------------------------------------------------------------
|
| Keputusan 20 Okt 2026, pasangan migrasi trouble_ticket_plan_costs.
| budget_plan_id = Budget Plan yang dipilih saat Create Plan (sumber dana),
| plan_cost_total = jumlah seluruh baris item (integer rupiah, cache tampilan
| yang ditulis ulang setiap plan disimpan).
|
| Aditif & nullable: tiket lama tidak terpengaruh. Tidak ada FK cascade ke
| budget_plans — master keuangan tidak boleh ikut terhapus (CLAUDE.md §5).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_tickets', 'budget_plan_id')) {
                $table->unsignedBigInteger('budget_plan_id')->nullable()->after('visit_site');
                $table->index('budget_plan_id');
            }

            if (! Schema::hasColumn('trouble_tickets', 'plan_cost_total')) {
                $table->unsignedBigInteger('plan_cost_total')->nullable()->after('budget_plan_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('trouble_tickets', 'budget_plan_id')) {
                $table->dropIndex(['budget_plan_id']);
                $table->dropColumn('budget_plan_id');
            }

            if (Schema::hasColumn('trouble_tickets', 'plan_cost_total')) {
                $table->dropColumn('plan_cost_total');
            }
        });
    }
};
