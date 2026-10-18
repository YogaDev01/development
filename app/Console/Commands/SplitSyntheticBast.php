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
 * Fase 1b penagihan per BAST — pecah BAST sintetis (hasil backfill, origin=BACKFILL).
 *
 * Untuk No. PO yang punya tepat 1 BAST sintetis dan invoice aktif yang belum tertaut dengan total
 * tagihan KURANG dari nilai BAST (selisih > Rp 1.000): satu BAST pecahan per invoice aktif (nilai =
 * billed_amount persis), satu BAST sisa (dibuat terakhir), invoice CANCELLED/terhapus ditautkan ke
 * induk, induk di-soft-delete. Total tagihan LEBIH tidak dipecah (TAGIHAN_LEBIH).
 *
 * Aturan mutlak: tidak ada baris/kolom yang dihapus (hanya soft delete), nilai & status invoice
 * serta kolom agregat No. PO tidak berubah (dijaga kontrol invarian), dan command ini tidak
 * memanggil sinkron status kecuali lewat --sync-po. Default dry run.
 *
 * Urutan kunci (Concerns\LocksPoNumbers): No. PO (pernyataan pertama) -> BAST induk -> invoice id naik;
 * rencana dihitung ulang di dalam kunci dan dilewati bila berubah (BERUBAH_SEJAK_DRYRUN).
 * Toleransi Rp 1.000 hanya untuk klasifikasi; form invoice menuntut nilai persis sama.
 */
class SplitSyntheticBast extends Command
{
    use ChecksInvoiceInvariants;
    use LocksPoNumbers;

    private const TOLERANSI = 1000;

    protected $signature = 'invoice:split-synthetic-bast
        {--apply : Tulis perubahan (tanpa opsi ini hanya dry run)}
        {--po= : Batasi pada id No. PO tertentu, dipisah koma}
        {--rollback= : Kembalikan pemecahan satu run_id (dry run; tulis dengan --apply)}
        {--sync-po= : Sinkronkan status No. PO untuk satu run_id (dry run; tulis dengan --apply)}';

