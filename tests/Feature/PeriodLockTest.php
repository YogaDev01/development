<?php

namespace Tests\Feature;

use App\Exceptions\PeriodLockedException;
use App\Models\Closing;
use App\Models\UserActivityLog;
use App\Support\PeriodLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Tahap 0 kunci periode (docs: rancangan Planner 2026-11-13).
 * Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.
 */
class PeriodLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        PeriodLock::flush();
        config(['closing.enforce_from_period' => null]);
    }

    private function closing(string $period, string $status): Closing
    {
        $s = \Carbon\Carbon::createFromFormat('!Y-m', $period)->startOfMonth();

        return Closing::create([
            'code' => Closing::generateCode($period), 'period' => $period,
            'start_date' => $s, 'end_date' => $s->copy()->endOfMonth(), 'status' => $status,
        ]);
    }

    public function test_tanggal_di_periode_closed_diblokir_termasuk_batas_bulan(): void
    {
        $this->closing('2026-10', 'CLOSED');

        $this->assertTrue(PeriodLock::isLocked('2026-10-01'));
        $this->assertTrue(PeriodLock::isLocked('2026-10-31'));
        $this->assertFalse(PeriodLock::isLocked('2026-09-30'));
        $this->assertFalse(PeriodLock::isLocked('2026-11-01'));
        $this->assertStringContainsString('sudah ditutup', PeriodLock::blocker('2026-10-15', 'Invoice'));
    }

    public function test_draft_reopened_dan_tanpa_closing_terbuka(): void
    {
        $this->closing('2026-09', 'DRAFT');
        $this->closing('2026-10', 'REOPENED');

        $this->assertNull(PeriodLock::blocker('2026-09-10'));
        $this->assertNull(PeriodLock::blocker('2026-10-10'));
        $this->assertNull(PeriodLock::blocker('2026-08-10'));
        $this->assertNull(PeriodLock::blocker(null));
    }

    public function test_enforce_from_period_membiarkan_periode_lama(): void
    {
        $this->closing('2026-08', 'CLOSED');
        $this->closing('2026-10', 'CLOSED');
        config(['closing.enforce_from_period' => '2026-10']);
        PeriodLock::flush();

        $this->assertFalse(PeriodLock::isLocked('2026-08-15'));
        $this->assertTrue(PeriodLock::isLocked('2026-10-15'));
    }

    public function test_assert_open_melempar_exception(): void
    {
        $this->closing('2026-10', 'CLOSED');

        $this->expectException(PeriodLockedException::class);
        PeriodLock::assertOpen('2026-10-05', 'Batch Treasury');
    }

    public function test_non_superadmin_tidak_bisa_menembus_walau_isi_alasan(): void
    {
        $this->closing('2026-10', 'CLOSED');
        $this->actingAs($this->userWithRole('finance'));

        $req = Request::create('/x', 'POST', [PeriodLock::REASON_FIELD => 'koreksi']);

        $this->assertNotNull(PeriodLock::guard('2026-10-05', 'Invoice', $req));
    }

    public function test_superadmin_wajib_alasan_dan_penembusan_tercatat(): void
    {
        $this->closing('2026-10', 'CLOSED');
        $this->actingAs($this->userWithRole('superadmin'));

        $tanpa = Request::create('/x', 'POST');
        $this->assertStringContainsString('alasan', PeriodLock::guard('2026-10-05', 'Invoice', $tanpa));

        $dengan = Request::create('/x', 'POST', [PeriodLock::REASON_FIELD => 'koreksi tanggal salah input']);
        $this->assertNull(PeriodLock::guard('2026-10-05', 'Invoice', $dengan, null, ['Nominal' => ['1', '2']]));

        $this->assertDatabaseHas('user_activity_logs', ['module' => 'closing', 'action' => 'period_override']);
    }

    public function test_bulk_tanpa_request_tidak_bisa_menembus_bahkan_superadmin(): void
    {
        $this->closing('2026-10', 'CLOSED');
        $this->actingAs($this->userWithRole('superadmin'));

        $this->assertNotNull(PeriodLock::guard('2026-10-05', 'Invoice'));
    }

    public function test_superadmin_view_as_tidak_boleh_menembus(): void
    {
        $this->closing('2026-10', 'CLOSED');
        $user = $this->userWithRole('superadmin');
        $this->actingAs($user)->withSession(['view_as_role_id' => $this->role('finance')->id]);

        $this->assertFalse(PeriodLock::canOverride($user));
    }

    public function test_alasan_periode_bukan_string_diperlakukan_kosong(): void
    {
        $this->closing('2026-10', 'CLOSED');
        $this->actingAs($this->userWithRole('superadmin'));

        $req = Request::create('/x', 'POST', [PeriodLock::REASON_FIELD => ['bukan', 'string']]);

        $this->assertStringContainsString('alasan', PeriodLock::guard('2026-10-05', 'Invoice', $req));
    }
}
