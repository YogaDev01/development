<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\MasterPic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Kaitan Beneficiary <-> PIC: Pindah PIC (satu beneficiary) dan Kaitkan
 * (beberapa beneficiary yang sudah ada ke satu PIC).
 *
 * Sejak halaman PIC & Beneficiary digabung (2026-11-09) ringkasan per PIC
 * (pic-groups), tab, dan endpoint hapus PIC milik layar Beneficiary sudah
 * tidak ada — halaman gabungan diuji di PicBeneficiaryPageTest, hapus PIC
 * lewat master-pic.destroy.
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

    /* ------------------------------------------------------- PINDAH PIC */

    public function test_beneficiary_bisa_dipindahkan_ke_pic_lain(): void
    {
        $asal   = $this->pic('RANI FEBRIANDINI');
        $tujuan = $this->pic('INTAN PUSPITA');

        $b = $this->beneficiary($asal, 'ZULBAIDHA');

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/beneficiaries/' . $b->id . '/move-pic', ['pic_id' => $tujuan->id])
            ->assertRedirect('/pic-beneficiary');

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
        $this->giveMenuAccess($manager->role, 'pic-beneficiary');

        $this->actingAs($manager)
            ->put('/beneficiaries/' . $b->id . '/move-pic', ['pic_id' => $tujuan->id])
            ->assertForbidden();

        $this->assertSame($asal->id, $b->refresh()->pic_id);
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
            ->assertRedirect('/pic-beneficiary');

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
        $this->giveMenuAccess($manager->role, 'pic-beneficiary');

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
        | Daftar pemilihnya sengaja TIDAK memakai filter kategori/status/
        | pencarian di layar. Kalau ikut tersaring, beneficiary yang sedang
        | disembunyikan filter (mis. sedang melihat kategori INTERNAL saja)
        | tidak akan bisa dipilih untuk dikaitkan ke PIC manapun. Diambil lazy
        | lewat endpoint tersendiri saat modal dibuka.
        */
        $this->beneficiary(null, 'HANYA EXTERNAL');

        $this->actingAs($this->userWithRole('superadmin'))
            ->getJson('/beneficiaries/picker?category=INTERNAL')
            ->assertOk()
            ->assertJsonFragment(['nickname' => 'HANYA EXTERNAL']);
    }

    public function test_manager_tidak_boleh_mengambil_daftar_pemilih(): void
    {
        $manager = $this->userWithRole('manager');
        $this->giveMenuAccess($manager->role, 'pic-beneficiary');

        $this->actingAs($manager)->getJson('/beneficiaries/picker')->assertForbidden();
    }

    public function test_modal_attach_tidak_menawarkan_membuat_baru(): void
    {
        $this->pic('RANI FEBRIANDINI');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/pic-beneficiary')->assertOk()->getContent();

        $this->assertStringContainsString('id="modalPicAttach"', $html);
        $this->assertStringContainsString('id="picAttachList"', $html);
        // "Kaitkan" memakai endpoint attach, bukan form Tambah Beneficiary.
        $this->assertStringContainsString("'/pic/' + picId + '/attach'", $html);
    }
}
