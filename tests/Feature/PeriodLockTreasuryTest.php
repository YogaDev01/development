<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\Closing;
use App\Models\DailyTransaction;
use App\Models\SourceFund;
use App\Support\PeriodLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Kunci periode tahap 2: Treasury + CR tahap bayar (CRITICAL; rancangan Planner 2026-11-13).
 * Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.
 */
class PeriodLockTreasuryTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');
        PeriodLock::flush();
        config(['closing.enforce_from_period' => null]);
    }

    private function tutup(string $period): void
    {
        $s = \Carbon\Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        PeriodLock::flush();
        Closing::create([
            'code' => Closing::generateCode($period), 'period' => $period,
            'start_date' => $s, 'end_date' => $s->copy()->endOfMonth(), 'status' => 'CLOSED',
        ]);
    }

    private function batch(BudgetRequest $cr, string $date, string $status = 'DONE'): DailyTransaction
    {
        $batch = DailyTransaction::create([
            'code'           => 'DT-UJI-' . uniqid(),
            'date'           => $date,
            'type'           => 'CASH_OUT',
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE'])->id,
            'status'         => $status,
            'created_by'     => $cr->applicant_id,
        ]);
        $batch->items()->create(['budget_request_id' => $cr->id, 'status' => 'INCLUDED']);

        return $batch;
    }

    private function bayar($user, DailyTransaction $batch, array $extra = [])
    {
        return $this->actingAs($user)->post('/daily-transaction/' . $batch->id . '/mark-paid', $extra + [
            'charge'        => '0',
            'payment_notes' => 'Transfer uji',
            'attachments'   => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')],
        ]);
    }

    public function test_mark_paid_batch_bertanggal_periode_closed_ditolak_tanpa_menyimpan_apa_pun(): void
    {
        $this->tutup('2026-10');
        $cr    = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->batch($cr, '2026-10-30');

        $this->bayar($this->userWithRole('treasury'), $batch)->assertSessionHas('error');

        $this->assertSame('DONE', $batch->fresh()->status);
        $this->assertSame('QUEUED', $cr->fresh()->status);
        $this->assertNull($cr->fresh()->paid_at);
        $this->assertSame(0, $batch->attachments()->count());
    }

    public function test_mark_paid_ditolak_bila_periode_hari_ini_closed(): void
    {
        $this->tutup(now()->format('Y-m'));
        $cr    = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->batch($cr, now()->subMonths(3)->toDateString());

        $this->bayar($this->userWithRole('treasury'), $batch)->assertSessionHas('error');

        $this->assertSame('DONE', $batch->fresh()->status);
    }

    public function test_mark_paid_di_periode_terbuka_tetap_berjalan(): void
    {
        $this->tutup('2026-10');
        $cr    = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->batch($cr, now()->toDateString());

        $this->bayar($this->userWithRole('treasury'), $batch)->assertSessionMissing('error');

        $this->assertSame('PAID', $batch->fresh()->status);
    }

    public function test_superadmin_mark_paid_di_periode_closed_wajib_alasan_dan_tercatat(): void
    {
        $this->tutup('2026-10');
        $cr    = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->batch($cr, '2026-10-30');
        $admin = $this->userWithRole('superadmin');

        $this->bayar($admin, $batch)->assertSessionHas('error');
        $this->assertSame('DONE', $batch->fresh()->status);

        $this->bayar($admin, $batch, ['alasan_periode' => 'batch lintas bulan, disetujui pemilik'])
            ->assertSessionMissing('error');
        $this->assertSame('PAID', $batch->fresh()->status);
        $this->assertDatabaseHas('user_activity_logs', ['module' => 'closing', 'action' => 'period_override']);
    }

    public function test_hapus_draft_batch_di_periode_closed_ditolak(): void
    {
        $this->tutup('2026-10');
        $cr    = $this->makeCostRequest(['status' => 'APPROVED']);
        $batch = $this->batch($cr, '2026-10-30', 'DRAFT');

        $this->actingAs($this->userWithRole('treasury'))
            ->delete('/daily-transaction/' . $batch->id)
            ->assertSessionHas('error');

        $this->assertNotNull(DailyTransaction::find($batch->id));
    }

    public function test_super_update_memindah_tanggal_keluar_dari_periode_closed_butuh_alasan(): void
    {
        $this->tutup('2026-10');
        $cr    = $this->makeCostRequest(['status' => 'QUEUED']);
        $batch = $this->batch($cr, '2026-10-30');
        $admin = $this->userWithRole('superadmin');

        $this->actingAs($admin)->post('/daily-transaction/' . $batch->id . '/super-update', ['date' => '2026-11-02'])
            ->assertSessionHas('error');
        $this->assertSame('2026-10-30', $batch->fresh()->date->toDateString());

        $this->actingAs($admin)->post('/daily-transaction/' . $batch->id . '/super-update', [
            'date' => '2026-11-02', 'alasan_periode' => 'salah tanggal input',
        ])->assertSessionHas('success');
        $this->assertSame('2026-11-02', $batch->fresh()->date->toDateString());
    }

    public function test_takeout_batch_paid_mencabut_paid_at_di_periode_closed_ditolak(): void
    {
        $this->tutup('2026-10');
        $cr = $this->makeCostRequest(['status' => 'PAID']);
        $cr->forceFill(['paid_at' => '2026-10-20 10:00:00'])->save();
        $batch = $this->batch($cr, now()->toDateString(), 'PAID');
        $item  = $batch->items()->first();

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/daily-transaction/' . $batch->id . '/reject-item/' . $item->id, ['notes' => 'koreksi'])
            ->assertSessionHas('error');

        $this->assertSame('PAID', $cr->fresh()->status);
        $this->assertNotNull($cr->fresh()->paid_at);
    }

    public function test_edit_superadmin_cr_yang_sudah_dibayar_di_periode_closed_ditolak_tanpa_alasan(): void
    {
        $this->tutup('2026-10');
        $cr = $this->makeCostRequest(['status' => 'PAID']);
        $cr->forceFill(['paid_at' => '2026-10-20 10:00:00'])->save();

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/budget-request/' . $cr->id, [])
            ->assertSessionHas('error');

        $this->assertStringContainsString('ditutup', session('error'));
    }
}
