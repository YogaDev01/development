<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotifyService;
use App\Services\TodoService;
use Illuminate\Console\Command;

/**
 * Reminder To-Do List — satu-satunya notifikasi terjadwal yang bisa diatur
 * user (Profil > Settings > Preferensi Notifikasi).
 *
 * Dijalankan TIAP JAM (routes/console.php, ->hourly()). Untuk setiap user
 * ACTIVE yang mengaktifkan reminder dan jam ini cocok dengan jadwalnya
 * (mode 'fixed' = jam tertentu, 'interval' = tiap N jam), dihitung To-Do
 * List-nya secara LIVE lewat TodoService::build(). Jika masih ada item,
 * dikirim satu pengingat (lonceng in-app + WhatsApp bila di-opt-in). Bila
 * tidak ada item, tidak ada pengingat.
 *
 * Kecocokan jam memakai config('app.timezone') (Asia/Jakarta) lewat now(),
 * sama seperti command terjadwal lain. Stateless: tidak menyimpan "terakhir
 * dikirim", jadwal murni fungsi dari jam saat ini.
 */
class SendTodoReminders extends Command
{
    protected $signature = 'todo:remind';

    protected $description = 'Kirim pengingat To-Do List ke user yang jadwalnya jatuh tempo jam ini (jika masih ada tugas)';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        User::with('role')
            ->where('status', 'ACTIVE')
            ->chunkById(200, function ($users) use ($now, &$sent) {
                foreach ($users as $user) {
                    if (! $user->todoReminderDueNow($now)) {
                        continue;
                    }

                    $count = count(TodoService::build($user));
                    if ($count === 0) {
                        continue;
                    }

                    $message = 'Anda memiliki '.$count.' tugas di To-Do List yang menunggu diproses. '
                        .'Silakan periksa dan tindak lanjuti.';

                    NotifyService::send(
                        [$user],
                        'Pengingat To-Do List',
                        $message,
                        route('todo-list.index'),
                        'todo',
                        "⏰ Pengingat To-Do List\n\n".$message
                    );

                    $sent++;
                }
            });

        $this->info($sent.' pengingat To-Do List dikirim.');

        return self::SUCCESS;
    }
}
