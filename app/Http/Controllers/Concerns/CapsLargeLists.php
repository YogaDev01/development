<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Batas aman jumlah baris untuk daftar yang digambar penuh ke halaman.
 *
 * Seluruh daftar transaksi di aplikasi ini dirender penuh ke DOM lalu diurus
 * DataTables di sisi peramban: tidak ada paginasi server sama sekali. Selama
 * datanya ratusan baris, itu cepat dan enak dipakai — pencarian dan sortir
 * tanpa memuat ulang. Begitu datanya ribuan, halamannya berhenti merespons
 * sebelum sempat menampilkan apa pun, dan tidak ada tanda apa-apa selain
 * peramban yang membeku.
 *
 * Cost Request lebih dulu menabrak batas itu dan menanganinya dengan
 * pembatasan baris + spanduk pemberitahuan. Pola itu diangkat ke sini supaya
 * daftar lain memakai perilaku yang sama persis, bukan menemukan sendiri
 * batasnya di kemudian hari.
 *
 * Ini penopang, bukan jawaban akhir: paginasi sisi server tetap pekerjaan yang
 * benar, dan menyentuh filter kolom serta ekspor yang sekarang bekerja di
 * peramban. Yang ini membuat halaman tidak pernah mati sambil menunggu itu.
 */
trait CapsLargeLists
{
    /** Jumlah baris maksimal yang digambar ke halaman. */
    protected int $maxRows = 1000;

    /**
     * Ambil paling banyak $maxRows baris terbaru, sambil melaporkan total
     * sebenarnya supaya halaman bisa jujur soal yang tidak ditampilkan.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: bool, 2: int}
     *         [baris, terpotong, total]
     */
    protected function cappedRows(Builder $query, string $orderColumn = 'created_at'): array
    {
        $total = (clone $query)->count();

        $rows = $query->orderByDesc($orderColumn)
            ->limit($this->maxRows)
            ->get();

        return [$rows, $total > $this->maxRows, $total];
    }
}
