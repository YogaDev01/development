<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Audit trail UserActivityLog.
 *
 * Fokus: actor selalu dari authenticated user (bukan input), referensi objek,
 * notes opsional, filter + paginasi server-side, hook Cost Request, dan
 * imutabilitas (tidak ada route CRUD log).
 */
class UserActivityLogTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    public function test_record_mengambil_actor_dari_user_terautentikasi(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        $log = UserActivityLog::record('profile', 'update', 'Memperbarui informasi profil');

        $this->assertNotNull($log);
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => 'finance',
            'module' => 'profile',
            'action' => 'update',
            'description' => 'Memperbarui informasi profil',
        ]);
    }

    public function test_record_menyimpan_referensi_objek_dan_notes(): void
    {
        $this->actingAs($this->userWithRole('finance'));

        $cr = $this->makeCostRequest();

        $log = UserActivityLog::record(
            'cost_request',
            'reject',
            'Menolak Cost Request #'.$cr->code,
            $cr,
            'Dokumen pendukung tidak lengkap'
        );

        $this->assertSame($cr->code, $log->reference);
        $this->assertSame(BudgetRequest::class, $log->subject_type);
        $this->assertSame($cr->id, $log->subject_id);
        $this->assertSame('Dokumen pendukung tidak lengkap', $log->notes);
    }

    public function test_notes_boleh_kosong(): void
    {
        $this->actingAs($this->userWithRole('finance'));

        $log = UserActivityLog::record('auth', 'login', 'Login ke aplikasi');

        $this->assertNull($log->notes);
    }

    public function test_feed_memfilter_dan_mengurutkan_terbaru_dulu(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        UserActivityLog::record('auth', 'login', 'Login ke aplikasi');
        UserActivityLog::record('cost_request', 'approve', 'Menyetujui CR-001');
        UserActivityLog::record('treasury', 'pay', 'Membayar batch BT-001');

        // Semua aktivitas
        $all = UserActivityLog::feedFor($user->id);
        $this->assertSame(3, $all->total());
        // Terbaru dulu
        $this->assertSame('treasury', $all->first()->module);

        // Filter module
        $filtered = UserActivityLog::feedFor($user->id, ['module' => 'cost_request']);
        $this->assertSame(1, $filtered->total());
        $this->assertSame('approve', $filtered->first()->action);

        // Filter keyword
        $byKeyword = UserActivityLog::feedFor($user->id, ['q' => 'batch']);
        $this->assertSame(1, $byKeyword->total());
    }

    public function test_feed_hanya_menampilkan_log_user_yang_diminta(): void
    {
        $a = $this->userWithRole('finance');
        $b = $this->userWithRole('manager');

        $this->actingAs($a);
        UserActivityLog::record('auth', 'login', 'Login ke aplikasi');

        $this->actingAs($b);
        UserActivityLog::record('auth', 'login', 'Login ke aplikasi');

        $this->assertSame(1, UserActivityLog::feedFor($a->id)->total());
        $this->assertSame(1, UserActivityLog::feedFor($b->id)->total());
    }

    public function test_transisi_cost_request_otomatis_tercatat(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        $cr = $this->makeCostRequest();
        $cr->addLog('DRAFT', 'SUBMITTED', 'submitted', 'Diajukan');

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'module' => 'cost_request',
            'action' => 'submit',
            'subject_type' => BudgetRequest::class,
            'subject_id' => $cr->id,
            'reference' => $cr->code,
        ]);
    }

    public function test_halaman_profil_menampilkan_tab_activity_log(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        UserActivityLog::record('profile', 'update', 'Memperbarui informasi profil');

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Activity Log')
            ->assertSee('Memperbarui informasi profil');
    }

    public function test_logout_mencatat_aktivitas_logout(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        $this->post('/logout');

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'logout',
        ]);
    }

    public function test_changeset_membangun_rincian_perubahan_nilai_terformat(): void
    {
        $this->actingAs($this->userWithRole('finance'));

        $cr = $this->makeCostRequest(['amount' => 12_000_000]);
        $before = $cr->getOriginal();
        $cr->update(['amount' => 13_000_000]);

        $changes = UserActivityLog::changeSet($before, $cr, [
            'amount' => ['label' => 'Nilai', 'money' => true],
        ]);

        $this->assertCount(1, $changes);
        $this->assertSame('Nilai', $changes[0]['label']);
        $this->assertSame('12.000.000', $changes[0]['from']);
        $this->assertSame('13.000.000', $changes[0]['to']);
    }

    public function test_record_menyimpan_changes_sebagai_json(): void
    {
        $this->actingAs($this->userWithRole('finance'));

        $log = UserActivityLog::record(
            'purchase_order',
            'update',
            'Menyunting Purchase Order #PO-1',
            null,
            null,
            null,
            [['label' => 'Nilai Kontrak', 'from' => '12.000.000', 'to' => '13.000.000']]
        );

        $this->assertIsArray($log->changes);
        $this->assertSame('Nilai Kontrak', $log->changes[0]['label']);
        $this->assertSame('13.000.000', $log->changes[0]['to']);
    }

    public function test_rincian_perubahan_adalah_credential_digate_canSeeChanges(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        UserActivityLog::record(
            'purchase_order',
            'update',
            'Menyunting Purchase Order #PO-9',
            null,
            null,
            null,
            [['label' => 'Nilai Kontrak', 'from' => '12.000.000', 'to' => '13.000.000']]
        );

        $logs = UserActivityLog::feedFor($user->id);
        $data = ['activityLogs' => $logs, 'activityFilters' => [], 'canSeeChanges' => true];

        // Owner/superadmin: rincian nilai tampak.
        $boleh = view('profile._activity_log', $data)->render();
        $this->assertStringContainsString('Nilai Kontrak', $boleh);
        $this->assertStringContainsString('13.000.000', $boleh);

        // Pengunjung lain (canSeeChanges=false): rincian nilai disembunyikan,
        // deskripsi tetap terlihat.
        $data['canSeeChanges'] = false;
        $tidak = view('profile._activity_log', $data)->render();
        $this->assertStringContainsString('Purchase Order #PO-9', $tidak);
        $this->assertStringNotContainsString('13.000.000', $tidak);
    }

    public function test_profil_sendiri_menampilkan_rincian_perubahan(): void
    {
        $user = $this->userWithRole('finance');
        $this->actingAs($user);

        UserActivityLog::record(
            'purchase_order',
            'update',
            'Menyunting Purchase Order #PO-7',
            null,
            null,
            null,
            [['label' => 'Nilai Kontrak', 'from' => '12.000.000', 'to' => '13.000.000']]
        );

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Nilai Kontrak')
            ->assertSee('13.000.000');
    }
}
