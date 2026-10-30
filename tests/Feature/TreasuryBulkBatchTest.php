<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\SourceFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Bulk approve / reject batch dari daftar Treasury (29 Okt 2026).
 *
 * Tiap batch dijalankan lewat aksi satuannya (approve / rejectBatch); hanya
 * batch PENDING_APPROVER yang diproses, dan batch yang statusnya berbeda dari
 * yang terlihat di layar (expected) dilewati.
 */
class TreasuryBulkBatchTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function batch(BudgetRequest $cr, bool $submit = true): DailyTransaction
    {
        $treasury = $this->userWithRole('treasury');
        $fund = SourceFund::create(['name' => 'Rekening Bulk ' . $cr->id, 'status' => 'ACTIVE', 'is_main_account' => false]);

        $this->actingAs($treasury)->post('/daily-transaction', [
            'type' => 'CASH_OUT', 'description' => 'Bulk uji', 'source_fund_id' => $fund->id, 'cr_ids' => [$cr->id],
        ])->assertSessionMissing('error');

        $batch = DailyTransaction::latest('id')->firstOrFail();
        if ($submit) {
            $this->actingAs($treasury)->post('/daily-transaction/' . $batch->id . '/submit')->assertSessionMissing('error');
        }

        return $batch->refresh();
    }

    private function approver(): User
    {
        return $this->userWithRole('approver');
    }

    public function test_bulk_approve_memproses_semua_batch_submitted(): void
    {
        $a = $this->batch($this->makeCostRequest(['status' => 'APPROVED']));
        $b = $this->batch($this->makeCostRequest(['status' => 'APPROVED']));

        $this->actingAs($this->approver())->post('/daily-transaction/bulk-approve', [
            'ids'      => [$a->id, $b->id],
            'expected' => [$a->id => 'PENDING_APPROVER', $b->id => 'PENDING_APPROVER'],
        ])->assertSessionHas('success', '2 batch berhasil di-approve.');

        $this->assertSame('DONE', $a->refresh()->status);
        $this->assertSame('DONE', $b->refresh()->status);
    }

    public function test_bulk_approve_melewati_draft_dan_status_yang_berubah(): void
    {
        $submitted = $this->batch($this->makeCostRequest(['status' => 'APPROVED']));
        $draft     = $this->batch($this->makeCostRequest(['status' => 'APPROVED']), false);

        $this->actingAs($this->approver())->post('/daily-transaction/bulk-approve', [
            'ids'      => [$submitted->id, $draft->id],
            'expected' => [$submitted->id => 'DONE', $draft->id => 'DRAFT'],
        ])->assertSessionHas('error');

        // expected tidak cocok -> dilewati; Draft bukan Submitted -> dilewati.
        $this->assertSame('PENDING_APPROVER', $submitted->refresh()->status);
        $this->assertSame('DRAFT', $draft->refresh()->status);
    }

    public function test_bulk_reject_wajib_alasan_dan_mengembalikan_cr_ke_approved(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->batch($cr);

        $this->actingAs($this->approver())->post('/daily-transaction/bulk-reject', [
            'ids' => [$batch->id],
        ])->assertSessionHasErrors('notes');
        $this->assertSame('PENDING_APPROVER', $batch->refresh()->status);

        $this->actingAs($this->approver())->post('/daily-transaction/bulk-reject', [
            'ids' => [$batch->id], 'notes' => 'Ditunda',
        ])->assertSessionHas('success', '1 batch berhasil ditolak.');

        $this->assertSame('REJECTED', $batch->refresh()->status);
        $this->assertSame('APPROVED', $cr->refresh()->status);
    }

    public function test_treasury_tidak_boleh_bulk_approve(): void
    {
        $batch = $this->batch($this->makeCostRequest(['status' => 'APPROVED']));

        $this->actingAs($this->userWithRole('treasury'))
            ->post('/daily-transaction/bulk-approve', ['ids' => [$batch->id]])
            ->assertForbidden();

        $this->assertSame('PENDING_APPROVER', $batch->refresh()->status);
    }
}
