<?php

/*
|--------------------------------------------------------------------------
| Generator kelompok aksi permission — alat developer (Fase 3)
|--------------------------------------------------------------------------
|   php scripts/security/permission-groups.php
|
| Membaca config/permissions.php lalu menulis config/permission_groups.php:
| permission dikelompokkan per (modul × kombinasi role yang IDENTIK). Karena
| isi tiap kelompok selalu punya role yang sama, mengelola hak per kelompok
| tidak pernah memecah parity dengan gerbang role: lama.
|
| Label kelompok diambil dari LABEL di bawah (kunci "modul|role,role").
| Kelompok baru tanpa label → generator berhenti dan menyebutkannya, supaya
| tidak ada aksi tanpa nama yang muncul di layar.
*/

const LABEL = [
    'accessgroup|superadmin'                                              => ['kelola', 'Kelola Access Group (tambah/ubah/hapus)'],
    'bank|admin,superadmin'                                               => ['kelola', 'Kelola master Bank'],
    'bank|admin,guest,superadmin'                                         => ['lihat', 'Lihat master Bank'],
    'beneficiaries|superadmin'                                            => ['kelola', 'Ubah/hapus Beneficiary & PIC-nya'],
    'master-pic|superadmin'                                               => ['kelola', 'Ubah/hapus PIC'],
    'budget-plan|manager,superadmin'                                      => ['pengaju', 'Buat, ubah, ajukan & revisi Budget Plan'],
    'budget-plan|approver,finance,finance_manager,head,superadmin'        => ['bulk', 'Setujui/tolak Budget Plan secara massal'],
    'budget-plan|approver,superadmin'                                     => ['ceo', 'Approval CEO Budget Plan'],
    'budget-plan|finance,finance_manager,superadmin'                      => ['finance', 'Verifikasi Finance & penutupan Budget Plan'],
    'budget-plan|head,superadmin'                                         => ['head', 'Approval Head Budget Plan'],
    'budget-request|manager,superadmin,supervisor'                        => ['pengaju', 'Buat, ubah, revisi Cost Request & isi settlement'],
    'budget-request|approver,superadmin'                                  => ['ceo', 'Approval CEO & Second Approval Cost Request'],
    'budget-request|finance_manager,superadmin'                           => ['settle', 'Approval & penutupan settlement (Finance Manager)'],
    'budget-request|approver,cost_control,finance,finance_manager,head,superadmin' => ['bulk', 'Review massal & takeout item Cost Request'],
    'budget-request|cost_control,finance,finance_manager,superadmin'      => ['finance', 'Verifikasi Finance Cost Request & settlement'],
    'budget-request|head,superadmin'                                      => ['head', 'Approval Head Cost Request & cek settlement'],
    'budget-request|manager,superadmin'                                   => ['submit', 'Submit & batalkan Cost Request'],
    'budget-request|manager,staff,superadmin,supervisor,teknisi'          => ['realisasi', 'Simpan & kirim realisasi (settlement)'],
    'cost-center|admin,finance,finance_manager,superadmin'                => ['kelola', 'Kelola master Cost Center'],
    'cost-register|approver,superadmin'                                   => ['lihat', 'Setujui, tolak & approve settlement Expense — CEO (lihat halaman lewat Menu Access)'],
    'cost-register|finance_manager,superadmin'                            => ['kelola', 'Buat, edit, ajukan, revisi, batalkan & settlement Expense (Finance Manager)'],
    'daily-transaction|finance,finance_manager,superadmin,treasury'       => ['kelola', 'Buat batch Treasury & tandai dibayar'],
    'daily-transaction|approver,superadmin'                               => ['approval', 'Approval batch Treasury'],
    'department|superadmin'                                               => ['kelola', 'Kelola Department'],
    'employee|admin,hr,superadmin'                                        => ['kelola', 'Kelola Employee'],
    'finance-summary-executive|approver,finance,finance_manager,superadmin' => ['lihat', 'Lihat Finance Summary Executive'],
    'groups|admin,superadmin'                                             => ['kelola', 'Kelola Cluster Group'],
    'groups|admin,guest,superadmin'                                       => ['lihat', 'Lihat Cluster Group'],
    'invoice-cash-receipt|finance_manager,superadmin'                     => ['approve', 'Approve Invoice'],
    'invoice-cash-receipt|approver,finance_manager,superadmin'            => ['cancel', 'Batalkan Invoice'],
    'invoice-cash-receipt|admin,collection,finance,finance_manager,superadmin' => ['kelola', 'Buat, ubah, ajukan Invoice & catat penerimaan'],
    'invoice-cash-receipt|accounting,approver,finance_manager,superadmin' => ['revise', 'Revisi Invoice'],
    'invoice-cash-receipt|accounting,finance_manager,superadmin'          => ['verify', 'Verifikasi Invoice'],
    'project|finance,finance_manager,head,superadmin'                     => ['aktivasi', 'Aktivasi PO & kelola nomor PO/BAST'],
    'project|approver,superadmin'                                         => ['approve', 'Approve PO & export daftar PO'],
    'project|head,superadmin'                                             => ['pengaju', 'Buat, ajukan PO, BAST & penutupan'],
    'project|approver,finance,finance_manager,superadmin'                 => ['revisi', 'Revisi Finance & approval massal PO'],
    'project|approver,finance,finance_manager,head,superadmin'            => ['ubah', 'Ubah PO & nomor PO'],
    'project|finance,finance_manager,superadmin'                          => ['finance', 'Verifikasi Finance & penutupan PO'],
    'project|superadmin'                                                  => ['hapus-bast', 'Hapus BAST PO'],
    'role|superadmin'                                                     => ['kelola', 'Kelola Role & Menu Access'],
    'security|superadmin'                                                 => ['konsol', 'Konsol Keamanan & cabut sesi'],
    'source-fund|finance,finance_manager,superadmin'                      => ['kelola', 'Kelola Source Fund'],
    'team|superadmin'                                                     => ['kelola', 'Buat Team & ubah status'],
    'trouble-ticket|superadmin'                                           => ['pengaturan', 'Pengaturan SLA, RCA & Severity Trouble Ticket'],
    'user|superadmin'                                                     => ['kelola', 'Kelola User (tambah/ubah/hapus)'],
];

