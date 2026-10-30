<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\RequestDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Settlement Custom COA (25 Okt 2026): baris settlement Cash Advance boleh
 * memilih Sub COA (sub-item Budget Plan CR yang sama) — dipecah dari item
 * pengajuan, pindah Sub COA, atau baris tambahan tanpa item asal.
 *
 * Lihat BudgetRequestController::validateRealizationInput()/syncRealizations(),
 * BudgetRequest::costUnits(), BudgetPlanDetail::usedAmount()/costLines(),
 * migration 2026_10_25_000200.
 */
class SettlementCustomCoaTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private \App\Models\Project $project;
    private \App\Models\BudgetPlan $plan;
    private Account $akunA;
    private Account $akunB;
    /** @var array<string, BudgetPlanDetail> */
    private array $sub = [];

    /**
     * CR Cash Advance PAID, 3 item @100.000 di Sub COA s1/s2/s3 (akun A).
     * Budget Plan yang sama juga punya s4 (akun B) dan s5 (tipe additional).
     */
    private function crPaid(string $status = 'PAID'): BudgetRequest
    {
        $this->project = $this->makeProject();
        $this->plan    = $this->makeBudgetPlan($this->project);
        $this->akunA   = Account::create(['name' => 'Transport Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
        $this->akunB   = Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);

        foreach (['s1' => [$this->akunA, 'main'], 's2' => [$this->akunA, 'main'], 's3' => [$this->akunA, 'main'],
                  's4' => [$this->akunB, 'main'], 's5' => [$this->akunB, 'additional']] as $key => [$akun, $tipe]) {
            $this->sub[$key] = BudgetPlanDetail::create([
                'budget_plan_id' => $this->plan->id, 'account_id' => $akun->id, 'type' => $tipe,
                'deskripsi' => 'Sub ' . strtoupper($key), 'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
            ]);
        }

        $cr = $this->makeCostRequest([
            'budget_plan' => $this->plan,
            'status'      => $status,
            'amount'      => 300_000,
            'type_id'     => $this->makeRequestType('Cash Advance')->id,
        ]);

        foreach (['s1', 's2', 's3'] as $key) {
            RequestDetail::create([
                'budget_request_id' => $cr->id,
                'budget_detail_id'  => $this->sub[$key]->id,
                'account_id'        => $this->akunA->id,
                'description'       => $this->akunA->name . ' — Sub ' . strtoupper($key),
                'notes'             => 'Item ' . $key,
                'amount'            => 100_000, 'qty' => 1, 'total' => 100_000,
                'status'            => 'INCLUDED',
            ]);
        }

        return $cr->fresh();
    }

    private function manager(): \App\Models\User
    {
        return $this->giveProjectAccess($this->userWithRole('manager'), $this->project);
    }

    private function item(BudgetRequest $cr, string $key): RequestDetail
    {
        return $cr->details()->where('budget_detail_id', $this->sub[$key]->id)->firstOrFail();
    }

    /** Satu baris form settlement. */
    private function baris(?RequestDetail $asal, string $sub, int $amount, ?string $note = null, bool $lampiran = true): array
    {
        return array_filter([
            'request_detail_id' => $asal?->id,
            'budget_detail_id'  => $this->sub[$sub]->id,
            'amount'            => $amount,
            'notes'             => 'Settle ' . $sub,
            'change_note'       => $note,
            'attachments'       => $lampiran && $amount > 0 ? [UploadedFile::fake()->image('bukti-' . $sub . '.jpg')] : null,
        ], fn ($v) => $v !== null);
    }

    private function submit(BudgetRequest $cr, array $rows)
    {
        return $this->actingAs($this->manager())
            ->post('/budget-request/' . $cr->id . '/submit-realization', ['realizations' => $rows]);
    }

    /* AC1 — prefill 1:1 dan submit tanpa perubahan = perilaku lama. */
    public function test_prefill_satu_baris_per_item_dan_submit_tanpa_perubahan(): void
    {
        $cr = $this->crPaid();

        $this->actingAs($this->manager())
            ->get('/budget-request/' . $cr->id . '/realize-form')
            ->assertOk()
            ->assertSee('Sub S1')
            ->assertSee('Sub S2')
            ->assertSee('Sub S3');

        $rows = $cr->settlementFormRows();
        $this->assertCount(3, $rows);
        $this->assertSame(
            [$this->sub['s1']->id, $this->sub['s2']->id, $this->sub['s3']->id],
            $rows->pluck('budget_detail_id')->all()
        );
        $this->assertSame([100_000, 100_000, 100_000], $rows->pluck('amount')->all());

        $this->submit($cr, [
            $this->baris($this->item($cr, 's1'), 's1', 100_000),
            $this->baris($this->item($cr, 's2'), 's2', 100_000),
            $this->baris($this->item($cr, 's3'), 's3', 100_000),
        ])->assertRedirect(route('budget-request.index'));

        $cr->refresh();
        $this->assertSame('REALIZED', $cr->status);
        $this->assertSame(300_000, $cr->total_realization);
        $this->assertSame(0, $cr->refundAmount());
        $this->assertSame(3, $cr->realizations()->count());
        foreach (['s1', 's2', 's3'] as $key) {
            $this->assertDatabaseHas('budget_request_realizations', [
                'budget_request_id' => $cr->id,
                'request_detail_id' => $this->item($cr, $key)->id,
                'budget_detail_id'  => $this->sub[$key]->id,
                'account_id'        => $this->akunA->id,
                'total'             => 100_000,
            ]);
        }
    }

    /* AC2 — dipecah / ditambah jadi 8 baris Sub COA berbeda. */
    public function test_pecah_dan_tambah_jadi_delapan_baris(): void
    {
        $cr = $this->crPaid();
        [$i1, $i2, $i3] = [$this->item($cr, 's1'), $this->item($cr, 's2'), $this->item($cr, 's3')];

        $this->submit($cr, [
            $this->baris($i1, 's1', 40_000),
            $this->baris($i1, 's4', 30_000, 'Sebagian untuk material'),   // pindah
            $this->baris($i1, 's5', 30_000, 'Pakai pos additional'),      // pindah
            $this->baris($i2, 's2', 90_000),
            $this->baris($i3, 's3', 50_000),
            $this->baris($i3, 's2', 20_000, 'Geser ke Sub S2'),           // pindah
            $this->baris(null, 's4', 25_000, 'Biaya tak terduga'),       // tambahan
            $this->baris(null, 's1', 15_000, 'Parkir'),                   // tambahan
        ])->assertRedirect(route('budget-request.index'));

        $cr->refresh();
        $this->assertSame('REALIZED', $cr->status);
        $this->assertSame(8, $cr->realizations()->count());
        $this->assertSame(300_000, $cr->total_realization);

        // account_id diturunkan dari sub-item terpilih.
        $this->assertSame(3, $cr->realizations()->where('account_id', $this->akunB->id)->count());
        $this->assertDatabaseHas('budget_request_realizations', [
            'budget_request_id' => $cr->id, 'request_detail_id' => null,
            'budget_detail_id'  => $this->sub['s4']->id, 'account_id' => $this->akunB->id, 'total' => 25_000,
        ]);
    }

    /* AC3 — sub-item Budget Plan lain / item TAKEOUT / item CR lain ditolak. */
    public function test_sub_coa_budget_plan_lain_ditolak_tanpa_menyimpan_apapun(): void
    {
        $cr    = $this->crPaid();
        $asing = BudgetPlanDetail::create([
            'budget_plan_id' => $this->makeBudgetPlan($this->project)->id, 'account_id' => $this->akunA->id,
            'deskripsi' => 'Sub Asing', 'amount' => 1, 'qty' => 1, 'total' => 1,
        ]);

        $this->submit($cr, [
            $this->baris($this->item($cr, 's1'), 's1', 100_000),
            ['request_detail_id' => $this->item($cr, 's2')->id, 'budget_detail_id' => $asing->id, 'amount' => 100_000,
             'change_note' => 'x', 'attachments' => [UploadedFile::fake()->image('a.jpg')]],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('PAID', $cr->fresh()->status);
        $this->assertSame(0, $cr->realizations()->count());
    }

    public function test_item_takeout_dan_item_cr_lain_ditolak(): void
    {
        $cr = $this->crPaid();
        $i3 = $this->item($cr, 's3');
        $i3->update(['status' => 'TAKEOUT']);

        $this->submit($cr, [$this->baris($i3, 's3', 100_000)])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, $cr->realizations()->count());

        $lain = $this->makeCostRequest(['budget_plan' => $this->plan, 'status' => 'PAID']);
        $itemLain = RequestDetail::create([
            'budget_request_id' => $lain->id, 'budget_detail_id' => $this->sub['s1']->id,
            'amount' => 10, 'qty' => 1, 'total' => 10, 'status' => 'INCLUDED',
        ]);

        $this->submit($cr, [$this->baris($itemLain, 's1', 10)])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, $cr->realizations()->count());
        $this->assertSame('PAID', $cr->fresh()->status);
    }

    public function test_baris_tambahan_wajib_sub_coa_dan_note(): void
    {
        $cr = $this->crPaid();

        // Tanpa Sub COA — ditolak juga saat simpan draft.
        $this->actingAs($this->manager())
            ->post('/budget-request/' . $cr->id . '/save-realization-draft', [
                'realizations' => [['amount' => 5_000]],
            ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, $cr->realizations()->count());

        // Sub COA ada, Note kosong — ditolak saat submit.
        $this->submit($cr, [
            $this->baris($this->item($cr, 's1'), 's1', 100_000),
            $this->baris($this->item($cr, 's2'), 's2', 100_000),
            $this->baris($this->item($cr, 's3'), 's3', 100_000),
            $this->baris(null, 's4', 5_000),
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('PAID', $cr->fresh()->status);
    }

    /* AC4 — Refund / Expense Claim / Second Payment memakai rumus lama atas total baru. */
    public function test_refund_klaim_dan_second_payment_mengikuti_total_baru(): void
    {
        $cr = $this->crPaid();
        [$i1, $i2, $i3] = [$this->item($cr, 's1'), $this->item($cr, 's2'), $this->item($cr, 's3')];

        $this->submit($cr, [
            $this->baris($i1, 's1', 100_000),
            $this->baris($i2, 's2', 100_000),
            $this->baris($i3, 's3', 100_000),
            $this->baris(null, 's4', 70_000, 'Tambahan material'),
        ])->assertRedirect(route('budget-request.index'));

        $cr->refresh();
        $this->assertSame(370_000, $cr->total_realization);
        $this->assertSame(70_000, $cr->expenseClaimAmount());
        $this->assertSame(0, $cr->refundAmount());

        $cr->update(['status' => 'SETTLE_CHECKED']);
        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/budget-request/' . $cr->id . '/finance-verify-settlement', ['notes' => 'ok'])
            ->assertRedirect();

        $this->assertSame('SETTLE_VERIFIED', $cr->fresh()->status);
        $this->assertDatabaseHas('budget_request_second_payments', ['budget_request_id' => $cr->id, 'amount' => 70_000]);
    }

    /* AC5 — baris lama (budget_detail_id NULL) tampil dengan Sub COA asal. */
    public function test_baris_lama_tanpa_budget_detail_id_memakai_sub_coa_asal(): void
    {
        $cr = $this->crPaid('REALIZED');
        $i1 = $this->item($cr, 's1');

        BudgetRequestRealization::create([
            'budget_request_id' => $cr->id, 'request_detail_id' => $i1->id, 'account_id' => $this->akunA->id,
            'description' => $i1->description, 'amount' => 80_000, 'qty' => 1, 'total' => 80_000,
        ]);

        $json = $this->actingAs($this->userWithRole('superadmin'))
            ->getJson('/budget-request/' . $cr->id . '/detail-json')
            ->assertOk()->json();

        $this->assertSame('Sub S1', $json['realizations'][0]['sub_coa']);
        $this->assertSame($this->sub['s1']->id, $json['realizations'][0]['budget_detail_id']);
        $this->assertFalse($json['realizations'][0]['is_moved']);
        $this->assertFalse($json['realizations'][0]['is_added']);

        // Used Sub S1 = nilai settle baris lama (fallback ke sub-item item asal).
        $this->assertSame(80_000, BudgetPlanDetail::find($this->sub['s1']->id)->usedAmount());
    }

    /* AC6 — settlement ditolak lalu submit ulang: lampiran tetap di baris yang sama. */
    public function test_lampiran_tetap_di_baris_yang_sama_setelah_submit_ulang(): void
    {
        $cr = $this->crPaid();
        [$i1, $i2, $i3] = [$this->item($cr, 's1'), $this->item($cr, 's2'), $this->item($cr, 's3')];

        $this->submit($cr, [
            $this->baris($i1, 's1', 60_000),
            $this->baris($i1, 's4', 40_000, 'Material'),
            $this->baris($i2, 's2', 100_000),
            $this->baris($i3, 's3', 100_000),
        ])->assertRedirect(route('budget-request.index'));

        $pecahan = $cr->realizations()->where('budget_detail_id', $this->sub['s4']->id)->firstOrFail();
        $lampiranId = $pecahan->attachments()->value('id');
        $this->assertNotNull($lampiranId);

        $cr->update(['status' => 'SETTLE_REJECTED']);

        // Submit ulang memakai id baris; lampiran lama dipakai (tidak upload baru).
        $semua = $cr->realizations()->get();
        $rows = $semua->map(fn ($r) => [
            'id'                => $r->id,
            'request_detail_id' => $r->request_detail_id,
            'budget_detail_id'  => $r->budget_detail_id,
            'amount'            => $r->total,
            'change_note'       => $r->change_note,
        ])->all();

        $this->submit($cr, $rows)->assertRedirect(route('budget-request.index'));

        $this->assertSame('REALIZED', $cr->fresh()->status);
        $this->assertSame($pecahan->id, \App\Models\RealizationDetailAttachment::find($lampiranId)?->realization_id);
        $this->assertSame(4, $cr->realizations()->count());
    }

    /* AC7 — perbandingan per COA/Sub COA: Request = amount CR (tidak ganda). */
    public function test_perbandingan_tidak_menghitung_request_ganda(): void
    {
        $cr = $this->crPaid();
        $i1 = $this->item($cr, 's1');

        $this->submit($cr, [
            $this->baris($i1, 's1', 50_000),
            $this->baris($i1, 's1', 30_000),
            $this->baris($i1, 's4', 20_000, 'Material'),
            $this->baris($this->item($cr, 's2'), 's2', 100_000),
            $this->baris($this->item($cr, 's3'), 's3', 90_000),
        ])->assertRedirect(route('budget-request.index'));

        $json = $this->actingAs($this->userWithRole('superadmin'))
            ->getJson('/budget-request/' . $cr->id . '/detail-json')
            ->assertOk()->json();

        $cmp = $json['settlement_comparison'];
        $this->assertSame(300_000, $cmp['request']);
        $this->assertSame(290_000, $cmp['settlement']);
        $this->assertSame($json['total_realization'], $cmp['settlement']);

        $pindah = collect($json['realizations'])->firstWhere('budget_detail_id', $this->sub['s4']->id);
        $this->assertTrue($pindah['is_moved']);
        $this->assertSame('Sub S1', $pindah['origin_label']);
    }

    /* AC8 — Used sub-item tujuan naik, asal turun; Used header Budget Plan tetap. */
    public function test_used_sub_item_mengikuti_settlement_dan_header_tetap(): void
    {
        $cr = $this->crPaid();
        $headerSebelum = $this->plan->fresh()->usedAmount();
        $this->assertSame(100_000, BudgetPlanDetail::find($this->sub['s1']->id)->usedAmount());
        $this->assertSame(0, BudgetPlanDetail::find($this->sub['s4']->id)->usedAmount());

        $this->submit($cr, [
            $this->baris($this->item($cr, 's1'), 's1', 40_000),
            $this->baris($this->item($cr, 's1'), 's4', 60_000, 'Material'),
            $this->baris($this->item($cr, 's2'), 's2', 100_000),
            $this->baris($this->item($cr, 's3'), 's3', 100_000),
        ])->assertRedirect(route('budget-request.index'));

        $this->assertSame(40_000, BudgetPlanDetail::find($this->sub['s1']->id)->usedAmount());
        $this->assertSame(60_000, BudgetPlanDetail::find($this->sub['s4']->id)->usedAmount());
        $this->assertSame($headerSebelum, $this->plan->fresh()->usedAmount());

        // Satu sumber: jumlah per sub-item CR = total settlement.
        $this->assertSame(300_000, array_sum($cr->fresh()->costByBudgetDetail()));
    }

    /* AC11 — e-sign tersimpan per ID baris realisasi (bukan nomor urut). */
    public function test_esign_draft_dikunci_ke_id_baris_realisasi(): void
    {
        $cr = $this->crPaid();
        $i1 = $this->item($cr, 's1');
        $png = 'data:image/png;base64,' . base64_encode(UploadedFile::fake()->image('ttd.png', 10, 10)->get());

        $this->actingAs($this->manager())
            ->post('/budget-request/' . $cr->id . '/save-realization-draft', [
                'realizations' => [
                    'a' => $this->baris($i1, 's1', 60_000),
                    'b' => $this->baris($i1, 's4', 40_000, 'Material'),
                    'c' => $this->baris($this->item($cr, 's2'), 's2', 100_000),
                    'd' => $this->baris($this->item($cr, 's3'), 's3', 100_000),
                ],
                // Tanda tangan untuk baris "b" (pecahan) saja.
                'signatures' => ['b' => $png],
            ])->assertRedirect(route('budget-request.index'));

        $this->assertSame('PAID', $cr->fresh()->status);
        $pecahan = $cr->realizations()->where('budget_detail_id', $this->sub['s4']->id)->firstOrFail();

        $this->assertDatabaseHas('budget_request_realization_attachments', [
            'budget_request_id' => $cr->id,
            'original_name'     => 'ttd-r' . $pecahan->id . '-' . $cr->code . '.png',
        ]);
    }

    private function draft(BudgetRequest $cr, array $rows, array $signatures = [])
    {
        return $this->actingAs($this->manager())
            ->post('/budget-request/' . $cr->id . '/save-realization-draft', array_filter([
                'realizations' => $rows,
                'signatures'   => $signatures ?: null,
            ]));
    }

    /*
     | Form baru selalu mengirim kunci `id` (kosong untuk baris baru). Baris
     | baru hasil Pecah/import TIDAK boleh mengambil alih baris tersimpan yang
     | dihapus user — lampiran & e-sign baris yang dihapus ikut dibuang.
     */
    public function test_form_baru_baris_tanpa_id_tidak_mengambil_alih_baris_yang_dihapus(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $cr  = $this->crPaid();
        $i1  = $this->item($cr, 's1');
        $png = 'data:image/png;base64,' . base64_encode(UploadedFile::fake()->image('ttd.png', 10, 10)->get());

        $this->draft($cr, [
            'a' => $this->baris($i1, 's1', 60_000),
            'b' => $this->baris($i1, 's4', 40_000, 'Material'),
            'c' => $this->baris($this->item($cr, 's2'), 's2', 100_000),
            'd' => $this->baris($this->item($cr, 's3'), 's3', 100_000),
        ], ['b' => $png])->assertRedirect(route('budget-request.index'));

        $lama        = $cr->realizations()->get();
        $dihapus     = $lama->firstWhere('budget_detail_id', $this->sub['s4']->id);
        $lampiranDel = $dihapus->attachments()->value('id');
        $ttdDel      = 'ttd-r' . $dihapus->id . '-' . $cr->code . '.png';
        $this->assertDatabaseHas('budget_request_realization_attachments', ['original_name' => $ttdDel]);

        $id = fn (string $sub) => $lama->firstWhere('budget_detail_id', $this->sub[$sub]->id)->id;

        // Baris s4 dihapus user; baris baru (id kosong) untuk item yang SAMA.
        $this->draft($cr, [
            'a'  => ['id' => $id('s1')] + $this->baris($i1, 's1', 60_000, null, false),
            'n9' => ['id' => null] + $this->baris($i1, 's5', 40_000, 'Pos additional'),
            'c'  => ['id' => $id('s2')] + $this->baris($this->item($cr, 's2'), 's2', 100_000, null, false),
            'd'  => ['id' => $id('s3')] + $this->baris($this->item($cr, 's3'), 's3', 100_000, null, false),
        ])->assertRedirect(route('budget-request.index'));

        $baru = $cr->realizations()->where('budget_detail_id', $this->sub['s5']->id)->firstOrFail();
        $this->assertNotSame($dihapus->id, $baru->id);
        $this->assertNull(BudgetRequestRealization::find($dihapus->id));
        $this->assertNull(\App\Models\RealizationDetailAttachment::find($lampiranDel));
        $this->assertDatabaseMissing('budget_request_realization_attachments', ['original_name' => $ttdDel]);
        $this->assertSame(4, $cr->realizations()->count());
        $this->assertSame([$id('s1'), $id('s2'), $id('s3')], $cr->realizations()
            ->whereIn('budget_detail_id', [$this->sub['s1']->id, $this->sub['s2']->id, $this->sub['s3']->id])
            ->orderBy('budget_detail_id')->pluck('id')->all());
    }

    /* Form lama (tidak satu baris pun membawa kunci `id`) tetap dipasangkan lewat item asal. */
    public function test_form_lama_tanpa_kunci_id_dipasangkan_lewat_item_asal(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $cr = $this->crPaid();
        [$i1, $i2, $i3] = [$this->item($cr, 's1'), $this->item($cr, 's2'), $this->item($cr, 's3')];

        $this->draft($cr, [
            $this->baris($i1, 's1', 100_000),
            $this->baris($i2, 's2', 100_000),
            $this->baris($i3, 's3', 100_000),
        ])->assertRedirect(route('budget-request.index'));

        $idSebelum = $cr->realizations()->orderBy('id')->pluck('id')->all();

        $this->draft($cr, [
            $this->baris($i1, 's1', 100_000, null, false),
            $this->baris($i2, 's2', 100_000, null, false),
            $this->baris($i3, 's3', 100_000, null, false),
        ])->assertRedirect(route('budget-request.index'));

        $this->assertSame($idSebelum, $cr->realizations()->orderBy('id')->pluck('id')->all());
        foreach ($cr->realizations()->get() as $r) {
            $this->assertSame(1, $r->attachments()->count());
        }
    }

    /* Item ikut yang barisnya dihapus semua = Settle 0: aturan bukti tetap berlaku. */
    public function test_item_tanpa_baris_dihitung_settle_nol_dalam_cek_bukti(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $cr = $this->crPaid();

        $this->draft($cr, [
            $this->baris($this->item($cr, 's1'), 's1', 100_000),
            $this->baris($this->item($cr, 's2'), 's2', 100_000),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $cr->realizations()->count());
    }

    /* AC9 — import Excel: baris tambahan dengan Budget Detail ID valid diterima, ID asing ditolak. */
    public function test_import_excel_menerima_baris_tambahan_dan_menolak_id_asing(): void
    {
        $cr    = $this->crPaid();
        $asing = BudgetPlanDetail::create([
            'budget_plan_id' => $this->makeBudgetPlan($this->project)->id, 'deskripsi' => 'Asing',
            'amount' => 1, 'qty' => 1, 'total' => 1,
        ]);

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Rincian Settlement');
        $sheet->fromArray([
            ['No', 'Request Detail ID', 'Budget Detail ID', 'Sub COA', 'Description', 'Requested', 'Actual Used'],
            [1, $this->item($cr, 's1')->id, $this->sub['s1']->id, '', '', 100000, 70000],
            [2, '', $this->sub['s4']->id, '', 'Tambahan', 0, 25000],
            [3, '', $asing->id, '', 'Asing', 0, 5000],
            [4, '', '', '', 'Tanpa Sub', 0, 1000],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'set') . '.xlsx';
        (new Xlsx($book))->save($path);

        $res = $this->actingAs($this->manager())
            ->post('/budget-request/' . $cr->id . '/settlement-import-preview', [
                'file' => new UploadedFile($path, 'settle.xlsx', null, null, true),
            ])->assertOk()->json();

        $this->assertCount(2, $res['rows']);
        $this->assertNull($res['rows'][1]['request_detail_id']);
        $this->assertSame($this->sub['s4']->id, $res['rows'][1]['budget_detail_id']);
        $this->assertSame(25_000, $res['rows'][1]['amount']);
        $this->assertCount(2, $res['errors']);

        @unlink($path);
    }
}
