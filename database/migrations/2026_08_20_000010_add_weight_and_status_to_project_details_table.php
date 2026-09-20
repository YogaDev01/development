<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahan untuk kebutuhan Project Monitoring: bobot milestone (total 100
     * per PO) dan status progress-nya, tanpa mengubah struktur plan/actual
     * yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('project_details', function (Blueprint $table) {
            $table->unsignedTinyInteger('weight')->default(0)->after('stage_id');
            $table->enum('status', ['NOT_STARTED', 'ON_GOING', 'DONE'])->default('NOT_STARTED')->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('project_details', function (Blueprint $table) {
            $table->dropColumn(['weight', 'status']);
        });
    }
};
