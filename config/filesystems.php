<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
        | 'serve' => false — PENTING.
        |
        | Bila true, Laravel mendaftarkan sendiri route GET /storage/{path}
        | (nama: storage.local) yang menyajikan isi disk ini TANPA otentikasi,
        | dan route itu menang atas route aplikasi. Artinya seluruh berkas yang
        | baru saja dipindahkan ke disk privat tetap bisa diunduh siapa saja.
        | Penyajian berkas ditangani SecureFileController.
        */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            /*
            | 'throw' => true — kegagalan penyimpanan harus BERSUARA.
            |
            | Dengan false (bawaan Laravel), disk penuh / izin folder salah /
            | path tidak sah hanya mengembalikan false. Kode pemanggil di
            | aplikasi ini tidak memeriksa nilai kembali itu — polanya selalu
            | `$path = $file->store(...)` lalu langsung menyimpan barisnya.
            | Hasilnya: berkas TIDAK tersimpan, barisnya tersimpan, tidak ada
            | error di mana pun, dan baru ketahuan berbulan-bulan kemudian saat
            | seseorang mengklik lampiran yang ternyata tidak ada. Untuk bukti
            | transfer dan BAST itu bukan gangguan kecil, itu masalah kepatuhan.
            |
            | Dengan true, kegagalan melempar exception dan langsung terlihat
            | saat unggah — saat masih bisa diulang.
            */
            'throw' => true,
            'report' => false,
        ],

        /*
        | Disk "secure" — SEMUA lampiran aplikasi tersimpan di sini.
        |
        | Rootnya storage/app/private, di luar document root, jadi web server
        | tidak bisa melayaninya langsung. Satu-satunya jalan keluar berkas
        | adalah SecureFileController, yang memeriksa sesi dan hak akses lebih
        | dulu (lihat App\Services\FileAccess). Dulu semuanya di disk "public"
        | dan bisa diunduh siapa saja yang memegang URL-nya.
        */
        'secure' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Lihat alasan 'throw' => true pada disk 'local' di atas. Disk ini
            // memegang SELURUH lampiran aplikasi, jadi justru di sini
            // kegagalan yang tertelan paling mahal akibatnya.
            'throw' => true,
            'report' => false,
        ],

        /*
        | Disk "public" dipertahankan hanya supaya konfigurasi bawaan Laravel
        | tetap utuh. JANGAN dipakai untuk lampiran: apa pun yang ditulis ke
        | sini akan terlayani tanpa pemeriksaan hak akses begitu storage:link
        | dijalankan.
        */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    /*
    | Tautan simbolik sengaja dikosongkan: "php artisan storage:link" akan
    | membuat public/storage menunjuk ke storage/app/public, dan begitu itu ada,
    | web server melayani berkas di sana tanpa melewati Laravel — hak akses
    | apa pun yang dipasang di aplikasi jadi tidak berlaku. Berkas dilayani
    | lewat route /storage/{path} (SecureFileController).
    */
    'links' => [],

];
