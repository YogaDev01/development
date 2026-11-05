<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\AccessGroupDetail;
use App\Models\CostCenter;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamLog;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Akses PO & cost center lewat Team — Fase 2 blueprint
 * docs/plans/2026-11-06-team-akses-organisasi.md (K2–K5).
 *
 * Akses = Access Group individu ∪ Access Group team ACTIVE (membership
 * ACTIVE). Struktur organisasi (leader / induk team) tidak memberi akses.
 * Fungsi Finance (cost_control/collection/treasury/accounting) melihat
 * seluruh PO; 'finance' umum tidak.
 */
class TeamAccessScopeTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    /* ------------------------------------------------------------ helpers */

    private function groupWithProject(Project $project, string $name): AccessGroup
    {
        $ag = AccessGroup::create(['name' => $name]);
        AccessGroupDetail::create(['access_group_id' => $ag->id, 'project_id' => $project->id]);

        return $ag;
    }

    private function teamWithGroup(string $code, User $leader, array $members, ?AccessGroup $ag = null, array $attributes = []): Team
    {
        $team = Team::create(array_merge(['code' => $code, 'name' => $code, 'leader_id' => $leader->id, 'status' => 'ACTIVE'], $attributes));

        foreach (array_merge([$leader], $members) as $u) {
            TeamMember::create(['team_id' => $team->id, 'user_id' => $u->id, 'level' => 1, 'status' => 'ACTIVE']);
        }

        if ($ag) {
            AccessGroupDetail::create(['access_group_id' => $ag->id, 'team_id' => $team->id]);
        }

        return $team;
    }

    private function poJson(User $user, Project $project)
    {
        return $this->actingAs($user)->getJson("/project/{$project->id}/detail-json");
    }

    /* ----------------------------------------------------------- akses team */

    public function test_team_member_gets_po_access_from_team_access_group(): void
    {
        $po = $this->makeProject();
        $member = $this->userWithRole('staff');
        $outsider = $this->userWithRole('staff');
        $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [$member], $this->groupWithProject($po, 'AG-A'));

        $this->assertTrue($member->accessibleProjectIds()->contains($po->id));
        $this->poJson($member, $po)->assertOk();
        $this->poJson($outsider, $po)->assertForbidden();
    }

    public function test_individual_access_group_still_works(): void
    {
        $po = $this->makeProject();
        $user = $this->giveProjectAccess($this->userWithRole('staff'), $po);

        $this->poJson($user, $po)->assertOk();
    }

    public function test_inactive_team_or_membership_gives_no_access(): void
    {
        $po = $this->makeProject();
        $member = $this->userWithRole('staff');
        $keluar = $this->userWithRole('staff');
        $team = $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [$member, $keluar], $this->groupWithProject($po, 'AG-A'));

        TeamMember::where('team_id', $team->id)->where('user_id', $keluar->id)->update(['status' => 'INACTIVE']);
        $this->poJson($keluar, $po)->assertForbidden();

        $team->update(['status' => 'INACTIVE']);
        $this->assertFalse($member->accessibleProjectIds()->contains($po->id));
        $this->poJson($member, $po)->assertForbidden();
    }

    public function test_hierarchy_never_inherits_access(): void
    {
        // Ketut leader Business Support; Ahmad leader Operation, Operation
        // berinduk Business Support (Ahmad bawahan Ketut di struktur).
        $poBs = $this->makeProject();
        $poOps = $this->makeProject();
        $ketut = $this->userWithRole('head');
        $ahmad = $this->userWithRole('head');

        $bs = $this->teamWithGroup('BUSINESS-SUPPORT', $ketut, [], $this->groupWithProject($poBs, 'AG-BS'));
        $this->teamWithGroup('OPERATION', $ahmad, [], $this->groupWithProject($poOps, 'AG-OPS'), ['parent_team_id' => $bs->id]);

        $this->poJson($ketut, $poBs)->assertOk();
        $this->poJson($ketut, $poOps)->assertForbidden();
        $this->poJson($ahmad, $poOps)->assertOk();
        $this->poJson($ahmad, $poBs)->assertForbidden();
    }

    public function test_cost_center_access_flows_through_team(): void
    {
        $cc = CostCenter::create(['code' => 'CC-UJI', 'name' => 'Overhead Uji', 'type' => 'OVERHEAD', 'status' => 'ACTIVE']);
        $ag = AccessGroup::create(['name' => 'AG-CC']);
        AccessGroupDetail::create(['access_group_id' => $ag->id, 'cost_center_id' => $cc->id]);

        $member = $this->userWithRole('staff');
        $outsider = $this->userWithRole('staff');
        $this->teamWithGroup('TEAM-CC', $this->userWithRole('manager'), [$member], $ag);

        $this->assertTrue($member->accessibleCostCenterIds()->contains($cc->id));
        $this->assertTrue($outsider->accessibleCostCenterIds()->isEmpty());
    }

    public function test_budget_plan_detail_follows_team_access(): void
    {
        $po = $this->makeProject();
        $bp = $this->makeBudgetPlan($po);
        $member = $this->userWithRole('staff');
        $outsider = $this->userWithRole('staff');
        $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [$member], $this->groupWithProject($po, 'AG-A'));

        $this->actingAs($member)->getJson("/budget-plan/{$bp->id}/detail-json")->assertOk();
        $this->actingAs($outsider)->getJson("/budget-plan/{$bp->id}/detail-json")->assertForbidden();
    }

    public function test_with_project_access_scope_lists_individual_and_team_users(): void
    {
        $po = $this->makeProject();
        $viaTeam = $this->userWithRole('staff');
        $viaIndividu = $this->giveProjectAccess($this->userWithRole('staff'), $po);
        $lain = $this->userWithRole('staff');
        $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [$viaTeam], $this->groupWithProject($po, 'AG-A'));

        $ids = User::withProjectAccess($po->id)->pluck('id');

        $this->assertTrue($ids->contains($viaTeam->id));
        $this->assertTrue($ids->contains($viaIndividu->id));
        $this->assertFalse($ids->contains($lain->id));
    }

    /* ------------------------------------------------------- fungsi finance */

    public function test_finance_functions_see_every_po_but_generic_finance_does_not(): void
    {
        $po = $this->makeProject();

        foreach (['cost_control', 'collection', 'treasury', 'accounting'] as $role) {
            $this->giveMenuAccess($this->role($role), 'list-po');
            $this->poJson($this->userWithRole($role), $po)->assertOk();
        }
        $this->poJson($this->userWithRole('finance_manager'), $po)->assertOk();

        $this->poJson($this->userWithRole('finance'), $po)->assertForbidden();
    }

    public function test_finance_function_without_menu_cannot_open_po_detail_by_url(): void
    {
        $po = $this->makeProject();

        $this->poJson($this->userWithRole('treasury'), $po)->assertForbidden();
    }

    public function test_finance_function_without_menu_keeps_access_group_scope(): void
    {
        $po = $this->makeProject();
        $user = $this->giveProjectAccess($this->userWithRole('collection'), $po);

        // Tanpa menu list-po: bukan full scope, tapi PO di Access Group-nya tetap terbuka.
        $this->poJson($user, $po)->assertOk();
        $this->poJson($user, $this->makeProject())->assertForbidden();
    }

    public function test_finance_function_without_menu_cannot_open_po_list(): void
    {
        $this->actingAs($this->userWithRole('treasury'))->get('/project')->assertForbidden();
    }

    /* ---------------------------------------------------- form akses team */

    public function test_superadmin_sets_team_access_and_it_is_logged(): void
    {
        $po = $this->makeProject();
        $ag = $this->groupWithProject($po, 'AG-A');
        $leader = $this->userWithRole('manager');
        $team = $this->teamWithGroup('TEAM-A', $leader, []);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put("/team/{$team->id}", $this->formPayload($team, ['access_group_sync' => 1, 'access_group_ids' => [$ag->id]]))
            ->assertRedirect('/team');

        $this->assertTrue(AccessGroupDetail::where('team_id', $team->id)->where('access_group_id', $ag->id)->exists());
        $this->assertTrue(TeamLog::where('team_id', $team->id)->where('action', 'access')->exists());
        $this->assertTrue($leader->accessibleProjectIds()->contains($po->id));
    }

    public function test_leader_cannot_grant_team_access(): void
    {
        $po = $this->makeProject();
        $ag = $this->groupWithProject($po, 'AG-RAHASIA');
        $this->giveMenuAccess($this->role('manager'), 'team');
        $leader = $this->userWithRole('manager');
        $team = $this->teamWithGroup('TEAM-A', $leader, []);

        $this->actingAs($leader)
            ->put("/team/{$team->id}", $this->formPayload($team, ['access_group_sync' => 1, 'access_group_ids' => [$ag->id]]))
            ->assertRedirect('/team');

        $this->assertFalse(AccessGroupDetail::where('team_id', $team->id)->exists());
        $this->assertFalse($leader->accessibleProjectIds()->contains($po->id));
    }

    public function test_leader_cannot_add_member_to_team_with_access_group(): void
    {
        $po = $this->makeProject();
        $this->giveMenuAccess($this->role('manager'), 'team');
        $leader = $this->userWithRole('manager');
        $team = $this->teamWithGroup('TEAM-A', $leader, [], $this->groupWithProject($po, 'AG-A'));
        $luar = $this->userWithRole('staff');

        $payload = $this->formPayload($team);
        $payload['members'][] = ['user_id' => $luar->id, 'level' => 2, 'status' => 'ACTIVE'];

        $this->actingAs($leader)->put("/team/{$team->id}", $payload)->assertSessionHasErrors('members');

        $this->assertFalse(TeamMember::where('team_id', $team->id)->where('user_id', $luar->id)->exists());
        $this->assertFalse($luar->accessibleProjectIds()->contains($po->id));
    }

    public function test_leader_may_still_edit_position_on_team_with_access_group(): void
    {
        $po = $this->makeProject();
        $this->giveMenuAccess($this->role('manager'), 'team');
        $leader = $this->userWithRole('manager');
        $team = $this->teamWithGroup('TEAM-A', $leader, [], $this->groupWithProject($po, 'AG-A'));

        $payload = $this->formPayload($team);
        $payload['members'][0]['position'] = 'Supervisor';

        $this->actingAs($leader)->put("/team/{$team->id}", $payload)->assertRedirect('/team');
        $this->assertSame('Supervisor', TeamMember::where('team_id', $team->id)->where('user_id', $leader->id)->value('position'));
    }

    /* ------------------------------------------- lampiran (review MEDIUM-3) */

    private function poAttachment(Project $po, string $nama): string
    {
        $path = 'project_attachments/' . $nama;
        \Illuminate\Support\Facades\Storage::disk('secure')->put($path, 'x');
        \App\Models\ProjectAttachment::create([
            'project_id' => $po->id, 'type' => 'general', 'title' => 'UJI',
            'file_path' => $path, 'file_name' => $nama, 'file_type' => 'application/pdf',
        ]);

        return $path;
    }

    public function test_po_attachment_follows_po_scope(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $po = $this->makeProject();
        $path = $this->poAttachment($po, 'po-uji.pdf');

        $member = $this->userWithRole('staff');
        $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [$member], $this->groupWithProject($po, 'AG-A'));

        // Tanpa Access Group & tanpa menu PO/Invoice: ditolak (dulu boleh).
        $this->actingAs($this->userWithRole('staff'))->get('/storage/' . $path)->assertForbidden();
        // Lewat Access Group team: boleh.
        $this->actingAs($member)->get('/storage/' . $path)->assertOk();
        // Pemegang menu Invoice (bukan manager/head): boleh, sama dengan invoice-nya.
        $this->giveMenuAccess($this->role('finance'), 'invoice-cash-receipt');
        $this->actingAs($this->userWithRole('finance'))->get('/storage/' . $path)->assertOk();
        // Manager di luar Access Group walau punya menu Invoice: ditolak.
        $this->giveMenuAccess($this->role('manager'), 'invoice-cash-receipt');
        $this->actingAs($this->userWithRole('manager'))->get('/storage/' . $path)->assertForbidden();
    }

    public function test_budget_plan_attachment_follows_budget_plan_scope(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $po = $this->makeProject();
        $bp = $this->makeBudgetPlan($po);
        $path = 'budget_plan_attachments/bp-uji.pdf';
        \Illuminate\Support\Facades\Storage::disk('secure')->put($path, 'x');
        \App\Models\BudgetPlanAttachment::create([
            'budget_plan_id' => $bp->id, 'original_name' => 'bp-uji.pdf', 'stored_name' => 'bp-uji.pdf', 'path' => $path,
            'mime_type' => 'application/pdf', 'size' => 1,
        ]);

        $member = $this->userWithRole('staff');
        $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [$member], $this->groupWithProject($po, 'AG-A'));

        $this->actingAs($member)->get('/storage/' . $path)->assertOk();
        $this->actingAs($this->userWithRole('staff'))->get('/storage/' . $path)->assertForbidden();
    }

    public function test_request_without_sync_flag_keeps_team_access(): void
    {
        $po = $this->makeProject();
        $team = $this->teamWithGroup('TEAM-A', $this->userWithRole('manager'), [], $this->groupWithProject($po, 'AG-A'));

        $this->actingAs($this->userWithRole('superadmin'))
            ->put("/team/{$team->id}", $this->formPayload($team))
            ->assertRedirect('/team');

        $this->assertSame(1, AccessGroupDetail::where('team_id', $team->id)->count());
    }

    private function formPayload(Team $team, array $extra = []): array
    {
        return array_merge([
            'name' => $team->name, 'leader_id' => $team->leader_id, 'status' => 'ACTIVE',
            'members' => $team->activeMembers()->get()->map(fn ($m) => [
                'id' => $m->id, 'user_id' => $m->user_id, 'level' => $m->level, 'status' => 'ACTIVE',
            ])->values()->all(),
        ], $extra);
    }
}
