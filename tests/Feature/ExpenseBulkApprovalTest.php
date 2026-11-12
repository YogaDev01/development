<?php

namespace Tests\Feature;

use App\Models\CostRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Bulk Approve / Reject Expense dari halaman daftar (2026-11-12).
 * Hanya Expense VERIFIED ("Submitted") yang diproses; aksi satuan approve()/
 * reject() dipanggil per Expense, jadi aturan status & role tetap satu sumber.
 */
class ExpenseBulkApprovalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function expense(string $status = 'VERIFIED'): CostRegister
    {
        return CostRegister::create([
            'project_id'     => $this->makeProject()->id,
            'type'           => 'OVERHEAD',
            'amount'         => 500_000,
            'notes'          => 'Expense uji bulk',
            'beneficiary_id' => $this->makeBeneficiary()->id,
            'status'         => $status,
            'input_by'       => $this->userWithRole('finance_manager')->id,
        ]);
    }

    public function test_approver_bulk_approve_hanya_memproses_yang_verified(): void
    {
        $a = $this->expense();
        $b = $this->expense();
        $draft = $this->expense('DRAFT');

        $this->actingAs($this->userWithRole('approver'))
            ->post('/expense/bulk-approve', ['ids' => [$a->id, $b->id, $draft->id]])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('error');   // Draft dilewati & dilaporkan

        $this->assertSame('APPROVED', $a->fresh()->status);
        $this->assertSame('APPROVED', $b->fresh()->status);
        $this->assertSame('DRAFT', $draft->fresh()->status);
    }

    public function test_bulk_reject_wajib_alasan_dan_mengubah_ke_rejected(): void
    {
        $a = $this->expense();
        $approver = $this->userWithRole('approver');

        $this->actingAs($approver)
            ->post('/expense/bulk-reject', ['ids' => [$a->id]])
            ->assertSessionHasErrors('notes');
        $this->assertSame('VERIFIED', $a->fresh()->status);

        $this->actingAs($approver)
            ->post('/expense/bulk-reject', ['ids' => [$a->id], 'notes' => 'Dokumen kurang'])
            ->assertSessionHas('success');
        $this->assertSame('REJECTED', $a->fresh()->status);
    }

    public function test_finance_manager_tidak_boleh_bulk_approve(): void
    {
        $a = $this->expense();

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/expense/bulk-approve', ['ids' => [$a->id]])
            ->assertForbidden();

        $this->assertSame('VERIFIED', $a->fresh()->status);
    }
}
