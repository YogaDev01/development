<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\SourceFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Batch Treasury berstatus DRAFT (29 Okt 2026).
 *
 * Batch buatan Treasury lahir sebagai DRAFT: masih bisa diubah/dihapus dan
 * belum bisa di-approve CEO. Status Cost Request di dalamnya baru berubah
 * (APPROVED -> QUEUED) saat Submit, tetapi selama Draft CR itu sudah
 * dipesan dan tidak boleh masuk batch lain.
 */
class TreasuryDraftBatchTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function treasury(): User
    {
        return $this->userWithRole('treasury');
    }

    private function draft(User $user, BudgetRequest ...$crs): DailyTransaction
    {
        $fund = SourceFund::create(['name' => 'Rekening Draft Uji', 'status' => 'ACTIVE', 'is_main_account' => false]);

        $this->actingAs($user)->post('/daily-transaction', [
            'type'           => 'CASH_OUT',
            'description'    => 'Draft uji',
            'source_fund_id' => $fund->id,
            'cr_ids'         => array_map(fn ($cr) => $cr->id, $crs),
        ])->assertSessionMissing('error');

        return DailyTransaction::latest('id')->firstOrFail();
    }

    public function test_batch_baru_berstatus_draft_dan_cr_tetap_approved(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($this->treasury(), $cr);

        $this->assertSame('DRAFT', $batch->status);
        $this->assertSame('APPROVED', $cr->refresh()->status);
    }

    public function test_ceo_tidak_bisa_approve_draft(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($this->treasury(), $cr);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/approve')
            ->assertSessionHas('error');

        $this->assertSame('DRAFT', $batch->refresh()->status);
        $this->assertSame('APPROVED', $cr->refresh()->status);
    }

    public function test_cr_di_draft_tidak_bisa_masuk_batch_lain(): void
    {
        $user = $this->treasury();
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $this->draft($user, $cr);

        $fund = SourceFund::create(['name' => 'Rekening Lain', 'status' => 'ACTIVE', 'is_main_account' => false]);
        $this->actingAs($user)->post('/daily-transaction', [
            'type' => 'CASH_OUT', 'description' => 'Kedua', 'source_fund_id' => $fund->id, 'cr_ids' => [$cr->id],
        ])->assertSessionHas('error');

        $this->assertSame(1, DailyTransaction::count());
    }

    public function test_submit_memindahkan_ke_pending_approver_dan_cr_ke_queued(): void
    {
        $user = $this->treasury();
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($user, $cr);

        $this->actingAs($user)->post('/daily-transaction/' . $batch->id . '/submit')
            ->assertSessionMissing('error');

        $this->assertSame('PENDING_APPROVER', $batch->refresh()->status);
        $this->assertSame('QUEUED', $cr->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'batched')->count());
    }

    public function test_submit_ditolak_bila_status_cr_sudah_berubah(): void
    {
        $user = $this->treasury();
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($user, $cr);

        $cr->update(['status' => 'REJECTED']);

        $this->actingAs($user)->post('/daily-transaction/' . $batch->id . '/submit')
            ->assertSessionHas('error');

        $this->assertSame('DRAFT', $batch->refresh()->status);
        $this->assertSame('REJECTED', $cr->refresh()->status);
    }

    public function test_approver_tidak_boleh_submit_draft(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($this->treasury(), $cr);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/submit')
            ->assertForbidden();

        $this->assertSame('DRAFT', $batch->refresh()->status);
    }

    public function test_hapus_draft_melepas_cr_untuk_batch_lain(): void
    {
        $user = $this->treasury();
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($user, $cr);

        $this->actingAs($user)->delete('/daily-transaction/' . $batch->id)
            ->assertSessionMissing('error');

        $this->assertSame(0, DailyTransaction::count());
        $this->assertSame(0, DailyTransactionItem::count());
        $this->assertSame('APPROVED', $cr->refresh()->status);

        // CR yang sama boleh dimasukkan ke batch baru.
        $this->draft($user, $cr);
        $this->assertSame(1, DailyTransaction::count());
    }

    public function test_batch_yang_sudah_disubmit_tidak_bisa_dihapus(): void
    {
        $user = $this->treasury();
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($user, $cr);
        $this->actingAs($user)->post('/daily-transaction/' . $batch->id . '/submit');

        $this->actingAs($user)->delete('/daily-transaction/' . $batch->id)
            ->assertSessionHas('error');

        $this->assertSame(1, DailyTransaction::count());
        $this->assertSame('QUEUED', $cr->refresh()->status);
    }

    public function test_keluarkan_item_dari_draft_lalu_submit_kosong_ditolak(): void
    {
        $user = $this->treasury();
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->draft($user, $cr);
        $item = $batch->items()->firstOrFail();

        $this->actingAs($user)
            ->post('/daily-transaction/' . $batch->id . '/draft-remove-item/' . $item->id)
            ->assertSessionMissing('error');

        $this->assertSame(0, DailyTransactionItem::count());
        $this->assertSame('APPROVED', $cr->refresh()->status);

        $this->actingAs($user)->post('/daily-transaction/' . $batch->id . '/submit')
            ->assertSessionHas('error');
        $this->assertSame('DRAFT', $batch->refresh()->status);
    }
}