    protected $description = 'Pecah BAST sintetis hasil backfill per invoice aktif (dry run kecuali --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($this->option('rollback')) {
            return $this->rollbackRun((string) $this->option('rollback'), $apply);
        }
        if ($this->option('sync-po')) {
            return $this->syncPoRun((string) $this->option('sync-po'), $apply);
        }

        $runId  = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        $filter = $this->option('po')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $this->option('po')))))
            : null;

        $hasil = $this->bangunRencana($filter);

        $baris = [];
        $dilewatiBerubah = 0;
        $ditulis = 0;

        if ($apply) {
            $sebelumInvoice = $this->invarian();
            $sebelumBast    = $this->bastAktifTotal();
        }

        foreach ($hasil['split'] as $rencana) {
            if (!$apply) {
                array_push($baris, ...$this->barisLaporan($rencana, $runId, null));
                continue;
            }

            try {
                $r = $this->terapkan($rencana, $runId);
            } catch (\Throwable $e) {
                $this->error("GAGAL pada No. PO {$rencana['po']->po_number}, transaksinya dibatalkan dan run berhenti: " . $e->getMessage());
                $this->writeLaporan($hasil, $baris, $runId);

                return self::FAILURE;
            }

            if ($r['status'] === 'BERUBAH_SEJAK_DRYRUN') {
                $dilewatiBerubah++;
                $baris[] = $this->barisSkip($rencana['po'], $rencana['parent'], 'BERUBAH_SEJAK_DRYRUN', (int) $rencana['total']);
                continue;
            }

            $ditulis++;
            array_push($baris, ...$this->barisLaporan($rencana, $runId, $r['baru']));
        }

        if ($apply && ($sebelumInvoice !== $this->invarian() || $sebelumBast !== $this->bastAktifTotal())) {
            $this->error("Kontrol invarian RUN gagal (invoice atau total BAST aktif berubah). Jalankan --rollback={$runId} --apply dan periksa; "
                . 'pastikan tidak ada submit BAST/invoice bersamaan selama run.');
            $this->writeLaporan($hasil, $baris, $runId);

            return self::FAILURE;
        }

        $this->writeLaporan($hasil, $baris, $runId, $apply, $ditulis, $dilewatiBerubah);

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ RENCANA */

    /**
     * Klasifikasikan semua No. PO yang punya invoice aktif belum tertaut.
     * Hanya membaca. split = kandidat; lainnya hanya dilaporkan.
     */
    private function bangunRencana(?array $filter): array
    {
        $hasil = ['split' => [], 'lain' => [], 'dalam_toleransi' => 0];

        $poIds = Invoice::whereNotNull('project_po_number_id')->whereNull('project_po_bast_id')
            ->where('status', '!=', 'CANCELLED')
            ->when($filter, fn ($q) => $q->whereIn('project_po_number_id', $filter))
            ->distinct()->orderBy('project_po_number_id')->pluck('project_po_number_id');

        foreach ($poIds as $poId) {
            $r = $this->rencanaUntukPo((int) $poId);
            if (!$r) {
                continue;
            }
            match ($r['kelas']) {
                'SPLIT'           => $hasil['split'][] = $r,
                'DALAM_TOLERANSI' => $hasil['dalam_toleransi']++,
                default           => $hasil['lain'][] = $r,
            };
        }

        return $hasil;
    }

    /** Rencana untuk satu No. PO dari keadaan DB saat ini (dipakai ulang di dalam kunci). */
    private function rencanaUntukPo(int $poId): ?array
    {
        $po = ProjectPoNumber::with('project:id,code')->find($poId);
        if (!$po) {
            return null;
        }

        $basts = ProjectPoBast::where('project_po_number_id', $poId)->get();
        $aktif = Invoice::where('project_po_number_id', $poId)->where('status', '!=', 'CANCELLED')
            ->orderBy('invoice_date')->orderBy('id')->get();
        $aktifBelum = $aktif->whereNull('project_po_bast_id')->values();

        if ($aktifBelum->isEmpty()) {
            return null;
        }

        $dasar = ['po' => $po, 'parent' => $basts->first(), 'aktif' => $aktifBelum, 'total' => (int) $aktifBelum->sum('billed_amount')];

        if ($basts->count() !== 1 || $basts->first()->origin !== ProjectPoBast::ORIGIN_BACKFILL) {
            return $dasar + ['kelas' => 'SKIP_BUKAN_SINTETIS'];
        }
        $parent = $basts->first();

        if ($aktif->whereNotNull('project_po_bast_id')->isNotEmpty()) {
            return $dasar + ['kelas' => 'SKIP_SUDAH_TERTAUT'];
        }

        $selisih = $dasar['total'] - (int) $parent->nilai_bast;
        if ($selisih > self::TOLERANSI) {
            return $dasar + ['kelas' => 'TAGIHAN_LEBIH'];
        }
        if ($selisih >= -self::TOLERANSI) {
            return $dasar + ['kelas' => 'DALAM_TOLERANSI'];
        }

        // Invoice CANCELLED/terhapus yang belum tertaut ikut ditautkan ke induk (riwayat).
        $tidakAktif = Invoice::withTrashed()->where('project_po_number_id', $poId)->whereNull('project_po_bast_id')
            ->get()->filter(fn ($i) => $i->trashed() || $i->status === 'CANCELLED')->sortBy('id')->values();

        $sisa = (int) $parent->nilai_bast - $dasar['total'];

        return $dasar + [
            'kelas'       => 'SPLIT',
            'tidak_aktif' => $tidakAktif,
            'sisa'        => $sisa,
            'hash'        => md5(json_encode([
                $parent->id, (int) $parent->nilai_bast,
                $aktifBelum->map(fn ($i) => [$i->id, (int) $i->billed_amount, $i->status, optional($i->invoice_date)->toDateString()])->all(),
                $tidakAktif->pluck('id')->all(),
            ])),
            'status_perkiraan' => ProjectPoNumber::tentukanStatus($po->status, $aktifBelum->count() + 1, false, false),
        ];
    }

    /* ------------------------------------------------------------------ PENERAPAN */

    /** Satu transaksi per No. PO; urutan kunci No. PO -> BAST induk -> invoice id naik. */
    private function terapkan(array $rencana, string $runId): array
    {
        $poId     = $rencana['po']->id;
        $parentId = $rencana['parent']->id;
        $invoiceIds = $rencana['aktif']->pluck('id')->merge($rencana['tidak_aktif']->pluck('id'))->unique()->sort()->values()->all();

        return DB::transaction(function () use ($rencana, $runId, $poId, $parentId, $invoiceIds) {
            $this->kunciNoPo($poId);                                                              // 1) No. PO: pernyataan pertama
            ProjectPoBast::whereKey($parentId)->lockForUpdate()->first();                         // 2) BAST induk
            Invoice::withTrashed()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get(); // 3) invoice id naik

            // Rencana dihitung ulang di dalam kunci; bila beda dari rencana awal, jangan menulis.
            $baru = $this->rencanaUntukPo($poId);
            if (!$baru || $baru['kelas'] !== 'SPLIT' || $baru['hash'] !== $rencana['hash']) {
                return ['status' => 'BERUBAH_SEJAK_DRYRUN'];
            }

            $sebelum = $this->invarianPo($poId);
            $parent  = $baru['parent'];
            $tanggal = $parent->tanggal_bast?->toDateString();
            $dibuat  = ['split' => [], 'sisa' => null];

            $log = function (string $aksi, array $f = []) use ($runId, $poId, $parentId) {
                DB::table('bast_split_log')->insert(array_merge([
                    'run_id' => $runId, 'project_po_number_id' => $poId, 'parent_bast_id' => $parentId,
                    'action' => $aksi, 'created_at' => now(),
                ], $f));
            };

            // a. Pecahan: satu BAST per invoice aktif (urut invoice_date, id), nilai = billed_amount persis.
            foreach ($baru['aktif'] as $n => $inv) {
                $b = ProjectPoBast::create([
                    'project_po_number_id' => $poId,
                    'no_bast'              => $parent->no_bast . '/T' . ($n + 1),
                    'tanggal_bast'         => $tanggal,
                    'nilai_bast'           => (int) $inv->billed_amount,
                    'notes'                => "Pecahan BAST sintetis run {$runId}",
                    'submitted_by'         => $parent->submitted_by,
                    'origin'               => ProjectPoBast::ORIGIN_SPLIT_INVOICE,
                    'split_from_bast_id'   => $parentId,
                    'split_run_id'         => $runId,
                ]);
                $log('CREATE_SPLIT', ['new_bast_id' => $b->id, 'invoice_id' => $inv->id, 'nilai' => (int) $b->nilai_bast]);

                $n2 = DB::table('invoices')->where('id', $inv->id)->whereNull('project_po_bast_id')
                    ->update(['project_po_bast_id' => $b->id]);
                if ($n2 !== 1) {
                    throw new \RuntimeException("Invoice {$inv->code} gagal ditautkan ke pecahan.");
                }
                $log('LINK_INVOICE', ['new_bast_id' => $b->id, 'invoice_id' => $inv->id, 'old_invoice_bast_id' => null]);
                $dibuat['split'][] = [$b, $inv];
            }

            // b. Sisa: dibuat TERAKHIR (id terbesar), tidak ditautkan ke invoice.
            $sisa = ProjectPoBast::create([
                'project_po_number_id' => $poId,
                'no_bast'              => $parent->no_bast,
                'tanggal_bast'         => $tanggal,
                'nilai_bast'           => (int) $baru['sisa'],
                'notes'                => "Sisa BAST sintetis run {$runId}",
                'submitted_by'         => $parent->submitted_by,
                'origin'               => ProjectPoBast::ORIGIN_SPLIT_SISA,
                'split_from_bast_id'   => $parentId,
                'split_run_id'         => $runId,
            ]);
            $log('CREATE_SISA', ['new_bast_id' => $sisa->id, 'nilai' => (int) $sisa->nilai_bast]);
            $dibuat['sisa'] = $sisa;

            // c. Invoice CANCELLED/terhapus ditautkan ke induk (riwayat).
            foreach ($baru['tidak_aktif'] as $inv) {
                $n3 = DB::table('invoices')->where('id', $inv->id)->whereNull('project_po_bast_id')
                    ->update(['project_po_bast_id' => $parentId]);
                if ($n3 !== 1) {
                    throw new \RuntimeException("Invoice {$inv->code} gagal ditautkan ke induk.");
                }
                $log('LINK_HISTORY', ['invoice_id' => $inv->id, 'old_invoice_bast_id' => null]);
            }

            // d. Induk di-soft-delete; nilai/nomor/tanggal tidak diubah, lampiran tidak dipindah.
            DB::table('project_po_basts')->where('id', $parentId)->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'delete_reason' => "Dipecah oleh run {$runId}", 'deleted_by' => null]);
            $log('TRASH_PARENT', ['nilai' => (int) $parent->nilai_bast]);

            if ($sebelum !== $this->invarianPo($poId)) {
                throw new \RuntimeException('Kontrol invarian No. PO gagal (Σ BAST aktif, agregat No. PO, atau nilai/status invoice berubah).');
            }

            return ['status' => 'OK', 'baru' => $dibuat];
        });
    }

    /** Invarian satu No. PO: Σ BAST aktif, kolom agregat No. PO, serta count/SUM/status seluruh invoice-nya. */
    private function invarianPo(int $poId): array
    {
        $po  = DB::table('project_po_numbers')->where('id', $poId)->first(['nilai_bast', 'no_bast', 'tanggal_bast']);
        $inv = DB::table('invoices')->where('project_po_number_id', $poId)->selectRaw(
            'COUNT(*) AS n, COALESCE(SUM(billed_amount),0) AS billed, COALESCE(SUM(captured_total),0) AS captured,'
            . ' COALESCE(SUM(tax_amount),0) AS tax, COALESCE(SUM(withholding_tax),0) AS wht'
        )->first();
        $status = DB::table('invoices')->where('project_po_number_id', $poId)->orderBy('id')->pluck('status', 'id')->all();

        return [
            'bast_aktif'  => (int) DB::table('project_po_basts')->where('project_po_number_id', $poId)->whereNull('deleted_at')->sum('nilai_bast'),
            'po_nilai'    => (int) $po->nilai_bast,
            'po_no'       => (string) $po->no_bast,
            'po_tanggal'  => (string) $po->tanggal_bast,
            'invoice'     => array_map('intval', (array) $inv),
            'status_hash' => md5(json_encode($status)),
        ];
    }

    private function bastAktifTotal(): array
    {
        $r = DB::table('project_po_basts')->whereNull('deleted_at')->selectRaw('COUNT(*) AS n, COALESCE(SUM(nilai_bast),0) AS nilai')->first();

        return array_map('intval', (array) $r);
    }

    /* ------------------------------------------------------------------ LAPORAN */

    private function barisSkip(ProjectPoNumber $po, ?ProjectPoBast $parent, string $flag, int $total): array
    {
        return [
            $po->project->code ?? '', $po->po_number, $parent?->no_bast, (int) ($parent?->nilai_bast ?? 0),
            '', '', '', '', '', $total, $flag, $po->status, '',
        ];
    }

    /** Baris CSV satu No. PO kandidat: terapan ($baru terisi) atau rencana (dry run). */
    private function barisLaporan(array $r, string $runId, ?array $baru): array
    {
        $po = $r['po'];
        $parent = $r['parent'];
        $awal = [$po->project->code ?? '', $po->po_number, $parent->no_bast, (int) $parent->nilai_bast];
        $akhir = [$po->status, $r['status_perkiraan']];
        $baris = [];

        foreach ($r['aktif'] as $n => $inv) {
            $b = $baru['split'][$n][0] ?? null;
            $baris[] = array_merge($awal, [
                $b?->no_bast ?? ($parent->no_bast . '/T' . ($n + 1)), (int) $inv->billed_amount, ProjectPoBast::ORIGIN_SPLIT_INVOICE,
                $inv->code, $inv->status, (int) $inv->billed_amount, 'LINK_INVOICE',
            ], $akhir);
        }
        $baris[] = array_merge($awal, [$parent->no_bast, (int) $r['sisa'], ProjectPoBast::ORIGIN_SPLIT_SISA, '', '', '', 'CREATE_SISA'], $akhir);
        foreach ($r['tidak_aktif'] as $inv) {
            $baris[] = array_merge($awal, [$parent->no_bast . ' (induk)', (int) $parent->nilai_bast, 'INDUK', $inv->code,
                $inv->trashed() ? 'DELETED' : $inv->status, (int) $inv->billed_amount, 'LINK_HISTORY'], $akhir);
        }

        return $baris;
    }

    private function writeLaporan(array $hasil, array $baris, string $runId, bool $apply = false, int $ditulis = 0, int $berubah = 0): void
    {
        foreach ($hasil['lain'] as $r) {
            $baris[] = $this->barisSkip($r['po'], $r['parent'], $r['kelas'], (int) $r['total']);
        }

        $csv = $this->tulisCsv("bast-split-{$runId}", [
            'project_code', 'no_po', 'induk_no', 'induk_nilai', 'bast_baru_no', 'bast_baru_nilai', 'bast_baru_origin',
            'invoice_code', 'invoice_status', 'billed_amount', 'flag', 'status_po_sekarang', 'status_po_perkiraan',
        ], $baris);

        $hitung = collect($hasil['lain'])->countBy('kelas');
        $this->info(($apply ? 'APPLY' : 'DRY RUN') . " run_id: {$runId}");
        $this->line('No. PO kandidat pemecahan (tertagih kurang): ' . count($hasil['split']) . ($apply ? " (dipecah: {$ditulis}, dilewati BERUBAH_SEJAK_DRYRUN: {$berubah})" : ''));
        foreach (['TAGIHAN_LEBIH', 'SKIP_BUKAN_SINTETIS', 'SKIP_SUDAH_TERTAUT'] as $k) {
            $this->line("{$k}: " . ($hitung[$k] ?? 0) . ' No. PO');
        }
        $this->line('Selisih dalam toleransi (bukan kandidat, urusan invoice:link-bast): ' . $hasil['dalam_toleransi'] . ' No. PO');
        if (!$apply) {
            $this->line('Tidak ada yang ditulis. Jalankan dengan --apply (disarankan --po=<id> untuk 1-2 No. PO dulu).');
        }
        $this->line('CSV: ' . $csv);
    }

    /* ------------------------------------------------------------------ ROLLBACK */

    /**
     * Kembalikan satu run: tautan invoice ke keadaan semula, pecahan & sisa di-soft-delete, induk dipulihkan.
     * No. PO yang pecahan/sisanya sudah dipakai invoice lain (dibuat setelah run) dilewati dan dilaporkan.
     */
    private function rollbackRun(string $runId, bool $apply): int
    {
        $aksi = ['CREATE_SPLIT', 'CREATE_SISA', 'LINK_INVOICE', 'LINK_HISTORY', 'TRASH_PARENT'];
        $semua = DB::table('bast_split_log')->where('run_id', $runId)->whereIn('action', $aksi)->get();
        if ($semua->isEmpty()) {
            $this->error("Tidak ada log pemecahan untuk run_id {$runId}.");

            return self::FAILURE;
        }
        $belum = $semua->whereNull('rolled_back_at');
        if ($belum->isEmpty()) {
            $this->info("Run {$runId} sudah di-rollback seluruhnya. Tidak ada yang dilakukan.");

            return self::SUCCESS;
        }

        $sebelumInvoice = $this->invarian();
        $sebelumBast    = $this->bastAktifTotal();
        $dirollback = 0;
        $dilewati = [];

        foreach ($belum->groupBy('project_po_number_id') as $poId => $rows) {
            $poId = (int) $poId;
            $dipakai = $this->dipakaiSetelahRun($rows);
            if ($dipakai) {
                $dilewati[] = $poId;
                continue;
            }
            if (!$apply) {
                $dirollback++;
                continue;
            }

            try {
                $ok = DB::transaction(function () use ($poId, $rows, $runId) {
                    $this->kunciNoPo($poId);                                                            // 1) No. PO
                    $idBast = $rows->pluck('new_bast_id')->merge($rows->pluck('parent_bast_id'))->filter()->unique()->sort()->values()->all();
                    ProjectPoBast::withTrashed()->whereIn('id', $idBast)->orderBy('id')->lockForUpdate()->get();   // 2) BAST
                    Invoice::withTrashed()->whereIn('id', $rows->pluck('invoice_id')->filter()->unique()->sort()->values()->all())
                        ->orderBy('id')->lockForUpdate()->get();                                       // 3) invoice

                    if ($this->dipakaiSetelahRun($rows)) {
                        return false;
                    }

                    $sebelum = $this->invarianPo($poId);

                    foreach ($rows->whereIn('action', ['LINK_INVOICE', 'LINK_HISTORY']) as $l) {
                        $saatIni = $l->action === 'LINK_INVOICE' ? $l->new_bast_id : $l->parent_bast_id;
                        DB::table('invoices')->where('id', $l->invoice_id)->where('project_po_bast_id', $saatIni)
                            ->update(['project_po_bast_id' => $l->old_invoice_bast_id]);
                    }
                    DB::table('project_po_basts')->whereIn('id', $rows->whereIn('action', ['CREATE_SPLIT', 'CREATE_SISA'])->pluck('new_bast_id')->filter()->all())
                        ->whereNull('deleted_at')->update(['deleted_at' => now(), 'delete_reason' => "Rollback run {$runId}"]);
                    DB::table('project_po_basts')->whereIn('id', $rows->where('action', 'TRASH_PARENT')->pluck('parent_bast_id')->all())
                        ->update(['deleted_at' => null, 'delete_reason' => null]);
                    DB::table('bast_split_log')->whereIn('id', $rows->pluck('id')->all())->update(['rolled_back_at' => now()]);

                    if ($sebelum !== $this->invarianPo($poId)) {
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

        if ($apply && ($sebelumInvoice !== $this->invarian() || $sebelumBast !== $this->bastAktifTotal())) {
            $this->error('Kontrol invarian RUN gagal pada rollback (invoice atau total BAST aktif berubah).');

            return self::FAILURE;
        }

        $this->info(($apply ? 'Rollback' : 'DRY RUN rollback') . " run {$runId}: {$dirollback} No. PO " . ($apply ? 'dikembalikan' : 'akan dikembalikan')
            . ($dilewati ? '; DILEWATI karena pecahan/sisa sudah dipakai invoice lain: No. PO ' . implode(', ', array_unique($dilewati)) : '') . '.');
        if (!$apply) {
            $this->line('Tidak ada yang ditulis. Jalankan dengan --apply untuk rollback.');
        }

        return self::SUCCESS;
    }

    /** Ada invoice (apa pun statusnya) yang menunjuk pecahan/sisa tetapi BUKAN hasil run ini? */
    private function dipakaiSetelahRun($rows): bool
    {
        $barukah = $rows->whereIn('action', ['CREATE_SPLIT', 'CREATE_SISA'])->pluck('new_bast_id')->filter()->all();
        if (!$barukah) {
            return false;
        }
        $dariRun = $rows->where('action', 'LINK_INVOICE')->pluck('invoice_id')->all();

        return Invoice::withTrashed()->whereIn('project_po_bast_id', $barukah)->whereNotIn('id', $dariRun)->exists();
    }

    /* ------------------------------------------------------------------ SYNC STATUS */

    /**
     * Sinkron status No. PO (ProjectPoNumber::syncInvoiceStatus) untuk No. PO pada satu run. Dry run
     * menjalankan sinkron sebenarnya di dalam transaksi lalu membatalkannya, jadi status No. PO dan
     * header PO yang dilaporkan persis hasil sebenarnya tanpa menulis apa pun.
     */
    private function syncPoRun(string $runId, bool $apply): int
    {
        $poIds = DB::table('bast_split_log')->where('run_id', $runId)->whereNull('rolled_back_at')
            ->whereIn('action', ['CREATE_SPLIT', 'CREATE_SISA'])->distinct()->orderBy('project_po_number_id')->pluck('project_po_number_id');
        if ($poIds->isEmpty()) {
            $this->error("Tidak ada pemecahan aktif untuk run_id {$runId}.");

            return self::FAILURE;
        }

        $baris = [];
        foreach ($poIds as $poId) {
            $po = ProjectPoNumber::with('project')->find($poId);
            if (!$po) {
                continue;
            }

            $sebelumPo = $po->status;
            $sebelumHeader = $po->project->status;

            DB::beginTransaction();
            try {
                $this->kunciNoPo((int) $poId);
                $po = ProjectPoNumber::with('project')->find($poId);
                $po->syncInvoiceStatus();
                $sesudahPo = $po->fresh()->status;
                $sesudahHeader = $po->project->fresh()->status;
                $apply ? DB::commit() : DB::rollBack();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("GAGAL sinkron No. PO {$poId}, dibatalkan: " . $e->getMessage());

                return self::FAILURE;
            }

            $baris[] = [$po->project->code ?? '', $po->po_number, $sebelumPo, $sesudahPo, $sebelumHeader, $sesudahHeader];
        }

        $csv = $this->tulisCsv("bast-split-sync-po-{$runId}",
            ['project_code', 'no_po', 'status_po_sebelum', 'status_po_sesudah', 'status_header_sebelum', 'status_header_sesudah'], $baris);

        $berubah = collect($baris)->filter(fn ($b) => $b[2] !== $b[3] || $b[4] !== $b[5])->count();
        $this->info(($apply ? 'APPLY' : 'DRY RUN') . " sinkron status run {$runId}: " . count($baris) . " No. PO, {$berubah} berubah.");
        if (!$apply) {
            $this->line('Tidak ada yang ditulis. Review CSV lalu jalankan dengan --apply.');
        }
        $this->line('CSV: ' . $csv);

        return self::SUCCESS;
    }
}
