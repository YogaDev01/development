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

    /*
    |--------------------------------------------------------------------------
    | Kelebihan Settlement (Second Payment) ikut memakan anggaran
    |--------------------------------------------------------------------------
    | Selisih Settlement di atas nilai pengajuan dibayar lewat Second Payment,
    | jadi uangnya benar-benar keluar dari budget ini. Dulu "Used" menjumlahkan
    | kolom `amount` (nilai pengajuan) saja, sehingga kelebihan itu tidak pernah
    | terlihat: uang keluar, sisa budget tidak berkurang.
    */

    public function test_kelebihan_settlement_ikut_mengurangi_budget(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        $cr = $this->makeCostRequest([
            'budget_plan' => $plan,
            'amount'      => 2_000_000,
            'status'      => 'SETTLE_VERIFIED',
        ]);

        // Settlement 3 juta atas pengajuan 2 juta -> Second Payment 1 juta.
        $cr->realizations()->create([
            'description' => 'Realisasi uji',
            'amount'      => 3_000_000,
            'qty'         => 1,
            'total'       => 3_000_000,
        ]);

        $this->assertSame(
            3_000_000,
            $plan->fresh()->usedAmount(),
            'Used harus mengikuti nilai Settlement, bukan nilai pengajuan.'
        );

        $this->assertSame(7_000_000, $plan->fresh()->remainingAmount());
    }

    public function test_pengecekan_nob_memperhitungkan_kelebihan_settlement(): void
    {
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        $cr = $this->makeCostRequest([
            'budget_plan' => $plan,
            'amount'      => 8_000_000,
            'status'      => 'SETTLE_VERIFIED',
        ]);

        // Settle di 9,5 juta -> selisih 1,5 juta dibayar lewat Second Payment.
        $cr->realizations()->create([
            'description' => 'Realisasi uji',
            'amount'      => 9_500_000,
            'qty'         => 1,
            'total'       => 9_500_000,
        ]);

        // Sisa anggaran sesungguhnya tinggal 500 ribu, jadi pengajuan 1 juta
        // berikutnya harus tertahan sebagai NOB. Dengan perhitungan lama
        // (pakai nilai pengajuan 8 juta) sisanya terbaca 2 juta dan pengajuan
        // ini lolos tanpa peringatan.
        $response = $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/budget-request/check-nob', [
                'budget_plan_id' => $plan->id,
                'amount'         => 1_000_000,
            ]);

        $response->assertOk();
        $this->assertTrue(
            (bool) $response->json('is_nob'),
            'Kelebihan Settlement harus ikut dihitung saat pengecekan NOB.'
        );
    }

    public function test_refund_settlement_tidak_dihitung_terpakai(): void
    {
        // Sisi sebaliknya dari kasus di atas: CR yang settle DI BAWAH pengajuan
        // hanya memakai sebesar realisasinya, sisanya kembali jadi anggaran.
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        $cr = $this->makeCostRequest([
            'budget_plan' => $plan,
            'amount'      => 5_000_000,
            'status'      => 'CLOSED',
        ]);

        $cr->realizations()->create([
            'description' => 'Realisasi uji',
            'amount'      => 3_000_000,
            'qty'         => 1,
            'total'       => 3_000_000,
        ]);

        $this->assertSame(3_000_000, $plan->fresh()->usedAmount());
    }

    public function test_cr_belum_settle_tetap_dihitung_penuh(): void
    {
        // Selama belum settle, nilai pengajuan penuh yang mengikat anggaran —
        // realisasi yang masih diajukan belum boleh mengurangi apa pun.
        $plan = $this->makeBudgetPlan(null, ['amount' => 10_000_000]);

        $cr = $this->makeCostRequest([
            'budget_plan' => $plan,
            'amount'      => 5_000_000,
            'status'      => 'REALIZED',
        ]);

        $cr->realizations()->create([
            'description' => 'Realisasi uji',
            'amount'      => 1_000_000,
            'qty'         => 1,
            'total'       => 1_000_000,
        ]);

        $this->assertSame(5_000_000, $plan->fresh()->usedAmount());
    }
}
