<?php

namespace Tests\Feature\Security;

use App\Models\InvoiceAttachment;
use App\Models\Role;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * D18 (export/cetak/unduh), D11 (akses ditolak), D3 (tambah role) —
 * disetujui pemilik 2026-11-07. docs/plans/2026-11-06-security-access-control.md
 */
class SecurityEventsTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /* ---------------------------------------------- D11 akses ditolak */

    public function test_akses_ditolak_dicatat_sekali_per_menit_dan_respons_tetap_403(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get('/role')->assertForbidden();
        $this->actingAs($manager)->get('/role')->assertForbidden();

        $logs = UserActivityLog::where('action', 'access_denied')->where('user_id', $manager->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('security', $logs->first()->module);
        $this->assertStringContainsString('GET /role', $logs->first()->description);
    }

    public function test_akses_ditolak_tidak_tampil_di_activity_log_profil(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get('/role')->assertForbidden();

        $this->assertSame(0, UserActivityLog::feedFor($manager->id)->total());
    }

    public function test_tamu_tidak_dicatat(): void
    {
        $this->get('/role')->assertRedirect('/login');

        $this->assertSame(0, UserActivityLog::where('action', 'access_denied')->count());
    }

    /* ---------------------------------------------- D18 export & unduh */

    public function test_export_dokumen_dicatat_dengan_module_yang_benar(): void
    {
        $bp    = $this->makeBudgetPlan();
        $admin = $this->userWithRole('superadmin');

        $this->actingAs($admin)->get('/budget-plan/' . $bp->id . '/export-budget')->assertOk();

        $log = UserActivityLog::where('action', 'export')->where('user_id', $admin->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('budget_plan', $log->module);
        $this->assertStringContainsString('#' . $bp->id, $log->description);
    }

    public function test_export_yang_dicatat_controller_tidak_dicatat_dua_kali(): void
    {
        Route::middleware(['web', 'auth'])->get('/_uji/sendiri', function () {
            UserActivityLog::record('cost_register', 'export', 'Export oleh controller');

            return 'ok';
        })->name('ujisendiri.export');

        Route::middleware(['web', 'auth'])->get('/_uji/umum', fn () => 'ok')->name('ujiumum.export');

        $user = $this->userWithRole('superadmin');
        $this->actingAs($user)->get('/_uji/sendiri')->assertOk();
        $this->actingAs($user)->get('/_uji/umum')->assertOk();

        $this->assertSame(1, UserActivityLog::where('action', 'export')->where('module', 'cost_register')->count());
        $this->assertSame(1, UserActivityLog::where('action', 'export')->where('module', 'security')->count());
    }

    public function test_unduh_lampiran_dicatat_sekali_dan_foto_profil_tidak(): void
    {
        Storage::fake('secure');

        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()));
        Storage::disk('secure')->put('invoice_attachments/sah.pdf', 'isi');
        InvoiceAttachment::create([
            'invoice_id' => $invoice->id,
            'title'      => 'Lampiran',
            'file_path'  => 'invoice_attachments/sah.pdf',
            'file_name'  => 'sah.pdf',
        ]);
        Storage::disk('secure')->put('avatars/foto.png', 'png');

        $finance = $this->userWithRole('finance');
        $this->actingAs($finance)->get('/storage/invoice_attachments/sah.pdf')->assertOk();
        $this->actingAs($finance)->get('/storage/invoice_attachments/sah.pdf')->assertOk();
        $this->actingAs($finance)->get('/storage/avatars/foto.png')->assertOk();

        $logs = UserActivityLog::where('action', 'download')->where('user_id', $finance->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('file', $logs->first()->module);
        $this->assertSame('invoice_attachments', $logs->first()->notes);
    }

    /* ---------------------------------------------- D3 tambah role */

    public function test_superadmin_menambah_role_tanpa_menu(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/role', ['name' => 'Auditor_Internal'])
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('success');

        $role = Role::where('name', 'auditor_internal')->first();
        $this->assertNotNull($role);
        $this->assertSame('ACTIVE', $role->status);
        $this->assertSame(0, $role->menuItems()->count());
        $this->assertTrue(UserActivityLog::where('module', 'user_access')->where('subject_id', $role->id)->exists());
    }

    public function test_nama_role_bawaan_atau_dari_route_ditolak(): void
    {
        $admin = $this->userWithRole('superadmin');

        foreach (['guest', 'teknisi', 'cost_control'] as $nama) {
            $this->actingAs($admin)->post('/role', ['name' => $nama])->assertSessionHasErrors('name');
        }

        $this->assertFalse(Role::where('name', 'guest')->exists());
    }

    public function test_format_nama_role_divalidasi(): void
    {
        $admin = $this->userWithRole('superadmin');

        foreach (['ab', '1auditor', 'audit internal', 'audit-internal'] as $nama) {
            $this->actingAs($admin)->post('/role', ['name' => $nama])->assertSessionHasErrors('name');
        }
    }

    public function test_non_superadmin_tidak_bisa_menambah_role(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->post('/role', ['name' => 'auditor_baru'])
            ->assertForbidden();

        $this->assertFalse(Role::where('name', 'auditor_baru')->exists());
    }
}
