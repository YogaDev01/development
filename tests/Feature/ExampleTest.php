<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Uji asap: aplikasi hidup dan halaman utama dijaga otentikasi.
 *
 * Menggantikan ExampleTest bawaan Laravel, yang mengharapkan '/' membalas 200
 * — di aplikasi ini '/' memang mengalihkan tamu ke halaman login, jadi uji
 * bawaan itu selalu merah dan lama-lama diabaikan orang.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_halaman_login_terbuka(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_pengguna_masuk_melihat_dashboard(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/')
            ->assertOk();
    }
}
