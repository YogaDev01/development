<?php

namespace Database\Seeders;

use App\Http\Controllers\SiteController;
use App\Models\Site;
use App\Models\SiteBackupTechnician;
use App\Models\SiteKeeper;
use App\Models\User;
use Illuminate\Database\Seeder;

/*
|--------------------------------------------------------------------------
| DUMMY TEKNISI BACKUP & SITE KEEPER
|--------------------------------------------------------------------------
| Mengisi dua daftar yang masih kosong di tab PIC pada drawer Site:
|
|   1. Teknisi Backup -> diambil dari teknisi yang BENAR-BENAR ada
|      (SiteController::backupTechnicianOptions(), yaitu user ACTIVE milik
|      PIC internal ACTIVE dan bukan leader team). Diutamakan teknisi yang
|      customer-nya sama dengan site; kalau tidak ada, dipakai teknisi mana
|      pun supaya pilihan Change Teknisi tidak kosong.
|   2. Site Keeper -> nama dummy, tetapi diambil dari nama user yang ada
|      karena validasi form site mewajibkan `keepers.*.name` lolos
|      `exists:users,name` (lihat SiteController::keeperRules()).
|
| Jalankan: php artisan db:seed --class=DummySitePicFillSeeder
| Idempoten: site yang daftarnya sudah terisi dilewati; tidak ada baris lama
| yang dihapus.
*/
class DummySitePicFillSeeder extends Seeder
{
    /** Jumlah maksimum teknisi backup & site keeper yang dibuat per site. */
    private const BACKUP_PER_SITE = 2;

    private const KEEPER_PER_SITE = 1;

    public function run(): void
    {
        $options = collect(SiteController::backupTechnicianOptions());

        if ($options->isEmpty()) {
            $this->command?->warn('Tidak ada kandidat teknisi (user ACTIVE + PIC internal ACTIVE). Teknisi Backup dilewati.');
        }

        // Kandidat nama Site Keeper: user ACTIVE mana pun, supaya lolos
        // validasi exists:users,name saat site-nya diedit lewat form.
        $keeperNames = User::query()
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->pluck('name', 'id');

        $backupFilled = 0;
        $keeperFilled = 0;

        Site::with(['backupTechnicians', 'keepers', 'picLinks.pic'])
            ->orderBy('id')
            ->each(function (Site $site) use ($options, $keeperNames, &$backupFilled, &$keeperFilled) {
                $backupFilled += $this->fillBackupTechnicians($site, $options);
                $keeperFilled += $this->fillKeepers($site, $keeperNames);
            });

        $this->command?->info("Teknisi Backup dibuat: {$backupFilled} baris.");
        $this->command?->info("Site Keeper dibuat: {$keeperFilled} baris.");
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{id:int, name:string, customers:array<int,int>}>  $options
     */
    private function fillBackupTechnicians(Site $site, $options): int
    {
        if ($options->isEmpty() || $site->backupTechnicians->isNotEmpty()) {
            return 0;
        }

        // PIC site sendiri tidak dipakai sebagai cadangan dirinya sendiri.
        $ownPicUserIds = $site->picLinks
            ->pluck('pic.user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $sameCustomer = $options
            ->filter(fn ($o) => $site->customer_id && in_array((int) $site->customer_id, $o['customers'], true));

        $pool = ($sameCustomer->isNotEmpty() ? $sameCustomer : $options)
            ->reject(fn ($o) => in_array((int) $o['id'], $ownPicUserIds, true))
            ->values();

        if ($pool->isEmpty()) {
            return 0;
        }

        // Rotasi berdasarkan id site supaya tiap site tidak dapat orang sama.
        $created = 0;
        $count = min(self::BACKUP_PER_SITE, $pool->count());
        for ($i = 0; $i < $count; $i++) {
            $pick = $pool[(($site->id + $i) % $pool->count())];
            SiteBackupTechnician::firstOrCreate([
                'site_id' => $site->id,
                'user_id' => (int) $pick['id'],
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $keeperNames
     */
    private function fillKeepers(Site $site, $keeperNames): int
    {
        if ($keeperNames->isEmpty() || $site->keepers->isNotEmpty()) {
            return 0;
        }

        $names = $keeperNames->values();
        $created = 0;
        $count = min(self::KEEPER_PER_SITE, $names->count());
        for ($i = 0; $i < $count; $i++) {
            SiteKeeper::firstOrCreate([
                'site_id' => $site->id,
                'name' => $names[(($site->id + $i) % $names->count())],
            ]);
            $created++;
        }

        return $created;
    }
}
