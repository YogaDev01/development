<?php

/*
|--------------------------------------------------------------------------
| Generator registry permission — alat developer (Fase 2 Security & Access Control)
|--------------------------------------------------------------------------
| Membangun config/permissions.php dari `artisan route:list --json` (read-only):
|
|   APP_ROUTES_CACHE=/tmp/tidak-ada.php php artisan route:list --json > route-list.json
|   php scripts/security/permissions-registry.php route-list.json
|
| Satu permission = satu route yang digerbang `role:`. Kuncinya nama route
| (atau "METHOD /uri" bila route tak bernama — sama dengan
| App\Support\Authorizer::routeKey()). 'seed_roles' = isi gerbang role: saat
| ini; dipakai SATU KALI untuk mengisi role_permissions agar mesin baru
| memberi jawaban identik dengan gerbang lama (parity).
|
| Label modul diambil dari MODUL di bawah; label permission default = nama
| route dan BOLEH disunting manual di config (generator mempertahankan label
| yang sudah ada). Lapis `menu:` tidak masuk registry — akses halaman tetap
| lewat role_menu_items.
*/

if ($argc < 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Pemakaian: php scripts/security/permissions-registry.php <route-list.json>\n");
    exit(1);
}

const MODUL = [
    'accessgroup'               => 'Access Group',
    'bank'                      => 'Bank',
    'beneficiaries'             => 'Beneficiary',
    'budget-plan'               => 'Budget Plan',
    'budget-request'            => 'Cost Request',
    'cash-management'           => 'Cash Management',
    'cash-receipt'              => 'Cash Receipt',
    'cost-center'               => 'Cost Center',
    'cost-register'             => 'Expense',
    'daily-transaction'         => 'Treasury',
    'department'                => 'Department',
    'employee'                  => 'Employee',
    'finance-summary-executive' => 'Finance Summary',
    'groups'                    => 'Cluster Group',
    'invoice-cash-receipt'      => 'Invoice & Cash Receipt',
    'master-pic'                => 'PIC',
    'project'                   => 'Purchase Order',
    'role'                      => 'Role & Menu Access',
    'security'                  => 'Konsol Keamanan',
    'source-fund'               => 'Source Fund',
    'team'                      => 'Team',
    'trouble-ticket'            => 'Trouble Ticket',
    'user'                      => 'User & Access',
];

$routes = json_decode(file_get_contents($argv[1]), true);
if (! is_array($routes)) {
    fwrite(STDERR, "GAGAL: berkas bukan JSON route:list.\n");
    exit(1);
}

$tujuan = dirname(__DIR__, 2) . '/config/permissions.php';
$lama   = is_file($tujuan) ? (require $tujuan)['permissions'] ?? [] : [];

$izin = [];
foreach ($routes as $r) {
    $gerbang = array_values(array_filter((array) $r['middleware'], fn ($m) => str_starts_with($m, 'role:')));
    if (! $gerbang) {
        continue;
    }
    if (count($gerbang) > 1) {
        fwrite(STDERR, "GAGAL: route punya lebih dari satu gerbang role: — {$r['uri']}\n");
        exit(1);
    }

    $nama = $r['name'] ?? '';
    if ($nama === '' || str_starts_with($nama, 'generated::')) {
        $methods = array_values(array_diff(explode('|', $r['method']), ['HEAD']));
        $kunci   = implode('|', $methods) . ' /' . ltrim($r['uri'], '/');
        $modul   = explode('/', ltrim($r['uri'], '/'))[0];
    } else {
        $kunci = $nama;
        $modul = explode('.', $nama)[0];
    }

    $roles = array_map(fn ($x) => strtolower(trim($x)), explode(',', substr($gerbang[0], 5)));
    sort($roles);

    $izin[$kunci] = [
        'module'     => $modul,
        'label'      => $lama[$kunci]['label'] ?? $kunci,
        'seed_roles' => array_values(array_unique($roles)),
    ];
}

ksort($izin);

$moduls = [];
foreach ($izin as $p) {
    $moduls[$p['module']] = MODUL[$p['module']] ?? ucwords(str_replace('-', ' ', $p['module']));
}
ksort($moduls);

$isi  = "<?php\n\n";
$isi .= "/*\n| Registry permission TESYS — DIBANGUN OTOMATIS oleh scripts/security/permissions-registry.php.\n";
$isi .= "| Satu entri = satu route bergerbang role:. 'seed_roles' hanya untuk isi awal role_permissions\n";
$isi .= "| (parity dengan gerbang lama); kewenangan yang berlaku dibaca dari tabel role_permissions.\n";
$isi .= "| Label boleh disunting manual; jalankan ulang generator bila route berubah.\n*/\n\n";
$isi .= "return [\n\n    'modules' => " . var_export($moduls, true) . ",\n\n    'permissions' => " . var_export($izin, true) . ",\n\n];\n";

// var_export memakai "array (" — rapikan ke sintaks pendek supaya sesuai gaya kode.
$isi = preg_replace(['/array \(/', '/\)(,?)\n/'], ['[', ']$1' . "\n"], $isi);

file_put_contents($tujuan, $isi);
echo count($izin) . " permission ditulis ke config/permissions.php\n";
