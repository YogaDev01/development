<?php

namespace Tests\Feature;

use App\Services\RoleDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan ARC-04: daftar penerima notifikasi per tahap approval dulu
 * disalin di empat controller. Uji ini memastikan penggantinya memang
 * mengembalikan orang yang benar — termasuk perilaku lama yang harus tetap:
 * pencocokan nama peran tidak peka huruf besar/kecil, dan hanya user aktif.
 */
class RoleDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function directory(): RoleDirectory
    {
        return app(RoleDirectory::class);
    }

    public function test_mengembalikan_pemegang_peran(): void
    {
        $head = $this->userWithRole('head');
        $this->userWithRole('manager');

        $hasil = $this->directory()->heads();

        $this->assertCount(1, $hasil);
        $this->assertSame($head->id, $hasil->first()->id);
    }

    public function test_finance_bisa_menyertakan_cost_control(): void
    {
        $this->userWithRole('finance');
        $this->userWithRole('cost_control');

        $this->assertCount(1, $this->directory()->finance());
        $this->assertCount(2, $this->directory()->finance(withCostControl: true));
    }

    public function test_user_nonaktif_tidak_ikut(): void
    {
        $this->userWithRole('approver', ['status' => 'INACTIVE']);
        $aktif = $this->userWithRole('approver');

        $hasil = $this->directory()->approvers();

        $this->assertCount(1, $hasil);
        $this->assertSame($aktif->id, $hasil->first()->id);
    }

    public function test_nama_peran_tidak_peka_huruf_besar_kecil(): void
    {
        // Data peran di basis data tidak seragam kapitalisasinya; helper lama
        // menangani ini dengan LOWER(name) dan perilaku itu harus bertahan.
        $role = $this->role('head');
        $role->update(['name' => 'Head']);

        $this->userWithRole('head');

        $this->assertCount(1, $this->directory()->heads());
    }
}
