<?php

namespace App\Services;

use App\Models\CashOpeningBalance;
use App\Models\SourceFund;
use Illuminate\Support\Collection;

/**
 * Cash & Bank — saldo aktual per rekening dan konsolidasi.
 *
 * BUKAN ledger baru. Seluruh pergerakan dibaca dari CashFlowService (Cash
 * Receipt, Treasury PAID, Cash Flow POSTED, plus dua sisi Top Up), sehingga
 * Cash Overview dan halaman Cashflow selalu memakai sumber yang sama.
 *
 *   Saldo = Saldo Awal (APPROVED) + Cash In − Cash Out
 *
 * - Hanya saldo awal berstatus APPROVED yang dihitung. Rekening tanpa saldo awal
 *   approved dihitung dari 0 dan ditandai 'has_opening' = false (Attention).
 * - Transaksi sebelum tanggal efektif saldo awal tidak dihitung (sudah tercakup
 *   di saldo awal).
 * - Top Up: keluar dari satu rekening, masuk ke rekening lain → total
 *   konsolidasi tidak berubah.
 * - Draft/pending/rejected/cancelled/belum dibayar tidak masuk ledger.
 * - Baris tanpa rekening (source_fund_id null) tidak bisa dialokasikan; dilaporkan
 *   terpisah di 'unassigned', tidak ikut saldo.
 */
class CashBalanceService
{
    public function __construct(private CashFlowService $flow)
    {
    }

    /**
     * @return array{accounts:Collection, unassigned:array{in:int,out:int,count:int}, summary:array}
     */
    public function snapshot(?string $asOf = null): array
    {
        $funds = SourceFund::with(['bank:id,name', 'openingBalance'])->orderBy('name')->get();
        $rows  = $this->flow->accountLedger(['to' => $asOf]);
        $byFund = $rows->groupBy(fn ($r) => $r['source_fund_id'] ?? 0);

        $accounts = $funds->map(function (SourceFund $fund) use ($byFund) {
            $ob      = $fund->openingBalance;
            $hasOpen = $ob && $ob->isApproved();
            $opening = $hasOpen ? (int) $ob->amount : 0;

            $mine = $byFund->get($fund->id, collect());
            if ($hasOpen) {
                $from = $ob->effective_date->format('Y-m-d');
                $mine = $mine->filter(fn ($r) => $r['date']->format('Y-m-d') >= $from);
            }

            $in  = (int) $mine->where('direction', 'IN')->sum('amount');
            $out = (int) $mine->where('direction', 'OUT')->sum('amount');

            return [
                'fund'        => $fund,
                'opening'     => $opening,
                'opening_status' => $ob?->status,
                'has_opening' => $hasOpen,
                'in'          => $in,
                'out'         => $out,
                'balance'     => $opening + $in - $out,
                'rows'        => $mine,
                'active'      => strtoupper((string) $fund->status) === 'ACTIVE',
            ];
        });

        $un = $byFund->get(0, collect());

        $summary = $this->summarize($accounts);

        return [
            'accounts'   => $accounts,
            'unassigned' => [
                'in'    => (int) $un->where('direction', 'IN')->sum('amount'),
                'out'   => (int) $un->where('direction', 'OUT')->sum('amount'),
                'count' => $un->count(),
            ],
            'summary'    => $summary,
        ];
    }

    /** KPI saldo konsolidasi. */
    public function summarize(Collection $accounts): array
    {
        $sum = fn (callable $f) => (int) $accounts->filter($f)->sum('balance');
        $type = fn ($a) => $a['fund']->account_type;

        return [
            'total'        => (int) $accounts->sum('balance'),
            'bank'         => $sum(fn ($a) => $type($a) === 'BANK'),
            'cash_on_hand' => $sum(fn ($a) => in_array($type($a), ['PETTY_CASH', 'CASH_ON_HAND'], true)),
            'other'        => $sum(fn ($a) => $type($a) === 'OTHER_CASH_EQUIVALENT'),
        ];
    }

    /** Hal-hal yang perlu perhatian (Attention Required). */
    public function attention(array $snapshot): array
    {
        $accounts = $snapshot['accounts'];
        $active   = $accounts->where('active', true);

        return [
            'no_opening' => $active->where('has_opening', false)
                ->filter(fn ($a) => $a['opening_status'] !== CashOpeningBalance::PENDING)->values(),
            'pending_opening' => $accounts->where('opening_status', CashOpeningBalance::PENDING)->values(),
            'negative'   => $accounts->filter(fn ($a) => $a['balance'] < 0)->values(),
            'unassigned' => $snapshot['unassigned'],
        ];
    }

    /**
     * Saldo penutup per bulan (YYYY-MM) untuk satu rekening, dari baris ledger
     * yang sudah dihitung snapshot().
     *
     * @return Collection<int,array{period:string,in:int,out:int,closing:int}>
     */
    public function monthlyTrend(array $account): Collection
    {
        $running = $account['opening'];

        return $account['rows']
            ->groupBy(fn ($r) => $r['date']->format('Y-m'))
            ->sortKeys()
            ->map(function ($rows, $period) use (&$running) {
                $in  = (int) $rows->where('direction', 'IN')->sum('amount');
                $out = (int) $rows->where('direction', 'OUT')->sum('amount');
                $running += $in - $out;

                return ['period' => $period, 'in' => $in, 'out' => $out, 'closing' => $running];
            })
            ->values();
    }

    /**
     * Dokumen bertanggal SEBELUM tanggal efektif saldo awal approved tidak akan
     * dihitung ke saldo (sudah tercakup saldo awal). Kembalikan pesan galat bila
     * itu terjadi, supaya pengguna tidak membuat dokumen yang "hilang".
     */
    public function dateBeforeOpening(int $fundId, string $date): ?string
    {
        $ob = CashOpeningBalance::where('source_fund_id', $fundId)
            ->where('status', CashOpeningBalance::APPROVED)->first();

        if ($ob && $date < $ob->effective_date->format('Y-m-d')) {
            return 'Tanggal sebelum saldo awal rekening ('
                . $ob->effective_date->format('d M Y') . ') — tidak akan dihitung ke saldo.';
        }

        return null;
    }

    /**
     * Pesan galat bila tanggal jatuh di periode yang sudah direkonsiliasi dan
     * disetujui (terkunci). Dipakai dokumen Cash & Bank baru; modul lama
     * (Treasury, Cash Receipt) belum dikunci oleh periode ini.
     */
    public function dateInLockedPeriod(int $fundId, string $date): ?string
    {
        $rec = \App\Models\CashReconciliation::where('source_fund_id', $fundId)
            ->where('status', \App\Models\CashReconciliation::APPROVED)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->first();

        return $rec
            ? "Periode {$rec->period_start->format('d M Y')} – {$rec->period_end->format('d M Y')} sudah direkonsiliasi dan terkunci ({$rec->number})."
            : null;
    }

    /** Saldo satu rekening saat ini (untuk kontrol saldo negatif). */
    public function balanceOf(int $fundId): int
    {
        $acc = $this->snapshot()['accounts']->first(fn ($a) => $a['fund']->id === $fundId);

        return (int) ($acc['balance'] ?? 0);
    }
}
