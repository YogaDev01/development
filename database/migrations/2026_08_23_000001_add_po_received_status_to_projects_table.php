<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status monitoring "PO Received" — mengikuti pola monitoring_bast_status
     * dkk, otomatis mengikuti status milestone (project_details) yang
     * namanya mengandung "PO Received".
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('monitoring_po_received_status', ['NOT_YET', 'ON_GOING', 'DONE'])
                ->default('NOT_YET')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('monitoring_po_received_status');
        });
    }
};
