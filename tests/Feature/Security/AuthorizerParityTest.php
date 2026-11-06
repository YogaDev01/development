<?php

namespace Tests\Feature\Security;

use App\Models\UserActivityLog;
use App\Support\Authorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Fase 2 — mesin permission dalam mode shadow.
 *
 * Inti uji: setelah seed parity, Authorizer::allows() memberi jawaban yang
 * SAMA dengan gerbang role: lama untuk SETIAP permission × SETIAP role.
 * Lalu: middleware shadow tidak pernah mengubah respons, hanya mencatat selisih.
 */
class AuthorizerParityTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /** Semua nama role yang disebut gerbang, ditambah satu role tanpa kewenangan. */
    private function semuaRole(): array
    {
        $nama = ['role_tanpa_hak'];
        foreach (Authorizer::registry() as $def) {
            $nama = array_merge($nama, $def['seed_roles']);
        }

        return array_values(array_unique($nama));
    }

    public function test_parity_setiap_permission_untuk_setiap_role(): void
    {
        $users = [];
        foreach ($this->semuaRole() as $nama) {
            $users[$nama] = $this->userWithRole($nama);
        }

        Authorizer::syncSeed(true);

        $routes = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $routes[Authorizer::routeKey($route)] = $route;
        }

        $beda = [];
        foreach (Authorizer::registry() as $key => $def) {
            foreach ($users as $nama => $user) {
                $lama = Authorizer::legacyAllows($user, $routes[$key]);
                $baru = Authorizer::allows($user, $key);
                if ($lama !== $baru) {
                    $beda[] = "$key [$nama] lama=" . var_export($lama, true) . ' baru=' . var_export($baru, true);
                }
            }
        }

        $this->assertSame([], $beda);
    }

    public function test_permission_tidak_terdaftar_selalu_ditolak(): void
    {
        $admin = $this->userWithRole('superadmin');
        Authorizer::syncSeed(true);

        $this->assertFalse(Authorizer::allows($admin, 'tidak.ada'));
    }

    public function test_view_as_memakai_role_yang_dipreview(): void
    {
        $admin   = $this->userWithRole('superadmin');
        $manager = $this->role('manager');
        Authorizer::syncSeed(true);

        $this->assertTrue(Authorizer::allows($admin, 'role.index'));

        session(['view_as_role_id' => $manager->id]);
        request()->attributes->remove('authz.keys.' . $admin->role_id);

        $this->assertFalse(Authorizer::allows($admin, 'role.index'));
    }

    public function test_sinkron_ulang_tidak_mengembalikan_hak_yang_dicabut(): void
    {
        $head = $this->role('head');
        $this->role('superadmin');
        Authorizer::syncSeed(true);

        // Pencabutan = revoked_at (baris dipertahankan), bukan hapus.
        DB::table('role_permissions')->where('role_id', $head->id)->where('permission_key', 'budget-request.head-approve')
            ->update(['revoked_at' => now()]);
        $hasil = Authorizer::syncSeed(true);

        $this->assertSame(0, $hasil['rows']);
        $this->assertFalse(Authorizer::allows($this->userWithRole('head'), 'budget-request.head-approve'));
    }

    public function test_role_yang_dibuat_belakangan_ikut_diisi(): void
    {
        $this->role('superadmin');
        Authorizer::syncSeed(true);

        // Role yang disebut gerbang tetapi belum ada (sebagian role dibuat oleh
        // migrasi lama, jadi dipilih yang memang belum ada).
        $ada  = DB::table('roles')->pluck('name')->map(fn ($n) => strtolower($n))->all();
        $nama = collect(Authorizer::registry())->pluck('seed_roles')->flatten()->unique()
            ->first(fn ($n) => ! in_array($n, $ada, true));
        $this->assertNotNull($nama, 'Semua role gerbang sudah ada di DB uji.');

        $baru  = $this->role($nama);
        $hasil = Authorizer::syncSeed(true);

        $this->assertGreaterThan(0, $hasil['rows']);
        $this->assertTrue(DB::table('role_permissions')->where('role_id', $baru->id)->exists());
    }

    public function test_sinkron_mengisi_permission_yang_belum_punya_baris(): void
    {
        $this->role('head');
        $this->role('superadmin');
        Authorizer::syncSeed(true);

        DB::table('role_permissions')->where('permission_key', 'budget-request.head-approve')->delete();
        $dry = Authorizer::syncSeed(false);
        $this->assertSame(1, $dry['permissions_seeded']);
        $this->assertFalse(DB::table('role_permissions')->where('permission_key', 'budget-request.head-approve')->exists());

        Authorizer::syncSeed(true);
        $this->assertSame(2, DB::table('role_permissions')->where('permission_key', 'budget-request.head-approve')->count());
    }

    /* ---------------------------------------------- middleware shadow */

    private function ruteUji(): void
    {
        Route::middleware(['web', 'auth', 'role:superadmin,head'])->get('/_uji/shadow', fn () => 'ok')->name('ujishadow.lihat');

        config(['permissions.permissions' => Authorizer::registry() + [
            'ujishadow.lihat' => ['module' => 'role', 'label' => 'uji', 'seed_roles' => ['head', 'superadmin']],
        ]]);
    }

    public function test_shadow_mencatat_selisih_tanpa_mengubah_respons(): void
    {
        config(['security.permission_mode' => 'shadow']);
        $this->ruteUji();
        $head = $this->userWithRole('head');
        $this->role('superadmin');
        Authorizer::syncSeed(true);

        // Cabut hak head di mesin baru saja — gerbang lama tetap mengizinkan.
        DB::table('role_permissions')->where('role_id', $head->role_id)->where('permission_key', 'ujishadow.lihat')
            ->update(['revoked_at' => now()]);

        $this->actingAs($head)->get('/_uji/shadow')->assertOk()->assertSee('ok');
        $this->actingAs($head)->get('/_uji/shadow')->assertOk();

        $logs = DB::table('permission_shadow_logs')->get();
        $this->assertCount(1, $logs, 'Selisih yang sama dicatat sekali per jendela.');
        $this->assertSame('ujishadow.lihat', $logs[0]->permission_key);
        $this->assertSame(1, (int) $logs[0]->legacy_allowed);
        $this->assertSame(0, (int) $logs[0]->engine_allowed);
    }

    public function test_shadow_tidak_mencatat_bila_jawaban_sama(): void
    {
        config(['security.permission_mode' => 'shadow']);
        $this->ruteUji();
        $head = $this->userWithRole('head');
        $this->role('superadmin');
        Authorizer::syncSeed(true);

        $this->actingAs($head)->get('/_uji/shadow')->assertOk();
        $this->actingAs($this->userWithRole('manager'))->get('/_uji/shadow')->assertForbidden();

        $this->assertSame(0, DB::table('permission_shadow_logs')->count());
    }

    public function test_mode_off_tidak_mengevaluasi_apa_pun(): void
    {
        config(['security.permission_mode' => 'off']);
        $this->ruteUji();
        $head = $this->userWithRole('head');

        // role_permissions kosong: bila dievaluasi pasti selisih.
        $this->actingAs($head)->get('/_uji/shadow')->assertOk();

        $this->assertSame(0, DB::table('permission_shadow_logs')->count());
    }

    public function test_tab_shadow_di_konsol_terbuka_untuk_superadmin(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/security?tab=shadow')
            ->assertOk()
            ->assertSee('Shadow Permission');

        $this->assertSame(0, UserActivityLog::where('action', 'access_denied')->count());
    }
}
