<?php

namespace App\Services;

use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\CoaAccount;
use App\Models\DailyTransaction;
use App\Models\Journal;
use App\Models\JournalSource;
use App\Support\AccountingMoney;
use App\Support\DocumentSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accounting Fase 2 — membuat jurnal DRAFT dari Treasury dan Cash Receipt.
 *
 * Sumber transaksi TIDAK diubah dan tidak diduplikasi: jurnal hanya merujuk
 * (journal_sources). Satuan sumber:
 *   - Treasury: satu jurnal per batch PAID; tiap item = satu sumber, biaya bank = sumber baris 0.
 *   - Cash Receipt: satu jurnal per header (bukan batal); detail lama tanpa header = satu jurnal per detail.
 *
 * Belum dijurnal (sengaja, keputusan 2026-11-13): Cash Advance, Second Payment
 * dan Expense Cash Advance — dilewati dan diberi catatan.
 *
 * Seluruh nominal dikali 100 (sen) lewat AccountingMoney.
 */
class JournalGenerator
{
    public const TREASURY = 'daily_transactions';
    public const RECEIPT_HEADER = 'cash_receipt_headers';
    public const RECEIPT_DETAIL = 'cash_receipts';

    /** Tanggal mulai periode pertama = cut-over pembukuan; sebelum itu tidak dijurnal. */
    public static function cutoverDate(): ?\Carbon\Carbon
    {
        $d = AccountingPeriod::min('start_date');

        return $d ? \Carbon\Carbon::parse($d)->startOfDay() : null;
    }

    /* ------------------------------------------------------------------
     | DAFTAR SUMBER
     ------------------------------------------------------------------ */

    /**
     * Baris sumber untuk layar "Transaksi Sumber".
     * Tiap baris: module, source_type, source_id, number, date, description, counterparty,
     * category, bank, amount (sen), tx_status, journal_status (NONE|DRAFT|SUBMITTED|POSTED),
     * journal_id, eligible, note.
     */
    public function candidates(?string $from = null, ?string $to = null, int $limit = 400, ?bool &$truncated = null): Collection
    {
        $cut = self::cutoverDate();
        $truncated = false;
        // Daftar hanya memuat transaksi pada/sesudah tanggal mulai pembukuan (dan filter tanggal user).
        $lo = $cut ? ($from && $from > $cut->toDateString() ? $from : $cut->toDateString()) : $from;

        return $this->treasuryRows($cut, $lo, $to, $limit, $truncated)->merge($this->receiptRows($cut, $lo, $to, $limit, $truncated))
            ->sortByDesc('date')->values();
    }

    private function treasuryRows(?\Carbon\Carbon $cut, ?string $lo, ?string $hi, int $limit, bool &$truncated): Collection
    {
        $batches = DailyTransaction::where('status', 'PAID')
            ->when($lo, fn ($q, $d) => $q->whereRaw('DATE(COALESCE(paid_at, date)) >= ?', [$d]))
            ->when($hi, fn ($q, $d) => $q->whereRaw('DATE(COALESCE(paid_at, date)) <= ?', [$d]))
            ->with(['sourceFund:id,name', 'toSourceFund:id,name',
                'includedItems.budgetRequest.type', 'includedItems.budgetRequest.beneficiary',
                'includedItems.costRegister'])
            ->orderByDesc('paid_at')->limit($limit + 1)->get();
        if ($batches->count() > $limit) {
            $truncated = true;
            $batches = $batches->take($limit);
        }

        $keys = $this->activeKeyIndex(self::TREASURY, $batches->pluck('id'));

        return $batches->map(function (DailyTransaction $b) use ($cut, $keys) {
            $date = ($b->paid_at ?? $b->date)?->copy()->startOfDay();
            [$units, $skipped] = $this->treasuryUnits($b);
            $todo = $units->filter(fn ($u) => ! isset($keys[$u['key']]));
            $journaled = $units->count() - $todo->count();
            $first = $units->map(fn ($u) => $keys[$u['key']] ?? null)->filter()->first();

            if ($b->isTopUp()) {
                $amount = (int) $b->captured_amount + (int) $b->charge;
            } else {
                $amount = (int) $todo->sum(fn ($u) => $u['amount']);
            }

            $eligible = $todo->isNotEmpty() && $cut && $date && $date->gte($cut);
            $note = null;
            if (! $cut) {
                $note = 'Buat periode akuntansi dulu (menentukan tanggal mulai pembukuan).';
            } elseif ($date && $date->lt($cut)) {
                $note = 'Sebelum tanggal mulai pembukuan.';
            } elseif ($skipped > 0) {
                $note = $skipped . ' item Cash Advance / Second Payment belum dijurnal.';
            }

            return [
                'module'         => 'TREASURY', 'source_type' => self::TREASURY, 'source_id' => $b->id,
                'number'         => $b->code, 'date' => $date,
                'description'    => $b->isTopUp() ? 'Top Up ' . ($b->toSourceFund->name ?? '') : ($b->description ?: 'Batch ' . $b->code),
                'counterparty'   => $b->isTopUp() ? 'Antar rekening' : $units->count() . ' item',
                'category'       => $b->type_label,
                'bank'           => $b->sourceFund->name ?? '-',
                'amount'         => AccountingMoney::fromRupiah($amount),
                'tx_status'      => 'PAID',
                'journal_status' => $todo->isEmpty() && $units->isNotEmpty() ? ($first['status'] ?? 'DRAFT') : ($journaled > 0 ? 'PARTIAL' : 'NONE'),
                'journal_id'     => $first['journal_id'] ?? null,
                'eligible'       => $eligible, 'note' => $note,
            ];
        });
    }

