<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Daftarkan kunci menu Master Data site/material. Sengaja TIDAK memberi akses
| ke role mana pun: superadmin sudah lolos menu:, role lain diberi lewat layar
| User & Access supaya tidak ada yang tiba-tiba melihat menu baru.
*/
return new class extends Migration
{
    private array $menus = [
        ['category-item',   'Category Item',   'fas fa-tags',            '/category-item',   60],
        ['master-material', 'Master Material', 'fas fa-microchip',       '/master-material', 61],
        ['accessories',     'Accessories',     'fas fa-plug',            '/accessories',     62],
        ['site',            'Sites',           'fas fa-broadcast-tower', '/site',            63],
        ['network',         'Network',         'fas fa-satellite-dish',  '/network',         64],
        ['homebase',        'Homebase',        'fas fa-warehouse',       '/homebase',        65],
        ['region',          'Region',          'fas fa-globe-asia',      '/region',          66],
        ['wilayah',         'Wilayah',         'fas fa-map-marked-alt',  '/wilayah',         67],
    ];

    public function up(): void
    {
        foreach ($this->menus as [$key, $label, $icon, $route, $sort]) {
            if (DB::table('menu_items')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('menu_items')->insert([
                'key'        => $key,
                'label'      => $label,
                'icon'       => $icon,
                'route'      => $route,
                'section'    => 'master',
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('menu_items')
            ->whereIn('key', array_column($this->menus, 0))
            ->pluck('id');

        DB::table('role_menu_items')->whereIn('menu_item_id', $ids)->delete();
        DB::table('menu_items')->whereIn('id', $ids)->delete();
    }
};
