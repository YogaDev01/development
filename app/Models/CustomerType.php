<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    /**
     * Satu-satunya model dari 73 yang sebelumnya tidak punya $fillable maupun
     * $guarded. Belum bisa disalahgunakan — tidak ada route yang menulis ke
     * tabel ini — tapi ia jadi jebakan bagi penulisan berikutnya: begitu ada
     * yang menambahkan CustomerType::create($request->all()), kolom apa pun
     * yang dikirim peramban akan lolos. Diisi sekarang, saat masih murah.
     *
     * Kolom mengikuti migrasi 2026_05_16_092458_create_customer_types_table.
     */
    protected $fillable = [
        'name',
        'status',
    ];
}
