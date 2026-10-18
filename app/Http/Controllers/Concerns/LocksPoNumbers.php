<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Invoice;
use App\Models\ProjectPoNumber;

/**
 * Urutan kunci baris untuk jalur yang menyentuh invoice ber-No. PO (penagihan per BAST):
 *
 *     No. PO (bila lebih dari satu, id naik) -> BAST -> invoice (per id) -> detail/header
 *
 * Urutan yang sama di semua jalur mencegah deadlock. Kunci No. PO harus pernyataan PERTAMA
 * dalam DB::transaction(): snapshot baca MySQL (REPEATABLE READ) baru terbentuk pada pembacaan
 * biasa pertama, sehingga pembacaan sesudah kunci melihat commit transaksi lain. Karena itu
 * id No. PO dicari SEBELUM transaksi (noPoDariInvoice) — hubungan invoice -> No. PO tidak berubah
 * sesudah invoice meninggalkan DRAFT, jadi aman dibaca di luar kunci.
 */
trait LocksPoNumbers
{
    /** Kunci baris No. PO, unik dan berurutan id naik. Nilai kosong (invoice tanpa No. PO) dilewati. */
    protected function kunciNoPo(int|array|null ...$poNumberIds): void
    {
        $ids = collect($poNumberIds)->flatten()->filter()->map(fn ($v) => (int) $v)->unique()->sort()->values();
        foreach ($ids as $poId) {
            ProjectPoNumber::whereKey($poId)->lockForUpdate()->first();
        }
    }

    /** id No. PO dari sekumpulan id invoice (unik, naik) — dibaca SEBELUM transaksi dimulai. */
    protected function noPoDariInvoice($invoiceIds): array
    {
        return Invoice::withTrashed()->whereIn('id', collect($invoiceIds)->all())
            ->pluck('project_po_number_id')
            ->filter()->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
    }
}
