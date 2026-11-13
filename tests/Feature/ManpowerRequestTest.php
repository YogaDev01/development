<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ManpowerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Manpower Request (HRIS) tahap 2a. Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class ManpowerRequestTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function userWith(string $role, string $name)
    {
        $this->giveMenuAccess($this->role($role), 'manpower-request');

        return $this->userWithRole($role, ['username' => $name, 'email' => "$name@example.test"]);
    }

    private function payload(array $rows = null, string $mode = 'submit'): array
    {
        return [
            'mode' => $mode,
            'department_id' => Department::firstOrCreate(['name' => 'Operasional'])->id,
            'needed_date' => '2026-12-01',
            'note' => 'Butuh tambahan NOC',
            'rows' => $rows ?? [['position' => 'NOC Officer', 'grade' => 'EXPERIENCED', 'type' => 'NEW']],
        ];
    }

    public function test_pengajuan_baru_bernomor_MP_dan_berstatus_submitted(): void
    {
        $user = $this->userWith('staff', 'pemohon');

        $this->actingAs($user)->post(route('manpower.store'), $this->payload())->assertRedirect();

        $mp = ManpowerRequest::with('details')->firstOrFail();
        $this->assertSame('MP001', $mp->request_no);
        $this->assertSame('SUBMITTED', $mp->status);
        $this->assertCount(1, $mp->details);
    }

    public function test_baris_replace_wajib_memilih_karyawan_saat_diajukan(): void
    {
        $user = $this->userWith('staff', 'pemohon');
        $rows = [['position' => 'CSO', 'grade' => 'SENIOR', 'type' => 'REPLACE']];

        $this->actingAs($user)->post(route('manpower.store'), $this->payload($rows))->assertSessionHasErrors('rows.0.replaced_id');
        $this->assertSame(0, ManpowerRequest::count());

        // Sebagai draft boleh belum dipilih.
        $this->actingAs($user)->post(route('manpower.store'), $this->payload($rows, 'draft'))->assertRedirect();
        $this->assertSame('DRAFT', ManpowerRequest::firstOrFail()->status);
    }

    public function test_karyawan_yang_sama_tidak_bisa_digantikan_dua_pengajuan(): void
    {
        $user = $this->userWith('staff', 'pemohon');
        $emp  = Employee::create(['name' => 'Dedi Kurniawan']);
        $rows = [['position' => 'Supervisor Operation', 'grade' => 'SENIOR', 'type' => 'REPLACE', 'replaced_id' => $emp->id]];

        $this->actingAs($user)->post(route('manpower.store'), $this->payload($rows))->assertRedirect();
        $this->actingAs($user)->post(route('manpower.store'), $this->payload($rows))->assertSessionHasErrors('rows.0.replaced_id');
        $this->assertSame(1, ManpowerRequest::count());
    }

    public function test_hanya_hr_yang_boleh_approve_dan_pemohon_hanya_melihat_miliknya(): void
    {
        $pemohon = $this->userWith('staff', 'pemohon');
        $lain    = $this->userWith('staff', 'lain');
        $hr      = $this->userWith('hr', 'hrd');

        $this->actingAs($pemohon)->post(route('manpower.store'), $this->payload());
        $mp = ManpowerRequest::firstOrFail();

        $this->actingAs($pemohon)->post(route('manpower.action', [$mp, 'approve']))->assertForbidden();
        $this->actingAs($lain)->get(route('manpower.show', $mp))->assertNotFound();

        $this->actingAs($hr)->post(route('manpower.action', [$mp, 'approve']))->assertRedirect();
        $mp->refresh();
        $this->assertSame('APPROVED', $mp->status);
        $this->assertSame($hr->id, (int) $mp->approved_by);
    }

    public function test_reject_wajib_alasan_dan_pengajuan_ditolak_bisa_diedit_lalu_diajukan_ulang(): void
    {
        $pemohon = $this->userWith('staff', 'pemohon');
        $hr      = $this->userWith('hr', 'hrd');
        $this->actingAs($pemohon)->post(route('manpower.store'), $this->payload());
        $mp = ManpowerRequest::firstOrFail();

        $this->actingAs($hr)->post(route('manpower.action', [$mp, 'reject']))->assertSessionHasErrors('note');
        $this->actingAs($hr)->post(route('manpower.action', [$mp, 'reject']), ['note' => 'Kurang detail'])->assertRedirect();
        $this->assertSame('REJECTED', $mp->fresh()->status);

        $this->actingAs($pemohon)->put(route('manpower.update', $mp), $this->payload())->assertRedirect();
        $this->assertSame('SUBMITTED', $mp->fresh()->status);
    }

    public function test_pengajuan_yang_sudah_disetujui_tidak_bisa_diedit(): void
    {
        $pemohon = $this->userWith('staff', 'pemohon');
        $hr      = $this->userWith('hr', 'hrd');
        $this->actingAs($pemohon)->post(route('manpower.store'), $this->payload());
        $mp = ManpowerRequest::firstOrFail();
        $this->actingAs($hr)->post(route('manpower.action', [$mp, 'approve']));

        $this->actingAs($pemohon)->put(route('manpower.update', $mp), $this->payload())->assertForbidden();
    }

    public function test_user_tanpa_menu_ditolak(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->get(route('manpower.index'))->assertForbidden();
    }
}
