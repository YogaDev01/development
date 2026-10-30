<?php

namespace Tests\Feature;

use App\Models\DailyTransaction;
use App\Models\DailyTransactionLogNote;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/** Tab Log batch Treasury: catatan gaya chat (tabel daily_transaction_log_notes). */
class TreasuryLogNoteTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function batch(array $attrs = []): DailyTransaction
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $fund = SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE', 'is_main_account' => false]);
        $this->actingAs($this->userWithRole('treasury'))->post('/daily-transaction', [
            'type' => 'CASH_OUT', 'description' => 'Batch uji',
            'source_fund_id' => $fund->id, 'cr_ids' => [$cr->id],
        ])->assertSessionMissing('error');

        $batch = DailyTransaction::latest('id')->firstOrFail();
        if ($attrs) {
            $batch->update($attrs);
        }

        return $batch;
    }

    public function test_catatan_tersimpan_dan_muncul_di_detail_json(): void
    {
        $batch = $this->batch();

        $this->actingAs($this->userWithRole('treasury'))
            ->postJson('/daily-transaction/' . $batch->id . '/log-note', ['notes' => 'Menunggu dana masuk'])
            ->assertOk()->assertJsonPath('log.notes', 'Menunggu dana masuk');

        $this->assertSame(1, DailyTransactionLogNote::where('daily_transaction_id', $batch->id)->count());

        $logs = $this->getJson('/daily-transaction/' . $batch->id . '/detail-json')->assertOk()->json('logs');
        $this->assertContains('note', array_column($logs, 'kind'));
    }

    public function test_catatan_ditolak_untuk_batch_paid(): void
    {
        $batch = $this->batch(['status' => 'PAID']);

        $this->actingAs($this->userWithRole('treasury'))
            ->postJson('/daily-transaction/' . $batch->id . '/log-note', ['notes' => 'Terlambat'])
            ->assertStatus(422);

        $this->assertSame(0, DailyTransactionLogNote::count());
    }

    public function test_catatan_wajib_diisi(): void
    {
        $batch = $this->batch();

        $this->actingAs($this->userWithRole('treasury'))
            ->postJson('/daily-transaction/' . $batch->id . '/log-note', ['notes' => ''])
            ->assertStatus(422);
    }
}
