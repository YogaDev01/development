<?php

namespace App\Console\Commands;

use App\Models\BudgetPlan;
use App\Services\NotifyService;
use App\Services\RoleDirectory;
use Illuminate\Console\Command;

/**
 * Budget Plan yang masih ACTIVE dan sudah sampai tanggal plan_finish-nya
 * dipindahkan otomatis ke CLOSING — periode anggarannya habis, jadi tidak
 * boleh lagi dianggap berjalan.
 *
 * closing_submitted_at sengaja dibiarkan NULL: statusnya CLOSING, tapi
 * berkas closing belum diajukan, sehingga tugasnya tetap di manager (muncul
 * di Todo List manager) dan belum masuk worklist verifikasi Finance.
 *
 * today() mengikuti config('app.timezone') (Asia/Jakarta), bukan timezone
 * server — sama seperti forecast:auto-close.
 */
class AutoClosingBudgetPlans extends Command
{
    protected $signature = 'budget-plan:auto-closing';

    protected $description = 'Pindahkan Budget Plan ACTIVE yang sudah sampai tanggal plan_finish ke status CLOSING';

    public function handle(RoleDirectory $roles): int
    {
        $plans = BudgetPlan::with('inputBy')
            ->where('status', 'ACTIVE')
            ->whereNotNull('plan_finish')
            ->whereDate('plan_finish', '<=', today())
            ->get();

        if ($plans->isEmpty()) {
            $this->info('Tidak ada Budget Plan yang jatuh tempo.');
            return self::SUCCESS;
        }

        // Daftar manager diambil sekali, bukan per baris.
        $managers = $roles->managers();

        foreach ($plans as $bp) {
            $bp->update([
                'status'               => 'CLOSING',
                'closing_submitted_at' => null,
            ]);

            // Tanpa pengguna yang masuk, addLog() mencatatnya sebagai 'System'.
            $bp->addLog(
                'ACTIVE',
                'CLOSING',
                'auto_closing',
                'Plan Finish (' . $bp->plan_finish->format('d-m-Y') . ') sudah tercapai.'
            );

            // Pembuat budget ikut diberi tahu meski perannya bukan manager.
            $penerima = $managers->concat(array_filter([$bp->inputBy]));

            NotifyService::send(
                $penerima,
                'Budget Plan masuk tahap Closing',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') sudah sampai Plan Finish '
                    . $bp->plan_finish->format('d-m-Y') . ' dan otomatis masuk tahap Closing. '
                    . 'Silakan ajukan closing beserta lampirannya.',
                route('budget-plan.index'),
                'workflow'
            );

            $this->info('CLOSING: ' . $bp->code . ' — ' . $bp->description);
        }

        $this->info($plans->count() . ' Budget Plan dipindahkan ke CLOSING.');

        return self::SUCCESS;
    }
}
