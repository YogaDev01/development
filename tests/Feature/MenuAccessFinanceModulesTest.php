<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Cost Register, Cashflow (cash-management) & Cash Receipt diatur lewat
 * Menu Access (2026-11-09): centang di modal "Atur Menu Access" = halaman
 * bisa dibuka, dan sebaliknya. Aksi tulis Cost Register tetap butuh role
 * finance group; approve/revise tetap dijaga role (dipakai Todo List).
 */
class MenuAccessFinanceModulesTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_cost_register_tampil_di_modal_menu_access(): void
    {
        $this->giveMenuAccess($this->role('approver'), 'cost-register', 'cash-management', 'cash-receipt');

        $tree  = Navigation::accessTree(MenuItem::all());
        $kunci = collect($tree['apps'])
            ->flatMap(fn ($app) => $app['sections'])
            ->flatMap(fn ($s) => $s['items'])
            ->pluck('key');

        $this->assertTrue($kunci->contains('cost-register'));
        $this->assertTrue($kunci->contains('cash-management'));
        $this->assertTrue($kunci->contains('cash-receipt'));
    }

    public function test_halaman_cost_register_mengikuti_centang_menu(): void
    {
        $approver = $this->userWithRole('approver');
        $this->actingAs($approver)->get('/expense')->assertForbidden();

        $this->giveMenuAccess($this->role('approver'), 'cost-register');
        $this->actingAs($approver->fresh())->get('/expense')->assertOk();
    }

    public function test_menu_cost_register_tidak_membuka_aksi_tulis_untuk_role_non_finance(): void
    {
        $this->giveMenuAccess($this->role('approver'), 'cost-register');

        $this->actingAs($this->userWithRole('approver'))->get('/expense/create')->assertForbidden();
    }

    // Expense (2026-11-10): pembuat hanya Finance Manager; role finance tetap ditolak walau menu dicentang.
    // (finance_manager sudah diberi menu cost-register oleh migrasi 2026_11_09_000100.)
    public function test_hanya_finance_manager_yang_bisa_membuat_expense(): void
    {
        $this->giveMenuAccess($this->role('finance_manager'), 'cost-register');
        $this->actingAs($this->userWithRole('finance_manager'))->get('/expense/create')->assertOk();

        $this->giveMenuAccess($this->role('finance'), 'cost-register');
        $this->actingAs($this->userWithRole('finance'))->get('/expense/create')->assertForbidden();
    }

    public function test_cashflow_dan_cash_receipt_mengikuti_centang_menu(): void
    {
        // Finance tanpa centang: dulu lolos lewat role:, sekarang ditolak.
        $finance = $this->userWithRole('finance');
        $this->actingAs($finance)->get('/cash-management')->assertForbidden();
        $this->actingAs($finance)->get('/cash-receipt')->assertForbidden();

        // Role di luar daftar lama (mis. accounting) bisa dibuka lewat centang.
        $this->giveMenuAccess($this->role('accounting'), 'cash-management', 'cash-receipt');
        $accounting = $this->userWithRole('accounting');
        $this->actingAs($accounting)->get('/cash-management')->assertOk();
        $this->actingAs($accounting)->get('/cash-receipt')->assertOk();
    }

    public function test_migrasi_menyamakan_centang_dengan_daftar_role_lama(): void
    {
        $finance    = $this->role('finance');
        $guest      = $this->role('guest');
        $collection = $this->role('collection');
        // Guest terlanjur dicentang Cashflow (dulu tetap 403 di route).
        $this->giveMenuAccess($guest, 'cash-management');

        $migrasi = require database_path('migrations/2026_11_09_000100_menu_access_cost_register_cashflow_cash_receipt.php');
        ob_start();
        $migrasi->up();
        $migrasi->up(); // aman diulang
        ob_end_clean();

        $punya = fn ($role, $key) => DB::table('role_menu_items')
            ->join('menu_items', 'menu_items.id', '=', 'role_menu_items.menu_item_id')
            ->where('role_menu_items.role_id', $role->id)
            ->where('menu_items.key', $key)
            ->count();

        $this->assertSame(1, $punya($finance, 'cost-register'));
        $this->assertSame(1, $punya($finance, 'cash-management'));
        $this->assertSame(1, $punya($finance, 'cash-receipt'));
        $this->assertSame(1, $punya($collection, 'cash-receipt'));
        $this->assertSame(0, $punya($collection, 'cash-management'));
        $this->assertSame(0, $punya($collection, 'cost-register'));
        $this->assertSame(0, $punya($guest, 'cash-management'));
    }
}
