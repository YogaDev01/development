<?php

namespace Tests\Feature;

use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Aksi yang tersedia per status Forecast.
 *
 * Aturannya satu: Forecast::isLocked(), dan hanya CLOSED yang terkunci.
 * Sebelumnya ACTIVE ikut terkunci — satu klik Activate mengunci isi forecast
 * selamanya karena tidak ada jalur kembali ke DRAFT.
 *
 * Yang dijaga di sini kesetaraan tampilan dan backend: setiap tombol yang
 * muncul di kolom Action harus benar-benar diterima controller, dan setiap
 * tombol yang hilang harus benar-benar ditolak. Keduanya diuji bersama supaya
 * tidak ada tombol hiasan yang mengantar pengguna ke pesan error.
 */
class ForecastActionAccessTest extends TestCase
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

    private function isiForecast(Forecast $forecast, string $catatan): array
    {
        return [
            'notes'      => $catatan,
            'date_start' => $forecast->date_start->toDateString(),
            'date_end'   => $forecast->date_end->toDateString(),
            'details'    => [[
                'description'    => 'Sewa kantor',
                'project_id'     => null,
                'type'           => 'OUT',
                'source_fund_id' => null,
                'amount'         => '10000000',
            ]],
        ];
    }

    /* ---------------------------------------------------------------- ACTIVE */

    public function test_active_bisa_dibuka_halaman_editnya(): void
    {
        $forecast = $this->forecastBerstatus('ACTIVE');

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/edit')
            ->assertOk();
    }

    public function test_active_bisa_diupdate(): void
    {
        $forecast = $this->forecastBerstatus('ACTIVE');

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/forecast/' . $forecast->id, $this->isiForecast($forecast, 'Catatan Baru'))
            ->assertRedirect('/forecast');

        $this->assertSame('CATATAN BARU', $forecast->refresh()->notes);
        // Statusnya tidak ikut mundur: mengedit isi bukan membatalkan aktivasi.
        $this->assertSame('ACTIVE', $forecast->status);
    }

    public function test_active_bisa_dihapus(): void
    {
        $forecast = $this->forecastBerstatus('ACTIVE');

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/forecast/' . $forecast->id)
            ->assertRedirect('/forecast');

        $this->assertNull(Forecast::find($forecast->id));
    }

    public function test_kolom_action_active_menawarkan_view_dan_hapus(): void
    {
        $forecast = $this->forecastBerstatus('ACTIVE');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast')->assertOk()->getContent();

        // Kolom Action kini hanya pintu masuk: View membuka modal detail,
        // Hapus tetap di baris. Edit dan seluruh alur pindah ke dalam modal.
        $this->assertStringContainsString('data-id="' . $forecast->id . '"', $html);
        $this->assertStringContainsString('Hapus', $html);

        // Izin Edit-nya dinyatakan lewat payload modal, bukan tautan di baris.
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/detail-json')
            ->assertOk()
            ->assertJson(['can_edit' => true, 'can_delete' => true]);
    }

    /* ---------------------------------------------------------------- CLOSED */

    public function test_closed_tetap_terkunci_dari_edit(): void
    {
        $forecast = $this->forecastBerstatus('CLOSED');

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/edit')
            ->assertRedirect('/forecast/' . $forecast->id);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/forecast/' . $forecast->id, $this->isiForecast($forecast, 'Coba Ubah'))
            ->assertRedirect();

        $this->assertSame('Forecast Uji', $forecast->refresh()->notes);
    }

    public function test_closed_tetap_terkunci_dari_hapus(): void
    {
        $forecast = $this->forecastBerstatus('CLOSED');

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/forecast/' . $forecast->id)
            ->assertRedirect();

        $this->assertNotNull(Forecast::find($forecast->id));
    }

    public function test_kolom_action_closed_hanya_view_tanpa_hapus(): void
    {
        $forecast = $this->forecastBerstatus('CLOSED');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast')->assertOk()->getContent();

        // CLOSED tetap bisa dilihat, tapi tidak bisa dihapus — tombolnya
        // memang tidak dirender, sejalan dengan destroy() yang menolaknya.
        $this->assertStringContainsString('data-id="' . $forecast->id . '"', $html);
        $this->assertStringNotContainsString('/forecast/' . $forecast->id . '/edit', $html);
        $this->assertStringNotContainsString('fas fa-trash', $html);

        // Modalnya pun tidak menawarkan Edit maupun alur apa pun.
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/detail-json')
            ->assertOk()
            ->assertJson([
                'can_edit'     => false,
                'can_delete'   => false,
                'can_activate' => false,
                'can_close'    => false,
            ]);
    }

    /* ----------------------------------------------------------------- DRAFT */

    public function test_draft_masih_bisa_diedit_dan_dihapus(): void
    {
        $forecast = $this->forecastBerstatus('DRAFT');

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/edit')
            ->assertOk();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast')->assertOk()->getContent();

        $this->assertStringContainsString('data-id="' . $forecast->id . '"', $html);
        $this->assertStringContainsString('Hapus', $html);

        // DRAFT adalah satu-satunya status yang boleh diaktifkan.
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/forecast/' . $forecast->id . '/detail-json')
            ->assertOk()
            ->assertJson([
                'can_edit'     => true,
                'can_activate' => true,
                'can_close'    => false,
            ]);
    }
}

