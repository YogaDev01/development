<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ManpowerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Todo List / Dashboard / Summary HRIS. Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class HrisOverviewTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_tiga_halaman_terbuka_untuk_pemegang_menu_hris_overview(): void
    {
        $this->giveMenuAccess($this->role('hr'), 'hris-overview', 'manpower-request', 'letter-management', 'recruitment', 'candidate-pool');
        $user = $this->userWithRole('hr');

        foreach (['hris.todo', 'hris.dashboard', 'hris.summary'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_tanpa_menu_hris_overview_ditolak(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('hris.dashboard'))->assertForbidden();
    }

    public function test_bagian_modul_tanpa_akses_tidak_ditampilkan(): void
    {
        // Hanya hak ringkasan, tanpa menu modul: tidak ada data Manpower yang bocor.
        $this->giveMenuAccess($this->role('staff'), 'hris-overview');
        $user = $this->userWithRole('staff');
        ManpowerRequest::create([
            'request_no' => 'MP001', 'requester_id' => $user->id, 'department_id' => Department::create(['name' => 'Ops'])->id,
            'request_date' => '2026-11-01', 'status' => 'SUBMITTED',
        ]);

        $this->actingAs($user)->get(route('hris.todo'))->assertOk()->assertDontSee('MP001');
        $this->actingAs($user)->get(route('hris.summary'))->assertOk()->assertDontSee('Manpower Planning');
    }

    public function test_todo_menampilkan_pengajuan_manpower_menunggu_persetujuan_untuk_hr(): void
    {
        $this->giveMenuAccess($this->role('hr'), 'hris-overview', 'manpower-request');
        $hr = $this->userWithRole('hr');
        ManpowerRequest::create([
            'request_no' => 'MP007', 'requester_id' => $hr->id, 'department_id' => Department::create(['name' => 'Ops'])->id,
            'request_date' => '2026-11-01', 'needed_date' => '2026-12-01', 'status' => 'SUBMITTED',
        ]);

        $this->actingAs($hr)->get(route('hris.todo'))->assertOk()->assertSee('MP007')->assertSee('menunggu persetujuan');
    }
}
