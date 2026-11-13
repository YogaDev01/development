<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ManpowerRequest;
use App\Models\ManpowerRequestDetail;
use App\Models\Recruitment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Recruitment & Kandidat (HRIS) tahap 2b. Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class RecruitmentTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function hr()
    {
        foreach (['recruitment', 'candidate-pool'] as $m) {
            $this->giveMenuAccess($this->role('hr'), $m);
        }

        return $this->userWithRole('hr', ['username' => 'hrd', 'email' => 'hrd@example.test']);
    }

    private function openDetail(string $position = 'NOC Officer'): ManpowerRequestDetail
    {
        $requester = $this->userWithRole('staff', ['username' => 'pemohon', 'email' => 'pemohon@example.test']);
        $mp = ManpowerRequest::create([
            'request_no' => 'MP001', 'requester_id' => $requester->id, 'department_id' => Department::create(['name' => 'Ops'])->id,
            'request_date' => '2026-11-01', 'needed_date' => '2026-12-01', 'status' => 'APPROVED',
        ]);

        return $mp->details()->create(['position' => $position, 'grade' => 'EXPERIENCED', 'type' => 'NEW', 'status' => 'OPEN']);
    }

    private function candidate(string $name = 'Andi'): Candidate
    {
        return Candidate::create(['code' => 'CND-' . random_int(1000, 9999), 'name' => $name, 'phone' => '0812', 'birth_date' => '1996-04-12', 'gender' => 'L']);
    }

    public function test_recruitment_dimulai_di_screening_dan_membuat_kebutuhan_on_process(): void
    {
        $hr = $this->hr();
        $detail = $this->openDetail();
        $c = $this->candidate();

        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $c->id, 'detail_id' => $detail->id])->assertRedirect();

        $this->assertSame('SCREENING', Recruitment::firstOrFail()->stage);
        $this->assertSame('ON_PROCESS', $detail->fresh()->status);
    }

    public function test_kandidat_yang_sedang_diproses_tidak_bisa_dipakai_dua_kali(): void
    {
        $hr = $this->hr();
        $d1 = $this->openDetail();
        $c = $this->candidate();

        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $c->id, 'detail_id' => $d1->id]);
        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $c->id, 'detail_id' => $d1->id])->assertSessionHasErrors('candidate_id');
        $this->assertSame(1, Recruitment::count());
    }

    public function test_posisi_non_teknisi_melewati_training_dan_hire_membuat_karyawan_inactive(): void
    {
        $hr = $this->hr();
        $detail = $this->openDetail('NOC Officer');
        $c = $this->candidate();
        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $c->id, 'detail_id' => $detail->id]);
        $r = Recruitment::firstOrFail();

        $stages = [];
        foreach (range(1, 4) as $_) {
            $this->actingAs($hr)->post(route('recruitment.move', [$r, 'next']))->assertRedirect();
            $stages[] = $r->fresh()->stage;
        }

        $this->assertSame(['INTERVIEW HR', 'INTERVIEW USER', 'KONTRAK', 'ONBOARD'], $stages);
        $r->refresh();
        $this->assertSame('APPROVED', $r->status);
        $emp = Employee::findOrFail($c->fresh()->hired_employee_id);
        $this->assertSame('INACTIVE', $emp->status);
        $this->assertSame(0, (int) $emp->default_salary);
        $this->assertSame('DONE', $detail->fresh()->status);
        $this->assertSame($emp->id, (int) $detail->fresh()->filled_employee_id);
    }

    public function test_hire_mengembalikan_kandidat_lain_pada_kebutuhan_yang_sama(): void
    {
        $hr = $this->hr();
        $detail = $this->openDetail('Teknisi Lapangan');
        [$a, $b] = [$this->candidate('A'), $this->candidate('B')];
        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $a->id, 'detail_id' => $detail->id]);
        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $b->id, 'detail_id' => $detail->id]);
        $ra = Recruitment::where('candidate_id', $a->id)->firstOrFail();

        // Teknisi: SCREENING → HR → USER → TRAINING → KONTRAK → ONBOARD (5 langkah).
        foreach (range(1, 5) as $_) {
            $this->actingAs($hr)->post(route('recruitment.move', [$ra, 'next']));
        }

        $this->assertSame('APPROVED', $ra->fresh()->status);
        $this->assertSame('RETURNED', Recruitment::where('candidate_id', $b->id)->firstOrFail()->status);
    }

    public function test_reject_wajib_alasan_dan_membuka_lagi_kebutuhan(): void
    {
        $hr = $this->hr();
        $detail = $this->openDetail();
        $c = $this->candidate();
        $this->actingAs($hr)->post(route('recruitment.store'), ['candidate_id' => $c->id, 'detail_id' => $detail->id]);
        $r = Recruitment::firstOrFail();

        $this->actingAs($hr)->post(route('recruitment.move', [$r, 'reject']))->assertSessionHasErrors('note');
        $this->actingAs($hr)->post(route('recruitment.move', [$r, 'reject']), ['note' => 'Kurang pengalaman'])->assertRedirect();

        $this->assertSame('REJECTED', $r->fresh()->status);
        $this->assertSame('OPEN', $detail->fresh()->status);
    }

    public function test_non_hr_ditolak_walau_punya_menu(): void
    {
        $this->giveMenuAccess($this->role('staff'), 'recruitment');
        $this->giveMenuAccess($this->role('staff'), 'candidate-pool');
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->get(route('recruitment.index'))->assertForbidden();
        $this->actingAs($user)->get(route('candidates.index'))->assertForbidden();
    }
}
