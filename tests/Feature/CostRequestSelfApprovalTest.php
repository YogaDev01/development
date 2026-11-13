<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetRequest;
use App\Models\RequestDetail;
use App\Models\RequestDetailAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Pengaju (applicant_id) tidak boleh memproses pengajuannya sendiri di tahap
 * approval CR maupun Settlement, kecuali Superadmin (keputusan 2026-11-13);
 * serta edit Superadmin wajib tercatat lama -> baru di log CR.
 * Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class CostRequestSelfApprovalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private \App\Models\Project $project;

    private function crMilik(User $pengaju, string $status): BudgetRequest
    {
        $this->project = $this->makeProject();
        $plan = $this->makeBudgetPlan($this->project);

        return $this->makeCostRequest(['budget_plan' => $plan, 'status' => $status, 'applicant_id' => $pengaju->id]);
    }

    private function headDenganAkses(): User
    {
        return $this->userWithRole('head');
    }

    public function test_head_tidak_bisa_approve_cr_miliknya_sendiri(): void
    {
        $head = $this->headDenganAkses();
        $cr   = $this->crMilik($head, 'SUBMITTED');
        $this->giveProjectAccess($head, $this->project);

        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok'])
            ->assertRedirect()
            ->assertSessionHas('error', BudgetRequest::SELF_APPROVAL_MESSAGE);

        $this->assertSame('SUBMITTED', $cr->fresh()->status);
        $this->assertDatabaseMissing('request_status_logs', ['budget_request_id' => $cr->id, 'status_to' => 'CHECKED']);
    }

    public function test_head_tidak_bisa_reject_cr_miliknya_sendiri(): void
    {
        $head = $this->headDenganAkses();
        $cr   = $this->crMilik($head, 'SUBMITTED');
        $this->giveProjectAccess($head, $this->project);

        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/head-reject', ['notes' => 'tolak'])
            ->assertSessionHas('error', BudgetRequest::SELF_APPROVAL_MESSAGE);

        $this->assertSame('SUBMITTED', $cr->fresh()->status);
    }

    public function test_head_lain_tetap_bisa_approve(): void
    {
        $pengaju = $this->userWithRole('manager');
        $cr      = $this->crMilik($pengaju, 'SUBMITTED');
        $head    = $this->giveProjectAccess($this->headDenganAkses(), $this->project);

        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok'])
            ->assertRedirect();

        $this->assertSame('CHECKED', $cr->fresh()->status);
    }

    public function test_superadmin_boleh_approve_cr_miliknya_sendiri(): void
    {
        $super = $this->userWithRole('superadmin');
        $cr    = $this->crMilik($super, 'SUBMITTED');

        $this->actingAs($super)
            ->post('/budget-request/' . $cr->id . '/head-approve', ['notes' => 'ok'])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertSame('CHECKED', $cr->fresh()->status);
    }

    public function test_bulk_review_melewati_cr_milik_sendiri_dan_melaporkannya(): void
    {
        $head    = $this->headDenganAkses();
        $milik   = $this->crMilik($head, 'SUBMITTED');
        $lain    = $this->makeCostRequest([
            'budget_plan'  => $this->makeBudgetPlan($this->project),
            'status'       => 'SUBMITTED',
            'applicant_id' => $this->userWithRole('manager')->id,
        ]);
        $this->giveProjectAccess($head, $this->project);

        $res = $this->actingAs($head)
            ->post('/budget-request/review-bulk-approve', ['ids' => [$milik->id, $lain->id]])
            ->assertRedirect();

        $this->assertSame('SUBMITTED', $milik->fresh()->status);
        $this->assertSame('CHECKED', $lain->fresh()->status);
        $this->assertStringContainsString($milik->code, (string) session('error'));
        $this->assertStringContainsString('pengajuan Anda sendiri', (string) session('error'));
    }

    public function test_head_tidak_bisa_check_settlement_miliknya_sendiri(): void
    {
        $head = $this->headDenganAkses();
        $cr   = $this->crMilik($head, 'REALIZED');
        $this->giveProjectAccess($head, $this->project);

        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/head-check-settlement', ['notes' => 'ok'])
            ->assertSessionHas('error', BudgetRequest::SELF_APPROVAL_MESSAGE);

        $this->assertSame('REALIZED', $cr->fresh()->status);
    }

    public function test_head_tidak_bisa_takeout_item_cr_miliknya_sendiri(): void
    {
        $head = $this->headDenganAkses();
        $cr   = $this->crMilik($head, 'SUBMITTED');
        $this->giveProjectAccess($head, $this->project);
        $item = RequestDetail::create([
            'budget_request_id' => $cr->id, 'description' => 'Item uji',
            'amount' => 100_000, 'qty' => 1, 'total' => 100_000, 'status' => 'INCLUDED',
        ]);

        $this->assertFalse($cr->fresh()->canModerateItems($head));

        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/detail/' . $item->id . '/takeout', ['notes' => 'keluar'])
            ->assertSessionHas('error', BudgetRequest::SELF_APPROVAL_MESSAGE);

        $this->assertSame('INCLUDED', $item->fresh()->status);
    }

    public function test_helper_self_approval(): void
    {
        $head  = $this->userWithRole('head');
        $super = $this->userWithRole('superadmin');

        $this->assertTrue($this->crMilik($head, 'SUBMITTED')->isSelfApprovalBlockedFor($head));
        $this->assertFalse($this->crMilik($super, 'SUBMITTED')->isSelfApprovalBlockedFor($super));
        $this->assertFalse($this->crMilik($this->userWithRole('manager'), 'SUBMITTED')->isSelfApprovalBlockedFor($head));
    }

    public function test_edit_superadmin_mencatat_log_nilai_lama_dan_baru(): void
    {
        $super   = $this->userWithRole('superadmin');
        $pengaju = $this->userWithRole('manager');
        $cr      = $this->crMilik($pengaju, 'APPROVED');
        $type    = $this->makeRequestType('Cost Payment');
        $cr->update(['type_id' => $type->id, 'description' => 'DESKRIPSI LAMA']);

        $akun = Account::create(['name' => 'Transport Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
        $sub  = BudgetPlanDetail::create([
            'budget_plan_id' => $cr->budget_plan_id, 'account_id' => $akun->id, 'type' => 'main',
            'deskripsi' => 'Sub A', 'amount' => 5_000_000, 'qty' => 1, 'total' => 5_000_000,
        ]);
        $tetap = RequestDetail::create([
            'budget_request_id' => $cr->id, 'budget_detail_id' => $sub->id, 'account_id' => $akun->id,
            'description' => 'Transport Uji — Sub A', 'notes' => 'Baris tetap',
            'amount' => 100_000, 'qty' => 1, 'total' => 100_000, 'status' => 'INCLUDED',
        ]);
        $hapus = RequestDetail::create([
            'budget_request_id' => $cr->id, 'budget_detail_id' => $sub->id, 'account_id' => $akun->id,
            'description' => 'Transport Uji — Sub A', 'notes' => 'Baris dihapus',
            'amount' => 50_000, 'qty' => 1, 'total' => 50_000, 'status' => 'INCLUDED',
        ]);
        RequestDetailAttachment::create([
            'request_detail_id' => $tetap->id, 'original_name' => 'a.pdf', 'stored_name' => 'a.pdf',
            'path' => 'uji/a.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'uploaded_by' => $super->id,
        ]);

        $this->actingAs($super)->put('/budget-request/' . $cr->id, [
            'type_id'        => $type->id,
            'budget_plan_id' => $cr->budget_plan_id,
            'cluster_id'     => $cr->cluster_id,
            'beneficiary_id' => $cr->beneficiary_id,
            'description'    => 'deskripsi baru',
            'details'        => [
                ['id' => $tetap->id, 'budget_detail_id' => $sub->id, 'notes' => 'Baris tetap', 'amount' => '100000', 'qty' => 2],
            ],
        ])->assertRedirect('/budget-request')->assertSessionMissing('error');

        $cr->refresh();
        $this->assertSame('APPROVED', $cr->status);
        $this->assertNull(RequestDetail::find($hapus->id));

        $log = $cr->statusLogs()->where('action_type', 'superadmin_edited')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $super->id, (int) $log->acted_by);
        $this->assertStringContainsString('Deskripsi: DESKRIPSI LAMA -> DESKRIPSI BARU', $log->notes);
        $this->assertStringContainsString('Rincian #' . $tetap->id . ' diubah', $log->notes);
        $this->assertStringContainsString('Rincian dihapus #' . $hapus->id, $log->notes);

        $snap = $log->request_snapshot;
        $this->assertSame(['old' => 'DESKRIPSI LAMA', 'new' => 'DESKRIPSI BARU'], $snap['header']['Deskripsi']);
        $this->assertSame(['old' => '1', 'new' => '2'], $snap['rincian']['diubah'][$tetap->id]['Qty']);
        $this->assertArrayHasKey($hapus->id, $snap['rincian']['dihapus']);
    }

    public function test_pengaju_biasa_edit_draft_tidak_menulis_log_superadmin_edited(): void
    {
        $manager = $this->userWithRole('manager');
        $cr      = $this->crMilik($manager, 'DRAFT');
        $this->giveProjectAccess($manager, $this->project);
        $type    = $this->makeRequestType('Cost Payment');
        $cr->update(['type_id' => $type->id, 'description' => 'DESKRIPSI LAMA']);

        $akun = Account::create(['name' => 'Transport Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
        $sub  = BudgetPlanDetail::create([
            'budget_plan_id' => $cr->budget_plan_id, 'account_id' => $akun->id, 'type' => 'main',
            'deskripsi' => 'Sub A', 'amount' => 5_000_000, 'qty' => 1, 'total' => 5_000_000,
        ]);
        $baris = RequestDetail::create([
            'budget_request_id' => $cr->id, 'budget_detail_id' => $sub->id, 'account_id' => $akun->id,
            'description' => 'Transport Uji — Sub A', 'notes' => 'Baris tetap',
            'amount' => 100_000, 'qty' => 1, 'total' => 100_000, 'status' => 'INCLUDED',
        ]);
        RequestDetailAttachment::create([
            'request_detail_id' => $baris->id, 'original_name' => 'a.pdf', 'stored_name' => 'a.pdf',
            'path' => 'uji/a.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'uploaded_by' => $manager->id,
        ]);

        $this->actingAs($manager)->put('/budget-request/' . $cr->id, [
            'type_id'        => $type->id,
            'budget_plan_id' => $cr->budget_plan_id,
            'cluster_id'     => $cr->cluster_id,
            'beneficiary_id' => $cr->beneficiary_id,
            'description'    => 'deskripsi baru',
            'details'        => [
                ['id' => $baris->id, 'budget_detail_id' => $sub->id, 'notes' => 'Baris tetap', 'amount' => '100000', 'qty' => 2],
            ],
        ])->assertRedirect('/budget-request')->assertSessionMissing('error');

        $this->assertSame('DESKRIPSI BARU', $cr->fresh()->description);
        $this->assertDatabaseMissing('request_status_logs', [
            'budget_request_id' => $cr->id, 'action_type' => 'superadmin_edited',
        ]);
    }

    public function test_finance_tidak_bisa_verify_settlement_miliknya_sendiri(): void
    {
        $finance = $this->userWithRole('finance');
        $cr      = $this->crMilik($finance, 'SETTLE_CHECKED');

        $this->actingAs($finance)
            ->post(route('budget-request.finance-verify-settlement', $cr->id), ['notes' => 'ok'])
            ->assertRedirect()
            ->assertSessionHas('error', BudgetRequest::SELF_APPROVAL_MESSAGE);

        $this->assertSame('SETTLE_CHECKED', $cr->fresh()->status);
    }

    public function test_settlement_bulk_close_melewati_cr_milik_sendiri(): void
    {
        $fm    = $this->userWithRole('finance_manager');
        $milik = $this->crMilik($fm, 'SETTLE_VERIFIED');
        $lain  = $this->makeCostRequest([
            'budget_plan'  => $this->makeBudgetPlan($this->project),
            'status'       => 'SETTLE_VERIFIED',
            'applicant_id' => $this->userWithRole('manager')->id,
        ]);

        $this->actingAs($fm)
            ->post(route('budget-request.settlement-bulk-close'), ['ids' => [$milik->id, $lain->id]])
            ->assertRedirect();

        $this->assertSame('SETTLE_VERIFIED', $milik->fresh()->status);
        $this->assertSame('CLOSED', $lain->fresh()->status);
        $this->assertStringContainsString($milik->code, (string) session('error'));
        $this->assertStringContainsString('pengajuan Anda sendiri', (string) session('error'));
    }

    public function test_settlement_bulk_reject_melewati_cr_milik_sendiri(): void
    {
        $fm    = $this->userWithRole('finance_manager');
        $milik = $this->crMilik($fm, 'SETTLE_VERIFIED');
        $lain  = $this->makeCostRequest([
            'budget_plan'  => $this->makeBudgetPlan($this->project),
            'status'       => 'SETTLE_VERIFIED',
            'applicant_id' => $this->userWithRole('manager')->id,
        ]);

        $this->actingAs($fm)
            ->post(route('budget-request.settlement-bulk-reject'), ['ids' => [$milik->id, $lain->id], 'notes' => 'Revisi bukti.'])
            ->assertRedirect();

        $this->assertSame('SETTLE_VERIFIED', $milik->fresh()->status);
        $this->assertSame('SETTLE_REJECTED', $lain->fresh()->status);
        $this->assertStringContainsString($milik->code, (string) session('error'));
    }

    public function test_todo_list_tidak_menampilkan_aksi_untuk_cr_milik_sendiri(): void
    {
        $head = $this->headDenganAkses();
        $this->giveMenuAccess($this->role('head'), 'todo-list');
        $milikApproval   = $this->crMilik($head, 'SUBMITTED');
        $milikSettlement = $this->makeCostRequest([
            'budget_plan' => $this->makeBudgetPlan($this->project), 'status' => 'REALIZED', 'applicant_id' => $head->id,
        ]);
        $pengaju   = $this->userWithRole('manager');
        $lainApproval   = $this->makeCostRequest([
            'budget_plan' => $this->makeBudgetPlan($this->project), 'status' => 'SUBMITTED', 'applicant_id' => $pengaju->id,
        ]);
        $lainSettlement = $this->makeCostRequest([
            'budget_plan' => $this->makeBudgetPlan($this->project), 'status' => 'REALIZED', 'applicant_id' => $pengaju->id,
        ]);
        $this->giveProjectAccess($head, $this->project);

        $res = $this->actingAs($head)->get(route('todo-list.index'))->assertOk();

        foreach ([$milikApproval, $milikSettlement] as $cr) {
            $res->assertDontSee('data-export-key="cr:' . $cr->id . '"', false)
                ->assertDontSee('class="todo-check" value="' . $cr->id . '"', false);
        }
        foreach ([$lainApproval, $lainSettlement] as $cr) {
            $res->assertSee('data-export-key="cr:' . $cr->id . '"', false);
        }

        // Badge/pengingat memakai definisi antrean yang sama: CR milik sendiri tidak dihitung.
        $hitung = app(\App\Http\Controllers\TodoListController::class)->pendingCounts($head);
        $this->assertSame(1, $hitung['head']);
        $this->assertSame(1, $hitung['headSettlementCheck']);
    }
}
