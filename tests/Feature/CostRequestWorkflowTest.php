<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Karakterisasi alur approval Cost Request + penjagaan perbaikan BIZ-03.
 *
 * Yang dijaga: peran yang berhak per tahap, status awal yang wajib, dan —
 * yang baru — bahwa approval kedua atas CR yang sudah berpindah status tidak
 * menulis apa-apa lagi (dulu bisa, dan meninggalkan log serta notifikasi
 * ganda).
 */
class CostRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private \App\Models\Project $project;

    private function cr(string $status = 'SUBMITTED'): BudgetRequest
    {
        $this->project = $this->makeProject();
        $plan = $this->makeBudgetPlan($this->project);

        return $this->makeCostRequest(['budget_plan' => $plan, 'status' => $status]);
    }

    /**
     * Head di aplikasi ini dibatasi Access Group (lihat BuildsCrWorklist::
     * scopeByRole) - persis seperti Manager. Jadi Head untuk uji approval
     * harus diberi akses ke proyeknya, sama seperti di produksi.
     */
    private function headUser(): \App\Models\User
    {
        return $this->giveProjectAccess($this->userWithRole('head'), $this->project);
    }

    public function test_head_menyetujui_cr_submitted(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok'])
            ->assertRedirect();

        $this->assertSame('CHECKED', $cr->fresh()->status);
        $this->assertDatabaseHas('request_status_logs', [
            'budget_request_id' => $cr->id,
            'status_to'         => 'CHECKED',
        ]);
    }

    public function test_finance_tidak_bisa_menyetujui_tahap_head(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->userWithRole('finance'))
            ->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok'])
            ->assertForbidden();

        $this->assertSame('SUBMITTED', $cr->fresh()->status);
    }

    public function test_approval_kedua_tidak_menulis_ulang(): void
    {
        $cr   = $this->cr('SUBMITTED');
        $head = $this->headUser();

        $this->actingAs($head)->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok']);
        $this->actingAs($head)->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok']);

        $this->assertSame('CHECKED', $cr->fresh()->status);
        $this->assertSame(
            1,
            \DB::table('request_status_logs')
                ->where('budget_request_id', $cr->id)
                ->where('status_to', 'CHECKED')
                ->count(),
            'Klik approve kedua tidak boleh menambah log baru.'
        );
    }

    public function test_urutan_tahap_tidak_bisa_dilompati(): void
    {
        // CEO tidak bisa approve CR yang belum diverifikasi Finance.
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->userWithRole('approver'))
            ->post('/budget-request/' . $cr->id . '/ceo-approve', ['notes' => 'ok'])
            ->assertRedirect();

        $this->assertSame('SUBMITTED', $cr->fresh()->status);
    }

    public function test_alur_penuh_submitted_sampai_approved(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok']);
        $this->assertSame('CHECKED', $cr->fresh()->status);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/budget-request/' . $cr->id . '/finance-verify', ['notes' => 'ok']);
        $this->assertSame('VERIFIED', $cr->fresh()->status);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/budget-request/' . $cr->id . '/ceo-approve', ['notes' => 'ok']);
        $this->assertSame('APPROVED', $cr->fresh()->status);

        // Setiap tahap meninggalkan jejak — audit trail adalah bagian dari
        // alurnya, bukan tambahan opsional.
        $this->assertSame(3, \DB::table('request_status_logs')->where('budget_request_id', $cr->id)->count());
    }
}
