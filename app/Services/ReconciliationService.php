<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\CashReconciliation;
use App\Models\CashReconciliationMatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cash & Bank Fase 3 — logika rekonsiliasi bank.
 *
 * Membandingkan rekening koran (bank_statement_lines) dengan ledger TESYS
 * (CashFlowService::accountLedger) TANPA mengubah ledger. Sumber kebenaran
 * saldo TESYS tetap CashBalanceService.
 *
 * Rumus ringkasan (rekonsiliasi bank standar):
 *   Saldo bank disesuaikan = Saldo rekening koran + Outstanding Deposits − Outstanding Payments
 *   Saldo TESYS disesuaikan = Saldo ledger TESYS + Kredit bank belum tercatat − Debit bank belum tercatat
 *   Selisih = Saldo bank disesuaikan − Saldo TESYS disesuaikan
 *
 * Outstanding = baris ledger (sampai akhir periode) yang belum dicocokkan ke
 * baris bank mana pun. Agar angkanya benar, saldo awal rekening harus
 * ditetapkan pada awal rekening koran pertama yang direkonsiliasi.
 */
class ReconciliationService
{
    /** Toleransi selisih hari untuk pencocokan otomatis. */
    public const DATE_TOLERANCE_DAYS = 3;

    public function __construct(private CashFlowService $flow, private CashBalanceService $balances)
    {
    }

    /** Identitas stabil satu baris ledger (dipakai untuk menjamin sekali-cocok). */
    public static function ledgerKey(array $row): string
    {
        return implode('|', [
            $row['source_module'], $row['source_id'], $row['direction'],
            $row['detail_id'] ?? '-', $row['source_fund_id'] ?? 0, $row['reference_number'] ?? '',
        ]);
    }

    /** Semua baris ledger rekening sampai akhir periode, setelah tanggal efektif saldo awal. */
    public function ledgerRows(CashReconciliation $rec): Collection
    {
        $fundId = (int) $rec->source_fund_id;
        $ob     = \App\Models\CashOpeningBalance::where('source_fund_id', $fundId)
            ->where('status', \App\Models\CashOpeningBalance::APPROVED)->first();
        $from   = $ob?->effective_date->format('Y-m-d');

        return $this->flow->accountLedger(['to' => $rec->period_end->format('Y-m-d')])
            ->filter(fn ($r) => (int) ($r['source_fund_id'] ?? 0) === $fundId)
            ->filter(fn ($r) => ! $from || $r['date']->format('Y-m-d') >= $from)
            ->map(function ($r) {
                $r['key'] = self::ledgerKey($r);

                return $r;
            })
            ->values();
    }

    /** Baris ledger yang belum dicocokkan (di rekonsiliasi mana pun). */
    public function unmatchedLedger(CashReconciliation $rec): Collection
    {
        $used = CashReconciliationMatch::where('source_fund_id', $rec->source_fund_id)->pluck('ledger_key')->flip();

        return $this->ledgerRows($rec)->reject(fn ($r) => $used->has($r['key']))->values();
    }

    /** Status turunan satu baris bank. */
    public function lineStatus(BankStatementLine $line, ?int $matchedSum = null): string
    {
        $sum = $matchedSum ?? (int) $line->matches->sum('ledger_amount');

        if ($sum === 0) {
            return $line->investigating ? 'INVESTIGATING' : 'UNMATCHED';
        }

        return $sum === $line->amount ? 'MATCHED' : 'PARTIAL';
    }

    /** Skor kemiripan kandidat ledger untuk baris bank (tinggi = lebih mirip). */
    public function score(BankStatementLine $line, array $row): int
    {
        $score = 0;
        $ref   = mb_strtolower(trim((string) ($row['reference_number'] ?? '')));
        $hay   = mb_strtolower($line->reference . ' ' . $line->description);

        if ($ref !== '' && $ref !== '-' && str_contains($hay, $ref)) {
            $score += 100;
        }
        $score -= abs($line->txn_date->diffInDays($row['date']));

        return $score;
    }

