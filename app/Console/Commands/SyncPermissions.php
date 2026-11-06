<?php

namespace App\Console\Commands;

use App\Support\Authorizer;
use Illuminate\Console\Command;

/**
 * Tambah pasangan role × permission dari seed_roles yang belum pernah ada
 * (parity dengan gerbang role: lama), mis. untuk permission atau role baru.
 * Bawaan dry-run: hanya melaporkan. Tidak pernah menghapus atau mengubah
 * baris yang sudah ada.
 */
class SyncPermissions extends Command
{
    protected $signature = 'security:sync-permissions {--apply : Benar-benar menulis ke role_permissions}';

    protected $description = 'Tambah hak role_permissions yang belum ada dari config/permissions.php (bawaan: dry-run)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $hasil = Authorizer::syncSeed($apply);

        $this->info(($apply ? 'DITULIS' : 'DRY-RUN (tidak ada yang ditulis)') . ':');
        $this->line('  Permission yang ditambah barisnya : ' . $hasil['permissions_seeded']);
        $this->line('  Baris role_permissions baru       : ' . $hasil['rows']);
        $this->line('  (Baris yang sudah ada, termasuk hak yang dicabut, tidak disentuh.)');

        if ($hasil['missing_roles']) {
            $this->warn('  Role disebut gerbang tetapi tidak ada di tabel roles: ' . implode(', ', $hasil['missing_roles']));
        }

        return self::SUCCESS;
    }
}
