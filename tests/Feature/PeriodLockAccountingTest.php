<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Closing;
use App\Support\PeriodLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Kunci periode tahap 5: saling mengacu Closing <-> AccountingPeriod (rancangan Planner 2026-11-13).
 * Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.
 */
class PeriodLockAccountingTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        PeriodLock::flush();
        config(['closing.enforce_from_period' => null]);
        $this->giveMenuAccess($this->role('accounting'), 'accounting-period');
    }

    private function closing(string $period, string $status): Closing
    {
        $s = \Carbon\Carbon::createFromFormat('!Y-m', $period)->startOfMonth();

        return Closing::create([
            'code' => Closing::generateCode($period), 'period' => $period, 'start_date' => $s,
            'end_date' => $s->copy()->endOfMonth(), 'status' => $status,
        ]);
    }

    private function accounting(string $status = 'OPEN'): AccountingPeriod
    {
        return AccountingPeriod::create([
            'name' => 'Okt 2026', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => $status,
        ]);
    }

    public function test_tutup_periode_akuntansi_ditolak_bila_closing_operasional_belum_ditutup(): void
    {
        $p = $this->accounting();
        $this->closing('2026-10', 'DRAFT');

        $this->actingAs($this->userWithRole('accounting'))
            ->post("/accounting/period/{$p->id}/close")
            ->assertSessionHas('error');

        $this->assertSame('OPEN', $p->fresh()->status);
    }

    public function test_tutup_periode_akuntansi_lolos_bila_closing_sudah_closed(): void
    {
        $p = $this->accounting();
        $this->closing('2026-10', 'CLOSED');

        $this->actingAs($this->userWithRole('accounting'))
            ->post("/accounting/period/{$p->id}/close")
            ->assertSessionHas('success');

        $this->assertSame('CLOSED', $p->fresh()->status);
    }

    public function test_bulan_sebelum_enforce_from_period_dibebaskan(): void
    {
        config(['closing.enforce_from_period' => '2026-11']);

        $this->assertSame([], PeriodLock::unclosedMonths('2026-10-01', '2026-10-31'));
        $this->assertSame(['2026-11'], PeriodLock::unclosedMonths('2026-10-01', '2026-11-30'));
    }

    public function test_buka_closing_ditolak_bila_periode_akuntansi_closed_atau_locked(): void
    {
        $c = $this->closing('2026-10', 'CLOSED');
        $this->accounting('CLOSED');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post("/closing/{$c->id}/reopen", ['reopen_reason' => 'koreksi'])
            ->assertSessionHas('error');

        $this->assertSame('CLOSED', $c->fresh()->status);
    }

    public function test_buka_closing_lolos_bila_periode_akuntansi_masih_open(): void
    {
        $c = $this->closing('2026-10', 'CLOSED');
        $this->accounting('OPEN');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post("/closing/{$c->id}/reopen", ['reopen_reason' => 'koreksi'])
            ->assertSessionHas('success');

        $this->assertSame('REOPENED', $c->fresh()->status);
    }
}
