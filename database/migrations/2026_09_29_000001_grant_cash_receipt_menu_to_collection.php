<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beri role 'collection' akses ke modul Cash Receipt (M-03).
 *
 * Collection-lah yang mengejar pembayaran customer, jadi merekalah yang paling
 * sering mencatat bukti terima dan mengalokasikannya ke invoice. Sebelumnya
 * menu ini hanya dipegang finance/treasury/approver.
 *
 * Berpasangan dengan middleware role di routes/web.php pada grup 'cash-receipt'
 * — kalau hanya salah satu yang diubah, gejalanya membingungkan: menu muncul
 * tapi diklik 403, atau route jalan tapi menunya tidak pernah kelihatan.
 *
 * Aman diulang: baris yang sudah ada dilewati.
 */
return new class extends Migration
{
    private const MENU_KEY  = 'cash-receipt';
    private const ROLE_NAME = 'collection';

    public function up(): void
    {
        $menuId = DB::table('menu_items')->where('key', self::MENU_KEY)->value('id');
        $roleId = DB::table('roles')->whereRaw('LOWER(name) = ?', [self::ROLE_NAME])->value('id');

        if (!$menuId || !$roleId) {
            return; // menu atau role tidak ada di instalasi ini
        }

        $sudahAda = DB::table('role_menu_items')
            ->where('role_id', $roleId)
            ->where('menu_item_id', $menuId)
            ->exists();

        if ($sudahAda) {
            return;
        }

        DB::table('role_menu_items')->insert([
            'role_id'      => $roleId,
            'menu_item_id' => $menuId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', self::MENU_KEY)->value('id');
        $roleId = DB::table('roles')->whereRaw('LOWER(name) = ?', [self::ROLE_NAME])->value('id');

        if (!$menuId || !$roleId) {
            return;
        }

        DB::table('role_menu_items')
            ->where('role_id', $roleId)
            ->where('menu_item_id', $menuId)
            ->delete();
    }
};
