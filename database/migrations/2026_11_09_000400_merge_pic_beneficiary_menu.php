<?php

use App\Support\Authorizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Gabungkan menu 'beneficiaries' & 'master-pic' menjadi satu menu
 * 'pic-beneficiary' (halaman "PIC & Beneficiary", 2026-11-09).
 *
 * Langkah (aman diulang):
 *   1. Baris menu_items 'pic-beneficiary' dipastikan ada.
 *   2. Setiap role yang punya SALAH SATU menu lama diberi menu baru —
 *      tidak ada role yang kehilangan akses MELIHAT halaman.
 *   3. Baris menu_items lama beserta role_menu_items-nya dihapus. Route
 *      sudah tidak memeriksa kunci lama; membiarkannya membuat kunci itu
 *      muncul di bagian "Lainnya" modal Menu Access dan centangnya tidak
 *      berpengaruh apa pun. Setiap baris yang dihapus dicetak & dicatat log.
 *   4. role_permissions disinkronkan (tambah saja) untuk permission baru
 *      master-pic.edit-data/update/destroy (lapis role:superadmin).
 *
 * CATATAN PERUBAHAN KEWENANGAN: mengubah/menghapus PIC kini hanya
 * superadmin (AccessRoles::PIC_MANAGE). Role lain yang dulu punya menu
 * 'master-pic' tetap bisa melihat & menambah PIC, tapi tidak lagi
 * mengubah/menghapus. Role yang terdampak dicetak di langkah 2.
 *
 * Superadmin tidak dicatat: Role::hasMenu() & CheckMenu sudah meloloskannya.
 */
return new class extends Migration
{
    private const BARU = 'pic-beneficiary';

    private const LAMA = ['beneficiaries', 'master-pic'];

    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasTable('role_menu_items')) {
            return;
        }

        $baruId = DB::table('menu_items')->where('key', self::BARU)->value('id');

        if (! $baruId) {
            $baruId = DB::table('menu_items')->insertGetId([
                'key'        => self::BARU,
                'label'      => 'PIC & Beneficiary',
                'icon'       => 'fas fa-user-tag',
                'route'      => '/pic-beneficiary',
                'section'    => 'master',
                'sort_order' => 13,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catat("buat menu '" . self::BARU . "'");
        }

        $lamaIds = DB::table('menu_items')->whereIn('key', self::LAMA)->pluck('id', 'key');

        if ($lamaIds->isNotEmpty()) {
            $grants = DB::table('role_menu_items')
                ->join('roles', 'roles.id', '=', 'role_menu_items.role_id')
                ->join('menu_items', 'menu_items.id', '=', 'role_menu_items.menu_item_id')
                ->whereIn('role_menu_items.menu_item_id', $lamaIds->values())
                ->get(['role_menu_items.role_id', 'roles.name as role_name', 'menu_items.key as menu_key']);

            foreach ($grants->groupBy('role_id') as $roleId => $baris) {
                $nama = $baris->first()->role_name;

                $sudah = DB::table('role_menu_items')
                    ->where('role_id', $roleId)
                    ->where('menu_item_id', $baruId)
                    ->exists();

                if (! $sudah) {
                    DB::table('role_menu_items')->insert([
                        'role_id'      => $roleId,
                        'menu_item_id' => $baruId,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $this->catat("beri menu '" . self::BARU . "' ke role '{$nama}' (dari: "
                        . $baris->pluck('menu_key')->implode(', ') . ')');
                }

                if ($baris->contains('menu_key', 'master-pic') && strtolower($nama) !== 'superadmin') {
                    $this->catat("PERHATIAN: role '{$nama}' dulu bisa ubah/hapus PIC; kini hanya lihat & tambah (PIC_MANAGE = superadmin)");
                }
            }

            foreach ($lamaIds as $key => $id) {
                $dicabut = DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
                $this->catat("hapus menu lama '{$key}' beserta {$dicabut} baris role_menu_items");
            }
        }

        if (Schema::hasTable('role_permissions') && Schema::hasTable('roles') && Authorizer::registry()) {
            $hasil = Authorizer::syncSeed(true);
            $this->catat('sinkron role_permissions: ' . json_encode($hasil));
        }
    }

    /**
     * Mengembalikan dua menu lama dan memberikan KEDUANYA ke setiap role yang
     * memegang menu baru. Pembagian asli (role mana punya yang mana) tidak
     * bisa direkonstruksi persis — lihat log migrasi up() untuk daftar
     * aslinya. Rollback penuh = kembalikan juga routes/web.php,
     * config/navigation.php, controller & view.
     */
    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasTable('role_menu_items')) {
            return;
        }

        $baruId = DB::table('menu_items')->where('key', self::BARU)->value('id');

        $data = [
            'beneficiaries' => ['Beneficiaries', 'fas fa-user-friends', '/beneficiaries', 13],
            'master-pic'    => ['PIC', 'fas fa-id-badge', '/master-pic', 16],
        ];

        $roleIds = $baruId
            ? DB::table('role_menu_items')->where('menu_item_id', $baruId)->pluck('role_id')
            : collect();

        foreach ($data as $key => [$label, $icon, $route, $sort]) {
            $id = DB::table('menu_items')->where('key', $key)->value('id')
                ?: DB::table('menu_items')->insertGetId([
                    'key'        => $key,
                    'label'      => $label,
                    'icon'       => $icon,
                    'route'      => $route,
                    'section'    => 'master',
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach ($roleIds as $roleId) {
                DB::table('role_menu_items')->insertOrIgnore([
                    'role_id'      => $roleId,
                    'menu_item_id' => $id,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        if ($baruId) {
            DB::table('role_menu_items')->where('menu_item_id', $baruId)->delete();
            DB::table('menu_items')->where('id', $baruId)->delete();
        }
    }

    private function catat(string $pesan): void
    {
        Log::info('[migrasi 2026_11_09_000400 pic-beneficiary] ' . $pesan);
        echo '  ' . $pesan . PHP_EOL;
    }
};
