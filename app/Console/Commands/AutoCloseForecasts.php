<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Forecast;

/**
 * Auto-close Finance Forecast yang masih ACTIVE tapi date_end sudah
 * terlewati — jalur otomatis untuk Close selain tombol manual (lihat
 * ForecastController::close()). today() sudah mengikuti config('app.timezone')
 * (Asia/Jakarta), BUKAN timezone server, sesuai dokumen Minor... Major B.
 */
class AutoCloseForecasts extends Command
{
    protected $signature = 'forecast:auto-close';

    protected $description = 'Auto-close Finance Forecast ACTIVE yang date_end-nya sudah lewat';

    public function handle(): int
    {
        $forecasts = Forecast::where('status', 'ACTIVE')
            ->whereDate('date_end', '<', today())
            ->get();

        foreach ($forecasts as $forecast) {
            $forecast->update(['status' => 'CLOSED', 'closed_at' => now()]);
            // `description` sudah pensiun sejak 2026_09_20_000013 — kolomnya
            // masih ada tapi tidak pernah diisi lagi, jadi barisnya selalu
            // tercetak kosong. Penggantinya `notes`, lewat accessor `label`.
            $this->info('Closed forecast #' . $forecast->id . ' (' . $forecast->label . ')');
        }

        $this->info($forecasts->count() . ' forecast di-auto-close.');

        return self::SUCCESS;
    }
}
