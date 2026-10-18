<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tautkan invoice lama (yang baru punya project_po_number_id) ke BAST-nya
 * (invoices.project_po_bast_id) — Fase 1 "Penagihan Invoice per BAST".
 *
 * Murni additive: hanya mengisi project_po_bast_id yang masih NULL. Nilai uang
 * dan status invoice TIDAK disentuh (dijaga kontrol invarian count + SUM sebelum
 * dan sesudah), dan syncInvoiceStatus()/syncPaymentStatus() tidak dipanggil.
 *
 * Aturan:
 *  R1  No. PO punya tepat 1 BAST  -> semua invoice-nya ke BAST itu, KECUALI total
 *                                    billed_amount invoice aktifnya berbeda dari nilai BAST lebih dari
 *                                    toleransi Rp 1.000: No. PO itu dilewati seluruhnya (termasuk invoice
 *                                    CANCELLED) dengan flag TAGIHAN_TIDAK_SAMA_BAST, kecuali dengan
 *                                    --sertakan-tagihan-tidak-sama.
 *  R2  No. PO punya >1 BAST       -> cocok nilai persis dulu, sisanya terdekat (NEAREST).
 *                                    Hanya ditulis dengan --include-multi.
 *  R3  No. PO tanpa BAST          -> dibiarkan NULL (TANPA_BAST).
 *  R4  invoice tanpa No. PO       -> tidak disentuh (hanya dihitung).
 *
 * Tanpa --apply hanya dry run (CSV laporan tetap dibuat).
 */
class LinkInvoicesToBast extends Command
{
    use \App\Console\Commands\Concerns\ChecksInvoiceInvariants;

    /** Selisih (rupiah) yang masih dianggap sama saat mengklasifikasi data lama (form invoice sendiri menuntut persis sama). */
    private const TOLERANSI = 1000;

    private const FLAG_TIDAK_SAMA = 'TAGIHAN_TIDAK_SAMA_BAST';

    protected $signature = 'invoice:link-bast
        {--apply : Tulis tautan (tanpa opsi ini hanya dry run)}
        {--include-multi : Ikut menulis aturan R2 (No. PO dengan >1 BAST)}
        {--sertakan-tagihan-tidak-sama : Ikut menulis R1 pada No. PO yang total tagihan aktifnya tidak sama dengan nilai BAST (keputusan bisnis)}
        {--rollback= : Kosongkan tautan yang ditulis oleh run_id tertentu (dry run; tulis dengan --apply)}';

    protected $description = 'Tautkan invoice lama ke BAST (dry run kecuali --apply)';

