<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Daftarkan menu 'todo-list' di section Finance.
    |
    | Akses route memakai middleware menu:todo-list, jadi menu item ini WAJIB
    | ada. Superadmin otomatis bypass; role lain (head, finance, cost_control,
    | manager, supervisor, approver) perlu dicentang manual lewat halaman
    | Role Management setelah migration ini jalan.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        $exists = DB::table('menu_items')->where('key', 'todo-list')->exists();

        if (!$exists) {
            DB::table('menu_items')->insert([
                'key'        => 'todo-list',
                'label'      => 'Todo List Request',
                'icon'       => 'fas fa-tasks',
                'route'      => '/todo-list',
                'section'    => 'finance',
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'todo-list')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};