    /** Pencocokan otomatis 1-1: arah sama, nominal sama persis, selisih hari ≤ toleransi. */
    public function autoMatch(CashReconciliation $rec, ?int $userId): int
    {
        $lines = $rec->lines()->with('matches')->orderBy('txn_date')->orderBy('id')->get()
            ->filter(fn ($l) => $l->matches->isEmpty());
        $pool  = $this->unmatchedLedger($rec)->keyBy('key');
        $count = 0;

        foreach ($lines as $line) {
            $best = null;
            $bestScore = null;
            foreach ($pool as $row) {
                if ($row['direction'] !== $line->direction || (int) $row['amount'] !== $line->amount) {
                    continue;
                }
                // abs(): diffInDays Carbon 3 bertanda.
                if (abs($line->txn_date->diffInDays($row['date'])) > self::DATE_TOLERANCE_DAYS) {
                    continue;
                }
                $s = $this->score($line, $row);
                if ($bestScore === null || $s > $bestScore) {
                    [$best, $bestScore] = [$row, $s];
                }
            }

            if ($best && $this->insertMatch($rec, $line, $best, 'AUTO', $userId)) {
                $pool->forget($best['key']);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Pencocokan manual satu baris bank dengan satu/lebih baris ledger.
     *
     * @param  string[]  $keys
     * @return string  'ok' | 'invalid' | 'conflict'
     */
    public function manualMatch(CashReconciliation $rec, BankStatementLine $line, array $keys, ?int $userId): string
    {
        $pool = $this->unmatchedLedger($rec)->keyBy('key');
        $rows = [];
        foreach (array_unique($keys) as $k) {
            $row = $pool->get($k);
            if (! $row || $row['direction'] !== $line->direction) {
                return 'invalid';
            }
            $rows[] = $row;
        }
        if (! $rows) {
            return 'invalid';
        }

        try {
            DB::transaction(function () use ($rec, $line, $rows, $userId) {
                foreach ($rows as $row) {
                    if (! $this->insertMatch($rec, $line, $row, 'MANUAL', $userId)) {
                        throw new \RuntimeException('conflict');
                    }
                }
            });
        } catch (\RuntimeException) {
            return 'conflict';
        }

        return 'ok';
    }

    /** Sisipkan pasangan; false bila baris ledger sudah dicocokkan (kunci unik). */
    private function insertMatch(CashReconciliation $rec, BankStatementLine $line, array $row, string $method, ?int $userId): bool
    {
        try {
            DB::transaction(fn () => CashReconciliationMatch::create([
                'reconciliation_id' => $rec->id,
                'source_fund_id'    => $rec->source_fund_id,
                'bank_line_id'      => $line->id,
                'ledger_key'        => $row['key'],
                'ledger_module'     => $row['source_module'],
                'ledger_source_id'  => $row['source_id'],
                'ledger_direction'  => $row['direction'],
                'ledger_amount'     => (int) $row['amount'],
                'ledger_date'       => $row['date']->format('Y-m-d'),
                'ledger_reference'  => $row['reference_number'] ?? null,
                'method'            => $method,
                'created_by'        => $userId,
            ]));

            return true;
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                return false;
            }
            throw $e;
        }
    }

    /** Ringkasan rekonsiliasi (lihat rumus di kelas). */
    public function summary(CashReconciliation $rec): array
    {
        $rec->loadMissing('lines.matches');

        $unmatched = $this->unmatchedLedger($rec);
        $outDep    = (int) $unmatched->where('direction', 'IN')->sum('amount');
        $outPay    = (int) $unmatched->where('direction', 'OUT')->sum('amount');

        $creditsNotBooked = 0;
        $debitsNotBooked  = 0;
        $unresolved       = 0;
        $byStatus         = ['MATCHED' => 0, 'PARTIAL' => 0, 'UNMATCHED' => 0, 'INVESTIGATING' => 0];

        foreach ($rec->lines as $line) {
            $sum    = (int) $line->matches->sum('ledger_amount');
            $status = $this->lineStatus($line, $sum);
            $byStatus[$status]++;
            if ($status !== 'MATCHED') {
                $unresolved++;
            }
            $remaining = $line->amount - $sum; // belum tercatat di TESYS
            if ($line->direction === 'IN') {
                $creditsNotBooked += $remaining;
            } else {
                $debitsNotBooked += $remaining;
            }
        }

        $acc = $this->balances->snapshot($rec->period_end->format('Y-m-d'))['accounts']
            ->first(fn ($a) => $a['fund']->id === (int) $rec->source_fund_id);
        $ledgerBalance = (int) ($acc['balance'] ?? 0);

        $adjBank = $rec->statement_ending_balance + $outDep - $outPay;
        $adjBook = $ledgerBalance + $creditsNotBooked - $debitsNotBooked;
        $diff    = $adjBank - $adjBook;

        return [
            'statement_balance'   => $rec->statement_ending_balance,
            'ledger_balance'      => $ledgerBalance,
            'outstanding_deposits' => $outDep,
            'outstanding_payments' => $outPay,
            'bank_credits_not_booked' => $creditsNotBooked,
            'bank_debits_not_booked'  => $debitsNotBooked,
            'adjusted_bank'       => $adjBank,
            'adjusted_book'       => $adjBook,
            'difference'          => $diff,
            'unresolved_lines'    => $unresolved,
            'by_status'           => $byStatus,
            'needs_exception'     => $diff !== 0 || $unresolved > 0,
            'unmatched_ledger'    => $unmatched,
        ];
    }

    /**
     * Status rekonsiliasi per rekening untuk Cash Overview / Bank Accounts.
     *
     * @return array{last:\Illuminate\Support\Collection, in_progress:\Illuminate\Support\Collection, unmatched_bank_lines:int}
     *   last = rekonsiliasi APPROVED terbaru per fund_id; in_progress = DRAFT/SUBMITTED terbaru per fund_id
     */
    public function overview(): array
    {
        $last = CashReconciliation::where('status', CashReconciliation::APPROVED)
            ->orderBy('period_end')->get()->keyBy('source_fund_id'); // yang terakhir menimpa
        $open = CashReconciliation::whereIn('status', [CashReconciliation::DRAFT, CashReconciliation::SUBMITTED])
            ->orderBy('period_end')->get()->keyBy('source_fund_id');

        $unmatched = BankStatementLine::whereIn('reconciliation_id', $open->pluck('id'))
            ->whereDoesntHave('matches')->count();

        return ['last' => $last, 'in_progress' => $open, 'unmatched_bank_lines' => $unmatched];
    }

    /** Kunci pengecekan: ada rekonsiliasi APPROVED yang mencakup tanggal ini? */
    public function lockedPeriodFor(int $fundId, string $date): ?CashReconciliation
    {
        return CashReconciliation::where('source_fund_id', $fundId)
            ->where('status', CashReconciliation::APPROVED)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | IMPOR CSV
    |--------------------------------------------------------------------------
    */

    public const TEMPLATE_HEADER = ['date', 'description', 'reference', 'debit', 'credit', 'balance'];

    private const HEADER_ALIASES = [
        'date'        => ['date', 'tanggal', 'transaction date', 'tgl'],
        'description' => ['description', 'deskripsi', 'keterangan', 'uraian'],
        'reference'   => ['reference', 'reference number', 'ref', 'referensi', 'no referensi'],
        'debit'       => ['debit', 'debet', 'keluar'],
        'credit'      => ['credit', 'kredit', 'masuk'],
        'balance'     => ['balance', 'saldo'],
    ];

    /**
     * Parse + validasi CSV. Tidak menulis apa pun.
     *
     * @return array{error:?string, rows:array<int,array>}
     *   tiap baris: line, state (OK|DUPLICATE|ERROR), message, data(date,description,reference,debit,credit,balance,hash)
     */
    public function parseCsv(string $path, CashReconciliation $rec, int $maxRows = 1000): array
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            return ['error' => 'File tidak dapat dibaca.', 'rows' => []];
        }

        $first = (string) fgets($fh);
        rewind($fh);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $header = fgetcsv($fh, 0, $delim);
        if (! $header) {
            fclose($fh);

            return ['error' => 'File kosong.', 'rows' => []];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $idx = [];
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($header as $i => $h) {
                if (in_array(mb_strtolower(trim((string) $h)), $aliases, true)) {
                    $idx[$field] = $i;
                    break;
                }
            }
        }
        foreach (['date', 'debit', 'credit'] as $need) {
            if (! isset($idx[$need])) {
                fclose($fh);

                return ['error' => "Kolom wajib '{$need}' tidak ditemukan. Gunakan template CSV.", 'rows' => []];
            }
        }

        $existing = BankStatementLine::where('source_fund_id', $rec->source_fund_id)->pluck('dedupe_hash')->flip();
        $seen     = [];
        $rows     = [];
        $lineNo   = 1;
        $start    = $rec->period_start->format('Y-m-d');
        $end      = $rec->period_end->format('Y-m-d');

        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            $lineNo++;
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= $maxRows) {
                fclose($fh);

                return ['error' => "Maksimal {$maxRows} baris per impor; pecah file.", 'rows' => []];
            }

            $get = fn ($f) => isset($idx[$f]) ? trim((string) ($r[$idx[$f]] ?? '')) : '';

            $date   = $this->parseDate($get('date'));
            $debit  = $get('debit') === '' ? 0 : $this->parseAmount($get('debit'));
            $credit = $get('credit') === '' ? 0 : $this->parseAmount($get('credit'));
            $bal    = $get('balance') === '' ? null : $this->parseAmount($get('balance'));

            $error = null;
            if (! $date) {
                $error = 'Tanggal tidak valid.';
            } elseif ($debit === null || $credit === null || ($get('balance') !== '' && $bal === null)) {
                $error = 'Nominal tidak valid.';
            } elseif ($debit < 0 || $credit < 0) {
                $error = 'Debit/Credit tidak boleh negatif.';
            } elseif ($debit > 0 && $credit > 0) {
                $error = 'Debit dan Credit tidak boleh terisi bersamaan.';
            } elseif ($debit === 0 && $credit === 0) {
                $error = 'Debit dan Credit kosong.';
            } elseif ($date < $start || $date > $end) {
                $error = 'Tanggal di luar periode rekonsiliasi.';
            }

            $data = [
                'date' => $date, 'description' => mb_substr($get('description'), 0, 500),
                'reference' => mb_substr($get('reference'), 0, 120),
                'debit' => (int) $debit, 'credit' => (int) $credit, 'balance' => $bal, 'hash' => null,
            ];

            $state = 'ERROR';
            if (! $error) {
                $base = implode('|', [$date, $data['description'], $data['reference'], $data['debit'], $data['credit'], $bal ?? '']);
                $seen[$base] = ($seen[$base] ?? 0) + 1;
                $data['hash'] = sha1($rec->source_fund_id . '|' . $base . '|' . $seen[$base]);
                $state = $existing->has($data['hash']) ? 'DUPLICATE' : 'OK';
                $error = $state === 'DUPLICATE' ? 'Sudah pernah diimpor untuk rekening ini (dilewati).' : null;
            }

            $rows[] = ['line' => $lineNo, 'state' => $state, 'message' => $error, 'data' => $data];
        }
        fclose($fh);

        return ['error' => null, 'rows' => $rows];
    }

    /** Terima Y-m-d, d/m/Y, d-m-Y, d/m/y. */
    public function parseDate(string $v): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y'] as $fmt) {
            $d = \DateTime::createFromFormat('!' . $fmt, $v);
            $e = \DateTime::getLastErrors();
            if ($d && (! $e || ($e['warning_count'] === 0 && $e['error_count'] === 0))) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /** "1.234.567", "1,234,567.00", "Rp 5.000" → int rupiah; null bila bukan angka. */
    public function parseAmount(string $v): ?int
    {
        $v = trim(str_ireplace('rp', '', $v));
        if ($v === '' || preg_match('/[^0-9.,\-\s]/', $v)) {
            return null;
        }
        $neg = str_starts_with($v, '-');
        $v   = preg_replace('/[.,]\d{1,2}$/', '', $v); // buang sen di ujung
        $d   = preg_replace('/[^0-9]/', '', $v);

        return $d === '' ? null : ($neg ? -1 : 1) * (int) $d;
    }
}
