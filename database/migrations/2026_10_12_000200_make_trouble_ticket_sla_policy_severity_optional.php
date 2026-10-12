<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Kebijakan SLA Trouble Ticket: `severity` menjadi opsional.
|
| Sejak 2026_10_12_000100, severity tiket dihitung dari aging dan tidak lagi
| jadi masukan form, sehingga kebijakan SLA tiket baru dicari per Problem
| (TroubleTicketSlaPolicy::applicableForProblem()). Severity kosong = berlaku
| untuk semua severity. Baris lama tidak diubah.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
            $table->string('severity', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Hanya bisa dikembalikan NOT NULL bila tidak ada baris yang kosong.
        if (! DB::table('trouble_ticket_sla_policies')->whereNull('severity')->exists()) {
            Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
                $table->string('severity', 20)->nullable(false)->change();
            });
        }
    }
};
