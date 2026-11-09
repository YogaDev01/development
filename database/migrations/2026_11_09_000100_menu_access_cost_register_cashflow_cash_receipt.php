<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost Register, Cashflow (cash-management) & Cash Receipt diatur lewat
 * Menu Access (modal "Atur Menu Access" di Role master).
 *
 * TEMUAN AUDIT (2026-11-09):
 *   - Cost Register dijaga daftar role tertulis (route role:… dan 'roles' di
 *     config/navigation.php), jadi tidak pernah muncul di modal Menu Access,
 *     dan daftar sidebar (tanpa approver) tidak sama dengan route (dengan
 *     approver).
 *   - Cashflow & Cash Receipt tampil di modal (kunci menu), tetapi route-nya
 *     dijaga role:…, sehingga centang di modal hanya mengatur sidebar:
 *     dicentang untuk role di luar daftar -> menu muncul lalu 403; dicabut
 *     -> menu hilang tapi URL tetap terbuka.
 *
 * Route ketiganya sekarang memakai menu:<key>. Supaya akses EFEKTIF tidak
 * berubah saat deploy, migrasi ini menyamakan role_menu_items dengan daftar
 * role route yang lama:
 *   1. baris menu_items dipastikan ada (cost-register baru);
 *   2. role di daftar lama diberi akses (bila belum);
 *   3. role di LUAR daftar lama yang terlanjur dicentang dicabut — sebelumnya
 *      centang itu tidak pernah membuka halaman (403), jadi mencabutnya tidak
 *      mengurangi akses nyata, sedangkan membiarkannya justru membuka akses
 *      baru. Setiap pencabutan dicetak & dicatat di log.
 * Superadmin tidak dicatat: Role::hasMenu() & CheckMenu sudah meloloskannya.
 *
 * Aman diulang.
 */
return new class extends Migration
{
    /** Cermin daftar role route lama di routes/web.php (tanpa superadmin). */
    private const AKSES = [
        'cost-register'   => ['finance', 'finance_manager', 'approver'],
        'cash-management' => ['finance', 'finance_manager', 'treasury', 'approver'],
        'cash-receipt'    => ['finance', 'finance_manager', 'treasury', 'approver', 'collection'],
    ];

    /** Data baris menu_items bila belum ada. */
    private const MENU = [
        'cost-register'   => ['Cost Register', 'fas fa-book', '/cost-register', 23],
        'cash-management' => ['Cash Management', 'fas fa-wallet', '/cash-management', 25],
        'cash-receipt'    => ['Cash Receipt', 'fas fa-hand-holding-usd', '/cash-receipt', 26],
    ];

    public function up(): void
    {
        foreach (self::AKSES as $menuKey => $roleNames) {
            $menuId = DB::table('menu_items')->where('key', $menuKey)->value('id');

            if (! $menuId) {
                [$label, $icon, $route, $sort] = self::MENU[$menuKey];
                $menuId = DB::table('menu_items')->insertGetId([
                    'key'        => $menuKey,
                    'label'      => $label,
                    'icon'       => $icon,
                    'route'      => $route,
                    'section'    => 'finance',
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roles = DB::table('roles')->get(['id', 'name']);

            foreach ($roles as $role) {
                $nama = strtolower($role->name);
                if ($nama === 'superadmin') {
                    continue;
                }

                $punya = DB::table('role_menu_items')
                    ->where('role_id', $role->id)
                    ->where('menu_item_id', $menuId)
                    ->exists();
                $boleh = in_array($nama, $roleNames, true);

                if ($boleh && ! $punya) {
                    DB::table('role_menu_items')->insert([
                        'role_id'      => $role->id,
                        'menu_item_id' => $menuId,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $this->catat("beri akses menu '{$menuKey}' ke role '{$role->name}'");
                } elseif (! $boleh && $punya) {
                    DB::table('role_menu_items')
                        ->where('role_id', $role->id)
                        ->where('menu_item_id', $menuId)
                        ->delete();
                    $this->catat("cabut centang menu '{$menuKey}' dari role '{$role->name}' (sebelumnya route menolak role ini)");
                }
            }
        }
    }

    /**
     * Hanya menu baru (cost-register) yang dihapus beserta aksesnya. Penyamaan
     * akses cash-management/cash-receipt tidak dikembalikan: keadaan lamanya
     * tidak menentukan akses route, dan lognya tersedia di storage/logs.
     * Rollback penuh = kembalikan juga routes/web.php & config/navigation.php.
     */
    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'cost-register')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }

    private function catat(string $pesan): void
    {
        Log::info('[migrasi menu access 2026_11_09_000100] ' . $pesan);
        echo '  ' . $pesan . PHP_EOL;
    }
};
