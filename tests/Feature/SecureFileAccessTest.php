<?php

namespace Tests\Feature;

use App\Models\InvoiceAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Lampiran hanya boleh keluar lewat route ber-otentikasi.
 *
 * Uji ini menjaga perbaikan SEC-02: dulu seluruh berkas ada di disk "public"
 * dan bisa diunduh siapa saja yang memegang URL-nya. Kalau suatu saat ada yang
 * mengembalikan disk 'public', menyalakan 'serve' => true pada disk local, atau
 * menjalankan storage:link, salah satu uji di bawah akan gagal.
 */
class SecureFileAccessTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk palsu: uji tidak boleh menulis ke storage sungguhan.
        Storage::fake('secure');
    }

    private function putFile(string $path, string $isi = 'rahasia'): void
    {
        Storage::disk('secure')->put($path, $isi);
    }

    public function test_tamu_tidak_bisa_mengunduh_lampiran(): void
    {
        $this->putFile('invoice_attachments/uji.pdf');

        $this->get('/storage/invoice_attachments/uji.pdf')
            ->assertRedirect('/login');
    }

    public function test_berkas_tanpa_baris_pemilik_ditolak(): void
    {
        // Berkas yatim (sisa penghapusan baris): tidak ada yang bisa
        // memastikan siapa yang berhak, jadi tidak diberikan ke siapa pun.
        $this->putFile('invoice_attachments/yatim.pdf');

        $this->actingAs($this->userWithRole('finance'))
            ->get('/storage/invoice_attachments/yatim.pdf')
            ->assertForbidden();
    }

    public function test_finance_bisa_membuka_lampiran_invoice(): void
    {
        $project = $this->makeProject();
        $invoice = $this->makeInvoice($this->makePoNumber($project));

        $this->putFile('invoice_attachments/sah.pdf');
        InvoiceAttachment::create([
            'invoice_id' => $invoice->id,
            'title'      => 'Lampiran',
            'file_path'  => 'invoice_attachments/sah.pdf',
            'file_name'  => 'sah.pdf',
        ]);

        $this->actingAs($this->userWithRole('finance'))
            ->get('/storage/invoice_attachments/sah.pdf')
            ->assertOk();
    }

    public function test_manager_di_luar_access_group_ditolak(): void
    {
        $project = $this->makeProject();
        $invoice = $this->makeInvoice($this->makePoNumber($project));

        $this->putFile('invoice_attachments/milik-orang-lain.pdf');
        InvoiceAttachment::create([
            'invoice_id' => $invoice->id,
            'title'      => 'Lampiran',
            'file_path'  => 'invoice_attachments/milik-orang-lain.pdf',
            'file_name'  => 'milik-orang-lain.pdf',
        ]);

        // Manager tanpa access group tidak punya proyek yang boleh dilihat.
        $this->actingAs($this->userWithRole('manager'))
            ->get('/storage/invoice_attachments/milik-orang-lain.pdf')
            ->assertForbidden();
    }

    public function test_path_traversal_ditolak(): void
    {
        $this->actingAs($this->userWithRole('finance'))
            ->get('/storage/' . urlencode('../.env'))
            ->assertNotFound();
    }

    public function test_disk_publik_tidak_dipakai_untuk_lampiran(): void
    {
        // Pengaman regresi: 'links' kosong berarti storage:link tidak akan
        // membuat public/storage, dan disk local tidak melayani route sendiri.
        $this->assertSame([], config('filesystems.links'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }
}
