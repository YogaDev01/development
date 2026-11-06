<?php

namespace Tests\Feature\Security;

use App\Models\AccessGroup;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Fase 1 Security & Access Control — audit autentikasi, rincian perubahan
 * akses, dan konsol Keamanan (sesi aktif).
 * docs/plans/2026-11-06-security-access-control.md
 */
class SecurityConsoleTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /* ---------------------------------------------- gerbang konsol */

    public function test_konsol_ditolak_untuk_non_superadmin(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get('/security')->assertForbidden();
        $this->actingAs($manager)->post('/security/sessions/revoke', ['session_hash' => str_repeat('a', 64)])->assertForbidden();
        $this->actingAs($manager)->post('/security/users/' . $manager->id . '/logout-all')->assertForbidden();
    }

    public function test_superadmin_bisa_membuka_semua_tab(): void
    {
        $admin = $this->userWithRole('superadmin');

        foreach (['audit', 'login', 'sessions'] as $tab) {
            $this->actingAs($admin)->get('/security?tab=' . $tab)->assertOk();
        }
    }

    /* ---------------------------------------------- jejak login */

    public function test_login_gagal_akun_terdaftar_dicatat(): void
    {
        $user = $this->userWithRole('manager');

        $this->post('/login', ['username' => $user->username, 'password' => 'salah-sekali-1'])
            ->assertRedirect();

        $log = UserActivityLog::where('action', 'login_failed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('auth', $log->module);
        $this->assertSame($user->id, (int) $log->subject_id);
        $this->assertSame('@' . $user->username, $log->reference);
        $this->assertNull($log->user_id);
    }

    public function test_login_gagal_username_asing_tidak_menyimpan_isian(): void
    {
        $this->post('/login', ['username' => 'mungkin-ini-password-Rahasia9', 'password' => 'apa saja'])
            ->assertRedirect();

        $log = UserActivityLog::where('action', 'login_failed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->reference);
        $this->assertNull($log->subject_id);
        $this->assertStringNotContainsString('Rahasia9', $log->description . $log->notes);
    }

    public function test_pemblokiran_login_dicatat_tanpa_mengubah_pesan(): void
    {
        $user = $this->userWithRole('manager');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'salah-' . $i]);
        }

        $this->post('/login', ['username' => $user->username, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('login');

        $this->assertTrue(UserActivityLog::where('action', 'lockout')->where('subject_id', $user->id)->exists());
        $this->assertGuest();
    }

    public function test_login_berhasil_mengisi_last_login(): void
    {
        $user = $this->userWithRole('manager');

        $this->post('/login', ['username' => $user->username, 'password' => 'rahasia123'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(UserActivityLog::where('action', 'login')->where('user_id', $user->id)->exists());
    }

    /* ---------------------------------------------- sesi aktif */

    public function test_cabut_sesi_menghapus_sesi_dan_merotasi_remember_token(): void
    {
        config(['session.driver' => 'database']);

        $admin  = $this->userWithRole('superadmin');
        $target = $this->userWithRole('manager');
        DB::table('users')->where('id', $target->id)->update(['remember_token' => 'token-lama']);
        $this->insertSession('sesi-target-1', $target->id);

        $this->actingAs($admin)
            ->post('/security/sessions/revoke', ['session_hash' => hash('sha256', 'sesi-target-1')])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-target-1']);
        $this->assertNotSame('token-lama', DB::table('users')->where('id', $target->id)->value('remember_token'));
        $this->assertTrue(UserActivityLog::where('action', 'revoke_session')->where('subject_id', $target->id)->exists());
    }

    public function test_hash_sesi_tidak_valid_ditolak(): void
    {
        config(['session.driver' => 'database']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/security/sessions/revoke', ['session_hash' => 'bukan-hash'])
            ->assertSessionHasErrors('session_hash');
    }

    public function test_logout_semua_perangkat_hanya_menyentuh_user_target(): void
    {
        config(['session.driver' => 'database']);

        $admin  = $this->userWithRole('superadmin');
        $target = $this->userWithRole('manager');
        $lain   = $this->userWithRole('manager');
        $this->insertSession('t-1', $target->id);
        $this->insertSession('t-2', $target->id);
        $this->insertSession('lain-1', $lain->id);

        $this->actingAs($admin)
            ->post('/security/users/' . $target->id . '/logout-all')
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertDatabaseHas('sessions', ['id' => 'lain-1']);
    }

    public function test_tidak_bisa_memutus_semua_sesi_sendiri(): void
    {
        config(['session.driver' => 'database']);

        $admin = $this->userWithRole('superadmin');
        $this->insertSession('admin-hp', $admin->id);

        $this->actingAs($admin)
            ->post('/security/users/' . $admin->id . '/logout-all')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sessions', ['id' => 'admin-hp']);
    }

    /* ---------------------------------------------- rincian perubahan akses */

    public function test_perubahan_role_user_tercatat_dengan_nama_role(): void
    {
        $admin = $this->userWithRole('superadmin');
        $ag    = AccessGroup::create(['name' => 'AG UJI']);
        $user  = $this->userWithRole('manager', ['access_group_id' => $ag->id]);
        $head  = $this->role('head');

        $this->actingAs($admin)->put('/user/' . $user->id, [
            'name'            => $user->name,
            'username'        => $user->username,
            'email'           => $user->email,
            'role_id'         => $head->id,
            'access_group_id' => $ag->id,
            'status'          => 'ACTIVE',
        ])->assertRedirect('/user');

        $log = UserActivityLog::where('module', 'user_access')->where('action', 'update')
            ->where('subject_id', $user->id)->latest('id')->first();

        $this->assertNotNull($log);
        // assertContainsEquals: kolom JSON MySQL menyusun ulang urutan key.
        $this->assertContainsEquals(['label' => 'Role', 'from' => 'manager', 'to' => 'head'], $log->changes);
    }

    public function test_perubahan_menu_role_tercatat_di_audit(): void
    {
        $admin = $this->userWithRole('superadmin');
        $role  = $this->role('manager');
        $menu  = MenuItem::firstOrCreate(['key' => 'forecast'], ['label' => 'Forecast']);

        $this->actingAs($admin)->put('/role/' . $role->id, ['menu_item_ids' => [$menu->id]])
            ->assertRedirect(route('role.index'));

        $log = UserActivityLog::where('module', 'user_access')->where('subject_id', $role->id)
            ->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertContainsEquals(['label' => 'Menu ditambah', 'from' => '-', 'to' => 'forecast'], $log->changes);
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id'            => $id,
            'user_id'       => $userId,
            'ip_address'    => '10.0.0.' . random_int(1, 200),
            'user_agent'    => 'PHPUnit',
            'payload'       => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
