<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\Project;
use App\Models\RequestDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Bulk Approve/Reject tahap pengajuan dari index Cost Request (dan Todo List).
 *
 * Endpoint sama dengan section Review Todo List. Yang ditambahkan index:
 * `expected[id] => STATUS` (CR dilewati bila statusnya sudah berubah), batas
 * 50 id, dan pesan CR yang dilewati. Todo List tidak mengirim `expected`.
 *
 * Catatan: test ini ditulis tetapi belum dijalankan (gerbang CLAUDE.md §4.3).
 */
class CostRequestBulkReviewTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache id tipe Cash Advance bersifat static; basis data uji dibuat ulang tiap test.
        (new \ReflectionProperty(BudgetRequest::class, 'cashAdvanceTypeIdsCache'))->setValue(null, null);
    }

    private function cr(string $status, string $type = 'Cash Advance'): BudgetRequest
    {
        $this->project ??= $this->makeProject();

        $cr = $this->makeCostRequest([
            'budget_plan' => $this->makeBudgetPlan($this->project),
            'status'      => $status,
            'amount'      => 500_000,
            'type_id'     => $this->makeRequestType($type)->id,
        ]);

        RequestDetail::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Item 1',
            'amount'            => 500_000,
            'qty'               => 1,
            'total'             => 500_000,
            'status'            => 'INCLUDED',
        ]);

        return $cr;
    }

    private function headUser(): User
    {
        return $this->giveProjectAccess($this->userWithRole('head'), $this->project);
    }

    public function test_head_bulk_approve_submitted_menjadi_checked_dan_tercatat_di_log(): void
    {
        $a = $this->cr('SUBMITTED');
        $b = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$a->id, $b->id],
                'expected' => [$a->id => 'SUBMITTED', $b->id => 'SUBMITTED'],
            ])
            ->assertSessionHas('success', '2 CR berhasil di-approve.')
            ->assertSessionMissing('error');

        foreach ([$a, $b] as $cr) {
            $this->assertSame('CHECKED', $cr->refresh()->status);
            $this->assertSame(1, $cr->statusLogs()->where('action_type', 'head_approved')->count());
        }
    }

    public function test_finance_bulk_approve_checked_menjadi_verified_dan_mengisi_approved_at(): void
    {
        $cr = $this->cr('CHECKED');

        $this->actingAs($this->userWithRole('finance'))
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$cr->id],
                'expected' => [$cr->id => 'CHECKED'],
            ])
            ->assertSessionHas('success', '1 CR berhasil di-approve.');

        $cr->refresh();
        $this->assertSame('VERIFIED', $cr->status);
        $this->assertNotNull($cr->approved_at);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'finance_approved')->count());
    }

    public function test_approver_bulk_approve_verified_menjadi_approved(): void
    {
        $cr = $this->cr('VERIFIED');

        $this->actingAs($this->userWithRole('approver'))
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$cr->id],
                'expected' => [$cr->id => 'VERIFIED'],
            ])
            ->assertSessionHas('success', '1 CR berhasil di-approve.');

        $this->assertSame('APPROVED', $cr->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'cr_approved')->count());
    }

    public function test_bulk_reject_wajib_alasan(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-reject', [
                'ids'      => [$cr->id],
                'expected' => [$cr->id => 'SUBMITTED'],
            ])
            ->assertSessionHasErrors('notes');

        $this->assertSame('SUBMITTED', $cr->refresh()->status);
    }

    public function test_bulk_reject_dengan_alasan_menolak_dan_mencatat_log(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-reject', [
                'ids'      => [$cr->id],
                'expected' => [$cr->id => 'SUBMITTED'],
                'notes'    => 'Dokumen belum lengkap',
            ])
            ->assertSessionHas('success', fn ($pesan) => str_starts_with($pesan, '1 CR berhasil ditolak'));

        $this->assertSame('REJECTED', $cr->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'head_rejected')->count());
    }

    public function test_expected_tidak_cocok_dilewati_termasuk_untuk_superadmin(): void
    {
        $cr = $this->cr('CHECKED');

        // Layar user masih menampilkan SUBMITTED, padahal CR sudah CHECKED.
        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$cr->id],
                'expected' => [$cr->id => 'SUBMITTED'],
            ])
            ->assertSessionHas('success', '0 CR berhasil di-approve.')
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, $cr->code)
                && str_contains($pesan, 'status berubah dari SUBMITTED menjadi CHECKED'));

        $this->assertSame('CHECKED', $cr->refresh()->status);
        $this->assertSame(0, $cr->statusLogs()->where('action_type', 'finance_approved')->count());
    }

    public function test_id_di_luar_access_group_tidak_berubah_dan_dilaporkan_dilewati(): void
    {
        $cr = $this->cr('SUBMITTED');

        // Head tanpa akses ke proyek CR ini.
        $this->actingAs($this->userWithRole('head'))
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$cr->id],
                'expected' => [$cr->id => 'SUBMITTED'],
            ])
            ->assertSessionHas('success', '0 CR berhasil di-approve.')
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'tidak ditemukan atau tidak ada akses'));

        $this->assertSame('SUBMITTED', $cr->refresh()->status);
    }

    public function test_campuran_status_oleh_head_hanya_submitted_yang_berubah(): void
    {
        $submitted = $this->cr('SUBMITTED');
        $checked   = $this->cr('CHECKED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$submitted->id, $checked->id],
                'expected' => [$submitted->id => 'SUBMITTED', $checked->id => 'CHECKED'],
            ])
            ->assertSessionHas('success', '1 CR berhasil di-approve.')
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, $checked->code)
                && str_contains($pesan, 'peran Anda tidak berhak'));

        $this->assertSame('CHECKED', $submitted->refresh()->status);
        $this->assertSame('CHECKED', $checked->refresh()->status);
        $this->assertSame(0, $checked->statusLogs()->where('action_type', 'finance_approved')->count());
    }

    public function test_jalur_todo_list_tanpa_expected_tetap_berjalan(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', ['ids' => [$cr->id]])
            ->assertSessionHas('success', '1 CR berhasil di-approve.')
            ->assertSessionMissing('error');

        $this->assertSame('CHECKED', $cr->refresh()->status);
    }

    public function test_ids_duplikat_hanya_memproses_satu_cr(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => [$cr->id, $cr->id],
                'expected' => [$cr->id => 'SUBMITTED'],
            ])
            ->assertSessionHas('success', '1 CR berhasil di-approve.')
            ->assertSessionMissing('error');

        $this->assertSame('CHECKED', $cr->refresh()->status);
        $this->assertSame(1, $cr->statusLogs()->where('action_type', 'head_approved')->count());
    }

    public function test_lebih_dari_5_cr_dilewati_pesan_dipotong_dengan_elipsis(): void
    {
        // Head tidak berhak atas tahap CHECKED: semua 6 CR dilewati.
        $crs = collect(range(1, 6))->map(fn () => $this->cr('CHECKED'));

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', [
                'ids'      => $crs->pluck('id')->all(),
                'expected' => $crs->mapWithKeys(fn ($c) => [$c->id => 'CHECKED'])->all(),
            ])
            ->assertSessionHas('success', '0 CR berhasil di-approve.')
            ->assertSessionHas('error', fn ($pesan) => str_starts_with($pesan, '6 CR dilewati: ')
                && str_ends_with($pesan, ', ….')
                && str_contains($pesan, $crs[4]->code)
                && ! str_contains($pesan, $crs[5]->code));
    }

    /**
     * Nilai data-elig checkbox baris CR pada HTML tab, atau null bila tidak ada checkbox-nya.
     */
    private function eligDi(string $html, BudgetRequest $cr): ?string
    {
        return preg_match('/class="cr-check-row" value="' . $cr->id . '"\s+data-elig="([01])"/', $html, $m)
            ? $m[1]
            : null;
    }

    private function tabHtml(User $user, string $tab): string
    {
        $this->giveMenuAccess($user->role, 'finance-cost-request', 'project-cost-request');

        return $this->actingAs($user)->get('/budget-request/tab/' . $tab)->assertOk()->getContent();
    }

    public function test_tab_ca_head_hanya_baris_submitted_yang_eligible(): void
    {
        $submitted = $this->cr('SUBMITTED');
        $checked   = $this->cr('CHECKED');
        $approved  = $this->cr('APPROVED');

        $html = $this->tabHtml($this->headUser(), 'ca');

        $this->assertStringContainsString('cr-check-all', $html);
        $this->assertSame('1', $this->eligDi($html, $submitted));
        $this->assertSame('0', $this->eligDi($html, $checked));
        $this->assertSame('0', $this->eligDi($html, $approved));
    }

    public function test_tab_cp_finance_hanya_baris_checked_yang_eligible(): void
    {
        $submitted = $this->cr('SUBMITTED', 'Cost Payment');
        $checked   = $this->cr('CHECKED', 'Cost Payment');
        $verified  = $this->cr('VERIFIED', 'Cost Payment');

        $html = $this->tabHtml($this->userWithRole('finance'), 'cp');

        $this->assertStringContainsString('cr-check-all', $html);
        $this->assertSame('0', $this->eligDi($html, $submitted));
        $this->assertSame('1', $this->eligDi($html, $checked));
        $this->assertSame('0', $this->eligDi($html, $verified));
    }

    public function test_tab_ca_approver_hanya_baris_verified_yang_eligible(): void
    {
        $checked  = $this->cr('CHECKED');
        $verified = $this->cr('VERIFIED');

        $html = $this->tabHtml($this->userWithRole('approver'), 'ca');

        $this->assertSame('0', $this->eligDi($html, $checked));
        $this->assertSame('1', $this->eligDi($html, $verified));
    }

    public function test_tab_manager_dan_supervisor_tanpa_kolom_checkbox(): void
    {
        $cr = $this->cr('SUBMITTED');

        foreach (['manager', 'supervisor'] as $role) {
            $user = $this->giveProjectAccess($this->userWithRole($role), $this->project);
            $html = $this->tabHtml($user, 'ca');

            $this->assertStringNotContainsString('cr-check-all', $html, $role);
            // Penanda spesifik <input> baris: 'cr-check-row' juga muncul di atribut class <table> ($gayaKartu).
            $this->assertStringNotContainsString('class="cr-check-row"', $html, $role);
            // Penanda spesifik <th> kolom centang: kelas 'cr-col-check' juga muncul di atribut class <table> ($gayaTabel).
            $this->assertStringNotContainsString('class="all text-center cr-col-check"', $html, $role);
            $this->assertNull($this->eligDi($html, $cr), $role);
        }
    }

    public function test_tab_db_tanpa_kolom_checkbox(): void
    {
        $this->cr('SUBMITTED');

        $html = $this->tabHtml($this->userWithRole('finance'), 'db');

        $this->assertStringNotContainsString('cr-check-all', $html);
        $this->assertStringNotContainsString('class="cr-check-row"', $html);
        $this->assertStringNotContainsString('class="all text-center cr-col-check"', $html);
    }

    public function test_ids_lebih_dari_50_ditolak_validasi(): void
    {
        $cr = $this->cr('SUBMITTED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/review-bulk-approve', ['ids' => range($cr->id, $cr->id + 50)])
            ->assertSessionHasErrors('ids');

        $this->assertSame('SUBMITTED', $cr->refresh()->status);
    }
}
