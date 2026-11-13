<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\CashAdjustment;
use App\Models\CashReconciliation;
use App\Models\CashTransfer;
use App\Models\DailyTransaction;
use App\Models\Forecast;
use App\Models\Invoice;
use App\Models\SourceFund;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cash & Bank Fase 4 — laporan kas. TIDAK menghitung saldo sendiri:
 * semua angka kas aktual berasal dari CashBalanceService / CashFlowService
 * (sumber yang sama dengan Cash Overview dan Cashflow), sehingga laporan,
 * Overview, dan Cashflow tidak bisa berbeda.
 *
 * Bentuk hasil satu laporan:
 *   title, description, columns[{key,label,type: text|money|date|int}], rows[], totals[]|null, notes[]
 */
class CashReportService
{
    public const REPORTS = [
        'position'       => 'Cash Position Report',
        'bank-balance'   => 'Bank Balance Report',
        'transactions'   => 'Cash Transaction Report',
        'in-out'         => 'Cash In & Cash Out Report',
        'cashflow'       => 'Cashflow Report',
        'reconciliation' => 'Bank Reconciliation Report',
        'transfers'      => 'Internal Transfer Report',
        'adjustments'    => 'Cash Adjustment Report',
        'forecast'       => 'Cash Forecast Report',
    ];

    public function __construct(
        private CashFlowService $flow,
        private CashBalanceService $balances,
        private ReconciliationService $recon,
    ) {
    }

    /** @param array{from:string,to:string,fund:?int,days:int} $f */
    public function build(string $key, array $f): ?array
    {
        return match ($key) {
            'position'       => $this->position($f),
            'bank-balance'   => $this->bankBalance($f),
            'transactions'   => $this->transactions($f),
            'in-out'         => $this->inOut($f),
            'cashflow'       => $this->cashflow($f),
            'reconciliation' => $this->reconciliation($f),
            'transfers'      => $this->transfers($f),
            'adjustments'    => $this->adjustments($f),
            'forecast'       => $this->forecast($f),
            default          => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | 1–2. POSISI & SALDO BANK
    |--------------------------------------------------------------------------
    */

    private function accountColumns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Rekening', 'type' => 'text'],
            ['key' => 'type', 'label' => 'Tipe', 'type' => 'text'],
            ['key' => 'bank', 'label' => 'Bank', 'type' => 'text'],
            ['key' => 'opening', 'label' => 'Saldo Awal', 'type' => 'money'],
            ['key' => 'in', 'label' => 'Cash In', 'type' => 'money'],
            ['key' => 'out', 'label' => 'Cash Out', 'type' => 'money'],
            ['key' => 'balance', 'label' => 'Saldo', 'type' => 'money'],
        ];
    }

    private function accountRows(array $f, ?string $type = null): Collection
    {
        return $this->balances->snapshot($f['to'])['accounts']
            ->when($f['fund'], fn ($c) => $c->filter(fn ($a) => $a['fund']->id === $f['fund']))
            ->when($type, fn ($c) => $c->filter(fn ($a) => $a['fund']->account_type === $type))
            ->map(fn ($a) => [
                'code' => $a['fund']->code ?? '-', 'name' => $a['fund']->name, 'type' => $a['fund']->type_label,
                'bank' => $a['fund']->bank->name ?? '-', 'opening' => $a['opening'], 'in' => $a['in'],
                'out' => $a['out'], 'balance' => $a['balance'], 'has_opening' => $a['has_opening'],
            ])->values();
    }

    private function position(array $f): array
    {
        $rows = $this->accountRows($f);

        return [
            'title' => self::REPORTS['position'],
            'description' => 'Saldo kas & bank per ' . $f['to'] . ' (aktual; draft/pending/belum dibayar tidak dihitung).',
            'columns' => $this->accountColumns(),
            'rows' => $rows->all(),
            'totals' => ['name' => 'TOTAL'] + $this->sum($rows, ['opening', 'in', 'out', 'balance']),
            'notes' => $this->openingNotes($rows),
        ];
    }

