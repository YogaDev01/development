<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Daftarkan kunci menu Trouble Ticket. Sengaja TIDAK memberi akses ke role
| mana pun (sama seperti 2026_10_09_100005): superadmin lolos menu:, role lain
| — termasuk 'staff' yang dipakai akun teknisi — diberi lewat User & Access.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menu_items')->where('key', 'trouble-ticket')->exists()) {
            return;
        }

        DB::table('menu_items')->insert([
            'key'        => 'trouble-ticket',
            'label'      => 'Trouble Ticket',
            'icon'       => 'fas fa-ticket-alt',
            'route'      => '/trouble-ticket',
            'section'    => 'operation',
            'sort_order' => 34,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $ids = DB::table('menu_items')->where('key', 'trouble-ticket')->pluck('id');

        DB::table('role_menu_items')->whereIn('menu_item_id', $ids)->delete();
        DB::table('menu_items')->whereIn('id', $ids)->delete();
    }
};
