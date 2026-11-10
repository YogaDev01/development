<?php

namespace Tests\Feature;

use App\Http\Controllers\TodoListController;
use App\Models\CostRegister;
use App\Models\DailyTransaction;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Audit Finance (30 Okt 2026): tahap yang butuh tindakan tetapi belum punya
 * antrean Todo List — Cost Register (approval & batch), batch Treasury DRAFT,
 * serta verifikasi closing Budget Plan dan PO oleh Finance.
 */
class TodoListFinanceQueueTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function keyAntrean($user): array
    {
        $this->actingAs($user);
        $hasil = app(TodoListController::class)->actionQueues($user);
        $this->assertFalse($hasil['gagal']);

        return collect($hasil['queues'])->pluck('key')->all();
    }

    private function register(string $status): CostRegister
    {
        return CostRegister::create([
            'project_id'     => $this->makeProject()->id,
            'type'           => 'OVERHEAD',
            'amount'         => 500_000,
            'notes'          => 'Dasar alokasi uji',
            'beneficiary_id' => $this->makeBeneficiary()->id,
            'status'         => $status,
            'input_by'       => $this->userWithRole('finance')->id,
        ]);
    }

    private function batch(string $status): DailyTransaction
    {
        return DailyTransaction::create([
            'code' => 'DT-UJI-' . uniqid(), 'date' => now()->toDateString(), 'type' => 'CASH_OUT',
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE'])->id,
            'status' => $status, 'created_by' => $this->userWithRole('treasury')->id,
        ]);
    }

    public function test_cost_register_submitted_muncul_di_todo_approver_saja(): void
    {
        $this->register('VERIFIED');

        $this->assertContains('reg-approve', $this->keyAntrean($this->userWithRole('approver')));
        $this->assertNotContains('reg-approve', $this->keyAntrean($this->userWithRole('finance')));
    }

    public function test_cost_register_approved_muncul_di_treasury_sampai_masuk_batch_aktif(): void
    {
        $reg = $this->register('APPROVED');
        $treasury = $this->userWithRole('treasury');

        $this->assertContains('reg-batch', $this->keyAntrean($treasury));

        // Sudah dipesan batch DRAFT -> tidak lagi di antrean "Masukkan ke Batch",
        // pindah ke antrean "Ajukan Batch Draft".
        $batch = $this->batch('DRAFT');
        $batch->items()->create(['cost_register_id' => $reg->id, 'status' => 'INCLUDED']);

        $keys = $this->keyAntrean($treasury);
        $this->assertNotContains('reg-batch', $keys);
        $this->assertContains('batch-draft', $keys);
    }

    public function test_batch_draft_tidak_muncul_untuk_approver(): void
    {
        $this->batch('DRAFT');

        $this->assertContains('batch-draft', $this->keyAntrean($this->userWithRole('finance')));
        $this->assertNotContains('batch-draft', $this->keyAntrean($this->userWithRole('approver')));
    }

    public function test_closing_budget_plan_yang_sudah_diajukan_masuk_antrean_finance_bukan_manager(): void
    {
        $plan = $this->makeBudgetPlan(null, ['status' => 'CLOSING', 'closing_submitted_at' => now()]);

        $this->assertContains('bp-closing-verify', $this->keyAntrean($this->userWithRole('finance')));

        $manager = $this->giveProjectAccess($this->userWithRole('manager'), $plan->project);
        $keys = $this->keyAntrean($manager);
        $this->assertNotContains('bp-closing-due', $keys);
        $this->assertNotContains('bp-closing-verify', $keys);
    }

    public function test_closing_po_masuk_antrean_finance(): void
    {
        $this->makeProject(['status' => 'CLOSING']);

        $this->assertContains('po-closing', $this->keyAntrean($this->userWithRole('finance_manager')));
        $this->assertNotContains('po-closing', $this->keyAntrean($this->userWithRole('approver')));
    }

    public function test_approver_bisa_approve_dan_reject_expense_dari_todo_list(): void
    {
        $approver = $this->userWithRole('approver');
        $this->giveMenuAccess($approver->role, 'todo-list');
        $setuju  = $this->register('VERIFIED');
        $kembali = $this->register('VERIFIED');

        $this->actingAs($approver)->get(route('todo-list.index'))
            ->assertOk()
            ->assertSee('td-reg-approve', false)
            ->assertSee('td-reg-reject', false);

        $this->actingAs($approver)->from(route('todo-list.index'))
            ->post(route('cost-register.approve', $setuju->id), ['notes' => ''])
            ->assertRedirect(route('todo-list.index'));
        $this->assertSame('APPROVED', $setuju->fresh()->status);

        // Alasan wajib saat ditolak (Fase 3: CEO Reject menggantikan 'kembalikan ke Draft').
        $this->actingAs($approver)->from(route('todo-list.index'))
            ->post(route('cost-register.reject', $kembali->id), ['notes' => ''])
            ->assertSessionHasErrors('notes');
        $this->assertSame('VERIFIED', $kembali->fresh()->status);

        $this->actingAs($approver)->from(route('todo-list.index'))
            ->post(route('cost-register.reject', $kembali->id), ['notes' => 'Nominal belum sesuai'])
            ->assertRedirect(route('todo-list.index'));
        $this->assertSame('REJECTED', $kembali->fresh()->status);

        $this->assertNotContains('reg-approve', $this->keyAntrean($approver));
    }

    public function test_finance_tidak_bisa_approve_cost_register(): void
    {
        $reg = $this->register('VERIFIED');

        $this->actingAs($this->userWithRole('finance'))
            ->post(route('cost-register.approve', $reg->id))
            ->assertForbidden();
        $this->assertSame('VERIFIED', $reg->fresh()->status);
    }

    public function test_halaman_todo_list_merender_antrean_baru(): void
    {
        $this->register('APPROVED');
        $this->batch('DRAFT');
        $this->makeProject(['status' => 'CLOSING']);

        $user = $this->userWithRole('finance_manager');
        $this->giveMenuAccess($user->role, 'todo-list');

        $this->actingAs($user)->get(route('todo-list.index'))
            ->assertOk()
            ->assertSee('id="tl-reg-batch"', false)
            ->assertSee('id="tl-batch-draft"', false)
            ->assertSee('id="tl-po-closing"', false);
    }
}
