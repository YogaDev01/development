<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Project::budgetPlanTotal() menjumlahkan Budget Plan berstatus ACTIVE dan
 * sesudahnya (CLOSING, CLOSED); status sebelum ACTIVE / REJECTED tidak dihitung.
 */
class ProjectBudgetPlanTotalTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    public function test_budget_plan_active_closing_closed_dihitung(): void
    {
        $po = $this->makeProject();

        foreach (['ACTIVE' => 1_000, 'CLOSING' => 2_000, 'CLOSED' => 4_000,
                  'DRAFT' => 8_000, 'SUBMITTED' => 16_000, 'REJECTED' => 32_000] as $status => $amount) {
            $this->makeBudgetPlan($po, ['status' => $status, 'amount' => $amount]);
        }

        $this->assertSame(7_000, $po->fresh()->budgetPlanTotal());
    }
}
