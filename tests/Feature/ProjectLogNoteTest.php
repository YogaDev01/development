<?php

namespace Tests\Feature;

use App\Models\ProjectLogNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/** Tab Log Purchase Order: catatan gaya chat (tabel project_log_notes). */
class ProjectLogNoteTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_catatan_tersimpan_dan_muncul_di_detail_json(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/project/' . $project->id . '/log-note', ['notes' => 'Menunggu dokumen PO'])
            ->assertOk()->assertJsonPath('log.notes', 'Menunggu dokumen PO');

        $this->assertSame(1, ProjectLogNote::where('project_id', $project->id)->count());

        $logs = $this->getJson('/project/' . $project->id . '/detail-json')->assertOk()->json('logs');
        $this->assertContains('note', array_column($logs, 'kind'));
    }

    public function test_catatan_ditolak_untuk_po_closed(): void
    {
        $project = $this->makeProject(['status' => 'CLOSED']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/project/' . $project->id . '/log-note', ['notes' => 'Terlambat'])
            ->assertStatus(422);

        $this->assertSame(0, ProjectLogNote::count());
    }

    public function test_catatan_wajib_diisi(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->userWithRole('superadmin'))
            ->postJson('/project/' . $project->id . '/log-note', ['notes' => ''])
            ->assertStatus(422);
    }
}
