<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trouble Ticket — approval plan & budget oleh supervisor
|--------------------------------------------------------------------------
|
| Keputusan 20 Okt 2026 (alur TT x Finance). Sesudah Create Plan + Biaya,
| tiket TETAP di tahap Plan menunggu supervisor menyetujui tanggal & biaya.
| Jika approve, sistem membuat Cost Request berstatus DRAFT dari plan itu.
|
|   plan_budget_status : PENDING | APPROVED | REJECTED (null = plan lama
|                        tanpa biaya, berperilaku seperti sebelumnya)
|   plan_budget_notes  : catatan supervisor; wajib saat reject
|   budget_request_id  : CR draft yang terbentuk. Sengaja TANPA foreign key:
|                        budget_request memakai SoftDeletes dan FK ke sana
|                        tidak boleh cascade (CLAUDE.md §5).
|
| Keputusan approve dicatat di kolom plan_approved_at/plan_approved_by yang
| sudah ada (migrasi 2026_10_10_000200) — artinya persis sama.
|
| Aditif & nullable: tiket lama tidak terpengaruh.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_tickets', 'plan_budget_status')) {
                $table->string('plan_budget_status', 12)->nullable()->after('plan_cost_total');
                $table->index('plan_budget_status');
            }

            if (! Schema::hasColumn('trouble_tickets', 'plan_budget_notes')) {
                $table->text('plan_budget_notes')->nullable()->after('plan_budget_status');
            }

            if (! Schema::hasColumn('trouble_tickets', 'budget_request_id')) {
                $table->unsignedBigInteger('budget_request_id')->nullable()->after('plan_budget_notes');
                $table->index('budget_request_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('trouble_tickets', 'plan_budget_status')) {
                $table->dropIndex(['plan_budget_status']);
                $table->dropColumn('plan_budget_status');
            }

            if (Schema::hasColumn('trouble_tickets', 'plan_budget_notes')) {
                $table->dropColumn('plan_budget_notes');
            }

            if (Schema::hasColumn('trouble_tickets', 'budget_request_id')) {
                $table->dropIndex(['budget_request_id']);
                $table->dropColumn('budget_request_id');
            }
        });
    }
};
