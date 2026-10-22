<?php

namespace Tests\Feature;

use App\Http\Controllers\TodoListController;
use App\Models\Account;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Budget Plan yang dibuat dari form PO ("bundled", budget_plans.via_po_approval):
 * statusnya digerakkan approval PO, bukan workflow Budget Plan.
 *
 *   PO submit  -> BP DRAFT    -> CHECKED   (tahap Head-check BP dilewati)
 *   PO verify  -> BP CHECKED  -> VERIFIED
 *   PO approve -> BP VERIFIED -> ACTIVE    (semua hasil approve, termasuk Early Start)
 *   PO revise  -> BP CHECKED/VERIFIED -> DRAFT
 *
 * Budget opsional; tanpa budget perilaku PO tidak berubah.
 */
class ProjectBudgetBundleTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function akun(): Account
    {
        return Account::create(['name' => 'Material Uji', 'category' => 'Variable Cost', 'status' => 'ACTIVE']);
    }

    private function payloadBudget(array $override = []): array
    {
        return array_replace_recursive([
            'description' => 'budget uji',
            'notes'       => 'catatan',
            'details'     => [
                ['account_id' => $this->akun()->id, 'deskripsi' => 'Material', 'amount' => '1.500.000', 'qty' => '2', 'unit' => 'pcs'],
            ],
        ], $override);
    }

    /** PO + BP bundled (dengan 1 detail) pada status tertentu. */
    private function poDenganBp(string $statusPo, string $statusBp, ?int $inputBy = null): array
    {
        $po = $this->makeProject(['status' => $statusPo, 'contract' => 0, 'value' => 0]);
        $bp = $this->makeBudgetPlan($po, [
            'status'          => $statusBp,
            'via_po_approval' => true,
            'amount'          => 3_000_000,
            'input_by'        => $inputBy ?? $po->manager_id,
        ]);
        BudgetPlanDetail::create([
            'budget_plan_id' => $bp->id, 'deskripsi' => 'Material', 'amount' => 1_500_000, 'qty' => 2, 'total' => 3_000_000,
        ]);

        return [$po, $bp];
    }

    /* ---------------------------------------------------------- store */

    public function test_store_tanpa_budget_tidak_membuat_budget_plan(): void
    {
        $head = $this->userWithRole('head');

        $this->actingAs($head)->post('/project', ['name' => 'Proyek Tanpa Budget'])->assertRedirect('/project');

        $this->assertSame(1, Project::count());
        $this->assertSame(0, BudgetPlan::count());
    }

    public function test_store_dengan_budget_membuat_bp_draft_bundled_di_transaksi_yang_sama(): void
    {
        $head = $this->userWithRole('head');

        $this->actingAs($head)->post('/project', [
            'name'   => 'Proyek Dengan Budget',
            'budget' => $this->payloadBudget(),
        ])->assertRedirect('/project');

        $po = Project::firstOrFail();
        $bp = BudgetPlan::firstOrFail();

        $this->assertSame($po->id, (int) $bp->project_id);
        $this->assertNull($bp->cost_center_id);
        $this->assertSame('DRAFT', $bp->status);
        $this->assertTrue($bp->isBundled());
        $this->assertSame($head->id, (int) $bp->input_by, 'Head menjadi input_by BP');
        $this->assertSame('BUDGET UJI', $bp->description);
        $this->assertSame(3_000_000, (int) $bp->amount, 'amount = harga satuan x qty');
        $this->assertSame(1, $bp->details()->count());
        $this->assertStringStartsWith('BD-', $bp->code);
        $this->assertSame('created', $bp->logs()->reorder()->orderBy('id')->first()->action);
    }

    public function test_store_budget_setengah_terisi_ditolak_dan_po_tidak_dibuat(): void
    {
        $head = $this->userWithRole('head');

        $this->actingAs($head)->post('/project', [
            'name'   => 'Proyek Budget Cacat',
            'budget' => ['description' => 'tanpa detail'],
        ])->assertSessionHasErrors('budget.details')
            ->assertSessionDoesntHaveErrors('budget.plan_finish');

        $this->assertSame(0, Project::count());
        $this->assertSame(0, BudgetPlan::count());
    }

    public function test_store_budget_tanpa_plan_finish_berhasil_dan_plan_finish_kosong(): void
    {
        $head = $this->userWithRole('head');

        $this->actingAs($head)->post('/project', [
            'name'   => 'Proyek Tanpa Plan Finish',
            'budget' => $this->payloadBudget(),
        ])->assertRedirect('/project')->assertSessionDoesntHaveErrors();

        $this->assertNull(BudgetPlan::firstOrFail()->plan_finish);
    }

    public function test_input_plan_finish_di_blok_budget_diabaikan_bukan_error(): void
    {
        $head = $this->userWithRole('head');

        $this->actingAs($head)->post('/project', [
            'name'   => 'Proyek Plan Finish Lama',
            'budget' => $this->payloadBudget(['plan_finish' => 'bukan-tanggal']),
        ])->assertRedirect('/project')->assertSessionDoesntHaveErrors();

        $this->assertNull(BudgetPlan::firstOrFail()->plan_finish);
    }

    public function test_form_po_tidak_memuat_plan_finish_budget_dan_memuat_tombol_import(): void
    {
        $this->akun();

        // "Plan Finish" polos tetap ada di tabel milestone PO, jadi yang diperiksa teks/field budget-nya.
        $this->actingAs($this->userWithRole('head'))->get('/project/create')
            ->assertOk()
            ->assertDontSee('Plan Finish Budget')
            ->assertDontSee('budget[plan_finish]', false)
            ->assertSee('Download Template')
            ->assertSee('Import Excel')
            ->assertSee('project/budget-import-preview', false);
    }

    /* ----------------------------------------------- propagasi status */

    public function test_submit_verify_approve_menggerakkan_bp_bundled(): void
    {
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT');

        $this->actingAs($this->userWithRole('head'))->post('/project/' . $po->id . '/submit')->assertRedirect('/project');
        $this->assertSame('SUBMITTED', $po->fresh()->status);
        $this->assertSame('CHECKED', $bp->fresh()->status);

        $this->actingAs($this->userWithRole('finance'))->post('/project/' . $po->id . '/verify')->assertRedirect('/project');
        $this->assertSame('VERIFIED', $bp->fresh()->status);

        $approver = $this->userWithRole('approver');
        $this->actingAs($approver)->post('/project/' . $po->id . '/approve', ['decision' => 'APPROVE'])->assertRedirect('/project');
        $bp->refresh();
        $this->assertSame('ACTIVE', $bp->status);
        $this->assertSame($approver->id, (int) $bp->approved_by);
        $this->assertNotNull($bp->approved_at);

        // Setiap transisi tercatat di log BP.
        $this->assertEqualsCanonicalizing(
            ['submitted', 'finance_verified', 'ceo_approved'],
            $bp->logs()->pluck('action')->all()
        );
    }

    public function test_approve_early_start_juga_mengaktifkan_bp(): void
    {
        [$po, $bp] = $this->poDenganBp('VERIFIED', 'VERIFIED');

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'EARLY_START'])
            ->assertRedirect('/project');

        $this->assertSame('EARLY_START', $po->fresh()->status);
        $this->assertSame('ACTIVE', $bp->fresh()->status);
    }

    public function test_submit_ditolak_bila_bp_bundled_tanpa_detail(): void
    {
        $po = $this->makeProject(['status' => 'DRAFT']);
        $bp = $this->makeBudgetPlan($po, ['status' => 'DRAFT', 'via_po_approval' => true, 'amount' => 0]);

        $this->actingAs($this->userWithRole('head'))
            ->post('/project/' . $po->id . '/submit')
            ->assertSessionHas('error');

        $this->assertSame('DRAFT', $po->fresh()->status);
        $this->assertSame('DRAFT', $bp->fresh()->status);
    }

    public function test_bp_biasa_tidak_ikut_bergerak_bersama_po(): void
    {
        $po = $this->makeProject(['status' => 'DRAFT']);
        $biasa = $this->makeBudgetPlan($po, ['status' => 'DRAFT']);

        $this->actingAs($this->userWithRole('head'))->post('/project/' . $po->id . '/submit')->assertRedirect('/project');

        $this->assertSame('SUBMITTED', $po->fresh()->status);
        $this->assertSame('DRAFT', $biasa->fresh()->status);
    }

    public function test_finance_revise_mengembalikan_po_dan_bp_ke_draft(): void
    {
        foreach ([['SUBMITTED', 'CHECKED'], ['VERIFIED', 'VERIFIED']] as [$statusPo, $statusBp]) {
            [$po, $bp] = $this->poDenganBp($statusPo, $statusBp);

            $this->actingAs($this->userWithRole('superadmin'))
                ->post('/project/' . $po->id . '/finance-revise', ['notes' => 'Perbaiki budget'])
                ->assertRedirect('/project');

            $this->assertSame('DRAFT', $po->fresh()->status);
            $this->assertSame('DRAFT', $bp->fresh()->status, "BP dari $statusBp kembali DRAFT, bukan REJECTED");
            $this->assertSame('revised', $bp->logs()->reorder()->orderByDesc('id')->first()->action);
        }
    }

    /* -------------------------------------- aksi workflow BP lama ditutup */

    public function test_aksi_workflow_budget_plan_ditolak_untuk_bp_bundled(): void
    {
        $admin = $this->userWithRole('superadmin');

        $aksi = [
            ['submit', 'DRAFT'], ['head-approve', 'SUBMITTED'], ['head-reject', 'SUBMITTED'],
            ['finance-verify', 'CHECKED'], ['finance-reject', 'CHECKED'],
            ['ceo-approve', 'VERIFIED'], ['ceo-reject', 'VERIFIED'], ['manager-revise', 'REJECTED'],
        ];

        foreach ($aksi as [$url, $status]) {
            $bp = $this->makeBudgetPlan(null, ['status' => $status, 'via_po_approval' => true]);

            $this->actingAs($admin)
                ->post('/budget-plan/' . $bp->id . '/' . $url, ['notes' => 'uji'])
                ->assertSessionHas('error');

            $this->assertSame($status, $bp->fresh()->status, "$url tidak boleh mengubah BP bundled");
        }
    }

    public function test_edit_update_dan_hapus_budget_plan_ditolak_untuk_bp_bundled(): void
    {
        $admin = $this->userWithRole('superadmin');
        $bp = $this->makeBudgetPlan(null, ['status' => 'DRAFT', 'via_po_approval' => true, 'description' => 'ASLI']);

        $this->actingAs($admin)->get('/budget-plan/' . $bp->id . '/edit')->assertRedirect('/budget-plan');
        $this->actingAs($admin)->put('/budget-plan/' . $bp->id, [
            'project_id' => $bp->project_id, 'description' => 'UBAH',
            'details' => [['amount' => '1000', 'qty' => 1]],
        ])->assertSessionHas('error');
        $this->actingAs($admin)->delete('/budget-plan/' . $bp->id)->assertSessionHas('error');

        $this->assertSame('ASLI', $bp->fresh()->description);
        $this->assertNotNull(BudgetPlan::find($bp->id));
    }

    public function test_workflow_bp_biasa_tetap_berjalan(): void
    {
        $bp = $this->makeBudgetPlan(null, ['status' => 'CHECKED']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/finance-verify')
            ->assertRedirect('/budget-plan');

        $this->assertSame('VERIFIED', $bp->fresh()->status);
    }

    /* ------------------------------------------------------- edit PO */

    public function test_edit_po_draft_memperbarui_detail_bp_bundled_draft(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);

        $this->actingAs($head)->put('/project/' . $po->id, [
            'name'   => $po->name,
            'budget' => $this->payloadBudget([
                'description' => 'budget baru',
                'details'     => [['amount' => '2.000.000', 'qty' => '1']],
            ]),
        ])->assertRedirect('/project');

        $bp->refresh();
        $this->assertSame('BUDGET BARU', $bp->description);
        $this->assertSame(2_000_000, (int) $bp->amount);
        $this->assertSame(1, $bp->details()->count());
    }

    public function test_edit_po_tidak_mengubah_plan_finish_lama_bp_bundled(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $bp->update(['plan_finish' => '2026-12-31']);

        $this->actingAs($head)->put('/project/' . $po->id, [
            'name'   => $po->name,
            'budget' => $this->payloadBudget(['description' => 'budget revisi']),
        ])->assertRedirect('/project');

        $bp->refresh();
        $this->assertSame('BUDGET REVISI', $bp->description);
        $this->assertSame('2026-12-31', $bp->plan_finish->toDateString(), 'plan_finish lama dibiarkan');
    }

    public function test_budget_bp_bundled_tidak_boleh_dikosongkan_lewat_edit_po(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);

        $this->actingAs($head)->put('/project/' . $po->id, ['name' => $po->name])
            ->assertSessionHasErrors('budget.description');

        $this->assertSame(3_000_000, (int) $bp->fresh()->amount);
    }

    public function test_edit_po_tidak_mengubah_bp_bundled_yang_bukan_draft(): void
    {
        [$po, $bp] = $this->poDenganBp('SUBMITTED', 'CHECKED');

        $this->actingAs($this->userWithRole('superadmin'))->put('/project/' . $po->id, [
            'name'   => $po->name,
            'budget' => $this->payloadBudget(['description' => 'diubah paksa']),
        ])->assertRedirect('/project');

        $bp->refresh();
        $this->assertSame('CHECKED', $bp->status);
        $this->assertNotSame('DIUBAH PAKSA', $bp->description);
        $this->assertSame(3_000_000, (int) $bp->amount);
    }

    public function test_bp_bundled_baru_hanya_boleh_ditambah_pada_po_draft(): void
    {
        $admin = $this->userWithRole('superadmin');
        $submitted = $this->makeProject(['status' => 'SUBMITTED']);
        $draft     = $this->makeProject(['status' => 'DRAFT']);

        $this->actingAs($admin)->put('/project/' . $submitted->id, ['name' => $submitted->name, 'budget' => $this->payloadBudget()]);
        $this->assertSame(0, BudgetPlan::where('project_id', $submitted->id)->count());

        $this->actingAs($admin)->put('/project/' . $draft->id, ['name' => $draft->name, 'budget' => $this->payloadBudget()]);
        $this->assertSame(1, BudgetPlan::where('project_id', $draft->id)->where('via_po_approval', true)->count());
    }

    public function test_po_early_start_diedit_kembali_draft_tanpa_membuka_bp_active(): void
    {
        [$po, $bp] = $this->poDenganBp('EARLY_START', 'ACTIVE');

        $this->actingAs($this->userWithRole('head'))->put('/project/' . $po->id, [
            'name'   => $po->name,
            'budget' => $this->payloadBudget(['description' => 'coba ubah']),
        ])->assertRedirect('/project');

        $this->assertSame('DRAFT', $po->fresh()->status);
        $bp->refresh();
        $this->assertSame('ACTIVE', $bp->status, 'BP ACTIVE tidak ikut dibuka');
        $this->assertNotSame('COBA UBAH', $bp->description, 'dan tidak bisa diubah (read-only)');
    }

    public function test_hapus_po_ditolak_bila_bp_bundled_bukan_draft(): void
    {
        [$po, $bp] = $this->poDenganBp('DRAFT', 'ACTIVE');

        $this->actingAs($this->userWithRole('superadmin'))->delete('/project/' . $po->id)
            ->assertRedirect('/project')->assertSessionHas('error');

        $this->assertNotNull(Project::find($po->id));
        $this->assertSame('ACTIVE', $bp->fresh()->status);
    }

    public function test_hapus_po_draft_dengan_bp_bundled_draft_tetap_berhasil(): void
    {
        [$po] = $this->poDenganBp('DRAFT', 'DRAFT');

        $this->actingAs($this->userWithRole('superadmin'))->delete('/project/' . $po->id)
            ->assertRedirect('/project')->assertSessionHas('success');

        $this->assertNull(Project::find($po->id));
    }

    /* --------------------------------------------------- otorisasi */

    public function test_role_non_head_tidak_bisa_membuat_po_dengan_budget(): void
    {
        // Gerbang route POST /project = role:superadmin,head -> 403, tidak ada PO/BP.
        $this->actingAs($this->userWithRole('finance'))->post('/project', [
            'name'   => 'Proyek Ditolak',
            'budget' => $this->payloadBudget(),
        ])->assertForbidden();

        $this->assertSame(0, Project::count());
        $this->assertSame(0, BudgetPlan::count());
    }

    public function test_role_non_head_yang_boleh_edit_po_tidak_bisa_menambah_budget(): void
    {
        // finance_manager lolos gerbang edit PO untuk semua status, tapi bukan PO_BUDGET_BUNDLE:
        // PO tetap tersimpan (redirect biasa), bagian budget diabaikan.
        $po = $this->makeProject(['status' => 'DRAFT']);

        $this->actingAs($this->userWithRole('finance_manager'))->put('/project/' . $po->id, [
            'name'   => 'Nama Diubah Finance',
            'budget' => $this->payloadBudget(),
        ])->assertRedirect('/project');

        $this->assertSame('Nama Diubah Finance', $po->fresh()->name);
        $this->assertSame(0, BudgetPlan::where('project_id', $po->id)->count());
    }

    public function test_gerbang_buat_po_hanya_head_dan_superadmin(): void
    {
        foreach (['head', 'superadmin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/project/create')->assertOk();
        }
        foreach (['finance', 'manager', 'approver'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/project/create')->assertForbidden();
        }

        // Konstanta akses budget harus sama dengan gerbang route tersebut.
        $this->assertSame(['superadmin', 'head'], \App\Support\AccessRoles::PO_BUDGET_BUNDLE);
        $middleware = app('router')->getRoutes()->getByName('project.create')->gatherMiddleware();
        $this->assertContains('role:superadmin,head', $middleware);
    }

    /* ------------------------------------- alur lanjutan & closing */

    public function test_resubmit_setelah_revise_membawa_bp_kembali_ke_checked(): void
    {
        [$po, $bp] = $this->poDenganBp('SUBMITTED', 'CHECKED');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/project/' . $po->id . '/finance-revise', ['notes' => 'Perbaiki'])
            ->assertRedirect('/project');
        $this->assertSame('DRAFT', $po->fresh()->status);
        $this->assertSame('DRAFT', $bp->fresh()->status);

        $this->actingAs($this->userWithRole('head'))->post('/project/' . $po->id . '/submit')->assertRedirect('/project');
        $this->assertSame('SUBMITTED', $po->fresh()->status);
        $this->assertSame('CHECKED', $bp->fresh()->status);
    }

    public function test_verify_hanya_menggerakkan_bp_bundled_berstatus_checked(): void
    {
        [$po, $bundledActive] = $this->poDenganBp('SUBMITTED', 'ACTIVE');
        $bundledDraft = $this->makeBudgetPlan($po, ['status' => 'DRAFT', 'via_po_approval' => true]);
        $biasaChecked = $this->makeBudgetPlan($po, ['status' => 'CHECKED']);

        $this->actingAs($this->userWithRole('finance'))->post('/project/' . $po->id . '/verify')->assertRedirect('/project');

        $this->assertSame('VERIFIED', $po->fresh()->status);
        $this->assertSame('ACTIVE', $bundledActive->fresh()->status);
        $this->assertSame('DRAFT', $bundledDraft->fresh()->status);
        $this->assertSame('CHECKED', $biasaChecked->fresh()->status, 'BP biasa tidak ikut');
    }

    public function test_approve_ke_waiting_po_tetap_mengaktifkan_bp_bundled(): void
    {
        // Kontrak > 0 tanpa No. PO -> approvalAutoStatus() = WAITING_PO.
        $po = $this->makeProject(['status' => 'VERIFIED', 'contract' => 1_000_000, 'value' => 1_000_000]);
        $bp = $this->makeBudgetPlan($po, ['status' => 'VERIFIED', 'via_po_approval' => true, 'amount' => 3_000_000]);

        $this->actingAs($this->userWithRole('approver'))
            ->post('/project/' . $po->id . '/approve', ['decision' => 'APPROVE'])
            ->assertRedirect('/project');

        $this->assertSame('WAITING_PO', $po->fresh()->status);
        $this->assertSame('ACTIVE', $bp->fresh()->status);
    }

    public function test_closing_bp_bundled_tidak_diblokir_guard_workflow_bundled(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $bp = $this->makeBudgetPlan(null, ['status' => 'ACTIVE', 'via_po_approval' => true]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-plan/' . $bp->id . '/submit-closing', [
                'closing_notes'       => 'selesai',
                'closing_attachments' => [\Illuminate\Http\UploadedFile::fake()->create('mom.pdf', 10, 'application/pdf')],
            ])
            ->assertRedirect('/budget-plan')
            ->assertSessionHas('success');

        $this->assertSame('CLOSING', $bp->fresh()->status);
    }

    /* ------------------------------------ penutupan BP mengikuti PO */

    public function test_po_menjadi_paid_tidak_memindahkan_bp_ke_closing(): void
    {
        [$po, $bp] = $this->poDenganBp('ACTIVE', 'ACTIVE');
        $biasa = $this->makeBudgetPlan($po, ['status' => 'ACTIVE']);
        $bp->update(['plan_finish' => now()->subDay()->toDateString()]);

        foreach (['PAID', 'PAID'] as $statusNoPo) {
            $this->makePoNumber($po, ['status' => $statusNoPo]);
        }
        $po->fresh()->syncStatusFromPoNumbers();

        $this->assertSame('PAID', $po->fresh()->status);
        $this->assertSame('ACTIVE', $bp->fresh()->status);
        $this->assertSame('ACTIVE', $biasa->fresh()->status);
        $this->assertFalse($bp->logs()->where('action', 'auto_closing')->exists());
    }

    public function test_perintah_auto_closing_berbasis_plan_finish_sudah_dihapus(): void
    {
        $this->assertArrayNotHasKey('budget-plan:auto-closing', \Illuminate\Support\Facades\Artisan::all());
    }

    public function test_jadwal_tidak_lagi_memuat_auto_closing_budget_plan(): void
    {
        \Illuminate\Support\Facades\Artisan::all(); // pastikan routes/console.php termuat

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertFalse(
            $events->contains(fn ($e) => str_contains((string) ($e->command ?? ''), 'budget-plan:auto-closing')
                || str_contains((string) ($e->description ?? ''), 'auto-closing')),
            'jadwal budget-plan:auto-closing sudah dihapus'
        );
    }

    /**
     * Karakterisasi perilaku lama (bukan keputusan bisnis baru): "BP project ditutup otomatis
     * saat PO ditutup". closeBudgetPlans() tidak membatasi ke BP bundled, jadi BP biasa
     * (non-bundled) ACTIVE milik PO yang sama ikut ditutup.
     */
    public function test_finance_close_po_juga_menutup_bp_non_bundled_active_milik_po_yang_sama(): void
    {
        $po    = $this->makeProject(['status' => 'PAID']);
        $biasa = $this->makeBudgetPlan($po, ['status' => 'ACTIVE']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/finance-close', ['notes' => 'Selesai'])
            ->assertRedirect('/project');

        $this->assertFalse($biasa->fresh()->isBundled());
        $this->assertSame('CLOSED', $biasa->fresh()->status);
        $this->assertSame('closed_with_po', $biasa->logs()->reorder()->orderByDesc('id')->first()->action);
    }

    public function test_finance_close_po_menutup_bp_bundled_active_dan_closing_dengan_log_closed_with_po(): void
    {
        $po = $this->makeProject(['status' => 'PAID']);
        $active  = $this->makeBudgetPlan($po, ['status' => 'ACTIVE', 'via_po_approval' => true]);
        $closing = $this->makeBudgetPlan($po, ['status' => 'CLOSING', 'via_po_approval' => true]);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/finance-close', ['notes' => 'Selesai'])
            ->assertRedirect('/project');

        foreach ([$active, $closing] as $bp) {
            $this->assertSame('CLOSED', $bp->fresh()->status);
            $this->assertSame('closed_with_po', $bp->logs()->reorder()->orderByDesc('id')->first()->action);
        }
    }

    public function test_verify_closing_po_menutup_bp_bundled_dengan_log_closed_with_po(): void
    {
        $po = $this->makeProject(['status' => 'CLOSING']);
        $active  = $this->makeBudgetPlan($po, ['status' => 'ACTIVE', 'via_po_approval' => true]);
        $closing = $this->makeBudgetPlan($po, ['status' => 'CLOSING', 'via_po_approval' => true]);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/project/' . $po->id . '/verify-closing')
            ->assertRedirect();

        foreach ([$active, $closing] as $bp) {
            $this->assertSame('CLOSED', $bp->fresh()->status);
            $this->assertSame('closed_with_po', $bp->logs()->reorder()->orderByDesc('id')->first()->action);
        }
    }

    /** ProjectController::close() tidak punya route, jadi jalur ini diuji langsung lewat controller. */
    public function test_close_po_aktif_menutup_bp_bundled_dengan_log_closed_with_po(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $po = $this->makeProject(['status' => 'ACTIVE']);
        $bp = $this->makeBudgetPlan($po, ['status' => 'ACTIVE', 'via_po_approval' => true]);

        $user = $this->userWithRole('superadmin');
        $this->actingAs($user);
        $request = \Illuminate\Http\Request::create('/project/' . $po->id . '/close', 'POST', [
            'closing_attachments' => [['title' => 'MoM']],
        ], [], [
            'closing_attachments' => [['file' => \Illuminate\Http\UploadedFile::fake()->create('mom.pdf', 10, 'application/pdf')]],
        ]);
        $request->setUserResolver(fn () => $user);

        app(\App\Http\Controllers\ProjectController::class)->close($request, $po->id);

        $this->assertSame('CLOSED', $po->fresh()->status);
        $this->assertSame('CLOSED', $bp->fresh()->status);
        $this->assertSame('closed_with_po', $bp->logs()->reorder()->orderByDesc('id')->first()->action);
    }

    /* ------------------------------------- import Excel Detail Budget di PO */

    private function xlsxBudget(array $baris): \Illuminate\Http\UploadedFile
    {
        $book  = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Detail Budget');
        $sheet->fromArray([['Account', 'Deskripsi', 'Harga Satuan', 'Qty', 'Unit', 'Notes']], null, 'A1');
        if ($baris) {
            $sheet->fromArray($baris, null, 'A2');
        }

        $path = tempnam(sys_get_temp_dir(), 'bud') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return new \Illuminate\Http\UploadedFile(
            $path, 'budget.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true
        );
    }

    public function test_head_dan_superadmin_bisa_unduh_template_import_budget_po(): void
    {
        foreach (['head', 'superadmin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/project/budget-import-template')
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_preview_import_budget_po_mengembalikan_baris_tanpa_menyimpan(): void
    {
        $akun = $this->akun();
        $file = $this->xlsxBudget([[$akun->name, 'Kabel', 1000, 2, 'pcs', 'catatan']]);

        $this->actingAs($this->userWithRole('head'))
            ->post('/project/budget-import-preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('errors', [])
            ->assertJsonPath('rows.0.account_id', $akun->id)
            ->assertJsonPath('rows.0.amount', 1000)
            ->assertJsonPath('rows.0.unit', 'pcs');

        $this->assertSame(0, BudgetPlan::count());
        $this->assertSame(0, BudgetPlanDetail::count());
    }

    public function test_preview_import_budget_po_memakai_account_variable_cost_saja(): void
    {
        $overhead = Account::create(['name' => 'Sewa Kantor', 'category' => 'Overhead', 'status' => 'ACTIVE']);
        $file = $this->xlsxBudget([[$overhead->name, 'Sewa', 5000, 1, 'pcs', null]]);

        $res = $this->actingAs($this->userWithRole('head'))
            ->post('/project/budget-import-preview', ['file' => $file])
            ->assertOk();

        $this->assertNull($res->json('rows.0.account_id'), 'source terkunci PROJECT');
        $this->assertNotEmpty($res->json('errors'));
    }

    public function test_route_import_budget_po_hanya_head_dan_superadmin(): void
    {
        foreach (['finance', 'manager', 'approver'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/project/budget-import-template')->assertForbidden();
            $this->actingAs($user)->post('/project/budget-import-preview', ['file' => $this->xlsxBudget([])])->assertForbidden();
        }
    }

    public function test_route_import_budget_plan_lama_tetap_superadmin_dan_manager(): void
    {
        $routes = app('router')->getRoutes();

        foreach (['budget-plan.import-template', 'budget-plan.import-preview'] as $nama) {
            $this->assertContains('role:superadmin,manager', $routes->getByName($nama)->gatherMiddleware());
        }
        foreach (['project.budget-import-template', 'project.budget-import-preview'] as $nama) {
            $this->assertContains('role:superadmin,head', $routes->getByName($nama)->gatherMiddleware());
        }

        $this->actingAs($this->userWithRole('manager'))->get('/budget-plan/import-template?source=PROJECT')->assertOk();
        $this->actingAs($this->userWithRole('head'))->get('/budget-plan/import-template?source=PROJECT')->assertForbidden();
    }

    public function test_preview_import_lewat_route_budget_plan_lama_tetap_berfungsi(): void
    {
        $akun = $this->akun();

        foreach (['manager', 'superadmin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post('/budget-plan/import-preview', [
                    'file'        => $this->xlsxBudget([[$akun->name, 'Kabel', 1000, 2, 'pcs', 'catatan']]),
                    'source_type' => 'PROJECT',
                ])
                ->assertOk()
                ->assertJsonPath('errors', [])
                ->assertJsonPath('rows.0.account_id', $akun->id)
                ->assertJsonPath('rows.0.amount', 1000)
                ->assertJsonPath('rows.0.unit', 'pcs');
        }

        $this->assertSame(0, BudgetPlan::count());
        $this->assertSame(0, BudgetPlanDetail::count());
    }

    public function test_preview_import_file_tidak_terbaca_mengembalikan_422_dengan_errors(): void
    {
        // xlsx valid secara mime, tetapi sheet "Detail Budget" tidak ada & isi rusak dipaksa lewat
        // file zip kosong bernama .xlsx agar IOFactory::load gagal.
        $path = tempnam(sys_get_temp_dir(), 'bad') . '.xlsx';
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('bukan-excel.txt', 'x');
        $zip->close();
        $file = new \Illuminate\Http\UploadedFile($path, 'rusak.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $res = $this->actingAs($this->userWithRole('manager'))
            ->post('/budget-plan/import-preview', ['file' => $file, 'source_type' => 'PROJECT']);

        // Validasi mime Laravel bisa menolak lebih dulu (422 juga); keduanya tidak boleh 200/500.
        $res->assertStatus(422);
        $this->assertNotEmpty($res->json('errors'));
    }

    /* ------------------------------------------------------ Todo List */

    public function test_todo_list_tidak_menggandakan_tugas_po_dan_bp_bundled(): void
    {
        $finance = $this->userWithRole('finance');
        $this->giveMenuAccess($finance->role, 'todo-list');

        // PO SUBMITTED dengan BP bundled CHECKED -> hanya tugas PO.
        $this->poDenganBp('SUBMITTED', 'CHECKED');
        $this->actingAs($finance);
        $kunci = collect(app(TodoListController::class)->actionQueues($finance)['queues'])->pluck('key');
        $this->assertTrue($kunci->contains('po-submitted'));
        $this->assertFalse($kunci->contains('bp-verify'), 'BP bundled tidak muncul di antrean Budgeting');

        // BP biasa CHECKED tetap muncul di antrean Budgeting.
        $this->makeBudgetPlan(null, ['status' => 'CHECKED']);
        $kunci = collect(app(TodoListController::class)->actionQueues($finance)['queues'])->pluck('key');
        $this->assertTrue($kunci->contains('bp-verify'));
    }

    /** Kunci antrean actionQueues() untuk user (menu todo-list diberikan). */
    private function kunciAntrean($user)
    {
        $this->giveMenuAccess($user->role, 'todo-list');
        $this->actingAs($user);

        return collect(app(TodoListController::class)->actionQueues($user)['queues'])->pluck('key');
    }

    public function test_todo_list_po_verified_muncul_di_approver_tanpa_menggandakan_bp_ceo(): void
    {
        [$po] = $this->poDenganBp('VERIFIED', 'VERIFIED');
        $approver = $this->userWithRole('approver');

        $kunci = $this->kunciAntrean($approver);
        $this->assertTrue($kunci->contains('po-verified'));
        $this->assertFalse($kunci->contains('bp-ceo'), 'BP bundled ikut PO, tidak muncul di antrean Budgeting');

        // PO SUBMITTED tidak masuk antrean Approver.
        $this->assertFalse($this->kunciAntrean($this->userWithRole('approver'))->contains('po-submitted'));
    }

    public function test_todo_list_po_verified_tidak_terlihat_role_lain(): void
    {
        $this->poDenganBp('VERIFIED', 'VERIFIED');

        foreach (['finance', 'finance_manager', 'head', 'manager'] as $role) {
            $this->assertFalse(
                $this->kunciAntrean($this->userWithRole($role))->contains('po-verified'),
                "$role tidak menangani PO VERIFIED"
            );
        }
    }

    public function test_todo_list_po_verified_masuk_export_dan_keluar_setelah_approve_atau_revise(): void
    {
        [$poA] = $this->poDenganBp('VERIFIED', 'VERIFIED');
        [$poB] = $this->poDenganBp('VERIFIED', 'VERIFIED');
        $approver = $this->userWithRole('approver');

        $this->actingAs($approver);
        $m = new \ReflectionMethod(TodoListController::class, 'exportRows');
        $m->setAccessible(true);
        $baris = $m->invoke(app(TodoListController::class), $approver);
        $this->assertTrue($baris->pluck('key')->contains('po:' . $poA->id));
        $this->assertTrue($baris->pluck('key')->contains('po:' . $poB->id));

        $this->post('/project/' . $poA->id . '/approve', ['decision' => 'APPROVE'])->assertRedirect('/project');
        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/project/' . $poB->id . '/finance-revise', ['notes' => 'Perbaiki'])
            ->assertRedirect('/project');

        $this->actingAs($approver);
        $baris = $m->invoke(app(TodoListController::class), $approver);
        $this->assertFalse($baris->pluck('key')->contains('po:' . $poA->id));
        $this->assertFalse($baris->pluck('key')->contains('po:' . $poB->id));
        $this->assertFalse($this->kunciAntrean($approver)->contains('po-verified'));
    }

    /* ------------------------------------------- Hapus Budget (Edit PO) */

    private function hapusBudget(Project $po, $user, array $extra = [])
    {
        return $this->actingAs($user)->put('/project/' . $po->id, array_merge([
            'name'   => $po->name,
            'budget' => ['hapus' => '1'],
        ], $extra));
    }

    public function test_hapus_budget_bundled_draft_berhasil_beserta_detail_log_dan_lampiran(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure');
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $bp->addLog('DRAFT', 'DRAFT', 'created', 'uji');

        \Illuminate\Support\Facades\Storage::disk('secure')->put('budget-plan/uji.pdf', 'isi');
        \App\Models\BudgetPlanAttachment::create([
            'budget_plan_id' => $bp->id, 'original_name' => 'uji.pdf', 'stored_name' => 'uji.pdf',
            'path' => 'budget-plan/uji.pdf', 'mime_type' => 'application/pdf', 'size' => 4,
        ]);
        $kode = $bp->code;

        $this->hapusBudget($po, $head)->assertRedirect('/project')->assertSessionHas('success');

        $this->assertNull(BudgetPlan::find($bp->id));
        $this->assertSame(0, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('budget_plan_logs')->where('budget_plan_id', $bp->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('budget_plan_attachments')->where('budget_plan_id', $bp->id)->count());
        \Illuminate\Support\Facades\Storage::disk('secure')->assertMissing('budget-plan/uji.pdf');

        $this->assertNotNull(Project::find($po->id), 'PO tetap ada');
        $this->assertSame('DRAFT', $po->fresh()->status);
        $this->assertTrue($po->logs()->where('action', 'budget_deleted')->where('notes', 'like', '%' . $kode . '%')->exists());
        $this->assertTrue(\App\Models\UserActivityLog::where('module', 'budget_plan')->where('action', 'delete')
            ->where('description', 'like', '%' . $kode . '%')->exists());
    }

    public function test_form_budget_kosong_tanpa_flag_hapus_tetap_error(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);

        $this->actingAs($head)->put('/project/' . $po->id, ['name' => $po->name, 'budget' => ['hapus' => '0']])
            ->assertSessionHasErrors('budget.description');

        $this->assertNotNull(BudgetPlan::find($bp->id));
    }

    public function test_hapus_budget_ditolak_bila_bp_bukan_draft(): void
    {
        foreach (['CHECKED', 'VERIFIED', 'ACTIVE'] as $statusBp) {
            [$po, $bp] = $this->poDenganBp('DRAFT', $statusBp);

            // Flag diabaikan (jalur lama): PO tersimpan biasa, BP tidak tersentuh.
            $this->hapusBudget($po, $this->userWithRole('superadmin'))
                ->assertRedirect('/project')
                ->assertSessionHasNoErrors();

            $this->assertNotNull(BudgetPlan::find($bp->id), "BP $statusBp tidak boleh terhapus");
            $this->assertSame($statusBp, $bp->fresh()->status);
            $this->assertSame(1, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
        }
    }

    public function test_hapus_budget_ditolak_bila_po_bukan_draft(): void
    {
        [$po, $bp] = $this->poDenganBp('SUBMITTED', 'DRAFT');

        $this->hapusBudget($po, $this->userWithRole('superadmin'));

        // Respons tidak diasersi (perilaku edit PO non-DRAFT di luar cakupan tes ini);
        // yang dijamin: BP & detailnya utuh dan status PO tidak berubah.
        $this->assertNotNull(BudgetPlan::find($bp->id));
        $this->assertSame(1, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
        $this->assertSame('SUBMITTED', $po->fresh()->status);
        $this->assertFalse($po->logs()->where('action', 'budget_deleted')->exists());
    }

    public function test_hapus_budget_diabaikan_untuk_role_yang_bukan_head_atau_superadmin(): void
    {
        foreach (['finance_manager', 'approver', 'finance'] as $role) {
            [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT');

            $this->hapusBudget($po, $this->userWithRole($role));

            $this->assertNotNull(BudgetPlan::find($bp->id), "$role tidak boleh menghapus budget");
            $this->assertSame(1, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count(), "$role: detail budget utuh");
            $this->assertFalse($po->logs()->where('action', 'budget_deleted')->exists(), "$role: tak ada log hapus budget");
        }
    }

    public function test_hapus_budget_ditolak_bila_ada_lebih_dari_satu_bp_bundled(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $kedua = $this->makeBudgetPlan($po, ['status' => 'DRAFT', 'via_po_approval' => true, 'input_by' => $head->id]);

        $this->hapusBudget($po, $head)->assertSessionHasErrors('budget.hapus');

        $this->assertNotNull(BudgetPlan::find($bp->id));
        $this->assertNotNull(BudgetPlan::find($kedua->id));
    }

    public function test_hapus_budget_ditolak_bila_dirujuk_log_project_monitoring(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $logId = \Illuminate\Support\Facades\DB::table('project_monitoring_logs')->insertGetId([
            'project_id' => $po->id, 'budget_plan_id' => $bp->id, 'type' => 'MESSAGE',
            'message' => 'uji', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->hapusBudget($po, $head)->assertSessionHasErrors('budget.hapus');

        $this->assertNotNull(BudgetPlan::find($bp->id));
        $this->assertSame($bp->id, (int) \Illuminate\Support\Facades\DB::table('project_monitoring_logs')->where('id', $logId)->value('budget_plan_id'));
    }

    public function test_hapus_budget_ditolak_bila_dirujuk_payroll_run(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $runId = \Illuminate\Support\Facades\DB::table('payroll_runs')->insertGetId([
            'code' => 'PRR-UJI-1', 'period' => '2026-07', 'budget_plan_id' => $bp->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->hapusBudget($po, $head)->assertSessionHasErrors('budget.hapus');

        $this->assertNotNull(BudgetPlan::find($bp->id));
        $this->assertSame($bp->id, (int) \Illuminate\Support\Facades\DB::table('payroll_runs')->where('id', $runId)->value('budget_plan_id'));
    }

    // Menguji pra-cek (sebelum transaksi). Cek ulang di dalam transaksi & rollback FK
    // tidak teruji otomatis: butuh perubahan data di tengah request.
    public function test_hapus_budget_ditolak_bila_sudah_dirujuk_cost_request_dan_po_tidak_berubah(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $this->makeCostRequest(['budget_plan' => $bp]);
        $namaAsli = $po->name;

        $this->hapusBudget($po, $head, ['name' => 'NAMA BARU'])
            ->assertSessionHasErrors('budget.hapus');

        $this->assertNotNull(BudgetPlan::find($bp->id));
        $this->assertSame(1, BudgetPlanDetail::where('budget_plan_id', $bp->id)->count());
        $this->assertSame($namaAsli, $po->fresh()->name, 'PO tidak ikut berubah');
    }

    public function test_hapus_budget_ditolak_bila_dirujuk_cost_request_yang_sudah_di_soft_delete(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $this->makeCostRequest(['budget_plan' => $bp])->delete();

        $this->hapusBudget($po, $head)->assertSessionHasErrors('budget.hapus');

        $this->assertNotNull(BudgetPlan::find($bp->id));
    }

    public function test_submit_po_setelah_budget_dihapus_berhasil_tanpa_budget(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);

        $this->hapusBudget($po, $head)->assertRedirect('/project');
        $this->assertNull(BudgetPlan::find($bp->id));

        $this->actingAs($head)->post('/project/' . $po->id . '/submit')->assertRedirect('/project');
        $this->assertSame('SUBMITTED', $po->fresh()->status);
    }

    public function test_hapus_budget_tidak_menyentuh_bp_biasa_pada_po_yang_sama(): void
    {
        $head = $this->userWithRole('head');
        [$po, $bp] = $this->poDenganBp('DRAFT', 'DRAFT', $head->id);
        $biasa = $this->makeBudgetPlan($po, ['status' => 'DRAFT']);

        $this->hapusBudget($po, $head)->assertRedirect('/project');

        $this->assertNull(BudgetPlan::find($bp->id));
        $this->assertNotNull(BudgetPlan::find($biasa->id), 'BP biasa tidak terhapus');
    }

    public function test_hapus_lewat_menu_budget_plan_tetap_ditolak_untuk_bp_bundled(): void
    {
        [, $bp] = $this->poDenganBp('DRAFT', 'DRAFT');

        $this->actingAs($this->userWithRole('superadmin'))->delete('/budget-plan/' . $bp->id)
            ->assertSessionHas('error');

        $this->assertNotNull(BudgetPlan::find($bp->id));
    }
}
