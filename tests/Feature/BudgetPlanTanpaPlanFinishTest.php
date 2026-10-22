<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Plan Finish dan auto-closing berbasis tanggal dihapus untuk SEMUA Budget Plan:
 * form create/update tidak lagi meminta plan_finish, kolom lama dibiarkan, dan
 * status BP tidak berpindah otomatis karena tanggal.
 */
class BudgetPlanTanpaPlanFinishTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function payload(int $projectId): array
    {
        $akun = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);

        return [
            'source_type' => 'PROJECT',
            'project_id'  => $projectId,
            'description' => 'budget biasa',
            'details'     => [
                ['account_id' => $akun->id, 'deskripsi' => 'Material', 'amount' => '1.000.000', 'qty' => '1', 'unit' => 'pcs'],
            ],
        ];
    }

    public function test_store_bp_biasa_tanpa_plan_finish_berhasil(): void
    {
        $po = $this->makeProject();

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan', $this->payload($po->id))
            ->assertRedirect('/budget-plan')
            ->assertSessionDoesntHaveErrors();

        $bp = BudgetPlan::firstOrFail();
        $this->assertSame('DRAFT', $bp->status);
        $this->assertFalse($bp->isBundled());
        $this->assertNull($bp->plan_finish);
        $this->assertSame(1_000_000, (int) $bp->amount);
    }

    public function test_update_bp_biasa_tanpa_plan_finish_berhasil_dan_nilai_lama_tidak_ditimpa(): void
    {
        $po = $this->makeProject();
        $bp = $this->makeBudgetPlan($po, ['status' => 'DRAFT', 'plan_finish' => '2026-12-31']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/budget-plan/' . $bp->id, $this->payload($po->id))
            ->assertRedirect('/budget-plan')
            ->assertSessionDoesntHaveErrors();

        $bp->refresh();
        $this->assertSame('BUDGET BIASA', $bp->description);
        $this->assertSame('2026-12-31', $bp->plan_finish->toDateString(), 'plan_finish lama dibiarkan');
    }

    public function test_form_create_dan_edit_bp_tidak_memuat_field_plan_finish(): void
    {
        $po = $this->makeProject();
        $bp = $this->makeBudgetPlan($po, ['status' => 'DRAFT']);
        $admin = $this->userWithRole('superadmin');

        foreach (['/budget-plan/create', '/budget-plan/' . $bp->id . '/edit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertDontSee('name="plan_finish"', false)
                ->assertDontSee('Plan Finish');
        }
    }

    public function test_bp_active_dengan_plan_finish_terlewati_tetap_active(): void
    {
        $po = $this->makeProject();
        $bp = $this->makeBudgetPlan($po, ['status' => 'ACTIVE', 'plan_finish' => now()->subMonth()->toDateString()]);

        // Perintah terjadwal budget-plan:auto-closing sudah dihapus.
        $this->assertArrayNotHasKey('budget-plan:auto-closing', \Illuminate\Support\Facades\Artisan::all());
        $this->assertSame('ACTIVE', $bp->fresh()->status);
    }

    public function test_bp_biasa_ditutup_manual_lewat_submit_closing(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $bp = $this->makeBudgetPlan(null, ['status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/submit-closing', [
                'closing_notes'       => 'selesai',
                'closing_attachments' => [\Illuminate\Http\UploadedFile::fake()->create('mom.pdf', 10, 'application/pdf')],
            ])
            ->assertSessionHas('success');

        $this->assertSame('CLOSING', $bp->fresh()->status);
    }
}
