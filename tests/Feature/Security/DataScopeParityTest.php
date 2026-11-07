<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\ProjectController;
use App\Models\AccessGroup;
use App\Models\AccessGroupDetail;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Fase 4 — mesin batas data (App\Support\DataScope) harus menghasilkan baris
 * yang SAMA PERSIS dengan fungsi scope lama untuk setiap role, dengan dan
 * tanpa Access Group, termasuk View As. Mesin belum dipasang ke controller.
 */
class DataScopeParityTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private const ROLE_TAMBAHAN = ['manager', 'supervisor', 'staff', 'teknisi', 'hr', 'head', 'role_tanpa_hak'];

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $p1 = $this->makeProject();
        $p2 = $this->makeProject();

        $cc = fn (string $code, string $type) => CostCenter::create(['code' => $code, 'name' => $code, 'type' => $type, 'status' => 'ACTIVE']);
        $ovh1 = $cc('OVH-1', 'OVERHEAD');
        $ovh2 = $cc('OVH-2', 'OVERHEAD');
        $tax  = $cc('TAX-1', 'TAX');
        $pay  = $cc('PAY-1', 'PAYROLL');

        $plans = [
            $this->makeBudgetPlan($p1),
            $bpP2 = $this->makeBudgetPlan($p2),
            $this->makeBudgetPlan(null, ['project_id' => null, 'cost_center_id' => $ovh1->id]),
            $this->makeBudgetPlan(null, ['project_id' => null, 'cost_center_id' => $ovh2->id]),
            $this->makeBudgetPlan(null, ['project_id' => null, 'cost_center_id' => $tax->id]),
            $this->makeBudgetPlan(null, ['project_id' => null, 'cost_center_id' => $pay->id]),
        ];
        foreach ($plans as $bp) {
            $this->makeCostRequest(['budget_plan' => $bp]);
        }

        $this->makeInvoice($this->makePoNumber($p1));
        $this->makeInvoice($this->makePoNumber($p2));

        $ag = AccessGroup::create(['name' => 'AG Paritas']);
        AccessGroupDetail::create(['access_group_id' => $ag->id, 'project_id' => $p1->id]);
        AccessGroupDetail::create(['access_group_id' => $ag->id, 'cost_center_id' => $ovh1->id]);

        $nama = self::ROLE_TAMBAHAN;
        foreach (DataScope::modules() as $def) {
            foreach ($def['dimensions'] as $dim) {
                foreach ($dim['seed'] ?? [] as $roles) {
                    $nama = array_merge($nama, $roles);
                }
            }
        }

        foreach (array_unique($nama) as $r) {
            $denganAg = $this->userWithRole($r, ['access_group_id' => $ag->id]);
            $tanpaAg  = $this->userWithRole($r);
            $this->users[$r . '+ag'] = $denganAg;
            $this->users[$r . '-ag'] = $tanpaAg;

            // CR milik sendiri pada PO di luar Access Group (jalur "tanpa AG: milik sendiri").
            $this->makeCostRequest(['budget_plan' => $bpP2, 'applicant_id' => $tanpaAg->id]);
        }

        DataScope::syncSeed(true);
    }

    public function test_parity_semua_role_dengan_dan_tanpa_access_group(): void
    {
        $beda = [];
        foreach ($this->users as $label => $user) {
            $beda = array_merge($beda, $this->bandingkan($user, $label));
        }

        $this->assertSame([], $beda);
    }

    /** Budget Plan memakai role asli, Cost Request role View As — keduanya dipertahankan. */
    public function test_parity_saat_superadmin_view_as(): void
    {
        $admin   = $this->users['superadmin+ag'];
        $beda    = [];

        foreach (['manager', 'head', 'hr', 'finance'] as $preview) {
            session(['view_as_role_id' => $this->role($preview)->id]);
            $beda = array_merge($beda, $this->bandingkan($admin->fresh(), 'superadmin view-as ' . $preview));
        }
        session()->forget('view_as_role_id');

        $this->assertSame([], $beda);
    }

    /** Penjaga agar uji parity tidak lulus secara kosong: hasilnya memang berbeda per role. */
    public function test_data_uji_membedakan_role(): void
    {
        $jumlah = function (string $label, string $modul, string $kelas) {
            $q = $kelas::query();
            request()->attributes->replace([]);
            DataScope::apply($q, $this->users[$label], $modul);

            return $q->count();
        };

        $totalCr = BudgetRequest::count();
        $this->assertSame($totalCr, $jumlah('superadmin+ag', 'cost_request', BudgetRequest::class));
        $this->assertLessThan($totalCr, $jumlah('manager+ag', 'cost_request', BudgetRequest::class));
        $this->assertSame(1, $jumlah('manager-ag', 'cost_request', BudgetRequest::class), 'Tanpa AG: hanya CR milik sendiri.');
        $this->assertGreaterThan($jumlah('manager+ag', 'budget_plan', BudgetPlan::class), $jumlah('hr+ag', 'budget_plan', BudgetPlan::class), 'HR melihat payroll.');
        $this->assertSame(1, $jumlah('head+ag', 'invoice', Invoice::class));
        $this->assertSame(2, $jumlah('finance+ag', 'invoice', Invoice::class));
    }

    /**
     * Daftar role di config/data_scopes.php harus sama dengan konstanta kode.
     * (Daftar early-return scopeByRole tertulis inline, dijaga oleh uji parity.)
     */
    public function test_seed_sama_dengan_konstanta_kode(): void
    {
        $konst = fn (string $kelas, string $nama) => (new \ReflectionClassConstant($kelas, $nama))->getValue();
        $seed  = fn (string $mod, string $dim, string $nilai) => DataScope::modules()[$mod]['dimensions'][$dim]['seed'][$nilai];
        $sama  = function (array $a, array $b, string $pesan) {
            sort($a);
            sort($b);
            $this->assertSame(array_values(array_unique($a)), array_values(array_unique($b)), $pesan);
        };

        $bp = \App\Http\Controllers\BudgetPlanController::class;
        $sama($konst($bp, 'BP_PROJECT_PRIVILEGED_ROLES'), $seed('budget_plan', 'project', 'all'), 'BP proyek');
        $sama($konst($bp, 'BP_OVERHEAD_PRIVILEGED_ROLES'), $seed('budget_plan', 'overhead', 'all'), 'BP overhead');
        $sama($konst($bp, 'BP_PAYROLL_ROLES'), $seed('budget_plan', 'payroll', 'all'), 'BP payroll');
        $sama(\App\Support\AccessRoles::PO_FULL_SCOPE, $seed('purchase_order', 'project', 'all'), 'PO');

        // CR: role payroll & overhead istimewa harus tercakup seed 'all' (atau daftar early-return).
        $cr = GlobalSearchController::class;
        $this->assertSame([], array_diff($konst($cr, 'PAYROLL_ROLES'), $seed('cost_request', 'payroll', 'all')));
        $this->assertSame([], array_diff($konst($cr, 'OVERHEAD_PRIVILEGED_ROLES'), $seed('cost_request', 'overhead', 'all')));
    }

    public function test_role_baru_tanpa_isi_mendapat_default(): void
    {
        $baru = $this->userWithRole('auditor_baru');

        $this->assertSame('access_group', DataScope::valueFor($baru, 'cost_request', 'project'));
        $this->assertSame('none', DataScope::valueFor($baru, 'cost_request', 'payroll'));
        // Parity invoice: role selain manager/head melihat semua (temuan, bukan aturan baru).
        $this->assertSame('all', DataScope::valueFor($baru, 'invoice', 'project'));
    }

    public function test_sinkron_tidak_menimpa_pengaturan_yang_ada(): void
    {
        $head = $this->role('head');
        DB::table('role_data_scopes')->where('role_id', $head->id)->where('module', 'invoice')->update(['scope' => 'all']);

        $this->assertSame(0, DataScope::syncSeed(true)['rows']);
        $this->assertSame('all', DB::table('role_data_scopes')->where('role_id', $head->id)->where('module', 'invoice')->value('scope'));
    }

    public function test_tab_batas_data_di_modal_role(): void
    {
        $head = $this->role('head');

        $json = $this->actingAs($this->users['superadmin+ag'])
            ->getJson('/role/' . $head->id . '/edit-data')->assertOk()->json('data_scopes');

        $this->assertTrue($json['ready']);
        $invoice = collect($json['modules'])->firstWhere('key', 'invoice');
        $this->assertSame('access_group', $invoice['dimensions'][0]['value']);
        $this->assertNotEmpty($json['fixed']);
    }

    public function test_modul_tak_dikenal_ditolak(): void
    {
        $q = BudgetRequest::query();
        DataScope::apply($q, $this->users['superadmin+ag'], 'tidak_ada');

        $this->assertSame(0, $q->count());
    }

    /** @return list<string> selisih */
    private function bandingkan(User $user, string $label): array
    {
        Auth::setUser($user);
        request()->attributes->replace([]); // memo per request: tiap user dihitung bersih

        $cari = app(GlobalSearchController::class);
        $beda = [];

        $cek = function (string $modul, $lama, $baru) use (&$beda, $label) {
            $lama = $lama->pluck('id')->sort()->values()->all();
            $baru = $baru->pluck('id')->sort()->values()->all();
            if ($lama !== $baru) {
                $beda[] = "$modul [$label] lama=" . json_encode($lama) . ' baru=' . json_encode($baru);
            }
        };

        // Cost Request
        $lama = BudgetRequest::query();
        (new \ReflectionMethod($cari, 'scopeByRole'))->invoke($cari, $lama);
        $baru = BudgetRequest::query();
        DataScope::apply($baru, $user, 'cost_request');
        $cek('cost_request', $lama->get(['id']), $baru->get(['id']));

        // Budget Plan
        $lama = BudgetPlan::query();
        $cari->scopeVisibleTo($lama);
        $baru = BudgetPlan::query();
        DataScope::apply($baru, $user, 'budget_plan');
        $cek('budget_plan', $lama->get(['id']), $baru->get(['id']));

        // Purchase Order — sama dengan ProjectController::index()
        $po  = app(ProjectController::class);
        $ids = (new \ReflectionMethod($po, 'accessiblePoIds'))->invoke($po);
        $lama = Project::when(is_array($ids), fn ($q) => $q->whereIn('id', $ids ?: [0]));
        $baru = Project::query();
        DataScope::apply($baru, $user, 'purchase_order');
        $cek('purchase_order', $lama->get(['id']), $baru->get(['id']));

        // Invoice
        $lama = Invoice::query();
        $cari->scopeInvoiceVisibleTo($lama);
        $baru = Invoice::query();
        DataScope::apply($baru, $user, 'invoice');
        $cek('invoice', $lama->get(['id']), $baru->get(['id']));

        return $beda;
    }
}
