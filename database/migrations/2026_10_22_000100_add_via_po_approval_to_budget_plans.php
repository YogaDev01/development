<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Budget Plan — penanda "ikut approval PO"
|--------------------------------------------------------------------------
|
| Keputusan 22 Okt 2026. Budget Plan yang dibuat dari bagian Budget pada
| form PO (Head) tidak berjalan di workflow Budget Plan sendiri
| (Manager submit -> Head -> Finance -> CEO), melainkan ikut approval PO:
| PO submit -> BP CHECKED, PO verify -> BP VERIFIED, PO approve -> BP ACTIVE.
|
|   via_po_approval : 1 = status BP digerakkan oleh PO, 0 = workflow BP biasa
|
| Aditif, default 0: seluruh Budget Plan yang sudah ada tetap berperilaku
| persis seperti sebelumnya. Tidak ada backfill.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_plans')) {
            return;
        }

        Schema::table('budget_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_plans', 'via_po_approval')) {
                $table->boolean('via_po_approval')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_plans')) {
            return;
        }

        Schema::table('budget_plans', function (Blueprint $table) {
            if (Schema::hasColumn('budget_plans', 'via_po_approval')) {
                $table->dropColumn('via_po_approval');
            }
        });
    }
};
