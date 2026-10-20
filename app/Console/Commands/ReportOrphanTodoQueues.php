<?php

namespace App\Console\Commands;

use App\Http\Controllers\TodoListController;
use App\Models\User;
use App\Services\TodoService;
use Illuminate\Console\Command;

/**
 * Laporan BACA-SAJA "antrean yatim": dokumen yang berstatus antrean tetapi
 * tidak terlihat oleh handler mana pun karena scope Access Group (tidak ada
 * user aktif ber-role penangan yang proyeknya ada di Access Group-nya).
 *
 * Tidak menulis ke database. Pembanding "semua dokumen antrean" diambil dari
 * sudut pandang superadmin (tanpa scope); "terlihat" = gabungan queueQuery()
 * milik setiap user AKTIF dengan role penangan (selain superadmin).
 */
class ReportOrphanTodoQueues extends Command
{
    protected $signature = 'todo:orphan-report {--sample=10 : Jumlah id contoh per antrean}';

    protected $description = 'Laporan baca-saja: dokumen antrean Todo List yang tak terlihat oleh handler mana pun (scope Access Group)';

    public function handle(TodoListController $todo): int
    {
        // Semua user aktif dimuat SEKALI, lalu disaring per antrean di memori.
        $aktif      = User::with('role')->where('status', 'ACTIVE')->get();
        $superadmin = $aktif->first(fn ($u) => $u->hasRole('superadmin'));

        if (! $superadmin) {
            $this->error('Tidak ada user superadmin aktif sebagai pembanding "semua dokumen".');

            return self::FAILURE;
        }

        $sample = max(0, (int) $this->option('sample'));
        $rows   = [];

        foreach (TodoService::QUEUES as $key => $def) {
            $semua = $todo->sebagai($superadmin, fn () => $todo->queueQuery($key)->pluck('id')->all());

            $penangan = $aktif
                ->filter(fn ($u) => ! $u->hasRole('superadmin') && TodoService::allows($u, $key));

            $terlihat = [];
            foreach ($penangan as $u) {
                foreach ($todo->sebagai($u, fn () => $todo->queueQuery($key)->pluck('id')->all()) as $id) {
                    $terlihat[$id] = true;
                }
            }

            $yatim = array_values(array_diff($semua, array_keys($terlihat)));

            $rows[] = [
                $key, count($semua), $penangan->count(), count($yatim),
                implode(',', array_slice($yatim, 0, $sample)),
            ];
        }

        $this->table(['Antrean', 'Dokumen', 'Handler aktif', 'Yatim', 'Contoh id'], $rows);
        $this->line('Baca-saja: tidak ada data yang diubah.');

        return self::SUCCESS;
    }
}
