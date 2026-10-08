<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Workflow approval Purchase Order dua langkah + Early Start.
 *
 *   DRAFT -submit-> SUBMITTED -verify(Finance)-> VERIFIED -approve(Approver)->
 *       EARLY_START        (approver pilih eksplisit; PO scope-saja, budget-able)
 *     | WAITING_PO | ACTIVE (otomatis: ΣNo.PO == kontrak -> ACTIVE, selain itu WAITING_PO)
 *
 *   EARLY_START -edit-> DRAFT (dilengkapi lalu disubmit ulang)
 */
class ProjectEarlyStartWorkflowTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function po(array $attrs = []): Project
    {
        return $this->makeProject($attrs);
    }

    public function test_submit_lalu_verify_memindah_status(): void
    {
        $po = $this->po(['status' => 'DRAFT']);

        $this->actingAs($this->userWithRole('head'))
            ->post('/project/' . $po->id . '/submit')
            ->assertRedirect('/project');
        $this->assertSame('SUBMITTED', $po->fresh()->status);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/verify')
            ->assertRedirect('/project');
        $po->refresh();
        $this->assertSame('VERIFIED', $po->status);
        $this->assertNotNull($po->verified_at);
    }

    public function test_approve_otomatis_active_bila_no_po_pas_kontrak(): void
    {
        $po = $this->po(['status' => 'VERIFIED', 'contract' => 500_000_000, 'value' => 0]);
        $this->makePoNumber($po, ['nominal' => 500_000_000]); // Σ == kontrak

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'APPROVE'])
            ->assertRedirect('/project');

        $this->assertSame('ACTIVE', $po->fresh()->status);
    }

    public function test_approve_otomatis_waiting_po_bila_no_po_kurang_dari_kontrak(): void
    {
        $po = $this->po(['status' => 'VERIFIED', 'contract' => 1_000_000_000, 'value' => 0]);
        $this->makePoNumber($po, ['nominal' => 500_000_000]); // Σ < kontrak

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'APPROVE'])
            ->assertRedirect('/project');

        $this->assertSame('WAITING_PO', $po->fresh()->status);
    }

    public function test_approve_tanpa_no_po_jadi_waiting_po(): void
    {
        $po = $this->po(['status' => 'VERIFIED', 'contract' => 1_000_000_000, 'value' => 0]);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'APPROVE'])
            ->assertRedirect('/project');

        $this->assertSame('WAITING_PO', $po->fresh()->status);
    }

    public function test_approve_early_start(): void
    {
        $po = $this->po(['status' => 'VERIFIED', 'contract' => 0, 'value' => 0]);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'EARLY_START'])
            ->assertRedirect('/project');

        $po->refresh();
        $this->assertSame('EARLY_START', $po->status);
        $this->assertNotNull($po->approved_at);
    }

    public function test_approve_hanya_dari_verified(): void
    {
        $po = $this->po(['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'APPROVE'])
            ->assertRedirect(); // ditolak, status tidak valid
        $this->assertSame('SUBMITTED', $po->fresh()->status);
    }

    public function test_verify_hanya_dari_submitted(): void
    {
        $po = $this->po(['status' => 'DRAFT']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/verify');
        $this->assertSame('DRAFT', $po->fresh()->status);
    }

    public function test_edit_early_start_mengembalikan_ke_draft(): void
    {
        $po = $this->po(['status' => 'EARLY_START', 'contract' => 0, 'value' => 0]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/project/' . $po->id, [
                'name'        => 'SCOPE SUDAH LENGKAP',
                'customer_id' => $po->customer_id,
                'manager_id'  => $po->manager_id,
                'contract'    => '750.000.000',
            ])
            ->assertRedirect('/project');

        $po->refresh();
        $this->assertSame('DRAFT', $po->status);
        $this->assertSame('SCOPE SUDAH LENGKAP', $po->name);
        $this->assertSame(750_000_000, (int) $po->contract);
    }

    public function test_early_start_termasuk_status_budget_able(): void
    {
        $this->assertSame(['EARLY_START', 'WAITING_PO', 'ACTIVE'], Project::BUDGETABLE_STATUSES);
    }

    public function test_po_early_start_muncul_di_pilihan_budget_plan(): void
    {
        $early = $this->po(['status' => 'EARLY_START', 'name' => 'PO EARLY START XYZ']);
        $draft = $this->po(['status' => 'DRAFT', 'name' => 'PO DRAFT XYZ']);

        $res = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/budget-plan/create');

        $res->assertStatus(200);
        $res->assertSee('PO EARLY START XYZ');   // budget-able
        $res->assertDontSee('PO DRAFT XYZ');     // belum budget-able
    }
}
