<?php

/*
|--------------------------------------------------------------------------
| Peta menu TESYS
|--------------------------------------------------------------------------
| Satu berkas untuk menjawab dua pertanyaan:
|
|   'items'  — setiap menu yang ada: namanya, ikonnya, tujuannya, kapan ia
|              dianggap sedang dibuka, dan hak akses apa yang dibutuhkan.
|   'master_data' — daftar Master Data bersama, sama di semua aplikasi.
|   'apps'   — aplikasi mana menampilkan menu apa, dalam seksi apa.
|
| Berkas ini HANYA menentukan tampilan navigasi. Kewenangan tetap dijaga
| middleware pada routes/web.php seperti sebelumnya; 'menu'/'roles' di sini
| memakai pemeriksaan yang sama persis (Role::hasMenu / User::hasRole) supaya
| menu tidak pernah menawarkan halaman yang berujung 403.
|
| Menambahkan sebuah modul ke aplikasi lain = menambahkan kuncinya di daftar
| 'apps'. Satu menu boleh muncul di beberapa aplikasi (mis. Customer dipakai
| Finance, Logistic, Operation, dan Project) — tidak ada route baru, halaman
| yang dibuka tetap satu.
|
| Urutan seksi: Todo List (tanpa label) -> Master Data -> lalu seksi 'apps'
| sesuai urutan penulisan di bawah (mis. pada 'finance': Overview -> Workflow).
| Todo List & Master Data TIDAK ditulis di 'apps' — keduanya disisipkan
| otomatis oleh Navigation::sections() untuk SEMUA aplikasi.
|
| Dalam satu seksi, nilai 'apps.<app>.<seksi>' boleh campur: entri berindeks
| angka = kunci item navigasi langsung (seperti sebelumnya), entri berkunci
| string = dropdown — nilainya ['icon' => kelas FA, 'items' => daftar kunci
| item di dalamnya]. Lihat 'finance.Workflow' di bawah untuk contohnya. Nama
| grup (kunci string) HANYA label tampilan, dibaca nav.blade.php — bukan
| kunci hak akses.
|
| Seksi 'General' sudah tidak ada: isinya (Panduan & General Feedback) pindah
| ke dropdown user di pojok kanan atas.
|
| Keterangan kunci pada 'items':
|   label   : teks menu
|   icon    : kelas Font Awesome 5 (project ini memuat FA 5.15.4, bukan FA6)
|   url     : tujuan; boleh 'route:nama.route' untuk memakai nama route
|   active  : pola request()->is() yang menandai menu sedang dibuka
|   menu    : kunci hak akses menu (Role::hasMenu)
|   roles   : daftar role yang boleh melihat (dipakai bila tidak ada kunci menu)
|   scope   : penanda tambahan untuk menu yang halamannya dipakai bersama
|             beberapa konteks — lihat layouts/app.blade.php
|             'fm' = tampilan Finance (bukan dari Surat Jalan)
|             'po' = halaman /project, dibedakan dari /project-monitoring
*/

