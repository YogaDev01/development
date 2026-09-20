<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Surat Jalan dapat kunci menu SENDIRI ('delivery-note').
|
| Sebelumnya menu ini menumpang kunci 'invoice-cash-receipt', jadi ia sama
| sekali tidak muncul di Role master: satu-satunya cara menyembunyikannya
| adalah mencabut Invoice & Cash Receipt sekalian — yang bukan itu maksudnya.
|
| Hak akses yang SUDAH berjalan tidak boleh berubah karena pemisahan ini.
| Karena itu setiap role yang saat ini memegang 'invoice-cash-receipt'
| langsung diberi 'delivery-note' juga; yang berubah hanya: mulai sekarang
| keduanya bisa dicabut sendiri-sendiri.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'delivery-note')->value('id');

        if (!$menuId) {
            // sort_order diselipkan tepat sesudah Invoice & Cash Receipt supaya
            // urutannya di layar Role master mengikuti urutan kerjanya.
            $sesudahInvoice = (int) DB::table('menu_items')
                ->where('key', 'invoice-cash-receipt')
                ->value('sort_order');

            $menuId = DB::table('menu_items')->insertGetId([
                'key'        => 'delivery-note',
                'label'      => 'Surat Jalan',
                'icon'       => 'fas fa-truck',
                'route'      => '/delivery-note',
                'section'    => 'finance',
                'sort_order' => $sesudahInvoice ? $sesudahInvoice + 1 : 27,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $invoiceMenuId = DB::table('menu_items')->where('key', 'invoice-cash-receipt')->value('id');
        if (!$invoiceMenuId) {
            return;
        }

        $roleIds = DB::table('role_menu_items')
            ->where('menu_item_id', $invoiceMenuId)
            ->pluck('role_id');

        $sudahPunya = DB::table('role_menu_items')
            ->where('menu_item_id', $menuId)
            ->pluck('role_id')
            ->all();

        $baris = $roleIds
            ->reject(fn ($roleId) => in_array($roleId, $sudahPunya))
            ->map(fn ($roleId) => ['role_id' => $roleId, 'menu_item_id' => $menuId])
            ->values()
            ->all();

        if ($baris) {
            DB::table('role_menu_items')->insert($baris);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'delivery-note')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};
