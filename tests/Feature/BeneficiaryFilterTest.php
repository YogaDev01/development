<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Beneficiary Master — filter Kategori & Status.
 *
 * Dulu keduanya punya DUA kendali yang mengerjakan hal yang sama: tombol
 * saringan sisi-peramban di toolbar, dan <select> server-side di sebelahnya.
 * Sekarang tinggal satu tombol per hal, dan penyaringannya tetap di server —
 * kartu ringkasan di atas tabel dihitung dari query yang sama, jadi menyaring
 * di sisi peramban akan membuat tabel menampilkan satu baris sementara
 * kartunya tetap menulis jumlah penuh.
 */
class BeneficiaryFilterTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function beneficiary(string $nickname, string $kategori, string $status): Beneficiary
    {
        return Beneficiary::create([
            'nickname'      => $nickname,
            'complete_name' => $nickname . ' Lengkap',
            'category'      => $kategori,
            'status'        => $status,
        ]);
    }

    private function isiData(): void
    {
        $this->beneficiary('SATU', 'INTERNAL', 'ACTIVE');
        $this->beneficiary('DUA', 'EXTERNAL', 'ACTIVE');
        $this->beneficiary('TIGA', 'EXTERNAL', 'INACTIVE');
    }

    /* --------------------------------------------------------- PENYARINGAN */

    public function test_filter_kategori_menyaring_baris_dan_kartu_sekaligus(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?tab=db&category=EXTERNAL')->assertOk();

        $this->assertSame(2, $res->viewData('beneficiaries')->count());

        // Inti dari penyaringan di server: kartunya ikut, bukan tetap 3.
        $this->assertSame(2, $res->viewData('summary')['total']);
    }

    public function test_filter_status_menyaring_baris_dan_kartu_sekaligus(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?tab=db&status=INACTIVE')->assertOk();

        $this->assertSame(1, $res->viewData('beneficiaries')->count());
        $this->assertSame(1, $res->viewData('summary')['total']);
    }

    public function test_kategori_dan_status_bisa_dipakai_bersamaan(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?tab=db&category=EXTERNAL&status=ACTIVE')->assertOk();

        $this->assertSame(1, $res->viewData('beneficiaries')->count());
        $this->assertSame('DUA', $res->viewData('beneficiaries')->first()->nickname);
    }

    public function test_tanpa_filter_menampilkan_semuanya(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?tab=db')->assertOk();

        $this->assertSame(3, $res->viewData('beneficiaries')->count());
    }

    /* ------------------------------------------------------ PEMUATAN LAZY */

    public function test_halaman_awal_hanya_memuat_baris_internal(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk();

        // Tab bawaan = Internal; baris External tidak dikirim sama sekali.
        $this->assertSame(['SATU'], $res->viewData('beneficiaries')->pluck('nickname')->all());
        $res->assertDontSee('data-category="EXTERNAL"', false);

        // Angka badge tab tetap penuh (dihitung agregat, bukan dari baris).
        $this->assertSame(
            ['internal' => 1, 'outsider' => 2, 'db' => 3],
            $res->viewData('tabCounts')
        );
    }

    public function test_tab_external_hanya_memuat_baris_bukan_internal(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?tab=outsider')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['DUA', 'TIGA'],
            $res->viewData('beneficiaries')->pluck('nickname')->all()
        );
    }

    public function test_endpoint_rows_mengembalikan_baris_satu_bagian(): void
    {
        $this->isiData();
        $admin = $this->userWithRole('superadmin');

        $outsider = $this->actingAs($admin)->get('/beneficiaries/rows/outsider')->assertOk()->getContent();
        $this->assertStringContainsString('DUA', $outsider);
        $this->assertStringContainsString('TIGA', $outsider);
        $this->assertStringNotContainsString('SATU', $outsider);

        $internal = $this->actingAs($admin)->get('/beneficiaries/rows/internal')->assertOk()->getContent();
        $this->assertStringContainsString('SATU', $internal);
        $this->assertStringNotContainsString('DUA', $internal);
    }

    public function test_endpoint_rows_menghormati_filter_dan_menolak_bagian_asing(): void
    {
        $this->isiData();
        $admin = $this->userWithRole('superadmin');

        $html = $this->actingAs($admin)->get('/beneficiaries/rows/outsider?status=INACTIVE')->getContent();
        $this->assertStringContainsString('TIGA', $html);
        $this->assertStringNotContainsString('DUA', $html);

        $this->actingAs($admin)->get('/beneficiaries/rows/semua')->assertNotFound();
    }

    public function test_endpoint_rows_butuh_akses_menu(): void
    {
        $this->actingAs($this->userWithRole('treasury'))
            ->get('/beneficiaries/rows/internal')
            ->assertForbidden();
    }

    /* ------------------------------------------------------ DAFTAR PILIHAN */

    public function test_billing_tetap_bisa_dipilih_walau_belum_ada_datanya(): void
    {
        // Hanya INTERNAL & EXTERNAL yang punya baris.
        $this->isiData();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        /*
        | Daftar pilihan Kategori & Status dinyatakan, bukan disimpulkan dari
        | baris yang ada. Saringan yang menyimpulkan punya lubang: BILLING
        | tidak pernah muncul selama belum ada satu pun barisnya — tepat saat
        | seseorang ingin memastikan memang belum ada.
        */
        $this->assertStringContainsString('"INTERNAL","EXTERNAL","BILLING"', $html);
        $this->assertStringContainsString("options: ['ACTIVE', 'INACTIVE']", $html);
    }

    public function test_hanya_ada_satu_kendali_untuk_tiap_filter(): void
    {
        $this->isiData();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        // <select> server-side yang lama sudah tidak dirender lagi — dua
        // kendali untuk satu hal bisa saling bertentangan.
        $this->assertStringNotContainsString('js-auto-submit', $html);
        $this->assertStringNotContainsString('formFilterBen', $html);

        // Yang tersisa: ketiganya saringan ceklis sisi-peramban, masing-masing
        // didaftarkan sekali.
        $this->assertSame(1, substr_count($html, "{ key: 'category'"));
        $this->assertSame(1, substr_count($html, "{ key: 'bank'"));
        $this->assertSame(1, substr_count($html, "{ key: 'status'"));
    }

    public function test_halaman_tidak_menampilkan_kartu_ringkasan(): void
    {
        $this->isiData();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        // Kartu ringkasan sengaja dihapus; angka jumlah ada di badge tab.
        $this->assertStringNotContainsString('id="benKpi"', $html);
        $this->assertStringNotContainsString('hitungUlangKartu', $html);

        // Baris tabel tetap membawa atribut yang dipakai saringan kolom.
        $this->assertStringContainsString('data-category="INTERNAL"', $html);
        $this->assertStringContainsString('data-status="ACTIVE"', $html);
    }

    /* -------------------------------------------------------- KOLOM ACTION */

    public function test_hapus_tampil_langsung_tanpa_menu_titik_tiga(): void
    {
        $this->isiData();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        // Menunya dulu hanya berisi satu perintah, jadi ia cuma menambah satu
        // klik tanpa menyembunyikan apa pun.
        $this->assertStringNotContainsString('ts-action-more', $html);
        $this->assertStringContainsString('fas fa-trash', $html);
    }

    public function test_role_tanpa_wewenang_tetap_tidak_melihat_tombol_hapus(): void
    {
        $this->isiData();

        $manager = $this->userWithRole('manager');
        $this->giveMenuAccess($manager->role, 'beneficiaries');

        $html = $this->actingAs($manager)->get('/beneficiaries')->assertOk()->getContent();

        // Memunculkan Hapus dari balik menu tidak boleh ikut memunculkannya
        // untuk role yang memang tidak berhak.
        $this->assertStringNotContainsString('fas fa-trash', $html);
    }
}
