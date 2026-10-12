<?php

namespace Tests\Feature;

use App\Models\MasterPic;
use App\Models\Site;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketAttachment;
use App\Models\TroubleTicketDevice;
use App\Models\TroubleTicketIssue;
use App\Models\TroubleTicketMessage;
use App\Models\TroubleTicketProblem;
use App\Models\TroubleTicketRca;
use App\Models\TroubleTicketSlaPolicy;
use App\Models\TroubleTicketSubRca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Trouble Ticket: pembuatan, keterlihatan (site → PIC → team), respons
 * pertama, keputusan Visit/Non-Visit, perjalanan, progress, pengajuan &
 * approval Closed oleh leader team, lampiran, dan konsistensi transaksi.
 */
class TroubleTicketTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private User $creator;

    private User $tech;

    private User $tech2;

    private User $leader;

    private User $outsider;

    private Site $site;

    private TroubleTicketProblem $problem;

    private TroubleTicketIssue $issue;

    private TroubleTicketRca $rca;

    private TroubleTicketSubRca $subRca;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');

        // Master tanpa UI — di produksi diisi TroubleTicketRcaSeeder.
        $this->problem = TroubleTicketProblem::create(['name' => 'Link Down']);
        $this->issue = TroubleTicketIssue::create(['name' => 'MODEM RUSAK']);
        $this->rca = TroubleTicketRca::create(['name' => 'HARDWARE']);
        $this->subRca = TroubleTicketSubRca::create(['rca_id' => $this->rca->id, 'name' => 'MODEM']);

        foreach (['staff', 'supervisor', 'manager'] as $r) {
            $this->giveMenuAccess($this->role($r), 'trouble-ticket');
        }

        $this->creator = $this->userWithRole('manager');
        $this->tech = $this->userWithRole('staff');
        $this->tech2 = $this->userWithRole('staff');
        $this->leader = $this->userWithRole('supervisor');
        $this->outsider = $this->userWithRole('staff');

        $this->site = $this->makeSite();
        $this->linkTech($this->site, $this->tech);
        $this->linkTech($this->site, $this->tech2);

        // Team: leader + kedua teknisi.
        $team = Team::create(['code' => 'TEAM-T1', 'name' => 'Field', 'leader_id' => $this->leader->id, 'status' => 'ACTIVE']);
        foreach ([$this->tech, $this->tech2] as $u) {
            TeamMember::create(['team_id' => $team->id, 'user_id' => $u->id, 'status' => 'ACTIVE']);
        }
    }

    /* ------------------------------------------------------------ helpers */

    private function makeSite(): Site
    {
        static $n = 0;
        $n++;

        return Site::create([
            'tid' => 'TID-'.$n, 'sitename' => 'Site Uji '.$n, 'type' => 'TOWER',
            'source_power' => 'PLN', 'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE',
            'customer_id' => $this->makeCustomer()->id,
        ]);
    }

    private function linkTech(Site $site, User $user): void
    {
        $pic = MasterPic::create(['type' => 'internal', 'user_id' => $user->id, 'pic' => $user->name, 'status' => 'ACTIVE']);
        SitePic::create(['site_id' => $site->id, 'pic_id' => $pic->id]);
    }

    private function createTicket(array $extra = []): TroubleTicket
    {
        $this->actingAs($this->creator)->post('/trouble-ticket', array_merge([
            'site_id' => $this->site->id, 'assigned_to' => $this->tech->id,
            'problem_id' => $this->problem->id, 'issue_id' => $this->issue->id,
            'description' => 'Modem mati sejak pagi',
        ], $extra))->assertRedirect();

        return TroubleTicket::latest('id')->firstOrFail();
    }

    /** Bawa tiket ke status tertentu lewat jalur resmi. */
    private function advance(TroubleTicket $t, string $decision = 'VISIT', string $until = 'IN_PROGRESS'): TroubleTicket
    {
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}")->assertOk();
        $this->post("/trouble-ticket/{$t->id}/plan", [
            'decision' => $decision, 'reason' => 'remote via NMS', 'visit_site' => now()->toDateString(),
        ]);
        if ($until === 'APPROVED') {
            return $t->fresh();
        }

        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/plan/approve");
        if ($until === 'ON_PLAN') {
            return $t->fresh();
        }

        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/start");
        if ($until === 'CLOSED') {
            $this->post("/trouble-ticket/{$t->id}/close", ['rca' => 'Perangkat', 'action' => 'Modem direstart, link normal']);
        }

        return $t->fresh();
    }

    /* ------------------------------------------------------------ tests */

    public function test_user_berwenang_membuat_tiket(): void
    {
        $t = $this->createTicket();

        $this->assertSame('OPEN', $t->status);
        $this->assertSame($this->site->customer_id, $t->customer_id);
        $this->assertMatchesRegularExpression('#^TT-0001/\d{2}/\d{2}$#', $t->ticket_number);
        $this->assertDatabaseHas('trouble_ticket_assignments', ['trouble_ticket_id' => $t->id, 'assigned_to' => $this->tech->id]);
        $this->assertDatabaseHas('trouble_ticket_messages', ['trouble_ticket_id' => $t->id, 'event' => 'created']);
    }

    public function test_teknisi_harus_pic_internal_site(): void
    {
        $this->actingAs($this->creator)->post('/trouble-ticket', [
            'site_id' => $this->site->id, 'assigned_to' => $this->outsider->id,
            'problem_id' => $this->problem->id, 'issue_id' => $this->issue->id, 'description' => 'Y',
        ])->assertSessionHasErrors('assigned_to');

        $this->assertSame(0, TroubleTicket::count());
    }

    public function test_leader_team_tidak_bisa_jadi_teknisi(): void
    {
        // Leader didaftarkan sebagai PIC internal site; ia tetap bukan teknisi.
        $this->linkTech($this->site, $this->leader);

        $this->actingAs($this->creator)->getJson("/trouble-ticket/site/{$this->site->id}/technicians")
            ->assertOk()
            ->assertJsonMissing(['name' => $this->leader->name])
            ->assertJsonFragment(['name' => $this->tech->name]);

        $this->post('/trouble-ticket', [
            'site_id' => $this->site->id, 'assigned_to' => $this->leader->id, 'description' => 'Y',
        ])->assertSessionHasErrors('assigned_to');
    }

    public function test_nomor_tt_unik_pada_pembuatan_berulang(): void
    {
        foreach (range(1, 5) as $_) {
            $this->createTicket();
        }

        $this->assertSame(5, TroubleTicket::distinct()->count('ticket_number'));
        $this->assertSame(5, (int) \DB::table('document_sequences')->where('key', 'trouble-ticket:'.now()->year)->value('counter'));
    }

    public function test_user_di_luar_rantai_team_tidak_bisa_melihat_tiket(): void
    {
        $t = $this->createTicket();

        $this->actingAs($this->outsider)->get("/trouble-ticket/{$t->id}")->assertForbidden();
        $this->actingAs($this->outsider)->get('/trouble-ticket')->assertOk()->assertDontSee($t->ticket_number);

        // Rekan satu team dengan PIC site & leader team melihatnya.
        $this->actingAs($this->tech2)->get("/trouble-ticket/{$t->id}")->assertOk();
        $this->actingAs($this->leader)->get('/trouble-ticket')->assertOk()->assertSee($t->ticket_number);
    }

    public function test_respons_pertama_dicatat_sekali_dan_tidak_direset(): void
    {
        $t = $this->createTicket();

        // Bukan teknisi: tidak mencatat respons.
        $this->actingAs($this->leader)->get("/trouble-ticket/{$t->id}")->assertOk();
        $this->assertNull($t->fresh()->acknowledged_at);

        Carbon::setTestNow(now()->addMinutes(7));
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}")->assertOk();
        $t->refresh();
        $pertama = $t->acknowledged_at;
        $this->assertNotNull($pertama);
        $this->assertSame('OPEN', $t->status);
        $this->assertEqualsWithDelta(420, $t->response_seconds, 2);

        Carbon::setTestNow(now()->addMinutes(30));
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}")->assertOk();
        $this->assertTrue($pertama->equalTo($t->fresh()->acknowledged_at));
        $this->assertSame(1, TroubleTicketMessage::where('trouble_ticket_id', $t->id)->where('event', 'responded')->count());
        Carbon::setTestNow();
    }

    public function test_plan_wajib_diajukan_sebelum_aksi_lain(): void
    {
        $t = $this->createTicket();

        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}")->assertSee('id="mdlTtDecide"', false);
        // Setelah "refresh" modal tetap muncul selama plan belum diajukan.
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}")->assertSee('id="mdlTtDecide"', false);

        // Aksi lain ditolak backend.
        $this->post("/trouble-ticket/{$t->id}/start")->assertSessionHasErrors('workflow');
        $this->post("/trouble-ticket/{$t->id}/close", ['rca' => 'X', 'action' => 'Y'])->assertSessionHasErrors('workflow');
        $this->postJson("/trouble-ticket/{$t->id}/messages", ['body' => 'halo'])->assertStatus(422);

        // Non-Visit tanpa alasan ditolak.
        $this->post("/trouble-ticket/{$t->id}/plan", ['decision' => 'NON_VISIT'])->assertSessionHasErrors('reason');

        $this->post("/trouble-ticket/{$t->id}/plan", ['decision' => 'VISIT', 'visit_site' => now()->toDateString()])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame('APPROVED', $t->status);
        $this->assertSame('PENDING', $t->approval);
        $this->assertSame('VISIT', $t->visit_decision);
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}")->assertDontSee('id="mdlTtDecide"', false);

        // Menunggu approval: teknisi tidak bisa mengajukan ulang atau memulai progress.
        $this->post("/trouble-ticket/{$t->id}/plan", ['decision' => 'NON_VISIT', 'reason' => 'x'])->assertSessionHasErrors('workflow');
        $this->post("/trouble-ticket/{$t->id}/start")->assertSessionHasErrors('workflow');
        $this->assertSame('VISIT', $t->fresh()->visit_decision);
    }

    public function test_approval_plan_membuka_pengerjaan(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'APPROVED');

        // Bukan leader team: tidak berwenang.
        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/plan/approve")->assertForbidden();
        $this->actingAs($this->tech2)->post("/trouble-ticket/{$t->id}/plan/approve")->assertForbidden();
        $this->actingAs($this->outsider)->post("/trouble-ticket/{$t->id}/plan/approve")->assertForbidden();

        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/plan/approve")->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('ON_PLAN', $t->status);
        $this->assertSame('APPROVED', $t->approval);
        $this->assertSame($this->leader->id, $t->plan_approved_by);
        $this->assertNotNull($t->plan_approved_at);

        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/start")->assertSessionHasNoErrors();
        $this->assertSame('IN_PROGRESS', $t->fresh()->status);
    }

    public function test_penolakan_plan_mengembalikan_tiket_ke_open(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'APPROVED');

        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/plan/reject", ['notes' => ''])->assertSessionHasErrors('notes');
        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/plan/reject", ['notes' => 'Kunjungan tidak perlu'])->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('OPEN', $t->status);
        $this->assertSame('REJECTED', $t->approval);
        $this->assertNull($t->visit_decision);

        $ev = TroubleTicketMessage::where('trouble_ticket_id', $t->id)->where('event', 'plan_rejected')->firstOrFail();
        $this->assertSame('VISIT', $ev->metadata['previous']['decision']);

        // Teknisi boleh mengajukan plan baru.
        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/plan", ['decision' => 'NON_VISIT', 'reason' => 'Cukup remote'])
            ->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $t->fresh()->status);
    }

    public function test_koreksi_plan_hanya_oleh_leader_dan_tercatat(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'APPROVED');

        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/plan/correct", ['decision' => 'NON_VISIT', 'reason' => 'x'])->assertForbidden();

        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/plan/correct", ['decision' => 'NON_VISIT', 'reason' => 'Cukup remote'])
            ->assertSessionHasNoErrors();
        $this->assertSame('NON_VISIT', $t->fresh()->visit_decision);
        $ev = TroubleTicketMessage::where('trouble_ticket_id', $t->id)->where('event', 'plan_corrected')->firstOrFail();
        $this->assertSame('VISIT', $ev->metadata['previous']['decision']);
    }

    public function test_teknisi_lain_tidak_bisa_menjalankan_aksi_operasional(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'ON_PLAN');

        // tech2 bisa melihat (satu team) tapi bukan teknisi yang ditugaskan.
        $this->actingAs($this->tech2)->post("/trouble-ticket/{$t->id}/start")->assertForbidden();
        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/start")->assertForbidden();
        $this->assertSame('ON_PLAN', $t->fresh()->status);
    }

    public function test_permintaan_ulang_tidak_mereset_timestamp(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'ON_PLAN');

        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/start")->assertSessionHasNoErrors();
        $mulai = $t->fresh()->progress_started_at;
        $this->assertNotNull($mulai);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->post("/trouble-ticket/{$t->id}/start")->assertSessionHasErrors('workflow');
        $this->assertTrue($mulai->equalTo($t->fresh()->progress_started_at));

        $this->post("/trouble-ticket/{$t->id}/close", ['rca' => 'Perangkat', 'action' => 'Modem direstart'])->assertSessionHasNoErrors();
        $tutup = $t->fresh()->closed_at;

        Carbon::setTestNow(now()->addMinutes(5));
        $this->post("/trouble-ticket/{$t->id}/close", ['rca' => 'Lain', 'action' => 'Lain'])->assertSessionHasErrors('workflow');
        $this->assertTrue($tutup->equalTo($t->fresh()->closed_at));
        Carbon::setTestNow();
    }

    public function test_non_visit_bisa_mulai_progress_setelah_plan_disetujui(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'IN_PROGRESS');

        $this->assertSame('IN_PROGRESS', $t->status);
        $this->assertNotNull($t->progress_started_at);
    }

    public function test_tutup_tiket_wajib_rca_action_dan_bukti_bila_problem_mewajibkan(): void
    {
        $hw = TroubleTicketProblem::create(['name' => 'Hardware', 'requires_evidence' => true]);
        $t = $this->advance($this->createTicket(['problem_id' => $hw->id]), 'NON_VISIT');

        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/close", ['rca' => '', 'action' => ''])
            ->assertSessionHasErrors(['rca', 'action']);
        $this->post("/trouble-ticket/{$t->id}/close", ['rca' => 'Perangkat', 'action' => 'Ganti adaptor modem'])
            ->assertSessionHasErrors('attachments');
        $this->assertSame('IN_PROGRESS', $t->fresh()->status);

        $this->post("/trouble-ticket/{$t->id}/close", [
            'rca' => 'Perangkat', 'sub_rca' => 'Adaptor rusak', 'action' => 'Ganti adaptor modem',
            'attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ])->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('CLOSED', $t->status);
        $this->assertSame(['Perangkat', 'Adaptor rusak', 'Ganti adaptor modem'], [$t->rca, $t->sub_rca, $t->action]);
        $this->assertNotNull($t->resolved_at);
        $this->assertNotNull($t->closed_at);
        $this->assertSame($this->tech->id, $t->closed_by);
        $this->assertSame(1, TroubleTicketAttachment::where('trouble_ticket_id', $t->id)->count());
    }

    public function test_field_referensi_telko_tersimpan_dan_tervalidasi(): void
    {
        // Problem / Initial Problem tak dikenal ditolak.
        $this->actingAs($this->creator)->post('/trouble-ticket', [
            'site_id' => $this->site->id, 'assigned_to' => $this->tech->id,
            'problem_id' => 999, 'issue_id' => 999, 'description' => 'Y',
        ])->assertSessionHasErrors(['issue_id', 'problem_id']);

        // Date Open di masa depan ditolak.
        $this->post('/trouble-ticket', [
            'site_id' => $this->site->id, 'assigned_to' => $this->tech->id,
            'problem_id' => $this->problem->id, 'issue_id' => $this->issue->id, 'description' => 'Y',
            'reported_at' => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('reported_at');

        $t = $this->createTicket([
            'device_id' => TroubleTicketDevice::create(['name' => 'MODEM'])->id,
            'rca_id' => $this->rca->id, 'sub_rca_id' => $this->subRca->id,
            'flag_alarm' => 'LINK DOWN',
            'reported_at' => now()->subHour()->format('Y-m-d H:i'),
        ]);
        $this->assertSame(
            ['MODEM RUSAK', 'MODEM', 'HARDWARE', 'MODEM', 'LINK DOWN'],
            [$t->issue_label, $t->device_label, $t->rca_label, $t->sub_rca_label, $t->flag_alarm],
        );
        $this->assertNull($t->severity, 'severity tidak lagi diisi dari form');
        $this->assertSame($this->tech->id, MasterPic::whereKey($t->pic_id)->value('user_id'));
        $this->assertNotNull($t->reported_at);

        // Sub-RCA harus milik RCA yang dipilih.
        $rcaLain = TroubleTicketRca::create(['name' => 'POWER']);
        $this->actingAs($this->creator)->post('/trouble-ticket', [
            'site_id' => $this->site->id, 'assigned_to' => $this->tech->id,
            'problem_id' => $this->problem->id, 'issue_id' => $this->issue->id, 'description' => 'Y',
            'rca_id' => $rcaLain->id, 'sub_rca_id' => $this->subRca->id,
        ])->assertSessionHasErrors('sub_rca_id');

        // Plan Visit wajib tanggal kunjungan; target online ikut tersimpan.
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}");
        $this->post("/trouble-ticket/{$t->id}/plan", ['decision' => 'VISIT'])->assertSessionHasErrors('visit_site');
        $this->post("/trouble-ticket/{$t->id}/plan", [
            'decision' => 'VISIT', 'visit_site' => now()->addDay()->toDateString(), 'target_online' => now()->addDays(2)->toDateString(),
        ])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(now()->addDay()->toDateString(), $t->visit_site->toDateString());
        $this->assertSame(now()->addDays(2)->toDateString(), $t->target_online->toDateString());
    }

    public function test_teknisi_tidak_bisa_menyetujui_plannya_sendiri(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'APPROVED');

        // Teknisi yang juga leader team-nya sendiri tetap tidak bisa menyetujui plannya.
        Team::query()->update(['leader_id' => $this->tech->id]);
        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/plan/approve")->assertForbidden();

        $this->assertSame('APPROVED', $t->fresh()->status);
    }

    public function test_resolusi_difinalkan_saat_tiket_ditutup(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'IN_PROGRESS');

        $this->assertSame('running', $t->slaMetric('resolution')['state']);
        $this->assertNull($t->resolution_seconds);

        Carbon::setTestNow(now()->addHours(3));
        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/close", ['rca' => 'Konfigurasi', 'action' => 'Konfigurasi ulang router'])
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('CLOSED', $t->status);
        $this->assertSame($this->tech->id, $t->closed_by);
        // Resolusi = closed_at − progress_started_at.
        $this->assertEqualsWithDelta(3 * 3600, $t->resolution_seconds, 2);
        $this->assertSame('done', $t->slaMetric('resolution')['state']);
        // Travel = progress_started_at − assigned_at (definisi SLAmeet).
        $this->assertSame('done', $t->slaMetric('travel')['state']);
        Carbon::setTestNow();
    }

    public function test_lampiran_lebih_dari_10mb_ditolak(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'IN_PROGRESS');

        // Isi PDF sungguhan supaya lolos deteksi isi; yang diuji hanya ukurannya.
        $pdf = fn (int $kb) => UploadedFile::fake()->createWithContent('log.pdf', str_pad("%PDF-1.4\n", $kb * 1024, ' '));

        $this->actingAs($this->tech)->postJson("/trouble-ticket/{$t->id}/messages", [
            'attachments' => [$pdf(10241)],
        ])->assertStatus(422)->assertJsonValidationErrors('attachments.0');
        $this->assertSame(0, TroubleTicketAttachment::count());

        $this->postJson("/trouble-ticket/{$t->id}/messages", [
            'attachments' => [$pdf(10240)],
        ])->assertOk();
        $this->assertSame('application/pdf', TroubleTicketAttachment::firstOrFail()->mime_type);
    }

    public function test_jenis_berkas_berbahaya_ditolak(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'IN_PROGRESS');
        $this->actingAs($this->tech);

        // Ekstensi terlarang.
        $this->postJson("/trouble-ticket/{$t->id}/messages", [
            'attachments' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')],
        ])->assertStatus(422);

        // Isi HTML yang menyamar sebagai .pdf: ditolak oleh deteksi isi.
        $this->postJson("/trouble-ticket/{$t->id}/messages", [
            'attachments' => [UploadedFile::fake()->createWithContent('invoice.pdf', '<html><script>alert(1)</script></html>')],
        ])->assertStatus(422);

        $this->assertSame(0, TroubleTicketAttachment::count());
    }

    public function test_unduhan_lampiran_mengikuti_keterlihatan_tiket(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'IN_PROGRESS');
        $this->actingAs($this->tech)->postJson("/trouble-ticket/{$t->id}/messages", [
            'body' => 'foto modem',
            'attachments' => [UploadedFile::fake()->image('modem.png')],
        ])->assertOk();

        $att = TroubleTicketAttachment::firstOrFail();
        $this->assertStringStartsWith('trouble_ticket_attachments/', $att->storage_path);
        $this->assertNotSame('modem.png', basename($att->storage_path));
        $this->assertSame('image/png', $att->mime_type);
        $this->assertSame(64, strlen($att->file_hash));

        $this->actingAs($this->outsider)->get(route('trouble-ticket.attachment', $att))->assertForbidden();
        $this->actingAs($this->outsider)->get('/storage/'.$att->storage_path)->assertForbidden();
        $this->actingAs($this->tech2)->get(route('trouble-ticket.attachment', $att))->assertOk();
    }

    public function test_pesan_idempoten_per_token(): void
    {
        $t = $this->advance($this->createTicket(), 'NON_VISIT', 'IN_PROGRESS');
        $token = (string) Str::uuid();

        $this->actingAs($this->tech)->postJson("/trouble-ticket/{$t->id}/messages", ['body' => 'cek', 'client_token' => $token])->assertOk();
        $this->postJson("/trouble-ticket/{$t->id}/messages", ['body' => 'cek', 'client_token' => $token])->assertOk();

        $this->assertSame(1, TroubleTicketMessage::where('trouble_ticket_id', $t->id)->where('type', 'MESSAGE')->count());
    }

    public function test_status_dan_jejak_konsisten_bila_operasi_basis_data_gagal(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'ON_PLAN');

        // Penulisan kejadian timeline gagal → transisi ikut batal.
        TroubleTicketMessage::creating(function () {
            throw new \RuntimeException('simulasi gagal');
        });

        try {
            $this->actingAs($this->tech)->withoutExceptionHandling()->post("/trouble-ticket/{$t->id}/start");
            $this->fail('Seharusnya melempar exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulasi gagal', $e->getMessage());
        }

        TroubleTicketMessage::flushEventListeners();

        $t->refresh();
        $this->assertSame('ON_PLAN', $t->status);
        $this->assertNull($t->progress_started_at);
    }

    public function test_reassign_mempertahankan_respons_pertama_dan_riwayat(): void
    {
        $t = $this->createTicket();
        $this->actingAs($this->tech)->get("/trouble-ticket/{$t->id}");
        $pertama = $t->fresh()->acknowledged_at;

        $this->actingAs($this->tech)->post("/trouble-ticket/{$t->id}/reassign", ['assigned_to' => $this->tech2->id, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($this->leader)->post("/trouble-ticket/{$t->id}/reassign", ['assigned_to' => $this->tech2->id, 'reason' => 'Tech1 cuti'])
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame($this->tech2->id, $t->assigned_to);
        $this->assertSame('OPEN', $t->status);
        $this->assertTrue($pertama->equalTo($t->acknowledged_at));
        $this->assertSame($this->tech->id, $t->acknowledged_by);
        $this->assertSame(2, $t->assignments()->count());
        $this->assertSame(1, $t->assignments()->whereNull('unassigned_at')->count());

        // Teknisi lama tidak lagi bisa beraksi; teknisi baru merespons sendiri.
        $this->actingAs($this->tech2)->get("/trouble-ticket/{$t->id}")->assertOk();
        $this->assertSame('OPEN', $t->fresh()->status);
        $this->assertNotNull($t->assignments()->whereNull('unassigned_at')->value('responded_at'));
    }

    public function test_sla_snapshot_dan_tanpa_target_tidak_pernah_terlambat(): void
    {
        $tanpaTarget = $this->createTicket();
        Carbon::setTestNow(now()->addDays(3));
        $this->assertSame('none', $tanpaTarget->fresh()->slaMetric('response')['level']);
        $this->assertSame(0, TroubleTicket::overdue()->count());
        Carbon::setTestNow();

        TroubleTicketSlaPolicy::create(['severity' => null, 'problem_id' => $this->problem->id, 'response_minutes' => 30, 'resolution_minutes' => 240, 'status' => 'ACTIVE']);
        $t = $this->createTicket();
        $this->assertSame(30, $t->sla_response_minutes);

        // Mengubah kebijakan tidak mengubah tiket lama.
        TroubleTicketSlaPolicy::query()->update(['response_minutes' => 5]);
        $this->assertSame(30, $t->fresh()->sla_response_minutes);

        Carbon::setTestNow(now()->addMinutes(25));
        $this->assertSame('warning', $t->fresh()->slaMetric('response')['level']);
        Carbon::setTestNow(now()->addMinutes(10));
        $this->assertSame('critical', $t->fresh()->slaMetric('response')['level']);
        Carbon::setTestNow();
    }

    public function test_halaman_daftar_detail_dan_pengaturan_dapat_dibuka(): void
    {
        $t = $this->advance($this->createTicket(), 'VISIT', 'IN_PROGRESS');

        $this->actingAs($this->leader)->get('/trouble-ticket?q=Link&status=IN_PROGRESS&sort=severity&dir=asc')
            ->assertOk()->assertSee($t->ticket_number);
        $this->actingAs($this->leader)->get("/trouble-ticket/{$t->id}")->assertOk()->assertSee('id="ttChatList"', false);
        $this->actingAs($this->leader)->getJson("/trouble-ticket/{$t->id}/messages?after_id=0")->assertOk()->assertJsonStructure(['html', 'status']);
        // Form buat tiket: tanpa Problem, Initial Problem, Flag Alarm, dan Status TT.
        $this->actingAs($this->creator)->get('/trouble-ticket/create')->assertOk()
            ->assertSee('id="fldSubRca"', false)
            ->assertDontSee('id="fldProblem"', false)
            ->assertDontSee('id="fldIssue"', false)
            ->assertDontSee('id="fldAlarm"', false)
            ->assertDontSee('Status TT');

        $this->actingAs($this->leader)->get('/trouble-ticket/settings')->assertForbidden();
        $this->actingAs($this->userWithRole('superadmin'))->get('/trouble-ticket/settings')->assertOk();

        // Tanpa menu akses: 403.
        $this->actingAs($this->userWithRole('finance'))->get('/trouble-ticket')->assertForbidden();
    }

    public function test_severity_dihitung_dari_aging_tiket(): void
    {
        $t = $this->createTicket();

        // Hari pembuatan: aging 0 → Rendah.
        $this->assertSame([0, 'Rendah', 4], [$t->aging_days, $t->severity_label, $t->severity_level]);

        // Titik awal dikunci di luar loop: now() di dalamnya sudah ikut bergeser.
        $open = $t->reported_at->copy()->startOfDay();

        foreach ([[1, 'Minor', 3], [2, 'Minor', 3], [3, 'Major', 2], [7, 'Major', 2], [8, 'Kritis', 1], [30, 'Kritis', 1]] as [$hari, $label, $level]) {
            Carbon::setTestNow($open->copy()->addDays($hari)->addHours(2));
            $segar = $t->fresh();
            $this->assertSame([$hari, $label, $level], [$segar->aging_days, $segar->severity_label, $segar->severity_level]);
        }

        Carbon::setTestNow();
    }

    public function test_export_excel_mengikuti_filter_dan_keterlihatan(): void
    {
        $a = $this->createTicket();
        $b = $this->createTicket();

        $baca = function ($res) {
            $file = tempnam(sys_get_temp_dir(), 'tt').'.xlsx';
            file_put_contents($file, $res->streamedContent());
            $rows = IOFactory::load($file)->getActiveSheet()->toArray();
            @unlink($file);

            return collect($rows)->skip(1)->pluck(0)->filter()->values()->all();
        };

        // Tanpa ids: semua tiket yang terlihat.
        $semua = $baca($this->actingAs($this->leader)->get(route('trouble-ticket.export'))->assertOk());
        $this->assertEqualsCanonicalizing([$a->ticket_number, $b->ticket_number], $semua);

        // Dengan ids (baris lolos filter): hanya itu.
        $this->assertSame([$a->ticket_number], $baca($this->post(route('trouble-ticket.export'), ['ids' => [$a->id]])->assertOk()));

        // Orang di luar rantai team tidak mendapat tiket walau mengirim id-nya.
        $this->assertSame([], $baca($this->actingAs($this->outsider)->post(route('trouble-ticket.export'), ['ids' => [$a->id, $b->id]])->assertOk()));
    }

    public function test_modal_info_tidak_mencatat_respons_dan_mengikuti_keterlihatan(): void
    {
        $t = $this->createTicket();

        // Teknisi melihat info dari daftar: bukan "membuka tiket", timer Response tetap jalan.
        $this->actingAs($this->tech)->get(route('trouble-ticket.info', $t))
            ->assertOk()->assertSee($t->issue_label);
        $this->assertNull($t->fresh()->acknowledged_at);

        $this->actingAs($this->outsider)->get(route('trouble-ticket.info', $t))->assertForbidden();

        // Daftar memuat tombol info & tautan No. TT.
        $this->actingAs($this->leader)->get('/trouble-ticket')
            ->assertOk()->assertSee(route('trouble-ticket.info', $t))->assertSee(route('trouble-ticket.show', $t));
    }
}
