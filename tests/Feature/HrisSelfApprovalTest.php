<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\ManpowerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Manpower Request & Letter: pemohon/pembuat tidak boleh menyetujui atau
 * menolak pengajuannya sendiri, kecuali Superadmin (keputusan 2026-11-13).
 * Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class HrisSelfApprovalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function payload(): array
    {
        return [
            'mode' => 'submit',
            'department_id' => Department::firstOrCreate(['name' => 'Operasional'])->id,
            'needed_date' => '2026-12-01',
            'note' => 'Butuh tambahan NOC',
            'rows' => [['position' => 'NOC Officer', 'grade' => 'EXPERIENCED', 'type' => 'NEW']],
        ];
    }

    public function test_hr_tidak_bisa_approve_atau_reject_manpower_miliknya_sendiri(): void
    {
        $this->giveMenuAccess($this->role('hr'), 'manpower-request');
        $hr = $this->userWithRole('hr');

        $this->actingAs($hr)->post(route('manpower.store'), $this->payload())->assertRedirect();
        $mp = ManpowerRequest::firstOrFail();

        $this->actingAs($hr)->post(route('manpower.action', [$mp, 'approve']))->assertSessionHas('error');
        $this->actingAs($hr)->post(route('manpower.action', [$mp, 'reject']), ['note' => 'x'])->assertSessionHas('error');
        $this->assertSame('SUBMITTED', $mp->fresh()->status);
        $this->assertTrue($mp->fresh()->isSelfApprovalBlockedFor($hr));
    }

    public function test_superadmin_boleh_approve_manpower_miliknya_sendiri(): void
    {
        $super = $this->userWithRole('superadmin');

        $this->actingAs($super)->post(route('manpower.store'), $this->payload())->assertRedirect();
        $mp = ManpowerRequest::firstOrFail();

        $this->actingAs($super)->post(route('manpower.action', [$mp, 'approve']))->assertRedirect()->assertSessionMissing('error');
        $this->assertSame('APPROVED', $mp->fresh()->status);
    }

    private function letterMilik(int $userId): Letter
    {
        $cat = LetterCategory::firstOrCreate(['code' => 'SK'], ['name' => 'Surat Keputusan']);
        $letter = Letter::create([
            'category_id' => $cat->id, 'user_id' => $userId, 'type' => 'internal',
            'department_id' => Department::firstOrCreate(['name' => 'Operasional'])->id,
            'letter_no' => '001/SK/INT/XI/2026', 'status' => 'submitted',
        ]);
        $letter->detail()->create(['body' => 'Isi surat', 'items' => [], 'meta' => []]);

        return $letter;
    }

    public function test_pembuat_surat_bukan_superadmin_ditolak_approve_sendiri(): void
    {
        $this->giveMenuAccess($this->role('hr'), 'letter-management');
        $hr     = $this->userWithRole('hr');
        $letter = $this->letterMilik($hr->id);

        $this->actingAs($hr)->post(route('letter.action', [$letter, 'approve']))->assertForbidden();
        $this->assertSame('submitted', $letter->fresh()->status);
    }

    public function test_superadmin_boleh_approve_surat_miliknya_sendiri(): void
    {
        $super  = $this->userWithRole('superadmin');
        $letter = $this->letterMilik($super->id);

        $this->actingAs($super)->post(route('letter.action', [$letter, 'approve']))->assertRedirect();
        $this->assertSame('approved', $letter->fresh()->status);
    }
}
