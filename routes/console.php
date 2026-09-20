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
