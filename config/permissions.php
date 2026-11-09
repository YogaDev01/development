<?php

/*
| Registry permission TESYS — DIBANGUN OTOMATIS oleh scripts/security/permissions-registry.php.
| Satu entri = satu route bergerbang role:. 'seed_roles' hanya untuk isi awal role_permissions
| (parity dengan gerbang lama); kewenangan yang berlaku dibaca dari tabel role_permissions.
| Label boleh disunting manual; jalankan ulang generator bila route berubah.
*/

return [

    'modules' => [
  'accessgroup' => 'Access Group',
  'bank' => 'Bank',
  'beneficiaries' => 'Beneficiary',
  'budget-plan' => 'Budget Plan',
  'budget-request' => 'Cost Request',
  'cost-center' => 'Cost Center',
  'cost-register' => 'Cost Register',
  'daily-transaction' => 'Treasury',
  'department' => 'Department',
  'employee' => 'Employee',
  'finance-summary-executive' => 'Finance Summary',
  'groups' => 'Cluster Group',
  'invoice-cash-receipt' => 'Invoice & Cash Receipt',
  'project' => 'Purchase Order',
  'role' => 'Role & Menu Access',
  'security' => 'Konsol Keamanan',
  'source-fund' => 'Source Fund',
  'team' => 'Team',
  'trouble-ticket' => 'Trouble Ticket',
  'user' => 'User & Access',
],

    'permissions' => [
  'GET /budget-request/{id}/submit-realization' => 
  [
    'module' => 'budget-request',
    'label' => 'GET /budget-request/{id}/submit-realization',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'accessgroup.create' => 
  [
    'module' => 'accessgroup',
    'label' => 'accessgroup.create',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'accessgroup.destroy' => 
  [
    'module' => 'accessgroup',
    'label' => 'accessgroup.destroy',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'accessgroup.edit' => 
  [
    'module' => 'accessgroup',
    'label' => 'accessgroup.edit',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'accessgroup.store' => 
  [
    'module' => 'accessgroup',
    'label' => 'accessgroup.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'accessgroup.update' => 
  [
    'module' => 'accessgroup',
    'label' => 'accessgroup.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'bank.create' => 
  [
    'module' => 'bank',
    'label' => 'bank.create',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'bank.destroy' => 
  [
    'module' => 'bank',
    'label' => 'bank.destroy',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'bank.edit' => 
  [
    'module' => 'bank',
    'label' => 'bank.edit',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'bank.index' => 
  [
    'module' => 'bank',
    'label' => 'bank.index',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'guest',
      2 => 'superadmin',
    ],
  ],
  'bank.show' => 
  [
    'module' => 'bank',
    'label' => 'bank.show',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'guest',
      2 => 'superadmin',
    ],
  ],
  'bank.store' => 
  [
    'module' => 'bank',
    'label' => 'bank.store',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'bank.update' => 
  [
    'module' => 'bank',
    'label' => 'bank.update',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'beneficiaries.destroy' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.destroy',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'beneficiaries.edit-data' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.edit-data',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'beneficiaries.move-pic' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.move-pic',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'beneficiaries.pic-attach' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.pic-attach',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'beneficiaries.pic-destroy' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.pic-destroy',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'beneficiaries.picker' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.picker',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'beneficiaries.update' => 
  [
    'module' => 'beneficiaries',
    'label' => 'beneficiaries.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'budget-plan.add-coa' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.add-coa',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.bulk-approve' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.bulk-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'budget-plan.bulk-reject' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.bulk-reject',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'budget-plan.ceo-approve' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.ceo-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.ceo-reject' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.ceo-reject',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.create' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.create',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.destroy' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.destroy',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.edit' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.edit',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.finance-close' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.finance-close',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'budget-plan.finance-reject' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.finance-reject',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'budget-plan.finance-verify' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.finance-verify',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'budget-plan.head-approve' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.head-approve',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.head-reject' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.head-reject',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.import-preview' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.import-preview',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.import-template' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.import-template',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.manager-revise' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.manager-revise',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.reject-closing' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.reject-closing',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'budget-plan.store' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.store',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.submit' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.submit',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.submit-closing' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.submit-closing',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.update' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.update',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-plan.verify-closing' => 
  [
    'module' => 'budget-plan',
    'label' => 'budget-plan.verify-closing',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'budget-request.ceo-approve' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.ceo-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'budget-request.ceo-close-settlement' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.ceo-close-settlement',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.ceo-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.ceo-reject',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'budget-request.create' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.create',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.destroy' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.destroy',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.detail.restore' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.detail.restore',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'cost_control',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'head',
      5 => 'superadmin',
    ],
  ],
  'budget-request.detail.takeout' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.detail.takeout',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'cost_control',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'head',
      5 => 'superadmin',
    ],
  ],
  'budget-request.edit' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.edit',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.finance-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.finance-reject',
    'seed_roles' => 
    [
      0 => 'cost_control',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'budget-request.finance-reject-settlement' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.finance-reject-settlement',
    'seed_roles' => 
    [
      0 => 'cost_control',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'budget-request.finance-verify' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.finance-verify',
    'seed_roles' => 
    [
      0 => 'cost_control',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'budget-request.finance-verify-settlement' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.finance-verify-settlement',
    'seed_roles' => 
    [
      0 => 'cost_control',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'budget-request.head-approve' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.head-approve',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'budget-request.head-check-settlement' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.head-check-settlement',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'budget-request.head-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.head-reject',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'budget-request.head-reject-settlement' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.head-reject-settlement',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'budget-request.import-preview' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.import-preview',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.import-template' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.import-template',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.manager-cancel' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.manager-cancel',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.manager-reject-settlement' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.manager-reject-settlement',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.manager-revise' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.manager-revise',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.realize-form' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.realize-form',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.review-bulk-approve' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.review-bulk-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'cost_control',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'head',
      5 => 'superadmin',
    ],
  ],
  'budget-request.review-bulk-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.review-bulk-reject',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'cost_control',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'head',
      5 => 'superadmin',
    ],
  ],
  'budget-request.save-realization-draft' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.save-realization-draft',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'staff',
      2 => 'superadmin',
      3 => 'supervisor',
      4 => 'teknisi',
    ],
  ],
  'budget-request.second-approve' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.second-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'budget-request.second-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.second-reject',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'budget-request.settle-approve' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settle-approve',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.settle-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settle-reject',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.settlement-bulk-approve' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settlement-bulk-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'cost_control',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'head',
      5 => 'superadmin',
    ],
  ],
  'budget-request.settlement-bulk-close' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settlement-bulk-close',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.settlement-bulk-reject' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settlement-bulk-reject',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.settlement-import-preview' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settlement-import-preview',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.settlement-template' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.settlement-template',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.store' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.store',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'budget-request.submit' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.submit',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
    ],
  ],
  'budget-request.submit-realization' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.submit-realization',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'staff',
      2 => 'superadmin',
      3 => 'supervisor',
      4 => 'teknisi',
    ],
  ],
  'budget-request.update' => 
  [
    'module' => 'budget-request',
    'label' => 'budget-request.update',
    'seed_roles' => 
    [
      0 => 'manager',
      1 => 'superadmin',
      2 => 'supervisor',
    ],
  ],
  'cost-center.destroy' => 
  [
    'module' => 'cost-center',
    'label' => 'cost-center.destroy',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'cost-center.store' => 
  [
    'module' => 'cost-center',
    'label' => 'cost-center.store',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'cost-center.update' => 
  [
    'module' => 'cost-center',
    'label' => 'cost-center.update',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'cost-register.approve' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'cost-register.cancel' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.cancel',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'cost-register.create' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.create',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'cost-register.destroy' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.destroy',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'cost-register.revise' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.revise',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'cost-register.store' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.store',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'cost-register.submit' => 
  [
    'module' => 'cost-register',
    'label' => 'cost-register.submit',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'daily-transaction.add-item' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.add-item',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'daily-transaction.approve' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'daily-transaction.bulk-approve' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.bulk-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'daily-transaction.bulk-reject' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.bulk-reject',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'daily-transaction.create' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.create',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'daily-transaction.destroy' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.destroy',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'daily-transaction.draft-remove-item' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.draft-remove-item',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'daily-transaction.mark-paid' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.mark-paid',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'daily-transaction.reject-batch' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.reject-batch',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'daily-transaction.reject-cr-item' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.reject-cr-item',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'daily-transaction.reject-item' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.reject-item',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'daily-transaction.store' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.store',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'daily-transaction.submit' => 
  [
    'module' => 'daily-transaction',
    'label' => 'daily-transaction.submit',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
      3 => 'treasury',
    ],
  ],
  'department.destroy' => 
  [
    'module' => 'department',
    'label' => 'department.destroy',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'department.index' => 
  [
    'module' => 'department',
    'label' => 'department.index',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'department.store' => 
  [
    'module' => 'department',
    'label' => 'department.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'department.update' => 
  [
    'module' => 'department',
    'label' => 'department.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'employee.create' => 
  [
    'module' => 'employee',
    'label' => 'employee.create',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'hr',
      2 => 'superadmin',
    ],
  ],
  'employee.destroy' => 
  [
    'module' => 'employee',
    'label' => 'employee.destroy',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'hr',
      2 => 'superadmin',
    ],
  ],
  'employee.edit' => 
  [
    'module' => 'employee',
    'label' => 'employee.edit',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'hr',
      2 => 'superadmin',
    ],
  ],
  'employee.store' => 
  [
    'module' => 'employee',
    'label' => 'employee.store',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'hr',
      2 => 'superadmin',
    ],
  ],
  'employee.update' => 
  [
    'module' => 'employee',
    'label' => 'employee.update',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'hr',
      2 => 'superadmin',
    ],
  ],
  'finance-summary-executive.index' => 
  [
    'module' => 'finance-summary-executive',
    'label' => 'finance-summary-executive.index',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'groups.create' => 
  [
    'module' => 'groups',
    'label' => 'groups.create',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'groups.destroy' => 
  [
    'module' => 'groups',
    'label' => 'groups.destroy',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'groups.edit' => 
  [
    'module' => 'groups',
    'label' => 'groups.edit',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'groups.edit-data' => 
  [
    'module' => 'groups',
    'label' => 'groups.edit-data',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'groups.index' => 
  [
    'module' => 'groups',
    'label' => 'groups.index',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'guest',
      2 => 'superadmin',
    ],
  ],
  'groups.show' => 
  [
    'module' => 'groups',
    'label' => 'groups.show',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'guest',
      2 => 'superadmin',
    ],
  ],
  'groups.store' => 
  [
    'module' => 'groups',
    'label' => 'groups.store',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'groups.update' => 
  [
    'module' => 'groups',
    'label' => 'groups.update',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.approve' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.approve',
    'seed_roles' => 
    [
      0 => 'finance_manager',
      1 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.cancel' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.cancel',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.cash-receipt.destroy' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.cash-receipt.destroy',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.cash-receipt.settle' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.cash-receipt.settle',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.cash-receipt.store' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.cash-receipt.store',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.create' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.create',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.destroy' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.destroy',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.edit' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.edit',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.import-preview' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.import-preview',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.import-template' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.import-template',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.mark-received' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.mark-received',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.revise' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.revise',
    'seed_roles' => 
    [
      0 => 'accounting',
      1 => 'approver',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.store' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.store',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.submit' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.submit',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.update' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.update',
    'seed_roles' => 
    [
      0 => 'admin',
      1 => 'collection',
      2 => 'finance',
      3 => 'finance_manager',
      4 => 'superadmin',
    ],
  ],
  'invoice-cash-receipt.verify' => 
  [
    'module' => 'invoice-cash-receipt',
    'label' => 'invoice-cash-receipt.verify',
    'seed_roles' => 
    [
      0 => 'accounting',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'project.activate' => 
  [
    'module' => 'project',
    'label' => 'project.activate',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'head',
      3 => 'superadmin',
    ],
  ],
  'project.activate-form' => 
  [
    'module' => 'project',
    'label' => 'project.activate-form',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'head',
      3 => 'superadmin',
    ],
  ],
  'project.activate-from-waiting' => 
  [
    'module' => 'project',
    'label' => 'project.activate-from-waiting',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'head',
      3 => 'superadmin',
    ],
  ],
  'project.approve' => 
  [
    'module' => 'project',
    'label' => 'project.approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'project.budget-import-preview' => 
  [
    'module' => 'project',
    'label' => 'project.budget-import-preview',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.budget-import-template' => 
  [
    'module' => 'project',
    'label' => 'project.budget-import-template',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.bulk-approve' => 
  [
    'module' => 'project',
    'label' => 'project.bulk-approve',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'project.bulk-revise' => 
  [
    'module' => 'project',
    'label' => 'project.bulk-revise',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'project.create' => 
  [
    'module' => 'project',
    'label' => 'project.create',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.destroy' => 
  [
    'module' => 'project',
    'label' => 'project.destroy',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.edit' => 
  [
    'module' => 'project',
    'label' => 'project.edit',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'project.edit-po-numbers' => 
  [
    'module' => 'project',
    'label' => 'project.edit-po-numbers',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'project.export-excel' => 
  [
    'module' => 'project',
    'label' => 'project.export-excel',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'superadmin',
    ],
  ],
  'project.finance-close' => 
  [
    'module' => 'project',
    'label' => 'project.finance-close',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'project.finance-revise' => 
  [
    'module' => 'project',
    'label' => 'project.finance-revise',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'superadmin',
    ],
  ],
  'project.import-preview' => 
  [
    'module' => 'project',
    'label' => 'project.import-preview',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.import-template' => 
  [
    'module' => 'project',
    'label' => 'project.import-template',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.po-bast.destroy' => 
  [
    'module' => 'project',
    'label' => 'project.po-bast.destroy',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'project.po-number.complete-bast' => 
  [
    'module' => 'project',
    'label' => 'project.po-number.complete-bast',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'head',
      3 => 'superadmin',
    ],
  ],
  'project.po-number.destroy' => 
  [
    'module' => 'project',
    'label' => 'project.po-number.destroy',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'head',
      3 => 'superadmin',
    ],
  ],
  'project.po-number.import-preview' => 
  [
    'module' => 'project',
    'label' => 'project.po-number.import-preview',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'project.po-number.import-template' => 
  [
    'module' => 'project',
    'label' => 'project.po-number.import-template',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'project.po-number.status' => 
  [
    'module' => 'project',
    'label' => 'project.po-number.status',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'head',
      3 => 'superadmin',
    ],
  ],
  'project.reject-closing' => 
  [
    'module' => 'project',
    'label' => 'project.reject-closing',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'project.store' => 
  [
    'module' => 'project',
    'label' => 'project.store',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.submit' => 
  [
    'module' => 'project',
    'label' => 'project.submit',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.submit-bast' => 
  [
    'module' => 'project',
    'label' => 'project.submit-bast',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.submit-bast.store' => 
  [
    'module' => 'project',
    'label' => 'project.submit-bast.store',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.submit-closing' => 
  [
    'module' => 'project',
    'label' => 'project.submit-closing',
    'seed_roles' => 
    [
      0 => 'head',
      1 => 'superadmin',
    ],
  ],
  'project.update' => 
  [
    'module' => 'project',
    'label' => 'project.update',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'project.update-po-numbers' => 
  [
    'module' => 'project',
    'label' => 'project.update-po-numbers',
    'seed_roles' => 
    [
      0 => 'approver',
      1 => 'finance',
      2 => 'finance_manager',
      3 => 'head',
      4 => 'superadmin',
    ],
  ],
  'project.verify' => 
  [
    'module' => 'project',
    'label' => 'project.verify',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'project.verify-closing' => 
  [
    'module' => 'project',
    'label' => 'project.verify-closing',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'role.edit' => 
  [
    'module' => 'role',
    'label' => 'role.edit',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'role.edit-data' => 
  [
    'module' => 'role',
    'label' => 'role.edit-data',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'role.index' => 
  [
    'module' => 'role',
    'label' => 'role.index',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'role.store' => 
  [
    'module' => 'role',
    'label' => 'role.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'role.update' => 
  [
    'module' => 'role',
    'label' => 'role.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'security.index' => 
  [
    'module' => 'security',
    'label' => 'security.index',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'security.sessions.revoke' => 
  [
    'module' => 'security',
    'label' => 'security.sessions.revoke',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'security.users.logout-all' => 
  [
    'module' => 'security',
    'label' => 'security.users.logout-all',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'source-fund.destroy' => 
  [
    'module' => 'source-fund',
    'label' => 'source-fund.destroy',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'source-fund.edit-data' => 
  [
    'module' => 'source-fund',
    'label' => 'source-fund.edit-data',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'source-fund.store' => 
  [
    'module' => 'source-fund',
    'label' => 'source-fund.store',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'source-fund.update' => 
  [
    'module' => 'source-fund',
    'label' => 'source-fund.update',
    'seed_roles' => 
    [
      0 => 'finance',
      1 => 'finance_manager',
      2 => 'superadmin',
    ],
  ],
  'team.create' => 
  [
    'module' => 'team',
    'label' => 'team.create',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'team.status' => 
  [
    'module' => 'team',
    'label' => 'team.status',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'team.store' => 
  [
    'module' => 'team',
    'label' => 'team.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.rca.store' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.rca.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.rca.toggle' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.rca.toggle',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.rca.update' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.rca.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.severity.store' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.severity.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.severity.toggle' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.severity.toggle',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.severity.update' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.severity.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.sla.limit' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.sla.limit',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.sub-rca.store' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.sub-rca.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.sub-rca.toggle' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.sub-rca.toggle',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'trouble-ticket.settings.sub-rca.update' => 
  [
    'module' => 'trouble-ticket',
    'label' => 'trouble-ticket.settings.sub-rca.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'user.create' => 
  [
    'module' => 'user',
    'label' => 'user.create',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'user.destroy' => 
  [
    'module' => 'user',
    'label' => 'user.destroy',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'user.edit' => 
  [
    'module' => 'user',
    'label' => 'user.edit',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'user.store' => 
  [
    'module' => 'user',
    'label' => 'user.store',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
  'user.update' => 
  [
    'module' => 'user',
    'label' => 'user.update',
    'seed_roles' => 
    [
      0 => 'superadmin',
    ],
  ],
],

];
