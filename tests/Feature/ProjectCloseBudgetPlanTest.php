<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * PO CLOSED → Budget Plan ACTIVE/CLOSING miliknya otomatis CLOSED;
 * status lain (mis. DRAFT, REJECTED) dan Budget Plan PO lain tidak disentuh.
 */
class ProjectCloseBudgetPlanTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function statusBudget(array $plans): array
    {
        return collect($plans)->map(fn ($bp) => $bp->fresh()->status)->all();
    }

    public function test_finance_close_menutup_budget_active_dan_closing(): void
    {
        $po    = $this->makeProject(['status' => 'PAID']);
        $other = $this->makeProject(['status' => 'ACTIVE']);

        $active  = $this->makeBudgetPlan($po, ['status' => 'ACTIVE']);
        $closing = $this->makeBudgetPlan($po, ['status' => 'CLOSING']);
        $draft   = $this->makeBudgetPlan($po, ['status' => 'DRAFT']);
        $lain    = $this->makeBudgetPlan($other, ['status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/finance-close', ['notes' => 'Selesai'])
            ->assertRedirect('/project');

        $this->assertSame('CLOSED', $po->fresh()->status);
        $this->assertSame(['CLOSED', 'CLOSED', 'DRAFT', 'ACTIVE'],
            $this->statusBudget([$active, $closing, $draft, $lain]));
    }

    public function test_verify_closing_menutup_budget_active_dan_closing(): void
    {
        $po = $this->makeProject(['status' => 'CLOSING']);

        $active  = $this->makeBudgetPlan($po, ['status' => 'ACTIVE']);
        $closing = $this->makeBudgetPlan($po, ['status' => 'CLOSING']);
        $reject  = $this->makeBudgetPlan($po, ['status' => 'REJECTED']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/verify-closing')
            ->assertRedirect();

        $this->assertSame('CLOSED', $po->fresh()->status);
        $this->assertSame(['CLOSED', 'CLOSED', 'REJECTED'],
            $this->statusBudget([$active, $closing, $reject]));
    }
}
