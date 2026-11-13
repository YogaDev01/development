<?php

namespace App\Services;

use App\Models\CoaAccount;
use Illuminate\Support\Facades\DB;

/**
 * Accounting Fase 4 — laporan keuangan. SELURUHNYA dari saldo buku besar
 * (jurnal POSTED/REVERSED lewat LedgerService); tidak pernah menghitung dari
 * Treasury atau Cash Receipt. Angka = sen (integer).
 *
 * Tiap laporan mengembalikan ['lines' => [...], 'checks' => [...]]. Baris:
 *   ['t' => 'section'|'row'|'total'|'grand', 'label', 'amount' (sen|null), 'account_id' (opsional)]
 * sehingga tampilan, cetak, dan CSV memakai satu bentuk data.
 *
 * Belum ada jurnal penutup tahun: laba ditahan tahun lalu dan laba berjalan di
 * Neraca DIHITUNG dari akun pendapatan/beban (tahun fiskal = 1 Januari).
 */
class FinancialStatementService
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /* ------------------------------------------------------------ LABA RUGI */

    public function incomeStatement(string $from, string $to): array
    {
        $tot = $this->ledger->totalsByAccount($from, $to);
        $sec = $this->plSections($tot, 'pd', 'pc');   // [section => [rows]] jumlah positif = sesuai sifat seksi

        $L = [];
        $revenue = $this->block($L, 'Pendapatan', $sec['REVENUE']);
        $cos = $this->block($L, 'Beban Pokok Layanan', $sec['COST_OF_SERVICES']);
        $gross = $revenue - $cos;
        $L[] = $this->line('total', 'Laba Kotor', $gross);
        $opex = $this->block($L, 'Beban Operasional', $sec['OPERATING_EXPENSE']);
        $operating = $gross - $opex;
        $L[] = $this->line('total', 'Laba Operasional', $operating);
        $oi = $this->block($L, 'Pendapatan Lain', $sec['OTHER_INCOME']);
        $oe = $this->block($L, 'Beban Lain', $sec['OTHER_EXPENSE']);
        $pbt = $operating + $oi - $oe;
        $L[] = $this->line('total', 'Laba Sebelum Pajak', $pbt);
        $tax = $this->block($L, 'Beban Pajak', $sec['TAX_EXPENSE']);
        $net = $pbt - $tax;
        $L[] = $this->line('grand', 'Laba Bersih', $net);

        return ['lines' => $L, 'checks' => [], 'net_income' => $net];
    }

    /** Laba bersih (sen) dari total debit/kredit PL: pendapatan − beban, semua seksi. */
    private function netIncome(array $tot, string $dKey, string $cKey): int
    {
        $net = 0;
        foreach ($this->accounts(['REVENUE', 'EXPENSE']) as $a) {
            [$d, $c] = $this->pair($tot, $a->id, $dKey, $cKey);
            $net += $a->account_type === 'REVENUE' ? $c - $d : $d - $c;
        }

        return $net;
    }

    /** [section => [[account, amount]]] untuk akun Laba Rugi; amount sesuai sifat normal akun. */
    private function plSections(array $tot, string $dKey, string $cKey): array
    {
        $out = array_fill_keys(['REVENUE', 'COST_OF_SERVICES', 'OPERATING_EXPENSE', 'OTHER_INCOME', 'OTHER_EXPENSE', 'TAX_EXPENSE'], []);
        foreach ($this->accounts(['REVENUE', 'EXPENSE']) as $a) {
            [$d, $c] = $this->pair($tot, $a->id, $dKey, $cKey);
            $amount = $a->account_type === 'REVENUE' ? $c - $d : $d - $c;
            if ($amount !== 0) {
                $out[$a->effectiveSection()][] = [$a, $amount];
            }
        }

        return $out;
    }

    /* --------------------------------------------------------------- NERACA */

    public function balanceSheet(string $asOf): array
    {
        $fyStart = \Carbon\Carbon::parse($asOf)->startOfYear()->toDateString();
        $tot = $this->ledger->totalsByAccount($asOf, $asOf);       // od/oc = sebelum, pd/pc = pada hari itu → total s.d. asOf
        $fy = $this->ledger->totalsByAccount($fyStart, $asOf);     // od/oc = sebelum tahun fiskal, pd/pc = tahun berjalan

        $bal = fn (CoaAccount $a, int $sign) => $sign * $this->through($tot, $a->id);
        $L = [];

        $assets = 0;
        $L[] = $this->line('section', 'ASET');
        foreach (['CURRENT_ASSET' => 'Aset Lancar', 'NON_CURRENT_ASSET' => 'Aset Tidak Lancar'] as $s => $label) {
            $assets += $this->block($L, $label, $this->bySection('ASSET', $s, fn ($a) => $bal($a, 1)));
        }
        $L[] = $this->line('grand', 'TOTAL ASET', $assets);

        $liab = 0;
        $L[] = $this->line('section', 'LIABILITAS');
        foreach (['CURRENT_LIABILITY' => 'Liabilitas Jangka Pendek', 'NON_CURRENT_LIABILITY' => 'Liabilitas Jangka Panjang'] as $s => $label) {
            $liab += $this->block($L, $label, $this->bySection('LIABILITY', $s, fn ($a) => $bal($a, -1)));
        }
        $L[] = $this->line('total', 'TOTAL LIABILITAS', $liab);

        $equity = 0;
        $L[] = $this->line('section', 'EKUITAS');
        foreach (\App\Support\AccountingCatalog::SECTIONS['EQUITY'] as $s => $label) {
            $equity += $this->block($L, $label, $this->bySection('EQUITY', $s, fn ($a) => $bal($a, -1)));
        }
        $prior = $this->netIncome($fy, 'od', 'oc');
        $current = $this->netIncome($fy, 'pd', 'pc');
        $L[] = $this->line('row', 'Laba Ditahan Tahun Sebelumnya (belum ditutup)', $prior);
        $L[] = $this->line('row', 'Laba Tahun Berjalan', $current);
        $equity += $prior + $current;
        $L[] = $this->line('total', 'TOTAL EKUITAS', $equity);
        $L[] = $this->line('grand', 'TOTAL LIABILITAS + EKUITAS', $liab + $equity);

        return ['lines' => $L, 'checks' => [[
            'label' => 'Aset = Liabilitas + Ekuitas', 'ok' => $assets === $liab + $equity, 'diff' => $assets - ($liab + $equity),
        ]], 'equity_total' => $equity];
    }

    /* ------------------------------------------------------------ ARUS KAS */

    /**
     * Metode langsung dari buku besar: untuk tiap jurnal yang menyentuh akun kas/bank,
     * baris LAWAN (non-kas) menentukan aktivitasnya. Perpindahan antar-akun kas saling
     * meniadakan dan tidak muncul. Kas = akun COA yang tertaut ke rekening Cash & Bank.
     */
    public function cashFlow(string $from, string $to): array
    {
        $cashIds = CoaAccount::whereNotNull('source_fund_id')->pluck('id')->all();
        if (! $cashIds) {
            return ['lines' => [], 'checks' => [['label' => 'Belum ada akun kas', 'ok' => false, 'diff' => 0,
                'note' => 'Tautkan akun COA ke rekening Cash & Bank agar Arus Kas bisa dihitung.']]];
        }

        $tot = $this->ledger->totalsByAccount($from, $to);
        $opening = 0;
        $closingLedger = 0;
        foreach ($cashIds as $id) {
            [$od, $oc, $pd, $pc] = $tot[$id] ?? [0, 0, 0, 0];
            $opening += $od - $oc;
            $closingLedger += $od - $oc + $pd - $pc;
        }

        $rows = DB::table('journal_lines as l')
            ->join('journals as j', 'j.id', '=', 'l.journal_id')
            ->whereNull('j.deleted_at')->whereIn('j.status', LedgerService::COUNTED)
            ->whereDate('j.journal_date', '>=', $from)->whereDate('j.journal_date', '<=', $to)
            ->whereIn('l.journal_id', fn ($q) => $q->select('journal_id')->from('journal_lines')->whereIn('coa_account_id', $cashIds))
            ->whereNotIn('l.coa_account_id', $cashIds)
            ->groupBy('l.coa_account_id')
            ->selectRaw('l.coa_account_id as id, COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')
            ->get();

        $accounts = CoaAccount::whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
        $byActivity = ['OPERATING' => [], 'INVESTING' => [], 'FINANCING' => []];
        foreach ($rows as $r) {
            $a = $accounts[$r->id];
            $amount = (int) $r->c - (int) $r->d;            // kredit lawan = kas masuk
            if ($amount !== 0) {
                $byActivity[$a->effectiveCashActivity()][] = [$a, $amount];
            }
        }

        $L = [];
        $net = 0;
        foreach (['OPERATING' => 'Aktivitas Operasi', 'INVESTING' => 'Aktivitas Investasi', 'FINANCING' => 'Aktivitas Pendanaan'] as $k => $label) {
            $sum = $this->block($L, $label, $byActivity[$k], 'Kas bersih dari ' . strtolower($label));
            $net += $sum;
        }
        $L[] = $this->line('grand', 'Kenaikan / (Penurunan) Kas Bersih', $net);
        $L[] = $this->line('row', 'Saldo Kas Awal', $opening);
        $L[] = $this->line('grand', 'Saldo Kas Akhir', $opening + $net);

        return ['lines' => $L, 'checks' => [[
            'label' => 'Saldo kas akhir = saldo akun kas di buku besar', 'ok' => $opening + $net === $closingLedger, 'diff' => $opening + $net - $closingLedger,
        ]]];
    }

    /* ---------------------------------------------------- PERUBAHAN EKUITAS */

    public function equityChanges(string $from, string $to): array
    {
        $tot = $this->ledger->totalsByAccount($from, $to);   // od/oc sebelum $from, pd/pc di dalam rentang
        $equityAccs = $this->accounts(['EQUITY']);

        $open = $this->netIncome($tot, 'od', 'oc');           // laba kumulatif sebelum $from (belum ditutup)
        $mov = ['CAPITAL' => 0, 'DISTRIBUTION' => 0, 'OTHER' => 0];
        foreach ($equityAccs as $a) {
            [$od, $oc, $pd, $pc] = $tot[$a->id] ?? [0, 0, 0, 0];
            $open += $oc - $od;
            $m = $pc - $pd;
            $key = in_array($a->effectiveSection(), ['CAPITAL', 'DISTRIBUTION'], true) ? $a->effectiveSection() : 'OTHER';
            $mov[$key] += $m;
        }
        $ni = $this->netIncome($tot, 'pd', 'pc');
        $close = $open + $mov['CAPITAL'] + $mov['DISTRIBUTION'] + $mov['OTHER'] + $ni;

        $L = [
            $this->line('row', 'Ekuitas Awal', $open),
            $this->line('row', 'Setoran Modal', $mov['CAPITAL']),
            $this->line('row', 'Laba Bersih Periode', $ni),
            $this->line('row', 'Dividen / Distribusi', $mov['DISTRIBUTION']),
            $this->line('row', 'Perubahan Ekuitas Lainnya', $mov['OTHER']),
            $this->line('grand', 'Ekuitas Akhir', $close),
        ];

        $bs = $this->balanceSheet($to)['equity_total'];

        return ['lines' => $L, 'checks' => [[
            'label' => 'Ekuitas akhir = total ekuitas di Neraca per ' . $to, 'ok' => $close === $bs, 'diff' => $close - $bs,
        ]]];
    }

    /* ------------------------------------------------------------ PEMBANTU */

    private function line(string $t, string $label, ?int $amount = null, ?int $accountId = null): array
    {
        return ['t' => $t, 'label' => $label, 'amount' => $amount, 'account_id' => $accountId];
    }

    /** Tulis judul seksi + baris akun + total seksi ke $L; kembalikan total (sen). Seksi tanpa isi tetap menampilkan total 0. */
    private function block(array &$L, string $title, array $rows, ?string $totalLabel = null): int
    {
        $L[] = $this->line('section', $title);
        $sum = 0;
        foreach ($rows as [$a, $amount]) {
            $L[] = $this->line('row', $a->code . ' — ' . $a->name, $amount, $a->id);
            $sum += $amount;
        }
        $L[] = $this->line('total', $totalLabel ?? 'Total ' . $title, $sum);

        return $sum;
    }

    /** Akun bertipe tertentu di seksi tertentu dengan jumlah ≠ 0: [[account, amount]]. */
    private function bySection(string $type, string $section, \Closure $amountOf): array
    {
        $out = [];
        foreach ($this->accounts([$type]) as $a) {
            if ($a->effectiveSection() === $section && ($amt = $amountOf($a)) !== 0) {
                $out[] = [$a, $amt];
            }
        }

        return $out;
    }

    private ?\Illuminate\Support\Collection $accountCache = null;

    private function accounts(array $types): \Illuminate\Support\Collection
    {
        $this->accountCache ??= CoaAccount::orderBy('code')->get();

        return $this->accountCache->whereIn('account_type', $types);
    }

    /** [debit, kredit] untuk akun dari totalsByAccount memakai kunci tertentu (od/oc/pd/pc). */
    private function pair(array $tot, int $id, string $dKey, string $cKey): array
    {
        $idx = ['od' => 0, 'oc' => 1, 'pd' => 2, 'pc' => 3];
        $r = $tot[$id] ?? [0, 0, 0, 0];

        return [$r[$idx[$dKey]], $r[$idx[$cKey]]];
    }

    /** Debit − kredit s.d. akhir rentang (sebelum + dalam rentang). */
    private function through(array $tot, int $id): int
    {
        [$od, $oc, $pd, $pc] = $tot[$id] ?? [0, 0, 0, 0];

        return $od - $oc + $pd - $pc;
    }
}
