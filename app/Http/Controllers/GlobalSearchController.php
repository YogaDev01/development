<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCrWorklist;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Pencarian lintas modul untuk command palette (Ctrl+K).
 *
 * Kelas ini TIDAK punya aturan visibilitas sendiri. Untuk tiap modul ia
 * memakai dua hal yang sudah ada:
 *
 *   1. Gerbang halaman — kunci menu yang sama dengan middleware `menu:` pada
 *      route index modul itu, dicek dengan logika yang sama seperti
 *      App\Http\Middleware\CheckMenu. Modul yang halamannya tidak bisa dibuka
 *      user ini tidak ikut dicari sama sekali.
 *
 *   2. Scoping baris — method scoping milik modul itu sendiri
 *      (BuildsCrWorklist::scopeByRole, BudgetPlanController::scopeVisibleTo,
 *      InvoiceCashReceiptController::scopeVisibleTo). Purchase Order dan
 *      Treasury memang tidak punya scoping baris di index-nya, dan di sini
 *      pun tidak ditambahkan — hasil pencarian harus sama persis dengan isi
 *      halamannya, tidak lebih luas dan tidak lebih sempit.
 *
 * Hasilnya hanya membaca; tidak ada aksi yang bisa dijalankan dari sini.
 */
class GlobalSearchController extends Controller
{
    use BuildsCrWorklist;

    /** Maksimum baris per modul, supaya daftar tetap terbaca sekali lihat. */
    private const PER_MODUL = 5;

    /** Minimal panjang kata kunci — di bawah ini hasilnya terlalu umum. */
    private const MIN_HURUF = 2;

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (Str::length($q) < self::MIN_HURUF) {
            return response()->json(['groups' => [], 'query' => $q]);
        }

        /** @var User $user */
        $user = Auth::user();

        $groups = [];

        foreach ([
            'costRequest'      => ['finance-cost-request', 'project-cost-request'],
            'purchaseOrder'    => ['list-po'],
            'budgetPlan'       => ['finance-budgeting', 'project-budgeting'],
            'invoice'          => ['invoice-cash-receipt'],
            'dailyTransaction' => ['finance-daily-transaction'],
        ] as $metode => $kunciMenu) {
            if (! $this->bolehBukaMenu($user, $kunciMenu)) {
                continue;
            }

            try {
                $grup = $this->{$metode}($q);
            } catch (\Throwable $e) {
                // Satu modul yang datanya cacat tidak boleh membuat seluruh
                // pencarian gagal — modul lain tetap dikembalikan.
                \Illuminate\Support\Facades\Log::error('GlobalSearch gagal pada ' . $metode, [
                    'user_id' => $user->id, 'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (! empty($grup['items'])) {
                $groups[] = $grup;
            }
        }

        return response()->json(['groups' => $groups, 'query' => $q]);
    }

    /**
     * Pengecekan akses menu, disalin dari App\Http\Middleware\CheckMenu —
     * bukan aturan baru. Lolos bila role punya SALAH SATU kunci.
     */
    private function bolehBukaMenu(User $user, array $kunci): bool
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        if (! $user->role) {
            return false;
        }

        if (strtolower($user->role->name) === 'superadmin') {
            return true;
        }

        foreach ($kunci as $k) {
            if ($user->role->hasMenu($k)) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */

    private function costRequest(string $q): array
    {
        $query = BudgetRequest::with('beneficiary')
            ->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));

        // Scoping milik modul Cost Request sendiri (trait BuildsCrWorklist).
        $this->scopeByRole($query);

        return [
            'key'   => 'cost-request',
            'label' => 'Cost Request',
            'icon'  => 'fas fa-file-invoice-dollar',
            'items' => $query->latest()->limit(self::PER_MODUL)->get()
                ->map(fn ($cr) => $this->baris(
                    $cr->code,
                    $cr->description,
                    $cr->status,
                    'budget-request',
                    url('/budget-request?open=' . $cr->id)
                ))->all(),
        ];
    }

    private function purchaseOrder(string $q): array
    {
        // ProjectController::index() tidak menyaring baris per role; di sini
        // pun tidak, supaya hasilnya sama dengan isi halaman Purchase Order.
        $items = Project::with('customer')
            ->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->latest()->limit(self::PER_MODUL)->get()
            ->map(fn ($p) => $this->baris(
                $p->code,
                $p->name,
                $p->status,
                'project',
                url('/project?open=' . $p->id)
            ))->all();

        return ['key' => 'purchase-order', 'label' => 'Purchase Order', 'icon' => 'fas fa-file-contract', 'items' => $items];
    }

    private function budgetPlan(string $q): array
    {
        $query = BudgetPlan::with('project')
            ->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));

        app(BudgetPlanController::class)->scopeVisibleTo($query);

        return [
            'key'   => 'budget-plan',
            'label' => 'Budgeting',
            'icon'  => 'fas fa-coins',
            'items' => $query->latest()->limit(self::PER_MODUL)->get()
                ->map(fn ($bp) => $this->baris(
                    $bp->code,
                    $bp->description,
                    $bp->status,
                    'budget-plan',
                    url('/budget-plan?open=' . $bp->id)
                ))->all(),
        ];
    }

    private function invoice(string $q): array
    {
        $query = Invoice::with('po')
            ->where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('faktur_number', 'like', "%{$q}%"));

        app(InvoiceCashReceiptController::class)->scopeVisibleTo($query);

        return [
            'key'   => 'invoice',
            'label' => 'Invoice & Cash Receipt',
            'icon'  => 'fas fa-file-invoice',
            'items' => $query->latest()->limit(self::PER_MODUL)->get()
                ->map(fn ($inv) => $this->baris(
                    $inv->code,
                    $inv->po?->name ?? '-',
                    // display_status, bukan kolom status — sama seperti chip di halamannya.
                    $inv->display_status,
                    'invoice',
                    url('/invoice-cash-receipt/' . $inv->id)
                ))->all(),
        ];
    }

    private function dailyTransaction(string $q): array
    {
        // DailyTransactionController::index() juga tidak menyaring baris per role.
        $items = DailyTransaction::where(fn ($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"))
            ->latest()->limit(self::PER_MODUL)->get()
            ->map(fn ($trx) => $this->baris(
                $trx->code,
                $trx->description,
                $trx->status,
                'daily-transaction',
                url('/daily-transaction/' . $trx->id)
            ))->all();

        return ['key' => 'treasury', 'label' => 'Treasury', 'icon' => 'fas fa-money-check-alt', 'items' => $items];
    }

    /* ------------------------------------------------------------------ */

    /** Satu baris hasil, memakai metadata status yang sama dengan chip di tabel. */
    private function baris(?string $judul, ?string $keterangan, ?string $status, string $konteks, string $url): array
    {
        $meta = WorkflowStatus::meta($status, $konteks);

        return [
            'title'    => $judul ?? '-',
            'subtitle' => Str::limit((string) $keterangan, 70),
            'status'   => $meta['label'],
            'tone'     => $meta['tone'],
            'url'      => $url,
        ];
    }
}