    private function receiptRows(?\Carbon\Carbon $cut, ?string $lo, ?string $hi, int $limit, bool &$truncated): Collection
    {
        $rows = collect();

        $headers = CashReceiptHeader::where('status', '!=', 'CANCELLED')
            ->when($lo, fn ($q, $d) => $q->whereDate('received_date', '>=', $d))
            ->when($hi, fn ($q, $d) => $q->whereDate('received_date', '<=', $d))
            ->with(['customer:id,name', 'sourceFund:id,name', 'details'])
            ->orderByDesc('received_date')->limit($limit + 1)->get();
        if ($headers->count() > $limit) {
            $truncated = true;
            $headers = $headers->take($limit);
        }
        $hk = $this->activeKeyIndex(self::RECEIPT_HEADER, $headers->pluck('id'));

        foreach ($headers as $h) {
            $key = JournalSource::makeActiveKey(self::RECEIPT_HEADER, $h->id);
            [$received] = $this->headerAmounts($h, $h->details);
            $rows->push($this->receiptRow('CASH_RECEIPT', self::RECEIPT_HEADER, $h->id, $h->code, $h->received_date,
                $h->notes ?: 'Penerimaan ' . $h->code, $h->customer->name ?? '-', $h->sourceFund->name ?? '-',
                $received, $h->status, $hk[$key] ?? null, $cut, $received > 0 && $h->source_fund_id));
        }

        // Detail lama tanpa header (perilaku lama, tetap sah).
        $legacy = CashReceipt::whereNull('cash_receipt_header_id')
            ->when($lo, fn ($q, $d) => $q->whereDate('received_date', '>=', $d))
            ->when($hi, fn ($q, $d) => $q->whereDate('received_date', '<=', $d))
            ->with(['invoice:id,invoice_number', 'sourceFund:id,name'])
            ->orderByDesc('received_date')->limit($limit + 1)->get();
        if ($legacy->count() > $limit) {
            $truncated = true;
            $legacy = $legacy->take($limit);
        }
        $lk = $this->activeKeyIndex(self::RECEIPT_DETAIL, $legacy->pluck('id'));

        foreach ($legacy as $d) {
            $key = JournalSource::makeActiveKey(self::RECEIPT_DETAIL, $d->id);
            $rows->push($this->receiptRow('CASH_RECEIPT', self::RECEIPT_DETAIL, $d->id, 'CRD-' . $d->id, $d->received_date,
                $d->notes ?: 'Pembayaran invoice ' . ($d->invoice->invoice_number ?? '-'), '-', $d->sourceFund->name ?? '-',
                (int) $d->amount, 'RECEIVED', $lk[$key] ?? null, $cut, (bool) $d->source_fund_id));
        }

        return $rows;
    }

