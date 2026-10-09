<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Kode katalog material/aksesoris: {Huruf}{Inisial Customer}-{MMYY}{5 digit}.
 * Contoh: PBTS-092600001 (material), ABTS-092600001 (aksesoris).
 *
 * Nomor urut per huruf + customer + bulan, diambil dari DocumentSequence.
 */
final class MasterItemCode
{
    public static function next(string $letter, Customer $customer, string $table, string $column): string
    {
        $prefix = $letter . strtoupper((string) $customer->initial) . '-' . now()->format('my');

        $number = DocumentSequence::next('master-item:' . $prefix, function () use ($table, $column, $prefix) {
            $last = DB::table($table)->where($column, 'like', $prefix . '%')->max($column);

            return $last ? (int) substr($last, strlen($prefix)) : 0;
        });

        return $prefix . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
