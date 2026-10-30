<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Settlement Custom COA (25 Okt 2026): satu baris settlement boleh memilih
| Sub COA (sub-item Budget Plan) sendiri — dipecah dari item pengajuan, pindah
| ke Sub COA lain, atau baris tambahan tanpa item asal.
|
| Kolom baru nullable dan TANPA backfill: baris lama (NULL) tetap memakai
| sub-item item asalnya (request_detail.budget_detail_id) — lihat
| BudgetRequestRealization::effectiveBudgetDetailId().
|
| FK RESTRICT (keputusan pengguna): sub-item yang sudah dipakai settlement
| tidak boleh terhapus diam-diam, sama seperti FK ke master pada catatan
| keuangan lain (lihat migration 2026_09_26_000002).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_request_realizations') || ! Schema::hasTable('budget_plan_details')) {
            return;
        }

        Schema::table('budget_request_realizations', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_request_realizations', 'budget_detail_id')) {
                $table->foreignId('budget_detail_id')
                    ->nullable()
                    ->after('request_detail_id')
                    ->constrained('budget_plan_details')
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_request_realizations')) {
            return;
        }

        Schema::table('budget_request_realizations', function (Blueprint $table) {
            if (Schema::hasColumn('budget_request_realizations', 'budget_detail_id')) {
                $table->dropForeign(['budget_detail_id']);
                $table->dropColumn('budget_detail_id');
            }
        });
    }
};
