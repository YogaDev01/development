<?php

/*
|--------------------------------------------------------------------------
| Panduan Penggunaan Tesys
|--------------------------------------------------------------------------
|
| Daftar topik panduan. Isi tiap topik ada di:
|   resources/views/panduan/topics/{slug}.blade.php
|
| Menambah topik baru:
|   1. Tambahkan entri di sini (di grup yang sesuai).
|   2. Buat file blade resources/views/panduan/topics/{slug}.blade.php.
|
| Field:
|   slug   -> dipakai di URL /panduan/{slug} dan nama file blade.
|   title  -> judul topik.
|   icon   -> class Font Awesome (tanpa "fas").
|   desc   -> ringkasan 1 kalimat untuk kartu di halaman indeks.
|   gate   -> syarat topik tampil untuk user (mengikuti akses menu sidebar):
|               null / tidak diisi = selalu tampil
|               "menu:<key>"       = tampil bila role punya menu <key>
|                                    (samakan dengan $can(...) di layout)
|               "role:a,b,c"       = tampil bila role user salah satunya
|
*/

return [

    'groups' => [

        'finance' => [
            'label' => 'Finance',
            'icon'  => 'fa-coins',
            'topics' => [
                [
                    'slug'  => 'dashboard',
                    'title' => 'Dashboard',
                    'icon'  => 'fa-tachometer-alt',
                    'desc'  => 'Halaman awal: highlight pembaruan aplikasi dan aktivitas terakhir tim.',
                    'gate'  => null,
                ],
                [
                    'slug'  => 'todo-list',
                    'title' => 'Todo List',
                    'icon'  => 'fa-tasks',
                    'desc'  => 'Daftar tugas & permintaan yang menunggu tindakan Anda, lengkap dengan aging.',
                    'gate'  => 'menu:todo-list',
                ],
                [
                    'slug'  => 'finance-summary',
                    'title' => 'Summary (Finance)',
                    'icon'  => 'fa-chart-pie',
                    'desc'  => 'Ringkasan posisi keuangan: budget, realisasi, collection, dan kas.',
                    'gate'  => 'menu:finance-summary',
                ],
                [
                    'slug'  => 'purchase-order',
                    'title' => 'Purchase Order',
                    'icon'  => 'fa-file-contract',
                    'desc'  => 'Registrasi PO dari customer, aktivasi proyek, No. PO, dan BAST.',
                    'gate'  => 'menu:list-po',
                ],
                [
                    'slug'  => 'budgeting',
                    'title' => 'Budgeting (Budget Plan)',
                    'icon'  => 'fa-money-check-alt',
                    'desc'  => 'Menyusun rencana anggaran per cost center beserta persetujuannya.',
                    'gate'  => 'menu:finance-budgeting',
                ],
                [
                    'slug'  => 'cost-request',
                    'title' => 'Cost Request',
                    'icon'  => 'fa-file-invoice-dollar',
                    'desc'  => 'Pengajuan biaya berjenjang: Cash Advance dan tipe non-Cash Advance.',
                    'gate'  => 'menu:finance-cost-request',
                ],
                [
                    'slug'  => 'treasury',
                    'title' => 'Treasury (Daily Transaction)',
                    'icon'  => 'fa-layer-group',
                    'desc'  => 'Pengelompokan pembayaran harian ke dalam batch dan penandaan paid.',
                    'gate'  => 'menu:finance-daily-transaction',
                ],
                [
                    'slug'  => 'invoice-cash-receipt',
                    'title' => 'Invoice & Cash Receipt',
                    'icon'  => 'fa-receipt',
                    'desc'  => 'Penerbitan invoice ke customer dan pencatatan penerimaan pembayaran.',
                    'gate'  => 'menu:invoice-cash-receipt',
                ],
                [
                    'slug'  => 'cost-register',
                    'title' => 'Cost Register',
                    'icon'  => 'fa-book',
                    'desc'  => 'Buku besar biaya: seluruh realisasi biaya yang sudah dibayar.',
                    'gate'  => 'role:superadmin,finance',
                ],
                [
                    'slug'  => 'reports',
                    'title' => 'Reports',
                    'icon'  => 'fa-chart-bar',
                    'desc'  => 'Kumpulan laporan keuangan yang dapat difilter dan diekspor.',
                    'gate'  => 'menu:reports',
                ],
            ],
        ],

        'project' => [
            'label' => 'Project Monitoring',
            'icon'  => 'fa-binoculars',
            'topics' => [
                [
                    'slug'  => 'project-summary',
                    'title' => 'Summary (Service Delivery)',
                    'icon'  => 'fa-chart-pie',
                    'desc'  => 'Ringkasan progres seluruh proyek yang sedang berjalan.',
                    'gate'  => 'menu:project-summary',
                ],
                [
                    'slug'  => 'project-monitoring',
                    'title' => 'Project Monitoring',
                    'icon'  => 'fa-binoculars',
                    'desc'  => 'Pemantauan milestone proyek: rencana vs aktual, progres, dan kurva-S.',
                    'gate'  => 'menu:project-monitoring',
                ],
            ],
        ],

        'operations' => [
            'label' => 'Operations',
            'icon'  => 'fa-tools',
            'topics' => [
                [
                    'slug'  => 'trouble-ticket',
                    'title' => 'Trouble Ticket',
                    'icon'  => 'fa-ticket-alt',
                    'desc'  => 'Penanganan gangguan site dan pengajuan biaya lapangan (pra-Cost Request).',
                    'gate'  => 'menu:trouble-ticket',
                ],
            ],
        ],

    ],

];
