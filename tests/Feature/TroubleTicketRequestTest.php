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
        // Cache per-request ini bertahan antar-test, padahal id jenis CR berubah.
        (new \ReflectionProperty(BudgetRequest::class, 'cashAdvanceTypeIdsCache'))->setValue(null, null);

        $problem =TroubleTicketProblem::create(['name' => 'Link Down']);
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
        // Token wajib > 0 sejak 2026-11-07 (syarat beneficiary Cash Advance, temuan F-5).
        $pic = MasterPic::create(['type' => 'internal', 'user_id' => $this->tech->id, 'pic' => $this->tech->name, 'token' => '5', 'status' => 'ACTIVE']);
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
        $this->cluster = Cluster::create(['name' => 'Cluster RQ', 'code' => '9001', 'status' => 'ACTIVE']);
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

        return $ticket->requests()->reorder('id', 'desc')->firstOrFail();
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

    /* ------------------------------------------------------------ integrasi Finance */

    private function teamTeknisi(): Team
    {
        return Team::where('code', 'TEAM-RQ1')->firstOrFail();
    }

    /** $atasan memimpin team induk dari team teknisi. */
    private function jadikanAtasanAtas(User $atasan): void
    {
        $induk = Team::create(['code' => 'TEAM-INDUK', 'name' => 'Induk', 'leader_id' => $atasan->id, 'status' => 'ACTIVE']);
        $this->teamTeknisi()->update(['parent_team_id' => $induk->id]);
    }

    /** Pengajuan disetujui, CR Cash Advance sudah dibayar, satu baris settlement berbukti. */
    private function crDibayar(bool $berbukti = true): array
    {
        $r = $this->ajukan();
        $this->approve($r);
        $r->refresh();
        $cr = BudgetRequest::findOrFail($r->budget_request_id);
        $cr->update(['status' => 'PAID', 'paid_at' => now()]);

        $d = $cr->details()->firstOrFail();
        $baris = $cr->realizations()->create([
            'request_detail_id' => $d->id, 'description' => $d->description, 'account_id' => $d->account_id,
            'amount' => (int) $d->total, 'qty' => 1, 'total' => (int) $d->total,
        ]);

        if ($berbukti) {
            $baris->attachments()->create(['original_name' => 'nota.jpg', 'stored_name' => 'nota.jpg', 'path' => 'cost_request_realization_details/nota.jpg']);
        }

        return [$r, $cr, $baris];
    }

    public function test_supervisor_team_lain_tidak_bisa_approve_penutupan_lewat_url(): void
    {
        $this->ticket->update(['status' => 'PROGRESS_DONE']);

        $this->actingAs($this->leader2)->post(route('trouble-ticket.close.approve', $this->ticket))->assertStatus(403);
        $this->assertSame('PROGRESS_DONE', $this->ticket->fresh()->status);

        $this->actingAs($this->leader)->post(route('trouble-ticket.close.approve', $this->ticket))->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', $this->ticket->fresh()->status);
    }

    public function test_pembuat_tiket_boleh_chat_tetapi_tidak_form_close(): void
    {
        $wf = app(TroubleTicketWorkflow::class);
        $this->ticket->update(['status' => 'REMOTE']);

        $this->assertFalse($wf->canClose($this->manager, $this->ticket->fresh()));
        $this->assertTrue($wf->canClose($this->tech, $this->ticket->fresh()));
        $this->assertTrue($wf->canClose($this->leader, $this->ticket->fresh()));

        // OD-7: form Create tanpa lampiran, jadi bukti dari pembuat masuk lewat chat.
        // Pembuat biasa (bukan pengawas) kembali melihat tiket buatannya; user lain tidak.
        $this->assertTrue($wf->canView($this->manager, $this->ticket->fresh()));
        $this->assertFalse($wf->canView($this->other, $this->ticket->fresh()));
        $this->assertTrue($wf->canMessage($this->manager, $this->ticket->fresh()));
        $this->actingAs($this->manager)->postJson("/trouble-ticket/{$this->ticket->id}/messages", [
            'body' => 'Foto alarm', 'attachments' => [UploadedFile::fake()->image('alarm.jpg')],
        ])->assertOk();
        $this->assertSame(1, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)
            ->where('user_id', $this->manager->id)->where('type', TroubleTicketMessage::TYPE_MESSAGE)->count());
    }

    public function test_pembaca_saja_tidak_bisa_kirim_chat_lewat_url(): void
    {
        $r = $this->ajukan();
        $this->approve($r);
        $this->ticket->update(['status' => 'REMOTE']);
        $finance = $this->userWithRole('finance');
        $this->giveMenuAccess($this->role('finance'), 'trouble-ticket');

        $this->assertTrue(app(TroubleTicketWorkflow::class)->canView($finance, $this->ticket->fresh()));
        $this->actingAs($finance)->postJson("/trouble-ticket/{$this->ticket->id}/messages", ['body' => 'coba'])->assertForbidden();
        $this->assertSame(0, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('user_id', $finance->id)->count());
    }

    public function test_sesama_anggota_team_tidak_bisa_me_review(): void
    {
        $rekan = $this->userWithRole('supervisor');
        TeamMember::create(['team_id' => $this->teamTeknisi()->id, 'user_id' => $rekan->id, 'status' => 'ACTIVE']);
        $r = $this->ajukan();

        $this->approve($r, $rekan)->assertStatus(403);
        $this->assertSame('SUBMITTED', $r->fresh()->status);
    }

    public function test_leader_atas_boleh_mengambil_alih_dan_tercatat(): void
    {
        $this->jadikanAtasanAtas($this->leader2);
        $r = $this->ajukan();

        $this->approve($r, $this->leader2)->assertSessionHas('success');

        $this->assertSame('APPROVED', $r->fresh()->status);
        $event = TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'request_approved')->firstOrFail();
        $this->assertTrue($event->metadata['takeover']);
        $this->assertSame('DRAFT', BudgetRequest::findOrFail($r->fresh()->budget_request_id)->status);
    }

    public function test_manager_direct_approval_membuat_cr_submitted_lewat_aturan_submit(): void
    {
        $this->jadikanAtasanAtas($this->manager);
        $r = $this->ajukan();

        $this->actingAs($this->manager)->post(route('trouble-ticket.requests.approve', [$this->ticket, $r->id]), ['direct' => 1])
            ->assertSessionHas('success');

        $cr = BudgetRequest::findOrFail($r->fresh()->budget_request_id);
        $this->assertSame('SUBMITTED', $cr->status);
        $this->assertNotNull($cr->submitted_at);
        $this->assertSame((int) $this->manager->id, (int) $cr->applicant_id);
        $this->assertTrue($cr->statusLogs()->where('status_to', 'SUBMITTED')->exists());
    }

    public function test_direct_approval_budget_plan_overhead_wajib_access_group_cost_center(): void
    {
        $this->jadikanAtasanAtas($this->manager);
        $r = $this->ajukan();
        $cc = \App\Models\CostCenter::create(['code' => 'CC-TT', 'name' => 'Overhead TT', 'type' => 'OVERHEAD', 'status' => 'ACTIVE']);
        \Illuminate\Support\Facades\DB::table('budget_plans')->where('id', $this->plan->id)
            ->update(['project_id' => null, 'cost_center_id' => $cc->id]);

        // Manager tanpa Access Group cost center itu tidak akan melihat CR-nya lewat scopeByRole.
        $this->actingAs($this->manager)->post(route('trouble-ticket.requests.approve', [$this->ticket, $r->id]), ['direct' => 1])
            ->assertSessionHasErrors('workflow');

        $this->assertNull($r->fresh()->budget_request_id);
        $this->assertSame(TroubleTicketRequest::SUBMITTED, $r->fresh()->status);
    }

    public function test_direct_approval_melebihi_limit_ca_batal_seluruhnya(): void
    {
        $this->jadikanAtasanAtas($this->manager);
        MasterPic::whereKey($this->ticket->pic_id)->update(['nominal' => 1]);
        $r = $this->ajukan();

        $this->actingAs($this->manager)->post(route('trouble-ticket.requests.approve', [$this->ticket, $r->id]), ['direct' => 1])
            ->assertSessionHasErrors('workflow');

        $this->assertSame('SUBMITTED', $r->fresh()->status);
        $this->assertNull($r->fresh()->budget_request_id);
        $this->assertSame(0, BudgetRequest::count());
    }

    public function test_supervisor_tidak_bisa_direct_approval(): void
    {
        $r = $this->ajukan();

        $this->actingAs($this->leader)->post(route('trouble-ticket.requests.approve', [$this->ticket, $r->id]), ['direct' => 1])
            ->assertStatus(403);
        $this->assertSame('SUBMITTED', $r->fresh()->status);
    }

    public function test_cr_draft_tiket_masuk_todo_manager(): void
    {
        $r = $this->ajukan();
        $this->approve($r);

        $this->assertArrayHasKey('ticketCrDraft', TodoService::pendingCounts($this->manager));
        $this->assertArrayNotHasKey('ticketCrDraft', TodoService::pendingCounts($this->leader));
    }

    public function test_cr_batal_membuat_pengajuan_baru_tanpa_mengubah_yang_lama(): void
    {
        $r = $this->ajukan();
        $this->approve($r);
        BudgetRequest::whereKey($r->fresh()->budget_request_id)->update(['status' => 'CANCELLED']);

        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.resubmit', [$this->ticket, $r->id]))->assertRedirect();

        $baru = $this->ticket->requests()->reorder('id', 'desc')->firstOrFail();
        $this->assertNotSame($r->id, $baru->id);
        $this->assertSame('DRAFT', $baru->status);
        $this->assertNull($baru->budget_request_id);
        $this->assertSame((int) $r->total, (int) $baru->total);
        $this->assertSame('APPROVED', $r->fresh()->status);
    }

    public function test_status_cr_tercermin_sekali_di_timeline_tiket(): void
    {
        $r = $this->ajukan();
        $this->approve($r);
        $cr = BudgetRequest::findOrFail($r->fresh()->budget_request_id);

        $this->actingAs($this->leader);
        $cr->addLog('DRAFT', 'SUBMITTED', 'submitted');

        $this->assertSame(1, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'cost_request_status')->count());

        $log = $cr->statusLogs()->latest('id')->firstOrFail();
        app(TroubleTicketWorkflow::class)->mirrorCostRequestLog($cr, $log); // diulang
        $this->assertSame(1, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'cost_request_status')->count());
    }

    public function test_settlement_dikirim_teknisi_lalu_diteruskan_atasan(): void
    {
        [$r, $cr] = $this->crDibayar();

        $this->actingAs($this->other)->post(route('trouble-ticket.settlement.submit', [$this->ticket, $cr->id]))->assertStatus(403);
        $this->actingAs($this->tech)->post(route('trouble-ticket.settlement.submit', [$this->ticket, $cr->id]))->assertSessionHasNoErrors();

        $this->assertSame('PENDING', $r->fresh()->settlement_review_status);
        $this->assertSame('PAID', $cr->fresh()->status);

        // Selama direview, baris tidak bisa disunting.
        $this->actingAs($this->tech)->post('/budget-request/' . $cr->id . '/save-realization-draft', ['from_ticket' => 1, 'realizations' => []])
            ->assertSessionHas('error');

        $this->actingAs($this->leader2)->post(route('trouble-ticket.settlement.review', [$this->ticket, $cr->id]), ['decision' => 'approve'])->assertStatus(403);
        $this->actingAs($this->leader)->post(route('trouble-ticket.settlement.review', [$this->ticket, $cr->id]), ['decision' => 'approve'])->assertSessionHasNoErrors();

        $this->assertSame('REALIZED', $cr->fresh()->status);
        $this->assertSame('APPROVED', $r->fresh()->settlement_review_status);

        // Kedua kali tidak memindahkan status lagi.
        $this->actingAs($this->leader)->post(route('trouble-ticket.settlement.review', [$this->ticket, $cr->id]), ['decision' => 'approve'])
            ->assertSessionHasErrors('workflow');
    }

    public function test_settlement_tanpa_bukti_ditolak_dan_pengembalian_wajib_alasan(): void
    {
        [$r, $cr, $baris] = $this->crDibayar(false);

        $this->actingAs($this->tech)->post(route('trouble-ticket.settlement.submit', [$this->ticket, $cr->id]))->assertSessionHasErrors('workflow');
        $this->assertNull($r->fresh()->settlement_review_status);

        $baris->attachments()->create(['original_name' => 'nota.jpg', 'stored_name' => 'nota.jpg', 'path' => 'cost_request_realization_details/nota.jpg']);
        $this->actingAs($this->tech)->post(route('trouble-ticket.settlement.submit', [$this->ticket, $cr->id]))->assertSessionHasNoErrors();

        $this->actingAs($this->leader)->post(route('trouble-ticket.settlement.review', [$this->ticket, $cr->id]), ['decision' => 'return'])
            ->assertSessionHasErrors('notes');
        $this->actingAs($this->leader)->post(route('trouble-ticket.settlement.review', [$this->ticket, $cr->id]), ['decision' => 'return', 'notes' => 'Nota buram'])
            ->assertSessionHasNoErrors();

        $this->assertSame('REJECTED', $r->fresh()->settlement_review_status);
        $this->assertSame('PAID', $cr->fresh()->status);
    }

    public function test_settlement_melebihi_pengajuan_wajib_alasan(): void
    {
        [, $cr, $baris] = $this->crDibayar();
        $baris->update(['amount' => (int) $baris->total + 1000, 'total' => (int) $baris->total + 1000]);

        $this->actingAs($this->tech)->post(route('trouble-ticket.settlement.submit', [$this->ticket, $cr->id]))->assertSessionHasErrors('workflow');

        $baris->update(['notes' => 'Tarif naik']);
        $this->actingAs($this->tech)->post(route('trouble-ticket.settlement.submit', [$this->ticket, $cr->id]))->assertSessionHasNoErrors();
    }

    public function test_cr_tiket_baru_tidak_bisa_melewati_review_dari_modul_cr(): void
    {
        [, $cr] = $this->crDibayar();

        $this->actingAs($this->userWithRole('superadmin'))->post('/budget-request/' . $cr->id . '/submit-realization', [])->assertSessionHas('error');
        $this->assertSame('PAID', $cr->fresh()->status);
    }

    public function test_pemohon_tetap_pemilik_settlement_setelah_tiket_dialihkan(): void
    {
        [, $cr] = $this->crDibayar();
        $this->ticket->update(['assigned_to' => $this->other->id]);
        $wf = app(TroubleTicketWorkflow::class);

        $this->assertTrue($wf->isSettlementOwner($this->tech, $cr));
        $this->assertFalse($wf->isSettlementOwner($this->other, $cr));
        // Teknisi lama tetap melihat tiketnya selama memegang dana.
        $this->assertTrue($wf->canView($this->tech, $this->ticket->fresh()));
    }

    public function test_kwitansi_terbit_dari_tanda_tangan_penerima_dan_gugur_saat_nilai_berubah(): void
    {
        [, $cr, $baris] = $this->crDibayar();
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        $form = fn (int $nilai, array $tambahan = []) => [
            'from_ticket' => 1,
            'realizations' => [[
                'id' => $baris->id, 'request_detail_id' => $baris->request_detail_id,
                'budget_detail_id' => $cr->details()->value('budget_detail_id'),
                'amount' => number_format($nilai, 0, ',', '.'), 'notes' => 'Ojek ke site', 'transaction_date' => now()->toDateString(), 'vendor_name' => 'Gojek',
            ]],
        ] + $tambahan;

        $this->actingAs($this->tech)->post('/budget-request/' . $cr->id . '/save-realization-draft',
            $form((int) $baris->total, ['signatures' => [$png], 'recipient_names' => ['Pak Budi']]))->assertSessionHas('success');

        $k = \App\Models\SettlementReceipt::where('budget_request_id', $cr->id)->firstOrFail();
        $this->assertSame('Pak Budi', $k->recipient_name);
        $this->assertSame('VALID', $k->status);
        Storage::disk('secure')->assertExists($k->pdf_path);
        $this->assertTrue($baris->attachments()->where('original_name', 'kwitansi-' . $k->receipt_number . '.pdf')->exists());

        $this->actingAs($this->tech)->get(route('trouble-ticket.receipt', [$this->ticket, $k->id]))->assertOk();
        $this->actingAs($this->other)->get(route('trouble-ticket.receipt', [$this->ticket, $k->id]))->assertForbidden();

        // Nilai berubah: kwitansi lama gugur tetapi arsipnya tetap ada.
        $this->actingAs($this->tech)->post('/budget-request/' . $cr->id . '/save-realization-draft', $form((int) $baris->total - 1000))->assertSessionHas('success');
        $this->assertSame('SUPERSEDED', $k->fresh()->status);
        Storage::disk('secure')->assertExists($k->pdf_path);
    }

    public function test_tiket_closed_tetap_berpenanda_keuangan_dan_pengajuannya_bisa_diselesaikan(): void
    {
        $r = $this->ajukan(null, [], false);
        $this->ticket->update(['status' => 'CLOSED']);
        $t = $this->ticket->fresh();

        $this->assertSame(TroubleTicket::FINANCE_OPEN, $t->financialState());
        $this->assertTrue(TroubleTicket::financeOpen()->whereKey($t->id)->exists());

        // Pengajuan lama bisa diselesaikan; pengajuan baru tetap ditolak.
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.submit', [$t, $r->id]))->assertSessionHasNoErrors();
        $this->assertSame('SUBMITTED', $r->fresh()->status);
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.save', $t), $this->payload())->assertStatus(403);

        $this->approve($r);
        BudgetRequest::whereKey($r->fresh()->budget_request_id)->update(['status' => 'CLOSED']);

        $t = TroubleTicket::findOrFail($t->id);
        $this->assertSame(TroubleTicket::FINANCE_SETTLED, $t->financialState());
        $this->assertFalse(TroubleTicket::financeOpen()->whereKey($t->id)->exists());
    }

    public function test_tiket_tanpa_dana_tidak_berpenanda_keuangan(): void
    {
        $this->assertSame(TroubleTicket::FINANCE_NONE, $this->ticket->fresh()->financialState());
    }

    public function test_finance_hanya_baca_tiket_ber_cr(): void
    {
        $finance = $this->userWithRole('finance');
        $wf = app(TroubleTicketWorkflow::class);

        $this->assertFalse($wf->canView($finance, $this->ticket));

        $r = $this->ajukan();
        $this->approve($r);
        $this->ticket->update(['status' => 'REMOTE']);
        $t = $this->ticket->fresh();

        $this->assertTrue($wf->canView($finance, $t));
        $this->assertFalse($wf->canMessage($finance, $t));
        $this->assertFalse($wf->canClose($finance, $t));
    }

    /** Berkas import di sheet Data dengan baris yang diberikan. */
    private function berkasImport(array $baris): UploadedFile
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->fromArray(array_merge([['Customer', 'Site ID', 'RCA', 'Sub-RCA', 'No. Tiket Custom', 'Date Open', 'Keterangan', 'Teknisi']], $baris), null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'tt') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_excel_parsial_dan_token_sekali_pakai(): void
    {
        $customer = $this->site->customer->name;
        $file = fn () => $this->berkasImport([
            [$customer, $this->site->tid, null, null, 'TT-IMP-1', '01/01/2025', 'Modem mati', $this->tech->username],
            [$customer, 'TID-TIDAK-ADA', null, null, null, null, 'Salah site', $this->tech->username],
            [$customer, $this->site->tid, null, null, null, null, '', $this->other->username],
        ]);

        $pratinjau = $this->actingAs($this->manager)->post(route('trouble-ticket.import.preview'), ['file' => $file()])->assertOk()->json();
        $this->assertSame(['ok', 'gagal', 'gagal'], array_column($pratinjau['rows'], 'status'));
        $this->assertSame(1, TroubleTicket::count()); // pratinjau tidak menyimpan

        $hasil = $this->actingAs($this->manager)->post(route('trouble-ticket.import.commit'), ['file' => $file(), 'token' => $pratinjau['token']])->assertOk()->json();
        $this->assertCount(1, $hasil['created']);
        $this->assertCount(2, $hasil['failed']);
        $this->assertTrue(TroubleTicket::where('ticket_number', 'TT-IMP-1')->exists());

        $this->actingAs($this->manager)->post(route('trouble-ticket.import.commit'), ['file' => $file(), 'token' => $pratinjau['token']])->assertStatus(409);
        $this->assertSame(2, TroubleTicket::count());
    }

    public function test_kwitansi_gugur_bila_nilai_diubah_dari_form_cost_request(): void
    {
        [, $cr, $baris] = $this->crDibayar();
        $k = \App\Models\SettlementReceipt::create([
            'receipt_number' => 'KW-T-1', 'budget_request_id' => $cr->id, 'trouble_ticket_id' => $this->ticket->id,
            'realization_id' => $baris->id, 'recipient_name' => 'Pak Budi', 'amount' => (int) $baris->total,
            'signature_path' => 'settlement_receipts/a.png', 'pdf_path' => 'settlement_receipts/a.pdf', 'signed_at' => now(),
        ]);

        $request = \Illuminate\Http\Request::create('/', 'POST');
        $baris->update(['total' => (int) $baris->total + 1000]);
        app(\App\Services\SettlementReceiptService::class)->sync($request, $cr, [$baris->fresh()]);

        $this->assertSame('SUPERSEDED', $k->fresh()->status);
    }

    public function test_submit_dari_form_cr_ditolak_bila_kwitansi_gugur_meninggalkan_baris_tanpa_lampiran(): void
    {
        // Pengajuan lama (tanpa nomor): settlement masih lewat form Cost Request.
        [$r, $cr, $baris] = $this->crDibayar(false);
        $r->update(['request_number' => null]);

        $k = \App\Models\SettlementReceipt::create([
            'receipt_number' => 'KW-T-1', 'budget_request_id' => $cr->id, 'trouble_ticket_id' => $this->ticket->id,
            'realization_id' => $baris->id, 'recipient_name' => 'Pak Budi', 'amount' => (int) $baris->total,
            'signature_path' => 'settlement_receipts/a.png', 'pdf_path' => 'settlement_receipts/a.pdf', 'signed_at' => now(),
        ]);
        // Salinan PDF kwitansi = satu-satunya Lampiran Item baris ini.
        $baris->attachments()->create(['original_name' => 'kwitansi-KW-T-1.pdf', 'stored_name' => 'k.pdf', 'path' => 'cost_request_realization_details/k.pdf']);

        $this->actingAs($this->userWithRole('superadmin'))->post('/budget-request/' . $cr->id . '/submit-realization', [
            'realizations' => [[
                'id' => $baris->id, 'request_detail_id' => $baris->request_detail_id,
                'budget_detail_id' => $cr->details()->value('budget_detail_id'),
                'amount' => number_format((int) $baris->total - 1000, 0, ',', '.'), 'notes' => 'Ojek ke site',
                'transaction_date' => now()->toDateString(), 'vendor_name' => 'Gojek',
            ]],
        ])->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Kwitansi digital gugur'));

        // Seluruh transaksi batal: status, kwitansi, dan salinannya utuh.
        $this->assertSame('PAID', $cr->fresh()->status);
        $this->assertSame('VALID', $k->fresh()->status);
        $this->assertTrue($baris->attachments()->where('original_name', 'kwitansi-KW-T-1.pdf')->exists());
    }

    public function test_pengajuan_gugur_hanya_bisa_diajukan_ulang_sekali(): void
    {
        $r = $this->ajukan();
        $this->approve($r);
        BudgetRequest::whereKey($r->fresh()->budget_request_id)->update(['status' => 'CANCELLED']);

        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.resubmit', [$this->ticket, $r->id]))->assertRedirect();
        $this->actingAs($this->tech)->post(route('trouble-ticket.requests.resubmit', [$this->ticket, $r->id]))->assertSessionHasErrors('workflow');

        $this->assertSame(2, $this->ticket->requests()->count());
    }

    public function test_cr_non_ca_yang_dibayar_menutup_keuangan_tiket(): void
    {
        $r = $this->ajukan();
        $this->approve($r);
        $cr = BudgetRequest::findOrFail($r->fresh()->budget_request_id);

        $this->actingAs($this->userWithRole('treasury'));
        $cr->update(['status' => 'CLOSED']);
        $cr->addLog('QUEUED', 'CLOSED', 'paid');

        $this->assertTrue(TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'financial_closed')->exists());
    }

    public function test_pembuat_tiket_tidak_melihat_dokumen_uang(): void
    {
        [, $cr] = $this->crDibayar();
        $wf = app(TroubleTicketWorkflow::class);

        $this->assertFalse($wf->canViewFinance($this->manager, $this->ticket->fresh(), $cr));
        $this->assertTrue($wf->canViewFinance($this->leader, $this->ticket->fresh(), $cr));
    }

    public function test_dokumen_uang_hanya_untuk_pemohon_dan_atasan_peninjau_bukan_teknisi_baru(): void
    {
        [, $cr] = $this->crDibayar();
        $this->ticket->update(['assigned_to' => $this->other->id]);
        $t = $this->ticket->fresh();
        $wf = app(TroubleTicketWorkflow::class);

        // Teknisi yang sekarang ditugaskan tetapi bukan pemohon dana: tidak berhak.
        $this->assertFalse($wf->canViewFinance($this->other, $t, $cr));
        // Pemohon tetap memegang dana (OD-20).
        $this->assertTrue($wf->canViewFinance($this->tech, $t, $cr));
        // Atasan peninjau settlement = atasan teknisi yang sedang ditugaskan.
        $this->assertTrue($wf->canReviewRequest($this->leader2, $t, TroubleTicketRequest::where('budget_request_id', $cr->id)->firstOrFail()));
        $this->assertTrue($wf->canViewFinance($this->leader2, $t, $cr));
        $this->assertTrue($wf->canViewFinance($this->userWithRole('finance'), $t, $cr));
        $this->assertFalse($wf->canViewFinance($this->manager, $t, $cr));
    }

    public function test_pengajuan_belum_cr_batal_otomatis_saat_tiket_dialihkan(): void
    {
        [$cair] = $this->crDibayar();
        $draft = $this->ajukan(submit: false);
        $diajukan = $this->ajukan();
        $ditolak = $this->ajukan();
        $ditolak->update(['status' => TroubleTicketRequest::REJECTED]);

        \App\Models\SiteBackupTechnician::create(['site_id' => $this->site->id, 'user_id' => $this->other->id]);
        $this->ticket->update(['status' => 'PLAN', 'visit_decision' => 'VISIT', 'visit_site' => now()->addDay()->toDateString()]);

        app(TroubleTicketWorkflow::class)->reassign($this->ticket->fresh(), $this->leader, $this->other->id, 'Teknisi cuti');

        foreach ([$draft, $diajukan, $ditolak] as $r) {
            $r->refresh();
            $this->assertSame(TroubleTicketRequest::CANCELLED, $r->status);
            $this->assertSame($this->leader->id, (int) $r->cancelled_by);
            $this->assertStringContainsString('dialihkan', $r->cancel_reason);
        }
        // Dana yang sudah jadi CR tetap milik pemohon (OD-20).
        $this->assertSame(TroubleTicketRequest::APPROVED, $cair->fresh()->status);
        $this->assertSame(3, TroubleTicketMessage::where('trouble_ticket_id', $this->ticket->id)->where('event', 'request_cancelled')->count());
    }

    public function test_tanggal_teks_import_tidak_menghentikan_pratinjau(): void
    {
        $file = $this->berkasImport([
            [$this->site->customer->name, $this->site->tid, null, null, null, 'bukan tanggal', 'Modem mati', $this->tech->username],
        ]);

        $rows = $this->actingAs($this->manager)->post(route('trouble-ticket.import.preview'), ['file' => $file])->assertOk()->json('rows');
        $this->assertSame('gagal', $rows[0]['status']);
    }

    public function test_import_menolak_sheet_terlalu_panjang_tanpa_memuat_seluruhnya(): void
    {
        $file = $this->berkasImport([
            [$this->site->customer->name, $this->site->tid, null, null, null, null, 'Modem mati', $this->tech->username],
        ]);
        // Sisipkan sel jauh di bawah batas baca: berkas harus ditolak sebelum dimuat penuh.
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $book->getSheetByName('Data')->setCellValue('G2500', 'baris jauh');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file->getRealPath());

        $this->actingAs($this->manager)->post(route('trouble-ticket.import.preview'), ['file' => $file])
            ->assertStatus(422)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'terlalu panjang'))->etc());
    }

    public function test_teknisi_tidak_bisa_import_tiket(): void
    {
        $teknisi = $this->userWithRole('teknisi');
        $this->giveMenuAccess($this->role('teknisi'), 'trouble-ticket');

        $this->actingAs($teknisi)->get(route('trouble-ticket.import.template'))->assertForbidden();
    }
}
