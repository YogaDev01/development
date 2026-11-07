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
use App\Models\RequestDetail;
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
    use Concerns\ScopesBudgetPlanVisibility;
    // normalizeMoney / syncDetails / generateCode / buildUniqueCode / getAccountsFor
    // + ACCOUNT_CATEGORY pindah ke Concerns\BuildsBudgetPlan (dipakai juga form PO).
    use Concerns\BuildsBudgetPlan;

    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI SUMBER DANA
    |--------------------------------------------------------------------------
    | Budget plan bersumber dari SALAH SATU:
    |   - Project    (PO customer) -> variable cost
    |   - CostCenter (internal)    -> overhead / payroll
    */

    /** Role yang boleh melihat budget OVERHEAD */
    // OVERHEAD_PRIVILEGED_ROLES / PAYROLL_ROLES / PROJECT_PRIVILEGED_ROLES
    // pindah ke Concerns\ScopesBudgetPlanVisibility bersama method yang memakainya.

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

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
            ->whereIn('projects.status', Project::BUDGETABLE_STATUSES)
            ->orderBy('customers.name')
            ->orderBy('projects.name')
            ->select('projects.*');

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'admin'])) {
            // Access Group individu ∪ team ACTIVE (User::accessibleProjectIds()).
            $projectIds = $user->accessibleProjectIds();
            if ($projectIds->isNotEmpty()) {
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

    // overheadPrivileged() / accessibleOverheadCostCenterIds() / canSeeOverhead() /
    // canSeePayroll() pindah ke Concerns\ScopesBudgetPlanVisibility.

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
            if (in_array($role, self::BP_PROJECT_PRIVILEGED_ROLES)) {
                return true;
            }
            return $user->accessibleProjectIds()->contains((int) $bp->project_id);
        }

        if ($bp->isCostCenterBudget()) {
            if (strtoupper($bp->costCenter?->type ?? '') === 'PAYROLL') {
                return $this->bpCanSeePayroll();
            }
            return $this->bpOverheadPrivileged()
                || $this->bpAccessibleOverheadCostCenterIds()->contains((int) $bp->cost_center_id);
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
        if ($this->bpOverheadPrivileged()) {
            $conditions[] = ['types' => CostCenter::OVERHEAD_LIKE_TYPES, 'ids' => null];
        } else {
            $ccIds = $this->bpAccessibleOverheadCostCenterIds();
            if ($ccIds->isNotEmpty()) {
                $conditions[] = ['types' => CostCenter::OVERHEAD_LIKE_TYPES, 'ids' => $ccIds->all()];
            }
        }

        // Payroll: role-gated
        if ($this->bpCanSeePayroll()) {
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

    /**
     * Budget Plan yang dibuat dari form PO (via_po_approval) tidak punya workflow
     * sendiri: statusnya digerakkan approval PO (submit -> CHECKED, verify ->
     * VERIFIED, approve -> ACTIVE, revise -> DRAFT). Aksi workflow Budget Plan,
     * ubah, dan hapus ditolak untuk BP ini. Closing (ACTIVE -> CLOSING/CLOSED)
     * tetap memakai alur biasa.
     */
    private function rejectIfBundled(BudgetPlan $bp)
    {
        if ($bp->isBundled()) {
            return redirect('/budget-plan')
                ->with('error', 'Budget Plan ini dikelola lewat approval PO (diubah lewat Edit PO, disetujui bersama PO; dapat dihapus lewat Edit PO selagi masih DRAFT).');
        }

        return null;
    }

    // scopeVisibleTo() pindah ke Concerns\ScopesBudgetPlanVisibility supaya
    // GlobalSearchController bisa memakainya lewat `use`, bukan dengan
    // me-resolve controller ini dari container.

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
            ->withSum(['costRequests as used_amount_sum' => fn ($q) => $q->disbursed()], DB::raw(BudgetRequest::effectiveCostSql()))
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

        // Pilihan Account untuk modal "Tambah COA" (Budget Plan ACTIVE) — hanya
        // dimuat untuk role yang boleh memakainya (lihat addCoa()).
        $coaAccounts = [];
        $coaCategoryOf = self::ACCOUNT_CATEGORY;
        if (Auth::user()->hasRole(['superadmin', 'manager'])) {
            foreach (array_unique($coaCategoryOf) as $category) {
                $coaAccounts[$category] = Account::active()->byCategory($category)
                    ->orderBy('name')->get(['id', 'name']);
            }
        }

        return view('budget-plan.index', compact('budgetPlans', 'summary', 'coaAccounts', 'coaCategoryOf'));
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

        /*
        | Aturan visibilitas dipanggil, TIDAK disalin.
        |
        | Sebelumnya seluruh badan scopeVisibleTo() diketik ulang di sini -
        | 30 baris identik. Dua salinan aturan akses berarti ekspor dan daftar
        | bisa diam-diam berbeda isi: yang satu diperbarui, yang lain
        | terlupakan, dan gejalanya adalah baris yang tidak tampil di tabel
        | tapi ikut terbawa ke berkas Excel.
        */
        $query = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter', 'inputBy']);

        $this->scopeVisibleTo($query);

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
                $allowed = $this->bpCanSeePayroll();
            } else {
                $allowed = $this->bpOverheadPrivileged()
                    || $this->bpAccessibleOverheadCostCenterIds()->contains((int) $costCenter->id);
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
        // "Used" seluruh sub-item dimuat sekaligus (bukan per baris).
        BudgetPlanDetail::primeCostLines($sortedDetails);

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

    /** Style baris header tabel pada sebuah sheet (biru gelap, teks putih, bold). */
    private function styleSheetHeader(Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $i => $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }
    }

    /**
     * Baris biaya per Cost Request untuk tab Cost modal Budget Plan dan sheet
     * Detail export Cost (aturan sama dengan realizedCostValue, per baris):
     *   - Cash Advance + settle sudah diajukan (termasuk 0) -> SATU BARIS PER
     *     BARIS SETTLEMENT, di Akun / Sub COA efektifnya (Settlement Custom
     *     COA, 25 Okt 2026 — baris bisa dipecah, pindah Sub COA, atau
     *     tambahan). Baris yang tidak dipindah tetap menampilkan deskripsi &
     *     catatan item asalnya seperti sebelumnya;
     *   - selainnya -> satu baris per item pengajuan, nilai pengajuan;
     *   - item takeout -> tetap tampil, nilai 0.
     * Jumlah 'effective' seluruh baris = realizedCostValue() CR itu.
     *
     * Relasi type, details.account, details.budgetDetail.account,
     * realizations.account, realizations.budgetDetail.account perlu dimuat.
     */
    private function crCostRows(BudgetRequest $cr): \Illuminate\Support\Collection
    {
        $rows = collect();

        $takeout = fn (RequestDetail $d) => [
            'account'     => $d->account->name ?? $d->budgetDetail->account->name ?? '-',
            'sub_coa'     => $d->budgetDetail->deskripsi ?? '-',
            'description' => $d->description ?: '-',
            'notes'       => $d->notes ?: null,
            'effective'   => 0,
            'is_takeout'  => true,
            'takeout_stage_label' => $d->takeout_stage_label,
            'keterangan'  => 'Takeout · ' . $d->takeout_stage_label,
        ];

        if (! $cr->usesSettlementCost()) {
            foreach ($cr->details as $d) {
                $rows->push($d->isTakenOut() ? $takeout($d) : [
                    'account'     => $d->account->name ?? $d->budgetDetail->account->name ?? '-',
                    'sub_coa'     => $d->budgetDetail->deskripsi ?? '-',
                    'description' => $d->description ?: '-',
                    'notes'       => $d->notes ?: null,
                    'effective'   => (int) $d->total,
                    'is_takeout'  => false,
                    'takeout_stage_label' => null,
                    'keterangan'  => '',
                ]);
            }

            return $rows;
        }

        $settleRow = function ($r, ?RequestDetail $d) {
            $bd     = $r->budgetDetail ?? $d?->budgetDetail;
            $pindah = $d && $r->effectiveBudgetDetailId($d) !== ($d->budget_detail_id ? (int) $d->budget_detail_id : null);
            $biasa  = $d && ! $pindah;

            return [
                'account'     => $r->account->name ?? $bd?->account?->name ?? $d?->account?->name ?? '-',
                'sub_coa'     => $bd->deskripsi ?? '-',
                'description' => $biasa ? ($d->description ?: '-') : ($r->description ?: '-'),
                'notes'       => $biasa ? ($d->notes ?: null) : ($r->notes ?: null),
                'effective'   => (int) $r->total,
                'is_takeout'  => false,
                'takeout_stage_label' => null,
                'keterangan'  => ! $d
                    ? 'Baris tambahan'
                    : ($pindah ? 'Pindah dari: ' . ($d->budgetDetail->deskripsi ?? '-') : ''),
            ];
        };

        foreach ($cr->details as $d) {
            if ($d->isTakenOut()) {
                $rows->push($takeout($d));
                continue;
            }

            foreach ($cr->realizations->where('request_detail_id', $d->id)->sortBy('id') as $r) {
                $rows->push($settleRow($r, $d));
            }
        }

        // Baris tambahan (dan baris yang item asalnya sudah tidak ikut).
        $includedIds = $cr->details->reject(fn ($d) => $d->isTakenOut())->pluck('id')->all();
        foreach ($cr->realizations->sortBy('id') as $r) {
            if ($r->request_detail_id && in_array($r->request_detail_id, $includedIds)) continue;
            $rows->push($settleRow($r, null));
        }

        return $rows;
    }

    /**
     * Daftar CR di balik satu sub-item untuk modal Budget Plan (tab Budget)
     * dan sheet Detail export Budget — dari BudgetPlanDetail::costLines(),
     * sumber yang sama dengan kolom "Used".
     *
     * Tampilan per unit mengikuti aturan lama: baris settlement Cash Advance
     * yang nilainya > 0 tampil sebagai baris settle (nominal & deskripsi
     * settle); selainnya baris pengajuan. Catatan: aturan "> 0" ini BEDA dari
     * "Used" (settle 0 dihitung 0) — perilaku lama yang dipertahankan apa
     * adanya. Unit yang item asalnya dipindah seluruhnya ke Sub COA lain
     * (tanpa baris settlement di sub-item ini) tidak ditampilkan, supaya
     * nilainya tidak muncul dua kali.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function budgetCostRows(\App\Models\BudgetPlanDetail $d): \Illuminate\Support\Collection
    {
        return $d->costLines()
            ->map(function ($u) {
                $cr  = $u['budget_request'];
                $rd  = $u['request_detail'];
                $rr  = $u['realizations'];
                $biasa = $rd && ! $u['is_added'] && ! $u['is_moved'];

                if ($u['real'] !== null && $rr->isEmpty()) {
                    return null;
                }

                $useSettle = $u['real'] !== null && (int) $u['real'] > 0;

                if (! $useSettle && ! $biasa) {
                    return null;
                }

                if ($useSettle) {
                    $satu   = $rr->count() === 1 ? $rr->first() : null;
                    $amount = (int) $u['real'];
                    $price  = $satu ? (int) $satu->amount : $amount;
                    $qty    = $satu ? (float) $satu->qty : 1.0;
                    $desc   = $rr->pluck('description')->filter()->implode('; ')
                        ?: ($rd?->description ?: ($rd?->notes ?: '-'));
                } else {
                    $amount = (int) $rd->total;
                    $price  = (int) $rd->amount;
                    $qty    = (float) $rd->qty;
                    $desc   = $rd->notes ?: ($rd->description ?: '-');
                }

                return [
                    'cr'        => $cr,
                    'amount'    => $amount,
                    'price'     => $price,
                    'qty'       => $qty,
                    'desc'      => $desc,
                    'is_settle' => $useSettle,
                    'is_added'  => (bool) $u['is_added'],
                    'is_moved'  => (bool) $u['is_moved'],
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * EXPORT COST — 1 file, 2 sheet:
     *   Header : satu baris per Cost Request (ringkasan + effective cost)
     *   Detail : satu baris per item CR — atau per baris settlement untuk Cash
     *            Advance yang settlement-nya sudah diajukan — dikelompokkan
     *            CoA / Sub CoA, nilai effective (lihat crCostRows())
     */
    public function exportCost($id)
    {
        $bp = BudgetPlan::with(['project.customer', 'costCenter'])->findOrFail($id);
        abort_unless($this->canViewBudgetPlan($bp), 403);

        $crs = BudgetRequest::where('budget_plan_id', $bp->id)
            ->disbursed()
            ->with([
                'type', 'beneficiary',
                'details', 'details.budgetDetail.account', 'details.account',
                'realizations.account', 'realizations.budgetDetail.account',
            ])
            ->latest()
            ->get();

        $spreadsheet = new Spreadsheet();

        // ── SHEET 1: HEADER (per Cost Request) ──────────────────────
        $header = $spreadsheet->getActiveSheet();
        $header->setTitle('Header');
        $this->styleSheetHeader($header, [
            'Kode CR', 'Deskripsi', 'Tipe', 'Beneficiary', 'Request',
            'Settlement', 'Refund', 'Expense Claim', 'Effective', 'Status', 'Tanggal',
        ]);

        $r = 2;
        foreach ($crs as $cr) {
            $eff = (int) $this->crCostRows($cr)->sum('effective');

            $header->setCellValue('A' . $r, $cr->code);
            $header->setCellValue('B' . $r, $cr->description);
            $header->setCellValue('C' . $r, $cr->type->name ?? '-');
            $header->setCellValue('D' . $r, $cr->beneficiary->nickname ?? '-');
            $header->setCellValue('E' . $r, (int) $cr->amount);
            $header->setCellValue('F' . $r, (int) $cr->realizedCostValue());
            $header->setCellValue('G' . $r, (int) $cr->refundAmount());
            $header->setCellValue('H' . $r, (int) $cr->expenseClaimAmount());
            $header->setCellValue('I' . $r, $eff);
            $header->setCellValue('J' . $r, $cr->status);
            $header->setCellValue('K' . $r, $cr->created_at?->format('d-m-Y'));
            $r++;
        }
        foreach (['A'=>18,'B'=>36,'C'=>16,'D'=>18,'E'=>16,'F'=>16,'G'=>14,'H'=>16,'I'=>16,'J'=>14,'K'=>12] as $col => $w) {
            $header->getColumnDimension($col)->setWidth($w);
        }

        // ── SHEET 2: DETAIL (per item CR / per baris settlement) ─────
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detail');
        $this->styleSheetHeader($detail, [
            'Kode CR', 'CoA', 'Sub CoA', 'Deskripsi', 'Effective', 'Keterangan',
        ]);

        $r = 2;
        foreach ($crs as $cr) {
            foreach ($this->crCostRows($cr) as $row) {
                $detail->setCellValue('A' . $r, $cr->code);
                $detail->setCellValue('B' . $r, $row['account']);
                $detail->setCellValue('C' . $r, $row['sub_coa']);
                $detail->setCellValue('D' . $r, $row['description']);
                $detail->setCellValue('E' . $r, $row['effective']);
                $detail->setCellValue('F' . $r, $row['keterangan']);
                $r++;
            }
        }
        foreach (['A'=>18,'B'=>20,'C'=>30,'D'=>40,'E'=>16,'F'=>20] as $col => $w) {
            $detail->getColumnDimension($col)->setWidth($w);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'budget-plan-' . $bp->code . '-cost.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * EXPORT BUDGET — 1 file, 2 sheet:
     *   Header : satu baris per sub-item Budget Plan (Account/Sub CoA + Budget/Used/Variance)
     *   Detail : satu baris per item CR di balik tiap sub-item (deskripsi settle-aware, nilai effective)
     */
    public function exportBudget($id)
    {
        $bp = BudgetPlan::with(['details.account'])->findOrFail($id);
        abort_unless($this->canViewBudgetPlan($bp), 403);

        $details = $bp->details->sortBy(fn ($d) => $d->account->name ?? '-')->values();
        // "Used" + daftar CR seluruh sub-item dimuat sekaligus.
        BudgetPlanDetail::primeCostLines($details);

        $spreadsheet = new Spreadsheet();

        // ── SHEET 1: HEADER (per sub-item Budget Plan) ──────────────
        $header = $spreadsheet->getActiveSheet();
        $header->setTitle('Header');
        $this->styleSheetHeader($header, [
            'Account', 'Sub CoA', 'Harga Satuan', 'Qty', 'Unit', 'Budget', 'Used', 'Variance',
        ]);

        $r = 2;
        foreach ($details as $d) {
            $header->setCellValue('A' . $r, $d->account->name ?? '-');
            $header->setCellValue('B' . $r, $d->deskripsi);
            $header->setCellValue('C' . $r, (int) $d->amount);
            $header->setCellValue('D' . $r, (float) $d->qty);
            $header->setCellValue('E' . $r, $d->unit);
            $header->setCellValue('F' . $r, (int) $d->total);
            $header->setCellValue('G' . $r, $d->usedAmount());
            $header->setCellValue('H' . $r, $d->remainingAmount());
            $r++;
        }
        foreach (['A'=>20,'B'=>32,'C'=>16,'D'=>8,'E'=>10,'F'=>16,'G'=>16,'H'=>16] as $col => $w) {
            $header->getColumnDimension($col)->setWidth($w);
        }

        // ── SHEET 2: DETAIL (item CR di balik tiap sub-item) ────────
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detail');
        $this->styleSheetHeader($detail, [
            'Account', 'Sub CoA', 'Tanggal', 'Kode CR', 'Beneficiary', 'Deskripsi', 'Harga Satuan', 'Qty', 'Amount',
        ]);

        $r = 2;
        foreach ($details as $d) {
            foreach ($this->budgetCostRows($d) as $row) {
                $cr = $row['cr'];

                $detail->setCellValue('A' . $r, $d->account->name ?? '-');
                $detail->setCellValue('B' . $r, $d->deskripsi);
                $detail->setCellValue('C' . $r, $cr->created_at?->format('d-m-Y') ?? '-');
                $detail->setCellValue('D' . $r, $cr->code ?? '-');
                $detail->setCellValue('E' . $r, $cr->beneficiary->nickname ?? '-');
                $detail->setCellValue('F' . $r, $row['desc']);
                $detail->setCellValue('G' . $r, $row['price']);
                $detail->setCellValue('H' . $r, $row['qty']);
                $detail->setCellValue('I' . $r, $row['amount']);
                $r++;
            }
        }
        foreach (['A'=>20,'B'=>30,'C'=>12,'D'=>18,'E'=>18,'F'=>40,'G'=>14,'H'=>8,'I'=>16] as $col => $w) {
            $detail->getColumnDimension($col)->setWidth($w);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'budget-plan-' . $bp->code . '-budget.xlsx';
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

    /*
    |--------------------------------------------------------------------------
    | BULK APPROVE / REJECT — satu tahap per proses (pola Treasury & Cost Request)
    |--------------------------------------------------------------------------
    | Tidak ada logika approval baru: tiap Budget Plan diproses oleh method satuan
    | yang sama (headApprove / financeVerify / ceoApprove dan pasangan reject-nya),
    | jadi cek role, cek status, aturan "via PO", log, dan notifikasi identik.
    | Tahap ditentukan oleh `status` yang dikirim: Budget Plan yang statusnya
    | sudah berubah dilewati, bukan dipaksa.
    */

    private const BULK_MAX = 50;

    public function bulkApprove(Request $request)
    {
        return $this->bulkProses($request, false);
    }

    public function bulkReject(Request $request)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        return $this->bulkProses($request, true);
    }

    private function bulkProses(Request $request, bool $tolak)
    {
        $request->validate([
            'ids'    => 'required|array|min:1|max:' . self::BULK_MAX,
            'ids.*'  => 'integer',
            'status' => 'required|in:SUBMITTED,CHECKED,VERIFIED',
        ]);

        // status awal => [method approve, method reject]
        $tahap = [
            'SUBMITTED' => ['headApprove', 'headReject'],
            'CHECKED'   => ['financeVerify', 'financeReject'],
            'VERIFIED'  => ['ceoApprove', 'ceoReject'],
        ][$request->input('status')];
        $aksi = $tolak ? $tahap[1] : $tahap[0];

        $ids   = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $plans = BudgetPlan::whereIn('id', $ids)->get()->keyBy('id');

        $ok     = 0;
        $errors = [];

        foreach ($ids as $id) {
            $bp = $plans->get($id);
            if (! $bp) {
                $errors[] = '#' . $id . ' (tidak ditemukan)';
                continue;
            }
            if ($bp->status !== $request->input('status')) {
                $errors[] = $bp->code . ' (status berubah dari ' . $request->input('status') . ' menjadi ' . $bp->status . ' — muat ulang)';
                continue;
            }

            // Aksi satuan mengembalikan redirect + flash; hanya flash 'error'-nya yang dibaca.
            session()->forget(['success', 'error']);
            try {
                $this->{$aksi}($request, $id);
                $error = session('error');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                $error = $e->getMessage() ?: 'akses ditolak.';
            }
            session()->forget(['success', 'error']);

            if ($error) {
                $errors[] = $bp->code . ' (' . $error . ')';
            } else {
                $ok++;
            }
        }

        $redirect = back();
        if ($ok) {
            $redirect->with('success', $ok . ' Budget Plan berhasil ' . ($tolak ? 'ditolak.' : 'di-approve.'));
        }
        if ($errors) {
            $redirect->with('error', count($errors) . ' Budget Plan dilewati: ' . implode(', ', array_slice($errors, 0, 5))
                . (count($errors) > 5 ? ', …' : '') . '.');
        }

        return $redirect;
    }

    /** Catatan chat dikunci begitu Budget Plan CLOSED (seperti CR yang CLOSED). */
    private function canPostLogNote(BudgetPlan $bp): bool
    {
        return $bp->status !== 'CLOSED';
    }

    /**
     * Tab Log (gaya chat, sama dengan Cost Request): riwayat workflow + catatan
     * bebas, terbaru lebih dulu. Tabel catatan yang belum dimigrasi di server
     * tidak boleh merusak detail Budget Plan.
     */
    private function logsFor(BudgetPlan $bp)
    {
        $semua = $bp->logs->map(fn ($l) => [
            'kind'         => 'activity',
            'action_label' => $l->action_label,
            'action_color' => $l->action_color,
            'status_from'  => $l->status_from,
            'status_to'    => $l->status_to,
            'user'         => $l->user_name ?? '-',
            'user_id'      => $l->user_id,
            'role'         => $l->role ?? '-',
            'notes'        => $l->notes,
            'at'           => $l->created_at,
        ]);

        try {
            $catatan = \App\Models\BudgetPlanLogNote::where('budget_plan_id', $bp->id)->get()
                ->map(fn ($n) => [
                    'kind'         => 'note',
                    'action_label' => 'Catatan',
                    'action_color' => 'secondary',
                    'status_from'  => null,
                    'status_to'    => null,
                    'user'         => $n->actor_name ?? 'System',
                    'user_id'      => $n->acted_by,
                    'role'         => $n->actor_role ?? '-',
                    'notes'        => $n->notes,
                    'at'           => $n->created_at,
                ]);
            $semua = $semua->concat($catatan);
        } catch (\Throwable $e) {
            // tabel catatan belum ada: tampilkan riwayat workflow saja
        }

        $semua   = $semua->sortByDesc('at')->values();
        $avatars = \App\Models\User::avatarUrlMap($semua->pluck('user_id'));

        return $semua->map(fn ($l) => [
            'kind'         => $l['kind'],
            'avatar'       => $avatars[$l['user_id']] ?? null,
            'action_label' => $l['action_label'],
            'action_color' => $l['action_color'],
            'status_from'  => $l['status_from'],
            'status_to'    => $l['status_to'],
            'user'         => $l['user'],
            'user_id'      => $l['user_id'],
            'role'         => $l['role'],
            'notes'        => $l['notes'],
            'date'         => $l['at']?->format('d M Y H:i'),
        ])->all();
    }

    /**
     * Kirim catatan ke tab Log (gaya chat). Siapa pun yang boleh melihat Budget
     * Plan boleh menulis; ditutup begitu Budget Plan CLOSED.
     */
    public function storeLogNote(Request $request, $id)
    {
        $bp = $this->findVisibleBudgetPlan($id);

        $data = $request->validate(['notes' => 'required|string|max:1000']);

        if (! $this->canPostLogNote($bp)) {
            return response()->json(['message' => 'Budget Plan sudah Closed; catatan baru tidak dapat ditambahkan.'], 422);
        }

        $user = Auth::user();
        $note = \App\Models\BudgetPlanLogNote::create([
            'budget_plan_id' => $bp->id,
            'acted_by'       => $user?->id,
            'actor_name'     => $user?->name ?? 'System',
            'actor_role'     => $user?->role?->name,
            'notes'          => trim($data['notes']),
        ]);

        return response()->json([
            'ok'  => true,
            'log' => [
                'kind'         => 'note',
                'avatar'       => $user?->avatar_url,
                'action_label' => 'Catatan',
                'action_color' => 'secondary',
                'status_from'  => null,
                'status_to'    => null,
                'user'         => $note->actor_name,
                'user_id'      => $note->acted_by,
                'role'         => $note->actor_role ?? '-',
                'notes'        => $note->notes,
                'date'         => now()->format('d M Y H:i'),
            ],
        ]);
    }

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
            ->disbursed()
            ->with([
                'type', 'applicant', 'beneficiary',
                'realizations.account', 'realizations.budgetDetail.account',
                'details', 'details.budgetDetail.account', 'details.account',
            ])
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
            'APPROVED' => 'Approved', 'QUEUED' => 'Queued', 'PAID' => 'Paid', 'REALIZED' => 'Settle',
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
        $sumClaim   = (int) $costRequests->sum(fn ($cr) => $cr->expenseClaimAmount());
        // Actual Used = Request - Refund (pengurang) + Expense Claim (tambahan
        // bayar kekurangan settlement). Setara nilai settlement aktual.
        $actualUsed = $sumRequest - $sumRefund + $sumClaim;

        // Tab Cash Advance — CR bertipe Cash Advance yang terhubung ke budget plan
        // ini, dikelompokkan per PIC (bukan per beneficiary — satu PIC bisa
        // memegang beberapa penerima dana, dan yang perlu dikejar saat CA
        // menggantung adalah orangnya). "Settle" = sudah melewati tahap
        // settlement (BudgetRequest::SETTLED_STATUSES), bukan cuma sudah Paid.
        // Referensi tabel: FinanceSummaryController::activeCashAdvanceData().
        $cashAdvanceCrs = BudgetRequest::where('budget_plan_id', $bp->id)
            ->cashAdvance()
            ->where('status', '!=', 'DRAFT')
            ->with(['beneficiary.pic'])
            ->orderByDesc('created_at')
            ->get();

        $caNegativeStatuses = ['REJECTED', 'CANCELLED'];
        $caSettledCount     = $cashAdvanceCrs->filter(fn ($cr) => $cr->isSettled())->count();
        $caNegativeCount    = $cashAdvanceCrs->whereIn('status', $caNegativeStatuses)->count();

        $caGroups = $cashAdvanceCrs
            ->groupBy(fn ($cr) => $cr->beneficiary?->pic_id
                ? 'pic:' . $cr->beneficiary->pic_id
                : 'ben:' . ($cr->beneficiary_id ?? 0))
            ->map(function ($crs) use ($caNegativeStatuses) {
                $first    = $crs->first();
                $settled  = $crs->filter(fn ($cr) => $cr->isSettled())->count();
                $negative = $crs->whereIn('status', $caNegativeStatuses)->count();

                return [
                    'pic_name' => $first->beneficiary?->pic?->pic
                        ?: ($first->beneficiary?->nickname ?? $first->beneficiary?->complete_name ?? '-'),
                    'total'    => $crs->count(),
                    'settled'  => $settled,
                    'pending'  => $crs->count() - $settled - $negative,
                    'value'          => (int) $crs->sum('amount'),
                    'value_formatted'=> number_format($crs->sum('amount'), 0, ',', '.'),
                    'items' => $crs->map(fn ($cr) => [
                        'id'               => $cr->id,
                        'code'             => $cr->code,
                        'description'      => $cr->description ?: '-',
                        'beneficiary'      => $cr->beneficiary?->nickname ?? $cr->beneficiary?->complete_name ?? '-',
                        'amount'           => (int) $cr->amount,
                        'amount_formatted' => number_format($cr->amount, 0, ',', '.'),
                        'status'           => $cr->status,
                        'status_label'     => $cr->status_label,
                        'status_color'     => $cr->status_color,
                        'is_settled'       => $cr->isSettled(),
                        'paid_date'        => $cr->paid_at?->format('d/m/Y'),
                        'aging'            => $cr->paid_at
                            ? (int) $cr->paid_at->copy()->startOfDay()->diffInDays(now()->startOfDay(), false)
                            : null,
                    ])->values(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'id'               => $bp->id,
            'code'             => $bp->code,
            'description'      => $bp->description,
            'status'           => $bp->status,
            'status_color'     => $bp->status_color,
            'via_po_approval'  => $bp->isBundled(),
            'notes'            => $bp->notes,
            'approver_notes'   => $bp->approver_notes,
            'amount'           => (int) ($bp->amount ?? 0),
            'amount_formatted' => $bp->amount_formatted,
            'used_amount'      => $bp->usedAmount(),
            'remaining_amount' => $bp->remainingAmount(),
            'usage_percentage' => $bp->amount > 0
                ? round(($bp->usedAmount() / $bp->amount) * 100, 1)
                : 0,
            'project_id'       => $bp->project_id,
            'project'          => $bp->project->name ?? '-',
            'project_code'     => $bp->project->code ?? '-',
            'customer'         => $bp->project->customer->name ?? '-',
            'manager'          => $bp->project?->pm_internal_name ?: '-',

            'summary' => [
                'status_counts' => $statusCounts,
                'type_counts'   => $typeCounts,
                'request_total' => $allRequests->count(),
                'budget' => [
                    'budget_value_formatted'   => $rp($bp->amount ?? 0),
                    'request_formatted'        => $rp($sumRequest),
                    'refund_formatted'         => $rp($sumRefund),
                    'expense_claim_formatted'  => $rp($sumClaim),
                    'actual_used_formatted'    => $rp($actualUsed),
                ],
            ],
            'cash_advance' => [
                'total'       => $cashAdvanceCrs->count(),
                'settled'     => $caSettledCount,
                'pending'     => $cashAdvanceCrs->count() - $caSettledCount - $caNegativeCount,
                'rejected'    => $caNegativeCount,
                'total_value' => (int) $cashAdvanceCrs->sum('amount'),
                'groups'      => $caGroups,
            ],

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

            'details' => tap($bp->details, fn ($all) => BudgetPlanDetail::primeCostLines($all))->map(function ($d) {
                $used      = $d->usedAmount();
                $remaining = $d->remainingAmount();

                // Daftar CR di balik sub-item ini — sumber yang sama dengan
                // "Used" (lihat budgetCostRows()).
                $crDetails = $this->budgetCostRows($d)
                    ->map(fn ($row) => [
                        'cr_id'                => $row['cr']->id,
                        'cr_date_formatted'    => $row['cr']->created_at?->format('d M Y') ?? '-',
                        'beneficiary'          => $row['cr']->beneficiary->nickname ?? '-',
                        'notes'                => $row['desc'],
                        'is_settle'            => $row['is_settle'],
                        'unit_price_formatted' => number_format($row['price'], 0, ',', '.'),
                        'qty'                  => $row['qty'],
                        'amount'               => $row['amount'],
                        'amount_formatted'     => number_format($row['amount'], 0, ',', '.'),
                    ])
                    ->values();

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

            'logs'              => $this->logsFor($bp),
            'can_post_log_note' => $this->canPostLogNote($bp),

            // Daftar Cost Request yang terhubung ke Budget Plan ini
            'requests' => $costRequests->map(function ($cr) use ($realizedValueOf, $refundOf) {
                $realisasi = $realizedValueOf($cr);
                $refund    = $refundOf($cr);

                // Effective cost per baris (aturan sama dengan realizedCostValue,
                // diterapkan per baris — lihat crCostRows()): Cash Advance yang
                // settlement-nya sudah diajukan per baris settlement di Akun /
                // Sub COA efektifnya; selainnya per item pengajuan. Item takeout
                // tampil dengan nilai 0.
                $isCa = $cr->isCashAdvance();

                // Grouping tab Cost: CR -> CoA (Account) -> Sub CoA (deskripsi
                // Budget Plan Detail) + Detail Deskripsi. Nilai = effective cost.
                $coaGroups = $this->crCostRows($cr)
                    ->groupBy('account')
                    ->map(function ($items, $accName) {
                        $rows = $items->map(fn ($row) => [
                            'sub_coa'     => $row['sub_coa'],
                            'description' => $row['description'],
                            'notes'       => $row['notes'],
                            'effective'   => $row['effective'],
                            'effective_formatted' => number_format($row['effective'], 0, ',', '.'),
                            'is_takeout'  => $row['is_takeout'],
                            'takeout_stage_label' => $row['takeout_stage_label'],
                            // "Baris tambahan" / "Pindah dari: {Sub COA asal}".
                            'keterangan'  => $row['is_takeout'] ? '' : $row['keterangan'],
                        ])->values();

                        return [
                            'account'   => $accName,
                            'effective' => (int) $rows->sum('effective'),
                            'effective_formatted' => number_format($rows->sum('effective'), 0, ',', '.'),
                            'rows'      => $rows,
                        ];
                    })
                    ->sortByDesc('effective')
                    ->values();

                return [
                    'id'                  => $cr->id,
                    'code'                => $cr->code,
                    'description'         => $cr->description,
                    'type'                => $cr->type->name ?? '-',
                    'is_cash_advance'     => $isCa,
                    'beneficiary'         => $cr->beneficiary->nickname ?? '-',
                    'amount'              => (int) $cr->amount,
                    'amount_formatted'    => $cr->amount_formatted,
                    'realization'         => $realisasi,
                    'realization_formatted' => number_format($realisasi, 0, ',', '.'),
                    'refund'              => $refund,
                    'refund_formatted'    => number_format($refund, 0, ',', '.'),
                    'expense_claim'           => $cr->expenseClaimAmount(),
                    'expense_claim_formatted' => number_format($cr->expenseClaimAmount(), 0, ',', '.'),
                    'effective'           => (int) $coaGroups->sum('effective'),
                    'effective_formatted' => number_format($coaGroups->sum('effective'), 0, ',', '.'),
                    'status'              => $cr->status,
                    'status_label'        => $cr->status_label,
                    'status_color'        => $cr->status_color,
                    'date'                => $cr->created_at->format('d M Y'),
                    'coa_groups'          => $coaGroups,
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
        if ($r = $this->rejectIfBundled($budgetPlan)) return $r;

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
        if ($r = $this->rejectIfBundled($budgetPlan)) return $r;

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
        if ($r = $this->rejectIfBundled($bp)) return $r;

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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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
        if ($r = $this->rejectIfBundled($bp)) return $r;
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

        // CLOSING yang belum diajukan berkasnya adalah sisa data lama (dulu hasil
        // auto-closing berbasis tanggal, kini sudah dihapus) — manager tetap harus
        // mengunggah berkas closing dari sana, jadi status itu ikut diterima di sini.
        if (!in_array($bp->status, ['ACTIVE', 'CLOSING'], true) || $bp->isClosingSubmitted()) {
            return back()->with('error', 'Hanya Budget Plan berstatus ACTIVE atau CLOSING (belum diajukan) yang dapat diajukan closing.');
        }

        $statusAwal = $bp->status;

        DB::transaction(function () use ($request, $bp, $statusAwal) {
            $bp->update([
                'status'               => 'CLOSING',
                'closing_submitted_at' => now(),
                'approver_notes'       => $request->closing_notes,
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

            $bp->addLog($statusAwal, 'CLOSING', 'closing_submitted', $request->closing_notes);
        });

        NotifyService::send(
            $this->financeUsers(),
            'Pengajuan closing Budget Plan',
            Auth::user()->name . ' mengajukan closing Budget Plan "' . $bp->description . '" (' . $bp->code . ') untuk diverifikasi.',
            route('budget-plan.index'),
            'workflow'
        );

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
    | FINANCE CLOSE — ACTIVE → CLOSED
    |--------------------------------------------------------------------------
    | Jalur pintas untuk Finance menutup budget yang pekerjaannya sudah selesai
    | tanpa menunggu manager mengajukan closing berlampiran. Bedanya dengan
    | verifyClosing(): di sini tidak ada berkas closing yang diverifikasi, jadi
    | alasannya wajib ditulis supaya jejaknya tetap bisa dipertanggungjawabkan.
    */

    public function financeClose(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk menutup Budget Plan.');
        }

        $request->validate(['notes' => 'required|string|max:500']);

        $bp = $this->findVisibleBudgetPlan($id);

        if ($bp->status !== 'ACTIVE') {
            return back()->with('error', 'Hanya Budget Plan berstatus ACTIVE yang dapat ditutup langsung.');
        }

        $bp->update([
            'status'         => 'CLOSED',
            'approver_notes' => $request->notes,
            'approved_by'    => $user->id,
            'approved_at'    => now(),
        ]);
        $bp->addLog('ACTIVE', 'CLOSED', 'finance_closed', $request->notes);

        // Manager pengaju perlu tahu budgetnya sudah ditutup — setelah ini tidak
        // ada lagi cost request yang bisa dibebankan ke sana.
        if ($bp->inputBy) {
            NotifyService::send(
                [$bp->inputBy],
                'Budget Plan ditutup Finance',
                $user->name . ' menutup Budget Plan "' . $bp->description . '" (' . $bp->code . '). Alasan: ' . $request->notes,
                route('budget-plan.index'),
                'workflow'
            );
        }

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

        $bp->update([
            'status'               => 'ACTIVE',
            'closing_submitted_at' => null,
            'approver_notes'       => $request->notes,
        ]);
        $bp->addLog('CLOSING', 'ACTIVE', 'closing_rejected', $request->notes);

        return redirect('/budget-plan')
            ->with('success', 'Closing ditolak. Budget Plan dikembalikan ke status ACTIVE.');
    }

    /*
    |--------------------------------------------------------------------------
    | TAMBAH COA — ACTIVE (manager)
    |--------------------------------------------------------------------------
    | Menambah baris detail (Account / Sub COA) ke Budget Plan yang sudah
    | ACTIVE supaya Cost Request bisa dibebankan ke akun yang belum ada.
    | Nilainya selalu 0 (harga satuan 0, qty 0, total 0): nilai budget TIDAK
    | bertambah, jadi pemakaian di baris baru otomatis tercatat NOB. Baris
    | lama tidak disentuh (CR yang sudah menunjuk ke sana tetap utuh).
    */

    public function addCoa(Request $request, $id)
    {
        $bp = $this->findVisibleBudgetPlan($id, ['costCenter']);

        $category = self::ACCOUNT_CATEGORY[$bp->source_type] ?? null;
        $allowedAccountIds = $this->getAccountsFor($bp->source_type)->pluck('id')->all();

        $request->validate([
            'rows'              => 'required|array|min:1|max:100',
            'rows.*.account_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($allowedAccountIds)],
            'rows.*.deskripsi'  => 'required|string|max:255',
            'rows.*.unit'       => 'required|in:' . implode(',', BudgetPlanDetail::UNITS),
            'notes'             => 'nullable|string|max:2000',
        ], [
            'rows.*.account_id.in' => 'Account harus kategori ' . ($category ?? '-') . ' yang aktif.',
        ]);

        // Baris kembar (Account + Sub COA sama) membingungkan pilihan sub-item
        // di form Cost Request — ditolak, baik terhadap baris lama maupun antar
        // baris baru.
        $keyOf = fn ($accountId, $deskripsi) => (int) $accountId . '|' . strtoupper(trim((string) $deskripsi));

        $result = DB::transaction(function () use ($request, $bp, $keyOf) {
            $locked = BudgetPlan::whereKey($bp->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'ACTIVE') {
                return 'Hanya Budget Plan berstatus ACTIVE yang dapat ditambah COA.';
            }

            $existing = $locked->details()->get(['account_id', 'deskripsi'])
                ->map(fn ($d) => $keyOf($d->account_id, $d->deskripsi))->flip();

            $rows = [];
            foreach ((array) $request->input('rows', []) as $row) {
                $key = $keyOf($row['account_id'], $row['deskripsi']);
                if ($existing->has($key)) {
                    return 'COA "' . trim($row['deskripsi']) . '" dengan Account yang sama sudah ada di Budget Plan ini.';
                }
                $existing->put($key, true);
                $rows[] = $row;
            }

            $names = Account::whereIn('id', array_column($rows, 'account_id'))->pluck('name', 'id');

            foreach ($rows as $row) {
                $locked->details()->create([
                    'account_id' => (int) $row['account_id'],
                    'category'   => 'Variable Cost',   // sama dengan syncBudgetDetails()
                    'type'       => 'additional',       // additional = tambahan setelah budget awal
                    'deskripsi'  => trim($row['deskripsi']),
                    'amount'     => 0,
                    'qty'        => 0,
                    'unit'       => $row['unit'],
                    'total'      => 0,
                ]);
            }

            // Catatan modal (opsional) ditulis di depan, diikuti daftar COA yang ditambahkan.
            $catatan = trim((string) $request->input('notes'));
            $locked->addLog('ACTIVE', 'ACTIVE', 'coa_added', ($catatan !== '' ? $catatan . ' · ' : '')
                . 'Tambah ' . count($rows) . ' COA: '
                . collect($rows)->map(fn ($r) => ($names[$r['account_id']] ?? '-') . ' — ' . trim($r['deskripsi']))->implode('; '));

            return count($rows);
        });

        if (is_string($result)) {
            return back()->with('error', $result);
        }

        return redirect('/budget-plan')
            ->with('success', $result . ' COA berhasil ditambahkan ke ' . $bp->code . ' (nilai budget 0).');
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

        // Temuan F-3 (2026-11-07): sebelumnya siapa pun yang login bisa membaca
        // sub-item, terpakai & sisa Budget Plan mana pun lewat id. Kini sama
        // dengan detail Budget Plan (canViewBudgetPlan) — form CR hanya
        // menawarkan Budget Plan yang memang terlihat, jadi alur normal tetap.
        abort_unless($this->canViewBudgetPlan($bp), 403);

        // "Used" seluruh sub-item dimuat sekaligus (bukan per baris).
        BudgetPlanDetail::primeCostLines($bp->details);

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
        if ($r = $this->rejectIfBundled($bp)) return $r;

        if ($bp->status !== 'DRAFT') {
            return redirect('/budget-plan')
                ->with('error', 'Hanya Budget Plan berstatus DRAFT yang dapat dihapus.');
        }

        /*
        | Berkas dihapus SETELAH transaksi benar-benar sukses.
        |
        | Sebelumnya Storage::delete() dipanggil di DALAM transaksi. Penghapusan
        | berkas tidak transaksional: kalau transaksinya rollback setelah
        | berkasnya terhapus — deadlock, constraint, timeout — barisnya kembali
        | utuh sementara lampirannya sudah hilang dari disk. Hasilnya baris
        | Budget Plan yang menunjuk berkas yang tidak ada lagi, tanpa error di
        | mana pun, dan baru ketahuan saat seseorang mengklik lampirannya.
        |
        | DB::afterCommit() menjalankan closure hanya kalau commit berhasil, dan
        | di luar transaksi apa pun kalau memang tidak ada transaksi.
        */
        $paths = $bp->attachments->pluck('path')->filter()->all();

        DB::transaction(function () use ($bp, $paths) {
            $bp->delete();

            DB::afterCommit(function () use ($paths) {
                foreach ($paths as $path) {
                    Storage::disk('secure')->delete($path);
                }
            });
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
    | yang mengisi ulang tabel Detail Budget. Logikanya ada di trait
    | Concerns\BuildsBudgetPlan (dipakai juga oleh form PO).
    */

    /**
     * GET /budget-plan/import-template?source=PROJECT|OVERHEAD|PAYROLL
     * Download template Excel siap isi, lengkap dengan dropdown Account & Unit.
     */
    public function downloadTemplate(Request $request)
    {
        return $this->budgetImportTemplateResponse((string) $request->query('source', 'PROJECT'));
    }

    /**
     * POST /budget-plan/import-preview
     * Body: file (xlsx), source_type (PROJECT|OVERHEAD|PAYROLL)
     * Response JSON: { rows: [...], errors: [...] }
     * Tidak menyimpan apa pun ke database.
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls|max:5120',
            'source_type' => 'required|in:PROJECT,OVERHEAD,PAYROLL',
        ]);

        return $this->budgetImportPreviewResponse($request, $request->source_type);
    }
}