    private function receiptRow($module, $type, $id, $number, $date, $desc, $party, $bank, int $rupiah, $txStatus, $j, $cut, bool $ok): array
    {
        $date = $date?->copy()->startOfDay();
        $note = null;
        if (! $cut) {
            $note = 'Buat periode akuntansi dulu (menentukan tanggal mulai pembukuan).';
        } elseif ($date && $date->lt($cut)) {
            $note = 'Sebelum tanggal mulai pembukuan.';
        } elseif (! $ok) {
            $note = 'Rekening atau nominal belum lengkap.';
        }

        return [
            'module' => $module, 'source_type' => $type, 'source_id' => $id, 'number' => $number, 'date' => $date,
            'description' => $desc, 'counterparty' => $party, 'category' => 'Penerimaan', 'bank' => $bank,
            'amount' => AccountingMoney::fromRupiah($rupiah), 'tx_status' => $txStatus,
            'journal_status' => $j['status'] ?? 'NONE', 'journal_id' => $j['journal_id'] ?? null,
            'eligible' => $j === null && $note === null,
            'note' => $j === null ? $note : null,
        ];
    }

    /** [active_key => [journal_id, status]] untuk sumber bertipe tertentu — satu query. */
    private function activeKeyIndex(string $type, Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return JournalSource::where('source_type', $type)->whereIn('source_id', $ids)
            ->whereNotNull('active_key')
            ->join('journals', 'journals.id', '=', 'journal_sources.journal_id')
            ->get(['journal_sources.active_key', 'journal_sources.journal_id', 'journals.status'])
            ->mapWithKeys(fn ($r) => [$r->active_key => ['journal_id' => $r->journal_id, 'status' => $r->status]])
            ->all();
    }

    /* ------------------------------------------------------------------
     | PEMBUATAN JURNAL
     ------------------------------------------------------------------ */

    /**
     * Buat jurnal Draft dari satu sumber. Idempoten: sumber yang sudah punya
     * jurnal aktif ditolak (UNIQUE active_key sebagai pagar terakhir).
     *
     * @throws \DomainException pesan yang aman ditampilkan ke pengguna
     */
    public function generate(string $sourceType, int $sourceId): Journal
    {
        if (! in_array($sourceType, [self::TREASURY, self::RECEIPT_HEADER, self::RECEIPT_DETAIL], true)) {
            throw new \DomainException('Jenis sumber tidak dikenal.');
        }
        if (! self::cutoverDate()) {
            throw new \DomainException('Belum ada periode akuntansi — buat periode dulu.');
        }

        try {
            return DB::transaction(function () use ($sourceType, $sourceId) {
                $built = match ($sourceType) {
                    self::TREASURY       => $this->buildTreasury($sourceId),
                    self::RECEIPT_HEADER => $this->buildReceiptHeader($sourceId),
                    self::RECEIPT_DETAIL => $this->buildReceiptDetail($sourceId),
                };

                $cut = self::cutoverDate();
                if ($built['date']->lt($cut)) {
                    throw new \DomainException('Tanggal transaksi sebelum tanggal mulai pembukuan (' . $cut->format('d M Y') . ').');
                }

                $dr = array_sum(array_column($built['lines'], 'debit'));
                $cr = array_sum(array_column($built['lines'], 'credit'));
                if ($dr !== $cr || $dr <= 0) {
                    throw new \DomainException('Jurnal otomatis tidak seimbang — periksa data sumber.');
                }

                $period = AccountingPeriod::forDate($built['date']);
                $number = Journal::nextNumber();

                $journal = Journal::create([
                    'journal_number' => $number, 'journal_date' => $built['date']->toDateString(),
                    'accounting_period_id' => $period?->id, 'journal_type' => 'SOURCE', 'status' => Journal::DRAFT,
                    'reference_number' => $built['reference'], 'description' => $built['description'],
                    'total_debit' => $dr, 'total_credit' => $cr, 'created_by' => Auth::id(),
                ]);

                foreach ($built['lines'] as $i => $l) {
                    $journal->lines()->create($l + ['line_no' => $i + 1]);
                }
                foreach ($built['sources'] as $s) {
                    $journal->sources()->create($s + [
                        'source_module' => $built['module'],
                        'source_snapshot' => $this->snapshot($s['source_type'], $s['source_id'], $s['source_line_id']),
                        'active_key'    => JournalSource::makeActiveKey($s['source_type'], $s['source_id'], $s['source_line_id']),
                    ]);
                }

                AccountingAuditLog::record('journal', $journal->id, 'generate', null,
                    ['source' => $built['reference'], 'total_debit' => $dr], null, null, Journal::DRAFT, $built['reference']);

                return $journal;
            });
        } catch (QueryException $e) {
            // UNIQUE active_key: dua klik / dua request bersamaan untuk sumber yang sama.
            if ((string) $e->getCode() === '23000') {
                throw new \DomainException('Sumber ini sudah punya jurnal aktif.');
            }
            throw $e;
        }
    }

