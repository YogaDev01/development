<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChecksInvoiceInvariants;
use App\Models\ProjectPoBast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tandai BAST hasil backfill migrasi 2026_10_18_000100 (origin = BACKFILL) supaya
 * invoice:split-synthetic-bast hanya memecah BAST sintetis, bukan BAST asli.
 *
 * Batas id diisi manusia dari produksi (--max-backfill-id = id BAST terbesar yang
 * dibuat migrasi). Command hanya menulis kolom `origin` (tidak menyentuh nilai
 * invoice, status, maupun nilai/no/tanggal BAST). Dry run kecuali --apply.
 *
 * BAST yang meragukan dilaporkan dan TIDAK ditandai:
 *  - BANYAK_BAST: No. PO punya >1 BAST (backfill hanya membuat satu per No. PO, jadi batasnya
 *    kemungkinan terlalu tinggi dan mencakup BAST submit normal);
 *  - NILAI_BEDA_AGREGAT / NO_BEDA_AGREGAT: nilai atau nomor BAST tidak sama dengan kolom agregat
 *    No. PO (backfill menyalin keduanya persis).
 */
class MarkBackfillBasts extends Command
{
    use ChecksInvoiceInvariants;

    protected $signature = 'bast:mark-backfill
        {--max-backfill-id= : id BAST terbesar yang dibuat migrasi backfill (wajib, diisi manusia dari produksi)}
        {--apply : Tulis kolom origin (tanpa opsi ini hanya dry run)}';

    protected $description = 'Tandai BAST hasil backfill (origin=BACKFILL) — dry run kecuali --apply';

    public function handle(): int
    {
        $max = (int) $this->option('max-backfill-id');
        if ($max < 1) {
            $this->error('Isi --max-backfill-id=<id BAST backfill terbesar> (angka positif).');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $runId = now()->format('YmdHis') . '-' . Str::lower(Str::random(6));

        $rows = [];
        $layak = [];
        ProjectPoBast::with('poNumber')->where('id', '<=', $max)->whereNull('origin')->orderBy('id')
            ->each(function (ProjectPoBast $b) use (&$rows, &$layak) {
                $po = $b->poNumber;
                $alasan = [];
                if (ProjectPoBast::withTrashed()->where('project_po_number_id', $b->project_po_number_id)->count() > 1) {
                    $alasan[] = 'BANYAK_BAST';
                }
                if ($po && (int) $po->nilai_bast !== (int) $b->nilai_bast) {
                    $alasan[] = 'NILAI_BEDA_AGREGAT';
                }
                if ($po && (string) $po->no_bast !== (string) $b->no_bast) {
                    $alasan[] = 'NO_BEDA_AGREGAT';
                }

                $rows[] = [$b->id, $po->po_number ?? '-', $b->no_bast, (int) $b->nilai_bast,
                    $alasan ? 'MERAGUKAN (tidak ditandai)' : '', implode(',', $alasan)];
                if (!$alasan) {
                    $layak[] = $b;
                }
            });

        $ditandai = 0;
        if ($apply && $layak) {
            try {
                $ditandai = $this->tulis($layak, $runId);
            } catch (\Throwable $e) {
                $this->error('GAGAL, semua perubahan dibatalkan: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        // Status di CSV mengikuti hasil sebenarnya.
        foreach ($rows as &$r) {
            if (str_starts_with($r[4], 'MERAGUKAN')) {
                continue;
            }
            $r[4] = $apply ? 'DITANDAI' : 'AKAN_DITANDAI';
        }
        unset($r);

        $csv = $this->tulisCsv("bast-mark-backfill-{$runId}",
            ['bast_id', 'no_po', 'no_bast', 'nilai_bast', 'status', 'alasan'], $rows);

        $meragukan = collect($rows)->filter(fn ($r) => str_starts_with($r[4], 'MERAGUKAN'))->count();
        $this->info(($apply ? 'APPLY' : 'DRY RUN') . " run_id: {$runId}");
        $this->line('Kandidat (id <= ' . $max . ', origin NULL): ' . count($rows));
        $this->line('Layak ditandai: ' . count($layak) . ($apply ? " (ditulis: {$ditandai})" : ' (belum ditulis; jalankan dengan --apply)'));
        $this->line('Meragukan (TIDAK ditandai, periksa manual): ' . $meragukan);
        $this->line('CSV: ' . $csv);

        return self::SUCCESS;
    }

    /** Tulis origin saja, dalam satu transaksi, dengan kontrol invarian. */
    private function tulis(array $layak, string $runId): int
    {
        $ditulis = 0;

        DB::transaction(function () use ($layak, $runId, &$ditulis) {
            $sebelumInvoice = $this->invarian();
            $sebelumBast    = $this->ringkasBast();

            foreach ($layak as $b) {
                $n = DB::table('project_po_basts')->where('id', $b->id)->whereNull('origin')
                    ->update(['origin' => ProjectPoBast::ORIGIN_BACKFILL]);
                if ($n !== 1) {
                    continue;
                }
                DB::table('bast_split_log')->insert([
                    'run_id'               => $runId,
                    'project_po_number_id' => $b->project_po_number_id,
                    'parent_bast_id'       => $b->id,
                    'action'               => 'MARK_BACKFILL',
                    'nilai'                => (int) $b->nilai_bast,
                    'created_at'           => now(),
                ]);
                $ditulis++;
            }

            if ($sebelumInvoice !== $this->invarian() || $sebelumBast !== $this->ringkasBast()) {
                throw new \RuntimeException('Kontrol invarian gagal: invoice atau nilai BAST berubah.');
            }
        });

        return $ditulis;
    }

    /** Jumlah baris & total nilai BAST (termasuk terhapus) — tidak boleh berubah. */
    private function ringkasBast(): array
    {
        $r = DB::table('project_po_basts')->selectRaw('COUNT(*) AS n, COALESCE(SUM(nilai_bast),0) AS nilai')->first();

        return array_map('intval', (array) $r);
    }
}
