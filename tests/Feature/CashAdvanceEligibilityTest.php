<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\MasterPic;
use App\Support\CashAdvanceQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Syarat beneficiary Cash Advance (keputusan pemilik 2026-11-07, temuan F-5):
 * hanya INTERNAL ber-PIC dengan Token > 0. Token kosong/0 = CA diblokir.
 * Nominal tetap opsional. Dipakai simpan/ubah/submit lewat CashAdvanceQuota::error().
 */
class CashAdvanceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_beneficiary_eksternal_ditolak(): void
    {
        $ben = $this->beneficiary('EXTERNAL', $this->pic('5'));

        $this->assertStringContainsString('kategori Internal', CashAdvanceQuota::error($ben->id, 1_000));
    }

    public function test_beneficiary_tidak_ada_ditolak(): void
    {
        $this->assertNotNull(CashAdvanceQuota::error(null, 1_000));
        $this->assertNotNull(CashAdvanceQuota::error(999_999, 1_000));
    }

    public function test_internal_tanpa_pic_ditolak(): void
    {
        $ben = $this->beneficiary('INTERNAL', null);

        $this->assertStringContainsString('memiliki PIC', CashAdvanceQuota::error($ben->id, 1_000));
    }

    public function test_token_kosong_nol_atau_bukan_angka_ditolak(): void
    {
        foreach ([null, '', '0', 'abc'] as $token) {
            $ben = $this->beneficiary('INTERNAL', $this->pic($token));

            $this->assertStringContainsString('Token', (string) CashAdvanceQuota::error($ben->id, 1_000), 'token=' . var_export($token, true));
        }
    }

    public function test_token_terisi_lolos_walau_nominal_kosong(): void
    {
        $ben = $this->beneficiary('INTERNAL', $this->pic('3'));

        $this->assertNull(CashAdvanceQuota::error($ben->id, 1_000));
    }

    public function test_limit_nominal_tetap_berlaku(): void
    {
        $ben = $this->beneficiary('INTERNAL', $this->pic('3', 500_000));

        $this->assertStringContainsString('tidak mencukupi', CashAdvanceQuota::error($ben->id, 1_000_000));
        $this->assertNull(CashAdvanceQuota::error($ben->id, 400_000));
    }

    private function pic(?string $token, ?int $nominal = null): MasterPic
    {
        static $n = 0;
        $n++;

        return MasterPic::create([
            'type'    => 'internal',
            'pic'     => 'PIC Syarat ' . $n,
            'token'   => $token,
            'nominal' => $nominal,
            'status'  => 'ACTIVE',
        ]);
    }

    private function beneficiary(string $category, ?MasterPic $pic): Beneficiary
    {
        static $n = 0;
        $n++;

        return Beneficiary::create([
            'nickname'      => 'Ben Syarat ' . $n,
            'complete_name' => 'Beneficiary Syarat ' . $n,
            'category'      => $category,
            'pic_id'        => $pic?->id,
            'status'        => 'ACTIVE',
        ]);
    }
}
