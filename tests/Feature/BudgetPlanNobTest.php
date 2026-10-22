<?php

namespace Tests\Feature;

use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Rumus NOB kini satu sumber di model BudgetPlan (dulu private di
 * BudgetRequestController): dipakai form Cost Request dan pembentukan CR dari
 * pengajuan Trouble Ticket. Rumus dan hasilnya TIDAK berubah dari versi lama.
 */
class BudgetPlanNobTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function plan(int $pagu = 1_000_000): BudgetPlan
    {
        return $this->makeBudgetPlan(null, ['amount' => $pagu]);
    }

    public function test_cr_belum_terikat_tidak_menghabiskan_pagu_tetapi_status_committed_ya(): void
    {
        $plan = $this->plan();

        // SUBMITTED sudah mengikat (BudgetConsumption::COMMITTED); DRAFT belum.
        $this->makeCostRequest(['budget_plan' => $plan, 'status' => 'DRAFT', 'amount' => 900_000]);
        $this->assertFalse($plan->isNob(500_000));
        $this->assertSame(0, $plan->nobOverage(500_000));

        $this->makeCostRequest(['budget_plan' => $plan, 'status' => 'CHECKED', 'amount' => 900_000]);
        $this->assertTrue($plan->isNob(200_000));
        $this->assertSame(100_000, $plan->nobOverage(200_000));
        // Tepat sebesar sisa pagu: belum NOB.
        $this->assertFalse($plan->isNob(100_000));
        $this->assertSame(0, $plan->nobOverage(100_000));
    }

    public function test_cr_yang_sedang_diubah_dikecualikan_dari_pemakaian(): void
    {
        $plan = $this->plan();
        $cr = $this->makeCostRequest(['budget_plan' => $plan, 'status' => 'SUBMITTED', 'amount' => 900_000]);

        $this->assertTrue($plan->isNob(200_000));
        $this->assertFalse($plan->isNob(200_000, $cr->id), 'edit CR itu sendiri tidak menghitung dirinya dua kali');
        $this->assertSame(0, $plan->nobOverage(1_000_000, $cr->id));
    }

    public function test_sub_item_harus_milik_budget_plan_yang_dipilih(): void
    {
        $plan = $this->plan();
        $lain = $this->plan();
        $milik = BudgetPlanDetail::create(['budget_plan_id' => $plan->id, 'deskripsi' => 'A', 'amount' => 1, 'qty' => 1, 'total' => 1]);
        $asing = BudgetPlanDetail::create(['budget_plan_id' => $lain->id, 'deskripsi' => 'B', 'amount' => 1, 'qty' => 1, 'total' => 1]);

        $this->assertTrue($plan->ownsDetails([$milik->id]));
        $this->assertTrue($plan->ownsDetails([]), 'daftar kosong dianggap sah (perilaku lama)');
        $this->assertTrue($plan->ownsDetails([null, '']), 'nilai kosong disaring');
        $this->assertFalse($plan->ownsDetails([$milik->id, $asing->id]));
    }
}
