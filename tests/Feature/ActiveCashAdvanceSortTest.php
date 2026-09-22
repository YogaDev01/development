<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\BudgetRequest;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Kartu "Active Cash Advance" (Summary > Summary Budget).
 *
 * Tabelnya pohon tiga tingkat Manager > Beneficiary > Cost Request yang
 * barisnya dilipat, jadi urutannya TIDAK bisa diserahkan ke penyortir
 * sisi-klien: penyortir itu memperlakukan <tr> sebagai daftar datar, sehingga
 * baris anak lepas dari induknya dan subtotal per manager berhenti bermakna.
 * Urutan dihitung di server, per tingkat, di dalam induknya masing-masing.
 *
 * Yang dikunci uji ini:
 *   - keempat pilihan urutan benar-benar mengubah susunan, di lapis manager
 *     MAUPUN lapis penerima dana;
 *   - urutan abjad tidak peka huruf besar-kecil (nickname ditulis campur:
 *     "Abe", "DEVASYA" — urutan ASCII akan menaruh semua huruf besar lebih
 *     dulu, dan itu bukan A-Z yang dimaksud siapa pun);
 *   - nilai ?aca_sort= yang asing jatuh ke bawaan, bukan merusak tabel;
 *   - tautan ke pengajuan hanya muncul untuk role yang memang boleh membuka
 *     halaman Cost Request.
 */
class ActiveCashAdvanceSortTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /**
     * Satu manager, tiga penerima dana yang urutan abjad dan urutan nilainya
     * SENGAJA berlawanan — kalau keduanya searah, uji ini akan lulus walau
     * penyortirannya tidak bekerja sama sekali.
     *
     * "anwar" kecil dan "ZULFA" besar menguji kepekaan huruf sekaligus.
     *
     * Mengembalikan PROJECT-nya, bukan manager: role bertingkat (Head) dibatasi
     * ke project di Access Group-nya, jadi uji yang memakai role seperti itu
     * perlu project ini untuk diberikan aksesnya.
     */
    private function siapkanCa(): Project
    {
        $manager = $this->userWithRole('manager');
        $project = $this->makeProject(['manager_id' => $manager->id]);
        $plan    = $this->makeBudgetPlan($project);
        $tipe    = $this->makeRequestType('Cash Advance');

        $nilai = ['anwar' => 5_000_000, 'Midah' => 30_000_000, 'ZULFA' => 90_000_000];

        foreach ($nilai as $nickname => $jumlah) {
            $penerima = Beneficiary::create([
                'nickname'      => $nickname,
                'complete_name' => $nickname . ' Uji',
                'status'        => 'ACTIVE',
            ]);

            $this->makeCostRequest([
                'budget_plan'    => $plan,
                'applicant_id'   => $manager->id,
                'beneficiary_id' => $penerima->id,
                'type_id'        => $tipe->id,
                'amount'         => $jumlah,
                'status'         => 'PAID',
            ]);
        }

        return $project;
    }

    /**
     * Satu manager dengan SATU Cash Advance senilai $nilai.
     *
     * Dipakai uji yang memeriksa urutan blok manager, jadi username-nya
     * ditentukan sendiri — nama bawaan userWithRole() memakai penghitung statis
     * yang nilainya ikut berubah tergantung uji mana yang jalan lebih dulu.
     */
    private function siapkanCaUntukManager(string $username, int $nilai): void
    {
        $manager = $this->userWithRole('manager', [
            'username' => $username,
            'email'    => $username . '@example.test',
        ]);

        $plan = $this->makeBudgetPlan($this->makeProject(['manager_id' => $manager->id]));

        $this->makeCostRequest([
            'budget_plan'    => $plan,
            'applicant_id'   => $manager->id,
            'beneficiary_id' => $this->makeBeneficiary()->id,
            'type_id'        => $this->makeRequestType('Cash Advance')->id,
            'amount'         => $nilai,
            'status'         => 'PAID',
        ]);
    }

    /** Nickname penerima dana sesuai urutan tampilnya di kartu. */
    private function urutanPenerima(string $sort): array
    {
        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/finance-summary?tab=budget&aca_sort=' . $sort)
            ->assertOk()
            ->getContent();

        preg_match_all('/class="aca-lv2">\s*<i[^>]*><\/i>\s*([^<]+)/', $html, $cocok);

        return array_map('trim', $cocok[1] ?? []);
    }

    /** Nama manager sesuai urutan bloknya di kartu. */
    private function urutanManager(string $sort): array
    {
        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/finance-summary?tab=budget&aca_sort=' . $sort)
            ->assertOk()
            ->getContent();

        preg_match_all('/class="aca-group-row"[^>]*data-sort-name="([^"]+)"/', $html, $cocok);

        return $cocok[1] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | URUTAN
    |--------------------------------------------------------------------------
    */

    public function test_urutan_nilai_besar_ke_kecil(): void
    {
        $this->siapkanCa();

        $this->assertSame(['ZULFA', 'Midah', 'anwar'], $this->urutanPenerima('value_desc'));
    }

    public function test_urutan_nilai_kecil_ke_besar(): void
    {
        $this->siapkanCa();

        $this->assertSame(['anwar', 'Midah', 'ZULFA'], $this->urutanPenerima('value_asc'));
    }

    public function test_urutan_abjad_a_ke_z_mengabaikan_besar_kecil_huruf(): void
    {
        $this->siapkanCa();

        // Urutan ASCII akan memberi ['Midah', 'ZULFA', 'anwar'] — huruf besar
        // dulu semua. Yang benar adalah urutan abjad yang dibaca manusia.
        $this->assertSame(['anwar', 'Midah', 'ZULFA'], $this->urutanPenerima('name_asc'));
    }

    public function test_urutan_abjad_z_ke_a(): void
    {
        $this->siapkanCa();

        $this->assertSame(['ZULFA', 'Midah', 'anwar'], $this->urutanPenerima('name_desc'));
    }

    public function test_urutan_nilai_dan_abjad_memang_berbeda(): void
    {
        $this->siapkanCa();

        // Penjaga terhadap uji yang lulus semu: kalau kedua urutan ini sama,
        // data contohnya yang salah, bukan kodenya yang benar.
        $this->assertNotSame(
            $this->urutanPenerima('value_desc'),
            $this->urutanPenerima('name_asc')
        );
    }

    public function test_sort_asing_jatuh_ke_bawaan(): void
    {
        $this->siapkanCa();

        $this->assertSame(
            $this->urutanPenerima('value_desc'),
            $this->urutanPenerima('bukan-kunci-yang-sah')
        );
    }

    /**
     * Blok manager TIDAK ikut berpindah saat urutan diubah.
     *
     * Yang dibandingkan orang di kartu ini adalah penerima dananya; manager
     * cuma pengelompokan. Kalau bloknya ikut diurutkan, manager yang sedang
     * dibuka melompat ke tempat lain setiap kali tombol urut ditekan.
     */
    public function test_urutan_manager_tidak_ikut_berubah(): void
    {
        // Dua manager dengan nama dan nilai yang SENGAJA berlawanan: 'alfa'
        // paling awal abjadnya tapi paling kecil nilainya. Kalau lapis manager
        // masih ikut diurutkan, name_asc akan menaikkan ALFA ke atas.
        $this->siapkanCaUntukManager('alfa', 10_000_000);
        $this->siapkanCaUntukManager('zulu', 90_000_000);

        $harapan = ['ZULU', 'ALFA']; // selalu nilai terbesar dulu

        foreach (['value_desc', 'value_asc', 'name_asc', 'name_desc'] as $sort) {
            $this->assertSame(
                $harapan,
                $this->urutanManager($sort),
                "Urutan manager berubah pada sort '{$sort}'."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL PENGAJUAN
    |--------------------------------------------------------------------------
    */

    /**
     * Kode CR membuka detail DI HALAMAN INI, bukan melempar ke menu Cost
     * Request. Pemicunya kelas .btn-detail-cr + data-id, yang sudah dikenali
     * partial budget-request._detail_modal — partial yang sama dipakai Project
     * Monitoring, jadi markup detailnya tidak disalin ke halaman ini.
     */
    public function test_kode_cr_membuka_modal_detail_di_halaman_yang_sama(): void
    {
        $this->siapkanCa();

        $cr = BudgetRequest::where('status', 'PAID')->firstOrFail();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/finance-summary?tab=budget')
            ->assertOk()
            // Pemicu modal pada kode CR-nya.
            ->assertSee('btn-detail-cr', false)
            ->assertSee('data-id="' . $cr->id . '"', false)
            // Modal-nya benar-benar ikut dirender di halaman ini.
            ->assertSee('id="mdlDetailCR"', false)
            ->getContent();

        // Tidak ada lagi tautan yang memindahkan halaman ke menu Cost Request.
        $this->assertStringNotContainsString('budget-request?open=', $html);
    }

    /**
     * Urutan diubah lewat judul kolom, dan tabelnya tidak punya kolom Action —
     * kode CR-nya sendiri yang jadi tautan.
     */
    public function test_judul_kolom_jadi_tombol_urut_tanpa_kolom_action(): void
    {
        $this->siapkanCa();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/finance-summary?tab=budget')
            ->assertOk()
            ->assertSee('data-aca-sort="name"', false)
            ->assertSee('data-aca-sort="value"', false)
            ->getContent();

        // Dropdown "Urutkan" yang lama sudah tidak ada.
        $this->assertStringNotContainsString('id="acaSort"', $html);
        $this->assertStringNotContainsString('aca-cr-action', $html);
    }

    /**
     * Penyortiran terjadi di layar, jadi setiap baris HARUS membawa kunci
     * urutnya sendiri.
     *
     * Nilainya angka mentah, bukan "70.600.000": kalau yang tertulis di
     * data-sort-value adalah teks berformat rupiah, JS akan membandingkannya
     * sebagai string dan 9.150.000 menjadi "lebih besar" dari 70.600.000.
     */
    public function test_setiap_baris_membawa_kunci_urut_berupa_angka_mentah(): void
    {
        $this->siapkanCa();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/finance-summary?tab=budget')
            ->assertOk()
            // 90.000.000 milik ZULFA, ditulis tanpa pemisah ribuan.
            ->assertSee('data-sort-value="90000000"', false)
            ->assertSee('data-sort-name="ZULFA"', false)
            ->getContent();

        $this->assertStringNotContainsString('data-sort-value="90.000.000"', $html);
    }

    /**
     * Detailnya bisa dibuka TANPA hak akses menu Cost Request.
     *
     * Itu inti perubahannya: kartu ini terbuka untuk semua role, dan dulu kode
     * CR-nya menautkan ke halaman Cost Request yang digerbang
     * 'menu:finance-cost-request' — jadi role seperti Treasury melihat tautan
     * yang pasti 403. Sekarang detailnya muncul di halaman ini, lewat endpoint
     * detail-json yang menjaga dirinya sendiri per dokumen.
     */
    public function test_role_tanpa_menu_cost_request_tetap_bisa_membuka_detail(): void
    {
        $project = $this->siapkanCa();

        // Halaman Summary punya gerbangnya sendiri ('menu:finance-summary').
        // Yang diuji di sini bukan itu, jadi aksesnya dibuka lebih dulu —
        // tanpa ini uji berhenti di 403 sebelum sempat melihat tabelnya.
        $head = $this->userWithRole('head');
        $this->giveMenuAccess($head->role, 'finance-summary');
        $this->giveProjectAccess($head, $project);

        $cr = BudgetRequest::where('status', 'PAID')->firstOrFail();

        $this->actingAs($head)
            ->get('/finance-summary?tab=budget')
            ->assertOk()
            ->assertSee('btn-detail-cr', false)
            ->assertSee('id="mdlDetailCR"', false);

        // Dan endpoint yang mengisi modal itu memang bisa dibuka olehnya.
        $this->actingAs($head)
            ->get('/budget-request/' . $cr->id . '/detail-json')
            ->assertOk()
            ->assertJsonPath('code', $cr->code);
    }
}
