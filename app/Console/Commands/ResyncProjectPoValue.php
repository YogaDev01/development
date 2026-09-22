<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronkan ulang projects.value = SUM(project_po_numbers.nominal).
 *
 * PO Value tidak lagi diinput manual — kolom `value` dijaga otomatis oleh
 * Project::syncValueFromPoNumbers() yang dipicu event ProjectPoNumber.
 * Command ini untuk menambal data yang terlanjur basi (PO lama, atau baris
 * No. PO yang pernah dihapus lewat query builder sehingga event Eloquent
 * tidak jalan). Aman dijalankan berulang.
 *
 * Pakai --dry-run untuk melihat PO mana saja yang tidak sinkron tanpa
 * mengubah data.
 */
class ResyncProjectPoValue extends Command
{
    protected $signature = 'project:resync-po-value {--dry-run : Tampilkan selisih saja, tanpa menyimpan}';

    protected $description = 'Hitung ulang PO Value (projects.value) dari total nominal No. PO';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $sums = DB::table('project_po_numbers')
            ->selectRaw('project_id, SUM(nominal) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $projects = DB::table('projects')->orderBy('id')->get(['id', 'code', 'name', 'value']);

        $rows = [];
        foreach ($projects as $project) {
            $expected = (int) ($sums[$project->id] ?? 0);
            $current  = (int) ($project->value ?? 0);

            if ($expected === $current) {
                continue;
            }

            $rows[] = [
                $project->code ?? $project->id,
                \Illuminate\Support\Str::limit($project->name ?? '-', 40),
                number_format($current, 0, ',', '.'),
                number_format($expected, 0, ',', '.'),
            ];

            if (! $dryRun) {
                DB::table('projects')->where('id', $project->id)->update(['value' => $expected]);
            }
        }

        if (! $rows) {
            $this->info('Semua PO Value sudah sinkron dengan total No. PO.');

            return self::SUCCESS;
        }

        $this->table(['Kode', 'Scope of Work', 'Value Lama', 'Value Baru'], $rows);
        $this->info(($dryRun ? 'DRY RUN — ' : '') . count($rows) . ' PO tidak sinkron' . ($dryRun ? ' (tidak diubah).' : ' dan sudah diperbarui.'));

        return self::SUCCESS;
    }
}
