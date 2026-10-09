<?php

namespace Tests\Feature;

use App\Models\Kota;
use App\Models\Provinsi;
use App\Models\Pulau;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WilayahSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_levels_idempotently_and_keeps_manual_rows(): void
    {
        // Baris manual lama tanpa kode harus diberi kode, bukan diduplikasi.
        $jawa  = Pulau::create(['name' => 'Jawa']);
        $jabar = Provinsi::create(['pulau_id' => $jawa->id, 'name' => 'Jawa Barat']);

        $this->seed(WilayahSeeder::class);
        $this->seed(WilayahSeeder::class);

        $this->assertSame(7, DB::table('pulau')->count());
        $this->assertSame(38, DB::table('provinsi')->count());
        $this->assertSame(514, DB::table('kota')->count());
        $this->assertSame(7285, DB::table('kecamatan')->count());
        $this->assertSame(83762, DB::table('desa')->count());

        $this->assertSame('32', $jabar->fresh()->kode);

        $papuaPegunungan = Provinsi::where('kode', '95')->firstOrFail();
        $this->assertSame('Papua', $papuaPegunungan->pulau->name);
        $this->assertSame('Bali & Nusa Tenggara', Provinsi::where('kode', '53')->firstOrFail()->pulau->name);
        $this->assertSame('Sumatera', Provinsi::where('kode', '21')->firstOrFail()->pulau->name);

        $bandung = Kota::where('kode', '32.73')->firstOrFail();
        $this->assertSame($jabar->id, $bandung->provinsi_id);

        // Desa & kelurahan bernama sama dalam satu kecamatan tetap tersimpan keduanya.
        $this->assertSame(2, DB::table('desa')->whereIn('kode', ['12.01.10.2018', '12.01.10.1001'])->count());
    }
}
