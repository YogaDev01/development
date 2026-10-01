<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestSecondPayment;
use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\SourceFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Second Payment yang gagal di sebuah batch harus bisa dibatch ulang.
 *
 * Saat dibatch, CR induk pindah SECOND_APPROVED -> QUEUED. Dulu batch ditolak
 * (rejectBatch) atau itemnya dikeluarkan (rejectItem) membiarkan CR induk
 * tetap QUEUED, sedangkan availableForBatch() mensyaratkan SECOND_APPROVED -
 * Expense Claim-nya tidak pernah bisa dibayar. Sekarang CR induk dikembalikan
 * ke SECOND_APPROVED, cerminan CR biasa yang kembali ke APPROVED.
 */
class SecondPaymentRebatchTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private User $treasury;

    protected function setUp(): void
    {
        parent::setUp();
        $this->treasury = $this->userWithRole('treasury');
    }

    private function fundId(): int
    {
        return SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE', 'is_main_account' => false])->id;
    }

    /** CR Expense Claim siap dibatch + Second Payment PENDING-nya. */
    private function claim(): array
    {
        $cr = $this->makeCostRequest(['status' => 'SECOND_APPROVED']);
        $this->actingAs($this->userWithRole('approver'));
        $cr->addLog('SETTLE_APPROVED', 'SECOND_APPROVED', 'second_payment_approved', null);

        $sp = BudgetRequestSecondPayment::create([
            'budget_request_id' => $cr->id,
            'amount'            => 250_000,
            'status'            => 'PENDING',
        ]);

        return [$cr, $sp];
    }

    private function batchFor(array $payload): DailyTransaction
    {
        $this->actingAs($this->treasury)->post('/daily-transaction', array_merge([
            'type'           => 'CASH_OUT',
            'description'    => 'Batch uji',
            'source_fund_id' => $this->fundId(),
        ], $payload))->assertSessionMissing('error');

        return DailyTransaction::latest('id')->firstOrFail();
    }

    public function test_batch_ditolak_mengembalikan_cr_induk_ke_second_approved(): void
    {
        [$cr, $sp] = $this->claim();
        $batch = $this->batchFor(['second_payment_ids' => [$sp->id]]);
        $this->assertSame('QUEUED', $cr->refresh()->status);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-batch', ['notes' => 'Tunda']);

        $this->assertSame('SECOND_APPROVED', $cr->refresh()->status);
        $this->assertSame('PENDING', $sp->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()
            ->where('status_from', 'QUEUED')->where('status_to', 'SECOND_APPROVED')
            ->where('action_type', 'second_payment_batch_rejected')->count());
    }

    public function test_second_payment_dari_batch_ditolak_bisa_dibatch_ulang(): void
    {
        [$cr, $sp] = $this->claim();
        $batch = $this->batchFor(['second_payment_ids' => [$sp->id]]);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-batch', ['notes' => 'Tunda']);

        $this->assertTrue(BudgetRequestSecondPayment::availableForBatch()->whereKey($sp->id)->exists());

        $this->batchFor(['second_payment_ids' => [$sp->id]]);

        $this->assertSame('QUEUED', $cr->refresh()->status);
        $this->assertSame(1, DailyTransactionItem::where('second_payment_id', $sp->id)
            ->where('status', 'INCLUDED')->count());
    }

    public function test_item_second_payment_dikeluarkan_dari_batch_bisa_dibatch_ulang(): void
    {
        [$cr, $sp] = $this->claim();
        $batch = $this->batchFor(['second_payment_ids' => [$sp->id]]);
        $item  = DailyTransactionItem::where('second_payment_id', $sp->id)->firstOrFail();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-item/' . $item->id, ['notes' => 'Keluarkan']);

        $this->assertSame('REJECTED', $item->refresh()->status);
        $this->assertSame('SECOND_APPROVED', $cr->refresh()->status);
        $this->assertTrue(BudgetRequestSecondPayment::availableForBatch()->whereKey($sp->id)->exists());
    }

    public function test_batch_cr_biasa_ditolak_tetap_kembali_ke_approved(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->batchFor(['cr_ids' => [$cr->id]]);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-batch', ['notes' => 'Tunda']);

        $this->assertSame('APPROVED', $cr->refresh()->status);
    }
}
