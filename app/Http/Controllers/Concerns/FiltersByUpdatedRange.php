<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\Request;

/**
 * Filter "Last Update Time" (?date_from=&date_to=&range=) untuk halaman daftar
 * yang aslinya menampilkan SEMUA data (Collection, Delivery Note).
 *
 * Beda dengan Cost Request / Treasury: TANPA parameter, tidak ada pembatasan
 * (bukan "bulan berjalan"). Invoice lama yang belum lunas harus tetap tampil
 * kecuali user sendiri memilih rentang.
 */
trait FiltersByUpdatedRange
{
    /**
     * Terapkan rentang tanggal ke query bila ada dan valid.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return array{0:string,1:string} [dateFrom, dateTo] Y-m-d yang dipakai ('' = tidak dibatasi)
     */
    protected function applyUpdatedRange($query, Request $request, string $column = 'updated_at'): array
    {
        $from = $this->parseRangeDate($request->query('date_from'));
        $to   = $this->parseRangeDate($request->query('date_to'));

        if ($from !== '') {
            $query->where($column, '>=', Carbon::createFromFormat('!Y-m-d', $from)->startOfDay());
        }
        if ($to !== '') {
            $query->where($column, '<=', Carbon::createFromFormat('!Y-m-d', $to)->endOfDay());
        }

        return [$from, $to];
    }

    /** Hanya Y-m-d yang sah; selain itu dianggap tidak ada. */
    private function parseRangeDate($value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        try {
            $d = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable $e) {
            return '';
        }

        return $d && $d->format('Y-m-d') === $value ? $value : '';
    }
}
