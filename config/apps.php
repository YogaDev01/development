<?php

/*
|--------------------------------------------------------------------------
| Daftar aplikasi Tesys
|--------------------------------------------------------------------------
| Satu sistem, enam aplikasi. Rail sempit di kiri layar memakai daftar ini,
| dan sidebar kedua (menu modul) mengikuti aplikasi yang sedang aktif.
|
| Hanya 'finance' yang sudah berisi modul. Empat lainnya menampilkan halaman
| "Coming Soon" sampai modulnya dibangun — cukup ubah 'ready' => true dan
| tambahkan menunya di resources/views/layouts/partials/nav-<key>.blade.php.
|
| 'home' untuk aplikasi yang belum siap memakai prefix /app/<key> supaya tidak
| bertabrakan dengan route modul Finance yang sudah ada (mis. /project).
|
| 'label' dipakai pemindah aplikasi (tombol lambang), 'sub' dipakai tulisan
| kecil di bawah "Terex System" pada sidebar — nama panjang yang enak dibaca.
|
| 'modules' hanya untuk TAMPILAN: daftar bagian yang direncanakan, ditampilkan
| di halaman "segera hadir" dan di sidebar aplikasi yang bersangkutan supaya
| halamannya tidak terasa kosong. Menambah nama di sini tidak membuat menu
| atau route apa pun aktif.
*/

return [

    'default' => 'finance',

    'items' => [

        'finance' => [
            'label' => 'Finance',
            'sub'   => 'Finance',
            'icon'  => 'fas fa-chart-pie',
            'home'  => '/',
            'ready' => true,
        ],

        'hr' => [
            'label' => 'HR',
            'sub'   => 'Human Resource',
            'icon'  => 'fas fa-user-tie',
            'home'  => '/app/hr',
            'ready' => false,
            'desc'  => 'Karyawan, absensi, cuti, dan penggajian dalam satu alur.',
            'modules' => ['Absensi', 'Cuti'],
        ],

        'rnd' => [
            'label' => 'R&D',
            'sub'   => 'Research & Development',
            'icon'  => 'fas fa-flask',
            'home'  => '/app/rnd',
            'ready' => false,
            'desc'  => 'Riset produk, pengujian, dan dokumentasi teknis.',
            'modules' => ['Riset Produk', 'Pengujian', 'Dokumentasi Teknis'],
        ],

        'logistic' => [
            'label' => 'Logistics',
            'sub'   => 'Logistics',
            'icon'  => 'fas fa-truck',
            'home'  => '/app/logistic',
            'ready' => false,
            'desc'  => 'Gudang, stok, pengiriman, dan penerimaan barang.',
            'modules' => ['Gudang', 'Stok', 'Pengiriman', 'Penerimaan Barang'],
        ],

        'operation' => [
            'label' => 'Operations',
            'sub'   => 'Operations',
            'icon'  => 'fas fa-cogs',
            'home'  => '/app/operation',
            'ready' => false,
            'desc'  => 'Jadwal kerja lapangan, tiket, dan pemeliharaan aset.',
            'modules' => ['Jadwal Lapangan', 'Tiket', 'Pemeliharaan Aset'],
        ],

        'project' => [
            'label' => 'Project',
            'sub'   => 'Project',
            'icon'  => 'fas fa-project-diagram',
            'home'  => '/app/project',
            'ready' => false,
            'desc'  => 'Perencanaan, progres, dan serah terima pekerjaan proyek.',
            'modules' => ['Perencanaan', 'Progres', 'Serah Terima'],
        ],

    ],

];