    private function bankBalance(array $f): array
    {
        $rows = $this->accountRows($f, 'BANK');
        $ov   = $this->recon->overview();
        $ids  = SourceFund::whereIn('name', $rows->pluck('name'))->pluck('id', 'name');

        $rows = $rows->map(function ($r) use ($ov, $ids) {
            $id = $ids[$r['name']] ?? null;
            $r['last_recon'] = $id && $ov['last']->has($id) ? $ov['last']->get($id)->period_end->format('Y-m-d') : 'Belum pernah';

            return $r;
        });

        $cols = $this->accountColumns();
        $cols[] = ['key' => 'last_recon', 'label' => 'Rekonsiliasi Terakhir (s/d)', 'type' => 'text'];

        return [
            'title' => self::REPORTS['bank-balance'],
            'description' => 'Saldo rekening bank per ' . $f['to'] . '.',
            'columns' => $cols,
            'rows' => $rows->all(),
            'totals' => ['name' => 'TOTAL'] + $this->sum($rows, ['opening', 'in', 'out', 'balance']),
            'notes' => $this->openingNotes($rows),
        ];
    }

    private function openingNotes(Collection $rows): array
    {
        $n = $rows->where('has_opening', false)->count();

        return $n ? ["{$n} rekening belum punya saldo awal terverifikasi (saldo dihitung dari 0)."] : [];
    }

    /*
    |--------------------------------------------------------------------------
    | 3–4. TRANSAKSI & CASH IN/OUT
    |--------------------------------------------------------------------------
    */

