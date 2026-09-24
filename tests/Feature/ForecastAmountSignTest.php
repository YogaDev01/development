<?php

namespace Tests\Feature;

use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan error 22003 pada Forecast.
 *
 * Kolom forecasts.forecast_amount / actual_amount dibuat unsigned sewaktu
 * isinya masih SUM polos. Setelah detail punya arah IN/OUT, rumus headernya
 * jadi selisih (Masuk - Keluar) yang boleh negatif, tapi tipe kolomnya tidak
 * ikut berubah — setiap forecast yang memuat baris OUT ditolak MySQL dengan
 * "Out of range value for column 'forecast_amount'".
 *
 * Yang diuji di sini dua hal yang saling menutupi:
 *   1. kolomnya sudah bertanda, sehingga rencana pengeluaran bisa disimpan;
 *   2. totalnya dihitung setelah SELURUH baris masuk, bukan per baris —
 *      dulu urutan baris ikut menentukan apakah penyimpanan berhasil.
 */
class ForecastAmountSignTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /** Forecast route berada di balik middleware 'menu:forecast'; superadmin lolos. */
    private function finance()
    {
        return $this->userWithRole('superadmin');
    }

    private function simpanForecast(array $details)
    {
        return $this->actingAs($this->finance())->post('/forecast', [
            'notes'      => 'Forecast Uji',
            'date_start' => now()->startOfMonth()->toDateString(),
            'date_end'   => now()->endOfMonth()->toDateString(),
            'details'    => $details,
        ]);
    }

    private function baris(string $type, int $amount, string $description = 'Baris uji'): array
    {
        return [
            'description'    => $description,
            'project_id'     => null,
            'type'           => $type,
            'source_fund_id' => null,
            'amount'         => (string) $amount,
        ];
    }

    public function test_forecast_yang_isinya_pengeluaran_tersimpan_bernilai_negatif(): void
    {
        // Rencana overhead: tidak terikat PO dan isinya murni pengeluaran.
        // Inilah bentuk yang dulu selalu gagal disimpan.
        $this->simpanForecast([
            $this->baris('OUT', 10_000_000, 'Sewa kantor'),
        ])->assertRedirect('/forecast');

        $forecast = Forecast::firstOrFail();

        $this->assertSame(-10_000_000, (int) $forecast->forecast_amount);
        $this->assertSame(1, $forecast->details()->count());
    }

    public function test_baris_keluar_sebelum_masuk_tidak_menggagalkan_penyimpanan(): void
    {
        /*
        | Regresi yang sebenarnya terjadi di produksi: totalnya dihitung ulang
        | setiap satu baris tersimpan, jadi baris OUT yang masuk lebih dulu
        | membuat total sementaranya -10jt dan penyimpanan gagal — padahal
        | total akhir forecast ini positif.
        */
        $this->simpanForecast([
            $this->baris('OUT', 10_000_000, 'Pengeluaran duluan'),
            $this->baris('IN', 50_000_000, 'Penerimaan belakangan'),
        ])->assertRedirect('/forecast');

        $forecast = Forecast::firstOrFail();

        $this->assertSame(40_000_000, (int) $forecast->forecast_amount);
        $this->assertSame(2, $forecast->details()->count());
    }

    public function test_urutan_baris_tidak_mengubah_hasil(): void
    {
        $this->simpanForecast([
            $this->baris('IN', 50_000_000),
            $this->baris('OUT', 10_000_000),
        ])->assertRedirect('/forecast');

        $this->assertSame(40_000_000, (int) Forecast::firstOrFail()->forecast_amount);
    }

    public function test_realisasi_pengeluaran_tersimpan_bernilai_negatif(): void
    {
        // Jalur kedua yang memakai rumus bertanda yang sama: input Actual
        // Amount saat forecast sudah ACTIVE.
        $this->simpanForecast([
            $this->baris('OUT', 10_000_000, 'Sewa kantor'),
        ]);

        $forecast = Forecast::firstOrFail();

        $this->actingAs($this->finance())
            ->post('/forecast/' . $forecast->id . '/activate')
            ->assertRedirect();

        $detailId = $forecast->details()->firstOrFail()->id;

        $this->actingAs($this->finance())
            ->post('/forecast/' . $forecast->id . '/detail/' . $detailId . '/actual', [
                'amount' => '8.000.000',
                'notes'  => 'Pembayaran sewa bulan berjalan',
            ])
            ->assertRedirect();

        $forecast->refresh();

        $this->assertSame(-8_000_000, (int) $forecast->actual_amount);

        // Realisasi -8jt atas rencana -10jt = 80% tercapai. Syarat `> 0` yang
        // lama melaporkannya 0% untuk seluruh forecast pengeluaran.
        $this->assertSame(80.0, $forecast->achievement_pct);
    }

    public function test_forecast_tanpa_detail_bernilai_nol_bukan_error(): void
    {
        // Seluruh baris bernilai nol disaring syncDetails(), jadi tidak ada
        // detail yang tersisa — headernya harus tetap terhitung, bukan gagal.
        $this->simpanForecast([
            $this->baris('OUT', 0),
        ])->assertRedirect('/forecast');

        $forecast = Forecast::firstOrFail();

        $this->assertSame(0, (int) $forecast->forecast_amount);
        $this->assertSame(0, (int) $forecast->actual_amount);
        $this->assertSame(0.0, $forecast->achievement_pct);
    }
}
