<?php

namespace Tests\Feature\Security;

use App\Support\Authorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Matriks Kewenangan Aksi di modal Role: pemetaan kelompok -> kolom dan jalur simpan
 * ke role_permissions (tidak menyentuh gerbang role: yang berlaku).
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_pemetaan_matriks_hanya_memakai_kelompok_dan_kolom_yang_valid(): void
    {
        $groups = config('permission_groups');
        $valid  = ['view', 'create', 'edit', 'delete', 'approve', 'export'];

        foreach (config('permission_matrix') as $gkey => $cols) {
            $this->assertArrayHasKey($gkey, $groups, "Kelompok $gkey tidak ada");
            $this->assertNotEmpty($cols);
            $this->assertSame([], array_diff($cols, $valid), "Kolom tidak dikenal di $gkey");
        }
    }

    public function test_simpan_matriks_memberi_dan_mencabut_hak_lalu_memberi_kembali(): void
    {
        $admin = $this->userWithRole('superadmin');
        $role  = $this->role('head');
        $keys  = config('permission_groups')['bank.kelola']['permissions'];

        $kirim = fn (int $v) => $this->actingAs($admin)->put(route('role.update', $role->id), ['perm_set' => ['bank.kelola' => (string) $v]]);

        $kirim(1);
        $this->assertSame(count($keys), DB::table('role_permissions')->where('role_id', $role->id)->whereIn('permission_key', $keys)->whereNull('revoked_at')->count());

        $kirim(0);
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->whereIn('permission_key', $keys)->whereNull('revoked_at')->count());
        $this->assertSame(count($keys), DB::table('role_permissions')->where('role_id', $role->id)->whereIn('permission_key', $keys)->count(), 'Baris harus dipertahankan (revoked_at)');

        $kirim(1);
        $this->assertSame(count($keys), DB::table('role_permissions')->where('role_id', $role->id)->whereIn('permission_key', $keys)->whereNull('revoked_at')->count());
        $this->assertSame(count($keys), DB::table('role_permissions')->where('role_id', $role->id)->whereIn('permission_key', $keys)->count(), 'Tidak boleh ada baris ganda');
    }

    public function test_simpan_tanpa_perm_set_tidak_mengubah_kewenangan(): void
    {
        $admin = $this->userWithRole('superadmin');
        $role  = $this->role('head');
        Authorizer::syncSeed(true);
        $sebelum = DB::table('role_permissions')->where('role_id', $role->id)->whereNull('revoked_at')->count();

        $this->actingAs($admin)->put(route('role.update', $role->id), ['menu_item_ids' => []]);

        $this->assertSame($sebelum, DB::table('role_permissions')->where('role_id', $role->id)->whereNull('revoked_at')->count());
    }

    public function test_kunci_kelompok_tidak_dikenal_diabaikan_dan_non_superadmin_ditolak(): void
    {
        $admin = $this->userWithRole('superadmin');
        $role  = $this->role('head');

        $this->actingAs($admin)->put(route('role.update', $role->id), ['perm_set' => ['tidak.ada' => '1']]);
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->count());

        $biasa = $this->userWithRole('finance');
        $this->actingAs($biasa)->put(route('role.update', $role->id), ['perm_set' => ['bank.kelola' => '1']])->assertForbidden();
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->count());
    }
}
