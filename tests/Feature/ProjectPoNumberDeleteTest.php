<?php

namespace Tests\Feature;

use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Hapus No. PO: ditolak bila sudah punya BAST (walau status RELEASED);
 * tanpa BAST, baris dan file lampirannya terhapus.
 */
class ProjectPoNumberDeleteTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function route(ProjectPoNumber $po): string
    {
        return route('project.po-number.destroy', $po->id);
    }

    public function test_hapus_no_po_released_yang_punya_bast_ditolak(): void
    {
        Storage::fake('secure');
        Storage::disk('secure')->put('project_attachments/bast.pdf', 'x');

        $project = $this->makeProject();
        $po = $this->makePoNumber($project, ['status' => 'RELEASED']);
        $bast = ProjectPoBast::create([
            'project_po_number_id' => $po->id,
            'no_bast'              => 'BAST-001',
            'tanggal_bast'         => '2026-10-10',
            'nilai_bast'           => 1_000_000,
        ]);
        $po->attachments()->create([
            'project_id' => $project->id, 'project_po_bast_id' => $bast->id, 'type' => 'po_number_bast',
            'title' => 'BAST-001', 'file_path' => 'project_attachments/bast.pdf', 'file_name' => 'bast.pdf',
            'file_type' => 'application/pdf',
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete($this->route($po))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('project_po_numbers', ['id' => $po->id]);
        $this->assertDatabaseHas('project_po_basts', ['id' => $bast->id]);
        Storage::disk('secure')->assertExists('project_attachments/bast.pdf');
    }

    public function test_hapus_no_po_released_dengan_bast_soft_deleted_tetap_ditolak(): void
    {
        $project = $this->makeProject();
        $po = $this->makePoNumber($project, ['status' => 'RELEASED']);
        $bast = ProjectPoBast::create([
            'project_po_number_id' => $po->id,
            'no_bast'              => 'BAST-001',
            'tanggal_bast'         => '2026-10-10',
            'nilai_bast'           => 1_000_000,
        ]);
        $bast->delete(); // soft delete

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete($this->route($po))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('project_po_numbers', ['id' => $po->id]);
        $this->assertSoftDeleted('project_po_basts', ['id' => $bast->id]);
    }

    public function test_hapus_no_po_released_tanpa_bast_berhasil_beserta_file(): void
    {
        Storage::fake('secure');
        Storage::disk('secure')->put('project_attachments/po.pdf', 'x');

        $project = $this->makeProject();
        $po = $this->makePoNumber($project, ['status' => 'RELEASED']);
        $po->attachments()->create([
            'project_id' => $project->id, 'type' => 'po_number', 'title' => 'PO',
            'file_path' => 'project_attachments/po.pdf', 'file_name' => 'po.pdf', 'file_type' => 'application/pdf',
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete($this->route($po))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('project_po_numbers', ['id' => $po->id]);
        Storage::disk('secure')->assertMissing('project_attachments/po.pdf');
    }
}
