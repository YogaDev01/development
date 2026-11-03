<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\ProjectPoNumber;
use App\Services\InvoiceCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Perbaiki invoices.captured_total yang tidak sesuai rumus
 * captured_total = billed_amount + tax_amount - withholding_tax
 * (InvoiceCalculationService), mis. PPh sudah terisi tetapi Total belum dihitung ulang.
 *
 * Bawaan: DRY RUN (hanya menampilkan selisih dan perkiraan status). Ubah data hanya dengan --apply.
 * - Rumus HANYA dari InvoiceCalculationService::computeFor (satu sumber kebenaran).
 * - Invoice yang billed_amount/tax_amount tersimpan berbeda dari hasil service (data tidak
 *   konsisten) DILEWATI dan dilaporkan; perbaiki manual.
 * - Hanya captured_total yang ditulis. Dihitung ulang di dalam kunci (No. PO lalu invoice, urutan
 *   yang sama dengan jalur pembayaran), lalu status pembayaran disinkronkan (Invoice::syncPaymentStatus).
 * - Nilai lama dicatat di storage/logs. Aman dijalankan berulang.
 * Jalankan dry run dulu dan periksa daftarnya sebelum --apply.
 */
class RepairInvoiceCapturedTotal extends Command
{
    protected $signature = 'invoice:repair-captured-total {--apply : Simpan perbaikan (tanpa ini hanya dry run)}';

    protected $description = 'Hitung ulang captured_total invoice yang tidak sesuai PPh (dry run kecuali --apply)';

    public function handle(InvoiceCalculationService $service): int
    {
        $apply = (bool) $this->option('apply');

        // CAST SIGNED: kolom UNSIGNED akan error "out of range" bila hasil pengurangan negatif.
        $kandidat = Invoice::query()
            ->whereRaw('CAST(captured_total AS SIGNED) <> GREATEST(0, CAST(billed_amount AS SIGNED) + CAST(tax_amount AS SIGNED) - CAST(withholding_tax AS SIGNED))')
            ->orderBy('id')
            ->get(['id']);

        if ($kandidat->isEmpty()) {
            $this->info('Semua captured_total sudah sesuai rumus.');

            return self::SUCCESS;
        }

        $rows = [];
        $dilewati = [];
        $kelebihan = [];

        foreach ($kandidat as $k) {
            $poId = Invoice::whereKey($k->id)->value('project_po_number_id');

            DB::transaction(function () use ($k, $poId, $service, $apply, &$rows, &$dilewati, &$kelebihan) {
                // Urutan kunci sama dengan jalur pembayaran: No. PO -> invoice.
                if ($poId) {
                    ProjectPoNumber::whereKey($poId)->lockForUpdate()->first();
                }
                $inv = Invoice::whereKey($k->id)->lockForUpdate()->first();
                if (! $inv) {
                    return;
                }

                // Dihitung ulang di dalam kunci dari data terkini.
                $calc = $service->computeFor($inv);
                if ($calc['billed_amount'] !== (int) $inv->billed_amount || $calc['tax_amount'] !== (int) $inv->tax_amount) {
                    $dilewati[] = [$inv->code, $inv->status, (int) $inv->billed_amount, (int) $inv->tax_amount, $calc['billed_amount'], $calc['tax_amount']];

                    return;
                }

                $lama = (int) $inv->captured_total;
                $baru = $calc['captured_total'];
                if ($lama === $baru) {
                    return;
                }

                $statusLama = $inv->status;
                $diterima   = $inv->totalReceived();

                // Total baru lebih kecil dari yang sudah diterima: PPh invoice kemungkinan hanya dicatat
                // padahal customer membayar bruto. Itu keputusan bisnis, bukan perbaikan otomatis.
                if ($diterima > $baru) {
                    $kelebihan[] = [$inv->code, $statusLama, $lama, $baru, $diterima, $diterima - $baru];

                    return;
                }
                $perkiraan  = $statusLama;
                if (in_array($statusLama, ['SENT', 'RECEIVED', 'PARTIALLY_PAID', 'PAID'], true)) {
                    $perkiraan = $diterima <= 0
                        ? ($statusLama === 'RECEIVED' ? 'RECEIVED' : 'SENT')
                        : ($diterima < $baru ? 'PARTIALLY_PAID' : 'PAID');
                }

                if ($apply) {
                    $inv->update(['captured_total' => $baru]);
                    $inv->syncPaymentStatus();
                    $perkiraan = $inv->fresh()->status;

                    Log::info('invoice:repair-captured-total', [
                        'invoice_id' => $inv->id, 'code' => $inv->code,
                        'captured_total_lama' => $lama, 'captured_total_baru' => $baru,
                        'status_lama' => $statusLama, 'status_sesudah' => $perkiraan,
                    ]);
                }

                $rows[] = [$inv->code, $statusLama . ' -> ' . $perkiraan, $lama, $baru, (int) $inv->withholding_tax, $diterima];
            });
        }

        if ($rows) {
            $this->table(['Invoice', 'Status', 'Total Lama', 'Total Baru', 'PPh', 'Diterima'], $rows);
        }
        if ($dilewati) {
            $this->warn('DILEWATI (billed/PPN tersimpan berbeda dari hasil hitung, periksa manual):');
            $this->table(['Invoice', 'Status', 'Billed', 'PPN', 'Billed Hitung', 'PPN Hitung'], $dilewati);
        }

        if ($kelebihan) {
            $this->warn('TIDAK DIUBAH (diterima akan melebihi Total baru, tinjau manual: PPh invoice mungkin hanya dicatat, customer bayar bruto):');
            $this->table(['Invoice', 'Status', 'Total Lama', 'Total Baru', 'Diterima', 'Kelebihan'], $kelebihan);
        }

        $this->info(($apply ? '' : 'DRY RUN - ') . count($rows) . ' invoice ' . ($apply ? 'diperbaiki' : 'akan diperbaiki (tidak diubah; jalankan dengan --apply)')
            . ', ' . count($dilewati) . ' dilewati (data tidak konsisten), ' . count($kelebihan) . ' ditinjau manual (kelebihan).');

        return self::SUCCESS;
    }
}
