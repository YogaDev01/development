<?php

/*
|--------------------------------------------------------------------------
| Snapshot gerbang route — alat developer (Fase 0 Security & Access Control)
|--------------------------------------------------------------------------
| Membangun ulang tests/Fixtures/security/route-gates.json dari output
| `artisan route:list --json` (read-only, tidak menyentuh basis data):
|
|   php artisan route:list --json > route-list.json
|   php scripts/security/route-gates-snapshot.php route-list.json
|
| Route cache (bootstrap/cache/routes-v*.php) HARUS tidak aktif, atau
| arahkan ke file yang tidak ada:
|   APP_ROUTES_CACHE=/tmp/tidak-ada.php php artisan route:list --json > route-list.json
| Tanpa itu route:list membaca cache, bukan routes/web.php.
|
| Normalisasi di sini HARUS sama dengan
| Tests\Feature\Security\RouteGateSnapshotTest::normalisasi(). Perbarui
| fixture hanya untuk perubahan gerbang yang disengaja, dalam commit yang
| sama dengan perubahan route-nya.
*/

if ($argc < 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Pemakaian: php scripts/security/route-gates-snapshot.php <route-list.json>\n");
    exit(1);
}

$routes = json_decode(file_get_contents($argv[1]), true);
if (! is_array($routes)) {
    fwrite(STDERR, "GAGAL: berkas bukan JSON route:list.\n");
    exit(1);
}

$normalisasi = function (string $m): ?string {
    if ($m === 'web' || $m === 'api') {
        return null;
    }
    if (str_starts_with($m, 'role:') || str_starts_with($m, 'menu:') || str_starts_with($m, 'throttle:')
        || $m === 'auth' || $m === 'guest') {
        return $m;
    }

    $kelas = strstr($m, ':', true) ?: $m;
    $param = str_contains($m, ':') ? substr($m, strpos($m, ':')) : '';
    $peta  = [
        'App\\Http\\Middleware\\CheckRole'                      => 'role',
        'App\\Http\\Middleware\\CheckMenu'                      => 'menu',
        'Illuminate\\Auth\\Middleware\\Authenticate'            => 'auth',
        'Illuminate\\Auth\\Middleware\\RedirectIfAuthenticated' => 'guest',
        'Illuminate\\Routing\\Middleware\\ThrottleRequests'     => 'throttle',
    ];
    $pos = strrpos($kelas, '\\');

    return ($peta[$kelas] ?? ($pos === false ? $kelas : substr($kelas, $pos + 1))) . $param;
};

$hasil = [];
foreach ($routes as $r) {
    $methods = implode('|', array_values(array_diff(explode('|', $r['method']), ['HEAD'])));
    $uri     = '/' . ltrim($r['uri'], '/');
    $hasil[$methods . ' ' . $uri] = array_values(array_filter(array_map($normalisasi, (array) $r['middleware'])));
}
ksort($hasil);

$tujuan = dirname(__DIR__, 2) . '/tests/Fixtures/security/route-gates.json';
file_put_contents($tujuan, json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo count($hasil) . " route ditulis ke tests/Fixtures/security/route-gates.json\n";
