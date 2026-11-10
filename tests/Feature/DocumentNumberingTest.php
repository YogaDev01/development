<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\CostRegister;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Support\DocumentSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan BIZ-04: nomor dokumen dulu dibuat dari COUNT(*) + 1.
 */
class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_nomor_invoice_berurut_dan_tidak_berulang(): void
    {
        $poNumber = $this->makePoNumber($this->makeProject());
        $project  = $poNumber->project;

        $satu = Invoice::generateCode($project->id);
        $this->makeInvoice($poNumber, ['code' => $satu]);

        $dua = Invoice::generateCode($project->id);

        $this->assertNotSame($satu, $dua);
        $this->assertStringStartsWith('0001/', $satu);
        $this->assertStringStartsWith('0002/', $dua);
    }

    public function test_nomor_tidak_dipakai_ulang_setelah_dokumen_dihapus(): void
    {
        // Ini yang tidak bisa dilakukan penomoran berbasis COUNT: menghapus
        // dokumen membuat hitungan mundur, sehingga nomor berikutnya
        // menghidupkan kembali nomor yang sudah pernah terbit - dan nomor
        // dokumen keuangan tidak boleh pernah berarti dua hal.
        $poNumber = $this->makePoNumber($this->makeProject());
        $project  = $poNumber->project;

        $satu    = Invoice::generateCode($project->id);
        $invoice = $this->makeInvoice($poNumber, ['code' => $satu]);
        $invoice->delete();

        $berikutnya = Invoice::generateCode($project->id);

        $this->assertNotSame($satu, $berikutnya);
    }

    public function test_penghitung_disemai_dari_data_yang_sudah_ada(): void
    {
        // Sistem yang sudah berjalan punya nomor terakhir; penghitung harus
        // melanjutkan dari sana, bukan mulai dari 0001 dan bentrok.
        $poNumber = $this->makePoNumber($this->makeProject());

        $this->makeInvoice($poNumber, ['code' => '0001/UJI-0001/09/26']);
        $this->makeInvoice($poNumber, ['code' => '0002/UJI-0002/09/26']);

        $berikutnya = Invoice::generateCode($poNumber->project_id);

        $this->assertStringStartsWith('0003/', $berikutnya);
    }

    public function test_dua_pengambilan_nomor_selalu_berbeda(): void
    {
        $angka = [];

        for ($i = 0; $i < 25; $i++) {
            $angka[] = DocumentSequence::next('uji:rangkaian', fn () => 0);
        }

        $this->assertSame(range(1, 25), $angka);
        $this->assertCount(25, array_unique($angka));
    }

    public function test_rangkaian_baru_disemai_lalu_berlanjut(): void
    {
        $this->assertSame(42, DocumentSequence::next('uji:baru', fn () => 41));
        $this->assertSame(43, DocumentSequence::next('uji:baru', fn () => 999));
    }

    public function test_rangkaian_yang_baru_dibuat_pihak_lain_tidak_menimbulkan_galat(): void
    {
        /*
         | Tepat setelah pemeriksaan "baris sudah ada?", permintaan lain lebih
         | dulu membuat baris penghitung (counter 100). Penyisipan kita harus
         | diabaikan tanpa galat, dan nomor melanjutkan dari baris itu.
         | Deadlock gap-lock yang sesungguhnya butuh dua koneksi - diuji lewat
         | UAT konkurensi, bukan di sini.
         */
        $sudah = false;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$sudah) {
            if ($sudah || ! str_contains($query->sql, 'from `document_sequences`')) {
                return;
            }
            $sudah = true;
            \Illuminate\Support\Facades\DB::table('document_sequences')->insert([
                'key' => 'uji:balapan', 'counter' => 100, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->assertSame(101, DocumentSequence::next('uji:balapan', fn () => 0));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('document_sequences')->where('key', 'uji:balapan')->count());
    }

    public function test_nomor_cost_request_dan_surat_jalan_juga_berurut(): void
    {
        $satu = BudgetRequest::generateCode('0001');
        $dua  = BudgetRequest::generateCode('0001');
        $this->assertNotSame($satu, $dua);

        // Format baru: 'CR-' + MM + YY + PO(4) + urut(4) = 15 karakter.
        $this->assertSame('CR-' . now()->format('m') . now()->format('y') . '0001' . '0001', $satu);
        $this->assertSame(15, strlen($satu));

        $sj1 = DeliveryNote::generateCode();
        $sj2 = DeliveryNote::generateCode();
        $this->assertNotSame($sj1, $sj2);
        $this->assertStringStartsWith('SJ-0001/', $sj1);
        $this->assertStringStartsWith('SJ-0002/', $sj2);
    }

    public function test_prefix_kode_cost_request_mengikuti_tipe_request(): void
    {
        $body = now()->format('m') . now()->format('y') . '0001';

        $harapan = [
            'Cash Advance'  => 'CA',
            'Payment'       => 'PA',
            'Pettycash'     => 'PC',
            'Purchase'      => 'PR',
            'Reimbursement' => 'RB',
            'Tipe Lain'     => 'CR',
        ];

        // Nomor urut dipakai bersama semua tipe: 0001, 0002, ... tanpa kembar.
        $urut = 0;
        foreach ($harapan as $nama => $prefix) {
            $urut++;
            $kode = BudgetRequest::generateCode('0001', $this->makeRequestType($nama)->id);
            $this->assertSame($prefix . '-' . $body . str_pad((string) $urut, 4, '0', STR_PAD_LEFT), $kode);
        }
    }

    public function test_nomor_urut_bersambung_dari_kode_cr_lama(): void
    {
        $body = now()->format('m') . now()->format('y') . '0001';
        $this->makeCostRequest(['code' => 'CR-' . $body . '0007']);

        $kode = BudgetRequest::generateCode('0001', $this->makeRequestType('Payment')->id);

        $this->assertSame('PA-' . $body . '0008', $kode);
    }

    /**
     * Semaian penghitung harus memakai nomor TERTINGGI yang sudah terbit, bukan
     * jumlah baris. Dengan kode yang tidak rapat (ada lompatan), COUNT(*) memberi
     * nomor yang sudah dipakai — dan budget_request.code UNIK, jadi penyimpanan
     * berikutnya gagal. Sekaligus menjaga: nomor tidak kembar walau baris
     * sebelumnya belum disimpan (penghitung terkunci, bukan hitungan baris).
     */
    public function test_nomor_cr_tidak_kembar_walau_kode_lama_tidak_rapat(): void
    {
        $body = now()->format('m') . now()->format('y') . '0001';
        $this->makeCostRequest(['code' => 'CR-' . $body . '0003']);
        $this->makeCostRequest(['code' => 'PA-' . $body . '0009']);

        $pa = $this->makeRequestType('Payment')->id;

        // COUNT(*) = 2 -> 0003 (sudah terbit). MAX = 9 -> 0010.
        $this->assertSame('PA-' . $body . '0010', BudgetRequest::generateCode('0001', $pa));
        // Tanpa menyimpan baris apa pun di antaranya, nomor tetap maju.
        $this->assertSame('PA-' . $body . '0011', BudgetRequest::generateCode('0001', $pa));
        $this->assertSame('PA-' . $body . '0012', BudgetRequest::generateCode('0001', $pa));
    }

    /** Cermin aturan yang sama untuk nomor Expense (expense_no UNIK). */
    public function test_nomor_expense_menyambung_dari_nomor_tertinggi(): void
    {
        $prefix = 'EXP-' . now()->format('ym') . '-';

        // Satu nomor sudah terbit tanpa baris penghitung (mis. data hasil restore,
        // atau nomor di bawahnya sudah dihapus — Expense DRAFT boleh dihapus).
        CostRegister::create([
            'expense_no' => $prefix . '0007',
            'amount'     => 1_000,
            'notes'      => 'uji',
            'status'     => 'DRAFT',
            'input_by'   => $this->userWithRole('finance_manager')->id,
        ]);

        // COUNT(*) = 1 -> 0002 (bentrok kalau 0002 pernah terbit). MAX = 7 -> 0008.
        $this->assertSame($prefix . '0008', CostRegister::nextExpenseNo());
        $this->assertSame($prefix . '0009', CostRegister::nextExpenseNo());
    }

    public function test_ganti_prefix_hanya_bagian_huruf(): void
    {
        $pa = $this->makeRequestType('Payment')->id;

        $this->assertSame('PA-112601130060', BudgetRequest::codeWithTypePrefix('CA-112601130060', $pa));
        $this->assertSame('PA-112601130060', BudgetRequest::codeWithTypePrefix('CR-112601130060', $pa));
        // Format asing dibiarkan apa adanya.
        $this->assertSame('CR-UJI-0001', BudgetRequest::codeWithTypePrefix('CR-UJI-0001', $pa));
    }

    public function test_prefix_dikunci_setelah_cr_pernah_diajukan(): void
    {
        // markSubmitted() menulis RequestStatusLog, dan request_status_logs.acted_by
        // NOT NULL (migrasi 2026_06_06_190814) — jadi harus ada pelaku yang login,
        // sama seperti seluruh jalur transisi status di controller.
        $this->actingAs($this->userWithRole('manager'));

        $cr = $this->makeCostRequest(['status' => 'DRAFT']);
        $this->assertTrue($cr->codePrefixChangeable());

        $cr->markSubmitted();
        $cr->update(['status' => 'DRAFT']); // mis. setelah Revise
        $this->assertFalse($cr->fresh()->codePrefixChangeable());
    }
}
