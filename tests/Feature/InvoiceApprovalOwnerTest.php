<?php

namespace Tests\Feature;

use App\Http\Controllers\TodoListController;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Approval invoice (VERIFIED -> APPROVED) milik FINANCE MANAGER, bukan Approver.
 *
 * Alurnya memang sudah begitu sejak awal — lihat komentar di atas route
 * invoice-cash-receipt.verify: "collection submit -> accounting verifikasi ->
 * finance_manager approve". Yang tertinggal adalah gerbang di TodoList, izin
 * route, dan tombol di dua Blade, sehingga tugasnya muncul di Todo List
 * Approver sementara Finance Manager tidak pernah melihatnya.
 *
 * Uji ini mengikat keempat titik itu sekaligus supaya tidak ada satu pun yang
 * diam-diam kembali ke Approver.
 */
class InvoiceApprovalOwnerTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function invoiceVerified(): Invoice
    {
        return $this->makeInvoice($this->makePoNumber($this->makeProject()), [
            'status'      => 'VERIFIED',
            'verified_at' => now(),
        ]);
    }

    /**
     * Key setiap antrean Todo List milik satu user.
     *
     * actionQueues() mengembalikan array berkunci ('queues', 'total', ...),
     * jadi daftar antreannya diambil dari 'queues' — mem-pluck langsung dari
     * hasilnya menghasilkan array kosong yang membuat assertNotContains lolos
     * tanpa menguji apa pun.
     */
    private function antrean($user): array
    {
        // BuildsCrWorklist membaca Auth::user()->effectiveRole(), jadi
        // actionQueues() tidak bisa dipanggil di luar sesi login — tanpa ini
        // ia melempar dan tertangkap jadi antrean kosong.
        $this->actingAs($user);

        $hasil = app(TodoListController::class)->actionQueues($user);

        $this->assertFalse($hasil['gagal'], 'buildWorklistData() gagal — antrean tidak bisa diuji.');

        return collect($hasil['queues'])->pluck('key')->all();
    }

    /* -------------------------------------------------- FINANCE MANAGER */

    public function test_finance_manager_melihat_tugas_approval_invoice(): void
    {
        $this->invoiceVerified();

        $this->assertContains(
            'invoice-approve',
            $this->antrean($this->userWithRole('finance_manager'))
        );
    }

    public function test_finance_manager_bisa_approve(): void
    {
        $invoice = $this->invoiceVerified();

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/approve')
            ->assertRedirect();

        $this->assertSame('APPROVED', $invoice->refresh()->status);
    }

    /* --------------------------------------------------------- APPROVER */

    public function test_approver_tidak_lagi_melihat_tugas_approval_invoice(): void
    {
        $this->invoiceVerified();

        $this->assertNotContains(
            'invoice-approve',
            $this->antrean($this->userWithRole('approver'))
        );
    }

    public function test_approver_ditolak_saat_mencoba_approve(): void
    {
        $invoice = $this->invoiceVerified();

        $this->actingAs($this->userWithRole('approver'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/approve')
            ->assertForbidden();

        // Yang penting bukan kode responsnya, tapi bahwa statusnya tidak pindah.
        $this->assertSame('VERIFIED', $invoice->refresh()->status);
    }

    /* ------------------------------------------------- TIDAK IKUT BERUBAH */

    public function test_superadmin_tetap_bisa_approve(): void
    {
        $invoice = $this->invoiceVerified();

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/approve')
            ->assertRedirect();

        $this->assertSame('APPROVED', $invoice->refresh()->status);
    }

    public function test_tahap_verifikasi_tetap_milik_finance_manager(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), [
            'status'       => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        $this->assertContains(
            'invoice-verify',
            $this->antrean($this->userWithRole('finance_manager'))
        );

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/verify')
            ->assertRedirect();

        $this->assertSame('VERIFIED', $invoice->refresh()->status);
    }

    public function test_tabel_ringkasan_menempatkan_approval_invoice_di_finance_manager(): void
    {
        $this->invoiceVerified();

        $html = $this->actingAs($this->userWithRole('superadmin'))
            ->get('/todo-list')->assertOk()->getContent();

        // Baris "Approval Invoice" dulu tercatat atas nama CEO.
        $this->assertMatchesRegularExpression(
            '/Finance Manager(?:(?!CEO).){0,600}?Approval Invoice/s',
            $html
        );
    }
}
