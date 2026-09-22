<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| PO Quantity — jumlah No. PO yang diharapkan untuk PO ini (manual, diisi
| Finance). Dibandingkan dengan jumlah baris project_po_numbers yang benar-
| benar sudah dientri; kalau tidak match, tampilkan alert "PO Adjustment
| Required" (lihat Project::poAdjustmentRequired()).
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('po_quantity')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('po_quantity');
        });
    }
};
