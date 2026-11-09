<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Aksi inline Todo List untuk section yang sebelumnya hanya link-out:
 * Purchase Order (Approval Finance / Approver / Verifikasi Closing),
 * Budgeting Verifikasi Closing, Treasury Draft, dan Revise Invoice.
 * Semua lewat todo-list.bulk (TodoListController::BULK_ROUTES) yang memanggil
 * aksi satuan modulnya — aturan status/log tetap di controller modul.
 */
class TodoListInlineActionTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function userTodo(string $role)
    {
        $user = $this->userWithRole($role);
        $this->giveMenuAccess($user->role, 'todo-list');

        return $user;
    }

    /* ---------------------------------------------------- PO — Approver */

    public function test_approver_approve_po_verified_dari_todo_list(): void
    {
        $approver = $this->userTodo('approver');
        $po = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($approver)
            ->post(route('todo-list.bulk', ['po-verified', 'approve']), ['ids' => [$po->id]])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        // decision=APPROVE: status akhir dipilih sistem (ACTIVE / WAITING_PO), bukan Early Start.
        $this->assertContains($po->fresh()->status, ['ACTIVE', 'WAITING_PO']);
        $this->assertSame($approver->id, (int) $po->fresh()->approved_by);
    }

    public function test_approver_revise_po_verified_wajib_catatan(): void
    {
        $approver = $this->userTodo('approver');
        $po = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($approver)
            ->post(route('todo-list.bulk', ['po-verified', 'reject']), ['ids' => [$po->id]])
            ->assertSessionHasErrors('notes');
        $this->assertSame('VERIFIED', $po->fresh()->status);

        $this->actingAs($approver)
            ->post(route('todo-list.bulk', ['po-verified', 'reject']), ['ids' => [$po->id], 'notes' => 'Nilai kontrak salah'])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('success');
        $this->assertSame('DRAFT', $po->fresh()->status);
    }

    public function test_finance_tidak_boleh_approve_po_tahap_approver(): void
    {
        $finance = $this->userTodo('finance');
        $po = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($finance)
            ->post(route('todo-list.bulk', ['po-verified', 'approve']), ['ids' => [$po->id]])
            ->assertForbidden();
        $this->assertSame('VERIFIED', $po->fresh()->status);
    }

    /* ---------------------------------------------------- PO — Finance */

    public function test_finance_verify_po_submitted_dari_todo_list(): void
    {
        $finance = $this->userTodo('finance');
        $po = $this->makeProject(['status' => 'SUBMITTED']);

        $this->actingAs($finance)
            ->post(route('todo-list.bulk', ['po-submitted', 'approve']), ['ids' => [$po->id]])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('success');

        $this->assertSame('VERIFIED', $po->fresh()->status);
    }

    public function test_section_po_submitted_tidak_bisa_merevisi_po_tahap_lain(): void
    {
        // project.finance-revise menerima SUBMITTED & VERIFIED; section 'po-submitted'
        // dibatasi BULK_EXPECTED_STATUS agar tidak dipakai lintas tahap.
        $finance = $this->userTodo('finance');
        $po = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($finance)
            ->post(route('todo-list.bulk', ['po-submitted', 'reject']), ['ids' => [$po->id], 'notes' => 'x'])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame('VERIFIED', $po->fresh()->status);
    }

    public function test_finance_verify_dan_reject_closing_po(): void
    {
        $finance = $this->userTodo('finance');
        $tutup = $this->makeProject(['status' => 'CLOSING']);
        $tolak = $this->makeProject(['status' => 'CLOSING']);

        $this->actingAs($finance)
            ->post(route('todo-list.bulk', ['po-closing', 'approve']), ['ids' => [$tutup->id]])
            ->assertSessionHas('success');
        $this->assertSame('CLOSED', $tutup->fresh()->status);

        $this->actingAs($finance)
            ->post(route('todo-list.bulk', ['po-closing', 'reject']), ['ids' => [$tolak->id], 'notes' => 'MoM belum ditandatangani'])
            ->assertSessionHas('success');
        $this->assertSame('PAID', $tolak->fresh()->status);
    }

    /* ---------------------------------------------------- Invoice — Revise */

    public function test_finance_manager_revise_invoice_verified_kembali_ke_submitted(): void
    {
        $fm = $this->userTodo('finance_manager');
        $po = $this->makeProject();
        $invoice = $this->makeInvoice($this->makePoNumber($po), ['status' => 'VERIFIED']);

        $this->actingAs($fm)
            ->post(route('todo-list.bulk', ['invoice-approve', 'reject']), ['ids' => [$invoice->id], 'notes' => 'Nominal tidak sesuai BAST'])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('success');

        $this->assertSame('SUBMITTED', $invoice->fresh()->status);
    }

    public function test_section_invoice_approve_menolak_invoice_tahap_lain(): void
    {
        $fm = $this->userTodo('finance_manager');
        $po = $this->makeProject();
        $invoice = $this->makeInvoice($this->makePoNumber($po), ['status' => 'SUBMITTED']);

        $this->actingAs($fm)
            ->post(route('todo-list.bulk', ['invoice-approve', 'reject']), ['ids' => [$invoice->id], 'notes' => 'x'])
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame('SUBMITTED', $invoice->fresh()->status);
    }

    /* ---------------------------------------------------- Halaman */

    public function test_halaman_menampilkan_tombol_aksi_po_untuk_approver(): void
    {
        $approver = $this->userTodo('approver');
        $po = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($approver)
            ->get(route('todo-list.index'))
            ->assertOk()
            ->assertSee('data-section="po-verified"', false)
            ->assertSee('todo-row-act', false)
            ->assertSee(route('todo-list.bulk', ['po-verified', 'approve']), false);
    }
}
