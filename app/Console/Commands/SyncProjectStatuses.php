<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * Hitung ulang status header PO yang sudah ada dari agregat status No. PO
 * (Project::syncStatusFromPoNumbers()). Status itu hanya dihitung saat ada
 * kejadian baru pada No. PO, jadi PO lama bisa menyimpan status usang —
 * mis. "Waiting Invoice" padahal ada No. PO yang BAST-nya masih Partial.
 *
 * Tanpa --apply hanya menampilkan perubahan yang akan terjadi (dry run).
 * Hanya menyentuh status berjalan (Project::OPERATIONAL_STATUSES). Status
 * Budget Plan tidak ikut berubah (BP baru ditutup saat PO ditutup).
 */
class SyncProjectStatuses extends Command
{
    protected $signature = 'project:sync-status {--apply : Simpan perubahan (tanpa opsi ini hanya dry run)}';

    protected $description = 'Hitung ulang status PO berjalan dari status No. PO (dry run kecuali --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ubah  = 0;

        Project::whereIn('status', Project::OPERATIONAL_STATUSES)
            ->with('poNumbers')
            ->orderBy('id')
            ->each(function (Project $po) use ($apply, &$ubah) {
                $target = $po->operationalStatus();
                if ($target === $po->status) {
                    return;
                }

                $ubah++;
                $this->line(($apply ? 'UBAH: ' : 'AKAN UBAH: ') . $po->code . ' ' . $po->status . ' -> ' . $target);

                if ($apply) {
                    $po->syncStatusFromPoNumbers();
                }
            });

        $this->info($ubah . ' PO ' . ($apply ? 'diubah.' : 'akan berubah. Jalankan dengan --apply untuk menyimpan.'));

        return self::SUCCESS;
    }
}
