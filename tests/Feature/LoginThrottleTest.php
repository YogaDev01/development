<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan SEC-03: sebelumnya tidak ada apa pun yang memperlambat
 * percobaan password beruntun terhadap satu akun.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('budi|127.0.0.1');
    }

    public function test_percobaan_keenam_ditolak_dengan_pesan_jeda(): void
    {
        $this->userWithRole('manager', ['username' => 'budi']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'budi', 'password' => 'salah'])
                ->assertSessionHasErrors('login');
        }

        $response = $this->post('/login', ['username' => 'budi', 'password' => 'salah']);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('login'),
        );
    }

    public function test_login_berhasil_mengosongkan_hitungan(): void
    {
        $this->userWithRole('manager', ['username' => 'budi']);

        $this->post('/login', ['username' => 'budi', 'password' => 'salah']);
        $this->post('/login', ['username' => 'budi', 'password' => 'rahasia123'])
            ->assertRedirect();

        $this->assertSame(0, RateLimiter::attempts('budi|127.0.0.1'));
    }
}
