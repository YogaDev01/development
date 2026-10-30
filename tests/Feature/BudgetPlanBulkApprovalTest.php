<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Bulk approve/reject Budget Plan: tiap Budget Plan diproses method approval
 * satuan, jadi role, status, dan log identik dengan aksi satuan.
 */
class BudgetPlanBulkApprovalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_head_bulk_approve_mengubah_submitted_menjadi_checked(): void
    {
        $a = $this->makeBudgetPlan(null, ['status' => 'SUBMITTED']);
        $b = $this->makeBudgetPlan(null, ['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('head'))
            ->post('/budget-plan/bulk-approve', ['ids' => [$a->id, $b->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('success');

        $this->assertSame('CHECKED', $a->refresh()->status);
        $this->assertSame('CHECKED', $b->refresh()->status);
        $this->assertSame(1, $a->logs()->where('status_from', 'SUBMITTED')->where('status_to', 'CHECKED')->count());
    }

    public function test_budget_plan_yang_statusnya_berubah_dilewati(): void
    {
        $a = $this->makeBudgetPlan(null, ['status' => 'SUBMITTED']);
        $b = $this->makeBudgetPlan(null, ['status' => 'CHECKED']);

        $this->actingAs($this->userWithRole('head'))
            ->post('/budget-plan/bulk-approve', ['ids' => [$a->id, $b->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('success')->assertSessionHas('error');

        $this->assertSame('CHECKED', $a->refresh()->status);
        $this->assertSame('CHECKED', $b->refresh()->status); // tidak diproses lagi
        $this->assertSame(0, $b->logs()->where('status_to', 'VERIFIED')->count());
    }

    public function test_role_tanpa_wewenang_tahap_itu_dilewati(): void
    {
        $a = $this->makeBudgetPlan(null, ['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/budget-plan/bulk-approve', ['ids' => [$a->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('error');

        $this->assertSame('SUBMITTED', $a->refresh()->status);
    }

    public function test_bulk_reject_wajib_catatan_dan_mengubah_ke_rejected(): void
    {
        $a = $this->makeBudgetPlan(null, ['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('head'))
            ->post('/budget-plan/bulk-reject', ['ids' => [$a->id], 'status' => 'SUBMITTED'])
            ->assertSessionHasErrors('notes');
        $this->assertSame('SUBMITTED', $a->refresh()->status);

        $this->post('/budget-plan/bulk-reject', ['ids' => [$a->id], 'status' => 'SUBMITTED', 'notes' => 'Revisi'])
            ->assertSessionHas('success');
        $this->assertSame('REJECTED', $a->refresh()->status);
    }

    public function test_maksimal_50_dan_status_harus_tahap_approval(): void
    {
        $head = $this->userWithRole('head');

        $this->actingAs($head)
            ->post('/budget-plan/bulk-approve', ['ids' => range(1, 51), 'status' => 'SUBMITTED'])
            ->assertSessionHasErrors('ids');

        $this->post('/budget-plan/bulk-approve', ['ids' => [1], 'status' => 'ACTIVE'])
            ->assertSessionHasErrors('status');
    }

    public function test_role_lain_tidak_boleh_mengakses_endpoint_bulk(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->post('/budget-plan/bulk-approve', ['ids' => [1], 'status' => 'SUBMITTED'])
            ->assertForbidden();
    }
}
