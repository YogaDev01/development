<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\CashReceipt;
use App\Models\Closing;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\ProjectPoNumber;
use Illuminate\Support\Collection;

/**
 * M-04 — pengumpul transaksi finance untuk sebuah periode.
 *
 * Rekapnya SELALU dibangun ulang dari dokumen aslinya, tidak pernah dari input
 * manual. Itu yang membuat closing bisa menjawab "ada transaksi yang terlewat
 * atau tidak": kalau ada dokumen baru bertanggal dalam periode yang sudah
 * ditutup, ia akan muncul saat rekap dihitung ulang dan selisihnya kelihatan.
 *
 * Tiap baris membawa source_module + source_id, sehingga closing tidak
 * menggandakan transaksi — ia hanya menunjuk.
 */
class ClosingService
{
    public const MODULES = [
        'TREASURY'       => 'Treasury',
        'INVOICE'        => 'Invoice',
        'COLLECTION'     => 'Collection',
        'CASH_RECEIPT'   => 'Cash Receipt',
        'COST_REQUEST'   => 'Cost Request',
        'PURCHASE_ORDER' => 'Purchase Order',
    ];

    /**
     * Kumpulkan seluruh transaksi finance dalam rentang tanggal.
     *
     * @return Collection<int, array>
     */
    public function collect(string $startDate, string $endDate): Collection
    {
        return collect()
            ->merge($this->treasury($startDate, $endDate))
            ->merge($this->invoices($startDate, $endDate))
            ->merge($this->collections($startDate, $endDate))
            ->merge($this->cashReceipts($startDate, $endDate))
            ->merge($this->costRequests($startDate, $endDate))
            ->merge($this->purchaseOrders($startDate, $endDate))
            ->values();
    }

    /** Rekap per modul — dipakai untuk kartu ringkasan di halaman Closing. */
    public function summarize(Collection $rows): array
    {
        $byModule = [];

        foreach (self::MODULES as $key => $label) {
            $moduleRows = $rows->where('source_module', $key);

            $byModule[$key] = [
                'label'  => $label,
                'count'  => $moduleRows->count(),
                'amount' => (int) $moduleRows->sum('amount'),
            ];
        }

        return [
            'by_module'         => $byModule,
            'total_transaction' => $rows->count(),
            'total_value'       => (int) $rows->sum('amount'),
        ];
    }

