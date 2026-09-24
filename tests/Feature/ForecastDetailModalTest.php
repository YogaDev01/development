<?php

namespace Tests\Feature;

use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Detail Forecast sebagai modal, bukan halaman tersendiri.
 *
 * Sebelumnya membuka satu forecast berarti meninggalkan daftarnya, dan
 * halaman itu punya tombol "Kembali" sendiri — bentuk yang tidak sama dengan
 * modal detail modul lain, yang ditutup lewat tanda X di kepala panel gelap.
 *
 * Alamat lama /forecast/{id} tetap hidup: ia diarahkan ke daftar dengan modal
 * yang sudah terbuka, supaya bookmark dan redirect edit() saat forecast
 * terkunci tidak berakhir di halaman yang tidak ada.
 */
class ForecastDetailModalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function forecastBerstatus(string $status): Forecast
    {
        $forecast = Forecast::create([
            'notes'        => 'Forecast Uji',
            'date_start'   => now()->startOfMonth()->toDateString(),
            'date_end'     => now()->endOfMonth()->toDateString(),
            'status'       => $status,
            'activated_at' => $status === 'DRAFT' ? null : now(),
            'created_by'   => $this->userWithRole('finance')->id,
        ]);

        $forecast->details()->create([
            'description' => 'Sewa kantor',
            'type'        => 'OUT',
            'amount'      => 10_000_000,
        ]);

        return $forecast->refresh();
    }

    /* ------------------------------------------------------- ALAMAT LAMA */

    public function test_alamat_detail_lama_diarahkan_ke_modal(): void
    {
        $forecast = $this->forecastBerstatus('ACTIVE');

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id)
            ->assertRedirect('/forecast?open=' . $forecast->id);
    }

    public function test_forecast_terkunci_mendarat_di_detail_saat_edit_ditolak(): void
    {
        $forecast = $this->forecastBerstatus('CLOSED');

        // edit() menolak lalu melempar ke alamat detail, yang kini meneruskan
        // ke daftar dengan modalnya terbuka — bukan ke halaman kosong.
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/edit')
            ->assertRedirect('/forecast/' . $forecast->id);

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id)
            ->assertRedirect('/forecast?open=' . $forecast->id);
    }

    /* ------------------------------------------------------ ISI MODALNYA */

    public function test_payload_modal_memuat_ringkasan_dan_rincian(): void
    {
        $forecast = $this->forecastBerstatus('ACTIVE');

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/detail-json')->assertOk();

        $res->assertJson([
            'code'   => 'FC-' . str_pad((string) $forecast->id, 4, '0', STR_PAD_LEFT),
            'label'  => 'Forecast Uji',
            'status' => 'ACTIVE',
        ]);

        // Rencana pengeluaran bernilai bersih negatif — angkanya ikut bertanda.
        $this->assertSame('-10.000.000', $res->json('forecast_amount'));

        // Variance bertanda mengikuti ARAH barisnya: seluruh angka pada baris
        // KELUAR bernilai negatif, termasuk selisihnya.
        $this->assertSame('-10.000.000', $res->json('variance'));
        $this->assertTrue($res->json('variance_negative'));
        // Rincian dikirim TERKELOMPOK per rekening — satu tabel per Source Fund
        // di modalnya.
        $this->assertSame(1, $res->json('detail_count'));
        $this->assertCount(1, $res->json('groups'));
        $this->assertCount(1, $res->json('groups.0.rows'));
        $this->assertSame('Sewa kantor', $res->json('groups.0.rows.0.description'));

        // Baris KELUAR tampil bertanda minus, termasuk rencananya.
        $this->assertSame('-10.000.000', $res->json('groups.0.rows.0.amount_formatted'));
    }

    public function test_alur_yang_boleh_dijalankan_ikut_dikirim(): void
    {
        $draft = $this->forecastBerstatus('DRAFT');

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $draft->id . '/detail-json')
            ->assertOk()
            ->assertJson([
                'can_activate'      => true,
                'can_close'         => false,
                'can_update_actual' => false,
            ]);

        $aktif = $this->forecastBerstatus('ACTIVE');

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $aktif->id . '/detail-json')
            ->assertOk()
            ->assertJson([
                'can_activate'      => false,
                'can_close'         => true,
                'can_update_actual' => true,
            ]);
    }

    public function test_forecast_tanpa_detail_tidak_bisa_diaktifkan(): void
    {
        $forecast = Forecast::create([
            'notes'      => 'Kosong',
            'date_start' => now()->toDateString(),
            'date_end'   => now()->toDateString(),
            'status'     => 'DRAFT',
            'created_by' => $this->userWithRole('finance')->id,
        ]);

        // Sejalan dengan activate() yang menolak forecast tanpa detail —
        // tombolnya memang tidak boleh muncul.
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/detail-json')
            ->assertOk()
            ->assertJson(['can_activate' => false]);
    }

    /* ------------------------------------------------- KERANGKA MODALNYA */

    public function test_halaman_daftar_memuat_kerangka_modal(): void
    {
        $this->forecastBerstatus('ACTIVE');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast')->assertOk()->getContent();

        // Bentuknya sama dengan modal detail modul lain: panel gelap di kiri,
        // ditutup lewat tanda X di kepalanya — bukan tombol "Kembali".
        $this->assertStringContainsString('id="modalForecastDetail"', $html);
        $this->assertStringContainsString('fc-sidebar dm-side', $html);
        $this->assertStringContainsString('class="close dm-close"', $html);
        $this->assertStringNotContainsString('Kembali', $html);

        // Alur dan Edit berada di dalam modal.
        $this->assertStringContainsString('id="fcBtnEdit"', $html);
        $this->assertStringContainsString('id="fcBtnActivate"', $html);
        $this->assertStringContainsString('id="fcBtnClose"', $html);
    }
}
