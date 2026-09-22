<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Beneficiary;
use App\Models\MasterPic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Beneficiary Master — hasil pencarian yang diciutkan per PIC.
 *
 * Mencari nama PIC dulu memuntahkan seluruh beneficiary yang menunjuknya
 * (18 baris untuk satu orang), padahal yang dicari orangnya. Halaman kini
 * membawa ringkasan per PIC dan menampilkannya sebagai satu baris, dengan
 * anggotanya dibuka lewat View.
 *
 * Tombol Hapus pada baris PIC MELEPAS KAITAN, bukan menghapus PIC-nya: baris
 * Master PIC masih dipakai Customer dan Project, jadi layar Beneficiary tidak
 * boleh membuang data acuan milik modul lain.
 */
class BeneficiaryPicViewTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function pic(string $nama, string $jabatan = 'Manager', array $extra = []): MasterPic
    {
        return MasterPic::create(array_merge([
            'type'     => 'customer',
            'pic'      => $nama,
            'position' => $jabatan,
            'status'   => 'ACTIVE',
        ], $extra));
    }

    private function beneficiary(?MasterPic $pic, string $nickname): Beneficiary
    {
        return Beneficiary::create([
            'nickname'      => $nickname,
            'complete_name' => $nickname . ' Lengkap',
            'category'      => 'EXTERNAL',
            'pic_id'        => $pic?->id,
            'status'        => 'ACTIVE',
        ]);
    }

    /* ------------------------------------------------ RINGKASAN PER PIC */

    public function test_halaman_membawa_ringkasan_per_pic(): void
    {
        $pic = $this->pic('RANI FEBRIANDINI', 'Vendor Management Senior Manager');
        $this->beneficiary($pic, 'ZULBAIDHA');
        $this->beneficiary($pic, 'JUNAIDI');

        // Tanpa PIC — tidak boleh ikut masuk ke ringkasan.
        $this->beneficiary(null, 'TANPA PIC');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        $this->assertStringContainsString('RANI FEBRIANDINI', $html);
        $this->assertStringContainsString('Vendor Management Senior Manager', $html);
        // Jumlah anggotanya ikut terkirim supaya barisnya bisa menampilkan
        // angka. @json menulis JSON mentah di dalam <script>, bukan ter-escape.
        $this->assertStringContainsString('"total":2', $html);
    }

    public function test_beneficiary_tanpa_pic_tidak_masuk_ringkasan(): void
    {
        $this->beneficiary(null, 'SENDIRIAN');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        // Satu-satunya baris tidak punya PIC, jadi ringkasannya kosong.
        $this->assertStringContainsString('var PIC_GROUPS = [];', $html);
    }

    /* ------------------------------------------------- REKENING PIC SENDIRI */

    public function test_rekening_pic_ikut_terkirim_ke_panel_detail(): void
    {
        $bank = Bank::create(['name' => 'BRI', 'code' => '002']);

        $pic = $this->pic('RANI FEBRIANDINI', 'Vendor Management Senior Manager', [
            'bank_id'        => $bank->id,
            'account_number' => '0817604612345',
        ]);
        $this->beneficiary($pic, 'ZULBAIDHA');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        // Rekening PIC-nya SENDIRI — sebelumnya tidak ada kolomnya sama sekali
        // di master_pics, jadi panel detail tidak bisa menampilkannya.
        $this->assertStringContainsString('"account_number":"0817604612345"', $html);
        $this->assertStringContainsString('"bank":"BRI"', $html);
    }

    /* -------------------------------------------------------- HAPUS PIC */

    public function test_superadmin_bisa_menghapus_seluruh_data_pic(): void
    {
        $rani = $this->pic('RANI FEBRIANDINI');
        $lain = $this->pic('INTAN PUSPITA');

        $a = $this->beneficiary($rani, 'ZULBAIDHA');
        $b = $this->beneficiary($rani, 'JUNAIDI');
        $c = $this->beneficiary($lain, 'MILIK PIC LAIN');

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/beneficiaries/pic/' . $rani->id)
            ->assertRedirect('/beneficiaries');

        // Baris PIC-nya benar-benar hilang.
        $this->assertNull(MasterPic::find($rani->id));

        // Beneficiary-nya TIDAK ikut terhapus, hanya kehilangan PIC
        // (pic_id ber-nullOnDelete).
        $this->assertNotNull(Beneficiary::find($a->id));
        $this->assertNull($a->refresh()->pic_id);
        $this->assertNull($b->refresh()->pic_id);

        // PIC lain tidak tersentuh.
        $this->assertNotNull(MasterPic::find($lain->id));
        $this->assertSame($lain->id, $c->refresh()->pic_id);
    }

    public function test_pic_yang_dipakai_project_tidak_bisa_dihapus(): void
    {
        $rani = $this->pic('RANI FEBRIANDINI');
        $this->beneficiary($rani, 'ZULBAIDHA');

        // Penjagaan yang sama dengan MasterPicController::destroy() — dua pintu
        // ke penghapusan yang sama harus menolak hal yang sama.
        DB::table('project_pic_links')->insert([
            'project_id'    => $this->makeProject()->id,
            'master_pic_id' => $rani->id,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/beneficiaries/pic/' . $rani->id)
            ->assertRedirect();

        $this->assertNotNull(MasterPic::find($rani->id));
    }

    public function test_manager_tidak_boleh_menghapus_pic(): void
    {
        $rani = $this->pic('RANI FEBRIANDINI');
        $a    = $this->beneficiary($rani, 'ZULBAIDHA');

        // Manager boleh melihat & menambah beneficiary, tapi tidak mengubah
        // baris lama — aturan yang sama dengan Edit/Hapus (MANAGE_ROLES).
        $manager = $this->userWithRole('manager');
        $this->giveMenuAccess($manager->role, 'beneficiaries');

        $this->actingAs($manager)
            ->delete('/beneficiaries/pic/' . $rani->id)
            ->assertForbidden();

        $this->assertNotNull(MasterPic::find($rani->id));
        $this->assertSame($rani->id, $a->refresh()->pic_id);
    }

    /* ------------------------------------------------------- PINDAH PIC */

    public function test_beneficiary_bisa_dipindahkan_ke_pic_lain(): void
    {
        $asal   = $this->pic('RANI FEBRIANDINI');
        $tujuan = $this->pic('INTAN PUSPITA');

        $b = $this->beneficiary($asal, 'ZULBAIDHA');

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/beneficiaries/' . $b->id . '/move-pic', ['pic_id' => $tujuan->id])
            ->assertRedirect('/beneficiaries');

        $this->assertSame($tujuan->id, $b->refresh()->pic_id);
    }

    public function test_pindah_ke_tanpa_pic_mengosongkan_kaitannya(): void
    {
        $asal = $this->pic('RANI FEBRIANDINI');
        $b    = $this->beneficiary($asal, 'ZULBAIDHA');
        $lain = $this->beneficiary($asal, 'JUNAIDI');

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/beneficiaries/' . $b->id . '/move-pic', ['pic_id' => ''])
            ->assertRedirect();

        $this->assertNull($b->refresh()->pic_id);
        // Hanya baris yang dipindahkan; anggota lain di PIC yang sama tetap.
        $this->assertSame($asal->id, $lain->refresh()->pic_id);
    }

    public function test_pindah_hanya_mengubah_pic_bukan_kolom_lain(): void
    {
        $asal   = $this->pic('RANI FEBRIANDINI');
        $tujuan = $this->pic('INTAN PUSPITA');

        $b = $this->beneficiary($asal, 'ZULBAIDHA');
        $b->update(['account_number' => '554401020227534', 'category' => 'BILLING']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/beneficiaries/' . $b->id . '/move-pic', ['pic_id' => $tujuan->id]);

        $b->refresh();

        $this->assertSame($tujuan->id, $b->pic_id);
        $this->assertSame('554401020227534', $b->account_number);
        $this->assertSame('BILLING', $b->category);
    }

    public function test_manager_tidak_boleh_memindahkan_pic(): void
    {
        $asal   = $this->pic('RANI FEBRIANDINI');
        $tujuan = $this->pic('INTAN PUSPITA');
        $b      = $this->beneficiary($asal, 'ZULBAIDHA');

        $manager = $this->userWithRole('manager');
        $this->giveMenuAccess($manager->role, 'beneficiaries');

        $this->actingAs($manager)
            ->put('/beneficiaries/' . $b->id . '/move-pic', ['pic_id' => $tujuan->id])
            ->assertForbidden();

        $this->assertSame($asal->id, $b->refresh()->pic_id);
    }

    /* ----------------------------------------------------- TOMBOL CLEAR */

    public function test_tombol_clear_mengosongkan_pencarian_tabel(): void
    {
        /*
        | Penjaga di tingkat sumber, bukan perilaku.
        |
        | Tombol Clear dulu hanya mengosongkan isi kotak pencarian tanpa
        | menyuruh DataTables menggambar ulang, sehingga namanya hilang tapi
        | tabel tetap memperlihatkan hasil pencarian sebelumnya. Perilakunya
        | hanya bisa diuji sungguhan lewat peramban; yang bisa dijaga di sini
        | adalah barisnya tidak hilang lagi tanpa sengaja.
        */
        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        $posisiTombol = strpos($html, "\$('#btnClearFilterBen').on('click'");
        $this->assertNotFalse($posisiTombol, 'Penangan tombol Clear tidak ditemukan.');

        $blok = substr($html, $posisiTombol, 600);
        $this->assertStringContainsString("terapkanPencarian('')", $blok);
    }

    /* ---------------------------------------------- TAMBAHKAN KE PIC (attach) */

    /*
    | "Tambahkan Beneficiary" di dalam modal Detail PIC MENGAITKAN baris yang
    | sudah ada — kebalikan dari Pindah PIC — bukan mendaftarkan penerima dana
    | baru. Salah paham ini pernah terjadi sekali: tombolnya sempat membuka
    | form Tambah Beneficiary biasa dengan PIC pra-terpilih, padahal yang
    | diminta adalah memilih dari daftar yang sudah ada.
    */

    public function test_superadmin_bisa_mengaitkan_beberapa_beneficiary_sekaligus(): void
    {
        $tujuan = $this->pic('RANI FEBRIANDINI');
        $a = $this->beneficiary(null, 'ZULBAIDHA');
        $b = $this->beneficiary(null, 'JUNAIDI');
        $lain = $this->beneficiary(null, 'TIDAK IKUT');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/beneficiaries/pic/' . $tujuan->id . '/attach', [
                'beneficiary_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect('/beneficiaries');

        $this->assertSame($tujuan->id, $a->refresh()->pic_id);
        $this->assertSame($tujuan->id, $b->refresh()->pic_id);
        $this->assertNull($lain->refresh()->pic_id);
    }

    public function test_attach_tidak_membuat_baris_beneficiary_baru(): void
    {
        $tujuan = $this->pic('RANI FEBRIANDINI');
        $a = $this->beneficiary(null, 'ZULBAIDHA');

        $sebelum = Beneficiary::count();

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/beneficiaries/pic/' . $tujuan->id . '/attach', [
                'beneficiary_ids' => [$a->id],
            ]);

        // Inti dari fitur ini: jumlah baris tidak berubah, hanya pic_id-nya.
        $this->assertSame($sebelum, Beneficiary::count());
    }

    public function test_attach_memindahkan_beneficiary_dari_pic_lain(): void
    {
        $lama   = $this->pic('INTAN PUSPITA');
        $tujuan = $this->pic('RANI FEBRIANDINI');
        $b      = $this->beneficiary($lama, 'ZULBAIDHA');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/beneficiaries/pic/' . $tujuan->id . '/attach', [
                'beneficiary_ids' => [$b->id],
            ]);

        $this->assertSame($tujuan->id, $b->refresh()->pic_id);
    }

    public function test_attach_menolak_id_kosong(): void
    {
        $tujuan = $this->pic('RANI FEBRIANDINI');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/beneficiaries/pic/' . $tujuan->id . '/attach', [
                'beneficiary_ids' => [],
            ])
            ->assertSessionHasErrors('beneficiary_ids');
    }

    public function test_manager_tidak_boleh_mengaitkan_beneficiary_ke_pic(): void
    {
        $tujuan = $this->pic('RANI FEBRIANDINI');
        $a      = $this->beneficiary(null, 'ZULBAIDHA');

        $manager = $this->userWithRole('manager');
        $this->giveMenuAccess($manager->role, 'beneficiaries');

        $this->actingAs($manager)
            ->post('/beneficiaries/pic/' . $tujuan->id . '/attach', [
                'beneficiary_ids' => [$a->id],
            ])
            ->assertForbidden();

        $this->assertNull($a->refresh()->pic_id);
    }

    public function test_pemilih_attach_tidak_ikut_tersaring_filter_layar(): void
    {
        /*
        | Daftar pemilihnya sengaja TIDAK memakai koleksi yang sama dengan
        | tabel utama, yang mengikuti filter kategori/status/pencarian di
        | layar. Kalau ikut tersaring, beneficiary yang sedang disembunyikan
        | filter (mis. sedang melihat kategori INTERNAL saja) tidak akan bisa
        | dipilih untuk dikaitkan ke PIC manapun.
        */
        $this->beneficiary(null, 'HANYA EXTERNAL');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries?category=INTERNAL')->assertOk()->getContent();

        $this->assertStringContainsString('"nickname":"HANYA EXTERNAL"', $html);
    }

    public function test_modal_attach_tidak_menawarkan_membuat_baru(): void
    {
        $this->pic('RANI FEBRIANDINI');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries')->assertOk()->getContent();

        $this->assertStringContainsString('id="modalPicAttach"', $html);
        $this->assertStringContainsString('id="picAttachList"', $html);
        // Bukan lagi membuka form Tambah Beneficiary biasa.
        $this->assertStringNotContainsString("\$('#tambahPic').val(picId", $html);
    }
}
