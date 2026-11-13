<?php

namespace Tests\Feature;

use App\Models\CashAdjustment;
use App\Models\CashFlow;
use App\Models\CashFlowDetail;
use App\Models\CashOpeningBalance;
use App\Models\CashTransfer;
use App\Models\DailyTransaction;
use App\Models\Forecast;
use App\Models\ForecastDetail;
use App\Models\SourceFund;
use App\Services\CashBalanceService;
use App\Services\CashReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Cash & Bank Fase 4: laporan kas, Cashflow, Forecast. (Ditulis 2026-11-13; belum dijalankan — CLAUDE.md §4.3.)
 */
class CashReportTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private SourceFund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fund = SourceFund::create(['name' => 'BCA', 'status' => 'ACTIVE']);
        CashOpeningBalance::create(['source_fund_id' => $this->fund->id, 'amount' => 1_000_000, 'effective_date' => '2026-10-01', 'status' => 'APPROVED']);
    }

    private function manual(string $dir, int $amount, string $date): void
    {
        static $n = 0;
        $n++;
        $cf = CashFlow::create(['code' => 'CF-R' . $n, 'date' => $date, 'description' => 'uji', 'source_fund_id' => $this->fund->id,
            'status' => 'POSTED', 'total_in' => $dir === 'IN' ? $amount : 0, 'total_out' => $dir === 'OUT' ? $amount : 0]);
        CashFlowDetail::create(['cash_flow_id' => $cf->id, 'direction' => $dir, 'category' => 'OTHER', 'description' => 'uji', 'amount' => $amount]);
    }

    private function filters(array $o = []): array
    {
        return $o + ['from' => '2026-10-01', 'to' => '2026-11-30', 'fund' => null, 'days' => 30];
    }

    private function svc(): CashReportService
    {
        return app(CashReportService::class);
    }

    public function test_menu_laporan_butuh_centang_dan_ekspor_butuh_role(): void
    {
        $role = $this->role('approver');
        $user = $this->userWithRole('approver');
        $this->actingAs($user)->get('/cash-reports')->assertForbidden();

        $this->giveMenuAccess($role, 'cash-reports');
        $this->actingAs($user->fresh())->get('/cash-reports/position')->assertOk();
        // approver tidak termasuk EXPORT_ROLES.
        $this->actingAs($user->fresh())->get('/cash-reports/position?format=csv')->assertForbidden();
        $this->actingAs($user->fresh())->get('/cash-reports/position?print=1')->assertForbidden();
        $this->actingAs($user->fresh())->get('/cash-reports/tidak-ada')->assertNotFound();
    }

    public function test_ekspor_csv_untuk_finance_manager(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-reports');
        $res = $this->actingAs($this->userWithRole('finance_manager'))->get('/cash-reports/position?format=csv');

        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
    }

    public function test_laporan_posisi_sama_dengan_saldo_overview(): void
    {
        $this->manual('IN', 500_000, '2026-11-05');
        $this->manual('OUT', 200_000, '2026-11-06');

        $r = $this->svc()->build('position', $this->filters());

        $this->assertSame(1_300_000, $r['totals']['balance']);
        $this->assertSame(app(CashBalanceService::class)->snapshot('2026-11-30')['summary']['total'], $r['totals']['balance']);
    }

    public function test_cashflow_bulanan_saldo_akhir_sama_dengan_saldo_dan_berantai(): void
    {
        $this->manual('IN', 500_000, '2026-10-10');
        $this->manual('OUT', 100_000, '2026-11-06');

        $r = $this->svc()->build('cashflow', $this->filters());
        $rows = collect($r['rows'])->keyBy('period');

        $this->assertSame(1_000_000, $rows['2026-10']['opening']);
        $this->assertSame(1_500_000, $rows['2026-10']['closing']);
        $this->assertSame($rows['2026-10']['closing'], $rows['2026-11']['opening']);
        $this->assertSame(1_400_000, $rows['2026-11']['closing']);
        $this->assertSame(app(CashBalanceService::class)->snapshot('2026-11-30')['summary']['total'], $rows['2026-11']['closing']);
    }

    public function test_laporan_transfer_dan_adjustment_total_hanya_yang_disetujui(): void
    {
        $other = SourceFund::create(['name' => 'Mandiri', 'status' => 'ACTIVE']);
        foreach (['APPROVED' => 100, 'PENDING' => 999] as $st => $amt) {
            CashTransfer::create(['transfer_number' => 'TRF/R/' . $st, 'transfer_date' => '2026-11-05', 'from_fund_id' => $this->fund->id,
                'to_fund_id' => $other->id, 'amount' => $amt, 'fee' => 1, 'status' => $st]);
            CashAdjustment::create(['adjustment_number' => 'ADJ/R/' . $st, 'source_fund_id' => $this->fund->id, 'effective_date' => '2026-11-05',
                'type' => 'INTEREST_INCOME', 'direction' => 'IN', 'amount' => $amt, 'reason' => 'uji', 'status' => $st]);
        }

        $this->assertSame(100, $this->svc()->build('transfers', $this->filters())['totals']['amount']);
        $this->assertSame(100, $this->svc()->build('adjustments', $this->filters())['totals']['in']);
        $this->assertCount(2, $this->svc()->build('transfers', $this->filters())['rows']);
    }

    public function test_transaksi_tanpa_sisi_transfer_pada_total(): void
    {
        $other = SourceFund::create(['name' => 'Mandiri', 'status' => 'ACTIVE']);
        $this->manual('IN', 500_000, '2026-11-05');
        DailyTransaction::create(['code' => 'TU-R', 'date' => '2026-11-06', 'type' => 'TOP_UP', 'status' => 'PAID',
            'source_fund_id' => $this->fund->id, 'to_source_fund_id' => $other->id, 'captured_amount' => 300_000]);

        $r = $this->svc()->build('transactions', $this->filters());

        $this->assertSame(500_000, $r['totals']['in']);
        $this->assertSame(0, $r['totals']['out']);
        $this->assertCount(3, $r['rows']); // 1 manual + 2 sisi transfer
    }

    public function test_forecast_dipisah_dari_kas_aktual_dan_hanya_batch_done_terjadwal(): void
    {
        $user = $this->userWithRole('finance_manager');
        DailyTransaction::create(['code' => 'B-DONE', 'date' => '2026-11-06', 'type' => 'CASH_OUT', 'status' => 'DONE',
            'source_fund_id' => $this->fund->id, 'captured_amount' => 400_000]);
        DailyTransaction::create(['code' => 'B-PEND', 'date' => '2026-11-06', 'type' => 'CASH_OUT', 'status' => 'PENDING_APPROVER',
            'source_fund_id' => $this->fund->id, 'captured_amount' => 700_000]);

        $fc = Forecast::create(['notes' => 'uji', 'date_start' => now()->subDay(), 'date_end' => now()->addDays(10), 'status' => 'ACTIVE', 'created_by' => $user->id]);
        ForecastDetail::create(['forecast_id' => $fc->id, 'description' => 'x', 'type' => 'IN', 'amount' => 900_000, 'actual_amount' => 100_000]);

        $o = $this->svc()->outlook(30);

        $this->assertSame(1_000_000, $o['actual']);              // forecast & batch tidak mengubah kas aktual
        $this->assertSame(400_000, $o['scheduled']);             // hanya DONE
        $this->assertSame(700_000, $o['awaitingBatch']);         // terpisah, tidak masuk proyeksi
        $this->assertSame(600_000, $o['confirmedProj']);         // 1.000.000 − 400.000
        $this->assertSame(800_000, $o['fIn']);                   // sisa forecast = 900.000 − 100.000
        $this->assertSame(1_400_000, $o['forecast_proj']);       // 600.000 + 800.000
    }

    public function test_ringkasan_outlook_di_forecast_hanya_untuk_pemegang_menu_cash_overview(): void
    {
        $role = $this->role('finance_manager');
        $this->giveMenuAccess($role, 'forecast');
        $user = $this->userWithRole('finance_manager');

        $this->actingAs($user)->get('/forecast')->assertOk()->assertDontSee('tiga lapis yang tidak dicampur');

        $this->giveMenuAccess($role, 'cash-overview');
        $this->actingAs($user->fresh())->get('/forecast')->assertOk()->assertSee('tiga lapis yang tidak dicampur');
    }
}