    /**
     * Bandingkan rekap tersimpan dengan keadaan data SEKARANG.
     *
     * Inilah "period control" yang diminta M-04: selisih berarti ada dokumen
     * yang masuk/berubah setelah periode ditutup, dan itu harus terlihat —
     * bukan diam-diam ditimpa.
     */
    public function drift(Closing $closing): array
    {
        $current = $this->collect(
            $closing->start_date->format('Y-m-d'),
            $closing->end_date->format('Y-m-d'),
        );

        $recorded = $closing->details
            ->map(fn ($d) => $d->source_module . '#' . $d->source_id)
            ->all();

        $currentKeys = $current
            ->map(fn ($r) => $r['source_module'] . '#' . $r['source_id'])
            ->all();

        $missing = $current->filter(fn ($r) => !in_array($r['source_module'] . '#' . $r['source_id'], $recorded, true));
        $stale   = $closing->details->filter(fn ($d) => !in_array($d->source_module . '#' . $d->source_id, $currentKeys, true));

        return [
            'missing'        => $missing->values(),   // ada di data, belum direkap
            'stale'          => $stale->values(),     // direkap, tapi dokumennya hilang/berubah tanggal
            'has_difference' => $missing->isNotEmpty() || $stale->isNotEmpty(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SUMBER
    |--------------------------------------------------------------------------
    | Tanggal acuan tiap modul sengaja dipilih yang mencerminkan KAPAN
    | peristiwanya terjadi, bukan kapan barisnya dibuat.
    */

    /** Treasury — batch pembayaran yang sudah dibayar. */
    private function treasury(string $from, string $to): Collection
    {
        return DailyTransaction::whereBetween('date', [$from, $to])
            ->get()
            ->map(fn ($dt) => [
                'source_module'     => 'TREASURY',
                'source_id'         => $dt->id,
                'reference_number'  => $dt->code,
                'transaction_date'  => $dt->date,
                'description'       => $dt->description ?: ('Batch ' . $dt->code),
                'status_at_closing' => $dt->status,
                'amount'            => (int) ($dt->captured_amount ?: $dt->charge),
            ]);
    }

    /** Invoice yang terbit dalam periode. */
    private function invoices(string $from, string $to): Collection
    {
        return Invoice::with('po:id,code,name')
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'DRAFT')
            ->get()
            ->map(fn ($inv) => [
                'source_module'     => 'INVOICE',
                'source_id'         => $inv->id,
                'reference_number'  => $inv->code,
                'transaction_date'  => $inv->invoice_date,
                'description'       => 'Invoice ' . $inv->code . ' — ' . ($inv->po->name ?? '-'),
                'status_at_closing' => $inv->status,
                'amount'            => (int) $inv->captured_total,
            ]);
    }

    /**
     * Collection — piutang yang MASIH terbuka pada akhir periode.
     *
     * Sengaja tidak memakai invoice_date: yang perlu direkap saat tutup buku
     * adalah posisi piutang di tanggal penutupan, termasuk invoice lama yang
     * belum lunas. Nilainya = sisa tagihan, bukan nilai invoice.
     */
    private function collections(string $from, string $to): Collection
    {
        return Invoice::with('cashReceipts')
            ->whereIn('status', Invoice::UNPAID_STATUSES)
            ->whereDate('invoice_date', '<=', $to)
            ->get()
            ->map(function ($inv) {
                $received = (int) $inv->cashReceipts->sum('amount') + (int) $inv->cashReceipts->sum('withholding_tax');
                $balance  = max(0, (int) $inv->captured_total - $received);

                return [
                    'source_module'     => 'COLLECTION',
                    'source_id'         => $inv->id,
                    'reference_number'  => $inv->code,
                    'transaction_date'  => $inv->actual_due_date ?? $inv->due_date,
                    'description'       => 'Piutang ' . $inv->code,
                    'status_at_closing' => $inv->status,
                    'amount'            => $balance,
                ];
            })
            ->filter(fn ($r) => $r['amount'] > 0);
    }

    /** Cash Receipt — uang masuk dalam periode. */
    private function cashReceipts(string $from, string $to): Collection
    {
        return CashReceipt::with('invoice:id,code')
            ->whereBetween('received_date', [$from, $to])
            ->get()
            ->map(fn ($cr) => [
                'source_module'     => 'CASH_RECEIPT',
                'source_id'         => $cr->id,
                'reference_number'  => $cr->invoice->code ?? ('CR#' . $cr->id),
                'transaction_date'  => $cr->received_date,
                'description'       => 'Penerimaan atas ' . ($cr->invoice->code ?? '-'),
                'status_at_closing' => 'RECEIVED',
                'amount'            => (int) $cr->amount + (int) $cr->withholding_tax,
            ]);
    }

    /** Cost Request yang dibayar dalam periode. */
    private function costRequests(string $from, string $to): Collection
    {
        return BudgetRequest::with('beneficiary:id,nickname')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get()
            ->map(fn ($cr) => [
                'source_module'     => 'COST_REQUEST',
                'source_id'         => $cr->id,
                'reference_number'  => $cr->code,
                'transaction_date'  => $cr->paid_at?->toDateString(),
                'description'       => $cr->description ?: ('Cost Request ' . $cr->code),
                'status_at_closing' => $cr->status,
                'amount'            => (int) $cr->amount,
            ]);
    }

    /** No. PO yang terbit dalam periode. */
    private function purchaseOrders(string $from, string $to): Collection
    {
        return ProjectPoNumber::with('project:id,code,name')
            ->whereBetween('release_date', [$from, $to])
            ->get()
            ->map(fn ($po) => [
                'source_module'     => 'PURCHASE_ORDER',
                // source_id = id No. PO, BUKAN id project: satu project bisa
                // punya beberapa No. PO, dan memakai project_id membuat dua
                // baris rekap bertabrakan kunci (module#id) sehingga deteksi
                // "transaksi terlewat" di drift() salah hitung.
                'source_id'         => $po->id,
                'reference_number'  => $po->po_number,
                'transaction_date'  => $po->release_date,
                'description'       => 'No. PO ' . $po->po_number . ' — ' . ($po->project->name ?? '-'),
                'status_at_closing' => $po->status,
                'amount'            => (int) $po->nominal,
            ]);
    }
}
