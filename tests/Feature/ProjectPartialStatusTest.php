<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Status header PO dihitung ulang dari agregat status No. PO:
 * ACTIVE / PROGRESS_DONE <-> PARTIALLY_INVOICED <-> PARTIALLY_PAID -> PAID.
 */
class ProjectPartialStatusTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function po(string $status, array $statusNoPo): Project
    {
        $po = $this->makeProject(['status' => $status]);
        foreach ($statusNoPo as $s) {
            $this->makePoNumber($po, ['status' => $s]);
        }

        return $po->fresh();
    }

    public function test_sebagian_ditagih_menjadi_partially_invoiced_dari_active(): void
    {
        $po = $this->po('ACTIVE', ['INVOICED', 'RELEASED']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('PARTIALLY_INVOICED', $po->fresh()->status);
    }

    public function test_sebagian_ditagih_menjadi_partially_invoiced_dari_progress_done(): void
    {
        $po = $this->po('PROGRESS_DONE', ['INVOICED', 'BAST_SUBMITTED']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('PARTIALLY_INVOICED', $po->fresh()->status);
    }

    public function test_sebagian_paid_menjadi_partially_paid(): void
    {
        $po = $this->po('PARTIALLY_INVOICED', ['PAID', 'INVOICED']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('PARTIALLY_PAID', $po->fresh()->status);
    }

    public function test_semua_paid_menjadi_paid(): void
    {
        $po = $this->po('PARTIALLY_PAID', ['PAID', 'PAID']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('PAID', $po->fresh()->status);
    }

    public function test_semua_ditagih_belum_dibayar_kembali_ke_progress_done(): void
    {
        $po = $this->po('PARTIALLY_INVOICED', ['INVOICED', 'INVOICED']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('PROGRESS_DONE', $po->fresh()->status);
    }

    public function test_invoice_dibatalkan_kembali_ke_active(): void
    {
        $po = $this->po('PARTIALLY_INVOICED', ['BAST_SUBMITTED', 'RELEASED']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('ACTIVE', $po->fresh()->status);
    }

    public function test_status_di_luar_alur_berjalan_tidak_disentuh(): void
    {
        $po = $this->po('CLOSING', ['PAID', 'RELEASED']);
        $po->syncStatusFromPoNumbers();

        $this->assertSame('CLOSING', $po->fresh()->status);
    }

    public function test_label_status_baru(): void
    {
        $this->assertSame('Partially Invoiced', Project::statusLabelFor('PARTIALLY_INVOICED'));
        $this->assertSame('Partially Paid', Project::statusLabelFor('PARTIALLY_PAID'));
    }
}
