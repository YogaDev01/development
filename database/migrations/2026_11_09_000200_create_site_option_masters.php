<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
| Master Tipe Site, Source Power & Program Site.
|
| Sebelumnya pilihan ketiganya tertanam di konstanta Site::TYPES /
| SOURCE_POWERS / PROGRAMS. Sekarang dipindah ke tabel master supaya bisa
| ditambah dari menu Master Data.
|
| Kolom sites.type / source_power / program TETAP menyimpan `code` (bukan
| FK id), jadi data site lama tidak perlu diubah. Isi awal master = isi
| konstanta lama dengan kode yang sama persis.
|
| sites.type & sites.source_power dijadikan nullable (tidak lagi wajib di
| form); sites.program tetap wajib. Perubahan NOT NULL -> NULL tidak
| mengubah data yang ada.
|
| Menu (menu_items) didaftarkan tanpa memberi akses ke role mana pun, sama
| seperti 2026_10_09_100005: superadmin sudah lolos menu:, role lain diberi
| lewat layar Role / Menu Access.
|
| Aman diulang.
*/
return new class extends Migration
{
    private const MASTERS = [
        'site_types' => [
            'SEKOLAH' => 'Sekolah',
            'KANTOR' => 'Kantor',
            'KEAMANAN' => 'Keamanan',
            'LAYANAN_KESEHATAN' => 'Layanan Kesehatan',
            'TEMPAT_IBADAH' => 'Tempat Ibadah',
            'TOWER' => 'Tower',
        ],
        'site_source_powers' => [
            'PLN' => 'PLN',
            'SPS' => 'SPS',
            'HYBRID' => 'Hybrid',
        ],
        'site_programs' => [
            'AKSES_INTERNET' => 'Akses Internet',
            'TOWER_POWER' => 'Tower & Power',
        ],
    ];

    private const MENUS = [
        ['site-type',         'Tipe Site',    'fas fa-building',     '/site-type',         68],
        ['site-source-power', 'Source Power', 'fas fa-bolt',         '/site-source-power', 69],
        ['site-program',      'Program Site', 'fas fa-clipboard-list', '/site-program',    70],
    ];

    public function up(): void
    {
        foreach (self::MASTERS as $table => $rows) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->id();
                    // Nilai yang disimpan di kolom sites (type/source_power/program).
                    $t->string('code', 30)->unique();
                    $t->string('name', 100)->unique();
                    $t->timestamps();
                });
            }

            foreach ($rows as $code => $name) {
                if (! DB::table($table)->where('code', $code)->exists()) {
                    DB::table($table)->insert([
                        'code' => $code,
                        'name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $t) {
                $t->string('type', 30)->nullable()->change();
                $t->string('source_power', 30)->nullable()->change();
            });
        }

        if (Schema::hasTable('menu_items')) {
            foreach (self::MENUS as [$key, $label, $icon, $route, $sort]) {
                if (DB::table('menu_items')->where('key', $key)->exists()) {
                    continue;
                }

                DB::table('menu_items')->insert([
                    'key' => $key,
                    'label' => $label,
                    'icon' => $icon,
                    'route' => $route,
                    'section' => 'master',
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            $ids = DB::table('menu_items')
                ->whereIn('key', array_column(self::MENUS, 0))
                ->pluck('id');

            DB::table('role_menu_items')->whereIn('menu_item_id', $ids)->delete();
            DB::table('menu_items')->whereIn('id', $ids)->delete();
        }

        // Kembali ke NOT NULL hanya bila tidak ada site berisi kosong; selain
        // itu kolom dibiarkan nullable agar rollback tidak gagal/menghapus data.
        if (Schema::hasTable('sites')) {
            foreach (['type', 'source_power'] as $col) {
                $kosong = DB::table('sites')->whereNull($col)->count();
                if ($kosong > 0) {
                    Log::warning("Rollback site option masters: sites.{$col} tetap nullable ({$kosong} baris kosong).");

                    continue;
                }

                Schema::table('sites', fn (Blueprint $t) => $t->string($col, 30)->nullable(false)->change());
            }
        }

        foreach (array_keys(self::MASTERS) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
