<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Support\BudgetConsumption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan BIZ-01: 'CHECKED' hilang dari daftar status yang dianggap
 * memakai anggaran, sehingga CR yang sudah lolos Head tapi belum diverifikasi
 * Finance tidak terhitung sama sekali.
 *
 * Pengecekan NOB dilakukan lewat endpoint check-nob supaya yang diuji adalah
 * jalur yang benar-benar dipakai form, bukan metode privat yang bisa saja
 * tidak pernah dipanggil.
 */
class BudgetConsumptionTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_cr_berstatus_checked_ikut_memakai_anggaran(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        // Sudah ada CR 8 juta yang menunggu verifikasi Finance.
        $this->makeCostRequest([
            'budget_plan' => $plan,
            'amount'      => 8_000_000,
            'status'      => 'CHECKED',
        ]);

        // Pengajuan 5 juta berikutnya jelas melewati sisa anggaran (2 juta).
        $response = $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-request/check-nob', [
                'budget_plan_id' => $plan->id,
                'amount'         => 5_000_000,
            ]);

        $response->assertOk();
        $this->assertTrue(
            (bool) $response->json('is_nob'),
            'CR berstatus CHECKED harus ikut dihitung memakai anggaran.'
        );
    }

    public function test_cr_draft_dan_ditolak_tidak_memakai_anggaran(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        $this->makeCostRequest(['budget_plan' => $plan, 'amount' => 9_000_000, 'status' => 'DRAFT']);
        $this->makeCostRequest(['budget_plan' => $plan, 'amount' => 9_000_000, 'status' => 'REJECTED']);
        $this->makeCostRequest(['budget_plan' => $plan, 'amount' => 9_000_000, 'status' => 'CANCELLED']);

        $response = $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-request/check-nob', [
                'budget_plan_id' => $plan->id,
                'amount'         => 5_000_000,
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('is_nob'));
    }

    public function test_setiap_status_berjalan_masuk_salah_satu_kelompok(): void
    {
        // Pengaman regresi: kalau status baru ditambahkan ke enum tapi lupa
        // dimasukkan ke salah satu kelompok, uji ini yang memberi tahu.
        $semua = array_merge(
            BudgetConsumption::COMMITTED,
            BudgetConsumption::NONE,
        );

        $enumStatus = [
            'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED', 'QUEUED',
            'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED',
            'REJECTED', 'CANCELLED',
        ];

        $this->assertSame(
            [],
            array_values(array_diff($enumStatus, $semua)),
            'Ada status Cost Request yang belum diputuskan memakai anggaran atau tidak.'
        );
    }

    public function test_disbursed_adalah_bagian_dari_committed(): void
    {
        // Uang yang sudah keluar pasti juga mengikat anggaran. Kalau suatu saat
        // tidak lagi demikian, ada yang salah dengan definisinya.
        $this->assertSame(
            [],
            array_values(array_diff(BudgetConsumption::DISBURSED, BudgetConsumption::COMMITTED)),
        );
    }

    public function test_used_amount_budget_plan_hanya_menghitung_yang_sudah_cair(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        $this->makeCostRequest(['budget_plan' => $plan, 'amount' => 3_000_000, 'status' => 'CHECKED']);
        $this->makeCostRequest(['budget_plan' => $plan, 'amount' => 2_000_000, 'status' => 'PAID']);

        // "Used" pada Budget Plan mengukur realisasi, bukan komitmen — jadi
        // hanya yang PAID yang dihitung. Dua pengertian ini memang berbeda dan
        // uji ini yang menjaga perbedaannya tetap disengaja.
        $this->assertSame(2_000_000, $plan->fresh()->usedAmount());
        $this->assertSame(
            5_000_000,
            (int) BudgetRequest::where('budget_plan_id', $plan->id)
                ->whereIn('status', BudgetConsumption::COMMITTED)
                ->sum('amount'),
        );
    }
}
