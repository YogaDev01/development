<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Kontrol invarian & penulisan CSV laporan yang dipakai bersama command konversi BAST
 * (invoice:link-bast, invoice:split-synthetic-bast, bast:mark-backfill).
 */
trait ChecksInvoiceInvariants
{
    /** Count & SUM seluruh invoice (termasuk soft-deleted) — tidak boleh berubah. */
    private function invarian(): array
    {
        $r = DB::table('invoices')->selectRaw(
            'COUNT(*) AS n, COALESCE(SUM(billed_amount),0) AS billed, COALESCE(SUM(captured_total),0) AS captured,'
            . ' COALESCE(SUM(tax_amount),0) AS tax, COALESCE(SUM(withholding_tax),0) AS wht'
        )->first();

        return array_map('intval', (array) $r);
    }

    /** Tulis CSV ke storage/app/private/reports/<nama>.csv dan kembalikan path-nya. */
    private function tulisCsv(string $nama, array $header, iterable $baris): string
    {
        $dir = storage_path('app/private/reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $nama . '.csv';

        $fh = fopen($path, 'w');
        fputcsv($fh, $header);
        foreach ($baris as $b) {
            fputcsv($fh, $b);
        }
        fclose($fh);

        return $path;
    }
}