    private function transactions(array $f): array
    {
        // accountLedger() menyaring rekening hanya pada sumber biasa; sisi transfer disaring di sini.
        $rows = $this->flow->accountLedger($this->ledgerFilters($f))
            ->when($f['fund'], fn ($c) => $c->filter(fn ($r) => (int) ($r['source_fund_id'] ?? 0) === $f['fund']))
            ->sortBy(fn ($r) => $r['date']->format('Y-m-d') . $r['reference_number'])->values()
            ->map(fn ($r) => [
                'date' => $r['date']->format('Y-m-d'), 'ref' => $r['reference_number'], 'description' => $r['description'],
                'source' => $r['category'] . (! empty($r['is_transfer']) ? ' [transfer]' : ''), 'account' => $r['source_fund'],
                'in' => $r['direction'] === 'IN' ? (int) $r['amount'] : 0,
                'out' => $r['direction'] === 'OUT' ? (int) $r['amount'] : 0,
                'transfer' => ! empty($r['is_transfer']),
            ]);

        $real = $rows->where('transfer', false);

        return [
            'title' => self::REPORTS['transactions'],
            'description' => "Seluruh pergerakan kas {$f['from']} s/d {$f['to']}.",
            'columns' => [
                ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'], ['key' => 'ref', 'label' => 'Referensi', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Deskripsi', 'type' => 'text'], ['key' => 'source', 'label' => 'Sumber', 'type' => 'text'],
                ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'],
                ['key' => 'in', 'label' => 'Cash In', 'type' => 'money'], ['key' => 'out', 'label' => 'Cash Out', 'type' => 'money'],
            ],
            'rows' => $rows->all(),
            'totals' => ['description' => 'TOTAL (tanpa sisi transfer internal)'] + $this->sum($real, ['in', 'out']),
            'notes' => ['Sisi transfer internal ditandai [transfer] dan tidak dihitung ke total Cash In/Out.'],
        ];
    }

    private function inOut(array $f): array
    {
        $ledger = $this->flow->ledger($this->ledgerFilters($f));
        $rows = $ledger->groupBy('category')->map(fn ($g, $cat) => [
            'category' => $cat, 'count' => $g->count(),
            'in' => (int) $g->where('direction', 'IN')->sum('amount'), 'out' => (int) $g->where('direction', 'OUT')->sum('amount'),
        ])->sortByDesc(fn ($r) => $r['in'] + $r['out'])->values();

        $tot = $this->sum($rows, ['count', 'in', 'out']);

        return [
            'title' => self::REPORTS['in-out'],
            'description' => "Cash In & Cash Out per kategori {$f['from']} s/d {$f['to']} (konsolidasi, transfer internal tidak dihitung).",
            'columns' => [
                ['key' => 'category', 'label' => 'Kategori', 'type' => 'text'], ['key' => 'count', 'label' => 'Jumlah', 'type' => 'int'],
                ['key' => 'in', 'label' => 'Cash In', 'type' => 'money'], ['key' => 'out', 'label' => 'Cash Out', 'type' => 'money'],
            ],
            'rows' => $rows->all(),
            'totals' => ['category' => 'TOTAL (Net: ' . number_format($tot['in'] - $tot['out'], 0, ',', '.') . ')'] + $tot,
            'notes' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 5. CASHFLOW (saldo awal & akhir per bulan — konsisten dengan Cash Overview)
    |--------------------------------------------------------------------------
    */

    private function cashflow(array $f): array
    {
        $snap = $this->balances->snapshot($f['to']);
        $accs = $snap['accounts']->when($f['fund'], fn ($c) => $c->filter(fn ($a) => $a['fund']->id === $f['fund']));
        $closingEnd = (int) $accs->sum('balance');

        // Pola sama dengan Cash Overview: dihitung mundur dari saldo akhir memakai baris yang membentuk saldo itu.
        $byMonth = $accs->flatMap(fn ($a) => $a['rows'])->groupBy(fn ($r) => $r['date']->format('Y-m'));
        $months  = [];
        for ($d = Carbon::parse($f['from'])->startOfMonth(); $d->lte(Carbon::parse($f['to'])); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        $out = [];
        $closing = $closingEnd;
        // Bulan setelah rentang tidak ada (to = akhir). Mundur dari bulan terakhir.
        foreach (array_reverse($months) as $m) {
            $rows = $byMonth->get($m, collect());
            $in   = (int) $rows->where('direction', 'IN')->sum('amount');
            $outv = (int) $rows->where('direction', 'OUT')->sum('amount');
            $out[] = ['period' => $m, 'opening' => $closing - ($in - $outv), 'in' => $in, 'out' => $outv, 'net' => $in - $outv, 'closing' => $closing];
            $closing -= ($in - $outv);
        }
        $rows = collect(array_reverse($out));

        return [
            'title' => self::REPORTS['cashflow'],
            'description' => "Saldo awal, Cash In/Out (termasuk sisi Top Up/transfer antar rekening hanya bila satu rekening difilter), dan saldo akhir per bulan.",
            'columns' => [
                ['key' => 'period', 'label' => 'Bulan', 'type' => 'text'], ['key' => 'opening', 'label' => 'Saldo Awal', 'type' => 'money'],
                ['key' => 'in', 'label' => 'Cash In', 'type' => 'money'], ['key' => 'out', 'label' => 'Cash Out', 'type' => 'money'],
                ['key' => 'net', 'label' => 'Net', 'type' => 'money'], ['key' => 'closing', 'label' => 'Saldo Akhir', 'type' => 'money'],
            ],
            'rows' => $rows->all(),
            'totals' => null,
            'notes' => [
                'Saldo akhir bulan terakhir = KPI Total Cash & Bank Balance di Cash Overview pada tanggal yang sama.',
                'Transaksi tanpa rekening (' . $snap['unassigned']['count'] . ' baris) tidak ikut saldo rekening.',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 6. REKONSILIASI
    |--------------------------------------------------------------------------
    */

    private function reconciliation(array $f): array
    {
        $recs = CashReconciliation::with('sourceFund:id,name')
            ->when($f['fund'], fn ($q) => $q->where('source_fund_id', $f['fund']))
            ->whereDate('period_end', '>=', $f['from'])->whereDate('period_start', '<=', $f['to'])
            ->orderByDesc('period_end')->limit(100)->get();

        $rows = $recs->map(function ($r) {
            $s = $this->recon->summary($r);

            return [
                'number' => $r->number, 'account' => $r->sourceFund->name ?? '-',
                'period' => $r->period_start->format('Y-m-d') . ' s/d ' . $r->period_end->format('Y-m-d'),
                'statement' => $s['statement_balance'], 'ledger' => $s['ledger_balance'],
                'outdep' => $s['outstanding_deposits'], 'outpay' => $s['outstanding_payments'],
                'diff' => $s['difference'], 'unresolved' => $s['unresolved_lines'],
                'status' => $r->status . ($r->exception_reason ? ' (pengecualian)' : ''),
            ];
        });

        return [
            'title' => self::REPORTS['reconciliation'],
            'description' => 'Rekonsiliasi bank yang periodenya beririsan dengan rentang tanggal (maks. 100).',
            'columns' => [
                ['key' => 'number', 'label' => 'No.', 'type' => 'text'], ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'],
                ['key' => 'period', 'label' => 'Periode', 'type' => 'text'], ['key' => 'statement', 'label' => 'Saldo Rek. Koran', 'type' => 'money'],
                ['key' => 'ledger', 'label' => 'Saldo Ledger', 'type' => 'money'], ['key' => 'outdep', 'label' => 'Outstanding Deposits', 'type' => 'money'],
                ['key' => 'outpay', 'label' => 'Outstanding Payments', 'type' => 'money'], ['key' => 'diff', 'label' => 'Selisih', 'type' => 'money'],
                ['key' => 'unresolved', 'label' => 'Baris Belum Cocok', 'type' => 'int'], ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
            ],
            'rows' => $rows->all(),
            'totals' => null,
            'notes' => ['Angka dihitung ulang saat laporan dibuka (rumus ReconciliationService::summary).'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 7–8. TRANSFER & ADJUSTMENT
    |--------------------------------------------------------------------------
    */

    private function transfers(array $f): array
    {
        $rows = CashTransfer::with(['fromFund:id,name', 'toFund:id,name', 'creator:id,username', 'approver:id,username'])
            ->whereDate('transfer_date', '>=', $f['from'])->whereDate('transfer_date', '<=', $f['to'])
            ->when($f['fund'], fn ($q) => $q->where(fn ($w) => $w->where('from_fund_id', $f['fund'])->orWhere('to_fund_id', $f['fund'])))
            ->orderBy('transfer_date')->orderBy('id')->get()
            ->map(fn ($t) => [
                'number' => $t->transfer_number, 'date' => $t->transfer_date->format('Y-m-d'), 'from' => $t->fromFund->name ?? '-',
                'to' => $t->toFund->name ?? '-', 'amount' => $t->amount, 'fee' => $t->fee, 'status' => $t->status,
                'creator' => $t->creator->username ?? '-', 'approver' => $t->approver->username ?? '-',
            ]);
        $ok = $rows->where('status', 'APPROVED');

        return [
            'title' => self::REPORTS['transfers'],
            'description' => "Internal Transfer {$f['from']} s/d {$f['to']}.",
            'columns' => [
                ['key' => 'number', 'label' => 'No.', 'type' => 'text'], ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'from', 'label' => 'Dari', 'type' => 'text'], ['key' => 'to', 'label' => 'Ke', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Nominal', 'type' => 'money'], ['key' => 'fee', 'label' => 'Biaya', 'type' => 'money'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'text'], ['key' => 'creator', 'label' => 'Dibuat', 'type' => 'text'],
                ['key' => 'approver', 'label' => 'Diputuskan', 'type' => 'text'],
            ],
            'rows' => $rows->all(),
            'totals' => ['from' => 'TOTAL DISETUJUI'] + $this->sum($ok, ['amount', 'fee']),
            'notes' => [],
        ];
    }

    private function adjustments(array $f): array
    {
        $rows = CashAdjustment::with(['sourceFund:id,name', 'creator:id,username', 'approver:id,username'])
            ->whereDate('effective_date', '>=', $f['from'])->whereDate('effective_date', '<=', $f['to'])
            ->when($f['fund'], fn ($q) => $q->where('source_fund_id', $f['fund']))
            ->orderBy('effective_date')->orderBy('id')->get()
            ->map(fn ($a) => [
                'number' => $a->adjustment_number, 'date' => $a->effective_date->format('Y-m-d'), 'account' => $a->sourceFund->name ?? '-',
                'type' => $a->type_label, 'in' => $a->direction === 'IN' ? $a->amount : 0, 'out' => $a->direction === 'OUT' ? $a->amount : 0,
                'reason' => $a->reason, 'status' => $a->status, 'creator' => $a->creator->username ?? '-', 'approver' => $a->approver->username ?? '-',
            ]);
        $ok = $rows->where('status', 'APPROVED');

        return [
            'title' => self::REPORTS['adjustments'],
            'description' => "Cash Adjustment {$f['from']} s/d {$f['to']}.",
            'columns' => [
                ['key' => 'number', 'label' => 'No.', 'type' => 'text'], ['key' => 'date', 'label' => 'Tgl Efektif', 'type' => 'date'],
                ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'], ['key' => 'type', 'label' => 'Jenis', 'type' => 'text'],
                ['key' => 'in', 'label' => 'Masuk', 'type' => 'money'], ['key' => 'out', 'label' => 'Keluar', 'type' => 'money'],
                ['key' => 'reason', 'label' => 'Alasan', 'type' => 'text'], ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                ['key' => 'creator', 'label' => 'Dibuat', 'type' => 'text'], ['key' => 'approver', 'label' => 'Diputuskan', 'type' => 'text'],
            ],
            'rows' => $rows->all(),
            'totals' => ['type' => 'TOTAL DISETUJUI'] + $this->sum($ok, ['in', 'out']),
            'notes' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 9. FORECAST — kas aktual ≠ pergerakan terkonfirmasi ≠ forecast
    |--------------------------------------------------------------------------
    */

    /**
     * Tiga lapis yang TIDAK pernah dicampur:
     *   A. Kas aktual            — saldo rekening (CashBalanceService).
     *   B. Terkonfirmasi (masa depan) — piutang invoice, batch Treasury DONE, CR APPROVED belum masuk batch.
     *   C. Forecast               — sisa rencana (amount − realisasi) forecast ACTIVE; asumsi, bukan komitmen.
     */
    public function outlook(int $days): array
    {
        $today = now()->startOfDay();
        $until = $today->copy()->addDays($days);

        $actual = $this->balances->snapshot()['summary']['total'];

        // B1. Piutang (invoice belum lunas). Rumus "diterima" = Invoice::hitungDiterima (sumber tunggal).
        $inv = Invoice::with('cashReceipts')->whereIn('status', Invoice::UNPAID_STATUSES)->get(['id', 'status', 'captured_total', 'withholding_tax', 'actual_due_date']);
        $recv = ['total' => 0, 'overdue' => 0, 'within' => 0, 'later' => 0, 'undated' => 0];
        foreach ($inv as $i) {
            $out = max(0, (int) $i->captured_total - Invoice::hitungDiterima(
                (int) $i->cashReceipts->sum('amount'), (int) $i->cashReceipts->sum('withholding_tax'), (int) $i->withholding_tax));
            if ($out === 0) {
                continue;
            }
            $recv['total'] += $out;
            $due = $i->actual_due_date;
            if (! $due) {
                $recv['undated'] += $out;
            } elseif ($due->lt($today)) {
                $recv['overdue'] += $out;
            } elseif ($due->lte($until)) {
                $recv['within'] += $out;
            } else {
                $recv['later'] += $out;
            }
        }

        // B2. Pembayaran terjadwal: batch Treasury CASH_OUT yang sudah disetujui (DONE) tetapi belum PAID.
        $scheduled = (int) DailyTransaction::where('type', 'CASH_OUT')->where('status', 'DONE')->sum('captured_amount');
        $awaitingBatch = (int) DailyTransaction::where('type', 'CASH_OUT')->where('status', 'PENDING_APPROVER')->sum('captured_amount');

        // B3. CR APPROVED yang belum masuk batch (QUEUED sudah terwakili batch). Nominal = kolom amount (item INCLUDED).
        $approvedUnpaid = (int) BudgetRequest::where('status', 'APPROVED')->sum('amount');

        // C. Forecast ACTIVE: sisa rencana pada forecast yang masih berlaku sampai horizon.
        $fIn = 0;
        $fOut = 0;
        Forecast::with('details')->where('status', 'ACTIVE')
            ->whereDate('date_end', '>=', $today)->whereDate('date_start', '<=', $until)->get()
            ->each(function ($fc) use (&$fIn, &$fOut) {
                foreach ($fc->details as $d) {
                    $remaining = (int) $d->amount - (int) $d->actual_amount;
                    $d->type === 'IN' ? $fIn += $remaining : $fOut += $remaining;
                }
            });

        $confirmedNet  = $recv['within'] - $scheduled - $approvedUnpaid;
        $confirmedProj = $actual + $confirmedNet;

        return compact('days', 'actual', 'recv', 'scheduled', 'awaitingBatch', 'approvedUnpaid', 'fIn', 'fOut', 'confirmedNet', 'confirmedProj') + [
            'forecast_net'  => $fIn - $fOut,
            'forecast_proj' => $confirmedProj + $fIn - $fOut,
            'until'         => $until->format('Y-m-d'),
        ];
    }

    private function forecast(array $f): array
    {
        $o = $this->outlook($f['days']);

        $rows = [
            ['group' => 'A. KAS AKTUAL', 'item' => 'Saldo kas & bank saat ini', 'amount' => $o['actual'], 'note' => 'Sumber: ledger (sama dengan Cash Overview)'],
            ['group' => 'B. TERKONFIRMASI', 'item' => 'Total piutang invoice belum lunas', 'amount' => $o['recv']['total'], 'note' => 'Informasi; bukan kas'],
            ['group' => 'B. TERKONFIRMASI', 'item' => "Expected collections jatuh tempo ≤ {$o['days']} hari", 'amount' => $o['recv']['within'], 'note' => 'Masuk proyeksi'],
            ['group' => 'B. TERKONFIRMASI', 'item' => 'Piutang overdue', 'amount' => $o['recv']['overdue'], 'note' => 'Waktu tagih tidak pasti — TIDAK masuk proyeksi'],
            ['group' => 'B. TERKONFIRMASI', 'item' => 'Piutang jatuh tempo setelah horizon / tanpa tanggal', 'amount' => $o['recv']['later'] + $o['recv']['undated'], 'note' => 'TIDAK masuk proyeksi'],
            ['group' => 'B. TERKONFIRMASI', 'item' => 'Scheduled payments (batch Treasury disetujui, belum dibayar)', 'amount' => -$o['scheduled'], 'note' => 'Masuk proyeksi'],
            ['group' => 'B. TERKONFIRMASI', 'item' => 'Approved but unpaid expenses (CR APPROVED belum masuk batch)', 'amount' => -$o['approvedUnpaid'], 'note' => 'Masuk proyeksi; nominal = kolom amount CR'],
            ['group' => 'B. TERKONFIRMASI', 'item' => 'Batch menunggu approval (info)', 'amount' => -$o['awaitingBatch'], 'note' => 'Belum dikonfirmasi — TIDAK masuk proyeksi'],
            ['group' => 'B. TERKONFIRMASI', 'item' => "PROYEKSI SALDO TERKONFIRMASI s/d {$o['until']}", 'amount' => $o['confirmedProj'], 'note' => 'Aktual + collections − scheduled − approved unpaid'],
            ['group' => 'C. FORECAST', 'item' => 'Sisa forecast masuk (ACTIVE)', 'amount' => $o['fIn'], 'note' => 'Asumsi rencana, bukan komitmen'],
            ['group' => 'C. FORECAST', 'item' => 'Sisa forecast keluar (ACTIVE)', 'amount' => -$o['fOut'], 'note' => 'Asumsi rencana, bukan komitmen'],
            ['group' => 'C. FORECAST', 'item' => 'PROYEKSI SALDO TERMASUK FORECAST', 'amount' => $o['forecast_proj'], 'note' => 'Terkonfirmasi + forecast'],
        ];

        return [
            'title' => self::REPORTS['forecast'],
            'description' => "Horizon {$o['days']} hari (s/d {$o['until']}). Kas aktual, pergerakan terkonfirmasi, dan forecast dipisah; forecast tidak pernah dihitung sebagai saldo aktual.",
            'columns' => [
                ['key' => 'group', 'label' => 'Kelompok', 'type' => 'text'], ['key' => 'item', 'label' => 'Komponen', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money'], ['key' => 'note', 'label' => 'Keterangan', 'type' => 'text'],
            ],
            'rows' => $rows,
            'totals' => null,
            'notes' => [
                'Perlu konfirmasi bisnis: definisi "terkonfirmasi" (batch DONE, CR APPROVED, piutang ≤ horizon) dan nominal CR (kolom amount) belum divalidasi dengan Finance.',
                'Second Payment / expense claim yang kembali ke QUEUED tidak dihitung terpisah.',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    private function ledgerFilters(array $f): array
    {
        return ['from' => $f['from'], 'to' => $f['to'], 'source_fund_id' => $f['fund'] ? [$f['fund']] : []];
    }

    /** @return array<string,int> */
    private function sum(Collection $rows, array $keys): array
    {
        $o = [];
        foreach ($keys as $k) {
            $o[$k] = (int) $rows->sum($k);
        }

        return $o;
    }
}
