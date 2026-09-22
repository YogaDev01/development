<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beri role 'manager' akses menu 'beneficiaries'.
 *
 * KENAPA MIGRASI, BUKAN "centang manual":
 * Sebelum ini tabel `role_menu_items` tidak punya satu baris pun untuk
 * 'beneficiaries', sehingga master penerima dana hanya terlihat superadmin
 * lewat bypass di Role::hasMenu(). Manager yang membuat Cost Request jadi
 * harus menitip ke superadmin setiap kali ada penerima dana baru.
 *
 * YANG DIBUKA DI SINI HANYA PINTU MASUKNYA — melihat daftar dan menambah.
 * Mengubah & menghapus tetap milik superadmin, dijaga lapis kedua
 * 'role:superadmin' di routes/web.php. Pembagian itu disengaja: Beneficiary
 * dipakai Cost Request yang sudah berjalan, jadi menyunting baris lama
 * diam-diam mengubah riwayat dokumen yang sudah disetujui.
 *
 * Superadmin tidak perlu dicatat: Role::hasMenu() sudah meloloskannya.
 *
 * Aman diulang: baris yang sudah ada dilewati, bukan digandakan.
 */
return new class extends Migration
{
    /** Role yang boleh MELIHAT & MENAMBAH — cermin dari middleware 'menu:beneficiaries'. */
    private const AKSES = [
        'beneficiaries' => ['manager'],
    ];

    public function up(): void
    {
        foreach (self::AKSES as $menuKey => $roleNames) {
            $menuId = DB::table('menu_items')->where('key', $menuKey)->value('id');

            if (!$menuId) {
                continue; // menu-nya belum ada di instalasi ini
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
        /*
         | Hanya cabut baris yang migrasi ini pasang. whereIn() polos atas
         | menu_item_id akan ikut menghapus akses role lain yang diberikan
         | migrasi/pengaturan lain di kemudian hari.
         */
        foreach (self::AKSES as $menuKey => $roleNames) {
            $menuId = DB::table('menu_items')->where('key', $menuKey)->value('id');

            if (!$menuId) {
                continue;
            }

            $roleIds = DB::table('roles')
                ->whereIn(DB::raw('LOWER(name)'), $roleNames)
                ->pluck('id');

            DB::table('role_menu_items')
                ->where('menu_item_id', $menuId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }
    }
};
