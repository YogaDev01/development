<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\MasterPic;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Halaman gabungan PIC & Beneficiary (2026-11-09).
 *
 * Satu halaman, satu kunci menu 'pic-beneficiary': PIC menjadi induk,
 * beneficiary detailnya, beneficiary tanpa PIC tampil sebagai baris sendiri.
 * Menu itu hanya membuka MELIHAT & MENAMBAH; mengubah/menghapus PIC kini
 * dikunci 'role:superadmin' (AccessRoles::PIC_MANAGE), sama seperti
 * Beneficiary (AccessRoles::BENEFICIARY_MANAGE).
 */
class PicBeneficiaryPageTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function manager(): User
    {
        $user = $this->userWithRole('manager');
        $this->giveMenuAccess($user->role, 'pic-beneficiary');

        return $user;
    }

    private function pic(string $nama, string $type = 'internal', string $status = 'ACTIVE'): MasterPic
    {
        return MasterPic::create(['type' => $type, 'pic' => $nama, 'status' => $status]);
    }

    private function beneficiary(?MasterPic $pic, string $nickname, string $category = 'EXTERNAL'): Beneficiary
    {
        return Beneficiary::create([
            'nickname'       => $nickname,
            'complete_name'  => $nickname . ' LENGKAP',
            'category'       => $category,
            'pic_id'         => $pic?->id,
            'account_number' => '9988' . strlen($nickname) . crc32($nickname),
            'status'         => 'ACTIVE',
        ]);
    }

    /* ------------------------------------------------------------ HALAMAN */

    public function test_pic_semua_tipe_dan_beneficiary_tanpa_pic_ada_di_satu_tabel(): void
    {
        $internal = $this->pic('RAHMAT KUSNAN', 'internal');
        $this->pic('VENDOR LUAR', 'external');
        $this->pic('PIC CUSTOMER', 'customer');
        $this->beneficiary($internal, 'RAHMAT', 'INTERNAL');
        $this->beneficiary(null, 'YATIM PIATU');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/pic-beneficiary')->assertOk()->getContent();

        // Satu tabel, tanpa tab Internal/External/Database.
        $this->assertSame(1, substr_count($html, 'id="pbTable"'));
        $this->assertStringNotContainsString('pic-main-tabs', $html);
        $this->assertStringNotContainsString('ben-main-tabs', $html);

        $this->assertStringContainsString('RAHMAT KUSNAN', $html);
        $this->assertStringContainsString('VENDOR LUAR', $html);
        $this->assertStringContainsString('PIC CUSTOMER', $html);

        // Beneficiary tanpa PIC = baris sendiri berlabel "Tanpa PIC".
        $this->assertMatchesRegularExpression('/class="pb-row-orphan"[^>]*data-jenis="Tanpa PIC"/s', $html);
        $this->assertStringContainsString('YATIM PIATU', $html);
    }

    public function test_beneficiary_ber_pic_dikirim_sebagai_detail_pic_nya(): void
    {
        $pic = $this->pic('RAHMAT KUSNAN');
        $ben = $this->beneficiary($pic, 'RAHMAT', 'INTERNAL');

        $response = $this->actingAs($this->userWithRole('superadmin'))->get('/pic-beneficiary')->assertOk();

        $benByPic = $response->viewData('benByPic');
        $this->assertSame([$ben->id], $benByPic[$pic->id]->pluck('id')->all());
        $this->assertTrue($response->viewData('orphans')->isEmpty());

        // Bukan baris tersendiri di tabel utama.
        $this->assertStringNotContainsString('data-ben-id="' . $ben->id . '"', $response->getContent());
    }

    public function test_pilihan_kategori_billing_tetap_tersedia(): void
    {
        $this->actingAs($this->manager())
            ->get('/pic-beneficiary')
            ->assertOk()
            ->assertSee('<option value="BILLING"', false);
    }

    public function test_url_lama_dialihkan_ke_halaman_gabungan(): void
    {
        $user = $this->manager();

        $this->actingAs($user)->get('/beneficiaries')->assertRedirect('/pic-beneficiary');
        $this->actingAs($user)->get('/master-pic')->assertRedirect('/pic-beneficiary');
    }

    public function test_menu_lama_tidak_lagi_membuka_halaman(): void
    {
        $user = $this->userWithRole('manager');
        $this->giveMenuAccess($user->role, 'beneficiaries', 'master-pic');

        $this->actingAs($user)->get('/pic-beneficiary')->assertForbidden();
    }

    /* ---------------------------------------------------------- AKSES PIC */

    public function test_pemegang_menu_bisa_menambah_pic(): void
    {
        $this->actingAs($this->manager())
            ->post('/master-pic', ['type' => 'external', 'pic' => 'pic baru', 'status' => 'ACTIVE'])
            ->assertRedirect('/pic-beneficiary');

        $this->assertDatabaseHas('master_pics', ['pic' => 'PIC BARU', 'type' => 'external']);
    }

    public function test_manager_tidak_bisa_mengubah_menghapus_atau_menarik_data_pic(): void
    {
        $pic     = $this->pic('RAHMAT KUSNAN');
        $manager = $this->manager();

        $this->actingAs($manager)->get('/master-pic/' . $pic->id . '/edit-data')->assertForbidden();
        $this->actingAs($manager)
            ->put('/master-pic/' . $pic->id, ['type' => 'internal', 'pic' => 'DIBAJAK', 'status' => 'ACTIVE'])
            ->assertForbidden();
        $this->actingAs($manager)->delete('/master-pic/' . $pic->id)->assertForbidden();

        $this->assertSame('RAHMAT KUSNAN', $pic->fresh()->pic);
    }

    public function test_superadmin_bisa_mengubah_pic(): void
    {
        $pic = $this->pic('RAHMAT KUSNAN');

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/master-pic/' . $pic->id, ['type' => 'internal', 'pic' => 'rahmat k', 'status' => 'INACTIVE'])
            ->assertRedirect('/pic-beneficiary');

        $this->assertSame('RAHMAT K', $pic->fresh()->pic);
        $this->assertSame('INACTIVE', $pic->fresh()->status);
    }

    public function test_hapus_pic_tidak_menghapus_beneficiary_nya(): void
    {
        $pic  = $this->pic('RAHMAT KUSNAN');
        $lain = $this->pic('PIC LAIN');
        $a    = $this->beneficiary($pic, 'RAHMAT', 'INTERNAL');
        $c    = $this->beneficiary($lain, 'MILIK LAIN');

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/master-pic/' . $pic->id)
            ->assertRedirect('/pic-beneficiary')
            ->assertSessionHas('success');

        $this->assertNull(MasterPic::find($pic->id));
        // pic_id nullOnDelete: beneficiary kembali menjadi "Tanpa PIC".
        $this->assertNotNull(Beneficiary::find($a->id));
        $this->assertNull($a->refresh()->pic_id);
        $this->assertSame($lain->id, $c->refresh()->pic_id);
    }

    public function test_pic_yang_dipakai_project_tidak_bisa_dihapus(): void
    {
        $pic = $this->pic('RAHMAT KUSNAN');

        DB::table('project_pic_links')->insert([
            'project_id'    => $this->makeProject()->id,
            'master_pic_id' => $pic->id,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->from('/pic-beneficiary')
            ->delete('/master-pic/' . $pic->id)
            ->assertSessionHas('error');

        $this->assertNotNull(MasterPic::find($pic->id));
    }

    public function test_form_edit_beneficiary_memuat_pic_nonaktif(): void
    {
        // Kalau PIC nonaktif tidak ada di pilihan, menyimpan form Edit akan
        // diam-diam mengosongkan pic_id beneficiary-nya.
        $nonaktif = $this->pic('PIC PENSIUN', 'internal', 'INACTIVE');

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/pic-beneficiary')->assertOk()->getContent();

        $edit = substr($html, strpos($html, 'id="editBenPic"'), 4000);
        $this->assertStringContainsString('value="' . $nonaktif->id . '"', $edit);

        $tambah = substr($html, strpos($html, 'id="tambahBenPic"'), 4000);
        $this->assertStringNotContainsString('value="' . $nonaktif->id . '"', substr($tambah, 0, strpos($tambah, '</select>')));
    }

    /* ----------------------------------------------------------- MIGRASI */

    public function test_migrasi_menyatukan_hak_akses_menu_lama(): void
    {
        $benOnly = $this->role('manager');
        $picOnly = $this->role('finance');
        $tanpa   = $this->role('treasury');

        $this->giveMenuAccess($benOnly, 'beneficiaries');
        $this->giveMenuAccess($picOnly, 'master-pic');

        ob_start();
        (require base_path('database/migrations/2026_11_09_000400_merge_pic_beneficiary_menu.php'))->up();
        ob_end_clean();

        $baru = MenuItem::where('key', 'pic-beneficiary')->first();
        $this->assertNotNull($baru);

        $pemegang = DB::table('role_menu_items')->where('menu_item_id', $baru->id)->pluck('role_id')->all();
        $this->assertEqualsCanonicalizing([$benOnly->id, $picOnly->id], $pemegang);
        $this->assertNotContains($tanpa->id, $pemegang);

        // Kunci lama dihapus beserta centangnya.
        $this->assertSame(0, MenuItem::whereIn('key', ['beneficiaries', 'master-pic'])->count());

        // Aman diulang.
        ob_start();
        (require base_path('database/migrations/2026_11_09_000400_merge_pic_beneficiary_menu.php'))->up();
        ob_end_clean();
        $this->assertSame(2, DB::table('role_menu_items')->where('menu_item_id', $baru->id)->count());
    }
}
