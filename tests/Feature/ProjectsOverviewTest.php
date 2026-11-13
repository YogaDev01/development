<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Todo List / Dashboard / Summary app Projects. Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class ProjectsOverviewTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_tiga_halaman_terbuka_untuk_pemegang_menu_projects_overview(): void
    {
        $this->giveMenuAccess($this->role('admin'), 'projects-overview');
        $user = $this->userWithRole('admin');
        $this->makeProject(['name' => 'Proyek Uji']);

        foreach (['projects.todo', 'projects.dashboard', 'projects.summary'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
        $this->actingAs($user)->get(route('projects.dashboard'))->assertSee('Total proyek');
    }

    public function test_tanpa_menu_projects_overview_ditolak(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('projects.dashboard'))->assertForbidden();
    }

    public function test_user_non_privileged_tanpa_akses_proyek_tidak_melihat_proyek_lain(): void
    {
        $this->giveMenuAccess($this->role('staff'), 'projects-overview');
        $user = $this->userWithRole('staff');
        $this->makeProject(['name' => 'Rahasia Tim Lain']);

        $this->actingAs($user)->get(route('projects.summary'))->assertOk()->assertDontSee('Rahasia Tim Lain');
    }
}
