<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CostRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Tab Cost di modal Detail PO memakai angka yang SAMA dengan kolom daftar PO
 * (keputusan 2026-11-10, revisi hasil audit alur Expense):
 *   Variable Cost  = Cost Request yang sudah cair + Expense kategori Variable Cost
 *   Overhead/Fixed = kolom manual PO + Cost Register lama + Expense
 * Sebelumnya modal ini melewatkan bagian Expense (Variable Cost) dan hanya
 * memakai kolom manual untuk Overhead/Fixed Cost, sehingga satu PO menampilkan
 * dua angka berbeda antara sel tabel dan modalnya.
 */
class ProjectCostSummaryExpenseTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function akun(string $kategori): Account
    {
        return Account::create([
            'name'     => 'Akun ' . $kategori . ' ' . uniqid(),
            'category' => $kategori,
            'status'   => 'ACTIVE',
        ]);
    }

    public function test_cost_summary_po_memuat_expense_dan_kolom_manual(): void
    {
        $project = $this->makeProject(['overhead' => 2_000_000, 'fixed_cost' => 5_000_000]);

        // Expense yang sudah dibayar lewat Treasury (CLOSED) dan dibebankan ke PO ini.
        $expense = CostRegister::create([
            'expense_no' => 'EXP-UJI-SUM',
            'amount'     => 1_800_000,
            'notes'      => 'uji',
            'status'     => 'CLOSED',
            'input_by'   => $this->userWithRole('finance_manager')->id,
        ]);
        foreach ([['Variable Cost', 500_000], ['Fixed Cost', 1_000_000], ['Overhead', 300_000]] as [$kategori, $total]) {
            $akun = $this->akun($kategori);
            $expense->details()->create([
                'account_id'    => $akun->id,
                'cost_category' => $akun->category,
                'project_id'    => $project->id,
                'allocation'    => 'po',
                'description'   => 'x',
                'qty'           => 1,
                'unit_price'    => $total,
                'total'         => $total,
            ]);
        }

        $json = $this->actingAs($this->userWithRole('superadmin'))
            ->getJson(route('project.show-json', $project->id))
            ->assertOk()
            ->json();

        $c = $json['cost_summary'];

        // Tanpa Cost Request: Variable Cost murni dari Expense, dan bagiannya tampil terpisah
        // supaya Request - Refund + Expense = Variable Cost cocok di layar.
        $this->assertSame(0, $c['total_request']);
        $this->assertSame(0, $c['total_refund']);
        $this->assertSame(500_000, $c['expense_variable']);
        $this->assertSame(500_000, $c['variable_cost']);

        // Overhead & Fixed Cost = kolom manual + Expense.
        $this->assertSame(2_300_000, $c['overhead']);
        $this->assertSame(6_000_000, $c['fixed_cost']);
        $this->assertSame(8_800_000, $c['total_cost']);

        // Angka modal = angka kolom daftar PO. Di PO ini tidak ada Cost Request, jadi
        // perbandingannya murni bagian Expense + kolom manual (bagian Cost Request punya
        // selisih lama sendiri untuk kasus Expense Claim — di luar lingkup test ini).
        $project->refresh();
        $this->assertSame($c['variable_cost'], $project->variableCostTotal());
        $this->assertSame($c['overhead'], $project->overheadCostTotal());
        $this->assertSame($c['fixed_cost'], $project->fixedCostTotal());
    }
}
