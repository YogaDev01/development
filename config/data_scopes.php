<?php

/*
|--------------------------------------------------------------------------
| Registry batas data (data scope) — Fase 4 Security & Access Control
|--------------------------------------------------------------------------
| Batas data diatur per modul × dimensi. Setiap dimensi punya pilihan yang
| DIDUKUNG data saat ini (lihat blueprint §3.3) — tidak ada pilihan
| Department/Division karena transaksi belum menyimpan unit pemilik (D4).
|
| 'seed'        : nilai awal per role (parity dengan kode yang berlaku hari
|                 ini). Role yang tidak disebut mendapat 'default'.
| 'role_source' : role yang dibaca — 'effective' (ikut View As) atau 'real'.
|                 Budget Plan memakai role asli karena begitulah kode lama
|                 (ScopesBudgetPlanVisibility) bekerja — dipertahankan demi
|                 parity, dicatat sebagai temuan.
|
| Sumber daftar role (WAJIB sama; dijaga DataScopeParityTest):
|   cost_request : BuildsCrWorklist::scopeByRole() (daftar early-return),
|                  OVERHEAD_PRIVILEGED_ROLES, PAYROLL_ROLES
|   budget_plan  : ScopesBudgetPlanVisibility BP_PROJECT_PRIVILEGED_ROLES,
|                  BP_OVERHEAD_PRIVILEGED_ROLES, BP_PAYROLL_ROLES
|   purchase_order : AccessRoles::PO_FULL_SCOPE
|   invoice      : ScopesInvoiceVisibility (hanya manager & head dibatasi)
|
| Mesin: App\Support\DataScope. BELUM dipasang ke controller — cutover per
| modul (Fase 5) butuh persetujuan pemilik.
*/

$crSemua = ['superadmin', 'approver', 'admin', 'finance', 'finance_manager', 'cost_control',
    'treasury', 'collection', 'accounting', 'guest'];

return [

    'modules' => [

        'cost_request' => [
            'label'       => 'Cost Request',
            'role_source' => 'effective',
            'dimensions'  => [
                'project' => [
                    'label'   => 'CR dari budget proyek',
                    'options' => [
                        'all'          => 'Semua PO',
                        'access_group' => 'PO di Access Group (tanpa Access Group: CR milik sendiri)',
                    ],
                    'default' => 'access_group',
                    'seed'    => ['all' => $crSemua],
                ],
                'overhead' => [
                    'label'   => 'CR dari budget overhead',
                    'options' => [
                        'all'          => 'Semua cost center overhead',
                        'access_group' => 'Cost center di Access Group',
                    ],
                    'default' => 'access_group',
                    'seed'    => ['all' => $crSemua],
                ],
                'payroll' => [
                    'label'   => 'CR payroll (rahasia)',
                    'options' => ['all' => 'Boleh melihat', 'none' => 'Tidak'],
                    'default' => 'none',
                    'seed'    => ['all' => array_values(array_unique(array_merge($crSemua, ['hr'])))],
                ],
            ],
        ],

        'budget_plan' => [
            'label'       => 'Budget Plan',
            'role_source' => 'real',
            'dimensions'  => [
                'project' => [
                    'label'   => 'Budget proyek',
                    'options' => ['all' => 'Semua PO', 'access_group' => 'PO di Access Group'],
                    'default' => 'access_group',
                    'seed'    => ['all' => ['superadmin', 'finance', 'finance_manager', 'approver', 'admin',
                        'cost_control', 'treasury', 'collection', 'accounting']],
                ],
                'overhead' => [
                    'label'   => 'Budget overhead',
                    'options' => ['all' => 'Semua cost center overhead', 'access_group' => 'Cost center di Access Group'],
                    'default' => 'access_group',
                    'seed'    => ['all' => ['superadmin', 'approver', 'finance_manager']],
                ],
                'payroll' => [
                    'label'   => 'Budget payroll (rahasia)',
                    'options' => ['all' => 'Boleh melihat', 'none' => 'Tidak'],
                    'default' => 'none',
                    'seed'    => ['all' => ['superadmin', 'approver', 'hr', 'finance_manager']],
                ],
            ],
        ],

        'purchase_order' => [
            'label'       => 'Purchase Order',
            'role_source' => 'effective',
            'dimensions'  => [
                'project' => [
                    'label'   => 'Daftar PO',
                    'options' => ['all' => 'Semua PO', 'access_group' => 'PO di Access Group'],
                    'default' => 'access_group',
                    'seed'    => ['all' => ['superadmin', 'approver', 'finance_manager',
                        'cost_control', 'collection', 'treasury', 'accounting']],
                ],
            ],
        ],

        'invoice' => [
            'label'       => 'Invoice & Cash Receipt',
            'role_source' => 'effective',
            'dimensions'  => [
                'project' => [
                    'label'   => 'Invoice per PO',
                    'options' => ['all' => 'Semua invoice', 'access_group' => 'Invoice PO di Access Group'],
                    // Parity: kode lama hanya membatasi manager & head; role lain (termasuk
                    // role baru) melihat semua invoice selama punya menu. Temuan, bukan aturan baru.
                    'default' => 'all',
                    'seed'    => ['access_group' => ['manager', 'head']],
                ],
            ],
        ],

    ],

    // Modul dengan aturan khusus — ditampilkan di tab Batas Data, tidak bisa diatur.
    'fixed' => [
        'trouble_ticket' => ['label' => 'Trouble Ticket', 'note' => 'Khusus: site → PIC → team, hierarki leader, pemohon dana, baca-saja Finance.'],
        'site'           => ['label' => 'Site', 'note' => 'Khusus: teknisi hanya customer dari site tempat ia terdaftar.'],
        'assignment'     => ['label' => 'Assignment', 'note' => 'Khusus: pembuat & penerima tugas.'],
        'lainnya'        => ['label' => 'Treasury, Cash Receipt, Cash Management, Cost Register, Delivery Note, Forecast, Closing, Payroll', 'note' => 'Semua baris bagi pemegang menu/role-nya (tanpa batas baris).'],
    ],

];
