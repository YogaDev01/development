<?php

namespace Tests\Feature;

use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Modul Assignment: alur status PENDING -> READ -> ON_PROGRESS -> CLOSED,
 * CANCELLED, komentar, otorisasi, lampiran secure, dan log per-assignment.
 * Setiap user boleh menugaskan ke user mana pun (lihat App\Models\Assignment
 * untuk diagram alurnya).
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

    public function test_user_dapat_membuat_assignment_berstatus_pending(): void
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
        $this->assertSame('PENDING', $a->status);
        $this->assertSame($pemberi->id, $a->assigner_id);
        $this->assertSame($penerima->id, $a->assignee_id);
        $this->assertSame('HIGH', $a->priority);

        $this->assertDatabaseHas('assignment_logs', [
            'assignment_id' => $a->id,
            'action'        => 'create',
            'actor_id'      => $pemberi->id,
        ]);
    }

    public function test_membuka_detail_oleh_penerima_memicu_status_read(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
        ]);

        // Pemberi membuka detail — status TIDAK berubah (bukan penerima).
        $this->actingAs($pemberi)->get(route('assignment.logs', $a))->assertOk();
        $this->assertSame('PENDING', $a->fresh()->status);

        // Penerima membuka detail — status berubah ke READ + tercatat log 'read'.
        $this->actingAs($penerima)->get(route('assignment.logs', $a))->assertOk();
        $a->refresh();
        $this->assertSame('READ', $a->status);
        $this->assertNotNull($a->read_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'read']);

        // Dibuka lagi tidak mengulang transisi/log (tetap READ, tetap 1 baris 'read').
        $this->actingAs($penerima)->get(route('assignment.logs', $a))->assertOk();
        $this->assertSame('READ', $a->fresh()->status);
        $this->assertSame(1, $a->logs()->where('action', 'read')->count());
        $this->assertDatabaseCount('assignment_logs', 1); // hanya 'read' (assignment dibuat langsung, bukan via store())
    }

    public function test_hanya_penerima_yang_dapat_start_progress_dan_hanya_dari_status_read(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
        ]);

        // Masih PENDING -> ditolak.
        $this->actingAs($penerima)->post(route('assignment.start', $a))->assertSessionHas('error');
        $this->assertSame('PENDING', $a->fresh()->status);

        $a->update(['status' => 'READ', 'read_at' => now()]);

        // Pemberi tidak boleh.
        $this->actingAs($pemberi)->post(route('assignment.start', $a))->assertForbidden();

        // Penerima boleh.
        $this->actingAs($penerima)->post(route('assignment.start', $a))->assertRedirect();
        $a->refresh();
        $this->assertSame('ON_PROGRESS', $a->status);
        $this->assertNotNull($a->started_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'start_progress']);
    }

    public function test_penerima_pemberi_atau_superadmin_dapat_menandai_selesai(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'ON_PROGRESS',
        ]);

        $this->actingAs($penerima)->post(route('assignment.done', $a))->assertRedirect();

        $a->refresh();
        $this->assertSame('CLOSED', $a->status);
        $this->assertNotNull($a->done_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'close']);
    }

    public function test_hanya_pemberi_yang_dapat_cancel_dan_edit(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
        ]);

        // Penerima TIDAK boleh membatalkan / mengubah.
        $this->actingAs($penerima)->post(route('assignment.cancel', $a))->assertForbidden();
        $this->actingAs($penerima)->put(route('assignment.update', $a), [
            'title' => 'X', 'assignee_id' => $penerima->id, 'priority' => 'LOW',
        ])->assertForbidden();

        // Pemberi boleh.
        $this->actingAs($pemberi)->post(route('assignment.cancel', $a))->assertRedirect();
        $a->refresh();
        $this->assertSame('CANCELLED', $a->status);
        $this->assertNotNull($a->cancelled_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'cancel']);
    }

    public function test_pemberi_dapat_membuka_kembali_tugas_yang_closed_atau_cancelled(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'CANCELLED', 'cancelled_at' => now(),
        ]);

        $this->actingAs($pemberi)->post(route('assignment.reopen', $a))->assertRedirect();

        $a->refresh();
        $this->assertSame('PENDING', $a->status);
        $this->assertNull($a->cancelled_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'reopen']);
    }

    public function test_komentar_hanya_bisa_ditambahkan_selama_tugas_belum_closed_atau_cancelled(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $orangLain = $this->userWithRole('treasury');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'ON_PROGRESS',
        ]);

        // Orang luar tidak boleh berkomentar.
        $this->actingAs($orangLain)->post(route('assignment.comment', $a), ['body' => 'halo'])->assertForbidden();

        // Penerima boleh berkomentar selama masih terbuka.
        $this->actingAs($penerima)->post(route('assignment.comment', $a), ['body' => 'Sedang dikerjakan'])
            ->assertRedirect();
        $this->assertDatabaseHas('assignment_logs', [
            'assignment_id' => $a->id, 'action' => 'comment', 'notes' => 'Sedang dikerjakan',
        ]);

        // Setelah CLOSED, komentar dimatikan.
        $a->update(['status' => 'CLOSED', 'done_at' => now()]);
        $this->actingAs($pemberi)->post(route('assignment.comment', $a), ['body' => 'Terlambat'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('assignment_logs', ['notes' => 'Terlambat']);
    }

    public function test_detail_hanya_untuk_pemberi_atau_penerima(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $orangLain = $this->userWithRole('treasury');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
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
