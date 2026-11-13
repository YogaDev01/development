<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Todo List / Dashboard / Summary Operations. Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class OperationsOverviewTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_tiga_halaman_terbuka_untuk_pemegang_menu_operations_overview(): void
    {
        $this->giveMenuAccess($this->role('staff'), 'operations-overview', 'trouble-ticket');
        $user = $this->userWithRole('staff');

        foreach (['operations.todo', 'operations.dashboard', 'operations.summary'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_tanpa_menu_operations_overview_ditolak(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('operations.dashboard'))->assertForbidden();
    }

    public function test_tanpa_menu_trouble_ticket_tidak_ada_data_tiket(): void
    {
        $this->giveMenuAccess($this->role('staff'), 'operations-overview');
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->get(route('operations.dashboard'))->assertOk()->assertSee('belum memiliki akses ke modul Trouble Ticket');
        $this->actingAs($user)->get(route('operations.summary'))->assertOk()->assertDontSee('Rata-rata resolusi');
    }
}
