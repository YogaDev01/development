<?php

namespace App\Support;

use Illuminate\Database\QueryException;
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
        return DB::transaction(function () use ($key, $seed) {
            $row = DB::table('document_sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $awal = (int) $seed();

                try {
                    DB::table('document_sequences')->insert([
                        'key'        => $key,
                        'counter'    => $awal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    // Rangkaian baru saja dibuat oleh permintaan lain; baca ulang
                    // dengan kunci supaya kita antre di belakangnya.
                    $row = DB::table('document_sequences')
                        ->where('key', $key)
                        ->lockForUpdate()
                        ->first();
                }

                $row = $row ?: (object) ['counter' => $awal];
            }

            $berikutnya = ((int) $row->counter) + 1;

            DB::table('document_sequences')
                ->where('key', $key)
                ->update(['counter' => $berikutnya, 'updated_at' => now()]);

            return $berikutnya;
        });
    }
}
