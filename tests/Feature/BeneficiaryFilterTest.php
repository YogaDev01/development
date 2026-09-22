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
            ->get('/beneficiaries?category=EXTERNAL')->assertOk();

        $this->assertSame(2, $res->viewData('beneficiaries')->count());

        // Inti dari penyaringan di server: kartunya ikut, bukan tetap 3.
        $this->assertSame(2, $res->viewData('summary')['total']);
    }

    public function test_filter_status_menyaring_baris_dan_kartu_sekaligus(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?status=INACTIVE')->assertOk();

        $this->assertSame(1, $res->viewData('beneficiaries')->count());
        $this->assertSame(1, $res->viewData('summary')['total']);
    }

    public function test_kategori_dan_status_bisa_dipakai_bersamaan(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?category=EXTERNAL&status=ACTIVE')->assertOk();

        $this->assertSame(1, $res->viewData('beneficiaries')->count());
        $this->assertSame('DUA', $res->viewData('beneficiaries')->first()->nickname);
    }

    public function test_tanpa_filter_menampilkan_semuanya(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk();

        $this->assertSame(3, $res->viewData('beneficiaries')->count());
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

    public function test_kartu_ringkasan_bisa_dihitung_ulang_di_peramban(): void
    {
        $this->isiData();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        /*
        | Penjaga di tingkat sumber. Penyaringan terjadi di peramban supaya
        | terasa seketika, jadi kartu yang dirender server harus ikut dihitung
        | ulang — tanpa itu tabel menyisakan 1 baris sementara kartunya tetap
        | menulis jumlah penuh. Perilakunya hanya bisa diuji lewat peramban;
        | yang bisa dijaga di sini adalah kaitannya tidak putus.
        */
        $this->assertStringContainsString('id="benKpi"', $html);
        $this->assertStringContainsString("table.on('draw', hitungUlangKartu)", $html);

        // Baris tabel membawa atribut yang dipakai perhitungan itu.
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
