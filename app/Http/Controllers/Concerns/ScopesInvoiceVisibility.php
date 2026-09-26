<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa boleh melihat Invoice yang mana.
 *
 * Dipindahkan dari InvoiceCashReceiptController dengan alasan yang sama dengan
 * Concerns\ScopesBudgetPlanVisibility: GlobalSearchController sebelumnya
 * meminjam aturannya lewat `app(InvoiceCashReceiptController::class)`,
 * me-resolve controller dari container untuk dipakai sebagai layanan.
 *
 * Aturannya sendiri tidak diubah: manager & head dibatasi ke PO yang ada di
 * Access Group mereka, peran finance/approval lain melihat semua invoice.
 *
 * App\Services\FileAccess::allowsInvoice() mencerminkan aturan yang sama untuk
 * lampiran — kalau aturan di sini berubah, yang di sana harus ikut.
 */
trait ScopesInvoiceVisibility
{
    /**
     * Namanya scopeInvoiceVisibleTo, bukan scopeVisibleTo.
     *
     * GlobalSearchController memakai trait ini BERSAMA
     * ScopesBudgetPlanVisibility; dua trait yang mendefinisikan method dengan
     * nama sama akan bentrok, dan penyelesaiannya di PHP (insteadof + as)
     * menghasilkan deklarasi yang sulit dibaca untuk keuntungan nol. Nama yang
     * berbeda lebih jelas dan menyebut modulnya sendiri.
     */
    public function scopeInvoiceVisibleTo($query): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasRole(['manager', 'head'])) {
            $query->whereIn('po_id', $user->accessibleProjectIds()->all());
        }
    }
}
