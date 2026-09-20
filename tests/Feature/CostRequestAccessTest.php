<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan SEC-05 (IDOR).
 *
 * Daftar Cost Request selalu tersaring lewat scopeByRole(). Yang dulu tidak
 * ikut tersaring adalah URL ber-{id}: manager mana pun bisa membuka
 * /budget-request/{id}/edit milik proyek orang lain hanya dengan mengetik
 * id-nya. Uji ini memastikan aturan daftar dan aturan halaman detail tetap
 * satu dan sama.
 */
class CostRequestAccessTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_manager_tidak_bisa_membuka_cr_manager_lain(): void
    {
        $pemilik = $this->userWithRole('manager');
        $plan    = $this->makeBudgetPlan($this->makeProject(['manager_id' => $pemilik->id]));
        $cr      = $this->makeCostRequest(['budget_plan' => $plan, 'applicant_id' => $pemilik->id]);

        $penyusup = $this->userWithRole('manager');

        $this->actingAs($penyusup)
            ->get('/budget-request/' . $cr->id . '/edit')
            ->assertForbidden();
    }

    public function test_manager_bisa_membuka_cr_miliknya_sendiri(): void
    {
        $pemilik = $this->userWithRole('manager');
        $plan    = $this->makeBudgetPlan($this->makeProject(['manager_id' => $pemilik->id]));
        $cr      = $this->makeCostRequest(['budget_plan' => $plan, 'applicant_id' => $pemilik->id]);

        $this->actingAs($pemilik)
            ->get('/budget-request/' . $cr->id . '/edit')
            ->assertOk();
    }

    public function test_detail_json_juga_tersaring(): void
    {
        $pemilik = $this->userWithRole('manager');
        $plan    = $this->makeBudgetPlan($this->makeProject(['manager_id' => $pemilik->id]));
        $cr      = $this->makeCostRequest(['budget_plan' => $plan, 'applicant_id' => $pemilik->id]);

        $this->actingAs($this->userWithRole('manager'))
            ->get('/budget-request/' . $cr->id . '/detail-json')
            ->assertForbidden();
    }

    public function test_superadmin_tetap_bisa_membuka_semua(): void
    {
        $pemilik = $this->userWithRole('manager');
        $plan    = $this->makeBudgetPlan($this->makeProject(['manager_id' => $pemilik->id]));
        $cr      = $this->makeCostRequest(['budget_plan' => $plan, 'applicant_id' => $pemilik->id]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/budget-request/' . $cr->id . '/detail-json')
            ->assertOk();
    }
}
