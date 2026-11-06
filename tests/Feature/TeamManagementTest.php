<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamLog;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Team — Fase 1 blueprint docs/plans/2026-11-06-team-akses-organisasi.md.
 *
 * Fokus: riwayat keanggotaan tidak pernah hilang (upsert, bukan hapus-lalu-
 * buat), team tidak bisa dihapus permanen, setiap perubahan tercatat di
 * team_logs, dan validasi struktur (induk melingkar, primary ganda, user
 * nonaktif, induk hanya diatur superadmin).
 */
class TeamManagementTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private User $admin;

    private User $leader;

    private User $memberA;

    private User $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin   = $this->userWithRole('superadmin');
        $this->giveMenuAccess($this->role('manager'), 'team');
        $this->leader  = $this->userWithRole('manager');
        $this->memberA = $this->userWithRole('staff');
        $this->memberB = $this->userWithRole('staff');
    }

    /* ------------------------------------------------------------ helpers */

    private function makeTeam(string $code, User $leader, array $members = [], array $attributes = []): Team
    {
        $team = Team::create(array_merge([
            'code' => $code, 'name' => $code, 'leader_id' => $leader->id, 'status' => 'ACTIVE',
        ], $attributes));

        foreach (array_merge([$leader], $members) as $i => $u) {
            TeamMember::create(['team_id' => $team->id, 'user_id' => $u->id, 'level' => $i === 0 ? 1 : 2, 'status' => 'ACTIVE']);
        }

        return $team;
    }

    /** Payload form Edit dari membership ACTIVE yang ada (+ perubahan). */
    private function payload(Team $team, array $override = [], ?array $members = null): array
    {
        $members ??= $team->activeMembers()->get()->map(fn ($m) => [
            'id' => $m->id, 'user_id' => $m->user_id, 'position' => $m->position,
            'level' => $m->level, 'status' => 'ACTIVE',
        ])->values()->all();

        return array_merge([
            'name' => $team->name, 'leader_id' => $team->leader_id, 'status' => $team->status,
            'members' => $members,
        ], $override);
    }

    /* -------------------------------------------------------------- tests */

    public function test_kartu_bagan_punya_kunci_unik_supaya_garis_mengikuti_atasan_langsung(): void
    {
        // leader → memberA (memimpin team bawah) → memberB, plus satu akar lain.
        $this->makeTeam('TEAM-ATAS', $this->leader, [$this->memberA]);
        $this->makeTeam('TEAM-BAWAH', $this->memberA, [$this->memberB]);
        $this->makeTeam('TEAM-TEPI', $this->memberB);
        $this->makeTeam('TEAM-AKAR-LAIN', $this->admin);

        $this->actingAs($this->admin)->get('/team')
            ->assertOk()
            ->assertViewHas('orgChart', function (array $akar) {
                $kunci = [];
                $telusuri = function (array $kartu) use (&$telusuri, &$kunci) {
                    foreach ($kartu as $k) {
                        $kunci[] = $k['key'];
                        $telusuri($k['children'] ?? []);
                    }
                };
                $telusuri($akar);

                return count($kunci) === 4 && count(array_unique($kunci)) === 4;
            });
    }

    public function test_detail_member_menyiapkan_satu_team_di_atasnya(): void
    {
        $atas = $this->makeTeam('TEAM-ATAS', $this->leader, [$this->memberA]);
        $bawah = $this->makeTeam('TEAM-BAWAH', $this->memberA, [$this->memberB]);

        $this->actingAs($this->admin)->get('/team')
            ->assertOk()
            ->assertSee('data-above="'.$atas->id.'" data-link="'.$this->memberA->id.'"', false)
            ->assertDontSee('data-above="'.$bawah->id.'"', false);
    }

    public function test_tab_team_profil_memakai_kartu_bagan_organisasi(): void
    {
        $this->makeTeam('TEAM-ATAS', $this->leader, [$this->memberA]);

        $this->actingAs($this->memberA)->get('/profile')
            ->assertOk()
            ->assertSee('oc-card is-me', false)
            ->assertSee('Level 2 · 1 orang');
    }

    public function test_edit_team_keeps_membership_rows_and_ids(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);
        $idsBefore = TeamMember::where('team_id', $team->id)->orderBy('id')->pluck('id')->all();

        $members = $this->payload($team)['members'];
        $members[1]['position'] = 'Officer';

        $this->actingAs($this->admin)
            ->put("/team/{$team->id}", $this->payload($team, [], $members))
            ->assertRedirect('/team');

        $this->assertSame($idsBefore, TeamMember::where('team_id', $team->id)->orderBy('id')->pluck('id')->all());
        $this->assertSame('Officer', TeamMember::find($idsBefore[1])->position);
        $this->assertTrue(TeamLog::where('team_id', $team->id)->where('action', 'member_updated')->exists());
    }

    public function test_removed_member_becomes_inactive_not_deleted(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);
        $row = TeamMember::where('team_id', $team->id)->where('user_id', $this->memberA->id)->first();

        $members = collect($this->payload($team)['members'])->reject(fn ($m) => $m['user_id'] === $this->memberA->id)->values()->all();

        $this->actingAs($this->admin)
            ->put("/team/{$team->id}", $this->payload($team, [], $members))
            ->assertRedirect('/team');

        $this->assertSame('INACTIVE', $row->fresh()->status);
        $this->assertTrue(TeamLog::where('team_id', $team->id)->where('action', 'member_removed')->exists());
    }

    public function test_rejoining_creates_new_row_and_keeps_old_history(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader);
        $old = TeamMember::create(['team_id' => $team->id, 'user_id' => $this->memberA->id, 'level' => 2, 'status' => 'INACTIVE']);

        $members = $this->payload($team)['members'];
        $members[] = ['user_id' => $this->memberA->id, 'level' => 2, 'status' => 'ACTIVE'];

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team, [], $members))->assertRedirect('/team');

        $this->assertSame('INACTIVE', $old->fresh()->status);
        $this->assertSame(1, TeamMember::where('team_id', $team->id)->where('user_id', $this->memberA->id)->where('status', 'ACTIVE')->count());
    }

    public function test_team_cannot_be_deleted_only_deactivated(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);

        $this->actingAs($this->admin)->delete("/team/{$team->id}")->assertStatus(405);

        $this->actingAs($this->admin)
            ->patch("/team/{$team->id}/status", ['status' => 'INACTIVE', 'notes' => 'Restrukturisasi'])
            ->assertRedirect('/team');

        $this->assertSame('INACTIVE', $team->fresh()->status);
        $this->assertSame(2, TeamMember::where('team_id', $team->id)->count());
        $this->assertTrue(TeamLog::where('team_id', $team->id)->where('action', 'deactivated')->where('notes', 'Restrukturisasi')->exists());
    }

    public function test_only_superadmin_may_change_status(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader);

        $this->actingAs($this->leader)->patch("/team/{$team->id}/status", ['status' => 'INACTIVE'])->assertForbidden();
        $this->assertSame('ACTIVE', $team->fresh()->status);
    }

    public function test_leader_cannot_change_status_via_edit_form(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);

        $this->actingAs($this->leader)
            ->put("/team/{$team->id}", $this->payload($team, ['status' => 'INACTIVE']))
            ->assertRedirect('/team');

        $this->assertSame('ACTIVE', $team->fresh()->status);
    }

    public function test_swapping_user_on_a_row_keeps_old_history(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);
        $rowA = TeamMember::where('team_id', $team->id)->where('user_id', $this->memberA->id)->first();

        // Baris milik A dikirim dengan id-nya, tapi user diganti ke B.
        $members = collect($this->payload($team)['members'])
            ->map(fn ($m) => $m['id'] === $rowA->id ? array_merge($m, ['user_id' => $this->memberB->id]) : $m)->all();

        $this->actingAs($this->admin)->put("/team/{$team->id}", $this->payload($team, [], $members))->assertRedirect('/team');

        $rowA->refresh();
        $this->assertSame($this->memberA->id, (int) $rowA->user_id);
        $this->assertSame('INACTIVE', $rowA->status);
        $this->assertTrue(TeamMember::where('team_id', $team->id)->where('user_id', $this->memberB->id)->where('status', 'ACTIVE')->exists());
    }

    public function test_inactive_team_cannot_receive_new_members(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader, [], ['status' => 'INACTIVE']);

        $members = $this->payload($team)['members'];
        $members[] = ['user_id' => $this->memberA->id, 'level' => 2, 'status' => 'ACTIVE'];

        $this->actingAs($this->admin)
            ->put("/team/{$team->id}", $this->payload($team, [], $members))
            ->assertSessionHasErrors('members');

        $this->assertFalse(TeamMember::where('user_id', $this->memberA->id)->exists());
    }

    public function test_inactive_parent_is_kept_when_saving(): void
    {
        $induk = $this->makeTeam('TEAM-INDUK', $this->memberB, [], ['status' => 'INACTIVE']);
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA], ['parent_team_id' => $induk->id]);

        $this->actingAs($this->admin)
            ->put("/team/{$team->id}", $this->payload($team, ['parent_team_id' => $induk->id]))
            ->assertRedirect('/team');

        $this->assertSame($induk->id, (int) $team->fresh()->parent_team_id);
    }

    public function test_index_shows_database_and_organization_tabs(): void
    {
        $induk = $this->makeTeam('TEAM-INDUK', $this->leader);
        $this->makeTeam('TEAM-ANAK', $this->memberA, [$this->memberB], ['parent_team_id' => $induk->id]);

        $this->actingAs($this->admin)->get('/team')
            ->assertOk()
            ->assertSee('id="paneDatabase"', false)
            ->assertSee('id="paneOrganisasi"', false)
            ->assertSee('id="tdPanel-' . $induk->id . '"', false)
            ->assertSee('Orang per Department')
            ->assertSee('id="team-' . $induk->id . '"', false);

        $this->actingAs($this->admin)->get('/team/structure')->assertRedirect(url('/team') . '#organisasi');
    }

    public function test_org_chart_links_teams_through_shared_person(): void
    {
        // Ketut memimpin Team B; Yoga anggota Team B sekaligus leader Team A.
        $ketut = $this->userWithRole('head', ['name' => 'Ketut Uji']);
        $yoga  = $this->userWithRole('manager', ['name' => 'Yoga Uji']);
        $teamB = $this->makeTeam('TEAM-B', $ketut, [$yoga]);
        $teamA = $this->makeTeam('TEAM-A', $yoga, [$this->memberA]);

        $html = $this->actingAs($this->admin)->get('/team')->assertOk()->getContent();

        // Yoga tampil SEKALI di bagan leader, dan Team A digantung di bawah kartunya.
        // (Isi Detail Member ada di <template> setelah bagan, jadi dihitung terpisah.)
        $bagan = strstr($html, 'id="ocTeamTemplates"', true);
        $this->assertSame(1, substr_count($bagan, 'data-user="' . $yoga->id . '"'));
        // Anggota biasa tidak tampil di bagan leader, hanya di Detail Member team-nya.
        $this->assertStringNotContainsString('data-user="' . $this->memberA->id . '"', $bagan);
        $this->assertStringContainsString('id="ocTeamTpl-' . $teamA->id . '"', $html);
        $this->assertStringContainsString('data-user="' . $this->memberA->id . '"', $html);
        $kartuYoga = strpos($html, 'data-user="' . $yoga->id . '"');
        $blokA     = strpos($html, 'id="team-' . $teamA->id . '"');
        $this->assertNotFalse($blokA);
        $this->assertGreaterThan($kartuYoga, $blokA);
        $this->assertStringContainsString('Leader ' . $teamA->name, $html);
        // Ketut akar, Team B di bawahnya.
        $this->assertLessThan($kartuYoga, strpos($html, 'data-user="' . $ketut->id . '"'));
        $this->assertNotFalse(strpos($html, 'id="team-' . $teamB->id . '"'));
    }

    public function test_parent_cycle_is_rejected(): void
    {
        $induk = $this->makeTeam('TEAM-INDUK', $this->leader);
        $anak  = $this->makeTeam('TEAM-ANAK', $this->memberA, [], ['parent_team_id' => $induk->id]);

        $this->actingAs($this->admin)
            ->put("/team/{$induk->id}", $this->payload($induk, ['parent_team_id' => $anak->id]))
            ->assertSessionHasErrors('parent_team_id');

        $this->assertNull($induk->fresh()->parent_team_id);
    }

    public function test_leader_cannot_change_parent(): void
    {
        $lain = $this->makeTeam('TEAM-LAIN', $this->memberB);
        $team = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);

        $this->actingAs($this->leader)
            ->put("/team/{$team->id}", $this->payload($team, ['parent_team_id' => $lain->id]))
            ->assertRedirect('/team');

        $this->assertNull($team->fresh()->parent_team_id);
    }

    public function test_user_cannot_have_two_primary_teams(): void
    {
        $teamA = $this->makeTeam('TEAM-A', $this->leader, [$this->memberA]);
        $teamB = $this->makeTeam('TEAM-B', $this->memberB, [$this->memberA]);
        TeamMember::where('team_id', $teamA->id)->where('user_id', $this->memberA->id)->update(['is_primary' => true]);

        $members = collect($this->payload($teamB)['members'])
            ->map(fn ($m) => $m['user_id'] === $this->memberA->id ? $m + ['is_primary' => 1] : $m)->all();

        $this->actingAs($this->admin)
            ->put("/team/{$teamB->id}", $this->payload($teamB, [], $members))
            ->assertSessionHasErrors('members');

        $this->assertFalse((bool) TeamMember::where('team_id', $teamB->id)->where('user_id', $this->memberA->id)->value('is_primary'));
    }

    public function test_inactive_user_cannot_become_active_member(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader);
        $nonaktif = $this->userWithRole('staff', ['status' => 'INACTIVE']);

        $members = $this->payload($team)['members'];
        $members[] = ['user_id' => $nonaktif->id, 'level' => 2, 'status' => 'ACTIVE'];

        $this->actingAs($this->admin)
            ->put("/team/{$team->id}", $this->payload($team, [], $members))
            ->assertSessionHasErrors('members');

        $this->assertFalse(TeamMember::where('user_id', $nonaktif->id)->exists());
    }

    public function test_team_log_rows_are_immutable(): void
    {
        $team = $this->makeTeam('TEAM-A', $this->leader);
        $this->actingAs($this->admin);
        $team->addLog('updated', ['name' => ['X', 'Y']]);

        $log = TeamLog::where('team_id', $team->id)->firstOrFail();
        $log->action = 'diubah';
        $this->assertFalse($log->save());
        $this->assertFalse($log->delete());
        $this->assertSame('updated', $log->fresh()->action);
    }
}
