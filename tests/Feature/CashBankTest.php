<?php

namespace Tests\Feature;

use App\Models\CashFlow;
use App\Models\CashFlowDetail;
use App\Models\CashAdjustment;
use App\Models\CashOpeningBalance;
use App\Models\CashTransfer;
use App\Models\DailyTransaction;
use App\Models\SourceFund;
use App\Services\CashBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Cash & Bank Fase 1: Cash Overview, Bank Accounts, saldo awal, saldo per rekening.
 * (Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.)
 */
class CashBankTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function fund(string $name = 'BCA Operasional', array $extra = []): SourceFund
    {
        return SourceFund::create(array_merge(['name' => $name, 'status' => 'ACTIVE'], $extra));
    }

    private function opening(SourceFund $f, int $amount, string $date, string $status = 'APPROVED', ?int $by = null): CashOpeningBalance
    {
        return CashOpeningBalance::create([
            'source_fund_id' => $f->id, 'amount' => $amount, 'effective_date' => $date,
            'status' => $status, 'created_by' => $by,
        ]);
    }

    private function manual(SourceFund $f, string $dir, int $amount, string $date, string $status = 'POSTED'): void
    {
        static $n = 0;
        $n++;
        $cf = CashFlow::create([
            'code' => 'CF-T' . $n, 'date' => $date, 'description' => 'uji', 'source_fund_id' => $f->id,
            'status' => $status, 'total_in' => $dir === 'IN' ? $amount : 0, 'total_out' => $dir === 'OUT' ? $amount : 0,
        ]);
        CashFlowDetail::create([
            'cash_flow_id' => $cf->id, 'direction' => $dir, 'category' => 'OTHER', 'description' => 'uji', 'amount' => $amount,
        ]);
    }

    public function test_menu_cash_bank_tidak_otomatis_dari_treasury(): void
    {
        $role = $this->role('finance');
        $this->giveMenuAccess($role, 'finance-daily-transaction', 'cash-receipt', 'cash-management');
        $user = $this->userWithRole('finance');

        $this->actingAs($user)->get('/cash-overview')->assertForbidden();
        $this->actingAs($user)->get('/bank-accounts')->assertForbidden();

        $this->giveMenuAccess($role, 'cash-overview', 'bank-accounts');
        $this->actingAs($user->fresh())->get('/cash-overview')->assertOk();
        $this->actingAs($user->fresh())->get('/bank-accounts')->assertOk();
    }

    public function test_hanya_superadmin_dan_finance_manager_boleh_menulis_rekening(): void
    {
        $this->giveMenuAccess($this->role('finance'), 'bank-accounts');
        $this->giveMenuAccess($this->role('finance_manager'), 'bank-accounts');

        $this->actingAs($this->userWithRole('finance'))->get('/bank-accounts/create')->assertForbidden();
        $this->actingAs($this->userWithRole('finance_manager'))->get('/bank-accounts/create')->assertOk();
    }

    public function test_saldo_awal_ditambah_masuk_dikurangi_keluar(): void
    {
        $f = $this->fund();
        $this->opening($f, 1_000_000, '2026-11-01');
        $this->manual($f, 'IN', 500_000, '2026-11-05');
        $this->manual($f, 'OUT', 200_000, '2026-11-06');

        $acc = app(CashBalanceService::class)->snapshot()['accounts']->first();

        $this->assertSame(1_300_000, $acc['balance']);
    }

    public function test_draft_cancelled_dan_saldo_awal_pending_tidak_memengaruhi_saldo(): void
    {
        $f = $this->fund();
        $this->opening($f, 9_000_000, '2026-11-01', 'PENDING');
        $this->manual($f, 'IN', 500_000, '2026-11-05', 'DRAFT');
        $this->manual($f, 'IN', 700_000, '2026-11-05', 'CANCELLED');

        $acc = app(CashBalanceService::class)->snapshot()['accounts']->first();

        $this->assertSame(0, $acc['balance']);
        $this->assertFalse($acc['has_opening']);
    }

    public function test_transaksi_sebelum_tanggal_efektif_tidak_dihitung(): void
    {
        $f = $this->fund();
        $this->opening($f, 1_000_000, '2026-11-10');
        $this->manual($f, 'IN', 400_000, '2026-11-09');

        $this->assertSame(1_000_000, app(CashBalanceService::class)->snapshot()['accounts']->first()['balance']);
    }

    public function test_top_up_memindahkan_saldo_antar_rekening_tanpa_mengubah_total(): void
    {
        $main = $this->fund('Main', ['is_main_account' => true]);
        $ops  = $this->fund('Ops');
        $this->opening($main, 5_000_000, '2026-11-01');
        $this->opening($ops, 0, '2026-11-01');

        DailyTransaction::create([
            'code' => 'TU-1', 'date' => '2026-11-05', 'type' => 'TOP_UP', 'status' => 'PAID',
            'source_fund_id' => $main->id, 'to_source_fund_id' => $ops->id, 'captured_amount' => 2_000_000,
        ]);

        $snap = app(CashBalanceService::class)->snapshot();
        $byName = $snap['accounts']->keyBy(fn ($a) => $a['fund']->name);

        $this->assertSame(3_000_000, $byName['Main']['balance']);
        $this->assertSame(2_000_000, $byName['Ops']['balance']);
        $this->assertSame(5_000_000, $snap['summary']['total']);
    }

    public function test_top_up_belum_dibayar_tidak_memengaruhi_saldo(): void
    {
        $main = $this->fund('Main');
        $ops  = $this->fund('Ops');
        $this->opening($main, 5_000_000, '2026-11-01');

        DailyTransaction::create([
            'code' => 'TU-2', 'date' => '2026-11-05', 'type' => 'TOP_UP', 'status' => 'PENDING_APPROVER',
            'source_fund_id' => $main->id, 'to_source_fund_id' => $ops->id, 'captured_amount' => 2_000_000,
        ]);

        $this->assertSame(5_000_000, app(CashBalanceService::class)->snapshot()['summary']['total']);
    }

    public function test_pembuat_saldo_awal_tidak_boleh_memverifikasi_sendiri(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'bank-accounts');
        $maker = $this->userWithRole('finance_manager');
        $other = $this->userWithRole('finance_manager');
        $f  = $this->fund();
        $ob = $this->opening($f, 1_000_000, '2026-11-01', 'PENDING', $maker->id);

        $this->actingAs($maker)->post("/bank-accounts/opening/{$ob->id}/verify")->assertSessionHas('error');
        $this->assertSame('PENDING', $ob->fresh()->status);

        $this->actingAs($other)->post("/bank-accounts/opening/{$ob->id}/verify")->assertSessionHas('success');
        $this->assertSame('APPROVED', $ob->fresh()->status);
        $this->assertSame($other->id, $ob->fresh()->verified_by);
    }

    public function test_saldo_awal_approved_terkunci_dari_perubahan_langsung(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'bank-accounts');
        $user = $this->userWithRole('finance_manager');
        $f  = $this->fund();
        $ob = $this->opening($f, 1_000_000, '2026-11-01', 'APPROVED', $user->id);

        $this->actingAs($user)->post("/bank-accounts/{$f->id}/opening", [
            'amount' => '9.999.999', 'effective_date' => '2026-11-01',
        ])->assertSessionHas('error');

        $this->assertSame(1_000_000, $ob->fresh()->amount);
    }

    public function test_rekening_tidak_punya_endpoint_edit_saldo_langsung(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'bank-accounts');
        $user = $this->userWithRole('finance_manager');
        $f = $this->fund();

        $this->actingAs($user)->put("/bank-accounts/{$f->id}", [
            'name' => 'BCA', 'account_type' => 'BANK', 'currency' => 'idr', 'status' => 'ACTIVE', 'balance' => 99,
        ])->assertRedirect();

        $this->assertSame(0, app(CashBalanceService::class)->snapshot()['summary']['total']);
        $this->assertSame('IDR', $f->fresh()->currency);
    }

    // ---- Fase 2: Internal Transfer & Cash Adjustment ----

    private function transfer(SourceFund $a, SourceFund $b, int $amount, int $fee, string $status, int $by): CashTransfer
    {
        static $n = 0;
        $n++;

        return CashTransfer::create([
            'transfer_number' => 'TRF/T/' . $n, 'transfer_date' => '2026-11-05', 'from_fund_id' => $a->id,
            'to_fund_id' => $b->id, 'amount' => $amount, 'fee' => $fee, 'status' => $status, 'created_by' => $by,
        ]);
    }

    private function adjustment(SourceFund $f, string $type, string $dir, int $amount, int $by): CashAdjustment
    {
        static $n = 0;
        $n++;

        return CashAdjustment::create([
            'adjustment_number' => 'ADJ/T/' . $n, 'source_fund_id' => $f->id, 'effective_date' => '2026-11-05',
            'type' => $type, 'direction' => $dir, 'amount' => $amount, 'reason' => 'Uji penyesuaian',
            'status' => 'PENDING', 'created_by' => $by,
        ]);
    }

    public function test_transfer_disetujui_memindahkan_saldo_dan_biaya_jadi_pengeluaran(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-transfers');
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $mandiri = $this->fund('Mandiri');
        $bca     = $this->fund('BCA');
        $this->opening($mandiri, 20_000_000, '2026-11-01');
        $this->opening($bca, 0, '2026-11-01');
        $t = $this->transfer($mandiri, $bca, 10_000_000, 6_500, 'PENDING', $maker->id);

        // Belum disetujui: saldo tidak berubah.
        $this->assertSame(20_000_000, app(CashBalanceService::class)->snapshot()['summary']['total']);

        $this->actingAs($boss)->post("/cash-transfers/{$t->id}/approve")->assertSessionHas('success');

        $by = app(CashBalanceService::class)->snapshot()['accounts']->keyBy(fn ($a) => $a['fund']->name);
        $this->assertSame(10_000_000 - 6_500, $by['Mandiri']['balance']);
        $this->assertSame(10_000_000, $by['BCA']['balance']);
        // Konsolidasi hanya berkurang oleh biaya transfer.
        $this->assertSame(20_000_000 - 6_500, app(CashBalanceService::class)->snapshot()['summary']['total']);
    }

    public function test_transfer_tidak_boleh_disetujui_pembuatnya_atau_dua_kali(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-transfers');
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $a = $this->fund('A');
        $b = $this->fund('B');
        $this->opening($a, 5_000_000, '2026-11-01');
        $t = $this->transfer($a, $b, 1_000_000, 0, 'PENDING', $maker->id);

        $this->actingAs($maker)->post("/cash-transfers/{$t->id}/approve")->assertSessionHas('error');
        $this->assertSame('PENDING', $t->fresh()->status);

        $this->actingAs($boss)->post("/cash-transfers/{$t->id}/approve")->assertSessionHas('success');
        $this->actingAs($boss)->post("/cash-transfers/{$t->id}/approve")->assertSessionHas('error');
        // Dua sisi transfer tercatat sekali (bukan dua kali): rekening asal 4 jt, tujuan 1 jt, total tetap 5 jt.
        $by = app(CashBalanceService::class)->snapshot()['accounts']->keyBy(fn ($x) => $x['fund']->name);
        $this->assertSame(4_000_000, $by['A']['balance']);
        $this->assertSame(1_000_000, $by['B']['balance']);
    }

    public function test_transfer_ditolak_bila_saldo_asal_tidak_cukup(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-transfers');
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $a = $this->fund('A');
        $b = $this->fund('B');
        $this->opening($a, 1_000_000, '2026-11-01');
        $t = $this->transfer($a, $b, 1_000_000, 1_000, 'PENDING', $maker->id);

        $this->actingAs($boss)->post("/cash-transfers/{$t->id}/approve")->assertSessionHas('error');
        $this->assertSame('PENDING', $t->fresh()->status);
    }

    public function test_transfer_pending_ditolak_dibatalkan_tidak_memengaruhi_saldo(): void
    {
        $maker = $this->userWithRole('finance_manager');
        $a = $this->fund('A');
        $b = $this->fund('B');
        $this->opening($a, 5_000_000, '2026-11-01');
        foreach (['PENDING', 'REJECTED', 'CANCELLED'] as $st) {
            $this->transfer($a, $b, 1_000_000, 500, $st, $maker->id);
        }

        $this->assertSame(5_000_000, app(CashBalanceService::class)->snapshot()['summary']['total']);
    }

    public function test_adjustment_hanya_berlaku_setelah_disetujui_pengguna_lain(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-adjustments');
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $f = $this->fund();
        $this->opening($f, 1_000_000, '2026-11-01');
        $a = $this->adjustment($f, 'INTEREST_INCOME', 'IN', 50_000, $maker->id);

        $this->assertSame(1_000_000, app(CashBalanceService::class)->snapshot()['summary']['total']);
        $this->actingAs($maker)->post("/cash-adjustments/{$a->id}/approve")->assertSessionHas('error');
        $this->actingAs($boss)->post("/cash-adjustments/{$a->id}/approve")->assertSessionHas('success');
        $this->assertSame(1_050_000, app(CashBalanceService::class)->snapshot()['summary']['total']);
    }

    public function test_adjustment_keluar_tidak_boleh_membuat_saldo_negatif(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cash-adjustments');
        $maker = $this->userWithRole('finance_manager');
        $boss  = $this->userWithRole('finance_manager');
        $f = $this->fund();
        $this->opening($f, 10_000, '2026-11-01');
        $a = $this->adjustment($f, 'BANK_CHARGE', 'OUT', 50_000, $maker->id);

        $this->actingAs($boss)->post("/cash-adjustments/{$a->id}/approve")->assertSessionHas('error');
        $this->assertSame('PENDING', $a->fresh()->status);
    }

    public function test_role_finance_tidak_bisa_menyetujui(): void
    {
        $this->giveMenuAccess($this->role('finance'), 'cash-transfers', 'cash-adjustments');
        $user = $this->userWithRole('finance');

        $this->actingAs($user)->post('/cash-transfers/1/approve')->assertForbidden();
        $this->actingAs($user)->post('/cash-adjustments/1/approve')->assertForbidden();
        $this->actingAs($user)->get('/cash-transfers/create')->assertOk();
    }

    public function test_viewer_transaksi_butuh_menu_sendiri(): void
    {
        $role = $this->role('finance_manager');
        $user = $this->userWithRole('finance_manager');
        $this->actingAs($user)->get('/cash-transactions')->assertForbidden();

        $this->giveMenuAccess($role, 'cash-transactions');
        $this->actingAs($user->fresh())->get('/cash-transactions')->assertOk();
    }
}
