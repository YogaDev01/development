<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChecksInvoiceInvariants;
use App\Http\Controllers\Concerns\LocksPoNumbers;
use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buat BAST dari invoice lama untuk No. PO yang tidak punya baris BAST sama sekali.
 *
 * Satu BAST per invoice aktif (bukan CANCELLED/terhapus): no_bast = "TANPA-BAST/<kode invoice>",
 * tanggal_bast = invoice_date, nilai_bast = billed_amount persis, origin = FROM_INVOICE, lalu invoice
 * ditautkan ke BAST itu. Kolom agregat No. PO (nilai_bast/no_bast/tanggal_bast) dihitung ulang dari
 * BAST aktif. Nilai & status invoice serta status No. PO TIDAK berubah (dijaga kontrol invarian) dan
 * sinkron status tidak dipanggil. Invoice CANCELLED/terhapus dibiarkan tidak tertaut.
 *
 * No. PO yang total tagihan aktifnya melebihi nominal, atau punya invoice bernilai 0, dilewati.
 * Default dry run; --apply wajib disertai --po=<id,...>. Jejak di invoice_bast_links_log
 * (rule FROM_INVOICE; flag berisi agregat lama untuk --rollback).
 *
 * Urutan kunci (Concerns\LocksPoNumbers): No. PO (pernyataan pertama) -> invoice id naik;
 * rencana dihitung ulang di dalam kunci dan dilewati bila berubah (BERUBAH_SEJAK_DRYRUN).
 */
class CreateBastFromInvoice extends Command
{
    use ChecksInvoiceInvariants;
    use LocksPoNumbers;

    private const RULE = 'FROM_INVOICE';

    protected $signature = 'bast:create-from-invoice
        {--po= : Batasi pada id No. PO tertentu, dipisah koma (wajib untuk --apply)}
        {--apply : Tulis perubahan (tanpa opsi ini hanya dry run)}
        {--rollback= : Kembalikan satu run_id (dry run; tulis dengan --apply)}';

