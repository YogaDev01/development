<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/** Tambah COA pada Budget Plan ACTIVE: baris baru bernilai 0, nilai budget tetap. */
class BudgetPlanAddCoaTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_tambah_coa_menyimpan_baris_bernilai_nol_tanpa_mengubah_budget(): void
    {
        $bp   = $this->makeBudgetPlan();
        $akun = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
        $lama = $bp->details()->create([
            'account_id' => $akun->id, 'category' => 'Variable Cost', 'type' => 'main',
            'deskripsi' => 'Kabel', 'amount' => 1_000_000, 'qty' => 10, 'total' => 10_000_000,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/add-coa', ['rows' => [
                ['account_id' => $akun->id, 'deskripsi' => 'Konektor', 'unit' => 'pcs'],
                ['account_id' => $akun->id, 'deskripsi' => 'Tray', 'unit' => 'meter'],
            ], 'notes' => 'Tambahan material dari site survey'])
            ->assertRedirect('/budget-plan')
            ->assertSessionHas('success');

        $baru = BudgetPlanDetail::where('budget_plan_id', $bp->id)->whereKeyNot($lama->id)->get();
        $this->assertCount(2, $baru);
        foreach ($baru as $d) {
            $this->assertSame(0, $d->amount);
            $this->assertSame(0, $d->total);
            $this->assertEquals(0, (float) $d->qty);
            $this->assertSame('additional', $d->type);
        }

        $this->assertSame(10_000_000, $bp->fresh()->amount);
        $this->assertSame(10_000_000, $lama->fresh()->total);
        $log = BudgetPlanLog::where('budget_plan_id', $bp->id)->where('action', 'coa_added')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Tambahan material dari site survey', $log->notes);
    }

    public function test_tambah_coa_wajib_mengisi_unit(): void
    {
        $bp   = $this->makeBudgetPlan();
        $akun = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/add-coa', ['rows' => [
                ['account_id' => $akun->id, 'deskripsi' => 'Konektor'],
            ]])
            ->assertSessionHasErrors('rows.0.unit');

        $this->assertSame(0, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
    }

    public function test_tambah_coa_ditolak_jika_budget_plan_tidak_active(): void
    {
        $bp   = $this->makeBudgetPlan(null, ['status' => 'CLOSED']);
        $akun = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/add-coa', ['rows' => [
                ['account_id' => $akun->id, 'deskripsi' => 'Konektor', 'unit' => 'pcs'],
            ]])
            ->assertSessionHas('error');

        $this->assertSame(0, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
    }

    public function test_tambah_coa_menolak_account_di_luar_kategori_sumber_dana(): void
    {
        $bp   = $this->makeBudgetPlan();
        $akun = Account::create(['name' => 'Gaji Uji', 'category' => 'Fixed Cost', 'status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/add-coa', ['rows' => [
                ['account_id' => $akun->id, 'deskripsi' => 'Gaji', 'unit' => 'orang'],
            ]])
            ->assertSessionHasErrors('rows.0.account_id');

        $this->assertSame(0, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
    }

    public function test_tambah_coa_menolak_baris_kembar(): void
    {
        $bp   = $this->makeBudgetPlan();
        $akun = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
        $bp->details()->create([
            'account_id' => $akun->id, 'category' => 'Variable Cost', 'type' => 'main',
            'deskripsi' => 'Kabel', 'amount' => 1_000, 'qty' => 1, 'total' => 1_000,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/add-coa', ['rows' => [
                ['account_id' => $akun->id, 'deskripsi' => 'kabel', 'unit' => 'roll'],
            ]])
            ->assertSessionHas('error');

        $this->assertSame(1, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
    }

    public function test_role_selain_manager_tidak_boleh_tambah_coa(): void
    {
        $bp   = $this->makeBudgetPlan();
        $akun = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/budget-plan/' . $bp->id . '/add-coa', ['rows' => [
                ['account_id' => $akun->id, 'deskripsi' => 'Konektor', 'unit' => 'pcs'],
            ]])
            ->assertForbidden();
    }
}
