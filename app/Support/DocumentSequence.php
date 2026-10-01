<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pemberi nomor urut dokumen.
 *
 * Dipakai semua penomoran dokumen keuangan. Pola lamanya — hitung baris lalu
 * tambah satu — punya dua masalah yang keduanya pernah menghasilkan galat
 * nyata: dua penyimpanan bersamaan mendapat nomor yang sama (satu gagal dengan
 * duplicate key), dan menghapus dokumen membuat nomor berikutnya memakai
 * ulang nomor yang sudah pernah dipakai.
 *
 * Di sini nomor diambil dari baris penghitung yang dikunci selama transaksi,
 * jadi permintaan kedua menunggu giliran, bukan membaca nilai basi.
 *
 * Penghitung disemai sekali dari keadaan data yang ada (lewat $seed), supaya
 * penomoran melanjutkan dari nomor terakhir yang sudah terbit — bukan mulai
 * dari satu lagi.
 */
final class DocumentSequence
{
    /**
     * Ambil nomor berikutnya untuk sebuah rangkaian.
     *
     * @param  string   $key   Nama rangkaian, mis. "invoice:2026".
     * @param  \Closure $seed  Dipanggil sekali saat rangkaian belum ada;
     *                         mengembalikan nomor terakhir yang sudah terpakai.
     */
    public static function next(string $key, \Closure $seed): int
    {
        /*
        | Langkah 1 - pastikan baris penghitung ada, DI LUAR kunci.
        |
        | Dulu baris dicari dengan SELECT ... FOR UPDATE lalu di-INSERT bila
        | belum ada. Pada rangkaian yang benar-benar baru, dua permintaan
        | bersamaan sama-sama mendapat gap lock atas baris kosong itu, lalu
        | sama-sama INSERT: InnoDB menyelesaikannya sebagai deadlock dan
        | me-rollback SELURUH transaksi salah satunya - termasuk transaksi
        | pemanggil (mis. store() Cash Receipt). Blok catch lama menelan galat
        | itu dan melanjutkan di atas transaksi yang sudah mati, sehingga
        | perintah berikutnya ter-autocommit dan pengguna mendapat 500
        | ("There is no active transaction"). Ditemukan lewat UAT konkurensi.
        |
        | INSERT IGNORE tanpa SELECT FOR UPDATE sebelumnya tidak memakai gap
        | lock: permintaan kedua hanya menunggu, lalu barisnya diabaikan.
        */
        if (! DB::table('document_sequences')->where('key', $key)->exists()) {
            DB::table('document_sequences')->insertOrIgnore([
                'key'        => $key,
                'counter'    => (int) $seed(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Langkah 2 - baris pasti ada: kunci satu baris itu dan naikkan.
        return DB::transaction(function () use ($key) {
            $row = DB::table('document_sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $berikutnya = ((int) $row->counter) + 1;

            DB::table('document_sequences')
                ->where('key', $key)
                ->update(['counter' => $berikutnya, 'updated_at' => now()]);

            return $berikutnya;
        });
    }
}
