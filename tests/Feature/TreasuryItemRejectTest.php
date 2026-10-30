<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestSecondPayment;
use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Treasury show — dua aksi atas 1 CR di batch PENDING_APPROVER:
 *  - Takeout: CR kembali ke APPROVED (bisa dibatch ulang)
 *  - Reject : CR pembayaran pertama → REJECTED; Second Payment → CR induk SETTLE_REJECTED
 */
class TreasuryItemRejectTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function batchFor(array $payload): DailyTransaction
    {
        $fund = SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE', 'is_main_account' => false]);
        $this->actingAs($this->userWithRole('treasury'))->post('/daily-transaction', array_merge([
            'type'           => 'CASH_OUT',
            'description'    => 'Batch uji',
            'source_fund_id' => $fund->id,
        ], $payload))->assertSessionMissing('error');

        // Batch lahir sebagai Draft; Submit memindahkan sumbernya ke QUEUED.
        $batch = DailyTransaction::latest('id')->firstOrFail();
        $this->actingAs($this->userWithRole('treasury'))->post('/daily-transaction/' . $batch->id . '/submit')
            ->assertSessionMissing('error');

        return $batch->refresh();
    }

    public function test_takeout_cr_biasa_kembali_ke_approved_dan_bisa_dibatch_ulang(): void
    {
        $cr    = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->batchFor(['cr_ids' => [$cr->id]]);
        $item  = DailyTransactionItem::where('budget_request_id', $cr->id)->firstOrFail();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-item/' . $item->id, ['notes' => 'Keluarkan']);

        $this->assertSame('APPROVED', $cr->refresh()->status);
        $this->assertSame('REJECTED', $item->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'batch_takeout')->count());
        $this->assertTrue(BudgetRequest::where('status', 'APPROVED')->whereKey($cr->id)
            ->whereDoesntHave('dailyTransactionItems', fn ($q) => $q->where('status', 'INCLUDED'))->exists());
    }

    public function test_reject_cr_biasa_menjadi_rejected(): void
    {
        $cr    = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->batchFor(['cr_ids' => [$cr->id]]);
        $item  = DailyTransactionItem::where('budget_request_id', $cr->id)->firstOrFail();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-cr-item/' . $item->id, ['notes' => 'Tolak']);

        $this->assertSame('REJECTED', $cr->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'batch_item_rejected')->count());
    }

    public function test_reject_second_payment_membuat_cr_induk_settle_rejected(): void
    {
        $cr = $this->makeCostRequest(['status' => 'SECOND_APPROVED']);
        $this->actingAs($this->userWithRole('approver'));
        $sp = BudgetRequestSecondPayment::create([
            'budget_request_id' => $cr->id, 'amount' => 250_000, 'status' => 'PENDING',
        ]);
        $batch = $this->batchFor(['second_payment_ids' => [$sp->id]]);
        $item  = DailyTransactionItem::where('second_payment_id', $sp->id)->firstOrFail();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-cr-item/' . $item->id, ['notes' => 'Tolak']);

        $this->assertSame('SETTLE_REJECTED', $cr->refresh()->status);
        $this->assertNull(BudgetRequestSecondPayment::find($sp->id));
        $this->assertSame('REJECTED', $item->refresh()->status);
    }

    public function test_hanya_approver_atau_superadmin_yang_boleh_reject(): void
    {
        $cr    = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->batchFor(['cr_ids' => [$cr->id]]);
        $item  = DailyTransactionItem::where('budget_request_id', $cr->id)->firstOrFail();

        $this->actingAs($this->userWithRole('treasury'))
            ->post('/daily-transaction/' . $batch->id . '/reject-cr-item/' . $item->id, ['notes' => 'Tolak'])
            ->assertForbidden();
        $this->assertSame('QUEUED', $cr->refresh()->status);
    }
}
