<?php

namespace App\Services;

use App\Models\CoaAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Accounting Fase 3 — pembacaan Buku Besar & Trial Balance.
 *
 * Hanya jurnal POSTED dan REVERSED yang dihitung (REVERSED = jurnal asal yang sudah
 * dibalik; pembaliknya sendiri berstatus POSTED, jadi keduanya tetap tampil dan
 * saling menetralkan). Draft/Submitted tidak pernah memengaruhi saldo.
 * Semua pengurutan dan batas memakai journal_date (bukan created_at), dan semua
 * angka = sen (integer) — hasilnya deterministik dan bisa direproduksi.
 */
class LedgerService
{
    public const COUNTED = ['POSTED', 'REVERSED'];

    private function lines()
    {
        return DB::table('journal_lines as l')
            ->join('journals as j', 'j.id', '=', 'l.journal_id')
            ->whereNull('j.deleted_at')
            ->whereIn('j.status', self::COUNTED);
    }

    /**
     * Buku besar satu akun.
     *
     * @return array{opening:int, rows:Collection, debit:int, credit:int, closing:int}  saldo mengikuti saldo normal akun
     */
    public function ledger(CoaAccount $acc, string $from, string $to, array $dim = []): array
    {
        $sign = $acc->normal_balance === 'DEBIT' ? 1 : -1;

        $open = $this->dimension($this->lines()->where('l.coa_account_id', $acc->id)->whereDate('j.journal_date', '<', $from), $dim)
            ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();
        $opening = $sign * ((int) $open->d - (int) $open->c);

        $rows = $this->dimension($this->lines()->where('l.coa_account_id', $acc->id)
            ->whereDate('j.journal_date', '>=', $from)->whereDate('j.journal_date', '<=', $to), $dim)
            ->orderBy('j.journal_date')->orderBy('j.id')->orderBy('l.line_no')
            ->get(['j.id as journal_id', 'j.journal_number', 'j.journal_date', 'j.reference_number',
                'j.status', 'l.description as line_description', 'j.description as journal_description', 'l.debit', 'l.credit']);

        $balance = $opening;
        $dr = $cr = 0;
        $rows = $rows->map(function ($r) use (&$balance, &$dr, &$cr, $sign) {
            $r->debit = (int) $r->debit;
            $r->credit = (int) $r->credit;
            $balance += $sign * ($r->debit - $r->credit);
            $dr += $r->debit;
            $cr += $r->credit;
            $r->balance = $balance;

            return $r;
        });

        return ['opening' => $opening, 'rows' => $rows, 'debit' => $dr, 'credit' => $cr, 'closing' => $balance];
    }

    /**
     * Saldo per akun untuk Trial Balance: [account_id => [od, oc, pd, pc]] dalam sen,
     * di mana od/oc = total debit/kredit SEBELUM $from dan pd/pc = di dalam [$from, $to].
     */
    public function totalsByAccount(string $from, string $to): array
    {
        $rows = $this->lines()
            ->whereDate('j.journal_date', '<=', $to)
            ->groupBy('l.coa_account_id')
            ->selectRaw('l.coa_account_id as id,
                COALESCE(SUM(CASE WHEN j.journal_date < ? THEN l.debit ELSE 0 END),0) od,
                COALESCE(SUM(CASE WHEN j.journal_date < ? THEN l.credit ELSE 0 END),0) oc,
                COALESCE(SUM(CASE WHEN j.journal_date >= ? THEN l.debit ELSE 0 END),0) pd,
                COALESCE(SUM(CASE WHEN j.journal_date >= ? THEN l.credit ELSE 0 END),0) pc', [$from, $from, $from, $from])
            ->get();

        return $rows->mapWithKeys(fn ($r) => [(int) $r->id => [(int) $r->od, (int) $r->oc, (int) $r->pd, (int) $r->pc]])->all();
    }

    /**
     * Trial Balance berhierarki. Tiap baris: account, depth, own (nilai langsung akun itu),
     * rolled (akun + seluruh turunannya, untuk tampilan induk). Total HANYA dari nilai own
     * supaya tidak terhitung dua kali.
     *
     * Satuan kolom: opening/ending = saldo bersih (debit − kredit) yang ditaruh di kolom
     * Debit bila ≥ 0, Kredit bila < 0.
     */
    public function trialBalance(string $from, string $to, ?string $type = null, ?string $category = null, bool $showZero = false): array
    {
        $tot = $this->totalsByAccount($from, $to);
        $accounts = CoaAccount::orderBy('code')->get()->keyBy('id');

        $own = [];
        foreach ($accounts as $id => $a) {
            [$od, $oc, $pd, $pc] = $tot[$id] ?? [0, 0, 0, 0];
            $own[$id] = ['open' => $od - $oc, 'pd' => $pd, 'pc' => $pc];
        }

        // Roll-up ke seluruh leluhur.
        $rolled = $own;
        foreach ($accounts as $id => $a) {
            for ($p = $a->parent_id, $guard = 0; $p && isset($rolled[$p]) && $guard < 20; $p = $accounts[$p]->parent_id ?? null, $guard++) {
                $rolled[$p]['open'] += $own[$id]['open'];
                $rolled[$p]['pd'] += $own[$id]['pd'];
                $rolled[$p]['pc'] += $own[$id]['pc'];
            }
        }

        $rows = [];
        $sum = ['od' => 0, 'oc' => 0, 'pd' => 0, 'pc' => 0, 'ed' => 0, 'ec' => 0];
        foreach ($accounts as $id => $a) {
            $o = $own[$id];
            $endNet = $o['open'] + $o['pd'] - $o['pc'];
            $sum['od'] += max(0, $o['open']);
            $sum['oc'] += max(0, -$o['open']);
            $sum['pd'] += $o['pd'];
            $sum['pc'] += $o['pc'];
            $sum['ed'] += max(0, $endNet);
            $sum['ec'] += max(0, -$endNet);

            if (($type && $a->account_type !== $type) || ($category && $a->category !== $category)) {
                continue;
            }
            $r = $rolled[$id];
            $rEnd = $r['open'] + $r['pd'] - $r['pc'];
            if (! $showZero && $r['open'] === 0 && $r['pd'] === 0 && $r['pc'] === 0) {
                continue;
            }

            $depth = 0;
            for ($p = $a->parent_id; $p && isset($accounts[$p]) && $depth < 10; $p = $accounts[$p]->parent_id) {
                $depth++;
            }
            $rows[] = [
                'account' => $a, 'depth' => $depth, 'is_parent' => ! $a->is_postable,
                'od' => max(0, $r['open']), 'oc' => max(0, -$r['open']), 'pd' => $r['pd'], 'pc' => $r['pc'],
                'ed' => max(0, $rEnd), 'ec' => max(0, -$rEnd),
            ];
        }

        // Total memakai saldo bersih per akun di atas; selisih open/ending harus nol bila jurnal seimbang.
        return ['rows' => $rows, 'sum' => $sum, 'balanced' => $sum['ed'] === $sum['ec'] && $sum['od'] === $sum['oc'] && $sum['pd'] === $sum['pc']];
    }

    private function dimension($q, array $dim)
    {
        return $q->when($dim['project_id'] ?? null, fn ($q, $v) => $q->where('l.project_id', $v))
            ->when($dim['cost_center_id'] ?? null, fn ($q, $v) => $q->where('l.cost_center_id', $v));
    }
}