    /**
     * Item Treasury yang boleh dijurnal: [[key, item, amount(rupiah), match_key]], jumlah yang dilewati.
     * Cash Advance, Second Payment, dan Expense Cash Advance dilewati.
     */
    private function treasuryUnits(DailyTransaction $b): array
    {
        if ($b->isTopUp()) {
            return [collect([['key' => JournalSource::makeActiveKey(self::TREASURY, $b->id, 0), 'item' => null, 'amount' => (int) $b->captured_amount, 'match' => null]]), 0];
        }

        $units = collect();
        $skipped = 0;
        foreach ($b->includedItems as $item) {
            $isCa = $item->isSecondPayment() || $item->isExpenseSecondPayment()
                || ($item->isCostRegister() ? (bool) $item->costRegister?->is_cash_advance : (bool) $item->budgetRequest?->isCashAdvance());
            if ($isCa) {
                $skipped++;
                continue;
            }
            $match = $item->isCostRegister()
                ? 'expense_type:' . ($item->costRegister->type ?? '')
                : 'request_type:' . ($item->budgetRequest->type_id ?? '');
            $units->push([
                'key' => JournalSource::makeActiveKey(self::TREASURY, $b->id, $item->id),
                'item' => $item, 'amount' => $item->sourceAmount(), 'match' => $match,
            ]);
        }

        return [$units, $skipped];
    }

    private function buildTreasury(int $id): array
    {
        $b = DailyTransaction::whereKey($id)->lockForUpdate()->first();
        if (! $b || $b->status !== 'PAID') {
            throw new \DomainException('Hanya batch Treasury berstatus PAID yang bisa dijurnal.');
        }
        $b->load(['includedItems.budgetRequest.type', 'includedItems.costRegister']);

        $date = ($b->paid_at ?? $b->date)->copy()->startOfDay();
        $bankAcc = $this->bankAccount($b->source_fund_id);
        $charge = (int) $b->charge;
        $lines = [];
        $sources = [];

        if ($b->isTopUp()) {
            $amount = (int) $b->captured_amount;
            if ($amount <= 0 || ! $b->to_source_fund_id) {
                throw new \DomainException('Top Up tidak lengkap (nominal / rekening tujuan).');
            }
            $this->assertFree(self::TREASURY, $b->id, 0);
            $toAcc = $this->bankAccount($b->to_source_fund_id);
            $lines[] = $this->line($toAcc->id, null, 'Top Up ' . $b->code, AccountingMoney::fromRupiah($amount), 0);
            $credit = $amount;
            $sources[] = $this->src(self::TREASURY, $b->id, 0, $b->code);
        } else {
            [$units] = $this->treasuryUnits($b);
            $units = $units->filter(fn ($u) => ! $this->isTaken(self::TREASURY, $b->id, $u['item']->id));
            if ($units->isEmpty()) {
                throw new \DomainException('Tidak ada item batch yang bisa dijurnal otomatis (semua sudah dijurnal, atau Cash Advance / Second Payment). Jurnalkan Cash Advance dan biaya bank batch ini secara manual (Jurnal Manual).');
            }
            $credit = 0;
            foreach ($units as $u) {
                if ($u['amount'] <= 0) {
                    throw new \DomainException('Item ' . $u['item']->sourceCode() . ' bernilai 0 / sumbernya sudah dihapus — periksa batch sebelum dijurnal.');
                }
                $acc = $this->resolve('TREASURY', 'EXPENSE', $u['match'], $date);
                $item = $u['item'];
                $lines[] = $this->line($acc['debit'], $acc['debit'], $item->sourceCode() . ' — ' . mb_substr($item->sourceDescription(), 0, 150),
                    AccountingMoney::fromRupiah($u['amount']), 0);
                $credit += $u['amount'];
                $sources[] = $this->src(self::TREASURY, $b->id, $item->id, $item->sourceCode());
            }
        }

        if ($charge > 0 && ! $this->isTaken(self::TREASURY, $b->id, 0)) {
            $acc = $this->resolve('TREASURY', 'BANK_CHARGE', null, $date);
            $lines[] = $this->line($acc['debit'], $acc['debit'], 'Biaya bank ' . $b->code, AccountingMoney::fromRupiah($charge), 0);
            $credit += $charge;
            if (! $b->isTopUp()) {
                $sources[] = $this->src(self::TREASURY, $b->id, 0, $b->code . ' (biaya bank)');
            }
        }

        $lines[] = $this->line($bankAcc->id, null, 'Pembayaran batch ' . $b->code, 0, AccountingMoney::fromRupiah($credit));

        return ['module' => 'TREASURY', 'date' => $date, 'reference' => $b->code,
            'description' => 'Treasury ' . $b->code . ($b->description ? ' — ' . $b->description : ''),
            'lines' => $lines, 'sources' => $sources];
    }

