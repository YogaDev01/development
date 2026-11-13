<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mulai berlaku kunci periode
    |--------------------------------------------------------------------------
    | Format Y-m (mis. '2026-12'). Closing berstatus CLOSED hanya memblokir
    | penulisan bila periodenya >= nilai ini. Null = semua periode CLOSED
    | memblokir. Dipakai supaya periode lama yang sudah ditutup sebelum fitur
    | ini ada tidak mendadak terkunci. Lihat App\Support\PeriodLock.
    */
    'enforce_from_period' => env('CLOSING_ENFORCE_FROM_PERIOD'),

];
