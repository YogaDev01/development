<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Buka menu 'project-monitoring' untuk SELURUH role.
 *
 * Modulnya sudah lama jadi — controller, model, dan tujuh route-nya lengkap —
 * tapi menunya tidak pernah bisa dilihat siapa pun selain superadmin, karena
 * `role_menu_items` tidak punya satu baris pun untuk kunci ini. Halamannya
 * hanya bisa dicapai dengan mengetik /project-monitoring langsung.
 *
 * KENAPA SEMUA ROLE, BUKAN SEBAGIAN:
 * Route-nya memang sengaja dibuka tanpa gerbang menu — lihat komentarnya di
 * routes/web.php:
 *
 *     // Dibuka untuk semua role (tanpa gerbang menu:project-monitoring) —
 *     // sebelumnya beberapa role (mis. finance) diblokir/tidak melihat data.
 *
 * Memberi menunya ke sebagian role saja akan membuat dua aturan yang berbeda
 * untuk halaman yang sama: sebagian orang melihat menunya, sebagian lagi harus
 * hafal alamatnya padahal sama-sama boleh masuk. Jadi daftar ini mengikuti
 * route, bukan menebak sendiri.
 *
 * Kalau nanti sebuah role dirasa tidak perlu melihatnya, cabut lewat layar
 * User & Access — itu tidak menutup halamannya, hanya menyembunyikan menunya.
 * Untuk benar-benar menutup, route-nya yang harus digerbang.
 *
 * Superadmin tidak perlu dicatat: Role::hasMenu() sudah meloloskannya.
 *
 * Aman diulang: baris yang sudah ada dilewati, bukan digandakan.
 */
return new class extends Migration
{
    private const MENU_KEY = 'project-monitoring';

    public function up(): void
    {
        $menuId = DB::table('menu_items')->where('key', self::MENU_KEY)->value('id');

        if (!$menuId) {
            return; // menu-nya belum terdaftar di instalasi ini
        }

        /*
         | Diambil dari tabel, bukan ditulis sebagai daftar tetap: daftar role
         | di aplikasi ini masih bertambah (hr, finance_manager, cost_control,
         | … ditambahkan lewat migrasi belakangan), dan daftar tetap di sini
         | akan diam-diam melewatkan role yang lahir setelahnya.
         */
        $roleIds = DB::table('roles')
            ->whereRaw('LOWER(name) != ?', ['superadmin'])
            ->pluck('id');

        $sudahPunya = DB::table('role_menu_items')
            ->where('menu_item_id', $menuId)
            ->pluck('role_id')
            ->all();

        $baris = $roleIds
            ->reject(fn ($roleId) => in_array($roleId, $sudahPunya))
            ->map(fn ($roleId) => [
                'role_id'      => $roleId,
                'menu_item_id' => $menuId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ])
            ->all();

        if ($baris) {
            DB::table('role_menu_items')->insert($baris);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', self::MENU_KEY)->value('id');

        if (!$menuId) {
            return;
        }

        DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
    }
};