    /**
     * Angka penerimaan header (rupiah): [diterima, teralokasi (Σ diterima per invoice), belum dialokasikan].
     * Jurnal HANYA mencatat uang yang diterima — PPh tidak dijurnal.
     * total_amount header = diterima + PPh, jadi sisa belum dialokasikan = total − Σ(diterima+PPh).
     */
    private function headerAmounts(CashReceiptHeader $h, $details): array
    {
        $paid = (int) $details->sum('amount');
        $wht = (int) $details->sum('withholding_tax');
        $unalloc = max(0, (int) $h->total_amount - ($paid + $wht));

        return [$paid + $unalloc, $paid, $unalloc];
    }

    private function buildReceiptHeader(int $id): array
    {
        $h = CashReceiptHeader::whereKey($id)->lockForUpdate()->first();
        if (! $h || $h->isCancelled()) {
            throw new \DomainException('Penerimaan tidak ditemukan atau sudah dibatalkan.');
        }
        $this->assertFree(self::RECEIPT_HEADER, $h->id, 0);
        $date = $h->received_date->copy()->startOfDay();

        $details = $h->details()->get();
        if ((int) $h->total_amount <= 0 || (int) $details->sum('amount') + (int) $details->sum('withholding_tax') > (int) $h->total_amount) {
            throw new \DomainException('Nominal penerimaan tidak konsisten (alokasi melebihi total).');
        }
        [$received, $paid, $unalloc] = $this->headerAmounts($h, $details);

        return $this->receiptLines('Penerimaan ' . $h->code, $h->code, $h->source_fund_id, $date, $received, $paid, $unalloc,
            self::RECEIPT_HEADER, $h->id);
    }

    private function buildReceiptDetail(int $id): array
    {
        $d = CashReceipt::whereKey($id)->lockForUpdate()->first();
        if (! $d || $d->cash_receipt_header_id) {
            throw new \DomainException('Detail ini bagian dari penerimaan ber-header — jurnalkan lewat headernya.');
        }
        $this->assertFree(self::RECEIPT_DETAIL, $d->id, 0);
        if ((int) $d->amount <= 0) {
            throw new \DomainException('Nominal pembayaran 0.');
        }

        return $this->receiptLines('Pembayaran invoice CRD-' . $d->id, 'CRD-' . $d->id, $d->source_fund_id,
            $d->received_date->copy()->startOfDay(), (int) $d->amount, (int) $d->amount, 0, self::RECEIPT_DETAIL, $d->id);
    }

