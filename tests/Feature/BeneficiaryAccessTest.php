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
     * Manager dengan akses menu 'beneficiaries' - keadaan setelah migrasi
     * 2026_09_21_000040 berjalan.
     *
     * Aksesnya diberikan di sini, bukan diandalkan dari migrasi itu: di basis
     * data uji tabel `roles` masih kosong saat migrasi berjalan, jadi migrasi
     * melewati pemberiannya. Lihat MakesTesysData::giveMenuAccess().
     */
    private function managerUser(): User
    {
        $user = $this->userWithRole('manager');
        $this->giveMenuAccess($user->role, 'beneficiaries');

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
            ->get('/beneficiaries')
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
            ->assertRedirect('/beneficiaries');

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
            ->assertRedirect('/beneficiaries');

        $this->assertSame('DIPERBARUI', $ben->fresh()->nickname);
    }

    public function test_superadmin_bisa_menghapus_beneficiary(): void
    {
        $ben = $this->makeBeneficiary();

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete('/beneficiaries/' . $ben->id)
            ->assertRedirect('/beneficiaries');

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

    /*
    |--------------------------------------------------------------------------
    | Role tanpa akses menu sama sekali
    |--------------------------------------------------------------------------
    */

    public function test_role_tanpa_akses_menu_tetap_ditolak(): void
    {
        // 'treasury' tidak diberi menu 'beneficiaries' oleh migrasi mana pun.
        $this->actingAs($this->userWithRole('treasury'))
            ->get('/beneficiaries')
            ->assertForbidden();
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
            ->get('/beneficiaries')
            ->assertSee('btn-edit')
            ->assertSee('Bank Master');

        $this->actingAs($this->managerUser())
            ->get('/beneficiaries')
            ->assertDontSee('btn-edit')
            ->assertDontSee('Bank Master')
            // Menambah tetap terbuka.
            ->assertSee('Tambah Beneficiary');
    }
}
