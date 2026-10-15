<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * "Used" Budget Plan dan pengecekan NOB memakai effective cost, bukan nilai
 * pengajuan:
 *   - Cash Advance yang settlement-nya sudah diajukan -> nilai settlement,
 *     TERMASUK Settle 0 (dana dikembalikan utuh, tidak memakan budget);
 *   - CA yang belum settle, atau tipe lain -> nilai pengajuan.
 */
class EffectiveCostBudgetUsageTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache id tipe CA bersifat statis per proses; id request_type bisa
        // berbeda antar test, jadi dikosongkan supaya tidak basi.
        (new \ReflectionProperty(BudgetRequest::class, 'cashAdvanceTypeIdsCache'))->setValue(null, null);
    }

    private function addSettlement(BudgetRequest $cr, int $total): void
    {
        BudgetRequestRealization::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Realisasi uji',
            'amount'            => $total,
            'qty'               => 1,
            'total'             => $total,
        ]);
    }

    private function cashAdvance($plan, int $amount, string $status = 'CLOSED'): BudgetRequest
    {
        return $this->makeCostRequest([
            'budget_plan' => $plan,
            'type_id'     => $this->makeRequestType('Cash Advance')->id,
            'amount'      => $amount,
            'status'      => $status,
        ]);
    }

    public function test_ca_dengan_settle_nol_tidak_memakan_budget(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);
        $cr = $this->cashAdvance($plan, 3_500_000, 'REALIZED');
        $this->addSettlement($cr, 0);

        $this->assertSame(0, $plan->fresh()->usedAmount());
        $this->assertSame(0, BudgetRequest::findOrFail($cr->id)->realizedCostValue());
    }

    public function test_used_memakai_effective_cost_per_jenis(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 50_000_000]);

        // CA settle lebih kecil -> 1.200.000
        $this->addSettlement($this->cashAdvance($plan, 2_000_000), 1_200_000);
        // CA expense claim (settle > request) -> 2.500.000
        $this->addSettlement($this->cashAdvance($plan, 2_000_000), 2_500_000);
        // CA belum settle -> nilai request 1.000.000
        $this->cashAdvance($plan, 1_000_000, 'PAID');
        // Non-CA -> nilai request 3.000.000
        $this->makeCostRequest([
            'budget_plan' => $plan,
            'type_id'     => $this->makeRequestType('Cost Payment')->id,
            'amount'      => 3_000_000,
            'status'      => 'CLOSED',
        ]);

        $this->assertSame(7_700_000, $plan->fresh()->usedAmount());
    }

    public function test_nob_tidak_menghitung_request_ca_yang_settle_nol(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 5_000_000]);
        $this->addSettlement($this->cashAdvance($plan, 3_500_000), 0);

        // Dengan nilai request, 3,5 jt + 3 jt > 5 jt (NOB). Dengan effective
        // cost (0), pengajuan 3 jt masih di dalam anggaran.
        $response = $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-request/check-nob', [
                'budget_plan_id' => $plan->id,
                'amount'         => 3_000_000,
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('is_nob'));
    }

    public function test_nob_menghitung_expense_claim_ca(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 5_000_000]);
        $this->addSettlement($this->cashAdvance($plan, 2_000_000), 4_000_000);

        // Effective 4 jt + 2 jt > 5 jt -> NOB (dengan nilai request tidak).
        $response = $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-request/check-nob', [
                'budget_plan_id' => $plan->id,
                'amount'         => 2_000_000,
            ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('is_nob'));
    }
}