    /** Dr bank (diterima); Cr piutang (diterima yang dialokasikan ke invoice) + Cr uang muka customer (sisa). PPh tidak dijurnal. */
    private function receiptLines(string $desc, string $ref, ?int $fundId, $date, int $received, int $paid, int $unalloc, string $type, int $id): array
    {
        $bank = $this->bankAccount($fundId);
        $lines = [$this->line($bank->id, null, $desc, AccountingMoney::fromRupiah($received), 0)];

        if ($paid > 0) {
            $acc = $this->resolve('CASH_RECEIPT', 'CUSTOMER_COLLECTION', null, $date);
            $lines[] = $this->line($acc['credit'], $acc['credit'], 'Pelunasan piutang — ' . $ref, 0, AccountingMoney::fromRupiah($paid));
        }
        if ($unalloc > 0) {
            $acc = $this->resolve('CASH_RECEIPT', 'CUSTOMER_ADVANCE', null, $date);
            $lines[] = $this->line($acc['credit'], $acc['credit'], 'Belum dialokasikan (uang muka customer) — ' . $ref, 0, AccountingMoney::fromRupiah($unalloc));
        }

        return ['module' => 'CASH_RECEIPT', 'date' => $date, 'reference' => $ref, 'description' => $desc,
            'lines' => $lines, 'sources' => [$this->src($type, $id, 0, $ref)]];
    }

    /**
     * Sidik nilai sumber saat ini (string). Disimpan di journal_sources saat jurnal dibuat dan
     * dibandingkan ulang saat Post: perubahan apa pun pada sumber (nominal, alokasi, status,
     * pembatalan, penghapusan) terdeteksi, bukan hanya perubahan total.
     */
    public function snapshot(string $type, int $id, int $lineId): string
    {
        if ($type === self::TREASURY) {
            $b = DailyTransaction::with(['includedItems.budgetRequest', 'includedItems.costRegister', 'includedItems.secondPayment', 'includedItems.expenseSecondPayment'])->find($id);
            if (! $b) {
                return 'missing';
            }
            if ($lineId === 0) {
                return 'b:' . $b->status . '|c:' . (int) $b->charge . '|t:' . ($b->isTopUp() ? (int) $b->captured_amount : 0);
            }
            $item = $b->includedItems->firstWhere('id', $lineId);

            return $item ? 'b:' . $b->status . '|a:' . $item->sourceAmount() : 'b:' . $b->status . '|item-missing';
        }

        if ($type === self::RECEIPT_HEADER) {
            $h = CashReceiptHeader::find($id);
            if (! $h) {
                return 'missing';
            }
            $d = $h->details()->get();

            return 's:' . $h->status . '|t:' . (int) $h->total_amount . '|n:' . $d->count()
                . '|a:' . (int) $d->sum('amount') . '|w:' . (int) $d->sum('withholding_tax');
        }

        $d = CashReceipt::find($id);

        return $d ? 'a:' . (int) $d->amount . '|w:' . (int) $d->withholding_tax . '|h:' . (int) $d->cash_receipt_header_id : 'missing';
    }

    /* ------------------------------------------------------------------
     | PEMERIKSAAN SUMBER SAAT POSTING
     ------------------------------------------------------------------ */

