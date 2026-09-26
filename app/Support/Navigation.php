<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Support\Collection;

/**
 * Pembaca config/navigation.php + config/apps.php.
 *
 * Satu tempat yang merakit susunan menu, dipakai oleh DUA konsumen supaya
 * keduanya tidak pernah berbeda pendapat:
 *
 *   - sidebar (layouts/partials/nav.blade.php)  -> sections()
 *   - modal "Atur Menu Access" di Role master  -> accessTree()
 *
 * Dengan begitu menambah menu ke sebuah aplikasi cukup di config; modal menu
 * access ikut menampilkannya di bawah aplikasi dan seksi yang benar.
 */
class Navigation
{
    public const MASTER_DATA = 'Master Data';

    /**
     * Seksi sidebar satu aplikasi: seksi dari 'apps.<key>' dengan Master Data
     * bersama disisipkan tepat setelah Overview.
     *
     * @return array<string, string[]>  label seksi => kunci item navigasi
     */
    public static function sections(string $app): array
    {
        $seksiApp = config('navigation.apps.' . $app, []);
        $master   = config('navigation.master_data', []);

        if (empty($master)) {
            return $seksiApp;
        }

        $hasil = [];
        $sudah = false;
        foreach ($seksiApp as $label => $kunci) {
            $hasil[$label] = $kunci;
            if ($label === 'Overview') {
                $hasil[self::MASTER_DATA] = $master;
                $sudah = true;
            }
        }

        return $sudah ? $hasil : [self::MASTER_DATA => $master] + $hasil;
    }

    /**
     * Kunci hak akses (menu_items.key) sebuah item navigasi, atau null bila
     * item itu tidak diatur lewat menu access (mis. dijaga 'roles').
     *
     * Dashboard tidak punya kunci 'menu' di config (selalu tampil di sidebar)
     * tetapi punya baris di menu_items dengan kunci yang sama dengan nama
     * itemnya — karena itu kunci item dipakai sebagai cadangan.
     */
    public static function menuKey(string $navKey): ?string
    {
        $item = config('navigation.items.' . $navKey);
        if (! $item) {
            return null;
        }
        if (! empty($item['menu'])) {
            return $item['menu'];
        }

        return empty($item['roles']) ? $navKey : null;
    }

    /**
     * Susunan checklist untuk modal menu access:
     *
     *   shared : menu yang berlaku di semua aplikasi (Dashboard + Master Data)
     *   apps   : per aplikasi -> per seksi -> menu (Master Data tidak diulang)
     *   others : baris menu_items yang belum dipetakan ke aplikasi mana pun,
     *            supaya tidak ada hak akses yang diam-diam tak bisa diatur
     *
     * Setiap menu berisi id/key/label/icon dari tabel menu_items; label & ikon
     * diambil dari config navigasi (nama yang dilihat user di sidebar).
     */
    public static function accessTree(?Collection $menuItems = null): array
    {
        $menuItems = ($menuItems ?? MenuItem::ordered()->get())->keyBy('key');
        $navItems  = config('navigation.items', []);
        $dipakai   = [];

        $petakan = function (array $navKeys) use ($menuItems, $navItems, &$dipakai): array {
            $hasil = [];
            foreach ($navKeys as $navKey) {
                $key = self::menuKey($navKey);
                $row = $key ? $menuItems->get($key) : null;
                if (! $row || isset($hasil[$row->id])) {
                    continue;
                }
                $dipakai[$row->key] = true;
                $hasil[$row->id] = [
                    'id'    => $row->id,
                    'key'   => $row->key,
                    'label' => $navItems[$navKey]['label'] ?? $row->label,
                    'icon'  => $navItems[$navKey]['icon'] ?? $row->icon,
                ];
            }

            return array_values($hasil);
        };

        $shared = [];
        $overviewBersama = $petakan(['dashboard']);
        if ($overviewBersama) {
            $shared[] = ['label' => 'Overview', 'items' => $overviewBersama];
        }
        $master = $petakan(config('navigation.master_data', []));
        if ($master) {
            $shared[] = ['label' => self::MASTER_DATA, 'items' => $master];
        }

        $apps = [];
        foreach (config('apps.items', []) as $appKey => $app) {
            $seksi = [];
            foreach (config('navigation.apps.' . $appKey, []) as $label => $navKeys) {
                if ($label === self::MASTER_DATA) {
                    continue;
                }
                $navKeys = array_values(array_diff($navKeys, ['dashboard']));
                $items   = $petakan($navKeys);
                if ($items) {
                    $seksi[] = ['label' => $label, 'items' => $items];
                }
            }
            if ($seksi) {
                $apps[] = [
                    'key'      => $appKey,
                    'label'    => 'Tesys ' . ($app['label'] ?? ucfirst($appKey)),
                    'icon'     => $app['icon'] ?? 'fas fa-th-large',
                    'sections' => $seksi,
                ];
            }
        }

        $others = $menuItems
            ->reject(fn ($row) => isset($dipakai[$row->key]))
            ->map(fn ($row) => [
                'id'    => $row->id,
                'key'   => $row->key,
                'label' => $row->label,
                'icon'  => $row->icon,
            ])
            ->values()
            ->all();

        return compact('shared', 'apps', 'others');
    }
}
