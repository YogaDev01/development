<?php

namespace Tests\Feature;

use App\Models\BudgetRequestRealization;
use App\Models\InvoiceAttachment;
use App\Models\RealizationDetailAttachment;
use App\Models\RequestDetail;
use App\Models\RequestDetailAttachment;
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

    /*
    |--------------------------------------------------------------------------
    | LAMPIRAN COST REQUEST — per item & per item realisasi
    |--------------------------------------------------------------------------
    |
    | FileAccess menelusuri pemilik berkas lewat baris induknya. Penelusuran itu
    | dulu ditulis sebagai join dengan nama tabel yang diketik ulang, dan salah
    | satunya keliru: `request_details` padahal tabelnya `request_detail`.
    | Setiap permintaan lampiran item CR mati dengan "Base table or view not
    | found" — keluar sebagai 500, sehingga gambar tidak pernah termuat di
    | viewer meski berkasnya ada dan penggunanya berhak.
    |
    | Kedua cabang di bawah tidak pernah punya uji sama sekali; itu sebabnya
    | kekeliruan nama tabel bisa lolos.
    */

    /** CR dengan satu baris item — cukup untuk menggantungkan lampiran. */
    private function detailCostRequest(): RequestDetail
    {
        $cr = $this->makeCostRequest();

        return RequestDetail::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Item uji',
            'amount'            => 100_000,
            'qty'               => 1,
            'total'             => 100_000,
            'status'            => 'INCLUDED',
        ]);
    }

    public function test_finance_bisa_membuka_lampiran_item_cost_request(): void
    {
        $detail = $this->detailCostRequest();

        $this->putFile('cost_request_detail_attachments/bukti.jpg');
        RequestDetailAttachment::create([
            'request_detail_id' => $detail->id,
            'original_name'     => 'bukti.jpg',
            'stored_name'       => 'bukti.jpg',
            'path'              => 'cost_request_detail_attachments/bukti.jpg',
            'mime_type'         => 'image/jpeg',
            'size'              => 7,
        ]);

        $this->actingAs($this->userWithRole('finance'))
            ->get('/storage/cost_request_detail_attachments/bukti.jpg')
            ->assertOk();
    }

    public function test_manager_di_luar_access_group_ditolak_lampiran_item_cost_request(): void
    {
        $detail = $this->detailCostRequest();

        $this->putFile('cost_request_detail_attachments/rahasia.jpg');
        RequestDetailAttachment::create([
            'request_detail_id' => $detail->id,
            'original_name'     => 'rahasia.jpg',
            'stored_name'       => 'rahasia.jpg',
            'path'              => 'cost_request_detail_attachments/rahasia.jpg',
            'mime_type'         => 'image/jpeg',
            'size'              => 7,
        ]);

        // Perbaikannya tidak boleh membuat gerbangnya ikut terbuka: manager
        // tanpa access group tetap tidak berhak.
        $this->actingAs($this->userWithRole('manager'))
            ->get('/storage/cost_request_detail_attachments/rahasia.jpg')
            ->assertForbidden();
    }

    public function test_lampiran_item_tanpa_baris_induk_ditolak(): void
    {
        $this->putFile('cost_request_detail_attachments/yatim.jpg');

        $this->actingAs($this->userWithRole('finance'))
            ->get('/storage/cost_request_detail_attachments/yatim.jpg')
            ->assertForbidden();
    }

    public function test_finance_bisa_membuka_lampiran_item_realisasi(): void
    {
        $cr = $this->makeCostRequest();

        $realization = BudgetRequestRealization::create([
            'budget_request_id' => $cr->id,
            'description'       => 'Realisasi uji',
            'amount'            => 100_000,
            'qty'               => 1,
            'total'             => 100_000,
        ]);

        $this->putFile('cost_request_realization_details/nota.jpg');
        RealizationDetailAttachment::create([
            'realization_id' => $realization->id,
            'original_name'  => 'nota.jpg',
            'stored_name'    => 'nota.jpg',
            'path'           => 'cost_request_realization_details/nota.jpg',
            'mime_type'      => 'image/jpeg',
            'size'           => 7,
        ]);

        $this->actingAs($this->userWithRole('finance'))
            ->get('/storage/cost_request_realization_details/nota.jpg')
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Lampiran Expense (cost_register_attachments)
    |--------------------------------------------------------------------------
    | Keterlihatan berkas = keterlihatan halamannya. Drawer Expense menampilkan
    | daftar lampiran ke setiap pemegang menu 'cost-register', jadi berkasnya
    | harus bisa dibuka pemegang menu itu — bukan hanya menu Treasury. Dulu
    | hanya menu Treasury, sehingga CEO/approver melihat tautan yang 403 dan
    | menyetujui Expense tanpa bisa membuka buktinya.
    */

    public function test_pemegang_menu_expense_bisa_membuka_lampiran_pengajuan(): void
    {
        $this->putFile('cost_register_attachments/kuitansi.pdf');

        // CEO/approver: hanya menu Expense, TANPA menu Treasury.
        $this->giveMenuAccess($this->role('approver'), 'cost-register');

        $this->actingAs($this->userWithRole('approver'))
            ->get('/storage/cost_register_attachments/kuitansi.pdf')
            ->assertOk();
    }

    public function test_pemegang_menu_treasury_tetap_bisa_membuka_lampiran_pengajuan(): void
    {
        $this->putFile('cost_register_attachments/kuitansi.pdf');

        $this->giveMenuAccess($this->role('treasury'), 'finance-daily-transaction');

        $this->actingAs($this->userWithRole('treasury'))
            ->get('/storage/cost_register_attachments/kuitansi.pdf')
            ->assertOk();
    }

    public function test_tanpa_menu_expense_maupun_treasury_lampiran_pengajuan_ditolak(): void
    {
        // Pengaman: perbaikan di atas melebarkan akses ke menu Expense saja,
        // bukan ke semua pengguna yang login.
        $this->putFile('cost_register_attachments/kuitansi.pdf');

        $this->actingAs($this->userWithRole('guest'))
            ->get('/storage/cost_register_attachments/kuitansi.pdf')
            ->assertForbidden();
    }

    public function test_pemegang_menu_expense_bisa_membuka_bukti_realisasi(): void
    {
        $this->putFile('cost_register_settlement_attachments/nota-realisasi.jpg');

        $this->giveMenuAccess($this->role('finance_manager'), 'cost-register');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->get('/storage/cost_register_settlement_attachments/nota-realisasi.jpg')
            ->assertOk();
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
