<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Permintaan: role 'guest' dibuat "open all access" persis seperti
    | 'approver' (CEO) untuk KEPERLUAN LIHAT SAJA — pembatasan create/
    | update/delete untuk guest sudah ditegakkan di level kode (lihat
    | perubahan role list di BuildsCrWorklist/FinanceSummaryController/
    | TodoListController/dst — guest ditambahkan ke daftar "privileged
    | view", TIDAK ditambahkan ke method aksi seperti ceoApprove/
    | ceoReject/verifyClosing dsb).
    |
    | Bagian akses HALAMAN (menu sidebar) diatur lewat data di tabel
    | role_menu_items, bukan kode — jadi disamakan di sini: setiap menu
    | yang sudah dimiliki 'approver', disalin juga ke 'guest'. Idempotent
    | (pakai firstOrCreate-style check per baris, aman dijalankan ulang).
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('role_menu_items')) {
            return;
        }

        $approverId = DB::table('roles')->where('name', 'approver')->value('id');
        $guestId    = DB::table('roles')->where('name', 'guest')->value('id');

        if (!$approverId || !$guestId) {
            return;
        }

        $approverMenuIds = DB::table('role_menu_items')
            ->where('role_id', $approverId)
            ->pluck('menu_item_id');

        $rows = $approverMenuIds->map(fn ($menuItemId) => [
            'role_id'      => $guestId,
            'menu_item_id' => $menuItemId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ])->all();

        if (!empty($rows)) {
            // insertOrIgnore — andalkan unique constraint (role_id, menu_item_id)
            // di tabelnya sendiri supaya idempotent, bukan cek manual di PHP.
            DB::table('role_menu_items')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun — kalau migration ini di-rollback,
        // tidak ada cara aman membedakan baris guest yang berasal dari sini
        // vs yang sudah di-set manual lewat halaman Access Group sebelumnya.
    }
};
