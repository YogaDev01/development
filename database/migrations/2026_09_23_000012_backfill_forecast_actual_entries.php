<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelamatkan realisasi yang sudah terlanjur terisi lewat form lama.
 *
 * Sejak realisasi dicatat sebagai entri, `forecast_details.actual_amount`
 * dihitung ulang dari entri-entrinya (ForecastDetail::syncActual()). Baris yang
 * angkanya berasal dari form lama tidak punya entri apa pun, jadi penambahan
 * realisasi pertama setelah pembaruan ini akan menghapus angka lamanya tanpa
 * disengaja. Di sini angka itu diubah menjadi satu entri historis, dengan
 * keterangan yang menjelaskan asalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tertinggal = DB::table('forecast_details')
            ->where('actual_amount', '>', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('forecast_actual_entries')
                  ->whereColumn('forecast_actual_entries.forecast_detail_id', 'forecast_details.id');
            })
            ->get(['id', 'actual_amount']);

        foreach ($tertinggal as $d) {
            DB::table('forecast_actual_entries')->insert([
                'forecast_detail_id' => $d->id,
                'amount'             => $d->actual_amount,
                'notes'              => 'Actual historis, dicatat sebelum keterangan per penambahan diwajibkan.',
                'created_by'         => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Entri historis tidak dibedakan dari entri biasa selain lewat
        // keterangannya; membalikkannya berarti menebak, jadi tidak dilakukan.
    }
};
