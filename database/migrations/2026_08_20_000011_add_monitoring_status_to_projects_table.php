<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status khusus Project Monitoring — sengaja terpisah dari kolom `status`
     * (workflow utama PO) agar tidak bentrok dengan perubahan status otomatis
     * dari alur Invoicing/Cash Receipt. Nilainya otomatis mengikuti status
     * milestone (project_details) bernama BAST/Invoicing/Payment — bukan
     * di-set manual oleh user.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('monitoring_bast_status', ['NOT_YET', 'ON_GOING', 'DONE'])->default('NOT_YET')->after('status');
            $table->enum('monitoring_invoice_status', ['NOT_YET', 'ON_GOING', 'DONE'])->default('NOT_YET')->after('monitoring_bast_status');
            $table->enum('monitoring_payment_status', ['NOT_YET', 'ON_GOING', 'DONE'])->default('NOT_YET')->after('monitoring_invoice_status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['monitoring_bast_status', 'monitoring_invoice_status', 'monitoring_payment_status']);
        });
    }
};
