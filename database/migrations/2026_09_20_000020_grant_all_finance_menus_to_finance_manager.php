<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * finance_manager memperoleh SELURUH menu modul Finance.
 *
 * Sebelumnya peran ini hanya didaftarkan pada sebagian menu (payroll, invoice,
 * closing), sehingga gerbang `menu:<key>` menutup halaman finance lain meski
 * gerbang `role:` di routes/web.php sudah mengizinkannya — halamannya ada,
 * tombolnya ada, tapi dibuka 403. Akses disamakan dengan peran 'finance':
 * setiap menu yang dipegang 'finance' ikut diberikan, ditambah semua menu
 * bersection 'finance' agar menu baru yang belum sempat diberikan ke 'finance'
 * pun tetap terjangkau.
 *
 * Aman diulang; hanya menambah, tidak pernah mencabut.
 */
return new class extends Migration
{
    private function roleId(string $name): ?int
    {
        return DB::table('roles')->whereRaw('LOWER(name) = ?', [$name])->value('id');
    }

    public function up(): void
    {
        $fmId = $this->roleId('finance_manager');

        if (!$fmId) {
            return;
        }

        $financeId = $this->roleId('finance');

        $menuIds = DB::table('menu_items')->where('section', 'finance')->pluck('id');

        if ($financeId) {
            $menuIds = $menuIds->merge(
                DB::table('role_menu_items')->where('role_id', $financeId)->pluck('menu_item_id')
            );
        }

        $menuIds = $menuIds->unique()->values();

        $sudahAda = DB::table('role_menu_items')
            ->where('role_id', $fmId)
            ->pluck('menu_item_id')
            ->all();

        $baris = $menuIds
            ->reject(fn ($id) => in_array($id, $sudahAda))
            ->map(fn ($id) => [
                'role_id'      => $fmId,
                'menu_item_id' => $id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ])
            ->all();

        if ($baris) {
            DB::table('role_menu_items')->insert($baris);
        }
    }

    /**
     * Tidak mencabut apa pun: baris yang ditambahkan di sini tidak bisa
     * dibedakan dari pemberian akses manual yang mungkin sudah ada, dan
     * menghapusnya berisiko mematikan akses yang memang sengaja diberikan.
     */
    public function down(): void
    {
        //
    }
};
