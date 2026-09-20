<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan status yang aman dari balapan.
 *
 * Pola lama di seluruh controller workflow:
 *
 *     $cr = BudgetRequest::findOrFail($id);
 *     if ($cr->status !== 'SUBMITTED') return back()->with('error', ...);
 *     $cr->update(['status' => 'CHECKED']);
 *     $cr->addLog(...);
 *
 * Antara baris pemeriksaan dan baris penulisan tidak ada apa pun yang mencegah
 * request kedua membaca status yang sama. Dua klik Approve yang datang
 * bersamaan - dua tab, dua approver, atau satu tombol yang diklik dua kali
 * karena halaman terasa lambat - sama-sama lolos pemeriksaan dan sama-sama
 * menulis. Akibatnya log ganda, notifikasi ganda, dan pada tahap pembayaran
 * bisa berarti transaksi yang diproses dua kali.
 *
 * Metode di bawah membungkus keduanya dalam satu transaksi dan mengunci baris
 * (SELECT ... FOR UPDATE), sehingga request kedua menunggu, lalu melihat
 * status yang sudah berubah dan mundur dengan sopan.
 */
trait TransitionsStatus
{
    /**
     * Kunci baris, pastikan statusnya masih yang diharapkan, lalu ubah.
     *
     * @param  class-string<Model>  $modelClass
     * @param  string|array<int, string>  $expected  Status yang harus berlaku sekarang.
     * @param  array<string, mixed>  $changes  Kolom yang diubah (termasuk status baru).
     * @param  \Closure(Model): void|null  $inside  Dijalankan di dalam transaksi
     *         yang sama - tempat mencatat log, supaya log dan perubahan status
     *         tidak pernah terpisah.
     * @return Model|null  Model yang sudah diperbarui, atau null bila statusnya
     *         sudah berubah lebih dulu (pemanggil menampilkan pesan yang sesuai).
     */
    protected function transitionStatus(
        string $modelClass,
        $id,
        string|array $expected,
        array $changes,
        ?\Closure $inside = null,
    ): ?Model {
        $expected = (array) $expected;

        return DB::transaction(function () use ($modelClass, $id, $expected, $changes, $inside) {
            /** @var Model|null $model */
            $model = $modelClass::query()->whereKey($id)->lockForUpdate()->first();

            if (! $model || ! in_array($model->getAttribute('status'), $expected, true)) {
                return null;
            }

            $model->update($changes);

            if ($inside) {
                $inside($model);
            }

            return $model;
        });
    }
}
