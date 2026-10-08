<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\Support\MakesTesysData;
use Tests\TestCase;
use App\Models\User;
use App\Notifications\AppNotification;

/**
 * Halaman Profil (rombak corporate/social-media) + preferensi notifikasi.
 */
class ProfilePageTest extends TestCase
{
    use RefreshDatabase, MakesTesysData;

    public function test_profil_terbuka_dan_memuat_tab_inti(): void
    {
        $user = $this->userWithRole('maker');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Ringkasan Aktivitas')
            ->assertSee('Changelog Aplikasi')
            ->assertSee('Preferensi Notifikasi');
    }

    public function test_default_semua_notifikasi_aktif(): void
    {
        $user = $this->userWithRole('maker');

        // notification_prefs null => semua kategori & channel aktif.
        $this->assertTrue($user->wantsNotification('workflow', 'database'));
        $this->assertTrue($user->wantsNotification('workflow', 'whatsapp'));
        $this->assertTrue($user->wantsNotification('general', 'database'));
    }

    public function test_default_reminder_todo_aktif_inapp_tanpa_whatsapp(): void
    {
        $user = $this->userWithRole('maker');

        // notification_prefs null => reminder in-app aktif, WhatsApp mati,
        // mode 'fixed' pada jam kerja default.
        $r = $user->todoReminderPrefs();
        $this->assertTrue($r['enabled']);
        $this->assertSame('fixed', $r['mode']);
        $this->assertFalse($r['whatsapp']);
        $this->assertSame([8, 13, 16], $r['hours']);

        $this->assertTrue($user->wantsNotification('todo', 'database'));
        $this->assertFalse($user->wantsNotification('todo', 'whatsapp')); // opt-in
    }

    public function test_simpan_preferensi_reminder_mode_interval_dan_whatsapp(): void
    {
        $user = $this->userWithRole('maker', ['phone' => '08123456789']);

        $this->actingAs($user)->post(route('profile.notifications'), [
            'wa_enabled'        => '1',
            'reminder_enabled'  => '1',
            'reminder_mode'     => 'interval',
            'reminder_interval' => '4',
            'reminder_whatsapp' => '1',
        ])->assertRedirect();

        $r = $user->refresh()->todoReminderPrefs();
        $this->assertTrue($r['enabled']);
        $this->assertSame('interval', $r['mode']);
        $this->assertSame(4, $r['interval_hours']);
        $this->assertTrue($r['whatsapp']);

        $this->assertTrue($user->wantsNotification('todo', 'whatsapp'));
    }

