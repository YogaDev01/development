<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyederhanakan opsi "Done By" (kolom DB tetap customer_type_id) menjadi:
 *   - Terex
 *   - Hanasta
 *
 * Customer lama yang bertipe Overhead / Maintenance / Fuel dipetakan ke "Terex".
 * Tipe lain yang tak dikenal sengaja TIDAK disentuh agar aman.
 */
return new class extends Migration
{
    private array $oldNames = ['overhead', 'maintenance', 'fuel'];

    public function up(): void
    {
        $terexId = $this->ensureType('Terex');
        $this->ensureType('Hanasta');

        // ID tipe lama (case-insensitive)
        $oldIds = DB::table('customer_types')
            ->get()
            ->filter(fn ($t) => in_array(strtolower($t->name), $this->oldNames, true))
            ->pluck('id')
            ->all();

        if (! empty($oldIds)) {

            // Remap customer ke Terex
            DB::table('customers')
                ->whereIn('customer_type_id', $oldIds)
                ->update([
                    'customer_type_id' => $terexId,
                    'updated_at'       => now(),
                ]);

            // Hapus baris tipe lama
            DB::table('customer_types')
                ->whereIn('id', $oldIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // Pemetaan data tidak dapat dikembalikan sempurna.
        // Kita hanya memunculkan kembali baris tipe lama agar tersedia di dropdown.
        foreach (['Overhead', 'Maintenance', 'Fuel'] as $name) {
            $this->ensureType($name);
        }
    }

    /**
     * Pastikan sebuah customer_type ada; kembalikan id-nya.
     */
    private function ensureType(string $name): int
    {
        $existing = DB::table('customer_types')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('customer_types')->insertGetId([
            'name'       => $name,
            'status'     => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};