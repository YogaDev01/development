<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Alur "Lupa Sandi" di halaman login (tamu): cek user+email, kirim kode, reset.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase, MakesTesysData;

    public function test_verify_cocok_dan_tidak_cocok(): void
    {
        $user = $this->userWithRole('maker', ['email' => 'pic@terex.test']);

        $this->postJson(route('forgot.verify'), [
            'username' => $user->username, 'email' => 'pic@terex.test',
        ])->assertOk()->assertJson(['match' => true]);

        $this->postJson(route('forgot.verify'), [
            'username' => $user->username, 'email' => 'salah@terex.test',
        ])->assertOk()->assertJson(['match' => false]);
    }

    public function test_kirim_kode_hanya_bila_cocok(): void
    {
        Mail::fake();
        $user = $this->userWithRole('maker', ['email' => 'pic@terex.test']);

        // Tidak cocok -> 422, tidak ada kode.
        $this->postJson(route('forgot.send'), [
            'username' => $user->username, 'email' => 'salah@terex.test',
        ])->assertStatus(422);
        $this->assertNull(Cache::get("auth:forgot-token:{$user->id}"));

        // Cocok -> ok, kode tersimpan (hash).
        $this->postJson(route('forgot.send'), [
            'username' => $user->username, 'email' => 'pic@terex.test',
        ])->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull(Cache::get("auth:forgot-token:{$user->id}"));
    }

    public function test_reset_dengan_kode_benar(): void
    {
        $user = $this->userWithRole('maker', ['email' => 'pic@terex.test']);
        Cache::put("auth:forgot-token:{$user->id}", ['hash' => Hash::make('123456'), 'attempts' => 0], 900);

        $this->postJson(route('forgot.reset'), [
            'username' => $user->username, 'email' => 'pic@terex.test',
            'code' => '123456',
            'password' => 'PasswordBaru123', 'password_confirmation' => 'PasswordBaru123',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertTrue(Hash::check('PasswordBaru123', $user->refresh()->password));
        $this->assertNull(Cache::get("auth:forgot-token:{$user->id}"));
    }

    public function test_reset_kode_salah_ditolak(): void
    {
        $user = $this->userWithRole('maker', ['email' => 'pic@terex.test']);
        $old  = $user->password;
        Cache::put("auth:forgot-token:{$user->id}", ['hash' => Hash::make('111111'), 'attempts' => 0], 900);

        $this->postJson(route('forgot.reset'), [
            'username' => $user->username, 'email' => 'pic@terex.test',
            'code' => '999999',
            'password' => 'PasswordBaru123', 'password_confirmation' => 'PasswordBaru123',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertSame($old, $user->refresh()->password);
    }

    public function test_link_lupa_sandi_tampil_di_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('Lupa Sandi?');
    }
}
