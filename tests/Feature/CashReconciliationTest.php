<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\CashFlow;
use App\Models\CashFlowDetail;
use App\Models\CashOpeningBalance;
use App\Models\CashReconciliation;
use App\Models\CashReconciliationMatch;
use App\Models\SourceFund;
use App\Services\CashBalanceService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Cash & Bank Fase 3: rekonsiliasi bank. (Ditulis 2026-11-13; belum dijalankan — CLAUDE.md §4.3.)
 */
class CashReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private SourceFund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fund = SourceFund::create(['name' => 'BCA', 'status' => 'ACTIVE']);
        CashOpeningBalance::create([
            'source_fund_id' => $this->fund->id, 'amount' => 1_000_000, 'effective_date' => '2026-11-01', 'status' => 'APPROVED',
        ]);
    }

    private function ledger(string $dir, int $amount, string $date, string $code): void
    {
        $cf = CashFlow::create([
            'code' => $code, 'date' => $date, 'description' => 'uji ' . $code, 'source_fund_id' => $this->fund->id,
            'status' => 'POSTED', 'total_in' => $dir === 'IN' ? $amount : 0, 'total_out' => $dir === 'OUT' ? $amount : 0,
        ]);
        CashFlowDetail::create(['cash_flow_id' => $cf->id, 'direction' => $dir, 'category' => 'OTHER', 'description' => 'uji', 'amount' => $amount]);
    }

    private function rec(int $statementBalance, string $status = 'DRAFT', ?int $by = null): CashReconciliation
    {
        static $n = 0;
        $n++;

        return CashReconciliation::create([
            'number' => 'REC/T/' . $n, 'source_fund_id' => $this->fund->id, 'period_start' => '2026-11-01',
            'period_end' => '2026-11-30', 'statement_ending_balance' => $statementBalance, 'status' => $status,
            'created_by' => $by, 'submitted_by' => $by,
        ]);
    }

    private function line(CashReconciliation $r, string $date, int $debit, int $credit, string $ref = '', string $desc = 'uji'): BankStatementLine
    {
        static $n = 0;
        $n++;

        return BankStatementLine::create([
            'reconciliation_id' => $r->id, 'source_fund_id' => $this->fund->id, 'txn_date' => $date, 'description' => $desc,
            'reference' => $ref, 'debit' => $debit, 'credit' => $credit, 'dedupe_hash' => sha1('t' . $n),
        ]);
    }

    private function svc(): ReconciliationService
    {
        return app(ReconciliationService::class);
    }

    public function test_menu_rekonsiliasi_butuh_centang_sendiri(): void
    {
        $role = $this->role('finance_manager');
        $user = $this->userWithRole('finance_manager');
        $this->actingAs($user)->get('/cash-reconciliation')->assertForbidden();

        $this->giveMenuAccess($role, 'cash-reconciliation');
        $this->actingAs($user->fresh())->get('/cash-reconciliation')->assertOk();
    }

    public function test_parse_nominal_dan_tanggal(): void
    {
        $s = $this->svc();
        $this->assertSame(1234567, $s->parseAmount('1.234.567'));
        $this->assertSame(1234567, $s->parseAmount('Rp 1,234,567.00'));
        $this->assertSame(-5000, $s->parseAmount('-5.000'));
        $this->assertNull($s->parseAmount('abc'));
        $this->assertSame('2026-11-05', $s->parseDate('05/11/2026'));
        $this->assertNull($s->parseDate('31/02/2026'));
    }

    public function test_csv_mendeteksi_galat_duplikat_dan_di_luar_periode(): void
    {
        $rec = $this->rec(0);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "date,description,reference,debit,credit,balance\n"
            . "2026-11-05,Masuk,R1,0,500000,1500000\n"
            . "2026-11-06,Keluar,R2,100000,0,1400000\n"
            . "2026-12-01,Luar periode,R3,0,1000,\n"
            . "2026-11-07,Dobel isi,R4,1000,1000,\n");

        $res = $this->svc()->parseCsv($path, $rec);
        $states = collect($res['rows'])->pluck('state')->all();
        $this->assertSame(['OK', 'OK', 'ERROR', 'ERROR'], $states);

        // Impor ulang baris yang sama → duplikat.
        foreach (collect($res['rows'])->where('state', 'OK') as $r) {
            BankStatementLine::create([
                'reconciliation_id' => $rec->id, 'source_fund_id' => $this->fund->id, 'txn_date' => $r['data']['date'],
                'debit' => $r['data']['debit'], 'credit' => $r['data']['credit'], 'dedupe_hash' => $r['data']['hash'],
            ]);
        }
        $again = $this->svc()->parseCsv($path, $rec);
        $this->assertSame(['DUPLICATE', 'DUPLICATE', 'ERROR', 'ERROR'], collect($again['rows'])->pluck('state')->all());
        @unlink($path);
    }

    public function test_pencocokan_otomatis_satu_baris_ledger_hanya_sekali(): void
    {
        $this->ledger('IN', 500_000, '2026-11-05', 'CF-A');
        $rec = $this->rec(1_500_000);
        $l1 = $this->line($rec, '2026-11-05', 0, 500_000);
        $l2 = $this->line($rec, '2026-11-06', 0, 500_000); // nominal sama, hanya satu transaksi TESYS

        $n = $this->svc()->autoMatch($rec, null);

        $this->assertSame(1, $n);
        $this->assertSame(1, CashReconciliationMatch::count());
        $this->assertSame('MATCHED', $this->svc()->lineStatus($l1->fresh('matches')));
        $this->assertSame('UNMATCHED', $this->svc()->lineStatus($l2->fresh('matches')));
    }

    public function test_pencocokan_tidak_melewati_arah_atau_toleransi_tanggal(): void
    {
        $this->ledger('OUT', 100_000, '2026-11-05', 'CF-B');
        $rec = $this->rec(900_000);
        $this->line($rec, '2026-11-05', 0, 100_000);   // arah beda (credit vs OUT)
        $this->line($rec, '2026-11-20', 100_000, 0);   // tanggal terlalu jauh

        $this->assertSame(0, $this->svc()->autoMatch($rec, null));
    }

    public function test_selisih_nol_bila_semua_cocok_dan_outstanding_dihitung(): void
    {
        $this->ledger('IN', 500_000, '2026-11-05', 'CF-C');   // cocok
        $this->ledger('OUT', 200_000, '2026-11-29', 'CF-D');  // outstanding payment (belum di bank)
        $rec = $this->rec(1_500_000);                         // 1.000.000 + 500.000 (belum dikurangi 200.000)
        $this->line($rec, '2026-11-05', 0, 500_000);
        $this->svc()->autoMatch($rec, null);

        $s = $this->svc()->summary($rec->fresh());

        $this->assertSame(200_000, $s['outstanding_payments']);
        $this->assertSame(1_300_000, $s['adjusted_bank']);
        $this->assertSame(1_300_000, $s['ledger_balance']);
        $this->assertSame(0, $s['difference']);
        $this->assertFalse($s['needs_exception']);
    }

    public function test_baris_bank_belum_tercatat_masuk_ke_penyesuaian_dan_butuh_pengecualian(): void
    {
        $rec = $this->rec(1_050_000);                   // bunga 50.000 ada di bank, belum di TESYS
        $this->line($rec, '2026-11-10', 0, 50_000, '', 'BUNGA');

        $s = $this->svc()->summary($rec->fresh());

        $this->assertSame(50_000, $s['bank_credits_not_booked']);
        $this->assertSame(0, $s['difference']);
        $this->assertTrue($s['needs_exception']); // baris belum cocok
    }

    public function test_submit_tanpa_alasan_ditolak_bila_ada_selisih(): void
    {
        $this->giveMenuAccess($this->role('finance'), 'cash-reconciliation');
        $user = $this->userWithRole('finance');
        $rec  = $this->rec(9_999_999);

        $this->actingAs($user)->post("/cash-reconciliation/{$rec->id}/submit")->assertSessionHas('error');
        $this->assertSame('DRAFT', $rec->fresh()->status);
    }

    public function test_approve_oleh_pembuat_ditolak_dan_periode_terkunci(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-reconciliation', 'cash-transfers');
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $rec   = $this->rec(1_000_000, 'SUBMITTED', $maker->id);

        $this->actingAs($maker)->post("/cash-reconciliation/{$rec->id}/approve")->assertSessionHas('error');
        $this->assertSame('SUBMITTED', $rec->fresh()->status);

        $this->actingAs($boss)->post("/cash-reconciliation/{$rec->id}/approve")->assertSessionHas('success');
        $this->assertSame('APPROVED', $rec->fresh()->status);

        // Terkunci: pencocokan otomatis dan impor ditolak.
        $this->actingAs($boss)->post("/cash-reconciliation/{$rec->id}/auto-match")->assertSessionHas('error');

        // Dokumen Cash & Bank baru di periode terkunci ditolak.
        $msg = app(CashBalanceService::class)->dateInLockedPeriod($this->fund->id, '2026-11-15');
        $this->assertNotNull($msg);
        $this->assertNull(app(CashBalanceService::class)->dateInLockedPeriod($this->fund->id, '2026-12-15'));
    }

    public function test_buka_ulang_hanya_superadmin_dengan_alasan_dan_tercatat(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-reconciliation');
        $this->giveMenuAccess($this->role('superadmin'), 'cash-reconciliation');
        $rec = $this->rec(1_000_000, 'APPROVED');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post("/cash-reconciliation/{$rec->id}/reopen", ['reason' => 'koreksi data'])->assertForbidden();

        $this->actingAs($this->userWithRole('superadmin'))
            ->post("/cash-reconciliation/{$rec->id}/reopen", ['reason' => 'koreksi data'])->assertSessionHas('success');

        $this->assertSame('DRAFT', $rec->fresh()->status);
        $this->assertDatabaseHas('cash_reconciliation_logs', ['reconciliation_id' => $rec->id, 'action' => 'REOPENED']);
    }

    public function test_periode_bertumpuk_untuk_rekening_sama_ditolak(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-reconciliation');
        $this->rec(0);

        $this->actingAs($this->userWithRole('finance_manager'))->post('/cash-reconciliation', [
            'source_fund_id' => $this->fund->id, 'period_start' => '2026-11-15', 'period_end' => '2026-11-20',
            'statement_ending_balance' => '1.000.000',
        ])->assertSessionHas('error');
    }
}
