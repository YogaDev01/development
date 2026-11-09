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
        'dashboard' => [
            'label' => 'Dashboard',
            'icon'  => 'fas fa-tachometer-alt',
            'url'   => 'app-home',      // mengikuti aplikasi yang sedang dibuka
            'active' => ['app-home'],
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
        'budgeting' => [
            'label'  => 'Budgeting',
            'icon'   => 'fas fa-chart-line',
            'url'    => '/budget-plan?from=finance',
            'active' => ['budget-plan*'],
            'menu'   => 'finance-budgeting',
            'scope'  => 'fm',
        ],
        'cost-register' => [
            'label'  => 'Cost Register',
            'icon'   => 'fas fa-book',
            'url'    => '/cost-register',
            'active' => ['cost-register*'],
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

        /* ------------------------------------------------- MASTER DATA */
        'customer' => [
            'label'  => 'Customer',
            'icon'   => 'fas fa-user-tie',
            'url'    => '/customer',
            'active' => ['customer*'],
            'menu'   => 'customer',
        ],
        'beneficiary' => [
            'label'  => 'Beneficiary',
            'icon'   => 'fas fa-hand-holding-usd',
            'url'    => '/beneficiaries',
            'active' => ['beneficiaries*'],
            'menu'   => 'beneficiaries',
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
            'active' => ['account*'],
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
        'pic' => [
            'label'  => 'PIC',
            'icon'   => 'fas fa-user-tag',
            'url'    => '/master-pic',
            'active' => ['master-pic*'],
            'menu'   => 'master-pic',
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
            'items' => ['beneficiary', 'employee', 'pic', 'team', 'user-access'],
        ],
        'Operation Master' => [
            'icon'  => 'fas fa-cogs',
            'items' => ['homebase', 'network', 'region', 'site', 'wilayah', 'trouble-ticket-rules'],
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
            'Overview'    => ['dashboard', 'finance-summary'],
            'Workflow'    => [
                'purchase-order', 'budgeting',
                'Cost Management' => [
                    'icon'  => 'fas fa-coins',
                    'items' => ['cost-request', 'cost-register', 'treasury'],
                ],
                'Billing Management' => [
                    'icon'  => 'fas fa-file-invoice',
                    'items' => ['delivery-note', 'invoice', 'cash-receipt'],
                ],
                'Cash Management' => [
                    'icon'  => 'fas fa-money-bill-wave',
                    'items' => ['cash-management', 'forecast'],
                ],
                'Reports' => [
                    'icon'  => 'fas fa-chart-pie',
                    'items' => ['closing', 'reports'],
                ],
            ],
        ],

        'hr' => [
            'Overview'    => ['dashboard'],
            'Workflow'    => ['payroll-run'],
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
            'Workflow'    => ['project-monitoring', 'trouble-ticket'],
        ],

        'project' => [
            'Overview'    => ['dashboard', 'project-summary'],
            'Workflow'    => ['project-monitoring'],
        ],
    ],
];
