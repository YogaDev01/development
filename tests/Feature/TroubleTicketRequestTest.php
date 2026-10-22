<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetRequest;
use App\Models\Cluster;
use App\Models\MasterPic;
use App\Models\ProjectDetail;
use App\Models\Site;
use App\Models\Stage;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketIssue;
use App\Models\TroubleTicketMessage;
use App\Models\TroubleTicketProblem;
use App\Models\TroubleTicketRequest;
use App\Models\TroubleTicketRequestItem;
use App\Models\User;
use App\Services\TodoService;
use App\Services\TroubleTicketWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Trouble Ticket — Pengajuan Biaya (pra-Cost Request): alur teknisi → supervisor
 * team yang sama → Cost Request DRAFT, revisi tanpa menghapus baris lama,
 * otorisasi, banyak pengajuan per tiket, dan pembayaran per CR.
 */
class TroubleTicketRequestTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private User $tech;
    private User $other;      // teknisi lain (bukan pemilik tiket)
    private User $leader;     // supervisor team yang sama dengan $tech
    private User $leader2;    // supervisor team LAIN
    private User $manager;
    private Site $site;
    private TroubleTicket $ticket;
    private BudgetPlan $plan;
    private BudgetPlanDetail $detail;
    private Cluster $cluster;
    private $ca;
    private $transport;
    private Beneficiary $benInternal;
    private Beneficiary $benLuar;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');

        $problem = TroubleTicketProblem::create(['name' => 'Link Down']);
        $issue = TroubleTicketIssue::create(['name' => 'MODEM RUSAK']);

        foreach (['staff', 'supervisor', 'manager'] as $r) {
            $this->giveMenuAccess($this->role($r), 'trouble-ticket');
        }

        $this->manager = $this->userWithRole('manager');
        $this->tech = $this->userWithRole('staff');
        $this->other = $this->userWithRole('staff');
        $this->leader = $this->userWithRole('supervisor');
        $this->leader2 = $this->userWithRole('supervisor');

        $customer = $this->makeCustomer();
        $this->site = Site::create([
            'tid' => 'TID-RQ', 'sitename' => 'Site Pengajuan', 'type' => 'TOWER', 'source_power' => 'PLN',
            'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE', 'customer_id' => $customer->id,
        ]);
        $pic = MasterPic::create(['type' => 'internal', 'user_id' => $this->tech->id, 'pic' => $this->tech->name, 'status' => 'ACTIVE']);
        SitePic::create(['site_id' => $this->site->id, 'pic_id' => $pic->id]);

        $team = Team::create(['code' => 'TEAM-RQ1', 'name' => 'Field', 'leader_id' => $this->leader->id, 'status' => 'ACTIVE']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $this->tech->id, 'status' => 'ACTIVE']);
        $team2 = Team::create(['code' => 'TEAM-RQ2', 'name' => 'Lain', 'leader_id' => $this->leader2->id, 'status' => 'ACTIVE']);
        TeamMember::create(['team_id' => $team2->id, 'user_id' => $this->other->id, 'status' => 'ACTIVE']);

        $this->actingAs($this->manager)->post('/trouble-ticket', [
            'site_id' => $this->site->id, 'assigned_to' => $this->tech->id,
            'problem_id' => $problem->id, 'issue_id' => $issue->id, 'description' => 'Modem mati',
        ])->assertRedirect();
        $this->ticket = TroubleTicket::latest('id')->firstOrFail();

        $project = $this->makeProject(['customer_id' => $customer->id]);
        // Project (cluster) yang bisa dipilih = cluster pada milestone project, seperti dropdown Project di form CR.
        $this->cluster = Cluster::create(['name' => 'Cluster RQ', 'code' => '0001', 'status' => 'ACTIVE']);
        ProjectDetail::create([
            'project_id' => $project->id, 'cluster_id' => $this->cluster->id,
            'stage_id' => Stage::create(['name' => 'BAST', 'code' => 'ST001', 'status' => 'ACTIVE'])->id,
            'weight' => 100, 'status' => 'ON_GOING',
        ]);
        $this->plan = $this->makeBudgetPlan($project, ['amount' => 10_000_000]);
        $akun = Account::create(['name' => 'Transport Field', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
        $this->detail = BudgetPlanDetail::create([
            'budget_plan_id' => $this->plan->id, 'account_id' => $akun->id, 'deskripsi' => 'Bensin',
            'amount' => 1_000_000, 'qty' => 1, 'total' => 1_000_000,
        ]);

        $this->ca = $this->makeRequestType('Cash Advance');
        $this->transport = $this->makeRequestType('Cost Payment');
        $this->benInternal = Beneficiary::create([
            'nickname' => 'Internal Tech', 'complete_name' => 'Internal Tech', 'category' => 'INTERNAL',
            'pic_id' => $this->ticket->pic_id, 'status' => 'ACTIVE',
        ]);
        $this->benLuar = $this->makeBeneficiary();
    }

    /* ------------------------------------------------------------ helpers */

    private function payload(array $over = [], array $itemOver = []): array
    {
        return array_merge([
            'request_type_id' => $this->ca->id,
            'budget_plan_id'  => $this->plan->id,
            'cluster_id'      => $this->cluster->id,
            'description'     => 'Bensin visit',
            'items'           => [array_merge(['budget_detail_id' => $this->detail->id, 'description' => 'Bensin PP', 'amount' => '150.000', 'qty' => 2], $itemOver)],
        ], $over);
    }

    private function ajukan(?User $oleh = null, array $over = [], bool $submit = true, ?TroubleTicket $ticket = null): TroubleTicketRequest
    {
        $ticket ??= $this->ticket;
        $this->actingAs($oleh ?? $this->tech)
            ->post(route('trouble-ticket.requests.save', $ticket), array_merge($this->payload(), $over, ['submit' => $submit ? 1 : 0]))
            ->assertSessionHasNoErrors();

        return $ticket->requests()->latest('id')->firstOrFail();
    }

    private function approve(TroubleTicketRequest $r, ?User $oleh = null)
    {
        return $this->actingAs($oleh ?? $this->leader)->post(route('trouble-ticket.requests.approve', [$this->ticket, $r->id]));
    }

    /* ------------------------------------------------------------ alur penuh */

    public function test_teknisi_mengajukan_supervisor_approve_membentuk_cost_request_draft(): void
    {
        $r = $this->ajukan();

        $this->assertSame('SUBMITTED', $r->status);
        $this->assertSame(300_000, (int) $r->total);
        $this->assertDatabaseHas('trouble_ticket_messages', ['trouble_ticket_id' => $this->ticket->id, 'event' => 'request_submitted']);
        $this->assertSame(0, BudgetRequest::count(), 'belum ada CR sebelum di-approve');

        $this->approve($r)->assertSessionHas('success');

        $r->refresh();
        $cr = BudgetRequest::findOrFail($r->budget_request_id);
        $this->assertSame('APPROVED', $r->status);
        $this->assertSame('DRAFT', $cr->status);
        $this->assertSame($this->ca->id, $cr->type_id);
        $this->assertSame($this->leader->id, $cr->applicant_id, 'applicant = supervisor yang meng-approve');
        $this->assertSame($this->benInternal->id, $cr->beneficiary_id, 'CA: beneficiary Internal PIC teknisi');
        $this->assertSame($this->cluster->id, $cr->cluster_id);
        $this->assertSame($this->plan->id, $cr->budget_plan_id);
        $this->assertStringContainsString($this->ticket->ticket_number, $cr->description);
        $this->assertStringContainsString($this->ticket->ticket_number, (string) $cr->notes);
        $this->assertSame(300_000, (int) $cr->amount);
        $this->assertSame(1, $cr->details()->count());
        $this->assertSame(150_000, (int) $cr->details()->first()->amount);
        $this->assertDatabaseHas('trouble_ticket_messages', ['trouble_ticket_id' => $this->ticket->id, 'event' => 'request_approved']);
    }

    public function test_nob_cr_hasil_approve_sama_dengan_rumus_bersama_budget_plan(): void
    {
        $this->plan->update(['amount' => 100_000]); // total 300.000 melampaui pagu 100.000
        $r = $this->ajukan();

        $this->approve($r);

        $cr = BudgetRequest::findOrFail($r->fresh()->budget_request_id);
        $plan = $this->plan->fresh();
        $this->assertTrue((bool) $cr->is_nob);
        $this->assertSame($plan->nobOverage(300_000, $cr->id), (int) $cr->nob_amount);
        $this->assertSame(200_000, (int) $cr->nob_amount);
    }

    public function test_jenis_non_ca_wajib_lampiran_per_baris_dan_lampiran_ikut_ke_cr(): void
    {
        $dasar = $this->payload(['request_type_id' => $this->transport->id, 'beneficiary_id' => $this->benLuar->id]);

        // Tanpa lampiran: ditolak.
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.save', $this->ticket), array_merge($dasar, ['submit' => 1]))
            ->assertSessionHasErrors('attachments');
        $this->assertSame(0, $this->ticket->requests()->count());

        // Dengan lampiran per baris: diterima, dan dibawa ke "Lampiran Item" CR.
        $dasar['items'][0]['files'] = [UploadedFile::fake()->image('nota.jpg')];
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.save', $this->ticket), array_merge($dasar, ['submit' => 1]))
            ->assertSessionHasNoErrors();
        $r = $this->ticket->requests()->firstOrFail();

        $this->approve($r);

        $cr = BudgetRequest::findOrFail($r->fresh()->budget_request_id);
        $this->assertSame($this->benLuar->id, $cr->beneficiary_id);
        $this->assertSame(1, $cr->details()->first()->attachments()->count());
    }

    public function test_cash_advance_tanpa_lampiran_diterima(): void
    {
        $r = $this->ajukan();

        $this->assertSame('SUBMITTED', $r->status);
        $this->assertSame(0, $r->attachments()->count());
    }

    /* ------------------------------------------------------------ revisi */

    public function test_reject_lalu_revisi_menyimpan_revisi_baru_dan_baris_lama_utuh(): void
    {
        $r = $this->ajukan();

        $this->actingAs($this->leader)->post(route('trouble-ticket.requests.reject', [$this->ticket, $r->id]), ['notes' => 'Terlalu mahal'])
            ->assertSessionHas('success');
        $this->assertSame('REJECTED', $r->fresh()->status);

        $itemLama = $r->items()->where('revision', 1)->firstOrFail();

        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.save', $this->ticket), array_merge(
            $this->payload([], ['amount' => '100.000', 'qty' => 1, 'source_item_id' => $itemLama->id]),
            ['request_id' => $r->id, 'submit' => 1],
        ))->assertSessionHasNoErrors();

        $r->refresh();
        $this->assertSame(2, $r->revision);
        $this->assertSame('SUBMITTED', $r->status);
        $this->assertSame(100_000, (int) $r->total);
        $this->assertSame(2, TroubleTicketRequestItem::where('trouble_ticket_request_id', $r->id)->count(), 'baris lama tidak dihapus');
        $this->assertSame(150_000, (int) $itemLama->fresh()->amount, 'baris lama tidak diubah');
        $this->assertSame(1, $r->currentItems()->count());

        $this->approve($r);
        $this->assertSame(100_000, (int) BudgetRequest::findOrFail($r->fresh()->budget_request_id)->amount);
    }

    public function test_supervisor_mengubah_pengajuan_tercatat_sebagai_event_dengan_ringkasan(): void
    {
        $r = $this->ajukan();
        $item = $r->currentItems()->firstOrFail();

        $this->actingAs($this->leader)->post(route('trouble-ticket.requests.edit', [$this->ticket, $r->id]), $this->payload(
            [], ['amount' => '200.000', 'qty' => 3, 'source_item_id' => $item->id],
        ))->assertSessionHasNoErrors();

        $r->refresh();
        $this->assertSame(2, $r->revision);
        $this->assertSame('SUBMITTED', $r->status, 'tetap menunggu review');
        $this->assertSame(600_000, (int) $r->total);
        $this->assertSame($this->leader->id, $r->currentItems()->first()->created_by);

        $ev = TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'request_edited')->firstOrFail();
        $this->assertSame($this->leader->id, (int) $ev->user_id);
        $this->assertSame(300_000, $ev->metadata['perubahan']['total_lama']);
        $this->assertSame(600_000, $ev->metadata['perubahan']['total_baru']);
        $this->assertCount(1, $ev->metadata['perubahan']['diubah']);
    }

    /* ------------------------------------------------------------ otorisasi */

    public function test_hanya_teknisi_tiket_yang_boleh_mengajukan_dan_tiket_closed_ditolak(): void
    {
        // Teknisi lain / supervisor / manager tidak boleh mengajukan atas nama teknisi tiket ini.
        foreach ([$this->other, $this->leader, $this->manager] as $u) {
            $this->actingAs($u)->post(route('trouble-ticket.requests.save', $this->ticket), $this->payload())->assertStatus(403);
        }

        // Tiket Closed tidak menerima pengajuan baru.
        $this->ticket->update(['status' => 'CLOSED']);
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.save', $this->ticket), $this->payload())->assertStatus(403);
        $this->assertSame(0, TroubleTicketRequest::count());
    }

    public function test_reviewer_hanya_supervisor_team_yang_sama_atau_superadmin_bukan_pembuat(): void
    {
        $r = $this->ajukan();

        $this->approve($r, $this->leader2)->assertStatus(403);   // supervisor team lain
        $this->approve($r, $this->other)->assertStatus(403);     // bukan supervisor
        $this->approve($r, $this->tech)->assertStatus(403);      // pembuatnya sendiri
        $this->assertSame('SUBMITTED', $r->fresh()->status);

        $this->approve($r, $this->userWithRole('superadmin'))->assertSessionHas('success'); // superadmin boleh
        $this->assertSame('APPROVED', $r->fresh()->status);
    }

    public function test_pembuat_tidak_boleh_approve_pengajuannya_sendiri_walau_superadmin(): void
    {
        $admin = $this->userWithRole('superadmin');
        $r = $this->ajukan($admin);

        $this->approve($r, $admin)->assertStatus(403);
        $this->assertSame('SUBMITTED', $r->fresh()->status);
    }

    public function test_unduh_lampiran_pengajuan_mengikuti_keterlihatan_tiket(): void
    {
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.save', $this->ticket), array_merge(
            $this->payload(['request_type_id' => $this->transport->id, 'beneficiary_id' => $this->benLuar->id], ['files' => [UploadedFile::fake()->image('nota.jpg')]]),
            ['submit' => 1],
        ))->assertSessionHasNoErrors();
        $lampiran = $this->ticket->requests()->firstOrFail()->attachments()->firstOrFail();
        $url = route('trouble-ticket.requests.attachment', [$this->ticket, $lampiran->id]);

        $this->actingAs($this->leader)->get($url)->assertOk();
        $this->actingAs($this->leader2)->get($url)->assertStatus(403);
    }

    /* ------------------------------------------------------------ banyak pengajuan, race */

    public function test_satu_tiket_boleh_banyak_pengajuan_termasuk_beberapa_submitted(): void
    {
        $this->ajukan();
        $this->ajukan(null, ['description' => 'Konsumsi']);

        $this->assertSame(2, $this->ticket->requests()->where('status', 'SUBMITTED')->count());
    }

    public function test_approve_dua_kali_hanya_membentuk_satu_cost_request(): void
    {
        $r = $this->ajukan();

        $this->approve($r)->assertSessionHas('success');
        $this->approve($r)->assertSessionHasErrors('workflow'); // sudah APPROVED

        $this->assertSame(1, BudgetRequest::count());
    }

    public function test_satu_cost_request_tidak_bisa_terhubung_ke_dua_pengajuan(): void
    {
        $a = $this->ajukan();
        $b = $this->ajukan(null, ['description' => 'Lain']);
        $this->approve($a);
        $crId = $a->fresh()->budget_request_id;

        $this->expectException(QueryException::class);
        $b->forceFill(['budget_request_id' => $crId])->save(); // UNIQUE budget_request_id
    }

    /* ------------------------------------------------------------ Create Plan & pembayaran */

    public function test_create_plan_hanya_jadwal_tanpa_biaya_dan_tidak_menyentuh_pengajuan(): void
    {
        $r = $this->ajukan();
        $this->actingAs($this->tech)->get("/trouble-ticket/{$this->ticket->id}")->assertOk(); // Open → Remote

        $this->actingAs($this->tech)->post(route('trouble-ticket.plan.create', $this->ticket), [
            'visit_site' => now()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors();

        $t = $this->ticket->fresh();
        $this->assertSame('PLAN', $t->status);
        $this->assertSame(0, $t->planCosts()->count());
        $this->assertNull($t->plan_budget_status);
        $this->assertSame('SUBMITTED', $r->fresh()->status);
        $this->assertSame(1, $t->requests()->count());
    }

    public function test_pembayaran_dicatat_per_cost_request_dan_tiket_lama_tetap_tercatat(): void
    {
        $a = $this->ajukan();
        $b = $this->ajukan(null, ['description' => 'Kedua']);
        $this->approve($a);
        $this->approve($b);
        $crA = BudgetRequest::findOrFail($a->fresh()->budget_request_id);
        $crB = BudgetRequest::findOrFail($b->fresh()->budget_request_id);
        $crA->update(['status' => 'PAID', 'paid_at' => now()]);
        $crB->update(['status' => 'PAID', 'paid_at' => now()]);

        $wf = app(TroubleTicketWorkflow::class);
        $wf->recordCostRequestPaid($crA, $this->manager);
        $wf->recordCostRequestPaid($crB, $this->manager);
        $wf->recordCostRequestPaid($crA, $this->manager); // diulang: tidak menggandakan

        $this->assertSame(2, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'cost_request_paid')->count());

        // Tiket lama: CR terhubung lewat trouble_tickets.budget_request_id.
        $lama = $this->makeCostRequest(['status' => 'PAID', 'paid_at' => now()]);
        $this->ticket->update(['budget_request_id' => $lama->id]);
        $wf->recordCostRequestPaid($lama, $this->manager);

        $this->assertSame(3, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'cost_request_paid')->count());
    }

    /* ------------------------------------------------------------ Todo List */

    public function test_antrean_todo_list_review_pengajuan_mengikuti_team_dan_bukan_pembuat(): void
    {
        $this->ajukan();

        $this->assertSame(1, TodoService::pendingCounts($this->leader)['ticketRequestReview']);
        $this->assertSame(0, TodoService::pendingCounts($this->leader2)['ticketRequestReview']);
        $this->assertSame(1, TodoService::pendingCounts($this->userWithRole('superadmin'))['ticketRequestReview']);
        // Pembuat (bukan role penangan) tidak punya antrean ini.
        $this->assertArrayNotHasKey('ticketRequestReview', TodoService::pendingCounts($this->tech));
    }
}
