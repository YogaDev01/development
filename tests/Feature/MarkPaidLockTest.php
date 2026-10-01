<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestSecondPayment;
use App\Models\DailyTransaction;
use App\Models\DailyTransactionAttachment;
use App\Models\SourceFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * M1 — Mark Paid batch Treasury tidak boleh diproses dua kali.
 *
 * markPaid() dulu memeriksa isDone() tanpa kunci, jadi dua klik bersamaan
 * sama-sama lolos: lampiran bukti transfer dan log status CR tercatat dua
 * kali, dan charge / paid_by yang pertama tertimpa. Sekarang batch dikunci
 * di awal transaksi dan statusnya diperiksa ulang sebelum apa pun disimpan.
 *
 * Balapan disimulasikan seperti TreasuryBatchLockTest: batch diubah menjadi
 * PAID tepat setelah dibaca tanpa kunci.
 */
class MarkPaidLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');
    }

    private function doneBatch(BudgetRequest $cr, ?BudgetRequestSecondPayment $sp = null): DailyTransaction
    {
        $batch = DailyTransaction::create([
            'code'           => 'DT-UJI-' . uniqid(),
            'date'           => now()->toDateString(),
            'type'           => 'CASH_OUT',
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE'])->id,
            'status'         => 'DONE',
            'created_by'     => $cr->applicant_id,
        ]);

        $batch->items()->create([
            'budget_request_id' => $cr->id,
            'second_payment_id' => $sp?->id,
            'status'            => 'INCLUDED',
        ]);

        return $batch;
    }

    private function markPaid(User $user, DailyTransaction $batch, string $charge = '6.500')
    {
        return $this->actingAs($user)->post('/daily-transaction/' . $batch->id . '/mark-paid', [
            'charge'        => $charge,
            'payment_notes' => 'Transfer uji',
            'attachments'   => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')],
        ]);
    }

    private function paidLogs(BudgetRequest $cr): int
    {
        return $cr->statusLogs()->where('action_type', 'paid')->count();
    }

    public function test_cash_advance_menjadi_paid_dan_lampiran_tersimpan(): void
    {
        $cr = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->doneBatch($cr);

        $this->markPaid($this->userWithRole('treasury'), $batch)->assertSessionMissing('error');

        $this->assertSame('PAID', $batch->refresh()->status);
        $this->assertSame(6_500, (int) $batch->charge);
        $this->assertSame('PAID', $cr->refresh()->status);
        $this->assertSame(1, $this->paidLogs($cr));
        $this->assertSame(1, DailyTransactionAttachment::where('daily_transaction_id', $batch->id)->count());
    }

    public function test_non_cash_advance_langsung_closed(): void
    {
        $cr = $this->makeCostRequest([
            'status'  => 'QUEUED',
            'type_id' => $this->makeRequestType('Cost Payment')->id,
        ]);
        $batch = $this->doneBatch($cr);

        $this->markPaid($this->userWithRole('treasury'), $batch)->assertSessionMissing('error');

        $this->assertSame('CLOSED', $cr->refresh()->status);
    }

    public function test_second_payment_dibayar_dan_cr_induk_ditutup(): void
    {
        $cr = $this->makeCostRequest(['status' => 'QUEUED']);
        $sp = BudgetRequestSecondPayment::create([
            'budget_request_id' => $cr->id,
            'amount'            => 250_000,
            'status'            => 'PENDING',
        ]);
        $batch = $this->doneBatch($cr, $sp);

        $this->markPaid($this->userWithRole('treasury'), $batch)->assertSessionMissing('error');

        $this->assertSame('PAID', $sp->refresh()->status);
        $this->assertSame('CLOSED', $cr->refresh()->status);
    }

    public function test_mark_paid_kedua_berurutan_ditolak(): void
    {
        $user = $this->userWithRole('treasury');
        $cr = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->doneBatch($cr);

        $this->markPaid($user, $batch);
        $this->markPaid($user, $batch, '9.999')
            ->assertSessionHas('error', 'Hanya batch DONE yang dapat ditandai PAID.');

        $this->assertSame(6_500, (int) $batch->refresh()->charge);
        $this->assertSame(1, $this->paidLogs($cr));
        $this->assertSame(1, DailyTransactionAttachment::where('daily_transaction_id', $batch->id)->count());
    }

    public function test_mark_paid_yang_kalah_balapan_tidak_menyimpan_apa_pun(): void
    {
        $cr = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->doneBatch($cr);

        // Tepat setelah batch dibaca tanpa kunci, staf lain menyelesaikan
        // Mark Paid batch yang sama dengan biaya bank 6.500.
        $sudah = false;
        DB::listen(function ($query) use ($batch, &$sudah) {
            if ($sudah || ! str_contains($query->sql, 'from `daily_transactions`')) {
                return;
            }
            $sudah = true;
            DB::table('daily_transactions')->where('id', $batch->id)
                ->update(['status' => 'PAID', 'charge' => 6_500]);
        });

        $this->markPaid($this->userWithRole('treasury'), $batch, '9.999')
            ->assertSessionHas('error', 'Batch ini baru saja ditandai PAID oleh pengguna lain — muat ulang halaman.');

        $this->assertSame(6_500, (int) $batch->refresh()->charge);
        $this->assertSame('QUEUED', $cr->refresh()->status);
        $this->assertSame(0, $this->paidLogs($cr));
        $this->assertSame(0, DailyTransactionAttachment::where('daily_transaction_id', $batch->id)->count());
        $this->assertSame([], Storage::disk('secure')->allFiles());
    }
}
