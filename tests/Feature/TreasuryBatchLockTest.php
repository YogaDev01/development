<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestSecondPayment;
use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\SourceFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * H1 — satu CR tidak boleh masuk dua batch Treasury aktif.
 *
 * Pemeriksaan kelayakan di storeCashOut() berjalan tanpa kunci, jadi dua
 * Treasury yang membuat batch bersamaan bisa sama-sama lolos. Perbaikannya
 * mengunci baris lalu memeriksa ulang status dari hasil kunci.
 *
 * PHPUnit berjalan satu proses, jadi balapan sungguhan tidak bisa diulang.
 * Yang diuji di sini adalah keadaan "request lain sudah menang": status baris
 * diubah tepat SETELAH pemeriksaan awal berjalan (lewat DB::listen), sehingga
 * pemeriksaan awal lolos dan hanya pemeriksaan di dalam kunci yang menahan.
 */
class TreasuryBatchLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function treasury(): User
    {
        return $this->userWithRole('treasury');
    }

    private function fund(): SourceFund
    {
        return SourceFund::create([
            'name'            => 'Rekening Operasional Uji',
            'status'          => 'ACTIVE',
            'is_main_account' => false,
        ]);
    }

    private function storeBatch(User $user, array $payload)
    {
        return $this->actingAs($user)->post('/daily-transaction', array_merge([
            'type'           => 'CASH_OUT',
            'description'    => 'Batch uji',
            'source_fund_id' => $this->fund()->id,
        ], $payload));
    }

    private function includedItemsFor(BudgetRequest $cr): int
    {
        return DailyTransactionItem::where('budget_request_id', $cr->id)
            ->whereNull('second_payment_id')
            ->where('status', 'INCLUDED')
            ->count();
    }

    /**
     * Jalankan $aksi sekali, tepat setelah query yang memuat semua $penanda
     * selesai dieksekusi - meniru request lain yang menang lebih dulu.
     */
    private function setelahQuery(array $penanda, \Closure $aksi): void
    {
        $sudah = false;

        DB::listen(function ($query) use ($penanda, $aksi, &$sudah) {
            if ($sudah) {
                return;
            }
            foreach ($penanda as $p) {
                if (! str_contains($query->sql, $p)) {
                    return;
                }
            }
            $sudah = true;
            $aksi();
        });
    }

    public function test_batch_normal_memindahkan_cr_ke_queued(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);

        $this->storeBatch($this->treasury(), ['cr_ids' => [$cr->id]])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame('QUEUED', $cr->refresh()->status);
        $this->assertSame(1, $this->includedItemsFor($cr));
        $this->assertSame(1, DailyTransaction::count());
        $this->assertSame(1, $cr->statusLogs()->where('status_to', 'QUEUED')->count());
    }

    public function test_batch_kedua_untuk_cr_yang_sama_ditolak(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $user = $this->treasury();

        $this->storeBatch($user, ['cr_ids' => [$cr->id]]);
        $this->storeBatch($user, ['cr_ids' => [$cr->id]])->assertSessionHas('error');

        $this->assertSame(1, $this->includedItemsFor($cr));
        $this->assertSame(1, DailyTransaction::count());
    }

    public function test_cr_yang_baru_saja_dibatch_pihak_lain_dibatalkan_di_dalam_kunci(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);

        // Pemeriksaan awal "status != APPROVED" lolos, lalu request lain
        // memindahkan CR ke QUEUED sebelum transaksi kita mengunci barisnya.
        $this->setelahQuery(['budget_request', '!= ?'], fn () =>
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'QUEUED'])
        );

        $this->storeBatch($this->treasury(), ['cr_ids' => [$cr->id]])
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'baru saja diproses'));

        $this->assertSame(0, DailyTransaction::count());
        $this->assertSame(0, $this->includedItemsFor($cr));
        $this->assertSame(0, $cr->statusLogs()->where('action_type', 'batched')->count());
    }

    public function test_cr_dari_batch_yang_ditolak_boleh_dibatch_ulang(): void
    {
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);
        $user = $this->treasury();

        $this->storeBatch($user, ['cr_ids' => [$cr->id]]);
        $batch = DailyTransaction::firstOrFail();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-batch', ['notes' => 'Ditahan dulu']);

        $this->assertSame('APPROVED', $cr->refresh()->status);

        $this->storeBatch($user, ['cr_ids' => [$cr->id]])->assertSessionMissing('error');

        $this->assertSame('QUEUED', $cr->refresh()->status);
        $this->assertSame(2, DailyTransaction::count());
        $this->assertSame(1, $this->includedItemsFor($cr));
    }

    public function test_add_item_melewati_cr_yang_sudah_ada_di_batch_aktif_lain(): void
    {
        $user = $this->treasury();
        $crA = $this->makeCostRequest(['status' => 'APPROVED']);
        $crB = $this->makeCostRequest(['status' => 'APPROVED']);

        $this->storeBatch($user, ['cr_ids' => [$crA->id]]);
        $this->storeBatch($user, ['cr_ids' => [$crB->id]]);
        $batchB = DailyTransaction::latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post('/daily-transaction/' . $batchB->id . '/add-item', ['cr_ids' => [$crA->id]])
            ->assertSessionHas('success', fn ($pesan) => str_starts_with($pesan, '0 baris'));

        $this->assertSame(1, $this->includedItemsFor($crA));
    }

    public function test_add_item_melewati_cr_yang_baru_saja_dibatch_pihak_lain(): void
    {
        $user = $this->treasury();
        $crLain = $this->makeCostRequest(['status' => 'APPROVED']);
        $this->storeBatch($user, ['cr_ids' => [$crLain->id]]);
        $batch = DailyTransaction::firstOrFail();

        $cr = $this->makeCostRequest(['status' => 'APPROVED']);

        // Request lain memindahkan CR ke QUEUED sebelum addItem mengunci.
        $this->setelahQuery(['budget_request_second_payments'], fn () =>
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'QUEUED'])
        );

        $this->actingAs($user)
            ->post('/daily-transaction/' . $batch->id . '/add-item', ['cr_ids' => [$cr->id]])
            ->assertSessionHas('success', fn ($pesan) => str_starts_with($pesan, '0 baris'));

        $this->assertSame(0, $this->includedItemsFor($cr));
    }

    public function test_add_item_ke_batch_yang_sudah_diproses_ditolak(): void
    {
        $user = $this->treasury();
        $crLain = $this->makeCostRequest(['status' => 'APPROVED']);
        $this->storeBatch($user, ['cr_ids' => [$crLain->id]]);
        $batch = DailyTransaction::firstOrFail();
        $batch->update(['status' => 'DONE']);

        $cr = $this->makeCostRequest(['status' => 'APPROVED']);

        $this->actingAs($user)
            ->post('/daily-transaction/' . $batch->id . '/add-item', ['cr_ids' => [$cr->id]])
            ->assertSessionHas('error', 'Hanya batch PENDING_APPROVER yang bisa ditambah item.');

        $this->assertSame('APPROVED', $cr->refresh()->status);
        $this->assertSame(0, $this->includedItemsFor($cr));
    }

    public function test_second_payment_yang_baru_saja_dibatch_pihak_lain_tidak_masuk_dua_kali(): void
    {
        $cr = $this->makeCostRequest(['status' => 'SECOND_APPROVED']);
        $sp = BudgetRequestSecondPayment::create([
            'budget_request_id' => $cr->id,
            'amount'            => 250_000,
            'status'            => 'PENDING',
        ]);

        // Pemeriksaan awal availableForBatch() lolos, lalu request lain
        // memasukkan SP ini ke batch (CR induk jadi QUEUED) lebih dulu.
        $this->setelahQuery(['budget_request_second_payments', 'exists'], fn () =>
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'QUEUED'])
        );

        $this->storeBatch($this->treasury(), ['second_payment_ids' => [$sp->id]])
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Second Payment #' . $sp->id));

        $this->assertSame(0, DailyTransaction::count());
        $this->assertSame(0, DailyTransactionItem::where('second_payment_id', $sp->id)->count());
    }
}
