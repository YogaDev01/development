<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Rentang aging per severity (13 Okt 2026)
|--------------------------------------------------------------------------
| Aditif, nullable, TANPA backfill.
|
| Sebelum ini tangga severity ditetapkan di kode (TroubleTicket::SEVERITY_AGING:
| 0 hari Rendah, 1-2 Minor, 3-7 Major, >=8 Kritis). Dua kolom ini memindahkan
| tangga itu ke data supaya bisa diatur di halaman Pengaturan.
|
| - `aging_from` : batas bawah umur tiket (hari), inklusif.
| - `aging_to`   : batas atas (hari), inklusif. Kosong = tanpa batas atas.
|
| Selama BELUM ADA satu pun severity aktif yang mengisi `aging_from`,
| perhitungan tetap memakai tangga bawaan di kode — lihat
| TroubleTicketSeverity::agingSteps(). Jadi migrasi ini sendiri tidak
| mengubah severity tiket mana pun.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_ticket_severities')) {
            return;
        }

        Schema::table('trouble_ticket_severities', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_ticket_severities', 'aging_from')) {
                $table->unsignedSmallInteger('aging_from')->nullable()->after('level');
            }
            if (! Schema::hasColumn('trouble_ticket_severities', 'aging_to')) {
                $table->unsignedSmallInteger('aging_to')->nullable()->after('aging_from');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_ticket_severities')) {
            return;
        }

        Schema::table('trouble_ticket_severities', function (Blueprint $table) {
            foreach (['aging_from', 'aging_to'] as $kolom) {
                if (Schema::hasColumn('trouble_ticket_severities', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
