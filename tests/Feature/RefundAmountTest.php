<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Refund = Pengajuan - Settlement, dan NYATA begitu settlement diisi (ada
 * realisasi) — bukan menunggu settlement disetujui. Simetris dengan
 * expenseClaimAmount(). Lihat BudgetRequest::refundAmount().
 */
class RefundAmountTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function addSettlement(BudgetRequest $cr, int $total): void
    {
        BudgetRequestRealization::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Realisasi uji',
            'amount'           => $total,
            'qty'              => 1,
            'total'            => $total,
        ]);
    }

    /** Settlement diisi tapi BELUM disetujui (status REALIZED) → refund sudah muncul. */
    public function test_refund_muncul_begitu_settlement_diisi_walau_belum_disetujui(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 3_800_000,
            'status'  => 'REALIZED',
        ]);
        $this->addSettlement($cr, 3_711_308);

        $cr = BudgetRequest::findOrFail($cr->id);

        $this->assertFalse($cr->isSettled(), 'REALIZED belum termasuk settled');
        $this->assertSame(88_692, $cr->refundAmount());
    }

    /** Sudah disetujui (CLOSED) → tetap request - settlement. */
    public function test_refund_saat_sudah_closed(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 3_800_000,
            'status'  => 'CLOSED',
        ]);
        $this->addSettlement($cr, 3_711_308);

        $this->assertSame(88_692, BudgetRequest::findOrFail($cr->id)->refundAmount());
    }

    /** Settlement melebihi pengajuan → refund 0 (kelebihannya expense claim). */
    public function test_refund_nol_saat_settlement_melebihi_pengajuan(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 1_000_000,
            'status'  => 'REALIZED',
        ]);
        $this->addSettlement($cr, 1_250_000);

        $cr = BudgetRequest::findOrFail($cr->id);
        $this->assertSame(0, $cr->refundAmount());
        $this->assertSame(250_000, $cr->expenseClaimAmount());
    }

    /** Cash Advance tanpa settlement → refund 0. */
    public function test_refund_nol_saat_belum_ada_settlement(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 2_000_000,
            'status'  => 'PAID',
        ]);

        $this->assertSame(0, BudgetRequest::findOrFail($cr->id)->refundAmount());
    }

    /** Tipe selain Cash Advance → refund selalu 0 (tidak punya tahap settlement). */
    public function test_refund_nol_untuk_non_cash_advance(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cost Payment')->id,
            'amount'  => 5_000_000,
            'status'  => 'CLOSED',
        ]);

        $this->assertSame(0, BudgetRequest::findOrFail($cr->id)->refundAmount());
    }

    /*
    |--------------------------------------------------------------------------
    | realizedCostValue() — aturan settlement yang sama dengan refund
    |--------------------------------------------------------------------------
    */

    /** CA dengan settlement diisi (belum disetujui) → pakai nilai settlement. */
    public function test_realized_cost_pakai_settlement_begitu_diisi(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 3_800_000,
            'status'  => 'REALIZED',
        ]);
        $this->addSettlement($cr, 3_711_308);

        $this->assertSame(3_711_308, BudgetRequest::findOrFail($cr->id)->realizedCostValue());
    }

    /** CA dengan settlement melebihi pengajuan → pakai nilai settlement (yang lebih besar). */
    public function test_realized_cost_pakai_settlement_walau_melebihi_pengajuan(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 1_000_000,
            'status'  => 'REALIZED',
        ]);
        $this->addSettlement($cr, 1_250_000);

        $this->assertSame(1_250_000, BudgetRequest::findOrFail($cr->id)->realizedCostValue());
    }

    /** CA tanpa settlement → pakai nilai pengajuan. */
    public function test_realized_cost_pakai_pengajuan_saat_belum_ada_settlement(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cash Advance')->id,
            'amount'  => 2_000_000,
            'status'  => 'PAID',
        ]);

        $this->assertSame(2_000_000, BudgetRequest::findOrFail($cr->id)->realizedCostValue());
    }

    /** Non-CA → selalu nilai pengajuan. */
    public function test_realized_cost_non_cash_advance_pakai_pengajuan(): void
    {
        $cr = $this->makeCostRequest([
            'type_id' => $this->makeRequestType('Cost Payment')->id,
            'amount'  => 5_000_000,
            'status'  => 'CLOSED',
        ]);

        $this->assertSame(5_000_000, BudgetRequest::findOrFail($cr->id)->realizedCostValue());
    }
}
