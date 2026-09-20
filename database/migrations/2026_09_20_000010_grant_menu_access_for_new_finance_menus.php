<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beri akses menu 'cash-management' & 'cash-receipt' ke role yang memang
 * memegangnya.
 *
 * KENAPA MIGRASI, BUKAN "centang manual":
 * Tanpa baris di `role_menu_items`, dua menu baru ini hanya terlihat oleh
 * superadmin — dan di lingkungan mana pun yang tabelnya belum diisi, modul
 * yang baru dibangun akan tampak "tidak muncul" padahal route-nya sudah ada.
 *
 * Daftar role di bawah SENGAJA sama persis dengan yang diizinkan di
 * routes/web.php:
 *
 *     Route::middleware('role:superadmin,finance,treasury,approver')
 *
 * Kalau daftarnya lebih longgar dari route, hasilnya justru lebih buruk
 * daripada tidak ada menu sama sekali: tautannya terlihat di sidebar lalu
 * menabrak 403 saat diklik. Jadi kalau nanti route-nya diperluas (mis.
 * finance_manager), daftar di sini harus ikut diperluas — berpasangan.
 *
 * Superadmin tidak perlu dicatat: Role::hasMenu() sudah meloloskannya.
 *
 * Aman diulang: baris yang sudah ada dilewati, bukan digandakan.
 */
return new class extends Migration
{
    /** Harus cermin dari middleware role di routes/web.php. */
    private const AKSES = [
        'cash-management' => ['finance', 'treasury', 'approver'],
        'cash-receipt'    => ['finance', 'treasury', 'approver'],
    ];

    public function up(): void
    {
        foreach (self::AKSES as $menuKey => $roleNames) {
            $menuId = DB::table('menu_items')->where('key', $menuKey)->value('id');

            if (!$menuId) {
                continue; // menu-nya belum ada — migrasi pendaftarnya yang akan mengurus
            }

            foreach ($roleNames as $roleName) {
                $roleId = DB::table('roles')->whereRaw('LOWER(name) = ?', [$roleName])->value('id');

                if (!$roleId) {
                    continue; // role tidak ada di instalasi ini
                }

                $sudahAda = DB::table('role_menu_items')
                    ->where('role_id', $roleId)
                    ->where('menu_item_id', $menuId)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                DB::table('role_menu_items')->insert([
                    'role_id'      => $roleId,
                    'menu_item_id' => $menuId,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $menuIds = DB::table('menu_items')
            ->whereIn('key', array_keys(self::AKSES))
            ->pluck('id');

        DB::table('role_menu_items')->whereIn('menu_item_id', $menuIds)->delete();
    }
};
