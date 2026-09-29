<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Akun User untuk PIC internal yang belum punya akun.
|
| Migrasi 2026_09_20_000011 sudah mencoba menghubungkan PIC internal ke akun
| yang SUDAH ADA, dan sisanya sebelas orang: bukan karena kaitannya ambigu,
| tapi karena akunnya memang belum pernah dibuat. Tabel users hanya berisi
| akun superadmin dan akun contoh dummy.*.
|
| Migrasi ini membuat akun itu lalu mengaitkannya, semuanya berperan `staff`.
|
| USERNAME diturunkan dari nama PIC (huruf kecil, hanya a-z0-9), mengikuti
| bentuk username yang sudah dipakai (superadmin, apriadmin, hani21). Kalau
| bentrok, ditambahi angka urut — bukan ditimpa: menimpa berarti dua orang
| berbagi satu akun, dan di aplikasi keuangan itu kesalahan pertanggungjawaban.
|
| PASSWORD awal seragam 'password123', sama dengan seeder akun contoh yang
| sudah ada di proyek ini. HARUS diganti pada login pertama.
|
| Aman diulang: akun yang sudah ada dilewati, dan hanya PIC yang user_id-nya
| masih kosong yang disentuh.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        $roleStaff = DB::table('roles')->where('name', 'staff')->value('id');

        if (! $roleStaff) {
            // Tanpa role staff, akun yang dibuat tidak punya hak apa pun —
            // lebih baik tidak membuat apa-apa daripada membuat akun lumpuh.
            return;
        }

        $pics = DB::table('master_pics')
            ->where('type', 'internal')
            ->whereNull('user_id')
            ->orderBy('id')
            ->get(['id', 'pic', 'email', 'phone']);

        if ($pics->isEmpty()) {
            return;
        }

        $hash    = Hash::make('password123');
        $terpakai = DB::table('master_pics')->whereNotNull('user_id')->pluck('user_id')->all();

        foreach ($pics as $pic) {
            $dasar = preg_replace('/[^a-z0-9]/', '', strtolower((string) $pic->pic));

            if ($dasar === '') {
                continue;   // nama kosong bukan dasar yang cukup untuk sebuah akun
            }

            /*
             * Akun yang sudah ada untuk orang ini dipakai ulang, tidak dibuat
             * kedua kalinya. Tanpa penjagaan ini, menjalankan migrasi dua kali
             * (atau melanjutkannya setelah gagal di tengah) akan melahirkan
             * "garka2" di samping "garka" — dua akun untuk satu orang.
             */
            $adaUser = DB::table('users')
                ->whereRaw("LOWER(REPLACE(REPLACE(name, ' ', ''), '.', '')) = ?", [$dasar])
                ->value('id');

            if ($adaUser) {
                if (! in_array($adaUser, $terpakai, true)) {
                    DB::table('master_pics')->where('id', $pic->id)->update([
                        'user_id'    => $adaUser,
                        'updated_at' => now(),
                    ]);
                    $terpakai[] = $adaUser;
                }
                continue;
            }

            $username = $dasar;
            $n = 2;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $dasar . $n++;
            }

            /*
             * Kolom email WAJIB isi dan unik. Dua PIC tidak punya alamat, jadi
             * dibuatkan penanda ber-domain .invalid — domain yang oleh standar
             * TIDAK pernah bisa dikirimi surat, supaya alamat karangan ini tidak
             * mungkin diam-diam dipakai berkirim pesan dan gampang dicari saat
             * alamat aslinya sudah ada.
             */
            $email = $pic->email ?: null;
            if ($email && DB::table('users')->where('email', $email)->exists()) {
                $email = null;
            }
            if (! $email) {
                $email = $username . '@belum-diisi.invalid';
            }

            $userId = DB::table('users')->insertGetId([
                'name'       => $pic->pic,
                'username'   => $username,
                'email'      => $email,
                'phone'      => $pic->phone ?: null,
                'password'   => $hash,
                'role_id'    => $roleStaff,
                'status'     => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (in_array($userId, $terpakai, true)) {
                continue;
            }

            DB::table('master_pics')->where('id', $pic->id)->update([
                'user_id'    => $userId,
                'updated_at' => now(),
            ]);

            $terpakai[] = $userId;
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus akun apa pun. Begitu sebuah akun dipakai
        // masuk, ia bisa sudah meninggalkan jejak (log, notifikasi, baris yang
        // mencatat input_by), dan menghapusnya akan memutus jejak itu. Kalau
        // sebuah akun memang tidak jadi dipakai, nonaktifkan lewat menu
        // User & Access.
    }
};
