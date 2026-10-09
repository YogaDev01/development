<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master wilayah seluruh Indonesia: pulau -> provinsi -> kota -> kecamatan -> desa.
 *
 * Sumber: github.com/cahyadsn/wilayah (MIT), Kepmendagri No 300.2.2-2138
 * Tahun 2025, dikonversi ke database/seeders/data/wilayah/*.csv.
 *
 * Aman dijalankan berulang: upsert berdasarkan kode; tidak menghapus apa pun.
 * Baris yang dulu diinput manual (tanpa kode) dengan nama & induk sama
 * diberi kode, bukan diduplikasi.
 *
 *   php artisan db:seed --class=WilayahSeeder
 */
class WilayahSeeder extends Seeder
{
    /** Kelompok pulau per 2 digit kode provinsi. */
    private const PULAU = [
        'Sumatera'             => ['11', '12', '13', '14', '15', '16', '17', '18', '19', '21'],
        'Jawa'                 => ['31', '32', '33', '34', '35', '36'],
        'Bali & Nusa Tenggara' => ['51', '52', '53'],
        'Kalimantan'           => ['61', '62', '63', '64', '65'],
        'Sulawesi'             => ['71', '72', '73', '74', '75', '76'],
        'Maluku'               => ['81', '82'],
        'Papua'                => ['91', '92', '93', '94', '95', '96', '97'],
    ];

    public function run(): void
    {
        $pulauIds = [];
        foreach (array_keys(self::PULAU) as $name) {
            DB::table('pulau')->insertOrIgnore(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $pulauIds[$name] = DB::table('pulau')->where('name', $name)->value('id');
        }

        $provToPulau = [];
        foreach (self::PULAU as $name => $codes) {
            foreach ($codes as $code) {
                $provToPulau[$code] = $pulauIds[$name];
            }
        }

        $parents = $provToPulau;
        foreach (['provinsi' => 'pulau_id', 'kota' => 'provinsi_id', 'kecamatan' => 'kota_id', 'desa' => 'kecamatan_id'] as $table => $parentCol) {
            $parents = $this->seedLevel($table, $parentCol, $parents);
            $this->command?->info(sprintf('%-10s %6d baris', $table, count($parents)));
        }
    }

    /**
     * @param  array<string,int>  $parentIds  kode induk => id induk
     * @return array<string,int>              kode => id untuk jenjang ini
     */
    private function seedLevel(string $table, string $parentCol, array $parentIds): array
    {
        $rows = [];
        $fh = fopen(database_path("seeders/data/wilayah/{$table}.csv"), 'r');
        fgetcsv($fh); // header
        while (($line = fgetcsv($fh)) !== false) {
            [$kode, $nama] = $line;
            $parentKode = $table === 'provinsi' ? $kode : substr($kode, 0, strrpos($kode, '.'));
            if (! isset($parentIds[$parentKode])) {
                throw new \RuntimeException("Induk {$parentKode} untuk {$table} {$kode} tidak ditemukan.");
            }
            $rows[] = ['kode' => $kode, 'name' => $nama, $parentCol => $parentIds[$parentKode]];
        }
        fclose($fh);

        // Beri kode ke baris manual lama (nama + induk sama) agar tidak terduplikasi.
        $legacy = DB::table($table)->whereNull('kode')->get(['id', 'name', $parentCol]);
        if ($legacy->isNotEmpty()) {
            $byKey = $legacy->keyBy(fn ($r) => $r->{$parentCol} . '|' . mb_strtolower($r->name));
            foreach ($rows as $row) {
                $hit = $byKey->pull($row[$parentCol] . '|' . mb_strtolower($row['name']));
                if ($hit) {
                    DB::table($table)->where('id', $hit->id)->update(['kode' => $row['kode']]);
                }
            }
        }

        $now = now();
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->upsert(
                array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $chunk),
                ['kode'],
                ['name', $parentCol, 'updated_at']
            );
        }

        return DB::table($table)->whereNotNull('kode')->pluck('id', 'kode')->all();
    }
}
