<?php

namespace Tests\Feature\Security;

use App\Support\Authorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Fase 3 — kelompok aksi (config/permission_groups.php) dan tab Kewenangan
 * baca-saja di modal Role & Menu Access.
 */
class PermissionGroupsTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_setiap_permission_tepat_di_satu_kelompok(): void
    {
        $hitung = [];
        foreach (config('permission_groups') as $g) {
            foreach ($g['permissions'] as $key) {
                $hitung[$key] = ($hitung[$key] ?? 0) + 1;
            }
        }

        $registry = array_keys(Authorizer::registry());
        sort($registry);
        $dikelompokkan = array_keys($hitung);
        sort($dikelompokkan);

        $this->assertSame($registry, $dikelompokkan, 'Permission tanpa kelompok / kelompok berisi permission yatim — jalankan scripts/security/permission-groups.php.');
        $this->assertSame([], array_keys(array_filter($hitung, fn ($n) => $n > 1)), 'Permission di lebih dari satu kelompok.');
    }

    /** Syarat parity: isi satu kelompok selalu punya kombinasi role yang sama. */
    public function test_isi_kelompok_punya_role_identik_dan_modul_sama(): void
    {
        $registry = Authorizer::registry();

        foreach (config('permission_groups') as $gkey => $g) {
            $sig = null;
            foreach ($g['permissions'] as $key) {
                $this->assertSame($g['module'], $registry[$key]['module'], "$key bukan modul {$g['module']}");
                $roles = $registry[$key]['seed_roles'];
                $sig ??= $roles;
                $this->assertSame($sig, $roles, "Role tidak seragam di kelompok $gkey ($key)");
            }
            $this->assertNotSame('', trim($g['label']));
        }
    }

    public function test_edit_data_role_memuat_status_kewenangan_per_kelompok(): void
    {
        $admin = $this->userWithRole('superadmin');
        $head  = $this->role('head');
        Authorizer::syncSeed(true);

        $status = $this->statusKelompok($admin, $head->id);
        $this->assertSame('all', $status['budget-request.head']);
        $this->assertSame('none', $status['budget-request.ceo']);

        DB::table('role_permissions')->where('role_id', $head->id)
            ->where('permission_key', 'budget-request.head-reject')->update(['revoked_at' => now()]);

        $this->assertSame('partial', $this->statusKelompok($admin, $head->id)['budget-request.head']);
    }

    public function test_tab_kewenangan_tidak_mengubah_simpan_menu_access(): void
    {
        $admin = $this->userWithRole('superadmin');
        $role  = $this->role('manager');
        Authorizer::syncSeed(true);
        $sebelum = DB::table('role_permissions')->count();

        // Form Menu Access tetap hanya menyimpan menu; kewenangan tidak tersentuh.
        $this->actingAs($admin)->put('/role/' . $role->id, ['menu_item_ids' => []])->assertRedirect(route('role.index'));

        $this->assertSame($sebelum, DB::table('role_permissions')->count());
    }

    public function test_halaman_role_tetap_terbuka(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))->get('/role')->assertOk()->assertSee('Kewenangan Aksi');
    }

    private function statusKelompok($admin, int $roleId): array
    {
        $json = $this->actingAs($admin)->getJson('/role/' . $roleId . '/edit-data')->assertOk()->json('permissions');
        $this->assertTrue($json['ready']);

        $hasil = [];
        foreach ($json['modules'] as $mod) {
            foreach ($mod['groups'] as $g) {
                $hasil[$g['key']] = $g['status'];
            }
        }

        return $hasil;
    }
}
