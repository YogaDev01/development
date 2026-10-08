<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Modul Assignment: pembuatan, mark done, cancel, otorisasi, lampiran secure,
 * dan log per-assignment. Setiap user boleh menugaskan ke user mana pun.
 */
class AssignmentTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_halaman_assignment_dapat_dibuka(): void
    {
        $user = $this->userWithRole('manager');
        $this->userWithRole('finance'); // kandidat penerima di dropdown

        $this->actingAs($user)->get('/assignment')
            ->assertOk()
            ->assertSee('Buat Tugas')
            ->assertSee('Tugaskan ke');
    }

    public function test_user_dapat_membuat_assignment_dan_tercatat_log(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');

        $this->actingAs($pemberi)->post('/assignment', [
            'title'       => 'Siapkan laporan kas',
            'description' => 'Rekap bulan ini',
            'assignee_id' => $penerima->id,
            'priority'    => 'HIGH',
            'due_date'    => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('assignment.index'));

        $a = Assignment::first();
        $this->assertNotNull($a);
        $this->assertSame('OPEN', $a->status);
        $this->assertSame($pemberi->id, $a->assigner_id);
        $this->assertSame($penerima->id, $a->assignee_id);
        $this->assertSame('HIGH', $a->priority);

        $this->assertDatabaseHas('assignment_logs', [
            'assignment_id' => $a->id,
            'action'        => 'create',
            'actor_id'      => $pemberi->id,
        ]);
    }

    public function test_penerima_dapat_menandai_selesai(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'OPEN',
        ]);

        $this->actingAs($penerima)->post("/assignment/{$a->id}/done")->assertRedirect();

        $this->assertSame('DONE', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->done_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'done']);
    }

    public function test_hanya_pemberi_yang_dapat_cancel_dan_edit(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'OPEN',
        ]);

        // Penerima TIDAK boleh membatalkan / mengubah.
        $this->actingAs($penerima)->post("/assignment/{$a->id}/cancel")->assertForbidden();
        $this->actingAs($penerima)->put("/assignment/{$a->id}", [
            'title' => 'X', 'assignee_id' => $penerima->id, 'priority' => 'LOW',
        ])->assertForbidden();

        // Pemberi boleh.
        $this->actingAs($pemberi)->post("/assignment/{$a->id}/cancel")->assertRedirect();
        $this->assertSame('CANCELLED', $a->fresh()->status);
    }

    public function test_detail_hanya_untuk_pemberi_atau_penerima(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $orangLain = $this->userWithRole('treasury');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'OPEN',
        ]);

        $this->actingAs($penerima)->get(route('assignment.logs', $a))->assertOk();
        $this->actingAs($orangLain)->get(route('assignment.logs', $a))->assertForbidden();
    }

    public function test_lampiran_disimpan_di_disk_secure(): void
    {
        Storage::fake('secure');

        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');

        $this->actingAs($pemberi)->post('/assignment', [
            'title'       => 'Dengan lampiran',
            'assignee_id' => $penerima->id,
            'priority'    => 'MEDIUM',
            'attachments' => [UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('assignment.index'));

        $att = Assignment::first()->attachments()->first();
        $this->assertNotNull($att);
        $this->assertStringStartsWith('assignment_attachments/', $att->path);
        Storage::disk('secure')->assertExists($att->path);
    }

    public function test_tidak_bisa_menugaskan_ke_user_nonaktif(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $nonaktif = $this->userWithRole('finance', ['status' => 'INACTIVE']);

        $this->actingAs($pemberi)->post('/assignment', [
            'title' => 'T', 'assignee_id' => $nonaktif->id, 'priority' => 'MEDIUM',
        ])->assertSessionHas('error');

        $this->assertSame(0, Assignment::count());
    }
}
