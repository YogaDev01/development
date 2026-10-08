<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\RequestDetail;
use App\Support\BudgetConsumption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Status SETTLE_REJECTED: reject settlement (Head/Finance/Finance Manager/
 * Approver) berhenti di sini — bukan langsung ke PAID — supaya pengaju bisa
 * merevisi lalu submit ulang lewat form yang sama (realize.blade.php), dengan
 * tambahan tombol "Simpan" (draft, status tidak berubah).
 *
 * Lihat App\Models\BudgetRequest::getAllowedNextStatuses(),
 * App\Support\BudgetConsumption, Concerns\HandlesCrSettlement.
 */
class SettleRejectedStatusTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private \App\Models\Project $project;

    private function cr(string $status, array $attributes = []): BudgetRequest
    {
        $this->project = $this->makeProject();
        $plan = $this->makeBudgetPlan($this->project);

        return $this->makeCostRequest(array_merge([
            'budget_plan' => $plan,
            'status'      => $status,
            'type_id'     => $this->makeRequestType('Cash Advance')->id,
        ], $attributes));
    }

    private function headUser(): \App\Models\User
    {
        return $this->giveProjectAccess($this->userWithRole('head'), $this->project);
    }

    private function managerUser(): \App\Models\User
    {
        return $this->giveProjectAccess($this->userWithRole('manager'), $this->project);
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL — status baru konsisten di setiap sumber kebenaran
    |--------------------------------------------------------------------------
    */

    public function test_settle_rejected_terdaftar_di_statuses(): void
    {
        $this->assertContains('SETTLE_REJECTED', BudgetRequest::STATUSES);
    }

    public function test_settle_rejected_bisa_lanjut_ke_realized(): void
    {
        $cr = $this->cr('SETTLE_REJECTED');
        $this->assertSame(['REALIZED'], $cr->getAllowedNextStatuses());
        $this->assertTrue($cr->canTransitionTo('REALIZED'));
    }

    public function test_settle_rejected_tetap_terhitung_kuota_ca_dan_dibursed(): void
    {
        $this->assertContains('SETTLE_REJECTED', BudgetRequest::CA_COUNTED_STATUSES);
        $this->assertContains('SETTLE_REJECTED', BudgetConsumption::DISBURSED);
        $this->assertContains('SETTLE_REJECTED', BudgetConsumption::COMMITTED);
        $this->assertContains('SETTLE_REJECTED', BudgetConsumption::REPORTED);
        $this->assertNotContains('SETTLE_REJECTED', BudgetConsumption::NONE);
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP — reject settlement berhenti di SETTLE_REJECTED (bukan PAID)
    |--------------------------------------------------------------------------
    */

    public function test_head_reject_settlement_berhenti_di_settle_rejected(): void
    {
        $cr = $this->cr('REALIZED');

        $this->actingAs($this->headUser())
            ->post('/budget-request/' . $cr->id . '/head-reject-settlement', ['notes' => 'Bukti kurang jelas'])
            ->assertRedirect();

        $this->assertSame('SETTLE_REJECTED', $cr->fresh()->status);
        $this->assertDatabaseHas('request_status_logs', [
            'budget_request_id' => $cr->id,
            'status_from'       => 'REALIZED',
            'status_to'         => 'SETTLE_REJECTED',
            'action_type'       => 'settlement_rejected',
        ]);
    }

    public function test_finance_reject_settlement_berhenti_di_settle_rejected(): void
    {
        $cr = $this->cr('SETTLE_CHECKED');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/budget-request/' . $cr->id . '/finance-reject-settlement', ['notes' => 'Selisih tidak wajar'])
            ->assertRedirect();

        $this->assertSame('SETTLE_REJECTED', $cr->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP — form realisasi menerima PAID maupun SETTLE_REJECTED
    |--------------------------------------------------------------------------
    */

    public function test_form_realisasi_terbuka_untuk_settle_rejected(): void
    {
        $cr = $this->cr('SETTLE_REJECTED');

        $this->actingAs($this->managerUser())
            ->get('/budget-request/' . $cr->id . '/realize-form')
            ->assertOk();
    }

    public function test_form_realisasi_ditolak_untuk_status_lain(): void
    {
        $cr = $this->cr('DRAFT');

        $this->actingAs($this->managerUser())
            ->get('/budget-request/' . $cr->id . '/realize-form')
            ->assertRedirect('/budget-request');
    }

    public function test_submit_realisasi_dari_settle_rejected_pindah_ke_realized(): void
    {
        $cr = $this->cr('SETTLE_REJECTED');
        $detail = RequestDetail::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Item uji',
            'amount'            => 100_000,
            'qty'               => 1,
            'total'             => 100_000,
            'status'            => 'INCLUDED',
        ]);

        $payload = [
            'realizations' => [
                [
                    'request_detail_id' => $detail->id,
                    'amount'            => 100_000,
                    'notes'             => 'Sesuai nota',
                    'attachments'       => [UploadedFile::fake()->image('bukti.jpg')],
                ],
            ],
        ];

        $this->actingAs($this->managerUser())
            ->post('/budget-request/' . $cr->id . '/submit-realization', $payload)
            ->assertRedirect(route('budget-request.index'));

        $this->assertSame('REALIZED', $cr->fresh()->status);
        $this->assertDatabaseHas('request_status_logs', [
            'budget_request_id' => $cr->id,
            'status_from'       => 'SETTLE_REJECTED',
            'status_to'         => 'REALIZED',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP — "Simpan" (draft): status TIDAK berubah
    |--------------------------------------------------------------------------
    */

    public function test_simpan_draft_tidak_mengubah_status(): void
    {
        $cr = $this->cr('PAID');
        $detail = RequestDetail::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Item uji',
            'amount'            => 50_000,
            'qty'               => 1,
            'total'             => 50_000,
            'status'            => 'INCLUDED',
        ]);

        $payload = [
            'realizations' => [
                [
                    'request_detail_id' => $detail->id,
                    'amount'            => 50_000,
                    'notes'             => 'Draft dulu',
                    'attachments'       => [UploadedFile::fake()->image('bukti.jpg')],
                ],
            ],
        ];

        $this->actingAs($this->managerUser())
            ->post('/budget-request/' . $cr->id . '/save-realization-draft', $payload)
            ->assertRedirect(route('budget-request.realize-form', $cr->id));

        // Status TIDAK berubah — ini inti dari "Simpan" vs "Ajukan".
        $this->assertSame('PAID', $cr->fresh()->status);
        $this->assertDatabaseHas('budget_request_realizations', [
            'budget_request_id' => $cr->id,
            'request_detail_id' => $detail->id,
            'total'              => 50_000,
        ]);
        // Tidak boleh menulis log transisi status apa pun.
        $this->assertDatabaseMissing('request_status_logs', [
            'budget_request_id' => $cr->id,
            'action_type'       => 'realization_submitted',
        ]);
    }

    public function test_simpan_draft_tetap_wajib_lampiran(): void
    {
        $cr = $this->cr('PAID');
        $detail = RequestDetail::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Item uji',
            'amount'            => 50_000,
            'qty'               => 1,
            'total'             => 50_000,
            'status'            => 'INCLUDED',
        ]);

        // Settle > 0 TANPA lampiran — harus ditolak, sama seperti submit.
        $payload = [
            'realizations' => [
                [
                    'request_detail_id' => $detail->id,
                    'amount'            => 50_000,
                ],
            ],
        ];

        $this->actingAs($this->managerUser())
            ->post('/budget-request/' . $cr->id . '/save-realization-draft', $payload)
            ->assertRedirect();

        $this->assertSame('PAID', $cr->fresh()->status);
        $this->assertDatabaseMissing('budget_request_realizations', [
            'budget_request_id' => $cr->id,
        ]);
    }
}
