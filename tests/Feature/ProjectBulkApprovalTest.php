<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Bulk Verify / Approve / Minta Revisi PO: tiap PO diproses method satuan
 * (verify / approve / financeRevise); role per tahap dicek ulang karena method
 * itu sendiri tidak mengecek role.
 */
class ProjectBulkApprovalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_finance_bulk_verify_mengubah_submitted_menjadi_verified(): void
    {
        $a = $this->makeProject(['status' => 'SUBMITTED']);
        $b = $this->makeProject(['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/bulk-approve', ['ids' => [$a->id, $b->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('success');

        $this->assertSame('VERIFIED', $a->refresh()->status);
        $this->assertSame('VERIFIED', $b->refresh()->status);
        $this->assertSame(1, $a->logs()->where('status_from', 'SUBMITTED')->where('status_to', 'VERIFIED')->count());
    }

    public function test_approver_bulk_approve_memakai_keputusan_approve_bukan_early_start(): void
    {
        $a = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/bulk-approve', ['ids' => [$a->id], 'status' => 'VERIFIED', 'decision' => 'EARLY_START'])
            ->assertSessionHas('success');

        $this->assertContains($a->refresh()->status, ['ACTIVE', 'WAITING_PO']);
        $this->assertNotSame('EARLY_START', $a->status);
    }

    public function test_po_yang_statusnya_berubah_dilewati(): void
    {
        $a = $this->makeProject(['status' => 'SUBMITTED']);
        $b = $this->makeProject(['status' => 'VERIFIED']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/bulk-approve', ['ids' => [$a->id, $b->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('success')->assertSessionHas('error');

        $this->assertSame('VERIFIED', $a->refresh()->status);
        $this->assertSame('VERIFIED', $b->refresh()->status); // tidak diproses
    }

    public function test_role_yang_tidak_berwenang_pada_tahap_itu_ditolak(): void
    {
        $verified = $this->makeProject(['status' => 'VERIFIED']);
        $submitted = $this->makeProject(['status' => 'SUBMITTED']);

        // Finance tidak boleh meng-approve tahap VERIFIED.
        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/bulk-approve', ['ids' => [$verified->id], 'status' => 'VERIFIED'])
            ->assertForbidden();
        // Approver tidak boleh memverifikasi tahap SUBMITTED.
        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/bulk-approve', ['ids' => [$submitted->id], 'status' => 'SUBMITTED'])
            ->assertForbidden();

        $this->assertSame('VERIFIED', $verified->refresh()->status);
        $this->assertSame('SUBMITTED', $submitted->refresh()->status);
    }

    public function test_bulk_revise_wajib_catatan_dan_mengembalikan_ke_draft(): void
    {
        $a = $this->makeProject(['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/bulk-revise', ['ids' => [$a->id], 'status' => 'SUBMITTED'])
            ->assertSessionHasErrors('notes');
        $this->assertSame('SUBMITTED', $a->refresh()->status);

        $this->post('/project/bulk-revise', ['ids' => [$a->id], 'status' => 'SUBMITTED', 'notes' => 'Lengkapi lampiran'])
            ->assertSessionHas('success');
        $this->assertSame('DRAFT', $a->refresh()->status);
    }

    public function test_maksimal_50_dan_status_harus_tahap_approval(): void
    {
        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/bulk-approve', ['ids' => range(1, 51), 'status' => 'SUBMITTED'])
            ->assertSessionHasErrors('ids');

        $this->post('/project/bulk-approve', ['ids' => [1], 'status' => 'ACTIVE'])
            ->assertSessionHasErrors('status');
    }

    public function test_role_lain_tidak_boleh_mengakses_endpoint_bulk(): void
    {
        $this->actingAs($this->userWithRole('head'))
            ->post('/project/bulk-approve', ['ids' => [1], 'status' => 'SUBMITTED'])
            ->assertForbidden();
    }
}
