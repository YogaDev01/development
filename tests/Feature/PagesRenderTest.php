<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Uji asap halaman: setiap layar utama benar-benar tergambar.
 *
 * Bukan menguji isinya, melainkan bahwa view-nya tidak melempar galat — kelas
 * kesalahan yang paling sering lolos di aplikasi dengan Blade sebesar ini:
 * satu variabel yang lupa dikirim controller, satu komponen yang argumennya
 * berubah, satu relasi yang dihapus. Semua itu hanya muncul saat halamannya
 * dibuka, dan halaman yang jarang dibuka bisa rusak berbulan-bulan tanpa ada
 * yang tahu.
 *
 * Dijalankan sebagai superadmin supaya yang diuji adalah penggambarannya,
 * bukan hak aksesnya (hak akses punya uji sendiri).
 */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public static function halaman(): array
    {
        return [
            'dashboard'          => ['/'],
            'purchase order'     => ['/project'],
            'budget plan'        => ['/budget-plan'],
            'cost request'       => ['/budget-request'],
            'invoice'            => ['/invoice-cash-receipt'],
            'daily transaction'  => ['/daily-transaction'],
            'project monitoring' => ['/project-monitoring'],
            'todo list'          => ['/todo-list'],
            'finance summary'    => ['/finance-summary'],
            'customer'           => ['/customer'],
            'pic & beneficiary'  => ['/pic-beneficiary'],
            'bank'               => ['/bank'],
            'cluster'            => ['/cluster'],
            'cost center'        => ['/cost-center'],
            'source fund'        => ['/source-fund'],
            'user'               => ['/user'],
            'role'               => ['/role'],
            'access group'       => ['/accessgroup'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('halaman')]
    public function test_halaman_tergambar(string $url): void
    {
        $response = $this->actingAs($this->userWithRole('superadmin'))->get($url);

        $response->assertOk();
    }

    public function test_form_tambah_invoice_tergambar(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/invoice-cash-receipt/create')
            ->assertOk();
    }

    public function test_halaman_detail_invoice_tergambar(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()));

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/invoice-cash-receipt/' . $invoice->id)
            ->assertOk();
    }

    public function test_daftar_dengan_data_tergambar(): void
    {
        // Daftar kosong dan daftar berisi melewati cabang Blade yang berbeda
        // (keadaan kosong vs baris data); keduanya perlu dilalui.
        $this->makeCostRequest();
        $this->makeInvoice($this->makePoNumber($this->makeProject()));

        $admin = $this->userWithRole('superadmin');

        $this->actingAs($admin)->get('/budget-request')->assertOk();
        $this->actingAs($admin)->get('/invoice-cash-receipt')->assertOk();
        $this->actingAs($admin)->get('/project')->assertOk();
    }
}
