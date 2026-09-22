<?php

/*
|--------------------------------------------------------------------------
| Aksi cepat Command Center (Ctrl+K)
|--------------------------------------------------------------------------
| Daftar ini HANYA lapisan tampilan: ia menentukan aksi apa yang muncul di
| palet pencarian, bukan siapa yang boleh menjalankannya. Kewenangan tetap
| dijaga middleware 'role:' pada routes/web.php seperti sebelumnya.
|
| 'roles' di sini disalin dari middleware rute tujuannya, supaya palet tidak
| pernah menawarkan jalan buntu (tombol yang berujung 403). Kalau middleware
| sebuah rute berubah, samakan barisnya di sini.
|
| Item dilewati otomatis bila route-nya tidak terdaftar (Route::has), jadi
| aman dipasang untuk modul yang belum aktif.
|
| 'menu' opsional: kunci hak akses menu (Role::hasMenu) yang harus dimiliki
| juga — dipakai saat modulnya bisa disembunyikan per access group.
*/

return [

    [
        'label' => 'Buat Cost Request',
        'icon'  => 'fas fa-file-invoice-dollar',
        'route' => 'budget-request.create',
        'roles' => ['superadmin', 'manager', 'supervisor'],
        'menu'  => 'finance-cost-request',
    ],

    [
        'label' => 'Buat Invoice',
        'icon'  => 'fas fa-receipt',
        'route' => 'invoice-cash-receipt.create',
        'roles' => ['superadmin', 'admin', 'finance', 'collection'],
        'menu'  => 'invoice-cash-receipt',
    ],

    [
        'label' => 'Buat Budget Plan',
        'icon'  => 'fas fa-chart-line',
        'route' => 'budget-plan.create',
        'roles' => ['superadmin', 'manager'],
        'menu'  => 'finance-budgeting',
    ],

    [
        'label' => 'Buat Surat Jalan',
        'icon'  => 'fas fa-truck',
        'route' => 'delivery-note.create',
        'roles' => ['superadmin', 'admin', 'finance', 'collection'],
        'menu'  => 'invoice-cash-receipt',
    ],

    [
        'label' => 'Buat Batch Treasury',
        'icon'  => 'fas fa-wallet',
        'route' => 'daily-transaction.create',
        'roles' => ['superadmin', 'finance', 'treasury'],
        'menu'  => 'finance-daily-transaction',
    ],

    [
        'label' => 'Buat Purchase Order',
        'icon'  => 'fas fa-file-contract',
        'route' => 'project.create',
        'roles' => ['superadmin', 'head'],
        'menu'  => 'list-po',
    ],

];
