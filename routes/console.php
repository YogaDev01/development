<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Finance Forecast: auto-close ACTIVE forecast yang date_end sudah lewat
// (jalur otomatis selain tombol Close manual — lihat ForecastController).
Schedule::command('forecast:auto-close')->daily();

/*
| Reminder To-Do List — tiap jam. Mengirim pengingat ke user ACTIVE yang
| mengaktifkan reminder (Profil > Settings) saat jam ini cocok dengan
| jadwalnya DAN masih ada item di To-Do List-nya. Lihat
| App\Console\Commands\SendTodoReminders.
|
| withoutOverlapping(): kalau satu jalannya tertahan, jangan menumpuk.
*/
Schedule::command('todo:remind')->hourly()->withoutOverlapping();

/*
| Kesehatan antrean — tiap 30 menit.
|
| Seluruh notifikasi aplikasi ini melewati antrean (AppNotification memakai
| ShouldQueue), jadi worker yang mati berarti notifikasi berhenti TANPA gejala
| apa pun di layar. Itu sudah pernah terjadi dan tidak ketahuan selama tiga
| hari. Perintah ini yang memberinya suara di log.
|
| withoutOverlapping(): kalau satu pemeriksaan tertahan, jangan menumpuk
| pemeriksaan lain di atasnya.
*/
Schedule::command('queue:health')->everyThirtyMinutes()->withoutOverlapping();