    public function handle(): int
    {
        if ($this->option('rollback')) {
            return $this->rollback((string) $this->option('rollback'), (bool) $this->option('apply'));
        }

        $apply        = (bool) $this->option('apply');
        $includeMulti = (bool) $this->option('include-multi');
        $runId        = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));

        $tanpaPo = Invoice::withTrashed()->whereNull('project_po_number_id')->count();
        $rows    = $this->buildPlan($includeMulti, (bool) $this->option('sertakan-tagihan-tidak-sama'));

        $ditulis = 0;
        if ($apply) {
            try {
                $ditulis = $this->applyPlan($rows, $runId);
            } catch (\Throwable $e) {
                $this->error('GAGAL, semua perubahan dibatalkan: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $csv = $this->writeCsv($rows, $runId);
        $this->summary($rows, $tanpaPo, $apply, $ditulis, $runId, $csv);

        return self::SUCCESS;
    }

    /**
     * Rencana tautan untuk semua invoice (termasuk CANCELLED & soft-deleted)
     * yang punya No. PO tetapi belum punya BAST. Hanya membaca.
     *
     * @return array<int, array{invoice: Invoice, po: ProjectPoNumber, bast: ?ProjectPoBast, rule: string, flags: array, write: bool}>
     */
    private function buildPlan(bool $includeMulti, bool $sertakanTidakSama = false): array
    {
        $rows  = [];
        $poIds = Invoice::withTrashed()
            ->whereNotNull('project_po_number_id')->whereNull('project_po_bast_id')
            ->distinct()->orderBy('project_po_number_id')->pluck('project_po_number_id');

        foreach ($poIds->chunk(200) as $chunk) {
            $pos    = ProjectPoNumber::whereIn('id', $chunk)->get()->keyBy('id');
            $basts  = ProjectPoBast::whereIn('project_po_number_id', $chunk)
                ->orderBy('tanggal_bast')->orderBy('id')->get()->groupBy('project_po_number_id');
            // BAST yang sudah dipakai invoice aktif lain (mis. run sebelumnya) tidak dipakai lagi.
            $used   = Invoice::whereIn('project_po_bast_id', $basts->flatten()->pluck('id'))
                ->where('status', '!=', 'CANCELLED')->pluck('project_po_bast_id')->flip()->all();
            $invoices = Invoice::withTrashed()->whereIn('project_po_number_id', $chunk)
                ->whereNull('project_po_bast_id')
                ->orderBy('invoice_date')->orderBy('id')->get()->groupBy('project_po_number_id');

            foreach ($invoices as $poId => $invs) {
                $po = $pos->get($poId);
                if (!$po) {
                    continue;
                }
                $list = $basts->get($poId, collect());

                if ($list->isEmpty()) {
                    foreach ($invs as $inv) {
                        $rows[] = $this->row($inv, $po, null, 'R3', ['TANPA_BAST'], false);
                    }
                } elseif ($list->count() === 1) {
                    array_push($rows, ...$this->planSingle($invs, $po, $list->first(), $used, $sertakanTidakSama));
                } else {
                    array_push($rows, ...$this->planMulti($invs, $po, $list, $used, $includeMulti));
                }
            }
        }

        return $rows;
    }

    /** R1 — tepat 1 BAST. */
    private function planSingle($invs, ProjectPoNumber $po, ProjectPoBast $bast, array $used, bool $sertakanTidakSama = false): array
    {
        $aktif = $invs->filter(fn ($i) => $this->aktif($i))->count() + (isset($used[$bast->id]) ? 1 : 0);
        $rows  = [];

        // Total tagihan aktif No. PO ini (yang akan ditautkan + yang sudah tertaut ke BAST ini) dibanding
        // nilai BAST. Tanpa invoice aktif tidak ada tagihan untuk dibandingkan, jadi tidak dianggap tidak sama.
        $tidakSama = false;
        if ($aktif > 0) {
            $total = (int) $invs->filter(fn ($i) => $this->aktif($i))->sum('billed_amount')
                + (int) Invoice::where('project_po_bast_id', $bast->id)->where('status', '!=', 'CANCELLED')->sum('billed_amount');
            $tidakSama = abs($total - (int) $bast->nilai_bast) > self::TOLERANSI;
        }

        // Dilewati seluruhnya (invoice CANCELLED/terhapus ikut tidak ditautkan) kecuali dengan opsi.
        $tulis = !$tidakSama || $sertakanTidakSama;

        foreach ($invs as $inv) {
            $flags = [];
            if (!$this->aktif($inv)) {
                $flags[] = $this->flagTidakAktif($inv);
            } else {
                if ($aktif > 1) {
                    $flags[] = 'LEGACY_MULTI';
                }
                if ((int) $inv->billed_amount !== (int) $bast->nilai_bast) {
                    $flags[] = 'VALUE_MISMATCH';
                }
            }
            if ($tidakSama) {
                $flags[] = self::FLAG_TIDAK_SAMA;
            }
            $rows[] = $this->row($inv, $po, $bast, 'R1', $flags, $tulis);
        }

        return $rows;
    }

    /** R2 — >1 BAST: cocok persis dulu, sisanya terdekat; yang tidak aktif ikut invoice aktif berikutnya. */
    private function planMulti($invs, ProjectPoNumber $po, $basts, array $used, bool $includeMulti): array
    {
        $invs     = $invs->values();
        $assigned = []; // index invoice => ProjectPoBast|null
        $flags    = [];

        $aktifIdx = $invs->keys()->filter(fn ($k) => $this->aktif($invs[$k]))->values();

        // Pass 1: nilai persis, BAST terawal yang belum terpakai.
        foreach ($aktifIdx as $k) {
            $cocok = $basts->first(fn ($b) => !isset($used[$b->id])
                && (int) $b->nilai_bast === (int) $invs[$k]->billed_amount);
            if ($cocok) {
                $assigned[$k] = $cocok;
                $used[$cocok->id] = true;
            }
        }

        // Pass 2: selisih terkecil, seri -> id terkecil.
        foreach ($aktifIdx as $k) {
            if (isset($assigned[$k])) {
                continue;
            }
            $billed = (int) $invs[$k]->billed_amount;
            $sisa = $basts->filter(fn ($b) => !isset($used[$b->id]))
                ->sort(fn ($a, $b) => [abs((int) $a->nilai_bast - $billed), $a->id] <=> [abs((int) $b->nilai_bast - $billed), $b->id])
                ->first();
            if ($sisa) {
                $assigned[$k] = $sisa;
                $used[$sisa->id] = true;
                $flags[$k] = ['NEAREST'];
            } else {
                $assigned[$k] = null;
                $flags[$k] = ['TANPA_BAST_TERSISA'];
            }
        }

        // Tidak aktif: BAST invoice aktif berikutnya pada No. PO ini, atau BAST pertama.
        foreach ($invs as $k => $inv) {
            if ($this->aktif($inv)) {
                continue;
            }
            $berikut = $aktifIdx->first(fn ($j) => $j > $k && ($assigned[$j] ?? null));
            $assigned[$k] = $berikut !== null ? $assigned[$berikut] : $basts->first();
            $flags[$k] = [$this->flagTidakAktif($inv)];
        }

        $rows = [];
        foreach ($invs as $k => $inv) {
            $f = $flags[$k] ?? [];
            if (!$includeMulti) {
                $f[] = 'SKIPPED_MULTI';
            }
            $rows[] = $this->row($inv, $po, $assigned[$k] ?? null, 'R2', $f, $includeMulti);
        }

        return $rows;
    }

    private function aktif(Invoice $inv): bool
    {
        return !$inv->trashed() && $inv->status !== 'CANCELLED';
    }

    private function flagTidakAktif(Invoice $inv): string
    {
        return $inv->status === 'CANCELLED' ? 'CANCELLED' : 'DELETED';
    }

    private function row(Invoice $inv, ProjectPoNumber $po, ?ProjectPoBast $bast, string $rule, array $flags, bool $write): array
    {
        return [
            'invoice' => $inv,
            'po'      => $po,
            'bast'    => $bast,
            'rule'    => $rule,
            'flags'   => $flags,
            'write'   => $write && $bast !== null,
        ];
    }

    /**
     * Tulis tautan per No. PO dalam transaksi, di dalam satu transaksi luar
     * supaya kontrol invarian yang gagal membatalkan seluruh run.
     */
    private function applyPlan(array $rows, string $runId): int
    {
        $ditulis = 0;

        DB::transaction(function () use ($rows, $runId, &$ditulis) {
            $sebelum = $this->invarian();

            $perPo = collect($rows)->where('write', true)->groupBy(fn ($r) => $r['po']->id);
            foreach ($perPo as $poId => $items) {
                DB::transaction(function () use ($items, $runId, $poId, &$ditulis) {
                    foreach ($items as $r) {
                        $n = DB::table('invoices')
                            ->where('id', $r['invoice']->id)->whereNull('project_po_bast_id')
                            ->update(['project_po_bast_id' => $r['bast']->id]);
                        if ($n !== 1) {
                            continue;
                        }
                        DB::table('invoice_bast_links_log')->insert([
                            'run_id'               => $runId,
                            'invoice_id'           => $r['invoice']->id,
                            'project_po_number_id' => $poId,
                            'project_po_bast_id'   => $r['bast']->id,
                            'rule'                 => $r['rule'],
                            'flag'                 => $r['flags'] ? implode(',', $r['flags']) : null,
                            'created_at'           => now(),
                        ]);
                        $ditulis++;
                    }
                });
            }

            if ($sebelum !== $this->invarian()) {
                throw new \RuntimeException('Kontrol invarian gagal: count/SUM invoice berubah.');
            }
        });

        return $ditulis;
    }

    /**
     * Kosongkan tautan yang ditulis oleh satu run. Default DRY RUN (hanya laporan);
     * menulis hanya dengan --apply. Tiap baris log yang benar-benar dikosongkan diberi
     * rolled_back_at. Baris yang tautannya sudah berubah sejak run itu dilewati dan
     * dilaporkan (tidak ditimpa). Log tidak pernah dihapus; kontrol invarian tetap berlaku.
     */
    private function rollback(string $runId, bool $apply): int
    {
        $log = DB::table('invoice_bast_links_log')->where('run_id', $runId)->get();
        if ($log->isEmpty()) {
            $this->error("Tidak ada log untuk run_id {$runId}.");

            return self::FAILURE;
        }

        $belum = $log->whereNull('rolled_back_at');
        if ($belum->isEmpty()) {
            $this->info("Run {$runId} sudah di-rollback seluruhnya ({$log->count()} baris log). Tidak ada yang dilakukan.");

            return self::SUCCESS;
        }

        // Hanya invoice yang MASIH menunjuk BAST hasil run ini yang akan dikosongkan.
        $cocok = $belum->filter(fn ($l) => DB::table('invoices')
            ->where('id', $l->invoice_id)->where('project_po_bast_id', $l->project_po_bast_id)->exists());
        $berubah = $belum->count() - $cocok->count();

        if (!$apply) {
            $this->info("DRY RUN rollback run {$runId}: {$cocok->count()} invoice akan dikosongkan project_po_bast_id-nya"
                . ($berubah ? ", {$berubah} dilewati (tautan sudah berubah)" : '') . '.');
            $this->line('Tidak ada yang ditulis. Jalankan dengan --apply untuk melakukan rollback.');

            return self::SUCCESS;
        }

        $kosong = 0;
        try {
            DB::transaction(function () use ($cocok, &$kosong) {
                $sebelum = $this->invarian();
                foreach ($cocok as $l) {
                    $n = DB::table('invoices')
                        ->where('id', $l->invoice_id)->where('project_po_bast_id', $l->project_po_bast_id)
                        ->update(['project_po_bast_id' => null]);
                    if ($n === 1) {
                        DB::table('invoice_bast_links_log')->where('id', $l->id)->update(['rolled_back_at' => now()]);
                        $kosong++;
                    }
                }
                if ($sebelum !== $this->invarian()) {
                    throw new \RuntimeException('Kontrol invarian gagal: count/SUM invoice berubah.');
                }
            });
        } catch (\Throwable $e) {
            $this->error('GAGAL, rollback dibatalkan: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rollback run {$runId}: {$kosong} invoice dikosongkan project_po_bast_id-nya"
            . ($berubah ? ", {$berubah} dilewati (tautan sudah berubah)" : '') . ' (log dicatat rolled_back_at, tidak dihapus).');

        return self::SUCCESS;
    }

    private function writeCsv(array $rows, string $runId): string
    {
        $dir = storage_path('app/private/reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . "invoice-link-bast-{$runId}.csv";

        $fh = fopen($path, 'w');
        fputcsv($fh, ['invoice_code', 'status', 'no_po', 'no_bast', 'nilai_bast', 'billed_amount', 'rule', 'flag']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['invoice']->code,
                $r['invoice']->status,
                $r['po']->po_number,
                $r['bast']?->no_bast,
                $r['bast']?->nilai_bast,
                (int) $r['invoice']->billed_amount,
                $r['rule'],
                implode(',', $r['flags']),
            ]);
        }
        fclose($fh);

        return $path;
    }

    private function summary(array $rows, int $tanpaPo, bool $apply, int $ditulis, string $runId, string $csv): void
    {
        $this->info(($apply ? 'APPLY' : 'DRY RUN') . " run_id: {$runId}");

        $ringkas = collect($rows)->groupBy(fn ($r) => $r['rule'])->map(fn ($g, $rule) => [
            'rule'  => $rule,
            'total' => $g->count(),
            'flag'  => $g->flatMap(fn ($r) => $r['flags'])->countBy()->map(fn ($n, $f) => "{$f}={$n}")->implode(' '),
        ])->values()->all();
        $this->table(['Rule', 'Invoice', 'Flag'], $ringkas);

        // Dihitung terpisah: No. PO yang total tagihan aktifnya tidak sama dengan nilai BAST.
        $tidakSama = collect($rows)->filter(fn ($r) => in_array(self::FLAG_TIDAK_SAMA, $r['flags'], true));
        $this->line(self::FLAG_TIDAK_SAMA . ': ' . $tidakSama->pluck('po.id')->unique()->count() . ' No. PO, '
            . $tidakSama->count() . ' invoice '
            . ($this->option('sertakan-tagihan-tidak-sama')
                ? '(ikut ditulis karena --sertakan-tagihan-tidak-sama)'
                : '(DILEWATI, tidak ditautkan; tambahkan --sertakan-tagihan-tidak-sama bila bisnis memutuskan menautkannya)'));

        $this->line('NO_PO_NUMBER (tidak disentuh): ' . $tanpaPo);
        if ($apply) {
            $this->line('Tautan ditulis: ' . $ditulis);
        } else {
            $this->line('Tidak ada yang ditulis. Jalankan dengan --apply (dan --include-multi untuk R2).');
        }
        $this->line('CSV: ' . $csv);
    }
}
