<?php

namespace Tests\Feature;

use App\Models\ProjectPoBast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * No. PO = header, BAST = detail. Kolom agregat lama tetap benar, dan
 * showJson menghitung bast_total / restitusi / selisih Contract.
 */
class ProjectPoBastTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function submit($user, $project, $poNumber, string $noBast, int $nilai): void
    {
        $this->actingAs($user)->post(route('project.submit-bast.store', $project->id), [
            'po_numbers' => [
                $poNumber->id => [
                    'no_bast'      => $noBast,
                    'tanggal_bast' => '2026-10-10',
                    'nominal_bast' => (string) $nilai,
                    'attachments'  => [UploadedFile::fake()->create($noBast . '.pdf', 10, 'application/pdf')],
                ],
            ],
        ]);
    }

    public function test_submit_bast_dua_kali_membuat_dua_baris_dan_nilai_agregat_benar(): void
    {
        Storage::fake('secure');
        $project = $this->makeProject();
        $poNumber = $this->makePoNumber($project);
        $user = $this->userWithRole('superadmin');

        $this->submit($user, $project, $poNumber, 'BAST-001', 40_000_000);
        $this->submit($user, $project, $poNumber, 'BAST-002', 60_000_000);

        $this->assertSame(2, ProjectPoBast::where('project_po_number_id', $poNumber->id)->count());
        $this->assertSame(100_000_000, (int) $poNumber->fresh()->nilai_bast);
        $this->assertSame(2, $poNumber->fresh()->basts()->has('attachments')->count());
    }

    public function test_show_json_bast_total_per_no_po_dihitung_dari_relasi_basts(): void
    {
        $project = $this->makeProject();
        // Kolom agregat sengaja berbeda untuk membuktikan angka berasal dari relasi basts.
        $poNumber = $this->makePoNumber($project, ['nilai_bast' => 999]);
        foreach ([['B-1', 40_000_000], ['B-2', 25_000_000]] as [$no, $nilai]) {
            ProjectPoBast::create([
                'project_po_number_id' => $poNumber->id,
                'no_bast'              => $no,
                'tanggal_bast'         => '2026-10-10',
                'nilai_bast'           => $nilai,
            ]);
        }
        $kosong = $this->makePoNumber($project);

        $json = $this->actingAs($this->userWithRole('superadmin'))
            ->getJson(route('project.show-json', $project->id))
            ->assertOk()
            ->json('po_numbers');

        $byId = collect($json)->keyBy('id');
        $this->assertSame(65_000_000, $byId[$poNumber->id]['bast_total']);
        $this->assertSame('65.000.000', $byId[$poNumber->id]['bast_total_formatted']);
        $this->assertCount(2, $byId[$poNumber->id]['basts']);
        $this->assertSame(0, $byId[$kosong->id]['bast_total']);
    }

    public function test_show_json_menghitung_bast_total_restitusi_dan_selisih_contract(): void
    {
        $project = $this->makeProject(['contract' => 150_000_000, 'restitusi' => 10_000_000]);
        $this->makePoNumber($project, ['nilai_bast' => 100_000_000]);
        $this->makePoNumber($project, ['nilai_bast' => 30_000_000]);
        $user = $this->userWithRole('superadmin');

        $json = $this->actingAs($user)
            ->getJson(route('project.show-json', $project->id))
            ->assertOk()
            ->json();

        $this->assertSame(130_000_000, $json['bast_total']);
        $this->assertSame(10_000_000, $json['restitusi']);
        // 150jt - (130jt + 10jt)
        $this->assertSame(10_000_000, $json['contract_selisih']);
    }
}
