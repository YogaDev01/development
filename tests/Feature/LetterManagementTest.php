<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Letter Management (HRIS) — alur status, penomoran, dan approval dinamis
 * menurut struktur Team. Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class LetterManagementTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function makeLetter(int $userId, string $status = 'draft', string $type = 'internal'): Letter
    {
        $cat = LetterCategory::firstOrCreate(['code' => 'SK'], ['name' => 'Surat Keputusan']);
        $dept = Department::create(['name' => 'Operasional']);
        $letter = Letter::create([
            'category_id' => $cat->id, 'user_id' => $userId, 'type' => $type,
            'department_id' => $dept->id, 'letter_no' => '001/SK/INT/XI/2026', 'status' => $status,
        ]);
        $letter->detail()->create(['body' => 'Isi surat', 'items' => [], 'meta' => []]);

        return $letter;
    }

    private function actor(string $role = 'staff')
    {
        $this->giveMenuAccess($this->role($role), 'letter-management');

        return $this->userWithRole($role);
    }

    public function test_membuat_surat_menghasilkan_draft_bernomor_otomatis_dan_log(): void
    {
        $user = $this->actor();
        $cat  = LetterCategory::firstOrCreate(['code' => 'SK'], ['name' => 'Surat Keputusan']);
        $dept = Department::create(['name' => 'Operasional']);

        $this->actingAs($user)->post(route('letter.store'), [
            'category_id' => $cat->id, 'type' => 'internal', 'department_id' => $dept->id,
        ])->assertRedirect();

        $letter = Letter::firstOrFail();
        $this->assertSame('draft', $letter->status);
        $this->assertMatchesRegularExpression('#^001/SK/INT/[IVX]+/\d{4}$#', $letter->letter_no);
        $this->assertSame('created', $letter->logs()->first()->action);
    }

    public function test_nomor_berikutnya_tidak_memakai_ulang_nomor_yang_dihapus(): void
    {
        $user = $this->actor();
        $cat  = LetterCategory::firstOrCreate(['code' => 'SK'], ['name' => 'Surat Keputusan']);
        $dept = Department::create(['name' => 'Operasional']);
        $data = ['category_id' => $cat->id, 'type' => 'internal', 'department_id' => $dept->id];

        $this->actingAs($user)->post(route('letter.store'), $data);
        $pertama = Letter::firstOrFail();
        $this->actingAs($user)->post(route('letter.action', [$pertama, 'delete']));
        $this->actingAs($user)->post(route('letter.store'), $data);

        $this->assertStringStartsWith('002/', Letter::firstOrFail()->letter_no);
    }

    public function test_submit_ditolak_bila_isi_surat_kosong(): void
    {
        $user   = $this->actor();
        $letter = $this->makeLetter($user->id);
        $letter->detail->update(['body' => '']);

        $this->actingAs($user)->post(route('letter.action', [$letter, 'submit']))->assertSessionHas('error');
        $this->assertSame('draft', $letter->fresh()->status);
    }

    public function test_hanya_leader_team_di_atas_pembuat_yang_boleh_approve(): void
    {
        $creator = $this->actor();
        $atasan  = $this->userWithRole('staff', ['username' => 'atasan', 'email' => 'atasan@example.test']);
        $lain    = $this->userWithRole('staff', ['username' => 'lain', 'email' => 'lain@example.test']);
        $dept    = Department::create(['name' => 'Operasional']);

        $induk = Team::create(['code' => 'T-IND', 'name' => 'Induk', 'department_id' => $dept->id, 'leader_id' => $atasan->id, 'status' => 'ACTIVE']);
        $anak  = Team::create(['code' => 'T-ANK', 'name' => 'Anak', 'department_id' => $dept->id, 'parent_team_id' => $induk->id, 'leader_id' => $creator->id, 'status' => 'ACTIVE']);
        TeamMember::create(['team_id' => $anak->id, 'user_id' => $creator->id, 'status' => 'ACTIVE']);

        $letter = $this->makeLetter($creator->id, 'submitted');

        // Pembuat (leader tim anak) tidak boleh menyetujui suratnya sendiri.
        $this->actingAs($creator)->post(route('letter.action', [$letter, 'approve']))->assertForbidden();

        $this->giveMenuAccess($this->role('staff'), 'letter-management');
        // User lain di luar rantai atasan ditolak.
        $this->actingAs($lain)->post(route('letter.action', [$letter, 'approve']))->assertForbidden();
        $this->assertSame('submitted', $letter->fresh()->status);

        // Leader team induk = atasan pembuat -> boleh.
        $this->actingAs($atasan)->post(route('letter.action', [$letter, 'approve']))->assertRedirect();
        $this->assertSame('approved', $letter->fresh()->status);
    }

    public function test_reject_wajib_alasan_dan_isi_surat_tidak_bisa_diubah_setelah_submit(): void
    {
        $user = $this->actor('superadmin');
        $letter = $this->makeLetter($user->id, 'submitted');

        $this->actingAs($user)->post(route('letter.action', [$letter, 'reject']))->assertSessionHasErrors('note');
        $this->actingAs($user)->put(route('letter.detail.save', $letter), ['body' => 'ubah'])->assertForbidden();
        $this->assertSame('submitted', $letter->fresh()->status);
    }

    public function test_aksi_di_luar_alur_status_ditolak(): void
    {
        $user = $this->actor('superadmin');
        $letter = $this->makeLetter($user->id, 'draft');

        // Draft tidak boleh langsung approve / complete.
        $this->actingAs($user)->post(route('letter.action', [$letter, 'approve']))->assertForbidden();
        $this->actingAs($user)->post(route('letter.action', [$letter, 'complete']))->assertForbidden();
    }

    public function test_user_tanpa_menu_tidak_bisa_membuka_modul(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->get(route('letter.index'))->assertForbidden();
    }
}
