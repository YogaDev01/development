<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
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
}
