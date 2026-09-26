<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Command palette (Ctrl+K).
 *
 * KENAPA UJI INI ADA
 *
 * Saat GlobalSearchController dipindahkan dari
 * `app(BudgetPlanController::class)->scopeVisibleTo(...)` ke trait, muncul
 * tabrakan nama: Concerns\BuildsCrWorklist SUDAH punya overheadPrivileged(),
 * dan trait baru mendefinisikan nama yang sama. PHP menolak kelasnya sama
 * sekali — setiap permintaan ke /global-search akan mati dengan fatal error.
 *
 * Seluruh 156 uji yang ada tetap LOLOS, karena tak satu pun menyentuh endpoint
 * ini. Yang menemukannya adalah `php artisan route:list` yang gagal boot. Uji
 * di bawah menutup celah itu: kalau kelasnya tidak bisa dibangun, atau
 * gerbang/penyaringannya berubah, uji ini yang gagal lebih dulu.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase, MakesTesysData;

    public function test_menolak_tamu(): void
    {
        $this->get('/global-search?q=abc')->assertRedirect('/login');
    }

    /**
     * Kelasnya harus bisa dibangun dan menjawab JSON. Ini uji paling penting di
     * berkas ini: tabrakan trait membuatnya fatal error, bukan 500 biasa.
     */
    public function test_menjawab_json_untuk_pengguna_login(): void
    {
        $this->actingAs($this->userWithRole('superadmin'));

        $this->get('/global-search?q=abc')
            ->assertOk()
            ->assertJsonStructure(['groups', 'query']);
    }

    /**
     * Kata kunci di bawah MIN_HURUF (3) tidak menyentuh basis data sama sekali
     * dan mengembalikan daftar kosong — batas itu yang menjaga endpoint ini
     * dari pemindaian tabel penuh untuk kata kunci yang tidak berguna.
     */
    public function test_kata_kunci_terlalu_pendek_tidak_mencari(): void
    {
        $this->actingAs($this->userWithRole('superadmin'));

        $this->get('/global-search?q=ab')
            ->assertOk()
            ->assertJson(['groups' => [], 'query' => 'ab']);
    }

    /**
     * Superadmin melihat Cost Request yang ada; pencarian kode memakai pola
     * AWALAN supaya indeks 'code' terpakai, jadi kode harus tetap ditemukan
     * dari depannya.
     */
    public function test_menemukan_cost_request_berdasarkan_awalan_kode(): void
    {
        $cr = $this->makeCostRequest();

        $this->actingAs($this->userWithRole('superadmin'));

        $respons = $this->get('/global-search?q=' . urlencode(substr($cr->code, 0, 5)))
            ->assertOk();

        $kode = collect($respons->json('groups'))
            ->flatMap(fn ($g) => $g['items'] ?? [])
            ->pluck('title');

        $this->assertTrue(
            $kode->contains($cr->code),
            'Cost Request ' . $cr->code . ' tidak ditemukan lewat awalan kodenya.'
        );
    }

    /**
     * Karakter wildcard SQL dari pengguna tidak boleh berfungsi sebagai
     * wildcard: mengetik "%" saja akan mencocokkan seluruh tabel.
     */
    public function test_wildcard_dari_pengguna_tidak_mencocokkan_semuanya(): void
    {
        $this->makeCostRequest();

        $this->actingAs($this->userWithRole('superadmin'));

        $respons = $this->get('/global-search?q=%25%25%25')->assertOk();

        $jumlah = collect($respons->json('groups'))
            ->flatMap(fn ($g) => $g['items'] ?? [])
            ->count();

        $this->assertSame(0, $jumlah, 'Tanda persen diperlakukan sebagai wildcard.');
    }
}
