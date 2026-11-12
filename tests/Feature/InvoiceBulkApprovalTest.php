<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Bulk Verify / Approve / Revisi invoice dari daftar Collection (2026-11-12).
 * Role per tahap = route satuan (InvoiceCashReceiptController::BULK_ROLES):
 * verify SUBMITTED: superadmin/finance_manager/accounting; approve VERIFIED:
 * superadmin/finance_manager; revise: + approver.
 */
class InvoiceBulkApprovalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function invoice(string $status)
    {
        return $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => $status]);
    }

    public function test_accounting_bulk_verifikasi_submitted(): void
    {
        $a = $this->invoice('SUBMITTED');
        $b = $this->invoice('SUBMITTED');

        $this->actingAs($this->userWithRole('accounting'))
            ->post('/invoice-cash-receipt/bulk-approve', ['ids' => [$a->id, $b->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('success');

        $this->assertSame('VERIFIED', $a->fresh()->status);
        $this->assertSame('VERIFIED', $b->fresh()->status);
    }

    public function test_status_yang_sudah_berubah_dilewati(): void
    {
        $a = $this->invoice('SUBMITTED');
        $sudah = $this->invoice('VERIFIED');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/bulk-approve', ['ids' => [$a->id, $sudah->id], 'status' => 'SUBMITTED'])
            ->assertSessionHas('error');

        $this->assertSame('VERIFIED', $a->fresh()->status);
        $this->assertSame('VERIFIED', $sudah->fresh()->status);   // tidak ikut di-approve
    }

    public function test_approver_tidak_boleh_bulk_approve_tapi_boleh_revisi(): void
    {
        $inv = $this->invoice('VERIFIED');
        $approver = $this->userWithRole('approver');

        $this->actingAs($approver)
            ->post('/invoice-cash-receipt/bulk-approve', ['ids' => [$inv->id], 'status' => 'VERIFIED'])
            ->assertForbidden();
        $this->assertSame('VERIFIED', $inv->fresh()->status);

        $this->actingAs($approver)
            ->post('/invoice-cash-receipt/bulk-revise', ['ids' => [$inv->id], 'status' => 'VERIFIED', 'notes' => 'Faktur salah'])
            ->assertSessionHas('success');
        $this->assertSame('SUBMITTED', $inv->fresh()->status);
    }

    public function test_bulk_revisi_wajib_alasan(): void
    {
        $inv = $this->invoice('SUBMITTED');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/bulk-revise', ['ids' => [$inv->id], 'status' => 'SUBMITTED'])
            ->assertSessionHasErrors('notes');

        $this->assertSame('SUBMITTED', $inv->fresh()->status);
    }
}
