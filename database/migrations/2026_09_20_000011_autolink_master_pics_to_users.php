<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M-02 — hubungkan PIC internal ke akun User secara OTOMATIS, tapi hanya bila
 * buktinya tidak bisa ditafsirkan dua kali.
 *
 * KENAPA SYARATNYA KETAT:
 *
 * Kolom `master_pics.user_id` menentukan "PIC ini orang yang sama dengan
 * pemilik akun itu". Salah pasang berarti seorang karyawan tampak sebagai PIC
 * sebuah PO yang bukan tanggung jawabnya — di aplikasi keuangan, itu bukan
 * kesalahan tampilan, itu kesalahan pertanggungjawaban.
 *
 * Nama depan yang kebetulan sama (mis. "HADI TANDIANTO" vs "Hadi Kurniawan")
 * TIDAK cukup jadi bukti, dan justru bentuk kemiripan seperti itulah yang
 * paling sering muncul. Karena itu pencocokan di sini hanya menerima:
 *
 *   1. alamat email PERSIS sama (pembanding paling kuat: email itu unik), atau
 *   2. nama lengkap PERSIS sama setelah dinormalkan (huruf kecil, hanya A-Z),
 *
 * dan dalam kedua kasus, kecocokannya harus TUNGGAL di kedua arah: satu PIC ke
 * satu User, dan User itu belum dipakai PIC lain. Begitu ada dua kandidat,
 * barisnya dilewati — biar manusia yang memutuskan.
 *
 * Sisanya sengaja dibiarkan kosong dan ditandai di halaman Master PIC
 * (kartu "Internal Tanpa Akun" + filter "Belum terhubung"), bukan ditebak.
 *
 * Migrasi ini aman diulang dan aman dijalankan di instalasi mana pun: kalau
 * tidak ada yang cocok, ia tidak mengubah apa-apa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $normal = fn ($teks) => preg_replace('/[^a-z]/', '', strtolower((string) $teks));

        $users = DB::table('users')->get(['id', 'name', 'email']);

        // User yang sudah dipakai PIC lain tidak boleh dipakai dua kali.
        $terpakai = DB::table('master_pics')->whereNotNull('user_id')->pluck('user_id')->all();

        $pics = DB::table('master_pics')
            ->where('type', 'internal')
            ->whereNull('user_id')
            ->get(['id', 'pic', 'email']);

        foreach ($pics as $pic) {
            $kandidat = collect();

            if (!empty($pic->email)) {
                $kandidat = $users->filter(
                    fn ($u) => !empty($u->email)
                        && strtolower($u->email) === strtolower($pic->email)
                );
            }

            if ($kandidat->isEmpty()) {
                $namaPic = $normal($pic->pic);

                // Nama kosong/terlalu pendek setelah dinormalkan bukan bukti apa pun.
                if (strlen($namaPic) < 4) {
                    continue;
                }

                $kandidat = $users->filter(fn ($u) => $normal($u->name) === $namaPic);
            }

            // Ambigu (0 atau >1 kandidat) -> lewati, biar diputuskan manusia.
            if ($kandidat->count() !== 1) {
                continue;
            }

            $userId = $kandidat->first()->id;

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
        // Sengaja tidak melepas kaitan apa pun: setelah migrasi ini jalan,
        // sebagian kaitan mungkin sudah diperbaiki/diisi manual oleh pengguna,
        // dan membedakannya dari hasil otomatis sudah tidak mungkin. Melepas
        // semuanya akan membuang pekerjaan orang.
    }
};
