<?php

namespace Tests\Feature;

use App\Models\ProjectDetail;
use App\Models\ProjectMonitoringLog;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Milestone PO (project_details) saat form Edit PO disimpan.
 *
 * Regresi yang dijaga: dulu syncDetails() menjalankan details()->delete() lalu
 * membuat ulang semua baris, sehingga menyimpan perubahan Nilai Kontrak saja
 * sudah cukup untuk menerbitkan id milestone baru. Akibatnya
 * project_monitoring_logs.project_detail_id (nullOnDelete) jadi NULL dan
 * status/weight/actual milestone kembali ke nilai default.
 *
 * Inti uji ini: EDIT PO HANYA MENGUBAH DATA, BUKAN ID.
 */
class ProjectMilestoneSyncTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function stage(string $name): Stage
    {
        static $n = 0;

        return Stage::firstOrCreate(['name' => $name], [
            'code'   => 'ST' . str_pad((string) (++$n), 3, '0', STR_PAD_LEFT),
            'status' => 'ACTIVE',
        ]);
    }

    /** Payload form Edit PO yang sah; `details` diisi pemanggil. */
    private function payload(\App\Models\Project $project, array $details): array
    {
        return [
            'name'        => $project->name,
            'customer_id' => $project->customer_id,
            'manager_id'  => $project->manager_id,
            'contract'    => '2.000.000.000',
            'details'     => $details,
        ];
    }

    public function test_edit_po_tidak_mengubah_id_milestone_dan_tidak_memutus_log(): void
    {
        $project = $this->makeProject();
        $cluster = $this->makeCluster();

        $detail = ProjectDetail::create([
            'project_id'   => $project->id,
            'cluster_id'   => $cluster->id,
            'stage_id'     => $this->stage('BAST')->id,
            'plan_start'   => '2026-01-01',
            'plan_end'     => '2026-01-31',
            'actual_start' => '2026-01-05',
            'weight'       => 40,
            'status'       => 'ON_GOING',
        ]);

        $log = ProjectMonitoringLog::create([
            'project_id'        => $project->id,
            'project_detail_id' => $detail->id,
            'type'              => 'MESSAGE',
            'message'           => 'Progres BAST 50%',
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/project/' . $project->id, $this->payload($project, [
                [
                    'id'         => $detail->id,
                    'cluster_id' => $cluster->id,
                    'stage_id'   => $detail->stage_id,
                    'plan_start' => '2026-02-01',
                    'plan_end'   => '2026-02-28',
                ],
            ]))
            ->assertRedirect('/project');

        $this->assertSame(1, $project->details()->count());

        $detail->refresh();

        // Identitas baris tidak berubah -> log tetap menempel.
        $this->assertSame($detail->id, $log->fresh()->project_detail_id);

        // Kolom milik form ikut berubah...
        $this->assertSame('2026-02-01', $detail->plan_start->format('Y-m-d'));
        $this->assertSame('2026-02-28', $detail->plan_end->format('Y-m-d'));

        // ...tapi kolom milik Project Monitoring tidak disentuh.
        $this->assertSame('ON_GOING', $detail->status);
        $this->assertSame(40, $detail->weight);
        $this->assertSame('2026-01-05', $detail->actual_start->format('Y-m-d'));
    }

    public function test_baris_tanpa_id_dibuat_baru_tanpa_mengganggu_baris_lama(): void
    {
        $project = $this->makeProject();
        $cluster = $this->makeCluster();

        $lama = ProjectDetail::create([
            'project_id' => $project->id,
            'cluster_id' => $cluster->id,
            'stage_id'   => $this->stage('Invoice')->id,
            'weight'     => 60,
            'status'     => 'DONE',
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/project/' . $project->id, $this->payload($project, [
                ['id' => $lama->id, 'cluster_id' => $cluster->id, 'stage_id' => $lama->stage_id],
                ['cluster_id' => $cluster->id, 'stage_id' => $this->stage('Payment')->id],
            ]))
            ->assertRedirect('/project');

        $this->assertSame(2, $project->details()->count());
        $this->assertSame('DONE', $lama->fresh()->status);
        $this->assertSame(60, $lama->fresh()->weight);
    }

    public function test_baris_yang_dibuang_dari_form_ikut_terhapus(): void
    {
        $project = $this->makeProject();
        $cluster = $this->makeCluster();

        $tetap  = ProjectDetail::create([
            'project_id' => $project->id,
            'cluster_id' => $cluster->id,
            'stage_id'   => $this->stage('SPMK')->id,
        ]);
        $dibuang = ProjectDetail::create([
            'project_id' => $project->id,
            'cluster_id' => $cluster->id,
            'stage_id'   => $this->stage('Financing')->id,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/project/' . $project->id, $this->payload($project, [
                ['id' => $tetap->id, 'cluster_id' => $cluster->id, 'stage_id' => $tetap->stage_id],
            ]))
            ->assertRedirect('/project');

        $this->assertDatabaseHas('project_details', ['id' => $tetap->id]);
        $this->assertDatabaseMissing('project_details', ['id' => $dibuang->id]);
    }

    public function test_id_milestone_milik_po_lain_tidak_bisa_dibajak(): void
    {
        $project = $this->makeProject();
        $lain    = $this->makeProject();
        $cluster = $this->makeCluster();

        $milikLain = ProjectDetail::create([
            'project_id' => $lain->id,
            'cluster_id' => $cluster->id,
            'stage_id'   => $this->stage('PO Received')->id,
            'weight'     => 25,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put('/project/' . $project->id, $this->payload($project, [
                ['id' => $milikLain->id, 'cluster_id' => $cluster->id, 'stage_id' => $milikLain->stage_id],
            ]))
            ->assertRedirect('/project');

        // Baris PO lain tidak tersentuh; di PO ini lahir baris baru.
        $milikLain->refresh();
        $this->assertSame($lain->id, $milikLain->project_id);
        $this->assertSame(25, $milikLain->weight);
        $this->assertSame(1, $project->details()->count());
        $this->assertNotSame($milikLain->id, $project->details()->first()->id);
    }
}
