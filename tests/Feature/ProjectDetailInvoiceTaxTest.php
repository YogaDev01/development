<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Tab Invoice di modal Detail PO menampilkan PPN & PPH apa adanya dari nilai
 * tersimpan di invoice (tidak dihitung ulang di showJson).
 */
class ProjectDetailInvoiceTaxTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    public function test_show_json_mengirim_ppn_dan_pph_tersimpan_per_invoice(): void
    {
        $project = $this->makeProject();
        $poNumber = $this->makePoNumber($project);
        $this->makeInvoice($poNumber, [
            'subtotal'        => 100_000_000,
            'tax_amount'      => 11_000_000,
            'withholding_tax' => 2_000_000,
            'captured_total'  => 109_000_000,
        ]);

        $user = $this->userWithRole('superadmin');

        $json = $this->actingAs($user)
            ->getJson(route('project.show-json', $project->id))
            ->assertOk()
            ->json();

        $inv = $json['invoices'][0];

        $this->assertSame(11_000_000, $inv['tax_amount']);
        $this->assertSame('11.000.000', $inv['tax_amount_formatted']);
        $this->assertSame(2_000_000, $inv['withholding_tax']);
        $this->assertSame('2.000.000', $inv['withholding_tax_formatted']);
        $this->assertSame(109_000_000, $inv['captured_total']);
    }
}
