<?php

namespace App\Services;

use App\Models\CashAdjustment;
use App\Models\CashFlow;
use App\Models\CashReceipt;
use App\Models\CashTransfer;
use App\Models\DailyTransaction;
use Illuminate\Support\Collection;

/**
 * M-01 — penyusun buku besar kas.
 *
 * INI BUKAN TABEL BARU YANG MENYALIN TRANSAKSI. Pergerakan kas di TESYS sudah
 * tercatat di modul masing-masing; service ini menyatukannya menjadi satu
 * daftar yang bentuknya seragam, sehingga Cash Management bisa jadi pusat
 * pemantauan tanpa menggandakan satu pun transaksi:
 *
 *   MASUK  <- cash_receipts        (pelunasan invoice)
 *   MASUK  <- cash_flows           (entri manual berarah IN)
 *   KELUAR <- daily_transactions   (Treasury: batch pembayaran CR yang PAID)
 *   KELUAR <- cash_flows           (entri manual berarah OUT)
 *
 * Yang SENGAJA TIDAK dihitung sebagai pergerakan kas:
 *
 *   - Treasury bertipe TOP_UP: pindah dana antar rekening perusahaan sendiri.
 *     Kas perusahaan tidak bertambah/berkurang; kalau ikut dihitung, satu
 *     transfer akan muncul sebagai uang keluar yang tidak pernah kembali.
 *   - Cost Request, Purchase Order, Invoice, Cost Register: itu komitmen dan
 *     pengakuan, bukan kas. Kas-nya baru bergerak lewat Treasury/Cash Receipt,
 *     dan di situlah ia dihitung — sekali saja.
 *   - Cash Flow berstatus DRAFT atau CANCELLED.
 *
 * Setiap baris membawa source_module + source_id + reference agar bisa
 * ditelusuri balik ke dokumen asalnya.
 */
class CashFlowService
{
    public const MODULE_CASH_RECEIPT = 'CASH_RECEIPT';
    public const MODULE_TREASURY     = 'TREASURY';
    public const MODULE_MANUAL       = 'CASH_FLOW';
    public const MODULE_TRANSFER     = 'CASH_TRANSFER';   // hanya biaya transfer di ledger konsolidasi
    public const MODULE_ADJUSTMENT   = 'CASH_ADJUSTMENT';

