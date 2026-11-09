<?php

/*
| Kelompok aksi permission — DIBANGUN OTOMATIS oleh scripts/security/permission-groups.php.
| Satu kelompok = permission satu modul dengan kombinasi role IDENTIK, jadi hak per kelompok
| tidak memecah parity. Ubah label di skrip generator, bukan di sini.
*/

return [
  'accessgroup.kelola' => 
  [
    'module' => 'accessgroup',
    'label' => 'Kelola Access Group (tambah/ubah/hapus)',
    'permissions' => 
    [
      0 => 'accessgroup.create',
      1 => 'accessgroup.destroy',
      2 => 'accessgroup.edit',
      3 => 'accessgroup.store',
      4 => 'accessgroup.update',
    ],
  ],
  'bank.kelola' => 
  [
    'module' => 'bank',
    'label' => 'Kelola master Bank',
    'permissions' => 
    [
      0 => 'bank.create',
      1 => 'bank.destroy',
      2 => 'bank.edit',
      3 => 'bank.store',
      4 => 'bank.update',
    ],
  ],
  'bank.lihat' => 
  [
    'module' => 'bank',
    'label' => 'Lihat master Bank',
    'permissions' => 
    [
      0 => 'bank.index',
      1 => 'bank.show',
    ],
  ],
  'beneficiaries.kelola' => 
  [
    'module' => 'beneficiaries',
    'label' => 'Ubah/hapus Beneficiary & PIC-nya',
    'permissions' => 
    [
      0 => 'beneficiaries.destroy',
      1 => 'beneficiaries.edit-data',
      2 => 'beneficiaries.move-pic',
      3 => 'beneficiaries.pic-attach',
      4 => 'beneficiaries.pic-destroy',
      5 => 'beneficiaries.picker',
      6 => 'beneficiaries.update',
    ],
  ],
  'budget-plan.bulk' => 
  [
    'module' => 'budget-plan',
    'label' => 'Setujui/tolak Budget Plan secara massal',
    'permissions' => 
    [
      0 => 'budget-plan.bulk-approve',
      1 => 'budget-plan.bulk-reject',
    ],
  ],
  'budget-plan.ceo' => 
  [
    'module' => 'budget-plan',
    'label' => 'Approval CEO Budget Plan',
    'permissions' => 
    [
      0 => 'budget-plan.ceo-approve',
      1 => 'budget-plan.ceo-reject',
    ],
  ],
  'budget-plan.finance' => 
  [
    'module' => 'budget-plan',
    'label' => 'Verifikasi Finance & penutupan Budget Plan',
    'permissions' => 
    [
      0 => 'budget-plan.finance-close',
      1 => 'budget-plan.finance-reject',
      2 => 'budget-plan.finance-verify',
      3 => 'budget-plan.reject-closing',
      4 => 'budget-plan.verify-closing',
    ],
  ],
  'budget-plan.head' => 
  [
    'module' => 'budget-plan',
    'label' => 'Approval Head Budget Plan',
    'permissions' => 
    [
      0 => 'budget-plan.head-approve',
      1 => 'budget-plan.head-reject',
    ],
  ],
  'budget-plan.pengaju' => 
  [
    'module' => 'budget-plan',
    'label' => 'Buat, ubah, ajukan & revisi Budget Plan',
    'permissions' => 
    [
      0 => 'budget-plan.add-coa',
      1 => 'budget-plan.create',
      2 => 'budget-plan.destroy',
      3 => 'budget-plan.edit',
      4 => 'budget-plan.import-preview',
      5 => 'budget-plan.import-template',
      6 => 'budget-plan.manager-revise',
      7 => 'budget-plan.store',
      8 => 'budget-plan.submit',
      9 => 'budget-plan.submit-closing',
      10 => 'budget-plan.update',
    ],
  ],
  'budget-request.bulk' => 
  [
    'module' => 'budget-request',
    'label' => 'Review massal & takeout item Cost Request',
    'permissions' => 
    [
      0 => 'budget-request.detail.restore',
      1 => 'budget-request.detail.takeout',
      2 => 'budget-request.review-bulk-approve',
      3 => 'budget-request.review-bulk-reject',
      4 => 'budget-request.settlement-bulk-approve',
    ],
  ],
  'budget-request.ceo' => 
  [
    'module' => 'budget-request',
    'label' => 'Approval CEO & Second Approval Cost Request',
    'permissions' => 
    [
      0 => 'budget-request.ceo-approve',
      1 => 'budget-request.ceo-reject',
      2 => 'budget-request.second-approve',
      3 => 'budget-request.second-reject',
    ],
  ],
  'budget-request.finance' => 
  [
    'module' => 'budget-request',
    'label' => 'Verifikasi Finance Cost Request & settlement',
    'permissions' => 
    [
      0 => 'budget-request.finance-reject',
      1 => 'budget-request.finance-reject-settlement',
      2 => 'budget-request.finance-verify',
      3 => 'budget-request.finance-verify-settlement',
    ],
  ],
  'budget-request.head' => 
  [
    'module' => 'budget-request',
    'label' => 'Approval Head Cost Request & cek settlement',
    'permissions' => 
    [
      0 => 'budget-request.head-approve',
      1 => 'budget-request.head-check-settlement',
      2 => 'budget-request.head-reject',
      3 => 'budget-request.head-reject-settlement',
    ],
  ],
  'budget-request.pengaju' => 
  [
    'module' => 'budget-request',
    'label' => 'Buat, ubah, revisi Cost Request & isi settlement',
    'permissions' => 
    [
      0 => 'GET /budget-request/{id}/submit-realization',
      1 => 'budget-request.create',
      2 => 'budget-request.destroy',
      3 => 'budget-request.edit',
      4 => 'budget-request.import-preview',
      5 => 'budget-request.import-template',
      6 => 'budget-request.manager-revise',
      7 => 'budget-request.realize-form',
      8 => 'budget-request.settlement-import-preview',
      9 => 'budget-request.settlement-template',
      10 => 'budget-request.store',
      11 => 'budget-request.update',
    ],
  ],
  'budget-request.realisasi' => 
  [
    'module' => 'budget-request',
    'label' => 'Simpan & kirim realisasi (settlement)',
    'permissions' => 
    [
      0 => 'budget-request.save-realization-draft',
      1 => 'budget-request.submit-realization',
    ],
  ],
  'budget-request.settle' => 
  [
    'module' => 'budget-request',
    'label' => 'Approval & penutupan settlement (Finance Manager)',
    'permissions' => 
    [
      0 => 'budget-request.ceo-close-settlement',
      1 => 'budget-request.manager-reject-settlement',
      2 => 'budget-request.settle-approve',
      3 => 'budget-request.settle-reject',
      4 => 'budget-request.settlement-bulk-close',
      5 => 'budget-request.settlement-bulk-reject',
    ],
  ],
  'budget-request.submit' => 
  [
    'module' => 'budget-request',
    'label' => 'Submit & batalkan Cost Request',
    'permissions' => 
    [
      0 => 'budget-request.manager-cancel',
      1 => 'budget-request.submit',
    ],
  ],
  'cost-center.kelola' => 
  [
    'module' => 'cost-center',
    'label' => 'Kelola master Cost Center',
    'permissions' => 
    [
      0 => 'cost-center.destroy',
      1 => 'cost-center.store',
      2 => 'cost-center.update',
    ],
  ],
  'cost-register.kelola' => 
  [
    'module' => 'cost-register',
    'label' => 'Buat, ajukan & batalkan Cost Register',
    'permissions' => 
    [
      0 => 'cost-register.cancel',
      1 => 'cost-register.create',
      2 => 'cost-register.destroy',
      3 => 'cost-register.store',
      4 => 'cost-register.submit',
    ],
  ],
  'cost-register.lihat' => 
  [
    'module' => 'cost-register',
    'label' => 'Setujui & revisi Cost Register (lihat halaman lewat Menu Access)',
    'permissions' => 
    [
      0 => 'cost-register.approve',
      1 => 'cost-register.revise',
    ],
  ],
  'daily-transaction.approval' => 
  [
    'module' => 'daily-transaction',
    'label' => 'Approval batch Treasury',
    'permissions' => 
    [
      0 => 'daily-transaction.approve',
      1 => 'daily-transaction.bulk-approve',
      2 => 'daily-transaction.bulk-reject',
      3 => 'daily-transaction.reject-batch',
      4 => 'daily-transaction.reject-cr-item',
      5 => 'daily-transaction.reject-item',
    ],
  ],
  'daily-transaction.kelola' => 
  [
    'module' => 'daily-transaction',
    'label' => 'Buat batch Treasury & tandai dibayar',
    'permissions' => 
    [
      0 => 'daily-transaction.add-item',
      1 => 'daily-transaction.create',
      2 => 'daily-transaction.destroy',
      3 => 'daily-transaction.draft-remove-item',
      4 => 'daily-transaction.mark-paid',
      5 => 'daily-transaction.store',
      6 => 'daily-transaction.submit',
    ],
  ],
  'department.kelola' => 
  [
    'module' => 'department',
    'label' => 'Kelola Department',
    'permissions' => 
    [
      0 => 'department.destroy',
      1 => 'department.index',
      2 => 'department.store',
      3 => 'department.update',
    ],
  ],
  'employee.kelola' => 
  [
    'module' => 'employee',
    'label' => 'Kelola Employee',
    'permissions' => 
    [
      0 => 'employee.create',
      1 => 'employee.destroy',
      2 => 'employee.edit',
      3 => 'employee.store',
      4 => 'employee.update',
    ],
  ],
  'finance-summary-executive.lihat' => 
  [
    'module' => 'finance-summary-executive',
    'label' => 'Lihat Finance Summary Executive',
    'permissions' => 
    [
      0 => 'finance-summary-executive.index',
    ],
  ],
  'groups.kelola' => 
  [
    'module' => 'groups',
    'label' => 'Kelola Cluster Group',
    'permissions' => 
    [
      0 => 'groups.create',
      1 => 'groups.destroy',
      2 => 'groups.edit',
      3 => 'groups.edit-data',
      4 => 'groups.store',
      5 => 'groups.update',
    ],
  ],
  'groups.lihat' => 
  [
    'module' => 'groups',
    'label' => 'Lihat Cluster Group',
    'permissions' => 
    [
      0 => 'groups.index',
      1 => 'groups.show',
    ],
  ],
  'invoice-cash-receipt.approve' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'Approve Invoice',
    'permissions' => 
    [
      0 => 'invoice-cash-receipt.approve',
    ],
  ],
  'invoice-cash-receipt.cancel' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'Batalkan Invoice',
    'permissions' => 
    [
      0 => 'invoice-cash-receipt.cancel',
    ],
  ],
  'invoice-cash-receipt.kelola' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'Buat, ubah, ajukan Invoice & catat penerimaan',
    'permissions' => 
    [
      0 => 'invoice-cash-receipt.cash-receipt.destroy',
      1 => 'invoice-cash-receipt.cash-receipt.settle',
      2 => 'invoice-cash-receipt.cash-receipt.store',
      3 => 'invoice-cash-receipt.create',
      4 => 'invoice-cash-receipt.destroy',
      5 => 'invoice-cash-receipt.edit',
      6 => 'invoice-cash-receipt.import-preview',
      7 => 'invoice-cash-receipt.import-template',
      8 => 'invoice-cash-receipt.mark-received',
      9 => 'invoice-cash-receipt.store',
      10 => 'invoice-cash-receipt.submit',
      11 => 'invoice-cash-receipt.update',
    ],
  ],
  'invoice-cash-receipt.revise' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'Revisi Invoice',
    'permissions' => 
    [
      0 => 'invoice-cash-receipt.revise',
    ],
  ],
  'invoice-cash-receipt.verify' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'Verifikasi Invoice',
    'permissions' => 
    [
      0 => 'invoice-cash-receipt.verify',
    ],
  ],
  'project.aktivasi' => 
  [
    'module' => 'project',
    'label' => 'Aktivasi PO & kelola nomor PO/BAST',
    'permissions' => 
    [
      0 => 'project.activate',
      1 => 'project.activate-form',
      2 => 'project.activate-from-waiting',
      3 => 'project.po-number.complete-bast',
      4 => 'project.po-number.destroy',
      5 => 'project.po-number.status',
    ],
  ],
  'project.approve' => 
  [
    'module' => 'project',
    'label' => 'Approve PO & export daftar PO',
    'permissions' => 
    [
      0 => 'project.approve',
      1 => 'project.export-excel',
    ],
  ],
  'project.finance' => 
  [
    'module' => 'project',
    'label' => 'Verifikasi Finance & penutupan PO',
    'permissions' => 
    [
      0 => 'project.finance-close',
      1 => 'project.reject-closing',
      2 => 'project.verify',
      3 => 'project.verify-closing',
    ],
  ],
  'project.hapus-bast' => 
  [
    'module' => 'project',
    'label' => 'Hapus BAST PO',
    'permissions' => 
    [
      0 => 'project.po-bast.destroy',
    ],
  ],
  'project.pengaju' => 
  [
    'module' => 'project',
    'label' => 'Buat, ajukan PO, BAST & penutupan',
    'permissions' => 
    [
      0 => 'project.budget-import-preview',
      1 => 'project.budget-import-template',
      2 => 'project.create',
      3 => 'project.destroy',
      4 => 'project.import-preview',
      5 => 'project.import-template',
      6 => 'project.store',
      7 => 'project.submit',
      8 => 'project.submit-bast',
      9 => 'project.submit-bast.store',
      10 => 'project.submit-closing',
    ],
  ],
  'project.revisi' => 
  [
    'module' => 'project',
    'label' => 'Revisi Finance & approval massal PO',
    'permissions' => 
    [
      0 => 'project.bulk-approve',
      1 => 'project.bulk-revise',
      2 => 'project.finance-revise',
    ],
  ],
  'project.ubah' => 
  [
    'module' => 'project',
    'label' => 'Ubah PO & nomor PO',
    'permissions' => 
    [
      0 => 'project.edit',
      1 => 'project.edit-po-numbers',
      2 => 'project.po-number.import-preview',
      3 => 'project.po-number.import-template',
      4 => 'project.update',
      5 => 'project.update-po-numbers',
    ],
  ],
  'role.kelola' => 
  [
    'module' => 'role',
    'label' => 'Kelola Role & Menu Access',
    'permissions' => 
    [
      0 => 'role.edit',
      1 => 'role.edit-data',
      2 => 'role.index',
      3 => 'role.store',
      4 => 'role.update',
    ],
  ],
  'security.konsol' => 
  [
    'module' => 'security',
    'label' => 'Konsol Keamanan & cabut sesi',
    'permissions' => 
    [
      0 => 'security.index',
      1 => 'security.sessions.revoke',
      2 => 'security.users.logout-all',
    ],
  ],
  'source-fund.kelola' => 
  [
    'module' => 'source-fund',
    'label' => 'Kelola Source Fund',
    'permissions' => 
    [
      0 => 'source-fund.destroy',
      1 => 'source-fund.edit-data',
      2 => 'source-fund.store',
      3 => 'source-fund.update',
    ],
  ],
  'team.kelola' => 
  [
    'module' => 'team',
    'label' => 'Buat Team & ubah status',
    'permissions' => 
    [
      0 => 'team.create',
      1 => 'team.status',
      2 => 'team.store',
    ],
  ],
  'trouble-ticket.pengaturan' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'Pengaturan SLA, RCA & Severity Trouble Ticket',
    'permissions' => 
    [
      0 => 'trouble-ticket.settings',
      1 => 'trouble-ticket.settings.rca.store',
      2 => 'trouble-ticket.settings.rca.toggle',
      3 => 'trouble-ticket.settings.rca.update',
      4 => 'trouble-ticket.settings.severity.store',
      5 => 'trouble-ticket.settings.severity.toggle',
      6 => 'trouble-ticket.settings.severity.update',
      7 => 'trouble-ticket.settings.sla.limit',
      8 => 'trouble-ticket.settings.sub-rca.store',
      9 => 'trouble-ticket.settings.sub-rca.toggle',
      10 => 'trouble-ticket.settings.sub-rca.update',
    ],
  ],
  'user.kelola' => 
  [
    'module' => 'user',
    'label' => 'Kelola User (tambah/ubah/hapus)',
    'permissions' => 
    [
      0 => 'user.create',
      1 => 'user.destroy',
      2 => 'user.edit',
      3 => 'user.store',
      4 => 'user.update',
    ],
  ],
];
