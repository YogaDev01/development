<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Master Beneficiary punya DUA tingkat kewenangan, bukan satu.
 *
 * Sebelumnya seluruh route-nya berada di balik satu gerbang 'menu:beneficiaries'
 * yang bersifat semua-atau-tidak-sama-sekali, dan tidak ada satu pun role yang
 * diberi akses - sehingga praktis hanya superadmin yang bisa masuk. Manager yang
 * membuat Cost Request harus menitipkan setiap penerima dana baru.
 *
 * Sekarang Manager boleh melihat & menambah, tapi TIDAK boleh menyentuh baris
 * yang sudah ada: Beneficiary dipakai Cost Request yang sudah disetujui, jadi
 * menyunting nomor rekening sebuah baris diam-diam mengubah arti dokumen yang
 * sudah berjalan.
 *
 * Uji ini mengunci pembagian itu di sisi SERVER. Menyembunyikan tombol di Blade
 * saja tidak dihitung - yang diuji di sini adalah apa yang terjadi kalau
 * seseorang memanggil route-nya langsung.
 */
class BeneficiaryAccessTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /**
     * Manager dengan akses menu 'pic-beneficiary' - keadaan setelah migrasi
     * 2026_09_21_000040 lalu 2026_11_09_000400 (menu gabungan PIC &
     * Beneficiary) berjalan.
     *
     * Aksesnya diberikan di sini, bukan diandalkan dari migrasi itu: di basis
     * data uji tabel `roles` masih kosong saat migrasi berjalan, jadi migrasi
     * melewati pemberiannya. Lihat MakesTesysData::giveMenuAccess().
     */
    private function managerUser(): User
    {
        $user = $this->userWithRole('manager');
        $this->giveMenuAccess($user->role, 'pic-beneficiary');

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGER - boleh melihat & menambah
    |--------------------------------------------------------------------------
    */

    public function test_manager_bisa_membuka_daftar_beneficiary(): void
    {
        $this->actingAs($this->managerUser())
            ->get('/pic-beneficiary')
            ->assertOk();
    }

    public function test_manager_bisa_menambah_beneficiary(): void
    {
        $this->actingAs($this->managerUser())
            ->post('/beneficiaries', [
                'nickname'      => 'Penerima Baru',
                'complete_name' => 'Penerima Dana Baru',
                'category'      => 'EXTERNAL',
                'status'        => 'ACTIVE',
            ])
            ->assertRedirect('/pic-beneficiary');

        // nickname & complete_name disimpan huruf besar oleh controller.
        $this->assertDatabaseHas('beneficiaries', [
            'nickname' => 'PENERIMA BARU',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGER - tidak boleh mengubah, menghapus, atau mengintip data lengkap
    |--------------------------------------------------------------------------
    */

    public function test_manager_tidak_bisa_mengubah_beneficiary(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->managerUser())
            ->put('/beneficiaries/' . $ben->id, [
                'nickname'      => 'DIBAJAK',
                'complete_name' => 'DIBAJAK',
                'category'      => 'EXTERNAL',
                'status'        => 'ACTIVE',
            ])
            ->assertForbidden();

        $this->assertSame($ben->nickname, $ben->fresh()->nickname);
    }

    public function test_manager_tidak_bisa_menghapus_beneficiary(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->managerUser())
            ->delete('/beneficiaries/' . $ben->id)
            ->assertForbidden();

        $this->assertDatabaseHas('beneficiaries', ['id' => $ben->id]);
    }

    /**
     * Endpoint pengisi modal Edit ikut dikunci.
     *
     * Kalau hanya PUT/DELETE yang dijaga, nomor rekening & kontak setiap
     * penerima dana masih bisa ditarik satu per satu lewat URL oleh role yang
     * tombol Edit-nya sudah disembunyikan - bocornya diam-diam, tanpa mengubah
     * apa pun sehingga tidak meninggalkan jejak.
     */
    public function test_manager_tidak_bisa_menarik_data_edit(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->managerUser())
            ->get('/beneficiaries/' . $ben->id . '/edit-data')
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | SUPERADMIN - tetap penuh
    |--------------------------------------------------------------------------
    */

    public function test_superadmin_bisa_mengubah_beneficiary(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/beneficiaries/' . $ben->id, [
                'nickname'      => 'Diperbarui',
                'complete_name' => 'Nama Diperbarui',
                'category'      => 'EXTERNAL',
                'status'        => 'ACTIVE',
            ])
            ->assertRedirect('/pic-beneficiary');

        $this->assertSame('DIPERBARUI', $ben->fresh()->nickname);
    }

    public function test_superadmin_bisa_menghapus_beneficiary(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/beneficiaries/' . $ben->id)
            ->assertRedirect('/pic-beneficiary');

        $this->assertDatabaseMissing('beneficiaries', ['id' => $ben->id]);
    }

    public function test_superadmin_bisa_menarik_data_edit(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/beneficiaries/' . $ben->id . '/edit-data')
            ->assertOk()
            ->assertJsonPath('id', $ben->id);
    }

    public function test_beneficiary_internal_wajib_punya_pic(): void
    {
        $this->actingAs($this->managerUser())
            ->post('/beneficiaries', [
                'nickname'      => 'Karyawan',
                'complete_name' => 'Karyawan Internal',
                'category'      => 'INTERNAL',
                'status'        => 'ACTIVE',
            ])
            ->assertSessionHasErrors('pic_id');

        $this->assertDatabaseMissing('beneficiaries', ['nickname' => 'KARYAWAN']);
    }

    public function test_nomor_rekening_ganda_ditolak_saat_tambah(): void
    {
        $payload = [
            'nickname'       => 'Pertama',
            'complete_name'  => 'Penerima Pertama',
            'category'       => 'EXTERNAL',
            'status'         => 'ACTIVE',
            'account_number' => '814301017409532',
        ];

        $user = $this->managerUser();
        $this->actingAs($user)->post('/beneficiaries', $payload)->assertRedirect('/pic-beneficiary');

        $this->actingAs($user)
            ->post('/beneficiaries', array_merge($payload, ['nickname' => 'Kedua']))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('beneficiaries', ['nickname' => 'KEDUA']);
    }

    /*
    |--------------------------------------------------------------------------
    | Role tanpa akses menu sama sekali
    |--------------------------------------------------------------------------
    */

    public function test_role_tanpa_akses_menu_tetap_ditolak(): void
    {
        // 'treasury' tidak diberi menu 'pic-beneficiary' di uji ini.
        $treasury = $this->userWithRole('treasury');

        $this->actingAs($treasury)->get('/pic-beneficiary')->assertForbidden();
        // URL lama ikut dijaga menu yang sama (bukan pengalihan terbuka).
        $this->actingAs($treasury)->get('/beneficiaries')->assertForbidden();
    }

    /**
     * Gerbang halaman dan tombol Edit dibaca dari dua tempat berbeda -
     * middleware 'role:superadmin' di routes/web.php dan MANAGE_ROLES di
     * controller. Keduanya harus sama, kalau tidak tombolnya terlihat lalu
     * menabrak 403 (atau hilang padahal role-nya berhak).
     */
    public function test_tombol_kelola_hanya_muncul_untuk_yang_berhak(): void
    {
        Beneficiary::query()->delete();
        $this->makeBeneficiary();

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/pic-beneficiary')
            ->assertSee('id="modalEditBen"', false)
            ->assertSee('btn-edit-pic')
            ->assertSee('Bank Master');

        $this->actingAs($this->managerUser())
            ->get('/pic-beneficiary')
            ->assertDontSee('id="modalEditBen"', false)
            ->assertDontSee('id="modalEditPic"', false)
            ->assertDontSee('btn-edit-ben')
            ->assertDontSee('btn-edit-pic')
            ->assertDontSee('/edit-data')
            ->assertDontSee('Bank Master')
            // Menambah tetap terbuka.
            ->assertSee('Tambah Beneficiary')
            ->assertSee('Tambah PIC');
    }
}
