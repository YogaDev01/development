<?php

/*
| Pemetaan kelompok aksi (config/permission_groups.php) ke kolom matriks Kewenangan Aksi
| di modal Role: view | create | edit | delete | approve | export.
|
| USULAN — perlu konfirmasi pemilik. Pemetaan ini hanya mengatur TAMPILAN matriks; kelompok aksi
| tetap satu unit: satu kelompok yang muncul di beberapa kolom (mis. "kelola" = create + edit + delete)
| menyala/mati bersamaan di semua kolomnya. Kolom View hampir kosong karena hak melihat halaman diatur
| lewat Menu Access, bukan kewenangan aksi.
|
| Kelompok yang tidak tercantum di sini tidak tampil di matriks (tetap ada di registry & audit).
| Hak yang disimpan dari matriks masuk ke role_permissions dan BELUM menentukan akses nyata
| selama gerbang `role:` lama masih berlaku (mode uji, lihat docs/security.md).
*/

$kelola = ['create', 'edit', 'delete'];

return [
    // Master data & administrasi
    'accessgroup.kelola'              => $kelola,
    'bank.kelola'                     => $kelola,
    'bank.lihat'                      => ['view'],
    'beneficiaries.kelola'            => ['edit', 'delete'],
    'cost-center.kelola'              => $kelola,
    'department.kelola'               => $kelola,
    'employee.kelola'                 => $kelola,
    'groups.kelola'                   => $kelola,
    'groups.lihat'                    => ['view'],
    'master-pic.kelola'               => ['edit', 'delete'],
    'role.kelola'                     => $kelola,
    'source-fund.kelola'              => $kelola,
    'team.kelola'                     => ['create', 'edit'],
    'user.kelola'                     => $kelola,
    'security.konsol'                 => ['view', 'edit'],
    'finance-summary-executive.lihat' => ['view'],
    'trouble-ticket.pengaturan'       => $kelola,

    // Budget Plan
    'budget-plan.pengaju'             => ['create', 'edit'],
    'budget-plan.head'                => ['approve'],
    'budget-plan.ceo'                 => ['approve'],
    'budget-plan.finance'             => ['approve'],
    'budget-plan.bulk'                => ['approve'],

    // Cost Request
    'budget-request.pengaju'          => ['create', 'edit'],
    'budget-request.submit'           => ['edit'],
    'budget-request.realisasi'        => ['edit'],
    'budget-request.head'             => ['approve'],
    'budget-request.ceo'              => ['approve'],
    'budget-request.finance'          => ['approve'],
    'budget-request.settle'           => ['approve'],
    'budget-request.bulk'             => ['approve'],

    // Expense (Cost Register)
    'cost-register.kelola'            => ['create', 'edit'],
    'cost-register.lihat'             => ['approve'],

    // Treasury
    'daily-transaction.kelola'        => ['create', 'edit'],
    'daily-transaction.approval'      => ['approve'],

    // Invoice & Cash Receipt
    'invoice-cash-receipt.kelola'     => ['create', 'edit'],
    'invoice-cash-receipt.revise'     => ['edit'],
    'invoice-cash-receipt.cancel'     => ['delete'],
    'invoice-cash-receipt.verify'     => ['approve'],
    'invoice-cash-receipt.approve'    => ['approve'],

    // Purchase Order (Project)
    'project.pengaju'                 => ['create', 'edit'],
    'project.aktivasi'                => ['edit'],
    'project.ubah'                    => ['edit'],
    'project.revisi'                  => ['edit'],
    'project.finance'                 => ['approve'],
    'project.approve'                 => ['approve', 'export'],
    'project.hapus-bast'              => ['delete'],
];