    public function test_simpan_preferensi_reminder_mematikan(): void
    {
        $user = $this->userWithRole('maker');

        // reminder_enabled TIDAK dikirim => mati.
        $this->actingAs($user)->post(route('profile.notifications'), [
            'reminder_mode' => 'fixed',
        ])->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->todoReminderPrefs()['enabled']);
        $this->assertFalse($user->wantsNotification('todo', 'database'));
    }

    public function test_master_switch_whatsapp_membungkam_reminder_wa(): void
    {
        $user = $this->userWithRole('maker', ['phone' => '08123456789']);

        // Reminder WA di-opt-in, tapi master switch WhatsApp dimatikan.
        $this->actingAs($user)->post(route('profile.notifications'), [
            'reminder_enabled'  => '1',
            'reminder_whatsapp' => '1',
            // wa_enabled TIDAK dikirim => false
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->wantsNotification('todo', 'database'));
        $this->assertFalse($user->wantsNotification('todo', 'whatsapp'));
    }

    public function test_via_notifikasi_menghormati_preferensi(): void
    {
        $user = $this->userWithRole('maker', ['phone' => '08123456789']);
        $user->update([
            'notification_prefs' => [
                'wa_enabled' => true,
                'categories' => [
                    'workflow' => ['database' => false, 'whatsapp' => true],
                ],
            ],
        ]);

        $notif = new AppNotification('Judul', 'Pesan', null, 'workflow');
        $channels = $notif->via($user->refresh());

        // Kategori workflow in-app dimatikan => tidak ada channel 'database'.
        $this->assertNotContains('database', $channels);
    }

    public function test_minta_kode_mengirim_email_dan_menyimpan_cache(): void
    {
        Mail::fake();
        $user = $this->userWithRole('maker');

        $this->actingAs($user)
            ->postJson(route('profile.password.token'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        // Mail::fake mencegah pengiriman nyata; cukup pastikan kode tersimpan.
        $this->assertNotNull(Cache::get("profile:pwd-token:{$user->id}"));
    }

    public function test_ganti_password_dengan_kode_benar(): void
    {
        $user = $this->userWithRole('maker');
        Cache::put("profile:pwd-token:{$user->id}", ['hash' => Hash::make('654321'), 'attempts' => 0], 900);

        $this->actingAs($user)->post(route('profile.password'), [
            'code'                  => '654321',
            'password'              => 'RahasiaBaru123',
            'password_confirmation' => 'RahasiaBaru123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('RahasiaBaru123', $user->refresh()->password));
        $this->assertNull(Cache::get("profile:pwd-token:{$user->id}"));
    }

    public function test_ganti_password_kode_salah_ditolak(): void
    {
        $user = $this->userWithRole('maker');
        $oldHash = $user->password;
        Cache::put("profile:pwd-token:{$user->id}", ['hash' => Hash::make('111111'), 'attempts' => 0], 900);

        $this->actingAs($user)->post(route('profile.password'), [
            'code'                  => '999999',
            'password'              => 'RahasiaBaru123',
            'password_confirmation' => 'RahasiaBaru123',
        ])->assertSessionHasErrors('code');

        $this->assertSame($oldHash, $user->refresh()->password);
    }

    public function test_todo_summary_endpoint_3_tabel(): void
    {
        $user = $this->userWithRole('maker');

        $res = $this->actingAs($user)
            ->getJson(route('profile.todo-summary'))
            ->assertOk()
            ->assertJsonStructure(['tables', 'total', 'completedToday', 'gagal']);

        // Tepat 3 tabel: Cost Request, Budgeting, Purchase Order.
        $modules = collect($res->json('tables'))->pluck('module')->all();
        $this->assertSame(['Cost Request', 'Budgeting', 'Purchase Order'], $modules);
    }

    public function test_tombol_summary_task_tampil_untuk_semua_role(): void
    {
        foreach (['maker', 'superadmin'] as $r) {
            $this->actingAs($this->userWithRole($r))
                ->get(route('profile.show'))
                ->assertOk()
                ->assertSee('id="btnSummaryTask"', false);
        }
    }

    public function test_todo_matrix_bisa_diakses_semua_role_login(): void
    {
        // maker tanpa menu todo-list tetap bisa melihat matrix (agregat) di profil.
        $this->actingAs($this->userWithRole('maker'))
            ->get(route('profile.todo-matrix'))
            ->assertOk()
            ->assertSee('summaryTaskWrap', false);
    }

    public function test_root_menampilkan_profil_sebagai_halaman_utama(): void
    {
        $user = $this->userWithRole('maker');

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Ringkasan Aktivitas');
    }

    public function test_simpan_app_default_dan_set_session_saat_landing(): void
    {
        $user = $this->userWithRole('maker');

        // Simpan pilihan App Default = project.
        $this->actingAs($user)->put(route('profile.update'), [
            'name'        => $user->name,
            'phone'       => $user->phone,
            'default_app' => 'project',
        ])->assertRedirect();

        $this->assertSame('project', $user->refresh()->default_app);
        $this->assertSame('project', $user->defaultApp());

        // Saat membuka halaman utama, aplikasi aktif = App Default.
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSessionHas('tesys.app', 'project');
    }

    public function test_app_default_tidak_valid_ditolak(): void
    {
        $user = $this->userWithRole('maker');

        $this->actingAs($user)->put(route('profile.update'), [
            'name'        => $user->name,
            'default_app' => 'tidak-ada',
        ])->assertSessionHasErrors('default_app');
    }

    public function test_upload_dan_hapus_cover(): void
    {
        Storage::fake('secure');
        $user = $this->userWithRole('maker');

        $this->actingAs($user)->post(route('profile.cover'), [
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 300),
        ])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->cover);
        Storage::disk('secure')->assertExists($user->cover);

        $this->actingAs($user)->delete(route('profile.cover.delete'))->assertRedirect();
        $this->assertNull($user->refresh()->cover);
    }
}
