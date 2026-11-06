<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Baseline Fase 0 — Centralized Security & Access Control
 * (docs/plans/2026-11-06-security-access-control.md, docs/security/access-matrix.md).
 *
 * Uji KARAKTERISASI: merekam perilaku akses SAAT INI, bukan perilaku ideal.
 * Mesin permission/data scope yang dibangun di fase berikutnya wajib
 * menghasilkan respons yang sama persis (parity). Uji bertanda TEMUAN
 * merekam celah yang sudah dilaporkan ke pemilik dan BELUM diputuskan;
 * bila pemilik memutuskan menutupnya, ekspektasinya diubah dalam commit
 * perbaikan itu, bukan diam-diam.
 */
class AccessBaselineTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    /* ---------------------------------------------- autentikasi & sesi */

    public function test_tamu_diarahkan_ke_login_untuk_endpoint_data(): void
    {
        $this->get('/budget-request/1/detail-json')->assertRedirect('/login');
        $this->get('/api/budget-plan/1/details')->assertRedirect('/login');
        $this->get('/storage/budget_requests/x.pdf')->assertRedirect('/login');
    }

    public function test_user_nonaktif_kehilangan_sesi_aktif(): void
    {
        $user = $this->userWithRole('manager', ['status' => 'INACTIVE']);

        $this->actingAs($user)->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    /* ---------------------------------------------- gerbang role & menu */

    public function test_route_superadmin_menolak_role_lain(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get('/role')->assertForbidden();
    }

    public function test_route_menu_menolak_role_tanpa_menu(): void
    {
        $this->actingAs($this->userWithRole('manager'))->get('/user')->assertForbidden();
    }

    /* ---------------------------------------------- visibilitas data (IDOR) */

    public function test_detail_budget_plan_proyek_lain_ditolak(): void
    {
        $bp = $this->makeBudgetPlan();

        $this->actingAs($this->userWithRole('manager'))
            ->get('/budget-plan/' . $bp->id . '/detail-json')
            ->assertForbidden();

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/budget-plan/' . $bp->id . '/detail-json')
            ->assertOk();
    }

    public function test_export_budget_plan_proyek_lain_ditolak(): void
    {
        $bp = $this->makeBudgetPlan();

        $this->actingAs($this->userWithRole('manager'))
            ->get('/budget-plan/' . $bp->id . '/export-budget')
            ->assertForbidden();
    }

    public function test_detail_po_di_luar_access_group_ditolak(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->userWithRole('manager'))
            ->get('/project/' . $project->id . '/detail-json')
            ->assertForbidden();
    }

    public function test_project_monitoring_di_luar_access_group_tidak_ditemukan(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->userWithRole('manager'))
            ->get('/project-monitoring/' . $project->id)
            ->assertNotFound();
    }

    public function test_akses_po_lewat_access_group_diizinkan(): void
    {
        $project = $this->makeProject();
        $anggota = $this->giveProjectAccess($this->userWithRole('manager'), $project);

        $this->actingAs($anggota)
            ->get('/project/' . $project->id . '/detail-json')
            ->assertOk();
    }

    /* ---------------------------------------------- TEMUAN (belum diputuskan) */

    /**
     * TEMUAN F-3: /api/budget-plan/{id}/details hanya digerbang `auth`; tidak
     * memeriksa visibilitas Budget Plan. User di luar Access Group mendapat
     * daftar sub-item beserta nilai, terpakai dan sisa.
     */
    public function test_temuan_rincian_budget_plan_tidak_tersaring(): void
    {
        $bp = $this->makeBudgetPlan();

        $this->actingAs($this->userWithRole('manager'))
            ->get('/api/budget-plan/' . $bp->id . '/details')
            ->assertOk();
    }

    /**
     * TEMUAN F-4: /budget-request/check-nob mengembalikan sisa & nilai budget
     * Budget Plan mana pun berdasarkan id, tanpa pemeriksaan visibilitas.
     */
    public function test_temuan_check_nob_membuka_nilai_budget_plan_lain(): void
    {
        $bp = $this->makeBudgetPlan();

        $this->actingAs($this->userWithRole('manager'))
            ->post('/budget-request/check-nob', ['budget_plan_id' => $bp->id, 'amount' => 1])
            ->assertOk()
            ->assertJsonStructure(['is_nob', 'remaining', 'budget_amount']);
    }
}