return [

    'items' => [

        /* ---------------------------------------------------- OVERVIEW */
        // Beranda = pintu ke Profil Saya (alamat '/' juga menampilkan profil).
        // Tanpa 'menu': terlihat untuk semua user login.
        'beranda' => [
            'label'  => 'Beranda',
            'icon'   => 'fas fa-home',
            'url'    => 'route:profile.show',
            'active' => ['/', 'profile*'],
        ],

        // Dashboard = halaman tersendiri (dashboard.index), bukan beranda.
        'dashboard' => [
            'label'  => 'Dashboard',
            'icon'   => 'fas fa-tachometer-alt',
            'url'    => '/dashboard',
            'active' => ['dashboard'],
        ],

        'project-summary' => [
            'label'  => 'Project Summary',
            'icon'   => 'fas fa-chart-area',
            'url'    => '/service-delivery-summary',
            'active' => ['service-delivery-summary*'],
            'menu'   => 'project-summary',
        ],

        /* ---------------------------------------------------- WORKFLOW */
        'todo-list' => [
            'label'  => 'Todo List',
            'icon'   => 'fas fa-tasks',
            'url'    => '/todo-list',
            'active' => ['todo-list*'],
            'menu'   => 'todo-list',
        ],
        // Tanpa 'menu'/'roles': terlihat untuk semua user login (keputusan
        // bisnis "semua user -> siapa saja"), seperti 'dashboard'. Sudah
        // tidak dirujuk dari 'apps' manapun (dicabut dari sidebar) — pintu
        // masuknya sekarang dari dalam halaman Todo List. Definisinya
        // dipertahankan di sini karena route-nya masih aktif.
        'assignment' => [
            'label'  => 'Assignment',
            'icon'   => 'fas fa-user-check',
            'url'    => '/assignment',
            'active' => ['assignment*'],
        ],
        'cost-request' => [
            'label'  => 'Cost Request',
            'icon'   => 'fas fa-file-invoice-dollar',
            'url'    => '/budget-request?from=finance',
            'active' => ['budget-request*'],
            'menu'   => 'finance-cost-request',
            'scope'  => 'fm',
        ],
        'invoice' => [
            // Nama modulnya di menu adalah "Collection"; dokumennya tetap
            // disebut Invoice di halaman detailnya.
            'label'  => 'Collection',
            'icon'   => 'fas fa-receipt',
            'url'    => '/invoice-cash-receipt',
            'active' => ['invoice-cash-receipt*'],
            'menu'   => 'invoice-cash-receipt',
        ],
        'delivery-note' => [
            'label'  => 'Delivery Note',
            'icon'   => 'fas fa-truck',
            'url'    => '/delivery-note',
            'active' => ['delivery-note*'],
            // Kunci sendiri (dulu menumpang 'invoice-cash-receipt'), supaya bisa
            // dicabut terpisah dari Invoice & Cash Receipt di Role master.
            'menu'   => 'delivery-note',
        ],
        'treasury' => [
            'label'  => 'Treasury',
            'icon'   => 'fas fa-wallet',
            'url'    => '/daily-transaction',
            'active' => ['daily-transaction*'],
            'menu'   => 'finance-daily-transaction',
        ],
        /*
         | Cash Management & Cash Receipt (M-01, M-03).
         |
         | 'menu' harus sama dengan key di tabel menu_items DAN dengan role
         | yang diizinkan di routes/web.php — tiga tempat itu berpasangan.
         | Kalau salah satu tertinggal, gejalanya membingungkan: menunya
         | muncul tapi diklik 403, atau route-nya jalan tapi menunya tidak
         | pernah kelihatan.
         */
        'cash-management' => [
            'label'  => 'Cashflow',
            'icon'   => 'fas fa-wallet',
            'url'    => '/cash-management',
            'active' => ['cash-management*'],
            'menu'   => 'cash-management',
        ],
        // Cash & Bank (Fase 1): pintu masuk baru, kunci menu sendiri.
        'cash-overview' => [
            'label'  => 'Cash Overview',
            'icon'   => 'fas fa-university',
            'url'    => '/cash-overview',
            'active' => ['cash-overview*'],
            'menu'   => 'cash-overview',
        ],
        'cash-transactions' => [
            'label'  => 'Cash Transactions',
            'icon'   => 'fas fa-list-alt',
            'url'    => '/cash-transactions',
            'active' => ['cash-transactions*'],
            'menu'   => 'cash-transactions',
        ],
        'cash-transfers' => [
            'label'  => 'Internal Transfer',
            'icon'   => 'fas fa-random',
            'url'    => '/cash-transfers',
            'active' => ['cash-transfers*'],
            'menu'   => 'cash-transfers',
        ],
        'cash-adjustments' => [
            'label'  => 'Cash Adjustment',
            'icon'   => 'fas fa-sliders-h',
            'url'    => '/cash-adjustments',
            'active' => ['cash-adjustments*'],
            'menu'   => 'cash-adjustments',
        ],
        'cash-reports' => [
            'label'  => 'Cash Reports',
            'icon'   => 'fas fa-file-alt',
            'url'    => '/cash-reports',
            'active' => ['cash-reports*'],
            'menu'   => 'cash-reports',
        ],
        'cash-reconciliation' => [
            'label'  => 'Reconciliation',
            'icon'   => 'fas fa-balance-scale-left',
            'url'    => '/cash-reconciliation',
            'active' => ['cash-reconciliation*'],
            'menu'   => 'cash-reconciliation',
        ],
        'bank-accounts' => [
            'label'  => 'Bank Accounts',
            'icon'   => 'fas fa-landmark',
            'url'    => '/bank-accounts',
            'active' => ['bank-accounts*'],
            'menu'   => 'bank-accounts',
        ],
        'cash-receipt' => [
            'label'  => 'Cash Receipt',
            'icon'   => 'fas fa-hand-holding-usd',
            'url'    => '/cash-receipt',
            'active' => ['cash-receipt*'],
            'menu'   => 'cash-receipt',
        ],
        'closing' => [
            'label'  => 'Closing',
            'icon'   => 'fas fa-calendar-check',
            'url'    => '/closing',
            'active' => ['closing*'],
            'menu'   => 'closing',
        ],
        'purchase-order' => [
            'label'  => 'Purchase Order',
            'icon'   => 'fas fa-file-contract',
            'url'    => '/project',
            'active' => [],             // ditentukan scope 'po'
            'menu'   => 'list-po',
            'scope'  => 'po',
        ],
        'payroll-run' => [
            'label'  => 'Payroll Run',
            'icon'   => 'fas fa-money-check-alt',
            'url'    => '/payroll-run',
            'active' => ['payroll-run*'],
            'menu'   => 'payroll-run',
        ],
        'project-monitoring' => [
            'label'  => 'Project Monitoring',
            'icon'   => 'fas fa-satellite-dish',
            'url'    => '/project-monitoring',
            'active' => ['project-monitoring*'],
            'menu'   => 'project-monitoring',
        ],
        'trouble-ticket' => [
            'label'  => 'Trouble Ticket',
            'icon'   => 'fas fa-ticket-alt',
            'url'    => '/trouble-ticket',
            'active' => ['trouble-ticket*'],
            'menu'   => 'trouble-ticket',
        ],
        'site-sla' => [
            'label'  => 'SLA Site',
            'icon'   => 'fas fa-signal',
            'url'    => '/site-sla',
            'active' => ['site-sla*'],
            'menu'   => 'site-sla',
        ],
        // Kebijakan SLA & master RCA/Sub-RCA/Severity Trouble Ticket. Halamannya
        // tetap di route trouble-ticket.settings (gerbang menu:trouble-ticket +
        // role:superadmin); di sidebar ia berdiri sebagai Master Data.
        'trouble-ticket-rules' => [
            'label'  => 'Custom Rules',
            'icon'   => 'fas fa-sliders-h',
            'url'    => 'route:trouble-ticket.settings',
            'active' => ['trouble-ticket/settings*'],
            // Dijaga 'roles' (bukan 'menu') agar sama persis dengan route
            // settings: role:superadmin — AccessRoles::TROUBLE_TICKET_SETTINGS.
            'roles'  => ['superadmin'],
        ],

        /* ---------------------------------------------------- ANALYSIS */
        'finance-summary' => [
            'label'  => 'Summary',
            'icon'   => 'fas fa-chart-pie',
            'url'    => 'route:finance-summary.index',
            'active' => ['finance-summary*'],
            'menu'   => 'finance-summary',
        ],
        // Finance Summary dibagi jadi tiga pintu menu. Halamannya tetap satu
        // (finance-summary.index); 'tab' memilih tab yang terbuka & penanda
        // aktif di sidebar. 'only_roles' = role yang melihat tab itu (sama
        // dengan EXECUTIVE_ROLES / COLLECTION_ROLES di FinanceSummaryController)
        // DI ATAS hak menu 'finance-summary'.
        'summary-executive' => [
            'label'       => 'Summary Executive',
            'icon'        => 'fas fa-chart-pie',
            'url'         => '/finance-summary?tab=executive',
            'active'      => ['finance-summary*'],
            'tab'         => 'executive',
            'tab_default' => true,
            'menu'        => 'finance-summary',
            'only_roles'  => ['superadmin', 'finance', 'finance_manager', 'approver', 'guest'],
        ],
        'summary-collection' => [
            'label'      => 'Summary Collection',
            'icon'       => 'fas fa-file-invoice-dollar',
            'url'        => '/finance-summary?tab=collection',
            'active'     => ['finance-summary*'],
            'tab'        => 'collection',
            'menu'       => 'finance-summary',
            'only_roles' => ['superadmin', 'finance', 'finance_manager', 'approver', 'collection', 'guest'],
        ],
        'summary-budget' => [
            'label'  => 'Summary Budget',
            'icon'   => 'fas fa-wallet',
            'url'    => '/finance-summary?tab=budget',
            'active' => ['finance-summary*'],
            'tab'    => 'budget',
            'menu'   => 'finance-summary',
        ],
        'budgeting' => [
            'label'  => 'Budgeting',
            'icon'   => 'fas fa-chart-line',
            'url'    => '/budget-plan?from=finance',
            'active' => ['budget-plan*'],
            'menu'   => 'finance-budgeting',
            'scope'  => 'fm',
        ],
        'cost-register' => [
            'label'  => 'Expense',
            'icon'   => 'fas fa-book',
            'url'    => '/expense',
            'active' => ['expense*', 'cost-register*'],
            // Dulu 'roles' tertulis (tidak tampil di modal Menu Access dan
            // tanpa approver, padahal route mengizinkan). Sejak 2026-11-09
            // diatur lewat Menu Access, sama dengan route menu:cost-register.
            'menu'   => 'cost-register',
        ],
        'forecast' => [
            'label'  => 'Forecast',
            'icon'   => 'fas fa-binoculars',
            'url'    => '/forecast',
            'active' => ['forecast*'],
            'menu'   => 'forecast',
        ],
        'reports' => [
            'label'  => 'Project Report',
            'icon'   => 'fas fa-chart-bar',
            'url'    => '/reports',
            'active' => ['reports*'],
            'menu'   => 'reports',
        ],

        /* -------------------------------------------- ASSET MANAGEMENT */
        // Semua halaman Asset memakai satu kunci menu 'asset-management'
        // (route menu:asset-management); modal Menu Access cukup satu centang.
        // Halaman ringkasan app Projects (satu hak menu: projects-overview).
        'projects-todo' => [
            'label'  => 'Todo List',
            'icon'   => 'fas fa-tasks',
            'url'    => 'route:projects.todo',
            'active' => ['projects/todo'],
            'menu'   => 'projects-overview',
        ],
        'projects-dashboard' => [
            'label'  => 'Dashboard',
            'icon'   => 'fas fa-tachometer-alt',
            'url'    => 'route:projects.dashboard',
            'active' => ['projects/dashboard'],
            'menu'   => 'projects-overview',
        ],
        'projects-summary' => [
            'label'  => 'Summary',
            'icon'   => 'fas fa-chart-pie',
            'url'    => 'route:projects.summary',
            'active' => ['projects/summary'],
            'menu'   => 'projects-overview',
        ],
        // Halaman ringkasan app Operations (satu hak menu: operations-overview).
        'operations-todo' => [
            'label'  => 'Todo List',
            'icon'   => 'fas fa-tasks',
            'url'    => 'route:operations.todo',
            'active' => ['operations/todo'],
            'menu'   => 'operations-overview',
        ],
        'operations-dashboard' => [
            'label'  => 'Dashboard',
            'icon'   => 'fas fa-tachometer-alt',
            'url'    => 'route:operations.dashboard',
            'active' => ['operations/dashboard'],
            'menu'   => 'operations-overview',
        ],
        'operations-summary' => [
            'label'  => 'Summary',
            'icon'   => 'fas fa-chart-pie',
            'url'    => 'route:operations.summary',
            'active' => ['operations/summary'],
            'menu'   => 'operations-overview',
        ],
        // Halaman ringkasan app HRIS (satu hak menu: hris-overview).
        'hris-todo' => [
            'label'  => 'Todo List',
            'icon'   => 'fas fa-tasks',
            'url'    => 'route:hris.todo',
            'active' => ['hris/todo'],
            'menu'   => 'hris-overview',
        ],
        'hris-dashboard' => [
            'label'  => 'Dashboard',
            'icon'   => 'fas fa-tachometer-alt',
            'url'    => 'route:hris.dashboard',
            'active' => ['hris/dashboard'],
            'menu'   => 'hris-overview',
        ],
        'hris-summary' => [
            'label'  => 'Summary',
            'icon'   => 'fas fa-chart-pie',
            'url'    => 'route:hris.summary',
            'active' => ['hris/summary'],
            'menu'   => 'hris-overview',
        ],
        'recruitment' => [
            'label'  => 'Recruitment',
            'icon'   => 'fas fa-people-arrows',
            'url'    => '/recruitment',
            'active' => ['recruitment*'],
            'menu'   => 'recruitment',
        ],
        'candidate-pool' => [
            'label'  => 'Talentpool',
            'icon'   => 'fas fa-address-book',
            'url'    => '/candidates',
            'active' => ['candidates*'],
            'menu'   => 'candidate-pool',
        ],
        'manpower-request' => [
            'label'  => 'Manpower Request',
            'icon'   => 'fas fa-user-plus',
            'url'    => '/manpower',
            'active' => ['manpower*'],
            'menu'   => 'manpower-request',
        ],
        'letter-management' => [
            'label'  => 'Letter Management',
            'icon'   => 'fas fa-envelope-open-text',
            'url'    => '/letter',
            'active' => ['letter*'],
            'menu'   => 'letter-management',
        ],
        /* ----------------------------------------------------- ACCOUNTING */
        // Fase 1. Tiap menu punya kunci hak sendiri (menu:accounting-*), sama
        // dengan route di routes/web.php dan migrasi 2026_11_14_000200.
        'accounting-journal' => [
            'label'  => 'Journal Entries',
            'icon'   => 'fas fa-book-open',
            'url'    => 'route:accounting.journal.index',
            'active' => ['accounting/journal*'],
            'menu'   => 'accounting-journal',
        ],
        'accounting-ledger' => [
            'label'  => 'General Ledger',
            'icon'   => 'fas fa-book',
            'url'    => 'route:accounting.ledger.index',
            'active' => ['accounting/ledger*'],
            'menu'   => 'accounting-ledger',
        ],
        'accounting-trial-balance' => [
            'label'  => 'Trial Balance',
            'icon'   => 'fas fa-balance-scale',
            'url'    => 'route:accounting.trial-balance.index',
            'active' => ['accounting/trial-balance*'],
            'menu'   => 'accounting-trial-balance',
        ],
        'accounting-income-statement' => [
            'label'  => 'Income Statement',
            'icon'   => 'fas fa-chart-line',
            'url'    => 'route:accounting.statement.income',
            'active' => ['accounting/statements/income*'],
            'menu'   => 'accounting-income-statement',
        ],
        'accounting-balance-sheet' => [
            'label'  => 'Balance Sheet',
            'icon'   => 'fas fa-balance-scale-right',
            'url'    => 'route:accounting.statement.balance',
            'active' => ['accounting/statements/balance*'],
            'menu'   => 'accounting-balance-sheet',
        ],
        'accounting-cash-flow' => [
            'label'  => 'Cash Flow Statement',
            'icon'   => 'fas fa-water',
            'url'    => 'route:accounting.statement.cash',
            'active' => ['accounting/statements/cash*'],
            'menu'   => 'accounting-cash-flow',
        ],
        'accounting-equity' => [
            'label'  => 'Changes in Equity',
            'icon'   => 'fas fa-layer-group',
            'url'    => 'route:accounting.statement.equity',
            'active' => ['accounting/statements/equity*'],
            'menu'   => 'accounting-equity',
        ],
        'accounting-audit' => [
            'label'  => 'Journal Audit Trail',
            'icon'   => 'fas fa-history',
            'url'    => 'route:accounting.audit.index',
            'active' => ['accounting/audit*'],
            'menu'   => 'accounting-audit',
        ],
        'accounting-coa' => [
            'label'  => 'Chart of Accounts',
            'icon'   => 'fas fa-sitemap',
            'url'    => 'route:accounting.coa.index',
            'active' => ['accounting/coa*'],
            'menu'   => 'accounting-coa',
        ],
        'accounting-mapping' => [
            'label'  => 'Account Mapping',
            'icon'   => 'fas fa-link',
            'url'    => 'route:accounting.mapping.index',
            'active' => ['accounting/mapping*'],
            'menu'   => 'accounting-mapping',
        ],
        'accounting-period' => [
            'label'  => 'Accounting Period',
            'icon'   => 'fas fa-calendar-alt',
            'url'    => 'route:accounting.period.index',
            'active' => ['accounting/period*'],
            'menu'   => 'accounting-period',
        ],
        'asset-dashboard' => [
            'label'  => 'Summary Asset',
            'icon'   => 'fas fa-tachometer-alt',
            'url'    => 'route:asset.dashboard',
            'active' => ['asset'],
            'menu'   => 'asset-management',
        ],
        'asset-list' => [
            'label'  => 'All Assets',
            'icon'   => 'fas fa-boxes',
            'url'    => 'route:asset.index',
            'active' => ['asset/list*', 'asset/create'],
            'menu'   => 'asset-management',
        ],
        'asset-movement' => [
            'label'  => 'Serah Terima & Transfer',
            'icon'   => 'fas fa-exchange-alt',
            'url'    => 'route:asset.movements.index',
            'active' => ['asset/movements*'],
            'menu'   => 'asset-management',
        ],
        'asset-maintenance' => [
            'label'  => 'Maintenance',
            'icon'   => 'fas fa-tools',
            'url'    => 'route:asset.maintenance.index',
            'active' => ['asset/maintenance*'],
            'menu'   => 'asset-management',
        ],
        'asset-inspection' => [
            'label'  => 'Inspeksi',
            'icon'   => 'fas fa-clipboard-check',
            'url'    => 'route:asset.inspection.index',
            'active' => ['asset/inspection*'],
            'menu'   => 'asset-management',
        ],
        'asset-opname' => [
            'label'  => 'Stock Opname',
            'icon'   => 'fas fa-tasks',
            'url'    => 'route:asset.opname.index',
            'active' => ['asset/opname*'],
            'menu'   => 'asset-management',
        ],
        'asset-procurement' => [
            'label'  => 'Pengadaan',
            'icon'   => 'fas fa-shopping-cart',
            'url'    => 'route:asset.procurement.index',
            'active' => ['asset/procurement*'],
            'menu'   => 'asset-management',
        ],
        'asset-report' => [
            'label'  => 'Laporan Aset',
            'icon'   => 'fas fa-file-alt',
            'url'    => 'route:asset.reports',
            'active' => ['asset/reports*'],
            'menu'   => 'asset-management',
        ],
        'asset-settings' => [
            'label'  => 'Pengaturan Aset',
            'icon'   => 'fas fa-sliders-h',
            'url'    => 'route:asset.settings.index',
            'active' => ['asset/settings*'],
            'menu'   => 'asset-management',
        ],

        /* ------------------------------------------------- MASTER DATA */
        'customer' => [
            'label'  => 'Customer',
            'icon'   => 'fas fa-user-tie',
            'url'    => '/customer',
            'active' => ['customer*'],
            'menu'   => 'customer',
        ],
        // Gabungan Beneficiary & PIC (2026-11-09): PIC sebagai induk,
        // beneficiary sebagai detailnya. URL lama /beneficiaries & /master-pic
        // dialihkan ke sini.
        'pic-beneficiary' => [
            'label'  => 'PIC & Beneficiary',
            'icon'   => 'fas fa-user-tag',
            'url'    => '/pic-beneficiary',
            'active' => ['pic-beneficiary*', 'beneficiaries*', 'master-pic*'],
            'menu'   => 'pic-beneficiary',
        ],
        'source-fund' => [
            'label'  => 'Source Funds',
            'icon'   => 'fas fa-coins',
            'url'    => '/source-fund',
            'active' => ['source-fund*'],
            'menu'   => 'source-fund',
        ],
        'cost-center' => [
            'label'  => 'Cost Center',
            'icon'   => 'fas fa-sitemap',
            'url'    => '/cost-center',
            'active' => ['cost-center*'],
            'menu'   => 'cost-center',
        ],
        'account' => [
            'label'  => 'Account',
            'icon'   => 'fas fa-landmark',
            'url'    => '/account',
            'active' => ['account', 'account/*'],
            'menu'   => 'account',
        ],
        'project-master' => [
            'label'  => 'Project',
            'icon'   => 'fas fa-project-diagram',
            'url'    => '/cluster',
            'active' => ['cluster*'],
            'menu'   => 'project',
        ],
        'category-item' => [
            'label'  => 'Category Item',
            'icon'   => 'fas fa-tags',
            'url'    => '/category-item',
            'active' => ['category-item*'],
            'menu'   => 'category-item',
        ],
        'master-material' => [
            'label'  => 'Material',
            'icon'   => 'fas fa-microchip',
            'url'    => '/master-material',
            'active' => ['master-material*'],
            'menu'   => 'master-material',
        ],
        'accessories' => [
            'label'  => 'Accessories',
            'icon'   => 'fas fa-plug',
            'url'    => '/accessories',
            'active' => ['accessories*'],
            'menu'   => 'accessories',
        ],
        'site' => [
            'label'  => 'Sites',
            'icon'   => 'fas fa-broadcast-tower',
            'url'    => '/site',
            'active' => ['site', 'site/*'],
            'menu'   => 'site',
        ],
        'site-type' => [
            'label'  => 'Tipe Site',
            'icon'   => 'fas fa-building',
            'url'    => '/site-type',
            'active' => ['site-type*'],
            'menu'   => 'site-type',
        ],
        'site-source-power' => [
            'label'  => 'Source Power',
            'icon'   => 'fas fa-bolt',
            'url'    => '/site-source-power',
            'active' => ['site-source-power*'],
            'menu'   => 'site-source-power',
        ],
        'site-program' => [
            'label'  => 'Program Site',
            'icon'   => 'fas fa-clipboard-list',
            'url'    => '/site-program',
            'active' => ['site-program*'],
            'menu'   => 'site-program',
        ],
        'network' => [
            'label'  => 'Network',
            'icon'   => 'fas fa-satellite-dish',
            'url'    => '/network',
            'active' => ['network*'],
            'menu'   => 'network',
        ],
        'homebase' => [
            'label'  => 'Homebase',
            'icon'   => 'fas fa-warehouse',
            'url'    => '/homebase',
            'active' => ['homebase*'],
            'menu'   => 'homebase',
        ],
        'region' => [
            'label'  => 'Region',
            'icon'   => 'fas fa-globe-asia',
            'url'    => '/region',
            'active' => ['region*'],
            'menu'   => 'region',
        ],
        'wilayah' => [
            'label'  => 'Wilayah',
            'icon'   => 'fas fa-map-marked-alt',
            'url'    => '/wilayah/pulau',
            'active' => ['wilayah*'],
            'menu'   => 'wilayah',
        ],
        'team' => [
            'label'  => 'Team',
            'icon'   => 'fas fa-user-friends',
            'url'    => '/team',
            'active' => ['team*', 'department*'],
            'menu'   => 'team',
        ],
        'milestone' => [
            'label'  => 'Milestone',
            'icon'   => 'fas fa-flag-checkered',
            'url'    => '/stage',
            'active' => ['stage*'],
            'menu'   => 'milestone',
        ],
        'employee' => [
            'label'  => 'Employee',
            'icon'   => 'fas fa-id-card',
            'url'    => '/employee',
            'active' => ['employee*'],
            'menu'   => 'employee',
        ],
        'user-access' => [
            'label'  => 'User & Access',
            'icon'   => 'fas fa-users-cog',
            'url'    => '/user',
            'active' => ['user', 'user/*', 'accessgroup*', 'role*', 'security*'],
            'menu'   => 'user-access',
        ],

        /* ----------------------------------------------------- GENERAL */
        'feedback-inbox' => [
            'label'  => 'Feedback Inbox',
            'icon'   => 'fas fa-inbox',
            'url'    => '/feedback-inbox',
            'active' => ['feedback-inbox*'],
            'menu'   => 'feedback-inbox',
        ],
        /*
         | 'panduan' & 'feedback' TIDAK lagi masuk daftar 'apps' — keduanya
         | dipindah ke dropdown user (pojok kanan atas), karena keduanya
         | bantuan akun, bukan navigasi modul, dan mengulanginya di enam sidebar
         | membuat tiap sidebar berakhir dengan dua baris yang sama.
         | Definisinya tetap di sini supaya label/ikon/tujuannya tetap satu
         | sumber — lihat layouts/app.blade.php yang membacanya.
         */
        'panduan' => [
            'label'  => 'Panduan',
            'icon'   => 'fas fa-question-circle',
            'url'    => 'route:panduan.index',
            'active' => ['panduan*'],
        ],
        'feedback' => [
            'label'  => 'General Feedback',
            'icon'   => 'fas fa-comment-dots',
            'url'    => '/feedback',
            'active' => ['feedback', 'feedback/*'],
        ],
    ],

    /*
    | MASTER DATA — satu daftar untuk SEMUA aplikasi.
    |
    | Master data adalah milik bersama (Customer yang ditagih Finance adalah
    | Customer yang dikirimi barang oleh Logistics), jadi daftarnya ditulis
    | SEKALI di sini dan disisipkan otomatis ke setiap aplikasi tepat setelah
    | seksi Overview. Yang tampil untuk seorang user ditentukan menu access
    | role-nya saja: bila Customer dicentang, ia muncul di Master Data Finance,
    | Logistics, HR, dan seterusnya — sama persis di semua aplikasi.
    |
    | Karena itu JANGAN menulis 'Master Data' lagi di daftar 'apps' di bawah.
    | Lihat App\Support\Navigation::sections().
    */
    'master_data' => [
        'Project Master' => [
            'icon'  => 'fas fa-project-diagram',
            'items' => ['milestone', 'project-master'],
        ],
        'Warehouse Master' => [
            'icon'  => 'fas fa-boxes',
            'items' => ['accessories', 'category-item', 'master-material'],
        ],
        'Personal Master' => [
            'icon'  => 'fas fa-user-friends',
            'items' => ['pic-beneficiary', 'employee', 'team', 'user-access'],
        ],
        'Operation Master' => [
            'icon'  => 'fas fa-cogs',
            'items' => ['homebase', 'network', 'region', 'site', 'site-type', 'site-source-power', 'site-program', 'wilayah', 'trouble-ticket-rules'],
        ],
        'Finance Master' => [
            'icon'  => 'fas fa-coins',
            'items' => ['account', 'cost-center', 'customer', 'source-fund'],
        ],
    ],

    /*
    | Isi sidebar tiap aplikasi (tanpa Master Data — lihat 'master_data').
    | Seksi kosong (semua menunya tidak boleh dibuka user ini) otomatis tidak
    | dirender, termasuk labelnya.
    */
    'apps' => [

        /*
         | Todo List TIDAK ditulis di sini — sama seperti Master Data, ia
         | disisipkan otomatis ke SEMUA aplikasi oleh Navigation::sections(),
         | selalu paling atas (di atas Overview, bahkan di atas Master Data).
         | Satu tempat, bukan diulang di tiap 'apps.<app>'. Assignment TIDAK
         | lagi di sidebar mana pun: halamannya tetap ada (route/menu access
         | utuh), tapi pintu masuknya sekarang dari dalam halaman Todo List.
         |
         | Workflow dikelompokkan jadi dropdown per tahap perjalanan uang:
         | pengajuan & pencatatan biaya (Cost Management) -> penagihan
         | (Billing Management) -> arus & proyeksi kas (Cash Management) ->
         | penutupan & laporan (Reports). Purchase Order & Budgeting tetap di
         | luar grup karena jadi titik masuk tersendiri, bukan bagian tahapan
         | itu. Nama grup (kunci string) HANYA label tampilan, dibaca
         | nav.blade.php — bukan kunci hak akses.
         */
        'finance' => [
            // Satu daftar datar tanpa label seksi. Satu-satunya grup: Summary.
            '' => [
                'todo-list', 'dashboard',
                'Summary' => [
                    'icon'  => 'fas fa-chart-pie',
                    'items' => ['summary-executive', 'summary-collection', 'summary-budget', 'asset-dashboard'],
                ],
                'purchase-order', 'budgeting',
                'Transaction Management' => [
                    'icon'  => 'fas fa-exchange-alt',
                    'items' => ['cost-request', 'cost-register', 'delivery-note', 'invoice'],
                ],
                'Cash & Bank' => [
                    'icon'  => 'fas fa-money-bill-wave',
                    'items' => ['cash-overview', 'treasury', 'cash-receipt', 'cash-management', 'forecast', 'cash-transactions', 'cash-transfers', 'cash-adjustments', 'bank-accounts', 'cash-reconciliation', 'cash-reports'],
                ],
                'Accounting' => [
                    'icon'  => 'fas fa-balance-scale',
                    'items' => ['accounting-journal', 'accounting-ledger', 'accounting-trial-balance', 'accounting-income-statement', 'accounting-balance-sheet', 'accounting-cash-flow', 'accounting-equity', 'accounting-audit', 'accounting-coa', 'accounting-mapping', 'accounting-period'],
                ],
                'Asset Management' => [
                    'icon'  => 'fas fa-boxes',
                    'items' => [
                        'asset-list', 'asset-movement', 'asset-maintenance', 'asset-inspection',
                        'asset-opname', 'asset-procurement', 'asset-report', 'asset-settings',
                    ],
                ],
                'Reports & Closing' => [
                    'icon'  => 'fas fa-chart-bar',
                    'items' => ['closing', 'reports'],
                ],
            ],
        ],

        'hr' => [
            '' => ['hris-todo', 'hris-dashboard', 'hris-summary', 'payroll-run',
                'Recruitment Management' => [
                    'icon'  => 'fas fa-user-plus',
                    'items' => ['manpower-request', 'recruitment', 'candidate-pool'],
                ],
                'letter-management',
            ],
        ],

        'rnd' => [
            'Overview'    => ['dashboard'],
            'Workflow'    => ['feedback-inbox'],
        ],

        'logistic' => [
            'Overview'    => ['dashboard'],
        ],

        /*
         | Project Monitoring muncul di DUA aplikasi: Operations dan Project.
         | Itu disengaja, bukan salinan yang terlewat — modulnya satu, route-nya
         | satu, cuma pintu masuknya dua. Pengawasan pekerjaan lapangan adalah
         | urusan Operations sehari-hari, sementara Project memakainya dari sisi
         | perencanaan. Lihat keterangan 'apps' di kepala berkas ini.
         */
        // Tanpa Overview: muka Operations adalah Trouble Ticket sendiri
        // (config/apps.php 'landing'), jadi tidak ada halaman Dashboard
        // terpisah yang bisa ditunjuk.
        'operation' => [
            '' => ['operations-todo', 'operations-dashboard', 'operations-summary', 'project-monitoring', 'trouble-ticket', 'site-sla'],
        ],

        'project' => [
            '' => ['projects-todo', 'projects-dashboard', 'projects-summary', 'project-summary', 'project-monitoring'],
        ],
    ],
];
