<?php

namespace App\Console\Commands;

use App\Models\BudgetPlan;
use Illuminate\Console\Command;

/**
 * Tutup (ACTIVE -> CLOSED) Budget Plan yang terhubung ke PO berstatus CLOSED.
 * Jalur normal menutup budget (-> CLOSED) saat PO ditutup; PO berstatus PAID tidak
 * memicu apa pun. PO yang ditutup lewat jalur lain bisa meninggalkan budget ACTIVE
 * yatim. Command ini membereskan data lama itu, mengikuti
 * BudgetPlanController::financeClose() (tanpa notifikasi).
 *
 * Tanpa --apply hanya menampilkan daftar (dry run). Budget non-ACTIVE
 * (CLOSING, CLOSED, dll.) tidak disentuh.
 */
class CloseBudgetPlansOfClosedProjects extends Command
{
    protected $signature = 'budget:close-by-closed-po {--apply : Simpan perubahan (tanpa opsi ini hanya dry run)}';

    protected $description = 'Tutup Budget Plan ACTIVE yang terhubung ke PO CLOSED (dry run kecuali --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ubah  = 0;

        BudgetPlan::where('status', 'ACTIVE')
            ->whereHas('project', fn ($q) => $q->where('status', 'CLOSED'))
            ->with('project:id,code,status')
            ->orderBy('id')
            ->each(function (BudgetPlan $bp) use ($apply, &$ubah) {
                $ubah++;
                $this->line(($apply ? 'TUTUP: ' : 'AKAN TUTUP: ') . $bp->code . ' (PO ' . $bp->project->code . ')');

                if ($apply) {
                    $catatan = 'PO ' . $bp->project->code . ' sudah Closed — Budget Plan ditutup otomatis (penyesuaian data).';
                    $bp->update(['status' => 'CLOSED', 'approver_notes' => $catatan, 'approved_at' => now()]);
                    $bp->addLog('ACTIVE', 'CLOSED', 'finance_closed', $catatan);
                }
            });

        $this->info($ubah . ' Budget Plan ' . ($apply ? 'ditutup.' : 'akan ditutup. Jalankan dengan --apply untuk menyimpan.'));

        return self::SUCCESS;
    }
}
