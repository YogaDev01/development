<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetRequestRealization;
use App\Models\MasterPic;
use App\Models\RequestDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Riwayat pengajuan PIC pada drawer Todo List (payload detail-json 'pic_history').
 *
 * Aturan yang dijaga:
 *  - cakupan = PIC yang sama (satu PIC bisa banyak beneficiary) DI PO yang sama;
 *  - hanya CR yang sudah dibayar atau setelahnya (paid_at terisi);
 *  - sumber baris: Cash Advance dari settlement, non Cash Advance dari pengajuan.
 */
class PicHistoryTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function makePic(): MasterPic
    {
        return MasterPic::create(['type' => 'INTERNAL', 'pic' => 'PIC Uji', 'status' => 'ACTIVE']);
    }

    private function benForPic(MasterPic $pic): Beneficiary
    {
        $b = $this->makeBeneficiary();
        $b->update(['pic_id' => $pic->id]);
        return $b;
    }

    public function test_riwayat_mencakup_pic_sama_di_po_sama_dan_mengecualikan_po_lain(): void
    {
        $admin   = $this->userWithRole('superadmin');
        $pic     = $this->makePic();
        $benA    = $this->benForPic($pic);
        $benB    = $this->benForPic($pic);

        $project = $this->makeProject();
        $plan    = $this->makeBudgetPlan($project);
        $bpd     = BudgetPlanDetail::create([
            'budget_plan_id' => $plan->id, 'deskripsi' => 'Sub A',
            'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
        ]);

        // CR yang sedang dibuka (beneficiary A).
        $current = $this->makeCostRequest(['budget_plan' => $plan, 'beneficiary_id' => $benA->id]);

        // Riwayat: beneficiary B (PIC sama), PO sama, non-CA, sudah dibayar.
        $hist = $this->makeCostRequest([
            'budget_plan' => $plan, 'beneficiary_id' => $benB->id,
            'status' => 'PAID', 'paid_at' => now(), 'amount' => 750_000,
        ]);
        RequestDetail::create([
            'budget_request_id' => $hist->id, 'budget_detail_id' => $bpd->id,
            'description' => 'Jasa 7 hari', 'amount' => 750_000, 'qty' => 1, 'total' => 750_000,
        ]);

        // CR di PO LAIN dengan PIC sama — harus dikecualikan.
        $planLain = $this->makeBudgetPlan($this->makeProject());
        $this->makeCostRequest([
            'budget_plan' => $planLain, 'beneficiary_id' => $benB->id,
            'status' => 'PAID', 'paid_at' => now(), 'amount' => 999_000,
        ]);

        $res = $this->actingAs($admin)
            ->getJson('/budget-request/' . $current->id . '/detail-json')
            ->assertOk()
            ->json('pic_history');

        $this->assertSame('PIC Uji', $res['pic_name']);
        $this->assertCount(1, $res['items']);
        $this->assertSame('Sub A', $res['items'][0]['sub_coa']);
        $this->assertSame(750_000, $res['items'][0]['value']);
        $this->assertSame($hist->id, $res['items'][0]['cr_id']);
    }

    public function test_cash_advance_mengambil_baris_dari_settlement(): void
    {
        $admin   = $this->userWithRole('superadmin');
        $pic     = $this->makePic();
        $benA    = $this->benForPic($pic);
        $benB    = $this->benForPic($pic);

        $plan = $this->makeBudgetPlan();
        $bpd  = BudgetPlanDetail::create([
            'budget_plan_id' => $plan->id, 'deskripsi' => 'Sub CA',
            'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
        ]);

        $current = $this->makeCostRequest(['budget_plan' => $plan, 'beneficiary_id' => $benA->id]);

        $caType = $this->makeRequestType('Cash Advance');
        $hist = $this->makeCostRequest([
            'budget_plan' => $plan, 'beneficiary_id' => $benB->id, 'type_id' => $caType->id,
            'status' => 'CLOSED', 'paid_at' => now(), 'amount' => 600_000,
        ]);
        $rd = RequestDetail::create([
            'budget_request_id' => $hist->id, 'budget_detail_id' => $bpd->id,
            'description' => 'Alamsyah 7 hari', 'amount' => 600_000, 'qty' => 1, 'total' => 600_000,
        ]);
        // Settlement MEMBEDAKAN nilai: 850rb, bukan 600rb pengajuan.
        BudgetRequestRealization::create([
            'budget_request_id' => $hist->id, 'request_detail_id' => $rd->id,
            'description' => 'Alamsyah 8 hari', 'amount' => 850_000, 'qty' => 1, 'total' => 850_000,
        ]);

        $items = $this->actingAs($admin)
            ->getJson('/budget-request/' . $current->id . '/detail-json')
            ->assertOk()
            ->json('pic_history.items');

        $this->assertCount(1, $items);
        $this->assertSame(850_000, $items[0]['value']);          // nilai settlement
        $this->assertSame('Alamsyah 8 hari', $items[0]['description']);
        $this->assertSame('Sub CA', $items[0]['sub_coa']);       // dari budget detail pengajuan
    }

    public function test_payload_detail_dan_settlement_memuat_sub_coa(): void
    {
        $admin = $this->userWithRole('superadmin');
        $plan  = $this->makeBudgetPlan();
        $bpd   = BudgetPlanDetail::create([
            'budget_plan_id' => $plan->id, 'deskripsi' => 'Pengiriman material ke site',
            'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
        ]);

        $cr = $this->makeCostRequest(['budget_plan' => $plan, 'type_id' => $this->makeRequestType('Cash Advance')->id]);
        $rd = RequestDetail::create([
            'budget_request_id' => $cr->id, 'budget_detail_id' => $bpd->id,
            'description' => 'Allowance — Pengiriman material ke site',
            'notes' => 'Untuk kebutuhan 2 site sekaligus.',
            'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
        ]);
        BudgetRequestRealization::create([
            'budget_request_id' => $cr->id, 'request_detail_id' => $rd->id,
            'description' => 'Pengiriman material ke site', 'amount' => 900_000, 'qty' => 1, 'total' => 900_000,
        ]);

        $json = $this->actingAs($admin)
            ->getJson('/budget-request/' . $cr->id . '/detail-json')
            ->assertOk()->json();

        $this->assertSame('Pengiriman material ke site', $json['details'][0]['sub_coa']);
        $this->assertSame('Untuk kebutuhan 2 site sekaligus.', $json['details'][0]['notes']);
        $this->assertSame('Pengiriman material ke site', $json['realizations'][0]['sub_coa']);
    }
}