$root = dirname(__DIR__, 2);
$reg  = (require $root . '/config/permissions.php')['permissions'];

$kelompok = [];
foreach ($reg as $key => $def) {
    $sig = $def['module'] . '|' . implode(',', $def['seed_roles']);
    if (! isset(LABEL[$sig])) {
        fwrite(STDERR, "GAGAL: kelompok baru tanpa label: {$sig} (mis. {$key}). Tambahkan ke LABEL.\n");
        exit(1);
    }
    [$slug, $label] = LABEL[$sig];
    $gkey = $def['module'] . '.' . $slug;
    $kelompok[$gkey] ??= ['module' => $def['module'], 'label' => $label, 'permissions' => []];
    $kelompok[$gkey]['permissions'][] = $key;
}

foreach ($kelompok as &$g) {
    sort($g['permissions']);
}
unset($g);
ksort($kelompok);

$isi  = "<?php\n\n/*\n| Kelompok aksi permission — DIBANGUN OTOMATIS oleh scripts/security/permission-groups.php.\n";
$isi .= "| Satu kelompok = permission satu modul dengan kombinasi role IDENTIK, jadi hak per kelompok\n";
$isi .= "| tidak memecah parity. Ubah label di skrip generator, bukan di sini.\n*/\n\n";
$isi .= 'return ' . var_export($kelompok, true) . ";\n";
$isi  = preg_replace(['/array \(/', '/\)(,?)\n/', '/\);\n$/'], ['[', ']$1' . "\n", "];\n"], $isi);

file_put_contents($root . '/config/permission_groups.php', $isi);
echo count($kelompok) . " kelompok ditulis ke config/permission_groups.php\n";
