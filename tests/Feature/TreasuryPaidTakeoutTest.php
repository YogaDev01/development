<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Takeout Superadmin dari batch Treasury PAID (2026-11-12): efek Mark Paid
 * dibalik hanya bila sumber belum melangkah lebih jauh; kode batch PAID tidak
 * boleh berubah (sudah jadi referensi transfer).
 */
class TreasuryPaidTakeoutTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function paidBatch(BudgetRequest $cr): DailyTransaction
    {
        $batch = DailyTransaction::create([
            'code'           => '20261112001' . '0001',
            'date'           => now()->toDateString(),
            'type'           => 'CASH_OUT',
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE'])->id,
            'status'         => 'PAID',
            'created_by'     => $cr->applicant_id,
        ]);
        $batch->items()->create(['budget_request_id' => $cr->id, 'status' => 'INCLUDED']);

        return $batch;
    }

    private function takeout(DailyTransaction $batch, string $role = 'superadmin')
    {
        $item = $batch->items()->first();

        return $this->actingAs($this->userWithRole($role))
            ->post('/daily-transaction/' . $batch->id . '/reject-item/' . $item->id, ['notes' => 'Salah batch']);
    }

    public function test_cr_non_ca_closed_kembali_approved_dan_kode_batch_tetap(): void
    {
        $cr = $this->makeCostRequest([
            'status'    => 'CLOSED',
            'type_id'   => $this->makeRequestType('Cost Payment')->id,
            'paid_at'   => now(),
            'closed_at' => now(),
        ]);
        $batch = $this->paidBatch($cr);
        $kode = $batch->code;

        $this->takeout($batch)->assertSessionMissing('error');

        $cr->refresh();
        $this->assertSame('APPROVED', $cr->status);
        $this->assertNull($cr->paid_at);
        $this->assertSame('REJECTED', $batch->items()->first()->status);
        $this->assertSame($kode, $batch->fresh()->code);
        $this->assertSame('PAID', $batch->fresh()->status);
    }

    public function test_cash_advance_yang_sudah_lanjut_settlement_ditolak(): void
    {
        $cr = $this->makeCostRequest(['status' => 'REALIZED']);
        $batch = $this->paidBatch($cr);

        $this->takeout($batch)->assertSessionHas('error');

        $this->assertSame('REALIZED', $cr->fresh()->status);
        $this->assertSame('INCLUDED', $batch->items()->first()->status);
    }

    public function test_approver_tidak_boleh_takeout_dari_batch_paid(): void
    {
        $cr = $this->makeCostRequest(['status' => 'PAID']);
        $batch = $this->paidBatch($cr);

        $this->takeout($batch, 'approver')->assertSessionHas('error');

        $this->assertSame('PAID', $cr->fresh()->status);
        $this->assertSame('INCLUDED', $batch->items()->first()->status);
    }
}
