<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CostCenter;
use App\Models\CostRegister;
use App\Models\DailyTransaction;
use App\Models\Project;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Expense (dulu Cost Register) = alur Cost Payment (Non-CA), Draft langsung VERIFIED:
 * DRAFT -> VERIFIED ("Submitted") -> APPROVED -> QUEUED -> CLOSED.
 * Hanya Finance Manager (input/ajukan) & CEO/approver (approve) — sejak 2026-11-10.
 * Rincian: akun (kategori dari akun) + tujuan PO atau Cost Center.
 * Biaya PO baru dihitung setelah dananya keluar lewat Treasury
 * (CostRegister::PO_COST_COUNTED_STATUSES).
 */
class CostRegisterWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');
        // Halaman Expense dijaga menu:cost-register sejak 2026-11-09.
        $this->giveMenuAccess($this->role('finance_manager'), 'cost-register');
        $this->giveMenuAccess($this->role('finance'), 'cost-register');
        // CEO membuka halaman Expense (detail, log) lewat Menu Access yang sama.
        $this->giveMenuAccess($this->role('approver'), 'cost-register');
    }

    private function fm()
    {
        return $this->userWithRole('finance_manager');
    }

    private function akun(string $kategori = 'Overhead'): Account
    {
        return Account::create(['name' => 'Akun ' . $kategori . ' ' . uniqid(), 'category' => $kategori, 'status' => 'ACTIVE']);
    }

    private function costCenter(): CostCenter
    {
        return CostCenter::create(['code' => 'OH-' . random_int(100, 999), 'name' => 'Kantor Pusat', 'type' => 'OVERHEAD', 'status' => 'ACTIVE']);
    }

    /** Data Cost Register lama (header satu PO + type). */
    private function register(string $status = 'DRAFT'): CostRegister
    {
        return CostRegister::create([
            'project_id'     => $this->makeProject()->id,
            'type'           => 'OVERHEAD',
            'amount'         => 500_000,
            'notes'          => 'Dasar alokasi uji',
            'beneficiary_id' => $this->makeBeneficiary()->id,
            'status'         => $status,
            'input_by'       => $this->fm()->id,
        ]);
    }

    public function test_store_expense_multi_po_dan_cost_center_dengan_kategori_dari_akun(): void
    {
        $po1 = $this->makeProject();
        $po2 = $this->makeProject();
        $cc  = $this->costCenter();
        $variabel = $this->akun('Variable Cost');
        $tetap    = $this->akun('Fixed Cost');

        $this->actingAs($this->fm())->post('/expense', [
            'period_start' => '2026-11',
            'notes'   => 'Biaya operasional uji',
            'amount'  => '999999999', // diabaikan: nominal berasal dari rincian
            'details' => [
                ['account_id' => $variabel->id, 'target' => 'po:' . $po1->id, 'description' => 'Material', 'qty' => 2, 'unit_price' => '1.000.000'],
                ['account_id' => $tetap->id,    'target' => 'po:' . $po2->id, 'description' => 'Gaji',     'qty' => 1, 'unit_price' => '500.000'],
                ['account_id' => $tetap->id,    'target' => 'cc:' . $cc->id,  'description' => 'Sewa',     'qty' => 1, 'unit_price' => '250.000'],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $cr = CostRegister::with('details')->firstOrFail();
        $this->assertSame(2_750_000, $cr->amount);
        $this->assertSame('DRAFT', $cr->status);
        $this->assertNull($cr->project_id);
        $this->assertNull($cr->type);
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{4}$/', $cr->expense_no);
        $this->assertSame(['Variable Cost', 'Fixed Cost', 'Fixed Cost'], $cr->details->pluck('cost_category')->all());
        $this->assertSame([$po1->id, $po2->id, null], $cr->details->pluck('project_id')->all());
        $this->assertSame($cc->id, $cr->details[2]->cost_center_id);
        $this->assertSame(1, $cr->logs()->where('action', 'created')->count());
    }

    public function test_store_wajib_rincian_akun_dan_tujuan_valid(): void
    {
        $fm = $this->fm();

        $this->actingAs($fm)->post('/expense', ['notes' => 'x'])->assertSessionHasErrors('details');

        $this->post('/expense', ['period_start' => '2026-11', 'notes' => 'x', 'details' => [
            ['account_id' => $this->akun()->id, 'target' => 'po:999999', 'description' => 'a', 'qty' => 1, 'unit_price' => '1000'],
        ]])->assertSessionHas('error');

        $this->post('/expense', ['period_start' => '2026-11', 'notes' => 'x', 'details' => [
            ['target' => 'po:' . $this->makeProject()->id, 'description' => 'a', 'qty' => 1, 'unit_price' => '1000'],
        ]])->assertSessionHasErrors('details.0.account_id');

        $this->assertSame(0, CostRegister::count());
    }

    public function test_role_finance_tidak_boleh_membuat_atau_mengajukan_expense(): void
    {
        $finance = $this->userWithRole('finance');
        $draft = $this->register('DRAFT');

        $this->actingAs($finance)->get('/expense/create')->assertForbidden();
        $this->post('/expense', ['notes' => 'x', 'details' => [
            ['account_id' => $this->akun()->id, 'target' => 'po:' . $this->makeProject()->id, 'description' => 'a', 'qty' => 1, 'unit_price' => '1000'],
        ]])->assertForbidden();
        $this->post('/expense/' . $draft->id . '/submit')->assertForbidden();

        $this->assertSame('DRAFT', $draft->refresh()->status);
        $this->assertSame(1, CostRegister::count());
    }

    public function test_submit_langsung_verified_lalu_ceo_approve(): void
    {
        $cr = $this->register('DRAFT');

        $this->actingAs($this->fm())->post('/expense/' . $cr->id . '/submit')
            ->assertSessionHas('success');
        $this->assertSame('VERIFIED', $cr->refresh()->status);
        $this->assertSame('Submitted', $cr->status_label);
        $this->assertNotNull($cr->verified_at);

        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $cr->id . '/approve')
            ->assertSessionHas('success');
        $this->assertSame('APPROVED', $cr->refresh()->status);
        $this->assertNotNull($cr->approved_at);
        $this->assertSame(1, $cr->logs()->where('status_from', 'VERIFIED')->where('status_to', 'APPROVED')->count());
    }

    public function test_finance_manager_tidak_boleh_approve_dan_approver_tidak_boleh_submit(): void
    {
        $verified = $this->register('VERIFIED');
        $draft    = $this->register('DRAFT');

        $this->actingAs($this->fm())->post('/expense/' . $verified->id . '/approve')->assertForbidden();
        $this->actingAs($this->userWithRole('finance'))->post('/expense/' . $verified->id . '/approve')->assertForbidden();
        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $draft->id . '/submit')->assertForbidden();

        $this->assertSame('VERIFIED', $verified->refresh()->status);
        $this->assertSame('DRAFT', $draft->refresh()->status);
    }

    public function test_reject_wajib_catatan_lalu_fm_revisi_ke_draft_atau_batal(): void
    {
        $cr = $this->register('VERIFIED');
        $lain = $this->register('VERIFIED');
        $approver = $this->userWithRole('approver');

        $this->actingAs($approver)->post('/expense/' . $cr->id . '/reject', [])->assertSessionHasErrors('notes');
        $this->assertSame('VERIFIED', $cr->refresh()->status);

        $this->post('/expense/' . $cr->id . '/reject', ['notes' => 'Lengkapi dasar'])->assertSessionHas('success');
        $this->assertSame('REJECTED', $cr->refresh()->status);
        $this->post('/expense/' . $lain->id . '/reject', ['notes' => 'Tidak perlu'])->assertSessionHas('success');

        // CEO tidak bisa merevisi; FM bisa.
        $this->post('/expense/' . $cr->id . '/reopen')->assertForbidden();
        $this->actingAs($this->fm())->post('/expense/' . $cr->id . '/reopen')
            ->assertRedirect(route('cost-register.edit', $cr->id));
        $this->assertSame('DRAFT', $cr->refresh()->status);

        // Rejected bisa dibatalkan.
        $this->post('/expense/' . $lain->id . '/cancel')->assertSessionHas('success');
        $this->assertSame('CANCELLED', $lain->refresh()->status);

        // Revisi hanya dari Rejected.
        $this->post('/expense/' . $cr->id . '/reopen')->assertSessionHas('error');
    }

    public function test_hapus_hanya_draft_dan_batal_selama_belum_queued(): void
    {
        $fm = $this->fm();
        $verified = $this->register('VERIFIED');
        $queued   = $this->register('QUEUED');

        $this->actingAs($fm)->delete('/expense/' . $verified->id)->assertSessionHas('error');
        $this->assertDatabaseHas('cost_registers', ['id' => $verified->id]);

        $this->post('/expense/' . $verified->id . '/cancel')->assertSessionHas('success');
        $this->assertSame('CANCELLED', $verified->refresh()->status);

        $this->post('/expense/' . $queued->id . '/cancel')->assertSessionHas('error');
        $this->assertSame('QUEUED', $queued->refresh()->status);
    }

    public function test_biaya_po_dari_expense_hanya_setelah_dananya_keluar(): void
    {
        $po = $this->makeProject();
        $akunVar = $this->akun('Variable Cost');
        $akunFix = $this->akun('Fixed Cost');
        $akunOh  = $this->akun('Overhead');

        $buat = function (string $status, array $baris) use ($po) {
            $cr = CostRegister::create([
                'expense_no' => 'EXP-UJI-' . uniqid(), 'amount' => array_sum(array_column($baris, 2)),
                'notes' => 'uji', 'status' => $status, 'input_by' => $this->fm()->id,
            ]);
            foreach ($baris as [$akun, $proyek, $total]) {
                $cr->details()->create([
                    'account_id' => $akun->id, 'cost_category' => $akun->category, 'project_id' => $proyek, 'allocation' => 'po',
                    'description' => 'x', 'qty' => 1, 'unit_price' => $total, 'total' => $total,
                ]);
            }
        };

        $lain = $this->makeProject();
        // Sudah dibayar lewat Treasury -> dihitung.
        $buat('PAID',   [[$akunVar, $po->id, 100_000], [$akunFix, $po->id, 20_000], [$akunOh, $lain->id, 7_000]]);
        $buat('CLOSED', [[$akunOh, $po->id, 3_000]]);
        // Belum keluar uang / keluar alur -> TIDAK dihitung, termasuk yang sudah di-approve CEO.
        $buat('DRAFT',     [[$akunVar, $po->id, 900_000]]);
        $buat('VERIFIED',  [[$akunVar, $po->id, 900_000]]);
        $buat('APPROVED',  [[$akunVar, $po->id, 900_000]]);
        $buat('QUEUED',    [[$akunVar, $po->id, 900_000]]);
        $buat('CANCELLED', [[$akunFix, $po->id, 800_000]]);

        $this->assertSame(
            ['Variable Cost' => 100_000, 'Fixed Cost' => 20_000, 'Overhead' => 3_000],
            $po->fresh()->expenseCostByCategory()
        );
        $this->assertSame(100_000, $po->fresh()->variableCostTotal()); // tanpa Cost Request
        $this->assertSame(20_000, $po->fresh()->fixedCostTotal());
        $this->assertSame(3_000, $po->fresh()->overheadCostTotal());
    }

    public function test_fixed_cost_dan_overhead_po_menjumlahkan_kolom_manual_dan_expense(): void
    {
        $po = $this->makeProject(['fixed_cost' => 5_000_000, 'overhead' => 2_000_000]);

        // Tanpa Expense: murni kolom manual PO.
        $this->assertSame(5_000_000, $po->fresh()->fixedCostTotal());
        $this->assertSame(2_000_000, $po->fresh()->overheadCostTotal());

        $cr = CostRegister::create([
            'expense_no' => 'EXP-UJI-MANUAL', 'amount' => 1_300_000, 'notes' => 'uji',
            'status' => 'CLOSED', 'input_by' => $this->fm()->id,
        ]);
        foreach ([['Fixed Cost', 1_000_000], ['Overhead', 300_000]] as [$kategori, $total]) {
            $akun = $this->akun($kategori);
            $cr->details()->create([
                'account_id' => $akun->id, 'cost_category' => $akun->category, 'project_id' => $po->id,
                'allocation' => 'po', 'description' => 'x', 'qty' => 1, 'unit_price' => $total, 'total' => $total,
            ]);
        }

        $this->assertSame(6_000_000, $po->fresh()->fixedCostTotal());
        $this->assertSame(2_300_000, $po->fresh()->overheadCostTotal());

        // Angka yang sama lewat jalur preload massal (daftar PO & Finance Summary).
        $daftar = Project::whereKey($po->id)->get();
        Project::preloadCostRegisterCosts($daftar);
        $this->assertSame(6_000_000, $daftar[0]->fixedCostTotal());
        $this->assertSame(2_300_000, $daftar[0]->overheadCostTotal());
    }

    public function test_treasury_hanya_menerima_register_approved_dan_takeout_mengembalikan_ke_approved(): void
    {
        $verified = $this->register('VERIFIED');
        $approved = $this->register('APPROVED');
        $treasury = $this->userWithRole('treasury');
        $fund = SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE', 'is_main_account' => false]);

        $this->actingAs($treasury)->post('/daily-transaction', [
            'type' => 'CASH_OUT', 'description' => 'Batch uji', 'source_fund_id' => $fund->id,
            'cost_register_ids' => [$verified->id],
        ])->assertSessionHas('error');
        $this->assertSame('VERIFIED', $verified->refresh()->status);

        $this->actingAs($treasury)->post('/daily-transaction', [
            'type' => 'CASH_OUT', 'description' => 'Batch uji', 'source_fund_id' => $fund->id,
            'cost_register_ids' => [$approved->id],
        ])->assertSessionMissing('error');

        $batch = DailyTransaction::latest('id')->firstOrFail();
        $this->actingAs($treasury)->post('/daily-transaction/' . $batch->id . '/submit');
        $this->assertSame('QUEUED', $approved->refresh()->status);

        $item = $batch->items()->firstOrFail();
        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-item/' . $item->id, ['notes' => 'Keluarkan']);
        $this->assertSame('APPROVED', $approved->refresh()->status);
        $this->assertSame(1, $approved->logs()->where('action', 'unbatched')->count());
    }

    public function test_mark_paid_menjadikan_register_closed(): void
    {
        $cr = $this->register('QUEUED');
        $batch = DailyTransaction::create([
            'code' => 'DT-UJI-' . uniqid(), 'date' => now()->toDateString(), 'type' => 'CASH_OUT',
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE'])->id,
            'status' => 'DONE', 'created_by' => $cr->input_by,
        ]);
        $batch->items()->create(['cost_register_id' => $cr->id, 'status' => 'INCLUDED']);

        $this->actingAs($this->userWithRole('treasury'))->post('/daily-transaction/' . $batch->id . '/mark-paid', [
            'charge' => '0', 'payment_notes' => 'Transfer uji',
            'attachments' => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')],
        ])->assertSessionMissing('error');

        $this->assertSame('CLOSED', $cr->refresh()->status);
        $this->assertNotNull($cr->paid_at);
        $this->assertSame(1, $cr->logs()->where('status_to', 'CLOSED')->count());
    }

    public function test_catatan_log_dan_detail_json(): void
    {
        $cr = $this->register('VERIFIED');
        $user = $this->userWithRole('approver');

        $this->actingAs($user)->postJson('/expense/' . $cr->id . '/log-note', ['notes' => 'Cek dulu'])
            ->assertOk()->assertJsonPath('log.notes', 'Cek dulu');

        $json = $this->getJson('/expense/' . $cr->id . '/detail-json')->assertOk()->json();
        $this->assertContains('note', array_column($json['logs'], 'kind'));
        $this->assertTrue($json['can_approve']);
        $this->assertTrue($json['is_legacy']);

        $closed = $this->register('CLOSED');
        $this->postJson('/expense/' . $closed->id . '/log-note', ['notes' => 'Terlambat'])->assertStatus(422);
    }

    public function test_filter_tanggal_export_dan_redirect_url_lama(): void
    {
        $lama = $this->register('DRAFT');
        $lama->forceFill(['created_at' => now()->subMonths(3)])->save();
        $baru = $this->register('DRAFT');

        $fm = $this->fm();
        $html = $this->actingAs($fm)->get('/expense?date_from=' . now()->startOfMonth()->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'))
            ->assertOk()->getContent();
        $this->assertStringContainsString('data-id="' . $baru->id . '"', $html);
        $this->assertStringNotContainsString('data-id="' . $lama->id . '"', $html);

        $this->get('/expense/export?date_from=' . now()->subYear()->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->get('/cost-register')->assertRedirect('/expense');
    }

    public function test_role_lain_tidak_boleh_mengakses_modul(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get('/expense')->assertForbidden();
    }

    /* ---------------------------------------------- Fase 2: Expense Cash Advance + settlement */

    /** Expense CA baru (header tanpa PO/type) dengan satu rincian ke $po. */
    private function expenseCa(string $status, ?\App\Models\Project $po = null, int $nominal = 1_000_000): CostRegister
    {
        $po ??= $this->makeProject();
        $akun = $this->akun('Variable Cost');
        $cr = CostRegister::create([
            'expense_no' => 'EXP-UJI-' . uniqid(), 'is_cash_advance' => true, 'amount' => $nominal,
            'notes' => 'Uang muka uji', 'beneficiary_id' => $this->makeBeneficiary()->id,
            'status' => $status, 'input_by' => $this->fm()->id,
        ]);
        $cr->details()->create([
            'account_id' => $akun->id, 'cost_category' => 'Variable Cost', 'project_id' => $po->id, 'allocation' => 'po',
            'description' => 'Uang muka', 'qty' => 1, 'unit_price' => $nominal, 'total' => $nominal,
        ]);

        return $cr;
    }

    private function batchDone(CostRegister $cr, array $item): DailyTransaction
    {
        $batch = DailyTransaction::create([
            'code' => 'DT-UJI-' . uniqid(), 'date' => now()->toDateString(), 'type' => 'CASH_OUT',
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji ' . uniqid(), 'status' => 'ACTIVE'])->id,
            'status' => 'DONE', 'created_by' => $cr->input_by,
        ]);
        $batch->items()->create($item + ['status' => 'INCLUDED']);

        return $batch;
    }

    private function markPaid(DailyTransaction $batch): void
    {
        $this->actingAs($this->userWithRole('treasury'))->post('/daily-transaction/' . $batch->id . '/mark-paid', [
            'charge' => '0', 'payment_notes' => 'Transfer uji',
            'attachments' => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')],
        ])->assertSessionMissing('error');
    }

    private function settle(CostRegister $cr, int $realisasi, ?\App\Models\Project $po = null)
    {
        return $this->actingAs($this->fm())->post('/expense/' . $cr->id . '/settle', [
            'details' => [[
                'account_id' => $this->akun('Fixed Cost')->id, 'target' => 'po:' . ($po ?? $this->makeProject())->id,
                'description' => 'Realisasi', 'qty' => 1, 'unit_price' => (string) $realisasi,
            ]],
            'attachments' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')],
        ]);
    }

    public function test_store_expense_cash_advance(): void
    {
        $this->actingAs($this->fm())->post('/expense', [
            'is_cash_advance' => '1', 'period_start' => '2026-11', 'notes' => 'Uang muka proyek',
            'details' => [['account_id' => $this->akun()->id, 'target' => 'po:' . $this->makeProject()->id,
                'description' => 'Uang muka', 'qty' => 1, 'unit_price' => '1.000.000']],
        ])->assertSessionHasNoErrors();

        $cr = CostRegister::firstOrFail();
        $this->assertTrue($cr->is_cash_advance);
        $this->assertSame('Expense CA', $cr->type_label);
    }

    public function test_mark_paid_expense_ca_menjadi_paid(): void
    {
        $ca = $this->expenseCa('QUEUED');
        $this->markPaid($this->batchDone($ca, ['cost_register_id' => $ca->id]));

        $this->assertSame('PAID', $ca->refresh()->status);
        $this->assertNotNull($ca->paid_at);
    }

    public function test_settlement_hanya_finance_manager_dan_wajib_bukti(): void
    {
        $ca = $this->expenseCa('PAID');

        $this->actingAs($this->userWithRole('finance'))->get('/expense/' . $ca->id . '/settle')->assertForbidden();
        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $ca->id . '/settle', [])->assertForbidden();

        $this->actingAs($this->fm())->get('/expense/' . $ca->id . '/settle')->assertOk();
        $this->post('/expense/' . $ca->id . '/settle', ['details' => [[
            'account_id' => $this->akun()->id, 'target' => 'po:' . $this->makeProject()->id,
            'description' => 'x', 'qty' => 1, 'unit_price' => '900000',
        ]]])->assertSessionHas('error');
        $this->assertSame('PAID', $ca->refresh()->status);

        $this->settle($ca, 900_000)->assertSessionHas('success');
        $ca->refresh();
        $this->assertSame('SETTLE_SUBMITTED', $ca->status);
        $this->assertSame(900_000, $ca->settled_amount);
        $this->assertSame(100_000, $ca->refund_amount);
        $this->assertSame(0, $ca->shortfall_amount);
        $this->assertSame(1, $ca->settlementAttachments()->count());
    }

    public function test_ceo_tolak_settlement_jadi_settle_rejected_dan_fm_tidak_boleh_approve(): void
    {
        $ca = $this->expenseCa('PAID');
        $this->settle($ca, 900_000);

        $this->actingAs($this->fm())->post('/expense/' . $ca->id . '/settle-approve')->assertForbidden();

        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $ca->id . '/settle-reject', [])->assertSessionHasErrors('notes');
        $this->post('/expense/' . $ca->id . '/settle-reject', ['notes' => 'Nota kurang'])->assertSessionHas('success');

        $this->assertSame('SETTLE_REJECTED', $ca->refresh()->status);
        $this->assertSame(1, $ca->logs()->where('action', 'settle_rejected')->count());

        // Finance Manager memperbaiki lalu mengajukan ulang dari Settle Rejected.
        $this->actingAs($this->fm())->get('/expense/' . $ca->id . '/settle')->assertOk();
        $this->settle($ca, 950_000)->assertSessionHas('success');
        $this->assertSame('SETTLE_SUBMITTED', $ca->refresh()->status);
        $this->assertSame(950_000, $ca->settled_amount);
    }

    public function test_approve_settlement_kelebihan_closed_dan_biaya_po_dari_realisasi(): void
    {
        $poAwal = $this->makeProject();
        $poRealisasi = $this->makeProject();
        $ca = $this->expenseCa('PAID', $poAwal, 1_000_000);

        // Sebelum settlement disetujui: biaya PO dari rincian pengajuan.
        $this->assertSame(1_000_000, $poAwal->fresh()->expenseCostByCategory()['Variable Cost']);

        $this->settle($ca, 800_000, $poRealisasi);
        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $ca->id . '/settle-approve')->assertSessionHas('success');

        $ca->refresh();
        $this->assertSame('CLOSED', $ca->status);
        $this->assertSame(200_000, $ca->refund_amount);
        $this->assertSame(0, $ca->secondPayments()->count());

        // Setelah disetujui: biaya pindah ke rincian realisasi (akun Fixed Cost, PO realisasi).
        $this->assertSame(['Variable Cost' => 0, 'Fixed Cost' => 0, 'Overhead' => 0], $poAwal->fresh()->expenseCostByCategory());
        $this->assertSame(800_000, $poRealisasi->fresh()->expenseCostByCategory()['Fixed Cost']);
    }

    public function test_approve_settlement_kekurangan_dibayar_lewat_treasury_lalu_closed(): void
    {
        $ca = $this->expenseCa('PAID', null, 1_000_000);
        $this->settle($ca, 1_250_000);
        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $ca->id . '/settle-approve');

        $ca->refresh();
        $this->assertSame('SETTLE_APPROVED', $ca->status);
        $sp = $ca->secondPayments()->firstOrFail();
        $this->assertSame(250_000, $sp->amount);
        $this->assertSame('PENDING', $sp->status);

        // Treasury: batch dari Second Payment Expense, submit, lalu dibayar.
        $treasury = $this->userWithRole('treasury');
        $fund = SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE', 'is_main_account' => false]);
        $this->actingAs($treasury)->post('/daily-transaction', [
            'type' => 'CASH_OUT', 'description' => 'Batch 2nd', 'source_fund_id' => $fund->id,
            'expense_second_payment_ids' => [$sp->id],
        ])->assertSessionMissing('error');

        $batch = DailyTransaction::latest('id')->firstOrFail();
        $item = $batch->items()->firstOrFail();
        $this->assertSame($ca->id, (int) $item->cost_register_id);
        $this->assertTrue($item->isExpenseSecondPayment());
        $this->assertSame(250_000, $batch->fresh()->total_amount);

        $this->actingAs($treasury)->post('/daily-transaction/' . $batch->id . '/submit')->assertSessionMissing('error');
        $this->assertSame('SETTLE_APPROVED', $ca->refresh()->status); // submit tidak mengubah induk

        $batch->update(['status' => 'DONE']);
        $this->markPaid($batch);

        $this->assertSame('PAID', $sp->refresh()->status);
        $this->assertSame('CLOSED', $ca->refresh()->status);
        $this->assertSame(1, $ca->logs()->where('action', 'second_payment_paid')->count());
    }

    public function test_takeout_second_payment_expense_tetap_pending(): void
    {
        $ca = $this->expenseCa('SETTLE_APPROVED');
        $sp = $ca->secondPayments()->create(['amount' => 50_000, 'status' => 'PENDING']);
        $batch = $this->batchDone($ca, ['cost_register_id' => $ca->id, 'cost_register_second_payment_id' => $sp->id]);
        $batch->update(['status' => 'PENDING_APPROVER']);
        $item = $batch->items()->firstOrFail();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/daily-transaction/' . $batch->id . '/reject-item/' . $item->id, ['notes' => 'Tunda']);

        $this->assertSame('REJECTED', $item->refresh()->status);
        $this->assertSame('PENDING', $sp->refresh()->status);
        $this->assertSame('SETTLE_APPROVED', $ca->refresh()->status);
        $this->assertSame(1, \App\Models\CostRegisterSecondPayment::availableForBatch()->count());
    }
    /* ---------------------------------------------- Fase 3: periode, alokasi, edit, rejected */

    /** POST /expense minimal yang sah (satu rincian Fixed Cost ke PO). */
    private function payloadExpense(array $extra = [], ?array $details = null): array
    {
        return array_merge([
            'period_start' => '2026-01',
            'notes'        => 'Uji periode',
            'details'      => $details ?? [['account_id' => $this->akun('Fixed Cost')->id, 'target' => 'po:' . $this->makeProject()->id,
                'description' => 'Sewa', 'qty' => 1, 'unit_price' => '5.000.000']],
        ], $extra);
    }

    public function test_periode_bagi_rata_dan_sisa_rupiah_ke_bulan_awal(): void
    {
        $this->actingAs($this->fm())->post('/expense', $this->payloadExpense(['period_end' => '2026-05', 'period_method' => 'equal']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $cr = CostRegister::with('periods')->firstOrFail();
        $this->assertSame('2026-01-01', $cr->period_start->toDateString());
        $this->assertSame('2026-05-01', $cr->period_end->toDateString());
        $this->assertSame(array_fill(0, 5, 1_000_000), $cr->periods->pluck('amount')->all());

        $this->assertSame([3, 3, 2], CostRegister::splitEqual(8, 3));
        $this->assertNull(CostRegister::splitEqual(2, 3));
    }

    public function test_periode_manual_wajib_tidak_nol_dan_jumlah_sama(): void
    {
        $fm = $this->fm();
        $dasar = ['period_end' => '2026-03', 'period_method' => 'manual'];

        $this->actingAs($fm)->post('/expense', $this->payloadExpense($dasar + ['period_amounts' => ['2026-01' => '5.000.000', '2026-02' => '0', '2026-03' => '0']]))
            ->assertSessionHas('error');
        $this->post('/expense', $this->payloadExpense($dasar + ['period_amounts' => ['2026-01' => '1.000.000', '2026-02' => '1.000.000', '2026-03' => '1.000.000']]))
            ->assertSessionHas('error');
        $this->post('/expense', $this->payloadExpense(['period_end' => '2025-12']))->assertSessionHas('error');
        $this->assertSame(0, CostRegister::count());

        $this->post('/expense', $this->payloadExpense($dasar + ['period_amounts' => ['2026-01' => '3.000.000', '2026-02' => '1.500.000', '2026-03' => '500.000']]))
            ->assertSessionHas('success');
        $this->assertSame([3_000_000, 1_500_000, 500_000], CostRegister::firstOrFail()->periods->pluck('amount')->all());
    }

    public function test_variable_cost_wajib_satu_po(): void
    {
        $var = $this->akun('Variable Cost');
        $this->actingAs($this->fm())->post('/expense', $this->payloadExpense([], [
            ['account_id' => $var->id, 'target' => 'annual:' . now()->year, 'description' => 'x', 'qty' => 1, 'unit_price' => '1000'],
        ]))->assertSessionHas('error');
        $this->post('/expense', $this->payloadExpense([], [
            ['account_id' => $var->id, 'target' => 'cc:' . $this->costCenter()->id, 'description' => 'x', 'qty' => 1, 'unit_price' => '1000'],
        ]))->assertSessionHas('error');
        $this->assertSame(0, CostRegister::count());
    }

    public function test_alokasi_tahunan_proporsional_nilai_po_disetujui_tahun_itu(): void
    {
        $tahun = (int) now()->year;
        $a = $this->makeProject(['value' => 600_000_000]);
        $b = $this->makeProject(['value' => 300_000_000]);
        $c = $this->makeProject(['value' => 100_000_000]);
        $draftPo = $this->makeProject(['value' => 900_000_000, 'status' => 'DRAFT']);      // belum disetujui
        $tahunLalu = $this->makeProject(['value' => 900_000_000]);
        $tahunLalu->forceFill(['created_at' => now()->subYear()])->save();                 // beda tahun

        $this->actingAs($this->fm())->post('/expense', $this->payloadExpense([], [
            ['account_id' => $this->akun('Overhead')->id, 'target' => 'annual:' . $tahun, 'description' => 'Overhead kantor', 'qty' => 1, 'unit_price' => '100.000.001'],
        ]))->assertSessionHas('success');
        $cr = CostRegister::firstOrFail();

        // Draft belum dihitung; disetujui CEO pun belum (uang belum keluar).
        $this->assertSame(0, $a->fresh()->expenseCostByCategory()['Overhead']);
        $cr->update(['status' => 'APPROVED']);
        $this->assertSame(0, $a->fresh()->expenseCostByCategory()['Overhead']);

        $cr->update(['status' => 'CLOSED']);
        $porsi = \App\Support\ExpenseAllocation::forProjects([$a->id, $b->id, $c->id, $draftPo->id, $tahunLalu->id]);
        $this->assertSame(60_000_001, $porsi[$a->id]['Overhead']); // sisa 1 rupiah ke pecahan terbesar
        $this->assertSame(30_000_000, $porsi[$b->id]['Overhead']);
        $this->assertSame(10_000_000, $porsi[$c->id]['Overhead']);
        $this->assertSame(0, $porsi[$draftPo->id]['Overhead']);
        $this->assertSame(0, $porsi[$tahunLalu->id]['Overhead']);
        $this->assertSame(100_000_001, $porsi[$a->id]['Overhead'] + $porsi[$b->id]['Overhead'] + $porsi[$c->id]['Overhead']);

        // Dihitung ulang: PO baru tahun ini ikut menerima porsi (total nilai PO kini 2 M;
        // 100.000.001 × 1/2 = 50.000.000,5 -> sisa 1 rupiah jatuh ke pecahan terbesar).
        $d = $this->makeProject(['value' => 1_000_000_000]);
        $this->assertSame(50_000_001, $d->fresh()->overheadCostTotal());
        $this->assertSame(30_000_000, \App\Support\ExpenseAllocation::forProjects([$a->id])[$a->id]['Overhead']);
    }

    public function test_alokasi_po_terpilih_proporsional_dan_wajib_po_disetujui(): void
    {
        $a = $this->makeProject(['value' => 750_000_000]);
        $b = $this->makeProject(['value' => 250_000_000]);
        $draftPo = $this->makeProject(['status' => 'SUBMITTED']);
        $fix = $this->akun('Fixed Cost');
        $fm = $this->fm();

        $this->actingAs($fm)->post('/expense', $this->payloadExpense([], [
            ['account_id' => $fix->id, 'target' => 'selected', 'project_ids' => [$a->id], 'description' => 'x', 'qty' => 1, 'unit_price' => '1000'],
        ]))->assertSessionHas('error'); // minimal 2 PO
        $this->post('/expense', $this->payloadExpense([], [
            ['account_id' => $fix->id, 'target' => 'selected', 'project_ids' => [$a->id, $draftPo->id], 'description' => 'x', 'qty' => 1, 'unit_price' => '1000'],
        ]))->assertSessionHas('error'); // PO belum disetujui
        $this->assertSame(0, CostRegister::count());

        $this->post('/expense', $this->payloadExpense([], [
            ['account_id' => $fix->id, 'target' => 'selected', 'project_ids' => [$a->id, $b->id], 'description' => 'Gaji PM', 'qty' => 2, 'unit_price' => '10.000.000'],
        ]))->assertSessionHas('success');
        $cr = CostRegister::with('details.projects')->firstOrFail();
        $this->assertSame('selected', $cr->details[0]->allocation);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $cr->details[0]->projects->pluck('id')->all());

        // Submitted belum jadi biaya PO; baru setelah batch Treasury dibayar (CLOSED).
        $cr->update(['status' => 'VERIFIED']);
        $this->assertSame(0, $a->fresh()->fixedCostTotal());

        $cr->update(['status' => 'CLOSED']);
        $this->assertSame(15_000_000, $a->fresh()->fixedCostTotal());
        $this->assertSame(5_000_000, $b->fresh()->fixedCostTotal());

        // Laporan PO: baris + porsi.
        $baris = \App\Support\ExpenseAllocation::linesForProject($b->id);
        $this->assertCount(1, $baris);
        $this->assertSame(5_000_000, $baris[0]['share']);
    }

    public function test_biaya_po_tidak_menghitung_expense_rejected(): void
    {
        $po = $this->makeProject();
        $cr = CostRegister::create(['expense_no' => 'EXP-UJI-R', 'amount' => 70_000, 'notes' => 'x', 'status' => 'REJECTED', 'input_by' => $this->fm()->id]);
        $cr->details()->create(['account_id' => $this->akun('Variable Cost')->id, 'cost_category' => 'Variable Cost', 'project_id' => $po->id,
            'allocation' => 'po', 'description' => 'x', 'qty' => 1, 'unit_price' => 70_000, 'total' => 70_000]);

        $this->assertSame(0, $po->fresh()->expenseCostByCategory()['Variable Cost']);
    }

    public function test_edit_draft_mengganti_rincian_dan_periode_selain_draft_ditolak(): void
    {
        $fm = $this->fm();
        $this->actingAs($fm)->post('/expense', $this->payloadExpense(['period_end' => '2026-02']))->assertSessionHas('success');
        $cr = CostRegister::firstOrFail();

        $this->get('/expense/' . $cr->id . '/edit')->assertOk();
        $this->put('/expense/' . $cr->id, $this->payloadExpense(['period_start' => '2026-03', 'period_end' => '2026-03', 'notes' => 'Diubah'], [
            ['account_id' => $this->akun('Overhead')->id, 'target' => 'cc:' . $this->costCenter()->id, 'description' => 'Listrik', 'qty' => 3, 'unit_price' => '200.000'],
        ]))->assertSessionHas('success');

        $cr->refresh()->load('details', 'periods');
        $this->assertSame(600_000, $cr->amount);
        $this->assertSame('Diubah', $cr->notes);
        $this->assertCount(1, $cr->details);
        $this->assertSame('cc', $cr->details[0]->allocation);
        $this->assertSame([600_000], $cr->periods->pluck('amount')->all());
        $this->assertSame(1, $cr->logs()->where('action', 'updated')->count());

        $cr->update(['status' => 'VERIFIED']);
        $this->get('/expense/' . $cr->id . '/edit')->assertRedirect(route('cost-register.index'));
        $this->put('/expense/' . $cr->id, $this->payloadExpense())->assertSessionHas('error');
        $this->assertSame(600_000, $cr->refresh()->amount);

        // Hapus Draft ikut menghapus periode (FK RESTRICT).
        $cr->update(['status' => 'DRAFT']);
        $this->delete('/expense/' . $cr->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('cost_registers', ['id' => $cr->id]);
        $this->assertDatabaseMissing('cost_register_periods', ['cost_register_id' => $cr->id]);
    }

    public function test_settlement_disetujui_menyesuaikan_periode_ke_realisasi(): void
    {
        $ca = $this->expenseCa('PAID', null, 1_000_000);
        $ca->update(['period_start' => '2026-01-01', 'period_end' => '2026-02-01', 'period_method' => 'manual']);
        $ca->periods()->createMany([['period' => '2026-01-01', 'amount' => 750_000], ['period' => '2026-02-01', 'amount' => 250_000]]);

        $this->settle($ca, 800_000);
        $this->actingAs($this->userWithRole('approver'))->post('/expense/' . $ca->id . '/settle-approve')->assertSessionHas('success');

        $this->assertSame([600_000, 200_000], $ca->refresh()->periods->pluck('amount')->all());

        // Skala ulang: jumlah tepat, tidak ada periode 0; null bila total < jumlah periode.
        $this->assertSame([1, 1, 2], CostRegister::rescale([0, 0, 10], 4));
        $this->assertNull(CostRegister::rescale([1, 1, 1], 2));
    }
}