    protected $description = 'Buat BAST dari invoice untuk No. PO tanpa BAST (dry run kecuali --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($this->option('rollback')) {
            return $this->rollbackRun((string) $this->option('rollback'), $apply);
        }

        $filter = $this->option('po')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $this->option('po')))))
            : null;

        if ($apply && !$filter) {
            $this->error('--apply wajib disertai --po=<id No. PO,...>. Jalankan dry run dulu dan pilih No. PO-nya.');

            return self::FAILURE;
        }

        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        $rencana = $this->bangunRencana($filter);
        $baris = [];
        $ditulis = 0;
        $berubah = 0;

        if ($apply) {
            $sebelumInvoice = $this->invarian();
        }

        foreach ($rencana as $r) {
            if ($r['flag']) {
                $baris[] = $this->barisSkip($r);
                continue;
            }
            if (!$apply) {
                array_push($baris, ...$this->barisRencana($r));
                continue;
            }

            try {
                $hasil = $this->terapkan($r, $runId);
            } catch (\Throwable $e) {
                $this->error("GAGAL pada No. PO {$r['po']->po_number}, transaksinya dibatalkan dan run berhenti: " . $e->getMessage());
                $this->tulisLaporan($runId, $baris, true, $ditulis, $berubah);

                return self::FAILURE;
            }

            if ($hasil === 'BERUBAH_SEJAK_DRYRUN') {
                $berubah++;
                $r['flag'] = $hasil;
                $baris[] = $this->barisSkip($r);
                continue;
            }

            $ditulis++;
            array_push($baris, ...$this->barisRencana($r));
        }

        if ($apply && $sebelumInvoice !== $this->invarian()) {
            $this->error("Kontrol invarian RUN gagal (count/SUM invoice berubah). Jalankan --rollback={$runId} --apply dan periksa; "
                . 'pastikan tidak ada submit invoice bersamaan selama run.');
            $this->tulisLaporan($runId, $baris, true, $ditulis, $berubah);

            return self::FAILURE;
        }

        $this->tulisLaporan($runId, $baris, $apply, $ditulis, $berubah);

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ RENCANA */

    /** No. PO tanpa baris BAST (termasuk yang terhapus) yang punya invoice aktif belum tertaut. */
    private function bangunRencana(?array $filter): array
    {
        $hasil = [];

        ProjectPoNumber::with('project:id,code')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('project_po_basts')->whereColumn('project_po_basts.project_po_number_id', 'project_po_numbers.id'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('invoices')->whereColumn('invoices.project_po_number_id', 'project_po_numbers.id')
                ->whereNull('invoices.deleted_at')->where('invoices.status', '!=', 'CANCELLED'))
            ->when($filter, fn ($q) => $q->whereIn('id', $filter))
            ->orderBy('id')
            ->each(function (ProjectPoNumber $po) use (&$hasil) {
                $hasil[] = $this->rencanaUntukPo($po);
            });

        return $hasil;
    }

    private function rencanaUntukPo(ProjectPoNumber $po): array
    {
        $invoices = Invoice::where('project_po_number_id', $po->id)->where('status', '!=', 'CANCELLED')
            ->orderBy('invoice_date')->orderBy('id')->get();
        $total = (int) $invoices->sum('billed_amount');

        $flag = null;
        if ($invoices->contains(fn ($i) => (int) $i->billed_amount <= 0)) {
            $flag = 'INVOICE_NILAI_NOL';
        } elseif ($total > (int) $po->nominal) {
            $flag = 'MELEBIHI_NOMINAL';
        } elseif ($invoices->contains(fn ($i) => $i->project_po_bast_id !== null)) {
            $flag = 'SUDAH_TERTAUT';
        }

        return [
            'po'       => $po,
            'invoices' => $invoices,
            'total'    => $total,
            'flag'     => $flag,
            'hash'     => md5(json_encode($invoices->map(fn ($i) => [$i->id, (int) $i->billed_amount, $i->status])->all())),
        ];
    }

    /* ------------------------------------------------------------------ PENERAPAN */

    /** Satu transaksi per No. PO; urutan kunci No. PO -> invoice id naik. */
    private function terapkan(array $rencana, string $runId): string
    {
        $poId = $rencana['po']->id;
        $invoiceIds = $rencana['invoices']->pluck('id')->sort()->values()->all();

        return DB::transaction(function () use ($rencana, $runId, $poId, $invoiceIds) {
            $this->kunciNoPo($poId);                                                                       // 1) No. PO: pernyataan pertama
            Invoice::withTrashed()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get();     // 2) invoice id naik

            // Rencana dihitung ulang di dalam kunci; bila beda dari rencana awal, jangan menulis.
            $po = ProjectPoNumber::find($poId);
            $baru = $this->rencanaUntukPo($po);
            $masihKosong = !ProjectPoBast::withTrashed()->where('project_po_number_id', $poId)->exists();
            if (!$masihKosong || $baru['flag'] || $baru['hash'] !== $rencana['hash']) {
                return 'BERUBAH_SEJAK_DRYRUN';
            }

            $sebelum = $this->invarianPo($poId);
            $agregatLama = json_encode([
                'n' => $po->nilai_bast, 'no' => $po->no_bast, 't' => $po->tanggal_bast?->toDateString() ?? $po->getRawOriginal('tanggal_bast'),
            ]);

            foreach ($baru['invoices'] as $inv) {
                $bast = ProjectPoBast::create([
                    'project_po_number_id' => $poId,
                    'no_bast'              => 'TANPA-BAST/' . $inv->code,
                    'tanggal_bast'         => ($inv->invoice_date ?? $inv->created_at)->toDateString(),
                    'nilai_bast'           => (int) $inv->billed_amount,
                    'notes'                => "BAST dibuat dari invoice {$inv->code} (run {$runId})",
                    'origin'               => ProjectPoBast::ORIGIN_FROM_INVOICE,
                    'split_run_id'         => $runId,
                ]);

                $n = DB::table('invoices')->where('id', $inv->id)->whereNull('project_po_bast_id')
                    ->update(['project_po_bast_id' => $bast->id]);
                if ($n !== 1) {
                    throw new \RuntimeException("Invoice {$inv->code} gagal ditautkan ke BAST.");
                }

                DB::table('invoice_bast_links_log')->insert([
                    'run_id' => $runId, 'invoice_id' => $inv->id, 'project_po_number_id' => $poId,
                    'project_po_bast_id' => $bast->id, 'rule' => self::RULE,
                    'flag' => $agregatLama, 'created_at' => now(),
                ]);
            }

            $po->recalculateBastAggregate();

            $sesudah = $this->invarianPo($poId);
            if ($sebelum['invoice'] !== $sesudah['invoice'] || $sebelum['status_hash'] !== $sesudah['status_hash']
                || $sebelum['po_status'] !== $sesudah['po_status']
                || $sesudah['bast_aktif'] !== $baru['total'] || $sesudah['po_nilai'] !== $baru['total']) {
                throw new \RuntimeException('Kontrol invarian No. PO gagal (nilai/status invoice, status No. PO, atau Σ BAST tidak sesuai).');
            }

            return 'OK';
        });
    }

    /** Invarian satu No. PO: count/SUM/status invoice-nya, status No. PO, Σ BAST aktif, dan agregat nilai_bast. */
    private function invarianPo(int $poId): array
    {
        $po  = DB::table('project_po_numbers')->where('id', $poId)->first(['status', 'nilai_bast']);
        $inv = DB::table('invoices')->where('project_po_number_id', $poId)->selectRaw(
            'COUNT(*) AS n, COALESCE(SUM(billed_amount),0) AS billed, COALESCE(SUM(captured_total),0) AS captured,'
            . ' COALESCE(SUM(tax_amount),0) AS tax, COALESCE(SUM(withholding_tax),0) AS wht'
        )->first();
        $status = DB::table('invoices')->where('project_po_number_id', $poId)->orderBy('id')->pluck('status', 'id')->all();

        return [
            'po_status'   => (string) $po->status,
            'po_nilai'    => (int) $po->nilai_bast,
            'bast_aktif'  => (int) DB::table('project_po_basts')->where('project_po_number_id', $poId)->whereNull('deleted_at')->sum('nilai_bast'),
            'invoice'     => array_map('intval', (array) $inv),
            'status_hash' => md5(json_encode($status)),
        ];
    }

    /* ------------------------------------------------------------------ LAPORAN */

    private function barisRencana(array $r): array
    {
        $po = $r['po'];

        return $r['invoices']->map(fn ($i) => [
            $po->project->code ?? '', $po->po_number, (int) $po->nominal, $i->code, $i->status, (int) $i->billed_amount,
            'TANPA-BAST/' . $i->code, ($i->invoice_date ?? $i->created_at)->toDateString(), 'CREATE_FROM_INVOICE', $po->status,
        ])->all();
    }

    private function barisSkip(array $r): array
    {
        $po = $r['po'];

        return [$po->project->code ?? '', $po->po_number, (int) $po->nominal, '', '', (int) $r['total'], '', '', $r['flag'], $po->status];
    }

    private function tulisLaporan(string $runId, array $baris, bool $apply, int $ditulis, int $berubah): void
    {
        $csv = $this->tulisCsv("bast-from-invoice-{$runId}", [
            'project_code', 'no_po', 'nominal', 'invoice_code', 'invoice_status', 'billed_amount',
            'bast_baru_no', 'bast_baru_tanggal', 'flag', 'status_po',
        ], $baris);

        $dilewati = collect($baris)->filter(fn ($b) => $b[8] !== 'CREATE_FROM_INVOICE')->count();
        $this->info(($apply ? 'APPLY' : 'DRY RUN') . " run_id: {$runId}");
        $this->line('Baris BAST baru: ' . collect($baris)->filter(fn ($b) => $b[8] === 'CREATE_FROM_INVOICE')->count()
            . ($apply ? " (No. PO ditulis: {$ditulis}, dilewati BERUBAH_SEJAK_DRYRUN: {$berubah})" : ''));
        $this->line("No. PO dilewati (flag): {$dilewati}");
        if (!$apply) {
            $this->line('Tidak ada yang ditulis. Jalankan dengan --apply --po=<id No. PO,...>.');
        }
        $this->line('CSV: ' . $csv);
    }

    /* ------------------------------------------------------------------ ROLLBACK */

    /**
     * Kembalikan satu run: invoice dilepas dari BAST, BAST di-soft-delete, agregat No. PO dipulihkan dari
     * jejak. No. PO yang BAST-nya sudah dipakai invoice lain (dibuat setelah run) dilewati dan dilaporkan.
     */
    private function rollbackRun(string $runId, bool $apply): int
    {
        $semua = DB::table('invoice_bast_links_log')->where('run_id', $runId)->where('rule', self::RULE)->get();
        if ($semua->isEmpty()) {
            $this->error("Tidak ada jejak BAST dari invoice untuk run_id {$runId}.");

            return self::FAILURE;
        }
        $belum = $semua->whereNull('rolled_back_at');
        if ($belum->isEmpty()) {
            $this->info("Run {$runId} sudah di-rollback seluruhnya. Tidak ada yang dilakukan.");

            return self::SUCCESS;
        }

        $sebelumInvoice = $this->invarian();
        $dirollback = 0;
        $dilewati = [];

        foreach ($belum->groupBy('project_po_number_id') as $poId => $rows) {
            $poId = (int) $poId;
            if ($this->dipakaiSetelahRun($rows)) {
                $dilewati[] = $poId;
                continue;
            }
            if (!$apply) {
                $dirollback++;
                continue;
            }

            try {
                $ok = DB::transaction(function () use ($poId, $rows, $runId) {
                    $this->kunciNoPo($poId);                                                                       // 1) No. PO
                    $idInvoice = $rows->pluck('invoice_id')->unique()->sort()->values()->all();
                    Invoice::withTrashed()->whereIn('id', $idInvoice)->orderBy('id')->lockForUpdate()->get();      // 2) invoice

                    if ($this->dipakaiSetelahRun($rows)) {
                        return false;
                    }

                    $sebelum = $this->invarianPo($poId);
                    $idBast  = $rows->pluck('project_po_bast_id')->unique()->all();

                    foreach ($rows as $l) {
                        DB::table('invoices')->where('id', $l->invoice_id)->where('project_po_bast_id', $l->project_po_bast_id)
                            ->update(['project_po_bast_id' => null]);
                    }
                    DB::table('project_po_basts')->whereIn('id', $idBast)->whereNull('deleted_at')
                        ->update(['deleted_at' => now(), 'delete_reason' => "Rollback run {$runId}"]);

                    $lama = json_decode((string) $rows->first()->flag, true) ?: [];
                    DB::table('project_po_numbers')->where('id', $poId)
                        ->update(['nilai_bast' => $lama['n'] ?? null, 'no_bast' => $lama['no'] ?? null, 'tanggal_bast' => $lama['t'] ?? null]);
                    DB::table('invoice_bast_links_log')->whereIn('id', $rows->pluck('id')->all())->update(['rolled_back_at' => now()]);

                    $sesudah = $this->invarianPo($poId);
                    if ($sebelum['invoice'] !== $sesudah['invoice'] || $sebelum['status_hash'] !== $sesudah['status_hash']
                        || $sebelum['po_status'] !== $sesudah['po_status']) {
                        throw new \RuntimeException('Kontrol invarian No. PO gagal saat rollback.');
                    }

                    return true;
                });
            } catch (\Throwable $e) {
                $this->error("GAGAL rollback No. PO {$poId}, transaksinya dibatalkan dan run berhenti: " . $e->getMessage());

                return self::FAILURE;
            }

            $ok ? $dirollback++ : $dilewati[] = $poId;
        }

        if ($apply && $sebelumInvoice !== $this->invarian()) {
            $this->error('Kontrol invarian RUN gagal pada rollback (count/SUM invoice berubah).');

            return self::FAILURE;
        }

        $this->info(($apply ? 'Rollback' : 'DRY RUN rollback') . " run {$runId}: {$dirollback} No. PO " . ($apply ? 'dikembalikan' : 'akan dikembalikan')
            . ($dilewati ? '; DILEWATI karena BAST sudah dipakai invoice lain: No. PO ' . implode(', ', array_unique($dilewati)) : '') . '.');
        if (!$apply) {
            $this->line('Tidak ada yang ditulis. Jalankan dengan --apply untuk rollback.');
        }

        return self::SUCCESS;
    }

    /** Ada invoice (apa pun statusnya) yang menunjuk BAST hasil run ini tetapi BUKAN yang tercatat di jejak? */
    private function dipakaiSetelahRun($rows): bool
    {
        return Invoice::withTrashed()->whereIn('project_po_bast_id', $rows->pluck('project_po_bast_id')->unique()->all())
            ->whereNotIn('id', $rows->pluck('invoice_id')->all())->exists();
    }
}
