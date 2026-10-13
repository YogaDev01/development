<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Nama kebijakan SLA + kolom target SLM (13 Okt 2026)
|--------------------------------------------------------------------------
| Aditif, seluruhnya nullable, TANPA backfill:
|
| - `name`  : nama kebijakan. Halaman Pengaturan kini mendaftar kebijakan
|             SLA berdasarkan namanya. Baris lama dibiarkan kosong dan
|             layar menampilkan label turunannya (severity/problem).
|
| - `slm_target_value` / `slm_target_unit` : target SLM, boleh dinyatakan
|             dalam jam atau hari. Disiapkan lebih dulu atas permintaan
|             pengguna; SENGAJA belum dipakai dan belum ditampilkan di mana
|             pun, dan semua barisnya kosong. Tidak ada kode yang membacanya
|             sampai aturannya ditetapkan.
|
| Tidak ada data yang diubah atau dihapus.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_ticket_sla_policies')) {
            return;
        }

        Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_ticket_sla_policies', 'name')) {
                $table->string('name', 60)->nullable()->after('id');
            }
            if (! Schema::hasColumn('trouble_ticket_sla_policies', 'slm_target_value')) {
                $table->unsignedInteger('slm_target_value')->nullable()->after('resolution_minutes');
            }
            if (! Schema::hasColumn('trouble_ticket_sla_policies', 'slm_target_unit')) {
                // 'HOUR' atau 'DAY'. Dibiarkan varchar seperti kolom status lain.
                $table->string('slm_target_unit', 10)->nullable()->after('slm_target_value');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_ticket_sla_policies')) {
            return;
        }

        Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
            foreach (['name', 'slm_target_value', 'slm_target_unit'] as $kolom) {
                if (Schema::hasColumn('trouble_ticket_sla_policies', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
