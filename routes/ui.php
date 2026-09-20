<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tesys UI — routes preview
|--------------------------------------------------------------------------
| File terpisah supaya tidak mengganggu routes/web.php.
| Diaktifkan dari routes/web.php dengan satu baris:
|
|     require __DIR__.'/ui.php';
|
| Hapus baris tersebut untuk menonaktifkan seluruh route di bawah ini.
*/

Route::middleware('auth')->prefix('ui')->name('ui.')->group(function () {

    // Halaman uji: markup Bootstrap/AdminLTE lama + layout & design system baru.
    Route::get('/preview', fn () => view('ui.preview'))->name('preview');

});
