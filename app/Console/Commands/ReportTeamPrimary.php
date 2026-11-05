<?php

namespace App\Console\Commands;

use App\Models\TeamMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan BACA-SAJA (tidak menulis apa pun ke database): bahan penetapan
 * Team Primary (blueprint 2026-11-06, K8).
 *
 * Isi CSV: setiap user yang punya membership ACTIVE, berapa team aktifnya,
 * daftar team-nya, dan team primary saat ini. User dengan lebih dari satu
 * team aktif ditandai PERLU_KEPUTUSAN — primary-nya ditetapkan manusia lewat
 * form Edit Team, tidak ditebak oleh sistem.
 */
class ReportTeamPrimary extends Command
{
    protected $signature = 'team:primary-report';

    protected $description = 'Laporan baca-saja: user dengan membership team ACTIVE & kandidat Team Primary (CSV)';

    public function handle(): int
    {
        $punyaPrimary = Schema::hasColumn('team_members', 'is_primary');

        $dir = storage_path('app/private/reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'team-primary-' . now()->format('YmdHis') . '.csv';

        $fh = fopen($path, 'w');
        fputcsv($fh, ['user_id', 'username', 'nama', 'jumlah_team_aktif', 'team_aktif', 'primary_saat_ini', 'catatan']);

        $kolom = ['id', 'team_id', 'user_id', 'status'];
        if ($punyaPrimary) {
            $kolom[] = 'is_primary';
        }

        $rows = TeamMember::query()
            ->where('team_members.status', 'ACTIVE')
            ->whereHas('team', fn ($t) => $t->where('status', 'ACTIVE'))
            ->with(['team:id,code,name', 'user:id,username,name'])
            ->get($kolom)
            ->groupBy('user_id');

        $tunggal = 0;
        $ganda   = 0;

        foreach ($rows->sortKeys() as $userId => $memberships) {
            $jumlah  = $memberships->count();
            $primary = $punyaPrimary
                ? $memberships->where('is_primary', true)->map(fn ($m) => $m->team?->code)->implode(', ')
                : '';

            if ($jumlah > 1) {
                $ganda++;
                $catatan = $primary === '' ? 'PERLU_KEPUTUSAN' : 'SUDAH_DITETAPKAN';
            } else {
                $tunggal++;
                $catatan = $primary === '' ? 'SATU_TEAM_BELUM_PRIMARY' : 'SUDAH_DITETAPKAN';
            }

            $user = $memberships->first()->user;
            fputcsv($fh, [
                $userId,
                $user?->username,
                $user?->name,
                $jumlah,
                $memberships->map(fn ($m) => ($m->team?->code ?? '#' . $m->team_id) . ' ' . ($m->team?->name ?? ''))->implode(' | '),
                $primary,
                $catatan,
            ]);
        }

        fclose($fh);

        if (! $punyaPrimary) {
            $this->warn('Kolom team_members.is_primary belum ada (migrasi 2026_11_06_000100 belum dijalankan).');
        }
        $this->info("{$ganda} user aktif di lebih dari satu team, {$tunggal} user di satu team. Tidak ada perubahan data.");
        $this->line('CSV: ' . $path);

        return self::SUCCESS;
    }
}
