<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckUserRoles extends Command
{
    protected $signature = 'roles:check';

    protected $description = 'Audit integritas role tiap user: deteksi role hilang atau nama role yang tidak dikenal (membuat hasRole() selalu gagal).';

    /**
     * Role kanonik yang dipahami oleh hasRole().
     */
    private array $canonical = ['superadmin', 'admin', 'approver', 'finance', 'maker', 'guest'];

    public function handle(): int
    {
        $users = User::with('role')->get();

        $problems = [];

        foreach ($users as $user) {
            $roleName = $user->role?->name;

            if (!$roleName) {
                $problems[] = [$user->id, $user->email ?? $user->name, $user->role_id, 'ROLE TIDAK DITEMUKAN'];
                continue;
            }

            if (!in_array(strtolower(trim($roleName)), $this->canonical, true)) {
                $problems[] = [$user->id, $user->email ?? $user->name, $user->role_id, "Nama role tidak dikenal: '{$roleName}'"];
            }
        }

        if (empty($problems)) {
            $this->info('OK — semua user memiliki role yang valid dan dikenal.');
        } else {
            $this->warn(count($problems) . ' user bermasalah (akan tertolak di semua pengecekan hasRole):');
            $this->table(['User ID', 'User', 'role_id', 'Masalah'], $problems);
            $this->line("Perbaiki dengan menetapkan role_id yang benar, mis. lewat halaman User atau tinker.");
        }

        // Ringkasan jumlah user per role kanonik.
        $this->newLine();
        $this->info('Jumlah user per role:');

        $rows = [];
        foreach ($this->canonical as $role) {
            $count = User::whereHas('role', function ($q) use ($role) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$role]);
            })->count();

            $rows[] = [$role, $count];
        }

        $this->table(['Role', 'Jumlah user'], $rows);

        return self::SUCCESS;
    }
}