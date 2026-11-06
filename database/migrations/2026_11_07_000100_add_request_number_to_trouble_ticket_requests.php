<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor dokumen pengajuan dana (FR). Additive; pengajuan lama tetap NULL dan
 * ditampilkan sebagai #id. Nomor terisi juga menjadi penanda pengajuan yang
 * lahir sesudah rilis ini, yang settlement-nya wajib lewat halaman tiket.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trouble_ticket_requests') && ! Schema::hasColumn('trouble_ticket_requests', 'request_number')) {
            Schema::table('trouble_ticket_requests', function (Blueprint $table) {
                $table->string('request_number', 30)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trouble_ticket_requests', 'request_number')) {
            Schema::table('trouble_ticket_requests', function (Blueprint $table) {
                $table->dropUnique(['request_number']);
                $table->dropColumn('request_number');
            });
        }
    }
};
