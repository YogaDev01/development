<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MasterPic;
use App\Models\Site;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TeamLog;
use App\Models\TeamMember;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketIssue;
use App\Models\TroubleTicketProblem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Team dengan beberapa customer (keputusan 6 Nov 2026).
 *
 * Fokus: form Team menyimpan daftar customer ke team_customers, dan batas
 * customer di Trouble Ticket mengikuti seluruh daftar itu.
 */
class TeamCustomerTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private User $admin;

    private User $leader;

    private User $creator;

    private User $tech;

    private Customer $customerA;

    private Customer $customerB;

    private Customer $customerLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->giveMenuAccess($this->role('manager'), 'trouble-ticket');
        $this->giveMenuAccess($this->role('staff'), 'trouble-ticket');

        $this->admin = $this->userWithRole('superadmin');
        $this->leader = $this->userWithRole('supervisor');
        $this->creator = $this->userWithRole('manager');
        $this->tech = $this->userWithRole('staff');

        $this->customerA = $this->makeCustomer();
        $this->customerB = $this->makeCustomer();
        $this->customerLain = $this->makeCustomer();
    }

    /* ------------------------------------------------------------ helpers */

    private function makeTeam(array $customers = []): Team
    {
        $team = Team::create([
            'code' => 'TEAM-C', 'name' => 'TEAM C', 'leader_id' => $this->leader->id, 'status' => 'ACTIVE',
            'customer_id' => $customers[0]->id ?? null,
        ]);
        foreach ([$this->leader, $this->creator] as $i => $u) {
            TeamMember::create(['team_id' => $team->id, 'user_id' => $u->id, 'level' => $i + 1, 'status' => 'ACTIVE']);
        }
        $team->customers()->attach(collect($customers)->pluck('id'));

        return $team;
    }

    private function payload(Team $team, array $override = []): array
    {
        return array_merge([
            'name' => $team->name, 'leader_id' => $team->leader_id, 'status' => $team->status,
            'members' => $team->activeMembers()->get()->map(fn ($m) => [
                'id' => $m->id, 'user_id' => $m->user_id, 'level' => $m->level, 'status' => 'ACTIVE',
            ])->values()->all(),
        ], $override);
    }

    private function makeSite(Customer $customer, string $tid): Site
    {
        $site = Site::create([
            'tid' => $tid, 'sitename' => 'Site '.$tid, 'type' => 'TOWER', 'source_power' => 'PLN',
            'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE', 'customer_id' => $customer->id,
        ]);
        $pic = MasterPic::firstOrCreate(
            ['user_id' => $this->tech->id],
            ['type' => 'internal', 'pic' => $this->tech->name, 'status' => 'ACTIVE']
        );
        SitePic::create(['site_id' => $site->id, 'pic_id' => $pic->id]);

        return $site;
    }

    private function postTicket(Site $site)
    {
        return $this->actingAs($this->creator)->post('/trouble-ticket', [
            'site_id' => $site->id, 'assigned_to' => $this->tech->id,
            'problem_id' => TroubleTicketProblem::create(['name' => 'Link Down '.$site->tid])->id,
            'issue_id' => TroubleTicketIssue::create(['name' => 'MODEM '.$site->tid])->id,
            'description' => 'Modem mati sejak pagi',
        ]);
    }

    /* ---------------------------------------------------------- form Team */

    public function test_team_baru_menyimpan_beberapa_customer(): void
    {
        $this->actingAs($this->admin)->post('/team', [
            'name' => 'team baru', 'leader_id' => $this->leader->id,
            'customer_sync' => 1, 'customer_ids' => [$this->customerA->id, $this->customerB->id],
            'members' => [['user_id' => $this->leader->id, 'level' => 1, 'status' => 'ACTIVE']],
        ])->assertRedirect('/team');

        $team = Team::where('name', 'TEAM BARU')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$this->customerA->id, $this->customerB->id],
            $team->customers()->pluck('customers.id')->all()
        );
        $this->assertSame($this->customerA->id, (int) $team->customer_id);
    }

    public function test_edit_mengganti_customer_dan_mencatat_namanya(): void
    {
        $team = $this->makeTeam([$this->customerA]);

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team, [
            'customer_sync' => 1, 'customer_ids' => [$this->customerB->id, $this->customerLain->id],
        ]))->assertRedirect('/team');

        $this->assertEqualsCanonicalizing(
            [$this->customerB->id, $this->customerLain->id],
            $team->customers()->pluck('customers.id')->all()
        );
        $this->assertSame($this->customerB->id, (int) $team->fresh()->customer_id);

        $log = TeamLog::where('team_id', $team->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame($this->customerA->name, $log->changes['customer_id'][0]);
        $this->assertStringContainsString($this->customerB->name, $log->changes['customer_id'][1]);
    }

    public function test_edit_tanpa_perubahan_customer_tidak_menulis_riwayat(): void
    {
        $team = $this->makeTeam([$this->customerA]);

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team, [
            'customer_sync' => 1, 'customer_ids' => [$this->customerA->id],
        ]))->assertRedirect('/team');

        $this->assertFalse(TeamLog::where('team_id', $team->id)->where('action', 'updated')->exists());
    }

    public function test_multi_select_kosong_mencabut_semua_customer(): void
    {
        $team = $this->makeTeam([$this->customerA, $this->customerB]);

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team, ['customer_sync' => 1]))
            ->assertRedirect('/team');

        $this->assertSame(0, $team->customers()->count());
        $this->assertNull($team->fresh()->customer_id);
    }

    public function test_permintaan_tanpa_penanda_tidak_mengubah_customer(): void
    {
        $team = $this->makeTeam([$this->customerA, $this->customerB]);

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team))->assertRedirect('/team');

        $this->assertSame(2, $team->customers()->count());
        $this->assertSame($this->customerA->id, (int) $team->fresh()->customer_id);
    }

    public function test_customer_yang_tidak_ada_ditolak(): void
    {
        $team = $this->makeTeam([$this->customerA]);

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team, [
            'customer_sync' => 1, 'customer_ids' => [999999],
        ]))->assertSessionHasErrors('customer_ids.0');

        $this->assertSame([$this->customerA->id], $team->customers()->pluck('customers.id')->all());
    }

    /* ------------------------------------------------------ Trouble Ticket */

    public function test_team_dua_customer_memilih_customer_dan_site_tetap_menyempit(): void
    {
        $this->makeTeam([$this->customerA, $this->customerB]);
        $siteA = $this->makeSite($this->customerA, 'TID-A');
        $siteB = $this->makeSite($this->customerB, 'TID-B');
        $siteLain = $this->makeSite($this->customerLain, 'TID-L');

        $this->actingAs($this->creator)->get('/trouble-ticket/create')
            ->assertOk()
            ->assertViewHas('customerTerkunci', fn ($c) => $c === null)
            ->assertViewHas('sites', fn ($sites) => $sites->pluck('id')->sort()->values()->all() === collect([$siteA->id, $siteB->id])->sort()->values()->all()
                && ! $sites->contains('id', $siteLain->id));
    }

    public function test_team_satu_customer_tetap_terkunci(): void
    {
        $this->makeTeam([$this->customerA]);
        $this->makeSite($this->customerA, 'TID-A');
        $this->makeSite($this->customerLain, 'TID-L');

        $this->actingAs($this->creator)->get('/trouble-ticket/create')
            ->assertOk()
            ->assertViewHas('customerTerkunci', fn ($c) => $c?->id === $this->customerA->id);
    }

    public function test_tiket_diterima_pada_customer_kedua_dan_ditolak_di_luar_team(): void
    {
        $this->makeTeam([$this->customerA, $this->customerB]);
        $siteB = $this->makeSite($this->customerB, 'TID-B');
        $siteLain = $this->makeSite($this->customerLain, 'TID-L');

        $this->postTicket($siteB)->assertSessionHasNoErrors();
        $this->assertTrue(TroubleTicket::where('site_id', $siteB->id)->exists());

        $this->postTicket($siteLain)->assertSessionHasErrors('site_id');
        $this->assertFalse(TroubleTicket::where('site_id', $siteLain->id)->exists());
    }

    public function test_team_inactive_tidak_memberi_customer(): void
    {
        $team = $this->makeTeam([$this->customerA]);
        $team->update(['status' => 'INACTIVE']);
        $siteLain = $this->makeSite($this->customerLain, 'TID-L');

        // Tanpa team aktif ber-customer, pembuat kembali bebas memilih site.
        $this->postTicket($siteLain)->assertSessionHasNoErrors();
    }
}
