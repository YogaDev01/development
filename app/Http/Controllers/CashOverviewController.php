<?php

namespace App\Http\Controllers;

use App\Services\CashBalanceService;
use App\Services\CashFlowService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Cash & Bank — Cash Overview (read-only).
 *
 * Tidak mencatat apa pun. Semua angka berasal dari CashFlowService (buku besar
 * gabungan) lewat CashBalanceService, sama dengan halaman Cashflow, sehingga
 * kedua halaman tidak pernah berbeda angka.
 */
class CashOverviewController extends Controller
{
    private const PRESETS = [
        'today'      => 'Hari ini',
        'week'       => 'Minggu ini',
        'month'      => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'year'       => 'Tahun ini',
        'custom'     => 'Rentang tanggal',
    ];

    public function __construct(
        private CashFlowService $flow,
        private CashBalanceService $balances,
        private \App\Services\ReconciliationService $recon,
    ) {
    }

    public function index(Request $request)
    {
        [$preset, $from, $to] = $this->resolvePeriod($request);

        // Saldo aktual per akhir periode.
        $snapshot = $this->balances->snapshot($to->toDateString());
        $accounts = $snapshot['accounts'];

        // Arus kas periode (konsolidasi; Top Up tidak dihitung) — definisi
        // yang sama dengan halaman Cashflow.
        $periodLedger = $this->flow->ledger(['from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $period       = $this->flow->summarize($periodLedger);

        // Cash In vs Cash Out 6 bulan terakhir sampai akhir periode.
        $monthly = $this->flow->monthlySeries(
            $this->flow->ledger([
                'from' => $to->copy()->subMonths(5)->startOfMonth()->toDateString(),
                'to'   => $to->toDateString(),
            ])
        );

        return view('cash-overview.index', [
            'presets'    => self::PRESETS,
            'preset'     => $preset,
            'from'       => $from,
            'to'         => $to,
            'snapshot'   => $snapshot,
            'accounts'   => $accounts,
            'kpi'        => $snapshot['summary'],
            'period'     => $period,
            'monthly'    => $monthly,
            'daily'      => $this->dailySeries($periodLedger, $from, $to),
            'trend'      => $this->balanceTrend($accounts, $snapshot['summary']['total'], $from, $to),
            'recent'     => $this->flow->ledger(['to' => $to->toDateString()])->take(15),
            'attention'  => $this->balances->attention($snapshot),
            'recon'      => $this->recon->overview(),
            'canDetail'  => (bool) auth()->user()?->effectiveRole()?->hasMenu('bank-accounts')
                            || auth()->user()?->hasRole('superadmin'),
        ]);
    }

    /** @return array{0:string,1:Carbon,2:Carbon} */
    private function resolvePeriod(Request $request): array
    {
        $preset = $request->query('period', 'month');
        if (! isset(self::PRESETS[$preset])) {
            $preset = 'month';
        }

        $now = now();
        switch ($preset) {
            case 'today':
                [$from, $to] = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
                break;
            case 'week':
                [$from, $to] = [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
                break;
            case 'last_month':
                [$from, $to] = [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()];
                break;
            case 'year':
                [$from, $to] = [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
                break;
            case 'custom':
                try {
                    $from = Carbon::parse($request->query('from'))->startOfDay();
                    $to   = Carbon::parse($request->query('to'))->endOfDay();
                } catch (\Throwable) {
                    $from = $now->copy()->startOfMonth();
                    $to   = $now->copy()->endOfMonth();
                }
                if ($to->lt($from)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                break;
            default:
                [$from, $to] = [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        return [$preset, $from, $to];
    }

    /** Cash In/Out per hari di dalam periode (maks. 62 hari terakhir). */
    private function dailySeries($ledger, Carbon $from, Carbon $to): array
    {
        $byDay = $ledger->groupBy(fn ($r) => $r['date']->format('Y-m-d'));
        $out   = [];
        $start = $from->copy();
        if ($start->diffInDays($to) > 61) {
            $start = $to->copy()->subDays(61)->startOfDay();
        }
        for ($d = $start->copy(); $d->lte($to); $d->addDay()) {
            $rows = $byDay->get($d->format('Y-m-d'), collect());
            $out[] = [
                'date' => $d->format('Y-m-d'),
                'in'   => (int) $rows->where('direction', 'IN')->sum('amount'),
                'out'  => (int) $rows->where('direction', 'OUT')->sum('amount'),
            ];
        }

        return $out;
    }

    /**
     * Saldo konsolidasi per hari. Dihitung MUNDUR dari saldo akhir periode memakai
     * baris yang sama dengan yang membentuk saldo (sudah disaring tanggal efektif
     * saldo awal), jadi titik terakhir selalu sama dengan KPI Total Balance.
     */
    private function balanceTrend($accounts, int $endBalance, Carbon $from, Carbon $to): array
    {
        $net = $accounts->flatMap(fn ($a) => $a['rows'])
            ->groupBy(fn ($r) => $r['date']->format('Y-m-d'))
            ->map(fn ($rows) => (int) $rows->where('direction', 'IN')->sum('amount')
                              - (int) $rows->where('direction', 'OUT')->sum('amount'));

        $start = $from->copy();
        if ($start->diffInDays($to) > 61) {
            $start = $to->copy()->subDays(61)->startOfDay();
        }

        $points  = [];
        $balance = $endBalance;
        for ($d = $to->copy()->startOfDay(); $d->gte($start); $d->subDay()) {
            $points[] = ['date' => $d->format('Y-m-d'), 'balance' => $balance];
            $balance -= (int) ($net->get($d->format('Y-m-d'), 0));
        }

        return array_reverse($points);
    }
}
