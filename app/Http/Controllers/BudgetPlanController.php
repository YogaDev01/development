<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetPlanAttachment;
use App\Models\BudgetRequest;
use App\Models\Account;
use App\Models\Project;
use App\Models\CostCenter;
use App\Models\User;
use App\Services\NotifyService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Services\RoleDirectory;

class BudgetPlanController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI SUMBER DANA
    |--------------------------------------------------------------------------
    | Budget plan bersumber dari SALAH SATU:
    |   - Project    (PO customer) -> variable cost
    |   - CostCenter (internal)    -> overhead / payroll
    */

    /** Kategori account yang boleh dipakai per jenis sumber dana.
     *  PENTING: nilai ini harus SAMA PERSIS dengan isi kolom accounts.category.
     *  Tipe cost center non-payroll lain (Tax/Asset/Liability/Capital/Adjustment)
     *  sengaja berbagi kategori "Overhead" yang sama — belum ada Account
     *  tersendiri untuk masing-masing tipe itu. */
    private const ACCOUNT_CATEGORY = [
        'PROJECT'    => 'Variable Cost',
        'OVERHEAD'   => 'Overhead',
        'PAYROLL'    => 'Fixed Cost',
        'TAX'        => 'Overhead',
        'ASSET'      => 'Overhead',
        'LIABILITY'  => 'Overhead',
        'CAPITAL'    => 'Overhead',
        'ADJUSTMENT' => 'Overhead',
    ];

    /** Role yang boleh melihat budget OVERHEAD */
    private const OVERHEAD_PRIVILEGED_ROLES = ['superadmin', 'approver', 'finance_manager'];

    /** Role yang boleh melihat budget PAYROLL (rahasia).
     *  'hr' & 'finance_manager' dibuat di langkah berikutnya. */
    private const PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager'];

    /** Role yang melihat SEMUA budget proyek (tanpa filter access group) */
    private const PROJECT_PRIVILEGED_ROLES = [
        'superadmin', 'finance', 'finance_manager', 'approver', 'admin',
        'cost_control', 'treasury', 'collection', 'accounting',
    ];

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function normalizeMoney(?string $value): int
    {
        if ($value === null || trim($value) === '') return 0;
        $stripped = preg_replace('/\D/', '', $value);
        return $stripped === '' ? 0 : (int) $stripped;
    }

    private function syncDetails(BudgetPlan $bp, Request $request): void
    {
        $bp->details()->delete();

        foreach ((array) $request->input('details', []) as $row) {
            $amount = $this->normalizeMoney($row['amount'] ?? null);
            $qty    = (float) ($row['qty'] ?? 1);
            if ($qty <= 0) $qty = 1;

            if (!$amount) continue;

            $total = (int) round($amount * $qty);

            $bp->details()->create([
                'account_id' => $row['account_id'] ?? null,
                'category'   => 'Variable Cost',   // auto fixed
                'type'       => 'main',             // auto fixed
                'deskripsi'  => $row['deskripsi']   ?? null,
                'amount'     => $amount,
                'qty'        => $qty,
                'unit'       => $row['unit'] ?? null,
                'total'      => $total,
                'notes'      => $row['notes']       ?? null,
            ]);
        }

        $bp->recalculateAmount();
    }

    private function generateCode(Project $project): string
    {
        // Format: BD-{MM}{YY}{poCode(4)}{seq(4)}   contoh: BD-072600030002
        $poCode = str_pad($project->code ?? '0000', 4, '0', STR_PAD_LEFT);
        $prefix = 'BD-' . now()->format('m') . now()->format('y') . $poCode;

        $count = BudgetPlan::whereYear('created_at', now()->format('Y'))
            ->whereMonth('created_at', now()->format('m'))
            ->where('project_id', $project->id)
            ->count();

        return $this->buildUniqueCode($prefix, $count);
    }

    /**
     * Kode budget untuk cost center.
     * Prefix dibedakan agar TIDAK bentrok dengan kode budget proyek:
     *   OVERHEAD -> OB-{MM}{YY}{ccNum(4)}{seq(4)}   contoh: OB-072600010001
     *   PAYROLL  -> PB-{MM}{YY}{ccNum(4)}{seq(4)}
     */
    private function generateCodeForCostCenter(CostCenter $cc): string
    {
        $prefix2 = strtoupper($cc->type) === 'PAYROLL' ? 'PB-' : 'OB-';

        // Ambil bagian numerik dari kode cost center (OH-001 -> 0001)
        $ccNum = preg_replace('/\D/', '', $cc->code ?? '');
        $ccNum = str_pad($ccNum !== '' ? $ccNum : '0', 4, '0', STR_PAD_LEFT);

        $prefix = $prefix2 . now()->format('m') . now()->format('y') . $ccNum;

        $count = BudgetPlan::whereYear('created_at', now()->format('Y'))
            ->whereMonth('created_at', now()->format('m'))
            ->where('cost_center_id', $cc->id)
            ->count();

        return $this->buildUniqueCode($prefix, $count);
    }

    /** Tambahkan nomor urut & pastikan unik */
    private function buildUniqueCode(string $prefix, int $count): string
    {
        $seq       = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $candidate = $prefix . $seq;

        $attempts = 0;
        while (BudgetPlan::where('code', $candidate)->exists() && $attempts < 100) {
            $count++;
            $seq       = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $seq;
            $attempts++;
        }

        return $candidate;
    }

    /**
     * Scope project yang tersedia untuk dropdown:
     * - Status: WAITING_PO atau ACTIVE
     * - Filter per access group user (kecuali superadmin/finance yg lihat semua)
     */
    private function getAvailableProjects(): \Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();
        $role = $user->role->name ?? '';

        $query = Project::with('customer')
            ->leftJoin('customers', 'customers.id', '=', 'projects.customer_id')
            ->whereIn('projects.status', ['WAITING_PO', 'ACTIVE'])
            ->orderBy('customers.name')
            ->orderBy('projects.name')
            ->select('projects.*');

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'admin'])) {
            if ($user->accessGroup) {
                $projectIds = $user->accessGroup->details->pluck('project_id');
                $query->whereIn('projects.id', $projectIds);
            } else {
                $query->whereRaw('1=0'); // tidak ada akses
            }
        }

        return $query->get();
    }

    /*
    |--------------------------------------------------------------------------
    | HAK LIHAT SUMBER DANA INTERNAL
    |--------------------------------------------------------------------------
    | Overhead & payroll TIDAK di-scope lewat access group (yang berbasis
    | proyek), melainkan lewat ROLE.
    */

    private function overheadPrivileged(): bool
    {
        return in_array(strtolower(Auth::user()->role->name ?? ''), self::OVERHEAD_PRIVILEGED_ROLES);
    }

    private function accessibleOverheadCostCenterIds(): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        return $user->accessGroup ? $user->accessGroup->costCenterIds() : collect();
    }

    private function canSeeOverhead(): bool
    {
        return $this->overheadPrivileged()
            || $this->accessibleOverheadCostCenterIds()->isNotEmpty();
    }

    private function canSeePayroll(): bool
    {
        return in_array(strtolower(Auth::user()->role->name ?? ''), self::PAYROLL_ROLES);
    }

    /**
     * Apakah user yang login berhak melihat detail satu Budget Plan.
     * Cermin dari scoping index(): budget proyek -> role privileged atau PO
     * ada di access group user; overhead -> privileged atau cost center ada di
     * access group; payroll -> role PAYROLL_ROLES (data rahasia). Dipakai
     * showJson() & exportReport() yang route-nya tidak bisa digerbang menu
     * (dipanggil dari beberapa halaman berbeda).
     */
    private function canViewBudgetPlan(BudgetPlan $bp): bool
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        if ($bp->isProjectBudget()) {
            if (in_array($role, self::PROJECT_PRIVILEGED_ROLES)) {
                return true;
            }
            $projectIds = $user->accessGroup
                ? $user->accessGroup->details->pluck('project_id')->filter()->map(fn ($i) => (int) $i)
                : collect();
            return $projectIds->contains((int) $bp->project_id);
        }

        if ($bp->isCostCenterBudget()) {
            if (strtoupper($bp->costCenter?->type ?? '') === 'PAYROLL') {
                return $this->canSeePayroll();
            }
            return $this->overheadPrivileged()
                || $this->accessibleOverheadCostCenterIds()->contains((int) $bp->cost_center_id);
        }

        return false;
    }

    /** Cost center ACTIVE yang boleh dipakai user ini untuk membuat budget */
    private function getAvailableCostCenters(): \Illuminate\Database\Eloquent\Collection
    {
        $query = CostCenter::active();

        $conditions = [];

        // Overhead (dan tipe non-payroll lain — Tax/Asset/Liability/Capital/
        // Adjustment, diperlakukan sama): privileged semua; lainnya per access group
        if ($this->overheadPrivileged()) {
            $conditions[] = ['types' => CostCenter::OVERHEAD_LIKE_TYPES, 'ids' => null];
        } else {
            $ccIds = $this->accessibleOverheadCostCenterIds();
            if ($ccIds->isNotEmpty()) {
                $conditions[] = ['types' => CostCenter::OVERHEAD_LIKE_TYPES, 'ids' => $ccIds->all()];
            }
        }

        // Payroll: role-gated
        if ($this->canSeePayroll()) {
            $conditions[] = ['types' => ['PAYROLL'], 'ids' => null];
        }

        if (empty($conditions)) {
            return CostCenter::whereRaw('1=0')->get();
        }

        return $query->where(function ($q) use ($conditions) {
            foreach ($conditions as $c) {
                $q->orWhere(function ($qq) use ($c) {
                    $qq->whereIn('type', $c['types']);
                    if (!is_null($c['ids'])) {
                        $qq->whereIn('id', $c['ids']);
                    }
                });
            }
        })
        ->orderBy('type')
        ->orderBy('name')
        ->get();
    }

    /** Account yang boleh dipakai, sesuai jenis sumber dana */
    private function getAccountsFor(string $sourceType)
    {
        $category = self::ACCOUNT_CATEGORY[strtoupper($sourceType)] ?? null;

        $query = Account::active()->orderBy('name');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->get(['id', 'name']);
    }

    /**
     * Batasi $query BudgetPlan pada yang boleh dilihat user yang login.
     *
     * Dipindahkan apa adanya dari index() — isinya tidak diubah sedikit pun —
     * supaya Global Search memakai aturan yang sama persis, bukan salinan
     * yang bisa menyimpang.
     *
     * Tiga jalur terpisah, digabung dengan OR:
     *   1) Budget PROYEK   -> aturan access group
     *   2) Budget OVERHEAD -> berdasarkan role
     *   3) Budget PAYROLL  -> berdasarkan role (lebih ketat, data rahasia)
     * Budget yang tidak masuk salah satu jalur TIDAK akan terlihat.
     */
    /**
     * Ambil Budget Plan + pastikan user berhak melihatnya.
     *
     * Aturannya scopeVisibleTo() yang sama dengan daftar Budget Plan. Tanpa
     * ini daftar memang tersaring per Access Group, tapi URL ber-{id} (show,
     * edit, dan seluruh aksi workflow) tetap terbuka bagi siapa pun yang
     * mengetik id budget milik proyek lain — penyaringan di daftar jadi
     * sekadar tampilan.
     */
    private function findVisibleBudgetPlan($id, array $with = []): BudgetPlan
    {
        $bp = BudgetPlan::with($with)->findOrFail($id);

        // canViewBudgetPlan() adalah versi per-baris dari scopeVisibleTo() dan
        // sudah dipakai showJson()/exportReport(); dipakai ulang di sini supaya
        // hanya ada satu definisi "boleh lihat".
        abort_unless($this->canViewBudgetPlan($bp), 403, 'Anda tidak memiliki akses ke Budget Plan ini.');

        return $bp;
    }

    public function scopeVisibleTo($query): void
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        $projectIds = $user->accessGroup
            ? $user->accessGroup->details->pluck('project_id')->filter()->values()
            : collect();

        $overheadPrivileged = $this->overheadPrivileged();
        $overheadCcIds      = $this->accessibleOverheadCostCenterIds();
        $canPayroll         = $this->canSeePayroll();

        $query->where(function ($q) use ($role, $projectIds, $overheadPrivileged, $overheadCcIds, $canPayroll) {

            // 1) Budget proyek
            $q->where(function ($qq) use ($role, $projectIds) {
                $qq->whereNotNull('project_id');

                if (!in_array($role, self::PROJECT_PRIVILEGED_ROLES)) {
                    $qq->whereIn('project_id', $projectIds->isEmpty() ? [0] : $projectIds);
                }
            });

            // 2) Budget overhead (dan tipe non-payroll lain — Tax/Asset/Liability/
            // Capital/Adjustment — yang diperlakukan sama seperti Overhead)
            if ($overheadPrivileged) {
                $q->orWhereHas('costCenter', fn ($c) => $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES));
            } elseif ($overheadCcIds->isNotEmpty()) {
                $q->orWhereHas('costCenter', fn ($c) =>
                    $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->whereIn('id', $overheadCcIds)
                );
            }

            // 3) Budget payroll
            if ($canPayroll) {
                $q->orWhereHas('costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $query = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter', 'logs', 'inputBy']);

        $this->scopeVisibleTo($query);

        // "Actual" per baris (BudgetPlan::usedAmount) dihitung dalam query yang
        // sama, bukan satu query SUM per Budget Plan.
        $budgetPlans = $query
            ->withSum(['costRequests as used_amount_sum' => fn ($q) => $q->whereIn('status', \App\Support\BudgetConsumption::DISBURSED)], 'amount')
            ->latest()->get();

        $summary = [
            'total'    => $budgetPlans->count(),
            'draft'    => $budgetPlans->where('status', 'DRAFT')->count(),
            'pending'  => $budgetPlans->where('status', 'SUBMITTED')->count(),
            'checked'  => $budgetPlans->where('status', 'CHECKED')->count(),
            'verified' => $budgetPlans->where('status', 'VERIFIED')->count(),
            'active'   => $budgetPlans->where('status', 'ACTIVE')->count(),
            'closing'  => $budgetPlans->where('status', 'CLOSING')->count(),
            'closed'   => $budgetPlans->where('status', 'CLOSED')->count(),
            'rejected' => $budgetPlans->where('status', 'REJECTED')->count(),
        ];

        return view('budget-plan.index', compact('budgetPlans', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT LIST — Excel dari seluruh Daftar Budget Plan (sesuai scoping akses
    | yang sama persis dengan index(), supaya data yang di-export = data yang
    | tampil di layar user itu)
    |--------------------------------------------------------------------------
    */

    public function exportList(Request $request)
    {
        /*
         | Export mengikuti hasil filter yang sedang aktif di tabel: browser
         | mengirim id baris yang lolos (lihat public/js/tesys-export-filtered.js).
         | Tanpa 'ids' — diakses langsung, atau memang tidak ada filter aktif —
         | perilakunya persis seperti sebelumnya: seluruh data.
         */
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        $query = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter', 'inputBy']);

        $projectIds = $user->accessGroup
            ? $user->accessGroup->details->pluck('project_id')->filter()->values()
            : collect();

        $overheadPrivileged = $this->overheadPrivileged();
        $overheadCcIds      = $this->accessibleOverheadCostCenterIds();
        $canPayroll         = $this->canSeePayroll();

        $query->where(function ($q) use ($role, $projectIds, $overheadPrivileged, $overheadCcIds, $canPayroll) {
            $q->where(function ($qq) use ($role, $projectIds) {
                $qq->whereNotNull('project_id');
                if (!in_array($role, self::PROJECT_PRIVILEGED_ROLES)) {
                    $qq->whereIn('project_id', $projectIds->isEmpty() ? [0] : $projectIds);
                }
            });

            if ($overheadPrivileged) {
                $q->orWhereHas('costCenter', fn ($c) => $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES));
            } elseif ($overheadCcIds->isNotEmpty()) {
                $q->orWhereHas('costCenter', fn ($c) =>
                    $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->whereIn('id', $overheadCcIds)
                );
            }

            if ($canPayroll) {
                $q->orWhereHas('costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });

        $budgetPlans = $query->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->latest()->get();

        $columns = [
            'Code', 'Description', 'PO/Cost Center', 'Customer', 'Manager',
            'Budget', 'Used', 'Variance', 'Margin (%)', 'Status', 'Input By',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Budget Plan');
        $sheet->fromArray($columns, null, 'A1');
        foreach (range('A', 'K') as $col) {
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($budgetPlans as $bp) {
            $used   = $bp->usedAmount();
            $var    = ($bp->amount ?? 0) - $used;
            $margin = ($bp->amount ?? 0) > 0 ? round(($var / $bp->amount) * 100, 1) : 0;

            $ref        = $bp->isProjectBudget() ? ($bp->project->code ?? '-') : ($bp->costCenter->code ?? '-');
            $customer   = $bp->isProjectBudget() ? ($bp->project->customer->name ?? '-') : ($bp->costCenter->name ?? '-');
            $pmInternal = $bp->project?->pm_internal_name ?: '-';

            $sheet->fromArray([
                $bp->code,
                $bp->description,
                $ref,
                $customer,
                $pmInternal,
                (int) ($bp->amount ?? 0),
                (int) $used,
                (int) $var,
                $margin,
                $bp->status,
                $bp->inputBy->name ?? '-',
            ], null, 'A' . $row);
            $row++;
        }

        foreach (['A'=>16,'B'=>40,'C'=>16,'D'=>28,'E'=>20,'F'=>16,'G'=>16,'H'=>16,'I'=>12,'J'=>12,'K'=>20] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'budget_plan_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $projects    = $this->getAvailableProjects();
        $costCenters = $this->getAvailableCostCenters();

        // Account dikelompokkan per jenis sumber dana; dropdown di-swap oleh JS
        $accountsBySource = [
            'PROJECT'  => $this->getAccountsFor('PROJECT'),
            'OVERHEAD' => $this->getAccountsFor('OVERHEAD'),
            'PAYROLL'  => $this->getAccountsFor('PAYROLL'),
        ];

        // Kompatibilitas: variabel lama tetap tersedia
        $accounts = $accountsBySource['PROJECT'];

        return view('budget-plan.create', compact(
            'projects', 'costCenters', 'accounts', 'accountsBySource'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'source_type'    => 'required|in:PROJECT,COST_CENTER',
            'project_id'     => 'required_if:source_type,PROJECT|nullable|exists:projects,id',
            'cost_center_id' => 'required_if:source_type,COST_CENTER|nullable|exists:cost_centers,id',
            'description'    => 'required|string|max:500',
            'notes'          => 'nullable|string',
            'details'        => 'required|array|min:1',
            'details.*.account_id' => 'nullable|exists:accounts,id',
            'details.*.deskripsi'  => 'nullable|string|max:255',
            'details.*.amount'     => 'required',
            'details.*.qty'        => 'nullable|numeric|min:0.01',
            'details.*.unit'       => 'nullable|in:' . implode(',', \App\Models\BudgetPlanDetail::UNITS),
        ]);

        $isProject = $request->source_type === 'PROJECT';

        if ($isProject) {
            $project        = Project::findOrFail($request->project_id);
            $code           = $this->generateCode($project);
            $projectId      = $project->id;
            $costCenterId   = null;
        } else {
            $costCenter = CostCenter::findOrFail($request->cost_center_id);

            // GUARD: cegah user membuat budget pada cost center yang tidak boleh dia akses
            if (strtoupper($costCenter->type) === 'PAYROLL') {
                $allowed = $this->canSeePayroll();
            } else {
                $allowed = $this->overheadPrivileged()
                    || $this->accessibleOverheadCostCenterIds()->contains((int) $costCenter->id);
            }

            if (!$allowed) {
                return back()->withInput()
                    ->with('error', 'Anda tidak berhak membuat budget pada cost center ini.');
            }

            $code         = $this->generateCodeForCostCenter($costCenter);
            $projectId    = null;
            $costCenterId = $costCenter->id;
        }

        DB::transaction(function () use ($request, $code, $projectId, $costCenterId) {
            $bp = BudgetPlan::create([
                'code'           => $code,
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
                'description'    => strtoupper(trim($request->description)),
                'type'           => 'one_time',   // fixed default
                'amount'         => 0,
                'status'         => 'DRAFT',
                'notes'          => $request->notes,
                'input_by'       => Auth::id(),
            ]);

            $this->syncDetails($bp, $request);

            foreach ((array) $request->file('attachments', []) as $row) {
                $file = is_array($row) ? ($row['file'] ?? null) : $row;
                if (!$file || !$file->isValid()) continue;
                $path = $file->store('budget_plan_attachments', 'secure');
                $bp->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }

            $bp->addLog('DRAFT', 'DRAFT', 'created');
        });

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan berhasil dibuat: ' . $code);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT REPORT (per Budget Plan) — Sheet 1: Informasi, Sheet 2: Detail
    |--------------------------------------------------------------------------
    */

    public function exportReport($id)
    {
        $bp = BudgetPlan::with(['project.customer', 'costCenter', 'details.account', 'inputBy', 'approvedBy'])
            ->findOrFail($id);

        abort_unless($this->canViewBudgetPlan($bp), 403);

        $spreadsheet = new Spreadsheet();

        // ── SHEET 1: INFORMASI ──────────────────────────────────────
        $info = $spreadsheet->getActiveSheet();
        $info->setTitle('Informasi');

        $sumber = $bp->isProjectBudget()
            ? ($bp->project->name ?? '-') . ' — ' . ($bp->project->customer->name ?? '-')
            : ($bp->costCenter->name ?? '-') . ' (' . $bp->source_type . ')';

        $used      = $bp->usedAmount();
        $remaining = $bp->remainingAmount();

        $rows = [
            ['Kode', $bp->code],
            ['Deskripsi', $bp->description],
            ['Sumber Dana', $sumber],
            ['Total Budget', (int) ($bp->amount ?? 0)],
            ['Terpakai', $used],
            ['Sisa', $remaining],
            ['Status', $bp->status],
            ['Notes', $bp->notes],
            ['Catatan Approver', $bp->approver_notes],
            ['Approved By', $bp->approvedBy->name ?? '-'],
            ['Approved At', $bp->approved_at?->format('d-m-Y H:i')],
            ['Input By', $bp->inputBy->name ?? '-'],
        ];

        $info->setCellValue('A1', 'Field');
        $info->setCellValue('B1', 'Value');
        foreach (['A1', 'B1'] as $cell) {
            $info->getStyle($cell)->getFont()->setBold(true);
            $info->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $info->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
        }
        foreach ($rows as $i => $row) {
            $r = $i + 2;
            $info->setCellValue('A' . $r, $row[0]);
            $info->setCellValue('B' . $r, $row[1]);
            $info->getStyle('A' . $r)->getFont()->setBold(true);
        }
        $info->getColumnDimension('A')->setWidth(22);
        $info->getColumnDimension('B')->setWidth(50);

        // ── SHEET 2: DETAIL ──────────────────────────────────────────
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Detail');

        $headers = ['Account', 'Deskripsi', 'Harga Satuan', 'Qty', 'Unit', 'Budget', 'Terpakai', 'Saving', 'Notes'];
        foreach ($headers as $i => $label) {
            $col = chr(65 + $i);
            $detailSheet->setCellValue($col . '1', $label);
            $detailSheet->getStyle($col . '1')->getFont()->setBold(true);
            $detailSheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $detailSheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $sortedDetails = $bp->details->sortBy(fn ($d) => $d->account->name ?? '-')->values();

        $r = 2;
        foreach ($sortedDetails as $d) {
            $dUsed      = $d->usedAmount();
            $dRemaining = $d->remainingAmount();

            $detailSheet->setCellValue('A' . $r, $d->account->name ?? '-');
            $detailSheet->setCellValue('B' . $r, $d->deskripsi);
            $detailSheet->setCellValue('C' . $r, (int) $d->amount);
            $detailSheet->setCellValue('D' . $r, (float) $d->qty);
            $detailSheet->setCellValue('E' . $r, $d->unit);
            $detailSheet->setCellValue('F' . $r, (int) $d->total);
            $detailSheet->setCellValue('G' . $r, $dUsed);
            $detailSheet->setCellValue('H' . $r, $dRemaining);
            $detailSheet->setCellValue('I' . $r, $d->notes);
            $r++;
        }

        foreach (['A'=>18,'B'=>32,'C'=>16,'D'=>8,'E'=>10,'F'=>16,'G'=>16,'H'=>16,'I'=>24] as $col => $width) {
            $detailSheet->getColumnDimension($col)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'budget-plan-' . $bp->code . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON — AJAX
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $bp = BudgetPlan::with([
            'project.customer',
            'project.manager',
            'costCenter',
            'details.account',
            'attachments',
            'logs',
            'inputBy',
            'approvedBy',
        ])->findOrFail($id);

        abort_unless($this->canViewBudgetPlan($bp), 403);

        $costRequests = BudgetRequest::where('budget_plan_id', $bp->id)
            ->whereIn('status', ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED'])
            ->with(['type', 'applicant', 'beneficiary', 'details', 'realizations'])
            ->latest()
            ->get();

        // SEMUA cost request yang mengacu ke budget plan ini (untuk kartu ringkasan Summary)
        $allRequests = BudgetRequest::where('budget_plan_id', $bp->id)
            ->with('type:id,name')
            ->get(['id', 'status', 'type_id', 'amount']);

        $crStatusColor = [
            'DRAFT' => 'secondary', 'SUBMITTED' => 'warning', 'CHECKED' => 'info', 'VERIFIED' => 'primary',
            'APPROVED' => 'info', 'QUEUED' => 'warning', 'PAID' => 'success', 'REALIZED' => 'success',
            'CLOSED' => 'dark', 'REJECTED' => 'danger', 'CANCELLED' => 'dark',
        ];
        $crStatusLabel = [
            'DRAFT' => 'Draft', 'SUBMITTED' => 'Submitted', 'CHECKED' => 'Checked', 'VERIFIED' => 'Verified',
            'APPROVED' => 'Approved', 'QUEUED' => 'Queued', 'PAID' => 'Paid', 'REALIZED' => 'Realized',
            'CLOSED' => 'Closed', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled',
        ];

        $statusCounts = $allRequests->groupBy('status')->map->count()
            ->map(fn ($count, $status) => [
                'status' => $status,
                'label'  => $crStatusLabel[$status] ?? $status,
                'color'  => $crStatusColor[$status] ?? 'secondary',
                'count'  => $count,
            ])->values();

        $typeCounts = $allRequests->groupBy(fn ($cr) => $cr->type->name ?? '-')->map->count()
            ->map(fn ($count, $type) => ['type' => $type, 'count' => $count])
            ->sortByDesc('count')->values();

        $rp = fn ($v) => number_format((int) $v, 0, ',', '.');

        // Per pengajuan: kalau sudah ada realisasinya (REALIZED/CLOSED + punya
        // baris realisasi) → pakai nilai realisasi & selisihnya jadi Refund.
        // Kalau belum ada realisasi → pakai nilai pengajuan penuh, Refund 0.
        // P-01: refund & nilai terpakai mengikuti BudgetRequest (settle-gated).
        $realizedValueOf = fn ($cr) => $cr->realizedCostValue();
        $refundOf        = fn ($cr) => $cr->refundAmount();

        $sumRequest = (int) $costRequests->sum('amount');
        $sumRefund  = (int) $costRequests->sum($refundOf);
        $actualUsed = $sumRequest - $sumRefund;

        // Rekap per beneficiary (dari cost request PAID/REALIZED/CLOSED)
        $beneficiaries = $costRequests->groupBy(fn ($cr) => $cr->beneficiary->nickname ?? '-')
            ->map(function ($rows, $name) use ($refundOf) {
                $req    = (int) $rows->sum('amount');
                $refund = (int) $rows->sum($refundOf);
                return [
                    'beneficiary'           => $name,
                    'request'               => $req,
                    'request_formatted'     => number_format($req, 0, ',', '.'),
                    'realization'           => $req - $refund,
                    'realization_formatted' => number_format($req - $refund, 0, ',', '.'),
                    'refund'                => $refund,
                    'refund_formatted'      => number_format($refund, 0, ',', '.'),
                ];
            })->sortByDesc('request')->values();

        return response()->json([
            'id'               => $bp->id,
            'code'             => $bp->code,
            'description'      => $bp->description,
            'status'           => $bp->status,
            'status_color'     => $bp->status_color,
            'notes'            => $bp->notes,
            'approver_notes'   => $bp->approver_notes,
            'amount'           => (int) ($bp->amount ?? 0),
            'amount_formatted' => $bp->amount_formatted,
            'used_amount'      => $bp->usedAmount(),
            'remaining_amount' => $bp->remainingAmount(),
            'usage_percentage' => $bp->amount > 0
                ? round(($bp->usedAmount() / $bp->amount) * 100, 1)
                : 0,
            'project'          => $bp->project->name ?? '-',
            'project_code'     => $bp->project->code ?? '-',
            'customer'         => $bp->project->customer->name ?? '-',
            'manager'          => $bp->project?->pm_internal_name ?: '-',

            'summary' => [
                'status_counts' => $statusCounts,
                'type_counts'   => $typeCounts,
                'request_total' => $allRequests->count(),
                'budget' => [
                    'budget_value_formatted' => $rp($bp->amount ?? 0),
                    'request_formatted'      => $rp($sumRequest),
                    'refund_formatted'       => $rp($sumRefund),
                    'actual_used_formatted'  => $rp($actualUsed),
                ],
            ],
            'beneficiaries' => $beneficiaries,

            // Source fund (PROJECT | OVERHEAD | PAYROLL)
            'source_type'      => $bp->source_type,
            'source_name'      => $bp->source_name,

            // Code Reference: kode PO untuk budget proyek, kode Cost Center untuk budget internal
            'code_reference'   => $bp->isProjectBudget()
                ? ($bp->project->code ?? '-')
                : ($bp->costCenter->code ?? '-'),

            'input_by'         => $bp->inputBy->name ?? '-',
            'approved_by'      => $bp->approvedBy->name ?? '-',
            'approved_at'      => $bp->approved_at?->format('d M Y H:i'),

            'details' => $bp->details->map(function ($d) {
                $used      = $d->usedAmount();
                $remaining = $d->remainingAmount();

                $crDetails = collect();
                if (\Illuminate\Support\Facades\Schema::hasColumn('request_detail', 'budget_detail_id')) {
                    $crDetails = $d->costRequestDetails()
                        ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED']))
                        ->with(['budgetRequest:id,code,description,beneficiary_id,created_at', 'budgetRequest.beneficiary:id,nickname', 'realization'])
                        ->get()
                        ->map(function ($rd) {
                            $amount = (int) ($rd->realization->total ?? $rd->total);
                            return [
                                'cr_id'                => $rd->budgetRequest->id ?? null,
                                'cr_date_formatted'    => $rd->budgetRequest->created_at?->format('d M Y') ?? '-',
                                'beneficiary'          => $rd->budgetRequest->beneficiary->nickname ?? '-',
                                'notes'                => $rd->notes ?: '-',
                                'unit_price_formatted' => number_format((int) $rd->amount, 0, ',', '.'),
                                'qty'                  => (float) $rd->qty,
                                'amount'               => $amount,
                                'amount_formatted'     => number_format($amount, 0, ',', '.'),
                            ];
                        })
                        ->values();
                }

                return [
                    'account'           => $d->account->name ?? '-',
                    'deskripsi'         => $d->deskripsi,
                    'amount'            => $d->amount,
                    'amount_formatted'  => number_format($d->amount, 0, ',', '.'),
                    'qty'               => (float) $d->qty,
                    'unit'              => $d->unit,
                    'total'             => $d->total,
                    'total_formatted'   => number_format($d->total, 0, ',', '.'),
                    'used'              => $used,
                    'used_formatted'    => number_format($used, 0, ',', '.'),
                    'remaining'         => $remaining,
                    'remaining_formatted' => number_format($remaining, 0, ',', '.'),
                    'notes'             => $d->notes,
                    'cr_details'        => $crDetails,
                ];
            }),

            'attachments' => $bp->attachments->map(fn($a) => [
                'original_name'  => $a->original_name,
                'path'           => $a->path,
                'is_image'       => $a->isImage(),
                'is_pdf'         => $a->isPdf(),
            ]),

            'logs' => $bp->logs->map(fn($l) => [
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from,
                'status_to'    => $l->status_to,
                'user'         => $l->user_name ?? '-',
                'user_id'      => $l->user_id,
                'role'         => $l->role ?? '-',
                'notes'        => $l->notes,
                'date'         => $l->created_at->format('d M Y H:i'),
            ]),

            // Daftar Cost Request yang terhubung ke Budget Plan ini
            'requests' => $costRequests->map(function ($cr) use ($realizedValueOf, $refundOf) {
                $realisasi = $realizedValueOf($cr);
                $refund    = $refundOf($cr);

                return [
                    'id'                  => $cr->id,
                    'code'                => $cr->code,
                    'description'         => $cr->description,
                    'type'                => $cr->type->name ?? '-',
                    'applicant'           => $cr->applicant->name ?? '-',
                    'beneficiary'         => $cr->beneficiary->nickname ?? '-',
                    'amount'              => (int) $cr->amount,
                    'amount_formatted'    => $cr->amount_formatted,
                    'realization'         => $realisasi,
                    'realization_formatted' => number_format($realisasi, 0, ',', '.'),
                    'refund'              => $refund,
                    'refund_formatted'    => number_format($refund, 0, ',', '.'),
                    'status'              => $cr->status,
                    'status_color'        => $cr->status_color,
                    'date'                => $cr->created_at->format('d M Y'),
                    'details' => $cr->details->map(fn ($d) => [
                        'description'      => $d->description,
                        'amount_formatted' => $d->amount_formatted ?? (number_format($d->amount, 0, ',', '.')),
                        'qty'              => (float) $d->qty,
                        'total_formatted'  => $d->total_formatted ?? (number_format($d->total, 0, ',', '.')),
                        'notes'            => $d->notes,
                        'is_takeout'       => $d->isTakenOut(),
                        'takeout_stage_label' => $d->isTakenOut() ? $d->takeout_stage_label : null,
                    ]),
                ];
            }),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $budgetPlan = $this->findVisibleBudgetPlan($id, ['details', 'attachments', 'project', 'costCenter']);

        if (!$budgetPlan->isEditable()) {
            return redirect('/budget-plan')
                ->with('error', 'Budget Plan hanya dapat diedit saat berstatus DRAFT.');
        }

        $projects    = $this->getAvailableProjects();
        $costCenters = $this->getAvailableCostCenters();

        // Account mengikuti jenis sumber dana budget ini
        $accounts = $this->getAccountsFor($budgetPlan->source_type);

        $accountsBySource = [
            'PROJECT'  => $this->getAccountsFor('PROJECT'),
            'OVERHEAD' => $this->getAccountsFor('OVERHEAD'),
            'PAYROLL'  => $this->getAccountsFor('PAYROLL'),
        ];

        return view('budget-plan.edit', compact(
            'budgetPlan', 'projects', 'costCenters', 'accounts', 'accountsBySource'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $budgetPlan = $this->findVisibleBudgetPlan($id);

        if (!$budgetPlan->isEditable()) {
            return redirect('/budget-plan')
                ->with('error', 'Budget Plan hanya dapat diedit saat berstatus DRAFT.');
        }

        $request->validate([
            // Sumber dana TIDAK bisa dipindah antar jenis (kode sudah menyandi sumbernya).
            // Budget proyek masih boleh ganti PO; budget cost center tidak ganti apa pun.
            'project_id'  => ($budgetPlan->isProjectBudget() ? 'required|exists:projects,id' : 'nullable'),
            'description' => 'required|string|max:500',
            'notes'       => 'nullable|string',
            'details'     => 'required|array|min:1',
            'details.*.account_id' => 'nullable|exists:accounts,id',
            'details.*.deskripsi'  => 'nullable|string|max:255',
            'details.*.amount'     => 'required',
            'details.*.qty'        => 'nullable|numeric|min:0.01',
            'details.*.unit'       => 'nullable|in:' . implode(',', \App\Models\BudgetPlanDetail::UNITS),
        ]);

        DB::transaction(function () use ($request, $budgetPlan) {

            $payload = [
                'description' => strtoupper(trim($request->description)),
                'notes'       => $request->notes,
            ];

            // Hanya budget proyek yang boleh berpindah PO.
            // cost_center_id & project_id lain sengaja TIDAK disentuh agar
            // guard "tepat satu sumber dana" di model tidak terlanggar.
            if ($budgetPlan->isProjectBudget()) {
                $payload['project_id'] = $request->project_id;
            }

            $budgetPlan->update($payload);

            $this->syncDetails($budgetPlan, $request);

            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = BudgetPlanAttachment::where('budget_plan_id', $budgetPlan->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('secure')->delete($att->path);
                    $att->delete();
                }
            }

            foreach ((array) $request->file('attachments', []) as $row) {
                $file = is_array($row) ? ($row['file'] ?? null) : $row;
                if (!$file || !$file->isValid()) continue;
                $path = $file->store('budget_plan_attachments', 'secure');
                $budgetPlan->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        });

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT — DRAFT → SUBMITTED (manager)
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $bp = $this->findVisibleBudgetPlan($id);

        if ($bp->status !== 'DRAFT') {
            return back()->with('error', 'Hanya Budget Plan berstatus DRAFT yang dapat disubmit.');
        }

        $bp->update(['status' => 'SUBMITTED']);
        $bp->addLog('DRAFT', 'SUBMITTED', 'submitted');

        NotifyService::send(
            $this->headUsers(),
            'Budget Plan menunggu approval',
            Auth::user()->name . ' mengajukan Budget Plan "' . $bp->description . '" (' . $bp->code . ') untuk disetujui.',
            route('budget-plan.index'),
            'workflow'
        );

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan berhasil diajukan ke Head.');
    }

    /** Role Head — penerima notifikasi saat Budget Plan disubmit manager. */
    private function headUsers()
    {
        // Satu sumber daftar peran: App\Services\RoleDirectory.
        return app(RoleDirectory::class)->heads();
    }

    /** Role Finance — penerima notifikasi saat Budget Plan disetujui Head. */
    private function financeUsers()
    {
        return app(RoleDirectory::class)->finance();
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD APPROVE — SUBMITTED → CHECKED
    |--------------------------------------------------------------------------
    */

    public function headApprove(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'SUBMITTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'CHECKED']);
        $bp->addLog('SUBMITTED', 'CHECKED', 'head_approved', $request->notes);

        NotifyService::send(
            $this->financeUsers(),
            'Budget Plan menunggu verifikasi Finance',
            $user->name . ' (Head) menyetujui Budget Plan "' . $bp->description . '" (' . $bp->code . ').',
            route('budget-plan.index'),
            'workflow'
        );

        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan disetujui Head',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') disetujui Head dan diteruskan ke Finance.',
                route('budget-plan.index'),
                'workflow'
            );
        }

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan disetujui Head dan diteruskan ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD REJECT — SUBMITTED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function headReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'SUBMITTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'REJECTED', 'approver_notes' => $request->notes]);
        $bp->addLog('SUBMITTED', 'REJECTED', 'head_rejected', $request->notes);

        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan ditolak',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') ditolak Head: ' . $request->notes,
                route('budget-plan.index'),
                'workflow'
            );
        }

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan ditolak. Menunggu keputusan Manager (Revise).');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE VERIFY — CHECKED → VERIFIED
    |--------------------------------------------------------------------------
    */

    public function financeVerify(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'CHECKED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'VERIFIED']);
        $bp->addLog('CHECKED', 'VERIFIED', 'finance_verified', $request->notes);

        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan diverifikasi Finance',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') diverifikasi Finance dan menunggu approval CEO.',
                route('budget-plan.index'),
                'workflow'
            );
        }

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan diverifikasi Finance dan menunggu approval CEO.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REJECT — CHECKED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function financeReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'CHECKED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'REJECTED', 'approver_notes' => $request->notes]);
        $bp->addLog('CHECKED', 'REJECTED', 'finance_rejected', $request->notes);

        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan ditolak',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') ditolak Finance: ' . $request->notes,
                route('budget-plan.index'),
                'workflow'
            );
        }

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan ditolak. Menunggu keputusan Manager (Revise).');
    }

    /*
    |--------------------------------------------------------------------------
    | CEO APPROVE — VERIFIED → ACTIVE
    |--------------------------------------------------------------------------
    */

    public function ceoApprove(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'approver'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'VERIFIED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update([
            'status'      => 'ACTIVE',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        $bp->addLog('VERIFIED', 'ACTIVE', 'ceo_approved', $request->notes);

        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan disetujui',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') disetujui CEO dan sekarang ACTIVE.',
                route('budget-plan.index'),
                'workflow'
            );
        }

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan disetujui CEO dan sekarang ACTIVE.');
    }

    /*
    |--------------------------------------------------------------------------
    | CEO REJECT — VERIFIED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function ceoReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'approver'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'VERIFIED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'REJECTED', 'approver_notes' => $request->notes]);
        $bp->addLog('VERIFIED', 'REJECTED', 'ceo_rejected', $request->notes);

        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan ditolak',
                'Budget Plan "' . $bp->description . '" (' . $bp->code . ') ditolak CEO: ' . $request->notes,
                route('budget-plan.index'),
                'workflow'
            );
        }

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan ditolak. Menunggu keputusan Manager (Revise).');
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGER REVISE — REJECTED → DRAFT (bisa disubmit ulang setelah diperbaiki)
    |--------------------------------------------------------------------------
    */

    public function managerRevise(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk merevisi Budget Plan ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $bp = $this->findVisibleBudgetPlan($id);
        if ($bp->status !== 'REJECTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'DRAFT']);
        $bp->addLog('REJECTED', 'DRAFT', 'revised', $request->notes);

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan dikembalikan ke Draft untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CLOSING — ACTIVE → CLOSING (manager)
    |--------------------------------------------------------------------------
    */

    public function submitClosing(Request $request, $id)
    {
        $request->validate([
            'closing_notes'          => 'nullable|string|max:1000',
            'closing_attachments'    => 'required|array|min:1',
            'closing_attachments.*'  => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $bp = $this->findVisibleBudgetPlan($id);

        if ($bp->status !== 'ACTIVE') {
            return back()->with('error', 'Hanya Budget Plan berstatus ACTIVE yang dapat diajukan closing.');
        }

        DB::transaction(function () use ($request, $bp) {
            $bp->update([
                'status'         => 'CLOSING',
                'approver_notes' => $request->closing_notes,
            ]);

            foreach ($request->file('closing_attachments', []) as $file) {
                if (!$file || !$file->isValid()) continue;
                $path = $file->store('budget_plan_attachments', 'secure');
                $bp->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }

            $bp->addLog('ACTIVE', 'CLOSING', 'closing_submitted', $request->closing_notes);
        });

        return redirect('/budget-plan')
            ->with('success', 'Pengajuan closing berhasil dikirim ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE VERIFY CLOSING — CLOSING → CLOSED
    |--------------------------------------------------------------------------
    */

    public function verifyClosing(Request $request, $id)
    {
        $bp = $this->findVisibleBudgetPlan($id);

        if ($bp->status !== 'CLOSING') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update([
            'status'      => 'CLOSED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        $bp->addLog('CLOSING', 'CLOSED', 'closing_approved', $request->notes);

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan berhasil ditutup (CLOSED).');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REJECT CLOSING — CLOSING → ACTIVE
    |--------------------------------------------------------------------------
    */

    public function rejectClosing(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);
        $bp = $this->findVisibleBudgetPlan($id);

        if ($bp->status !== 'CLOSING') {
            return back()->with('error', 'Status tidak valid.');
        }

        $bp->update(['status' => 'ACTIVE', 'approver_notes' => $request->notes]);
        $bp->addLog('CLOSING', 'ACTIVE', 'closing_rejected', $request->notes);

        return redirect('/budget-plan')
            ->with('success', 'Closing ditolak. Budget Plan dikembalikan ke status ACTIVE.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACCOUNTS (Variable Cost) — AJAX
    |--------------------------------------------------------------------------
    */

    public function getAccounts()
    {
        $accounts = Account::active()
            ->byCategory('Variable Cost')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($accounts);
    }

    /*
    |--------------------------------------------------------------------------
    | API — Sub-item Budget Plan (dipakai Cost Request create/edit)
    |--------------------------------------------------------------------------
    | GET /api/budget-plan/{budgetPlanId}/details
    | Hanya baris dari Budget Plan berstatus ACTIVE yang boleh dipakai
    | mengajukan Cost Request. Setiap baris membawa info sisa budget
    | (total - terpakai) supaya user tahu batas pengajuan sebelum submit.
    */
    public function getDetails($budgetPlanId)
    {
        $bp = BudgetPlan::with('details.account')->find($budgetPlanId);

        if (!$bp || $bp->status !== 'ACTIVE') {
            return response()->json([]);
        }

        return response()->json(
            $bp->details
                ->sortBy(fn ($d) => strtoupper(($d->account->name ?? '-') . '|' . ($d->deskripsi ?? '')))
                ->map(function ($d) {
                    return [
                        'id'                  => $d->id,
                        'account_id'          => $d->account_id,
                        'account'             => $d->account->name ?? '-',
                        'deskripsi'           => $d->deskripsi,
                        'label'               => trim(($d->account->name ?? '-') . ($d->deskripsi ? ' — ' . $d->deskripsi : '')),
                        'unit'                => $d->unit,
                        'amount'              => $d->amount,
                        'qty'                 => (float) $d->qty,
                        'total'               => $d->total,
                        'total_formatted'     => number_format($d->total, 0, ',', '.'),
                        'used'                => $d->usedAmount(),
                        'remaining'           => $d->remainingAmount(),
                        'remaining_formatted' => number_format($d->remainingAmount(), 0, ',', '.'),
                    ];
                })->values()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $bp = $this->findVisibleBudgetPlan($id, ['attachments']);

        if ($bp->status !== 'DRAFT') {
            return redirect('/budget-plan')
                ->with('error', 'Hanya Budget Plan berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($bp) {
            foreach ($bp->attachments as $att) {
                Storage::disk('secure')->delete($att->path);
            }
            $bp->delete();
        });

        return redirect('/budget-plan')
            ->with('success', 'Budget Plan berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — Detail Budget
    |--------------------------------------------------------------------------
    | Template & parser untuk mengisi baris "Detail Budget" (create/edit)
    | secara massal dari file Excel. Import TIDAK langsung menyimpan ke
    | database — hanya mem-parsing & mengembalikan JSON, lalu JS di form
    | yang mengisi ulang tabel Detail Budget. Simpan tetap lewat tombol
    | "Simpan" seperti biasa, supaya alur validasi & DB transaction sama
    | persis dengan input manual.
    */

    private const IMPORT_HEADERS = ['Account', 'Deskripsi', 'Harga Satuan', 'Qty', 'Unit', 'Notes'];

    /**
     * GET /budget-plan/import-template?source=PROJECT|OVERHEAD|PAYROLL
     * Download template Excel siap isi, lengkap dengan dropdown Account & Unit.
     */
    public function downloadTemplate(Request $request)
    {
        $sourceType = strtoupper($request->query('source', 'PROJECT'));
        $accounts   = $this->getAccountsFor($sourceType);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detail Budget');

        // Header
        foreach (self::IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i); // A, B, C, ...
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        // Contoh baris
        $sheet->setCellValue('A2', $accounts->first()->name ?? 'NAMA ACCOUNT');
        $sheet->setCellValue('B2', 'Contoh deskripsi item');
        $sheet->setCellValue('C2', 100000);
        $sheet->setCellValue('D2', 1);
        $sheet->setCellValue('E2', 'pcs');
        $sheet->setCellValue('F2', 'Catatan opsional');

        // Lebar kolom
        foreach (['A' => 30, 'B' => 30, 'C' => 16, 'D' => 8, 'E' => 14, 'F' => 30] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Sheet referensi: daftar Account & Unit yang valid (dipakai dropdown)
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $refSheet->setCellValue('A1', 'Daftar Account Valid');
        $refSheet->setCellValue('B1', 'Daftar Unit Valid');
        $refSheet->getStyle('A1:B1')->getFont()->setBold(true);

        foreach ($accounts->values() as $i => $acc) {
            $refSheet->setCellValue('A' . ($i + 2), $acc->name);
        }
        foreach (BudgetPlanDetail::UNITS as $i => $unit) {
            $refSheet->setCellValue('B' . ($i + 2), $unit);
        }
        $refSheet->getColumnDimension('A')->setWidth(30);
        $refSheet->getColumnDimension('B')->setWidth(18);

        // Dropdown Account (kolom A) & Unit (kolom E) di sheet utama, merujuk ke sheet Referensi
        $accountCount = max($accounts->count(), 1);
        $unitCount    = count(BudgetPlanDetail::UNITS);

        for ($row = 2; $row <= 200; $row++) {
            $validationAccount = $sheet->getCell('A' . $row)->getDataValidation();
            $validationAccount->setType(DataValidation::TYPE_LIST);
            $validationAccount->setErrorStyle(DataValidation::STYLE_WARNING);
            $validationAccount->setAllowBlank(true);
            $validationAccount->setShowInputMessage(true);
            $validationAccount->setShowErrorMessage(true);
            $validationAccount->setShowDropDown(false);
            $validationAccount->setPromptTitle('Pilih Account');
            $validationAccount->setPrompt('Pilih dari daftar di sheet Referensi.');
            $validationAccount->setFormula1("Referensi!\$A\$2:\$A\$" . ($accountCount + 1));

            $validationUnit = $sheet->getCell('E' . $row)->getDataValidation();
            $validationUnit->setType(DataValidation::TYPE_LIST);
            $validationUnit->setErrorStyle(DataValidation::STYLE_WARNING);
            $validationUnit->setAllowBlank(true);
            $validationUnit->setShowInputMessage(true);
            $validationUnit->setShowErrorMessage(true);
            $validationUnit->setShowDropDown(false);
            $validationUnit->setPromptTitle('Pilih Unit');
            $validationUnit->setPrompt('Pilih dari daftar di sheet Referensi.');
            $validationUnit->setFormula1("Referensi!\$B\$2:\$B\$" . ($unitCount + 1));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template-detail-budget-' . strtolower($sourceType) . '-' . now()->format('Ymd-His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /budget-plan/import-preview
     * Body: file (xlsx), source_type (PROJECT|OVERHEAD|PAYROLL)
     * Response JSON: { rows: [...], errors: [...] }
     * Tidak menyimpan apa pun ke database — hanya parsing untuk ditampilkan
     * & diisi ulang oleh JS ke tabel Detail Budget di form.
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls|max:5120',
            'source_type' => 'required|in:PROJECT,OVERHEAD,PAYROLL',
        ]);

        $accounts = $this->getAccountsFor($request->source_type)
            ->keyBy(fn ($a) => strtoupper(trim($a->name)));

        $units = collect(BudgetPlanDetail::UNITS)->map(fn ($u) => strtoupper($u));

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Detail Budget') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows'   => [],
                'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            // Baris 0 = header, lewati
            if ($i === 0) continue;

            [$accName, $deskripsi, $amount, $qty, $unit, $notes] = array_pad($row, 6, null);

            // Lewati baris kosong total
            if (blank($accName) && blank($deskripsi) && blank($amount) && blank($qty)) {
                continue;
            }

            $rowNum = $i + 1; // nomor baris asli di Excel (1-based, +1 karena header)
            $accKey = strtoupper(trim((string) $accName));
            $account = $accounts->get($accKey);

            if (!$account && filled($accName)) {
                $errors[] = "Baris {$rowNum}: Account \"{$accName}\" tidak ditemukan di daftar Variable Cost/Overhead/Payroll yang sesuai.";
            }

            $unitKey    = strtoupper(trim((string) $unit));
            $unitValid  = blank($unit) || $units->contains($unitKey);
            if (!$unitValid) {
                $errors[] = "Baris {$rowNum}: Unit \"{$unit}\" tidak valid.";
            }

            $amountNum = is_numeric($amount) ? (int) round((float) $amount) : (int) $this->normalizeMoney((string) $amount);
            $qtyNum    = is_numeric($qty) ? (float) $qty : 1;
            if ($qtyNum <= 0) $qtyNum = 1;

            $result[] = [
                'row'         => $rowNum,
                'account_id'  => $account->id ?? null,
                'account_name'=> $account->name ?? $accName,
                'deskripsi'   => $deskripsi,
                'amount'      => $amountNum,
                'qty'         => $qtyNum,
                'unit'        => $unitValid && filled($unit) ? strtolower(trim((string) $unit)) : null,
                'notes'       => $notes,
                'total'       => $amountNum * $qtyNum,
            ];
        }

        if (empty($result) && empty($errors)) {
            $errors[] = 'Tidak ada baris data yang terbaca dari file. Pastikan mengisi mulai baris ke-2.';
        }

        return response()->json([
            'rows'   => $result,
            'errors' => $errors,
        ]);
    }
}