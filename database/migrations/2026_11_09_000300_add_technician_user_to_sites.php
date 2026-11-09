<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
| Teknisi site langsung ke akun user (keputusan 9 Nov 2026): sebelumnya field
| Teknisi disimpan sebagai baris site_pics → master_pics, sehingga teknisi
| tanpa Master PIC tidak bisa dipasang. Kolom baru ini menjadi sumber utama;
| site_pics TIDAK dihapus dan tetap dibaca sebagai teknisi lama.
|
| Backfill: site yang teknisi lamanya (PIC internal berjabatan "teknisi")
| sudah terhubung ke akun user diisi dengan user tersebut. Site yang Master
| PIC teknisinya belum terhubung tetap kosong; jumlahnya dicatat di log.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        if (! Schema::hasColumn('sites', 'technician_user_id')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->foreignId('technician_user_id')->nullable()->after('customer_id')
                    ->constrained('users')->restrictOnDelete();
            });
        }

        // Backfill idempoten: hanya mengisi site yang kolomnya masih kosong.
        if (! Schema::hasTable('site_pics') || ! Schema::hasTable('master_pics')) {
            return;
        }

        // Teknisi lama per site: baris site_pics pertama yang PIC-nya internal
        // berjabatan teknisi (sama dengan Site::technicianPic()).
        $rows = DB::table('site_pics')
            ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
            ->where('master_pics.type', 'internal')
            ->whereRaw('LOWER(master_pics.position) LIKE ?', ['%teknisi%'])
            ->orderBy('site_pics.id')
            ->get(['site_pics.site_id', 'master_pics.user_id'])
            ->unique('site_id');

        $diisi = 0;
        $tanpaUser = [];
        foreach ($rows as $row) {
            if ($row->user_id === null) {
                $tanpaUser[] = (int) $row->site_id;

                continue;
            }
            $diisi += DB::table('sites')
                ->where('id', $row->site_id)
                ->whereNull('technician_user_id')
                ->update(['technician_user_id' => $row->user_id]);
        }

        Log::info('Migrasi sites.technician_user_id: '.$diisi.' site diisi dari teknisi lama; '
            .count($tanpaUser).' site teknisinya belum terhubung ke akun user (tetap kosong).',
            ['site_ids_tanpa_user' => $tanpaUser]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sites') || ! Schema::hasColumn('sites', 'technician_user_id')) {
            return;
        }

        // Data asal (site_pics) tidak pernah diubah migrasi ini, jadi aman dilepas.
        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_user_id');
        });
    }
};
