<?php

namespace Tests\Feature;

use App\Models\BudgetPlanLogNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/** Tab Log Budget Plan: catatan gaya chat (tabel budget_plan_log_notes). */
class BudgetPlanLogNoteTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_catatan_tersimpan_dan_muncul_di_detail_json(): void
    {
        $bp = $this->makeBudgetPlan();

        $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-plan/' . $bp->id . '/log-note', ['notes' => 'Menunggu revisi PO'])
            ->assertOk()->assertJsonPath('log.notes', 'Menunggu revisi PO');

        $this->assertSame(1, BudgetPlanLogNote::where('budget_plan_id', $bp->id)->count());

        $logs = $this->getJson('/budget-plan/' . $bp->id . '/detail-json')->assertOk()->json('logs');
        $this->assertContains('note', array_column($logs, 'kind'));
    }

    public function test_catatan_ditolak_untuk_budget_plan_closed(): void
    {
        $bp = $this->makeBudgetPlan(null, ['status' => 'CLOSED']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-plan/' . $bp->id . '/log-note', ['notes' => 'Terlambat'])
            ->assertStatus(422);

        $this->assertSame(0, BudgetPlanLogNote::count());
    }

    public function test_catatan_wajib_diisi(): void
    {
        $bp = $this->makeBudgetPlan();

        $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-plan/' . $bp->id . '/log-note', ['notes' => ''])
            ->assertStatus(422);
    }
}