    /**
     * Buku besar kas untuk sebuah rentang tanggal.
     *
     * @param  array{from?:string|null, to?:string|null, direction?:string|null, source_fund_id?:int|null, module?:string|null, q?:string|null} $filters
     */
    public function ledger(array $filters = []): Collection
    {
        $from = $filters['from'] ?? null;
        $to   = $filters['to'] ?? null;

        $rows = collect()
            ->merge($this->cashReceiptRows($from, $to))
            ->merge($this->treasuryRows($from, $to))
            ->merge($this->manualRows($from, $to))
            ->merge($this->transferFeeRows($from, $to))
            ->merge($this->adjustmentRows($from, $to));

        // Filter sisa diterapkan SETELAH penggabungan supaya satu definisi
        // filter berlaku sama untuk semua sumber (tidak ada sumber yang diam-
        // diam lolos karena kolomnya bernama lain).
        // Arah, rekening dan sumber boleh berisi lebih dari satu pilihan
        // (filter checklist di halaman) — nilai tunggal tetap diterima supaya
        // tautan lama tidak rusak.
        $direction = array_filter((array) ($filters['direction'] ?? []), fn ($v) => $v !== '' && $v !== null);
        if ($direction) {
            $rows = $rows->whereIn('direction', $direction);
        }

        $funds = array_map('intval', array_filter((array) ($filters['source_fund_id'] ?? []), fn ($v) => $v !== '' && $v !== null));
        if ($funds) {
            $rows = $rows->whereIn('source_fund_id', $funds);
        }

        $modules = array_filter((array) ($filters['module'] ?? []), fn ($v) => $v !== '' && $v !== null);
        if ($modules) {
            $rows = $rows->whereIn('source_module', $modules);
        }

        if (!empty($filters['q'])) {
            $q = mb_strtolower(trim($filters['q']));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower(
                $r['description'] . ' ' . $r['reference_number'] . ' ' . $r['category']
            ), $q));
        }

        return $rows
            ->sortByDesc(fn ($r) => $r['date']->format('Y-m-d') . str_pad((string) $r['source_id'], 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Buku besar PER REKENING: ledger() + dua sisi Top Up (OUT dari rekening
     * asal, IN ke rekening tujuan). Top Up tidak mengubah kas konsolidasi,
     * tetapi mengubah saldo tiap rekening — karena itu hanya dipakai untuk
     * saldo rekening (CashBalanceService), bukan untuk total Cash In/Out.
     * Baris Top Up ditandai 'is_transfer' => true.
     */
    public function accountLedger(array $filters = []): Collection
    {
        return $this->ledger($filters)
            ->merge($this->transferRows($filters['from'] ?? null, $filters['to'] ?? null))
            ->merge($this->internalTransferRows($filters['from'] ?? null, $filters['to'] ?? null))
            ->values();
    }

    /**
     * Ringkasan sesuai formula M-01:
     *     Net Cash Flow = Total Cash Inflow - Total Cash Outflow
     *
     * Dihitung dari koleksi yang SAMA dengan yang ditampilkan di tabel, bukan
     * dari query terpisah — supaya angka ringkasan tidak pernah bercerita lain
     * dari daftar di bawahnya.
     */
    public function summarize(Collection $ledger): array
    {
        $in  = $ledger->where('direction', 'IN');
        $out = $ledger->where('direction', 'OUT');

        $totalIn  = (int) $in->sum('amount');
        $totalOut = (int) $out->sum('amount');

        return [
            'total_in'          => $totalIn,
            'total_out'         => $totalOut,
            'net'               => $totalIn - $totalOut,
            'count'             => $ledger->count(),
            'count_in'          => $in->count(),
            'count_out'         => $out->count(),
            'by_module'         => $this->breakdownBy($ledger, 'source_module'),
            'by_category'       => $this->breakdownBy($ledger, 'category'),
        ];
    }

    /** Rekap per periode (YYYY-MM) untuk grafik & "Period Summary". */
    public function monthlySeries(Collection $ledger): Collection
    {
        return $ledger
            ->groupBy(fn ($r) => $r['date']->format('Y-m'))
            ->map(function ($rows, $period) {
                $in  = (int) $rows->where('direction', 'IN')->sum('amount');
                $out = (int) $rows->where('direction', 'OUT')->sum('amount');

                return [
                    'period'    => $period,
                    'total_in'  => $in,
                    'total_out' => $out,
                    'net'       => $in - $out,
                    'count'     => $rows->count(),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | SUMBER
    |--------------------------------------------------------------------------
    */

    /** Uang MASUK — pelunasan invoice. */
    private function cashReceiptRows(?string $from, ?string $to): Collection
    {
        return CashReceipt::with(['invoice.po.customer', 'sourceFund:id,name'])
            ->when($from, fn ($q) => $q->whereDate('received_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('received_date', '<=', $to))
            ->get()
            ->map(fn ($cr) => [
                'date'             => $cr->received_date,
                'direction'        => 'IN',
                'category'         => 'Pelunasan Invoice',
                // withholding_tax di cash receipt adalah PPh yang disetor
                // customer — bukan uang yang masuk rekening, jadi TIDAK ikut
                // dihitung sebagai arus kas masuk (beda dengan perhitungan
                // "sudah tertagih" di modul Invoice).
                'amount'           => (int) $cr->amount,
                'description'      => 'Penerimaan invoice ' . ($cr->invoice->code ?? '-')
                                      . ($cr->invoice?->po?->customer?->name ? ' — ' . $cr->invoice->po->customer->name : ''),
                'source_module'    => self::MODULE_CASH_RECEIPT,
                'source_id'        => $cr->id,
                'reference_number' => $cr->invoice->code ?? '-',
                'source_fund_id'   => $cr->source_fund_id,
                'source_fund'      => $cr->sourceFund->name ?? '-',
                'url'              => $cr->invoice_id ? url('/invoice-cash-receipt/' . $cr->invoice_id) : null,
                'status'           => 'POSTED',
            ]);
    }

    /**
     * Uang KELUAR — batch Treasury yang sudah benar-benar dibayar.
     *
     * Hanya status PAID: batch yang masih menunggu persetujuan belum
     * memindahkan uang. TOP_UP dikecualikan (lihat catatan kelas).
     */
    private function treasuryRows(?string $from, ?string $to): Collection
    {
        return DailyTransaction::with(['sourceFund:id,name'])
            ->where('status', 'PAID')
            ->where('type', 'CASH_OUT')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->map(fn ($dt) => [
                'date'             => $dt->date,
                'direction'        => 'OUT',
                'category'         => 'Pembayaran Treasury',
                // captured_amount = nominal yang benar-benar keluar (sudah
                // termasuk charge bank kalau ada); fallback ke charge untuk
                // baris lama yang belum punya captured_amount.
                'amount'           => (int) ($dt->captured_amount ?: $dt->charge),
                'description'      => $dt->description ?: ('Batch pembayaran ' . $dt->code),
                'source_module'    => self::MODULE_TREASURY,
                'source_id'        => $dt->id,
                'reference_number' => $dt->code,
                'source_fund_id'   => $dt->source_fund_id,
                'source_fund'      => $dt->sourceFund->name ?? '-',
                'url'              => url('/daily-transaction/' . $dt->id),
                'status'           => 'POSTED',
            ]);
    }

    /** Dua sisi Top Up yang sudah PAID (lihat accountLedger). */
    private function transferRows(?string $from, ?string $to): Collection
    {
        return DailyTransaction::with(['sourceFund:id,name', 'toSourceFund:id,name'])
            ->where('status', 'PAID')
            ->where('type', 'TOP_UP')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->flatMap(function ($dt) {
                $base = [
                    'date'             => $dt->date,
                    'category'         => 'Top Up (transfer internal)',
                    'amount'           => (int) $dt->captured_amount,
                    'description'      => $dt->description ?: ('Top Up ' . $dt->code),
                    'source_module'    => self::MODULE_TREASURY,
                    'source_id'        => $dt->id,
                    'reference_number' => $dt->code,
                    'url'              => url('/daily-transaction/' . $dt->id),
                    'status'           => 'POSTED',
                    'is_transfer'      => true,
                ];

                return [
                    $base + ['direction' => 'OUT', 'source_fund_id' => $dt->source_fund_id,
                             'source_fund' => $dt->sourceFund->name ?? '-'],
                    $base + ['direction' => 'IN', 'source_fund_id' => $dt->to_source_fund_id,
                             'source_fund' => $dt->toSourceFund->name ?? '-'],
                ];
            });
    }

    /**
     * Biaya Internal Transfer (APPROVED): pengeluaran NYATA dari rekening asal,
     * dicatat terpisah dari nominal transfer (yang netral di konsolidasi).
     */
    private function transferFeeRows(?string $from, ?string $to): Collection
    {
        return CashTransfer::with('fromFund:id,name')
            ->where('status', CashTransfer::APPROVED)
            ->where('fee', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('transfer_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transfer_date', '<=', $to))
            ->get()
            ->map(fn ($t) => [
                'date'             => $t->transfer_date,
                'direction'        => 'OUT',
                'category'         => 'Biaya Transfer',
                'amount'           => (int) $t->fee,
                'description'      => 'Biaya transfer ' . $t->transfer_number,
                'source_module'    => self::MODULE_TRANSFER,
                'source_id'        => $t->id,
                'reference_number' => $t->transfer_number . '-FEE',
                'source_fund_id'   => $t->from_fund_id,
                'source_fund'      => $t->fromFund->name ?? '-',
                'url'              => url('/cash-transfers/' . $t->id),
                'status'           => 'POSTED',
            ]);
    }

    /** Dua sisi Internal Transfer APPROVED (hanya untuk saldo per rekening). */
    private function internalTransferRows(?string $from, ?string $to): Collection
    {
        return CashTransfer::with(['fromFund:id,name', 'toFund:id,name'])
            ->where('status', CashTransfer::APPROVED)
            ->when($from, fn ($q) => $q->whereDate('transfer_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transfer_date', '<=', $to))
            ->get()
            ->flatMap(function ($t) {
                $base = [
                    'date'             => $t->transfer_date,
                    'category'         => 'Internal Transfer',
                    'amount'           => (int) $t->amount,
                    'description'      => $t->description ?: ('Internal transfer ' . $t->transfer_number),
                    'source_module'    => self::MODULE_TRANSFER,
                    'source_id'        => $t->id,
                    'reference_number' => $t->transfer_number,
                    'url'              => url('/cash-transfers/' . $t->id),
                    'status'           => 'POSTED',
                    'is_transfer'      => true,
                ];

                return [
                    $base + ['direction' => 'OUT', 'source_fund_id' => $t->from_fund_id, 'source_fund' => $t->fromFund->name ?? '-'],
                    $base + ['direction' => 'IN', 'source_fund_id' => $t->to_fund_id, 'source_fund' => $t->toFund->name ?? '-'],
                ];
            });
    }

    /** Cash Adjustment APPROVED. */
    private function adjustmentRows(?string $from, ?string $to): Collection
    {
        return CashAdjustment::with('sourceFund:id,name')
            ->where('status', CashAdjustment::APPROVED)
            ->when($from, fn ($q) => $q->whereDate('effective_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('effective_date', '<=', $to))
            ->get()
            ->map(fn ($a) => [
                'date'             => $a->effective_date,
                'direction'        => $a->direction,
                'category'         => 'Adjustment: ' . $a->type_label,
                'amount'           => (int) $a->amount,
                'description'      => $a->reason,
                'source_module'    => self::MODULE_ADJUSTMENT,
                'source_id'        => $a->id,
                'reference_number' => $a->adjustment_number,
                'source_fund_id'   => $a->source_fund_id,
                'source_fund'      => $a->sourceFund->name ?? '-',
                'url'              => url('/cash-adjustments/' . $a->id),
                'status'           => 'POSTED',
            ]);
    }

    /** Entri manual yang sudah POSTED — satu baris per detail. */
    private function manualRows(?string $from, ?string $to): Collection
    {
        return CashFlow::with(['details', 'sourceFund:id,name'])
            ->where('status', 'POSTED')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->flatMap(fn ($cf) => $cf->details->map(fn ($d) => [
                'date'             => $cf->date,
                'direction'        => $d->direction,
                'detail_id'        => $d->id, // pembeda baris pada dokumen yang sama (kunci rekonsiliasi)
                'category'         => $d->category_label,
                'amount'           => (int) $d->amount,
                'description'      => $d->description ?: $cf->description,
                'source_module'    => self::MODULE_MANUAL,
                'source_id'        => $cf->id,
                'reference_number' => $d->reference_number ?: $cf->code,
                'source_fund_id'   => $cf->source_fund_id,
                'source_fund'      => $cf->sourceFund->name ?? '-',
                'url'              => url('/cash-management/' . $cf->id),
                'status'           => $cf->status,
            ]));
    }

    /** @return array<string,array{label:string,in:int,out:int,count:int}> */
    private function breakdownBy(Collection $ledger, string $key): array
    {
        return $ledger
            ->groupBy($key)
            ->map(fn ($rows, $label) => [
                'label' => (string) $label,
                'in'    => (int) $rows->where('direction', 'IN')->sum('amount'),
                'out'   => (int) $rows->where('direction', 'OUT')->sum('amount'),
                'count' => $rows->count(),
            ])
            ->sortByDesc(fn ($r) => $r['in'] + $r['out'])
            ->all();
    }
}