    /**
     * Pesan bila sumber jurnal berubah / tidak valid lagi sejak jurnal dibuat; null = masih cocok.
     * Dibandingkan dengan total jurnal (sen) — baris boleh diedit user, totalnya tidak boleh bergeser.
     */
    public function sourceDrift(Journal $journal): ?string
    {
        if ($journal->journal_type !== 'SOURCE') {
            return null;
        }
        $journal->loadMissing('sources');
        $expected = 0;

        // Sidik sumber per sumber; null = jurnal lama tanpa sidik (cukup cek total).
        foreach ($journal->sources as $s) {
            if ($s->source_snapshot !== null && $s->source_snapshot !== $this->snapshot($s->source_type, (int) $s->source_id, (int) $s->source_line_id)) {
                return 'Sumber ' . $s->source_number . ' berubah sejak jurnal dibuat (nominal, alokasi, atau status). Hapus draft dan buat ulang dari sumber.';
            }
        }

        foreach ($journal->sources->groupBy(fn ($s) => $s->source_type . ':' . $s->source_id) as $group) {
            $first = $group->first();
            if ($first->source_type === self::TREASURY) {
                $b = DailyTransaction::with(['includedItems.budgetRequest', 'includedItems.costRegister'])->find($first->source_id);
                if (! $b || $b->status !== 'PAID') {
                    return 'Batch Treasury ' . $first->source_number . ' tidak lagi berstatus PAID.';
                }
                foreach ($group as $s) {
                    if ((int) $s->source_line_id === 0) {
                        $expected += $b->isTopUp() ? (int) $b->captured_amount + (int) $b->charge : (int) $b->charge;
                        continue;
                    }
                    $item = $b->includedItems->firstWhere('id', $s->source_line_id);
                    if (! $item) {
                        return 'Item ' . $s->source_number . ' tidak lagi termuat di batch.';
                    }
                    $expected += $item->sourceAmount();
                }
            } elseif ($first->source_type === self::RECEIPT_HEADER) {
                $h = CashReceiptHeader::find($first->source_id);
                if (! $h || $h->isCancelled()) {
                    return 'Penerimaan ' . $first->source_number . ' sudah dibatalkan / dihapus.';
                }
                $expected += $this->headerAmounts($h, $h->details()->get())[0];
            } else {
                $d = CashReceipt::find($first->source_id);
                if (! $d) {
                    return 'Pembayaran ' . $first->source_number . ' sudah dihapus.';
                }
                $expected += (int) $d->amount;
            }
        }

        $expectedSen = AccountingMoney::fromRupiah($expected);
        if ($expectedSen !== (int) $journal->total_debit) {
            return 'Nominal sumber berubah sejak jurnal dibuat (sumber ' . AccountingMoney::format($expectedSen) . ', jurnal '
                . AccountingMoney::format((int) $journal->total_debit) . '). Hapus draft dan buat ulang.';
        }

        return null;
    }

    /* ------------------------------------------------------------------
     | PEMBANTU
     ------------------------------------------------------------------ */

    private function line(?int $accountId, ?int $suggested, string $desc, int $debit, int $credit): array
    {
        return ['coa_account_id' => $accountId, 'suggested_account_id' => $suggested,
            'description' => mb_substr($desc, 0, 255), 'debit' => $debit, 'credit' => $credit];
    }

    private function src(string $type, int $id, int $lineId, ?string $number): array
    {
        return ['source_type' => $type, 'source_id' => $id, 'source_line_id' => $lineId, 'source_number' => $number];
    }

    private function bankAccount(?int $fundId): CoaAccount
    {
        if (! $fundId) {
            throw new \DomainException('Transaksi tidak punya rekening bank.');
        }
        $acc = CoaAccount::where('source_fund_id', $fundId)->first();
        if (! $acc || ! $acc->isActive() || ! $acc->is_postable) {
            throw new \DomainException('Rekening bank belum ditautkan ke akun COA aktif (Chart of Accounts → tautkan ke rekening Cash & Bank).');
        }

        return $acc;
    }

    /** Mapping paling spesifik (pembeda cocok) lalu yang umum, aktif dan berlaku pada tanggal itu. */
    private function resolve(string $module, string $type, ?string $matchKey, $date): array
    {
        $base = AccountMapping::where('status', 'ACTIVE')->where('source_module', $module)->where('transaction_type', $type)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));

        $m = ($matchKey ? (clone $base)->where('match_key', $matchKey)->first() : null)
            ?? (clone $base)->whereNull('match_key')->first();

        if (! $m) {
            throw new \DomainException('Mapping belum ada: ' . \App\Support\AccountingCatalog::transactionLabel($module, $type)
                . ' (Accounting → Account Mapping).');
        }
        foreach (['debit_account_id', 'credit_account_id'] as $col) {
            $isBank = $col === 'debit_account_id' ? $m->debit_is_bank : $m->credit_is_bank;
            if (! $isBank && $m->{$col} && ! CoaAccount::postable()->whereKey($m->{$col})->exists()) {
                throw new \DomainException('Mapping ' . $type . ' menunjuk akun yang nonaktif / tidak bisa diposting.');
            }
        }

        return ['debit' => $m->debit_account_id, 'credit' => $m->credit_account_id];
    }

    private function isTaken(string $type, int $id, int $lineId): bool
    {
        return JournalSource::where('active_key', JournalSource::makeActiveKey($type, $id, $lineId))->exists();
    }

    private function assertFree(string $type, int $id, int $lineId): void
    {
        if ($this->isTaken($type, $id, $lineId)) {
            throw new \DomainException('Sumber ini sudah punya jurnal aktif.');
        }
    }
}
