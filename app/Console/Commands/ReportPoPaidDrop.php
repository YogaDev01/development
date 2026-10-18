<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use Illuminate\Console\Command;

/**
 * Laporan BACA-SAJA (tidak menulis apa pun ke database): No. PO berstatus PAID
 * yang tidak lagi memenuhi syarat PAID menurut aturan status per BAST (Fase 2):
 * total BAST = nominal, setiap BAST punya invoice aktif, dan semua invoice aktif
 * itu PAID. Jalankan SESUDAH `invoice:link-bast --apply` supaya tautan invoice-BAST ada.
 */
class ReportPoPaidDrop extends Command
{
    protected $signature = 'invoice:report-po-paid-drop';

    protected $description = 'Laporan baca-saja: No. PO PAID yang akan turun bila aturan status per BAST diterapkan (CSV)';

    public function handle(): int
    {
        $dir = storage_path('app/private/reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'po-paid-drop-' . now()->format('YmdHis') . '.csv';

        $fh = fopen($path, 'w');
        fputcsv($fh, ['project_code', 'no_po', 'nominal', 'total_bast', 'jumlah_bast', 'bast_tertagih', 'alasan']);

        $total = 0;
        $turun = 0;
        ProjectPoNumber::where('status', 'PAID')->with('project:id,code')->orderBy('id')
            ->each(function (ProjectPoNumber $po) use ($fh, &$total, &$turun) {
                $total++;
                $basts = ProjectPoBast::where('project_po_number_id', $po->id)->get();
                $sum   = (int) $basts->sum('nilai_bast');

                $alasan   = [];
                $tertagih = 0;
                foreach ($basts as $b) {
                    $aktif = Invoice::where('project_po_bast_id', $b->id)->where('status', '!=', 'CANCELLED')->get();
                    if ($aktif->isEmpty()) {
                        continue;
                    }
                    $tertagih++;
                    if ($aktif->contains(fn ($i) => $i->status !== 'PAID')) {
                        $alasan['INVOICE_BELUM_PAID'] = true;
                    }
                }

                if ($basts->isEmpty()) {
                    $alasan['TANPA_BAST'] = true;
                } else {
                    if ($sum !== (int) $po->nominal) {
                        $alasan['TOTAL_BAST_BEDA_NOMINAL'] = true;
                    }
                    if ($tertagih < $basts->count()) {
                        $alasan['BAST_BELUM_TERTAGIH_ATAU_TERTAUT'] = true;
                    }
                }

                if ($alasan) {
                    $turun++;
                    fputcsv($fh, [
                        $po->project?->code, $po->po_number, (int) $po->nominal, $sum,
                        $basts->count(), $tertagih, implode(',', array_keys($alasan)),
                    ]);
                }
            });

        fclose($fh);

        $this->info("{$turun} dari {$total} No. PO PAID akan turun. Tidak ada perubahan data.");
        $this->line('CSV: ' . $path);

        return self::SUCCESS;
    }
}
