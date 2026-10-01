<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\BudgetRequestSecondPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * H2 — verifikasi settlement tidak boleh membuat dua Second Payment.
 *
 * financeVerifySettlement() dulu memeriksa status tanpa kunci, jadi dua
 * verifikasi bersamaan masing-masing membuat satu baris Second Payment.
 * Sekarang perubahan status dan pembuatan Second Payment berada di dalam
 * transitionStatus() (kunci baris + periksa ulang status).
 *
 * Balapan disimulasikan seperti TreasuryBatchLockTest: keadaan "staf lain
 * sudah memverifikasi" ditulis tepat setelah CR dibaca tanpa kunci.
 */
class SettlementVerifyLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function settleChecked(int $amount = 1_000_000, int $realisasi = 1_250_000): BudgetRequest
    {
        $cr = $this->makeCostRequest(['status' => 'SETTLE_CHECKED', 'amount' => $amount]);

        BudgetRequestRealization::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Realisasi uji',
            'amount'            => $realisasi,
            'qty'               => 1,
            'total'             => $realisasi,
        ]);

        return $cr;
    }

    private function verify(User $user, BudgetRequest $cr)
    {
        return $this->actingAs($user)
            ->post('/budget-request/' . $cr->id . '/finance-verify-settlement', ['notes' => 'ok']);
    }

    private function verifiedLogs(BudgetRequest $cr): int
    {
        return $cr->statusLogs()->where('action_type', 'settlement_verified')->count();
    }

    public function test_verifikasi_dengan_expense_claim_membuat_satu_second_payment(): void
    {
        $cr = $this->settleChecked();

        $this->verify($this->userWithRole('finance'), $cr)->assertSessionMissing('error');

        $this->assertSame('SETTLE_VERIFIED', $cr->refresh()->status);
        $this->assertSame(1, $cr->secondPayments()->count());
        $this->assertSame(250_000, (int) $cr->secondPayments()->value('amount'));
        $this->assertSame('PENDING', $cr->secondPayments()->value('status'));
        $this->assertSame(1, $this->verifiedLogs($cr));
    }

    public function test_verifikasi_tanpa_selisih_tidak_membuat_second_payment(): void
    {
        $cr = $this->settleChecked(1_000_000, 900_000);

        $this->verify($this->userWithRole('finance'), $cr)->assertSessionMissing('error');

        $this->assertSame('SETTLE_VERIFIED', $cr->refresh()->status);
        $this->assertSame(0, $cr->secondPayments()->count());
    }

    public function test_verifikasi_kedua_berurutan_ditolak(): void
    {
        $cr = $this->settleChecked();
        $user = $this->userWithRole('finance');

        $this->verify($user, $cr);
        $this->verify($user, $cr)->assertSessionHas('error', 'Status tidak valid.');

        $this->assertSame(1, $cr->secondPayments()->count());
        $this->assertSame(1, $this->verifiedLogs($cr));
    }

    public function test_verifikasi_yang_kalah_balapan_tidak_membuat_second_payment_kedua(): void
    {
        $cr = $this->settleChecked();

        // Tepat setelah CR dibaca tanpa kunci, staf lain menyelesaikan
        // verifikasi yang sama (status pindah + Second Payment dibuat).
        $sudah = false;
        DB::listen(function ($query) use ($cr, &$sudah) {
            if ($sudah || ! str_contains($query->sql, 'from `budget_request`')) {
                return;
            }
            $sudah = true;
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'SETTLE_VERIFIED']);
            BudgetRequestSecondPayment::create([
                'budget_request_id' => $cr->id,
                'amount'            => 250_000,
                'status'            => 'PENDING',
            ]);
        });

        $this->verify($this->userWithRole('finance'), $cr)
            ->assertSessionHas('error', 'Status sudah berubah — muat ulang halaman.');

        $this->assertSame(1, $cr->secondPayments()->count());
        $this->assertSame(0, $this->verifiedLogs($cr));
    }

    public function test_bulk_todo_list_tetap_memverifikasi_semua_cr(): void
    {
        $crA = $this->settleChecked();
        $crB = $this->settleChecked();

        $finance = $this->userWithRole('finance');
        $this->giveMenuAccess($finance->role, 'todo-list');

        $this->actingAs($finance)
            ->post('/todo-list/bulk/settle-verify/approve', ['ids' => [$crA->id, $crB->id]])
            ->assertSessionHas('success', '2 item berhasil diproses.');

        foreach ([$crA, $crB] as $cr) {
            $this->assertSame('SETTLE_VERIFIED', $cr->refresh()->status);
            $this->assertSame(1, $cr->secondPayments()->count());
        }
    }
}
