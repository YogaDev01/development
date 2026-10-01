<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\Project;
use App\Models\RequestDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * M3 — Revise, bulk review, dan takeout/restore memakai transisi terkunci;
 * bulk review juga dibatasi Access Group seperti aksi satuan.
 *
 * Balapan disimulasikan seperti TreasuryBatchLockTest: keadaan "orang lain
 * sudah menang" ditulis tepat setelah CR dibaca tanpa kunci.
 *
 * Batasan: "dua takeout bersamaan atas dua item terakhir" bergantung pada dua
 * koneksi yang belum saling melihat commit - tidak bisa diulang di satu
 * koneksi RefreshDatabase. Yang diuji untuk takeout adalah tahap CR yang
 * berubah setelah dibaca.
 */
class CostRequestReviewLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private Project $project;

    private function cr(string $status, array $itemTotals = [400_000, 600_000]): BudgetRequest
    {
        $this->project ??= $this->makeProject();

        $cr = $this->makeCostRequest([
            'budget_plan' => $this->makeBudgetPlan($this->project),
            'status'      => $status,
            'amount'      => array_sum($itemTotals),
        ]);

        foreach ($itemTotals as $i => $total) {
            RequestDetail::create([
                'budget_request_id' => $cr->id,
                'description'       => 'Item ' . ($i + 1),
                'amount'            => $total,
                'qty'               => 1,
                'total'             => $total,
                'status'            => 'INCLUDED',
            ]);
        }

        return $cr;
    }

    private function headUser(): User
    {
        return $this->giveProjectAccess($this->userWithRole('head'), $this->project);
    }

    /** Jalankan $aksi sekali, tepat setelah query pertama yang memuat semua $penanda. */
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

    /* ------------------------------------------------------------- REVISE */

    public function test_revise_normal_mengembalikan_cr_ke_draft(): void
    {
        $cr = $this->cr('REJECTED');
        $cr->details()->first()->update(['status' => 'TAKEOUT']);

        $this->actingAs($this->giveProjectAccess($this->userWithRole('manager'), $this->project))
            ->post('/budget-request/' . $cr->id . '/manager-revise', ['notes' => 'Perbaiki'])
            ->assertSessionMissing('error');

        $this->assertSame('DRAFT', $cr->refresh()->status);
        $this->assertSame(0, $cr->details()->where('status', 'TAKEOUT')->count());
        $this->assertSame(1_000_000, (int) $cr->amount);
    }

    public function test_revise_tidak_menghidupkan_cr_yang_baru_saja_dibatalkan(): void
    {
        $cr = $this->cr('REJECTED');

        $this->setelahQuery(['from `budget_request`'], fn () =>
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'CANCELLED'])
        );

        $this->actingAs($this->giveProjectAccess($this->userWithRole('manager'), $this->project))
            ->post('/budget-request/' . $cr->id . '/manager-revise', ['notes' => 'Perbaiki'])
            ->assertSessionHas('error', 'Status sudah berubah — muat ulang halaman.');

        $this->assertSame('CANCELLED', $cr->refresh()->status);
        $this->assertSame(0, $cr->statusLogs()->where('action_type', 'revised')->count());
    }

    /* -------------------------------------------------------- BULK REVIEW */

    public function test_bulk_approve_normal(): void
    {
        $a = $this->cr('SUBMITTED');
        $b = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', ['ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '2 CR berhasil di-approve.');

        $this->assertSame('CHECKED', $a->refresh()->status);
        $this->assertSame('CHECKED', $b->refresh()->status);
    }

    public function test_bulk_approve_melewati_cr_yang_baru_saja_ditolak_orang_lain(): void
    {
        $a = $this->cr('SUBMITTED');
        $b = $this->cr('SUBMITTED');
        $head = $this->headUser();

        // Tepat setelah daftar CR dimuat, Head lain menolak CR B.
        $this->setelahQuery(['from `budget_request`', ' in ('], fn () =>
            DB::table('budget_request')->where('id', $b->id)->update(['status' => 'REJECTED'])
        );

        $this->actingAs($head)
            ->post('/budget-request/review-bulk-approve', ['ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '1 CR berhasil di-approve.');

        $this->assertSame('CHECKED', $a->refresh()->status);
        $this->assertSame('REJECTED', $b->refresh()->status);
        $this->assertSame(0, $b->statusLogs()->where('action_type', 'head_approved')->count());
    }

    public function test_bulk_reject_melewati_cr_yang_baru_saja_disetujui_orang_lain(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->setelahQuery(['from `budget_request`', ' in ('], fn () =>
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'CHECKED'])
        );

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-reject', ['ids' => [$cr->id], 'notes' => 'Tolak'])
            ->assertSessionHas('success', fn ($pesan) => str_starts_with($pesan, '0 CR'));

        $this->assertSame('CHECKED', $cr->refresh()->status);
    }

    public function test_bulk_approve_tidak_memproses_cr_di_luar_access_group(): void
    {
        $cr = $this->cr('SUBMITTED');

        // Head tanpa akses ke proyek CR ini.
        $headLain = $this->userWithRole('head');

        $this->actingAs($headLain)
            ->post('/budget-request/review-bulk-approve', ['ids' => [$cr->id]])
            ->assertSessionHas('success', '0 CR berhasil di-approve.');

        $this->assertSame('SUBMITTED', $cr->refresh()->status);
    }

    /* ---------------------------------------------------- TAKEOUT/RESTORE */

    public function test_takeout_normal_mengurangi_nilai_cr(): void
    {
        $cr = $this->cr('SUBMITTED');
        $item = $cr->details()->first();

        $this->actingAs($this->headUser())
            ->post('/budget-request/' . $cr->id . '/detail/' . $item->id . '/takeout', ['notes' => 'Tidak perlu'])
            ->assertSessionMissing('error');

        $this->assertSame('TAKEOUT', $item->refresh()->status);
        $this->assertSame(600_000, (int) $cr->refresh()->amount);
        $this->assertSame('SUBMITTED', $cr->status);
    }

    public function test_takeout_ditolak_bila_tahap_cr_baru_saja_berubah(): void
    {
        $cr = $this->cr('SUBMITTED');
        $item = $cr->details()->first();

        // Tepat setelah CR dibaca, Head lain sudah menyetujuinya (CHECKED):
        // Head tidak lagi berhak men-takeout di tahap Finance.
        $this->setelahQuery(['from `budget_request`'], fn () =>
            DB::table('budget_request')->where('id', $cr->id)->update(['status' => 'CHECKED'])
        );

        $this->actingAs($this->headUser())
            ->post('/budget-request/' . $cr->id . '/detail/' . $item->id . '/takeout', ['notes' => 'Tidak perlu'])
            ->assertSessionHas('error', 'Status sudah berubah — muat ulang halaman.');

        $this->assertSame('INCLUDED', $item->refresh()->status);
        $this->assertSame(1_000_000, (int) $cr->refresh()->amount);
    }

    public function test_restore_normal_mengembalikan_nilai_cr(): void
    {
        $cr = $this->cr('SUBMITTED');
        $item = $cr->details()->first();
        $head = $this->headUser();

        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/detail/' . $item->id . '/takeout', ['notes' => 'Tidak perlu']);
        $this->actingAs($head)
            ->post('/budget-request/' . $cr->id . '/detail/' . $item->id . '/restore')
            ->assertSessionMissing('error');

        $this->assertSame('INCLUDED', $item->refresh()->status);
        $this->assertSame(1_000_000, (int) $cr->refresh()->amount);
    }
}
