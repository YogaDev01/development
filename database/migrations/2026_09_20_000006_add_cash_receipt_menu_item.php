<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| M-03 — daftarkan menu 'cash-receipt' di section Finance.
|
| Menu Invoice & Cash Receipt yang lama TIDAK diubah: di sana pembayaran
| dicatat per invoice, dan itu tetap jalur yang benar untuk pelunasan tunggal.
| Menu ini untuk satu penerimaan yang menutup beberapa invoice sekaligus.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menu_items')->where('key', 'cash-receipt')->exists()) {
            return;
        }

        DB::table('menu_items')->insert([
            'key'        => 'cash-receipt',
            'label'      => 'Cash Receipt',
            'icon'       => 'fas fa-hand-holding-usd',
            'route'      => '/cash-receipt',
            'section'    => 'finance',
            'sort_order' => 26,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'cash-receipt')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};
