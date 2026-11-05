<?php

namespace Tests\Feature;

use App\Models\ProjectLog;
use App\Models\RequestStatusLog;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Histori transaksi tidak berubah oleh perubahan organisasi — Fase 3
 * blueprint docs/plans/2026-11-06-team-akses-organisasi.md (K6, K7, §4).
 *
 *  - konteks organisasi pelaku dibekukan di baris log saat aksi;
 *  - pindah team / ganti leader / nonaktifkan team tidak mengubah baris log;
 *  - pelaku tetap bisa MEMBACA dokumen yang pernah ia tindak, tanpa aksi.
 */
class OrgHistoryImmutabilityTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole('superadmin');
    }

    /* ------------------------------------------------------------ helpers */

    private function teamFor(User $user, string $code, bool $primary = true, array $members = []): Team
    {
        $team = Team::create(['code' => $code, 'name' => $code, 'leader_id' => $user->id, 'status' => 'ACTIVE']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $user->id, 'position' => 'Head ' . $code, 'level' => 1, 'is_primary' => $primary, 'status' => 'ACTIVE']);
        foreach ($members as $m) {
            TeamMember::create(['team_id' => $team->id, 'user_id' => $m->id, 'level' => 2, 'status' => 'ACTIVE']);
        }

        return $team;
    }

    /** Sidik jari baris log (semua kolom) untuk membandingkan sebelum/sesudah. */
    private function fingerprint(string $model, string $fk, int $id): string
    {
        return md5(json_encode($model::where($fk, $id)->orderBy('id')->get()->map->getAttributes()->all()));
    }

    /* -------------------------------------------------- konteks organisasi */

    public function test_status_log_freezes_actor_org_context(): void
    {
        $head = $this->userWithRole('head');
        $this->teamFor($head, 'TEAM-FIN');
        $cr = $this->makeCostRequest(['status' => 'SUBMITTED']);

        $this->actingAs($head);
        $cr->addLog('SUBMITTED', 'CHECKED', 'head_approved', 'ok');

        $ctx = RequestStatusLog::where('budget_request_id', $cr->id)->latest('id')->first()->actor_org_context;
        $this->assertSame('TEAM-FIN', $ctx['primary_team']['code']);
        $this->assertSame('Head TEAM-FIN', $ctx['position']);
        $this->assertSame(['TEAM-FIN'], $ctx['teams']);
    }

    public function test_primary_is_not_guessed(): void
    {
        $head = $this->userWithRole('head');
        $this->teamFor($head, 'TEAM-X', false);
        $project = $this->makeProject();

        $this->actingAs($head);
        $project->addLog('ACTIVE', 'ACTIVE', 'edited');

        $ctx = ProjectLog::where('project_id', $project->id)->latest('id')->first()->actor_org_context;
        $this->assertNull($ctx['primary_team']);
        $this->assertSame(['TEAM-X'], $ctx['teams']);
    }

    public function test_org_changes_do_not_rewrite_existing_logs(): void
    {
        $head = $this->userWithRole('head');
        $member = $this->userWithRole('staff');
        $team = $this->teamFor($head, 'TEAM-A', true, [$member]);
        $cr = $this->makeCostRequest(['status' => 'APPROVED']);

        $this->actingAs($head);
        $cr->addLog('SUBMITTED', 'CHECKED', 'head_approved');
        $before = $this->fingerprint(RequestStatusLog::class, 'budget_request_id', $cr->id);

        // Ganti leader, keluarkan Head dari team, lalu nonaktifkan team.
        $this->actingAs($this->admin)->put("/team/{$team->id}", [
            'name' => $team->name, 'leader_id' => $member->id, 'status' => 'ACTIVE',
            'members' => [['user_id' => $member->id, 'level' => 1, 'status' => 'ACTIVE']],
        ])->assertRedirect('/team');
        $this->actingAs($this->admin)->patch("/team/{$team->id}/status", ['status' => 'INACTIVE'])->assertRedirect('/team');

        $this->assertSame($before, $this->fingerprint(RequestStatusLog::class, 'budget_request_id', $cr->id));
        $this->assertSame('TEAM-A', RequestStatusLog::where('budget_request_id', $cr->id)->first()->actor_org_context['primary_team']['code']);
    }

    /* ------------------------------------------------------- baca-saja CR */

    public function test_former_actor_can_read_cr_but_not_act(): void
    {
        $head = $this->userWithRole('head');
        $cr = $this->makeCostRequest(['status' => 'SUBMITTED']);

        // Head sempat punya akses proyek dan menindak CR ini…
        $this->giveProjectAccess($head, $cr->budgetPlan->project);
        $this->actingAs($head);
        $cr->addLog('DRAFT', 'SUBMITTED', 'submitted');

        // …lalu aksesnya dicabut (pindah team / Access Group dilepas).
        $head->update(['access_group_id' => null]);

        $this->actingAs($head)->getJson("/budget-request/{$cr->id}/detail-json")
            ->assertOk()
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('workflow_actions', [])
            ->assertJsonPath('can_post_log_note', false);

        $this->actingAs($head)->post("/budget-request/{$cr->id}/head-approve")->assertForbidden();
        $this->assertSame('SUBMITTED', $cr->fresh()->status);
    }

    public function test_non_actor_without_access_cannot_read_cr(): void
    {
        $cr = $this->makeCostRequest(['status' => 'SUBMITTED']);

        $this->actingAs($this->userWithRole('head'))->getJson("/budget-request/{$cr->id}/detail-json")->assertForbidden();
    }

    /* ------------------------------------------------------- baca-saja PO */

    public function test_former_actor_can_read_po_but_not_post_note(): void
    {
        $project = $this->makeProject();
        $user = $this->userWithRole('head');

        $this->actingAs($user);
        $project->addLog('ACTIVE', 'ACTIVE', 'edited');

        $this->actingAs($user)->getJson("/project/{$project->id}/detail-json")
            ->assertOk()
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('can_post_log_note', false);

        $this->actingAs($user)->postJson("/project/{$project->id}/log-note", ['notes' => 'halo'])->assertForbidden();
    }

    public function test_active_access_is_not_marked_read_only(): void
    {
        $project = $this->makeProject();
        $user = $this->giveProjectAccess($this->userWithRole('head'), $project);

        $this->actingAs($user)->getJson("/project/{$project->id}/detail-json")
            ->assertOk()
            ->assertJsonPath('read_only', false);
    }
}
