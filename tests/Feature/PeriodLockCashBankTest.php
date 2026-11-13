<?php

namespace Tests\Feature;

use App\Models\CashAdjustment;
use App\Models\CashFlow;
use App\Models\CashFlowDetail;
use App\Models\CashOpeningBalance;
use App\Models\CashTransfer;
use App\Models\Closing;
use App\Models\SourceFund;
use App\Support\PeriodLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Kunci periode tahap 3: Cash & Bank + Cash Management (rancangan Planner 2026-11-13).
 * Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.
 */
class PeriodLockCashBankTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        PeriodLock::flush();
        config(['closing.enforce_from_period' => null]);
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-transfers', 'cash-adjustments', 'cash-management');

        $s = \Carbon\Carbon::createFromFormat('!Y-m', '2026-10')->startOfMonth();
        Closing::create([
            'code' => 'CLS/2026/10', 'period' => '2026-10', 'start_date' => $s,
            'end_date' => $s->copy()->endOfMonth(), 'status' => 'CLOSED',
        ]);
    }

    private function fund(string $name): SourceFund
    {
        $f = SourceFund::create(['name' => $name, 'status' => 'ACTIVE']);
        CashOpeningBalance::create([
            'source_fund_id' => $f->id, 'amount' => 20_000_000, 'effective_date' => '2026-09-01',
            'status' => 'APPROVED', 'created_by' => null,
        ]);

        return $f;
    }

    private function transfer(string $tgl, int $by): CashTransfer
    {
        return CashTransfer::create([
            'transfer_number' => 'TRF/UJI/' . uniqid(), 'transfer_date' => $tgl,
            'from_fund_id' => $this->fund('Asal ' . uniqid())->id, 'to_fund_id' => $this->fund('Tujuan ' . uniqid())->id,
            'amount' => 1_000_000, 'fee' => 0, 'status' => CashTransfer::PENDING, 'created_by' => $by,
        ]);
    }

    private function adjustment(string $tgl, int $by): CashAdjustment
    {
        return CashAdjustment::create([
            'adjustment_number' => 'ADJ/UJI/' . uniqid(), 'source_fund_id' => $this->fund('Rek ' . uniqid())->id,
            'effective_date' => $tgl, 'type' => 'INTEREST_INCOME', 'direction' => 'IN', 'amount' => 50_000,
            'reason' => 'bunga uji', 'status' => CashAdjustment::PENDING, 'created_by' => $by,
        ]);
    }

    private function cashFlow(string $tgl, string $status): CashFlow
    {
        $cf = CashFlow::create([
            'code' => 'CF-UJI-' . uniqid(), 'date' => $tgl, 'description' => 'uji', 'status' => $status,
            'total_in' => 100_000, 'total_out' => 0,
        ]);
        CashFlowDetail::create(['cash_flow_id' => $cf->id, 'direction' => 'IN', 'category' => 'OTHER', 'description' => 'uji', 'amount' => 100_000]);

        return $cf;
    }

    public function test_approve_transfer_di_periode_closed_ditolak_dan_di_periode_terbuka_lolos(): void
    {
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $tutup = $this->transfer('2026-10-15', $maker->id);
        $buka  = $this->transfer('2026-11-05', $maker->id);

        $this->actingAs($boss)->post("/cash-transfers/{$tutup->id}/approve")->assertSessionHas('error');
        $this->assertSame(CashTransfer::PENDING, $tutup->fresh()->status);

        $this->actingAs($boss)->post("/cash-transfers/{$buka->id}/approve")->assertSessionHas('success');
        $this->assertSame(CashTransfer::APPROVED, $buka->fresh()->status);
    }

    public function test_tolak_dan_batal_transfer_pending_tetap_boleh_di_periode_closed(): void
    {
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $a = $this->transfer('2026-10-15', $maker->id);
        $b = $this->transfer('2026-10-16', $maker->id);

        $this->actingAs($boss)->post("/cash-transfers/{$a->id}/reject", ['reason' => 'salah'])->assertSessionHas('success');
        $this->actingAs($maker)->post("/cash-transfers/{$b->id}/cancel")->assertSessionHas('success');
    }

    public function test_superadmin_approve_transfer_di_periode_closed_wajib_alasan(): void
    {
        $t     = $this->transfer('2026-10-15', $this->userWithRole('finance_manager')->id);
        $admin = $this->userWithRole('superadmin');

        $this->actingAs($admin)->post("/cash-transfers/{$t->id}/approve")->assertSessionHas('error');
        $this->assertSame(CashTransfer::PENDING, $t->fresh()->status);

        $this->actingAs($admin)->post("/cash-transfers/{$t->id}/approve", ['alasan_periode' => 'koreksi disetujui pemilik'])
            ->assertSessionHas('success');
        $this->assertSame(CashTransfer::APPROVED, $t->fresh()->status);
        $this->assertDatabaseHas('user_activity_logs', ['module' => 'closing', 'action' => 'period_override']);
    }

    public function test_approve_adjustment_di_periode_closed_ditolak(): void
    {
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $a = $this->adjustment('2026-10-20', $maker->id);

        $this->actingAs($boss)->post("/cash-adjustments/{$a->id}/approve")->assertSessionHas('error');
        $this->assertSame(CashAdjustment::PENDING, $a->fresh()->status);
    }

    public function test_post_cash_flow_di_periode_closed_ditolak(): void
    {
        $cf = $this->cashFlow('2026-10-10', 'DRAFT');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post("/cash-management/{$cf->id}/post")
            ->assertSessionHas('error');

        $this->assertSame('DRAFT', $cf->fresh()->status);
    }

    public function test_batal_cash_flow_posted_di_periode_closed_ditolak_tapi_draft_boleh(): void
    {
        $user   = $this->userWithRole('finance_manager');
        $posted = $this->cashFlow('2026-10-10', 'POSTED');
        $draft  = $this->cashFlow('2026-10-11', 'DRAFT');

        $this->actingAs($user)->post("/cash-management/{$posted->id}/cancel", ['cancel_reason' => 'salah'])
            ->assertSessionHas('error');
        $this->assertSame('POSTED', $posted->fresh()->status);

        $this->actingAs($user)->post("/cash-management/{$draft->id}/cancel", ['cancel_reason' => 'salah'])
            ->assertSessionHas('success');
        $this->assertSame('CANCELLED', $draft->fresh()->status);
    }
}
