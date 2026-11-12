<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetPlanDetail;
use App\Models\RequestDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Edit Budget Plan oleh Superadmin di luar Draft (2026-11-12): sub-item
 * di-upsert per id supaya tautan Cost Request tidak putus; sub-item yang
 * sudah dipakai tidak boleh dihapus.
 */
class BudgetPlanSuperEditTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function activePlanDenganSubItemTerpakai(): array
    {
        $plan = $this->makeBudgetPlan(null, ['status' => 'ACTIVE']);
        $akun = Account::create(['name' => 'Akun Uji ' . uniqid(), 'category' => 'Variable Cost', 'status' => 'ACTIVE']);

        $dipakai = $plan->details()->create([
            'account_id' => $akun->id, 'category' => 'Variable Cost', 'type' => 'main',
            'deskripsi' => 'Terpakai', 'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
        ]);
        $bebas = $plan->details()->create([
            'account_id' => $akun->id, 'category' => 'Variable Cost', 'type' => 'main',
            'deskripsi' => 'Bebas', 'amount' => 500_000, 'qty' => 1, 'total' => 500_000,
        ]);

        $cr = $this->makeCostRequest(['budget_plan' => $plan, 'status' => 'APPROVED']);
        RequestDetail::create([
            'budget_request_id' => $cr->id,
            'budget_detail_id'  => $dipakai->id,
            'account_id'        => $akun->id,
            'description'       => 'Rincian CR uji',
            'amount'            => 1_000_000,
            'qty'               => 1,
            'total'             => 1_000_000,
        ]);

        return [$plan, $dipakai, $bebas, $akun];
    }

    private function baris(BudgetPlanDetail $d, int $amount): array
    {
        return ['id' => $d->id, 'account_id' => $d->account_id, 'deskripsi' => $d->deskripsi, 'amount' => (string) $amount, 'qty' => 1];
    }

    public function test_ubah_nominal_mempertahankan_id_sub_item_dan_tautan_cr(): void
    {
        [$plan, $dipakai, $bebas] = $this->activePlanDenganSubItemTerpakai();

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/budget-plan/' . $plan->id, [
                'project_id'  => $plan->project_id,
                'description' => 'Budget uji diedit',
                'details'     => [$this->baris($dipakai, 1_500_000), $this->baris($bebas, 500_000)],
            ])
            ->assertSessionMissing('error');

        $this->assertSame(1_500_000, (int) $dipakai->fresh()->amount);
        $this->assertSame(1, RequestDetail::where('budget_detail_id', $dipakai->id)->count());
        $this->assertTrue($plan->logs()->where('action', 'superadmin_edited')->exists());
    }

    public function test_menghapus_sub_item_terpakai_ditolak(): void
    {
        [$plan, $dipakai, $bebas] = $this->activePlanDenganSubItemTerpakai();

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/budget-plan/' . $plan->id, [
                'project_id'  => $plan->project_id,
                'description' => 'Budget uji diedit',
                'details'     => [$this->baris($bebas, 500_000)],   // sub-item terpakai dibuang
            ])
            ->assertSessionHas('error');

        $this->assertNotNull($dipakai->fresh());
        $this->assertSame(1, RequestDetail::where('budget_detail_id', $dipakai->id)->count());
    }

    public function test_manager_tetap_tidak_bisa_edit_budget_plan_active(): void
    {
        [$plan, $dipakai] = $this->activePlanDenganSubItemTerpakai();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->put('/budget-plan/' . $plan->id, [
                'project_id'  => $plan->project_id,
                'description' => 'Coba ubah',
                'details'     => [$this->baris($dipakai, 9_000_000)],
            ]);

        $this->assertSame(1_000_000, (int) $dipakai->fresh()->amount);
    }
}
