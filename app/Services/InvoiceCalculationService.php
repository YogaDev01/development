<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * MN-07 — SATU-SATUNYA tempat rumus nilai Invoice hidup.
 *
 * Sebelumnya rumus captured_total ditulis ulang di Invoice::recalculateTotals()
 * dan diduplikasi (dalam bentuk JavaScript) di form create/edit invoice. Begitu
 * Withholding Tax masuk, duplikasi seperti itu dijamin cepat atau lambat
 * berbeda hasil. Jadi rumusnya dipusatkan di sini; backend tetap sumber
 * kebenaran, frontend hanya menampilkan pratinjau memakai angka-angka yang
 * dikirim dari method yang sama.
 *
 * Rumusnya:
 *
 *     billed_amount  = subtotal x billing_percentage      (nilai DPP ditagih)
 *     tax_amount     = billed_amount x tax_rate           (PPN)
 *     captured_total = billed_amount + tax_amount - withholding_tax
 *
 * Contoh dari spesifikasi:
 *     Invoice Amount 100.000.000 + Tax 11.000.000 - WHT 2.000.000
 *     = Captured Total 109.000.000
 */
class InvoiceCalculationService
{
    /**
     * Hitung seluruh komponen nilai invoice dari angka mentah.
     *
     * Dipakai dua arah: oleh Invoice::recalculateTotals() (dari baris detail)
     * dan oleh endpoint pratinjau form (dari input yang sedang diketik), supaya
     * angka yang dilihat user saat mengisi form identik dengan yang tersimpan.
     *
     * @param  int         $subtotal    Total DPP seluruh baris detail.
     * @param  float|null  $percentage  Persentase penagihan; null = tagih penuh.
     * @param  float       $taxRate     Tarif PPN dalam persen.
     * @param  int         $withholding PPh yang dipotong di muka (rupiah).
     * @return array{billed_amount:int, tax_amount:int, withholding_tax:int, captured_total:int}
     */
    public function compute(int $subtotal, ?float $percentage, float $taxRate, int $withholding = 0): array
    {
        // Persentase penagihan (mis. Termin 50%) — NULL/100 berarti tagih penuh.
        // PPN & total dihitung dari nominal SETELAH persentase, karena itulah
        // yang benar-benar ditagihkan ke customer saat ini.
        $billedAmount = $percentage !== null
            ? (int) round($subtotal * $percentage / 100)
            : $subtotal;

        $taxAmount = (int) round($billedAmount * ($taxRate / 100));

        // WHT tidak boleh negatif, dan tidak boleh melebihi nilai tagihan —
        // captured_total negatif akan merusak perhitungan status pembayaran
        // (remainingBalance/syncPaymentStatus) di seluruh aplikasi.
        $withholding = max(0, min($withholding, $billedAmount + $taxAmount));

        return [
            'billed_amount'   => $billedAmount,
            'tax_amount'      => $taxAmount,
            'withholding_tax' => $withholding,
            'captured_total'  => $billedAmount + $taxAmount - $withholding,
        ];
    }

    /** Versi yang membaca langsung dari sebuah Invoice (tanpa menyimpan). */
    public function computeFor(Invoice $invoice, ?int $subtotal = null): array
    {
        return $this->compute(
            $subtotal ?? (int) $invoice->subtotal,
            $invoice->billing_percentage !== null ? (float) $invoice->billing_percentage : null,
            (float) $invoice->tax_rate,
            (int) $invoice->withholding_tax,
        );
    }

    /**
     * Sisa yang masih harus diterima atas sebuah invoice.
     *
     * Catatan: withholding_tax di `cash_receipts` (PPh yang di-settle saat
     * pembayaran) IKUT dihitung sebagai "diterima" — itu uang yang tidak masuk
     * rekening tapi juga bukan piutang, karena sudah disetor customer sebagai
     * pajak. Sedangkan withholding_tax di `invoices` (PPh dipotong di muka)
     * sudah lebih dulu mengecilkan captured_total, jadi tidak dihitung dua kali.
     */
    public function outstanding(Invoice $invoice): int
    {
        return max(0, (int) $invoice->captured_total - $invoice->totalReceived());
    }
}
