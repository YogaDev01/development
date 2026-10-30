<?php

namespace Tests\Feature;

use App\Models\CostRegister;
use App\Models\DailyTransaction;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Cost Register = alur Cost Payment (Non-CA), Draft langsung VERIFIED:
 * DRAFT -> VERIFIED ("Submitted") -> APPROVED -> QUEUED -> CLOSED.
 */
class CostRegisterWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');
    }

    private function register(string $status = 'DRAFT'): CostRegister
    {
        return CostRegister::create([
            'project_id'     => $this->makeProject()->id,
            'type'           => 'OVERHEAD',
            'amount'         => 500_000,
            'notes'          => 'Dasar alokasi uji',
            'beneficiary_id' => $this->makeBeneficiary()->id,
            'status'         => $status,
            'input_by'       => $this->userWithRole('finance')->id,
        ]);
    }

    public function test_store_menghitung_nominal_dari_rincian_di_server(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->userWithRole('finance'))->post('/cost-register', [
            'mode'       => 'single',
            'project_id' => $project->id,
            'type'       => 'FIXED_COST',
            'notes'      => 'Prorate personel',
            'amount'     => '999999999', // diabaikan: nominal berasal dari rincian
            'details'    => [
                ['description' => 'Gaji A', 'qty' => 2, 'unit_price' => '1.000.000'],
                ['description' => 'Gaji B', 'qty' => 1, 'unit_price' => '500.000'],
            ],
        ])->assertSessionHasNoErrors();

        $cr = CostRegister::firstOrFail();
        $this->assertSame(2_500_000, $cr->amount);
        $this->assertSame('DRAFT', $cr->status);
        $this->assertSame(2, $cr->details()->count());
        $this->assertSame(1, $cr->logs()->where('action', 'created')->count());
    }

    public function test_store_wajib_punya_rincian(): void
    {
        $this->actingAs($this->userWithRole('finance'))->post('/cost-register', [
            'mode' => 'single', 'project_id' => $this->makeProject()->id, 'type' => 'OVERHEAD', 'notes' => 'x',
        ])->assertSessionHasErrors('details');

        $this->assertSame(0, CostRegister::count());
    }

    public function test_submit_langsung_verified_lalu_approver_approve(): void
    {
        $cr = $this->register('DRAFT');

        $this->actingAs($this->userWithRole('finance'))->post('/cost-register/' . $cr->id . '/submit')
            ->assertSessionHas('success');
        $this->assertSame('VERIFIED', $cr->refresh()->status);
        $this->assertSame('Submitted', $cr->status_label);
        $this->assertNotNull($cr->verified_at);

        $this->actingAs($this->userWithRole('approver'))->post('/cost-register/' . $cr->id . '/approve')
            ->assertSessionHas('success');
        $this->assertSame('APPROVED', $cr->refresh()->status);
        $this->assertNotNull($cr->approved_at);
        $this->assertSame(1, $cr->logs()->where('status_from', 'VERIFIED')->where('status_to', 'APPROVED')->count());
    }

    public function test_finance_tidak_boleh_approve_dan_approver_tidak_boleh_submit(): void
    {
        $verified = $this->register('VERIFIED');
        $draft    = $this->register('DRAFT');

        $this->actingAs($this->userWithRole('finance'))->post('/cost-register/' . $verified->id . '/approve')->assertForbidden();
        $this->actingAs($this->userWithRole('approver'))->post('/cost-register/' . $draft->id . '/submit')->assertForbidden();

        $this->assertSame('VERIFIED', $verified->refresh()->status);
        $this->assertSame('DRAFT', $draft->refresh()->status);
    }

    public function test_revise_wajib_catatan_dan_kembali_ke_draft(): void
    {
        $cr = $this->register('VERIFIED');
        $approver = $this->userWithRole('approver');

        $this->actingAs($approver)->post('/cost-register/' . $cr->id . '/revise', [])->assertSessionHasErrors('notes');
        $this->assertSame('VERIFIED', $cr->refresh()->status);

        $this->post('/cost-register/' . $cr->id . '/revise', ['notes' => 'Lengkapi dasar'])->assertSessionHas('success');
        $this->assertSame('DRAFT', $cr->refresh()->status);
    }

    public function test_hapus_hanya_draft_dan_batal_selama_belum_queued(): void
    {
        $finance = $this->userWithRole('finance');
        $verified = $this->register('VERIFIED');
        $queued   = $this->register('QUEUED');

        $this->actingAs($finance)->delete('/cost-register/' . $verified->id)->assertSessionHas('error');
        $this->assertDatabaseHas('cost_registers', ['id' => $verified->id]);

        $this->post('/cost-register/' . $verified->id . '/cancel')->assertSessionHas('success');
        $this->assertSame('CANCELLED', $verified->refresh()->status);

        $this->post('/cost-register/' . $queued->id . '/cancel')->assertSessionHas('error');
        $this->assertSame('QUEUED', $queued->refresh()->status);
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

        $this->actingAs($user)->postJson('/cost-register/' . $cr->id . '/log-note', ['notes' => 'Cek dulu'])
            ->assertOk()->assertJsonPath('log.notes', 'Cek dulu');

        $json = $this->getJson('/cost-register/' . $cr->id . '/detail-json')->assertOk()->json();
        $this->assertContains('note', array_column($json['logs'], 'kind'));
        $this->assertTrue($json['can_approve']);

        $closed = $this->register('CLOSED');
        $this->postJson('/cost-register/' . $closed->id . '/log-note', ['notes' => 'Terlambat'])->assertStatus(422);
    }

    public function test_filter_tanggal_dan_export(): void
    {
        $lama = $this->register('DRAFT');
        $lama->forceFill(['created_at' => now()->subMonths(3)])->save();
        $baru = $this->register('DRAFT');

        $finance = $this->userWithRole('finance');
        $html = $this->actingAs($finance)->get('/cost-register?date_from=' . now()->startOfMonth()->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'))
            ->assertOk()->getContent();
        $this->assertStringContainsString('data-id="' . $baru->id . '"', $html);
        $this->assertStringNotContainsString('data-id="' . $lama->id . '"', $html);

        $this->get('/cost-register/export?date_from=' . now()->subYear()->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_role_lain_tidak_boleh_mengakses_modul(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get('/cost-register')->assertForbidden();
    }
}
