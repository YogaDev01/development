<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\Customer;
use App\Models\MasterPic;
use App\Models\Cluster;
use App\Models\Stage;
use App\Models\User;
use App\Services\NotifyService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Services\RoleDirectory;

class ProjectController extends Controller
{
    use \App\Http\Controllers\Concerns\CapsLargeLists;

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

    /**
     * Apakah user boleh melihat detail JSON satu PO. Longgar — mengikuti dua
     * pintu masuk yang sah: Daftar PO (menu:list-po, tanpa scoping) & Project
     * Monitoring (per access group, terbuka untuk semua role). Yang diblok:
     * user tanpa menu list-po DAN tanpa PO ini di access group-nya.
     */
    private function canViewProjectJson(Project $project): bool
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        if (in_array($role, ['superadmin', 'admin', 'approver', 'finance', 'finance_manager', 'guest'])) {
            return true;
        }
        if ($user->role && $user->role->hasMenu('list-po')) {
            return true;
        }

        return $user->accessibleProjectIds()->contains($project->id);
    }

    /**
     * Kandidat Manager PO — user dengan role manager / head (case-insensitive).
     */
    private function managerOptions()
    {
        return User::whereHas('role', fn ($r) => $r->whereRaw("LOWER(name) IN ('manager', 'head')"))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function syncDetails(Project $project, Request $request): void
    {
        // Simpan actual_start/actual_end lama — form ini TIDAK mengedit kolom itu lagi
        // (akan ada form terpisah untuk itu), jadi nilainya harus dipertahankan supaya
        // tidak ke-reset null tiap kali Project & Milestone di-generate ulang di sini.
        $oldActuals = $project->details()->get()->keyBy(
            fn($d) => ($d->cluster_id ?? 'x') . '-' . ($d->stage_id ?? 'x')
        );

        $project->details()->delete();

        foreach ((array) $request->input('details', []) as $row) {
            if (blank($row['cluster_id'] ?? null) && blank($row['stage_id'] ?? null)) continue;

            $key = ($row['cluster_id'] ?: 'x') . '-' . ($row['stage_id'] ?: 'x');
            $old = $oldActuals->get($key);

            $project->details()->create([
                'cluster_id'   => $row['cluster_id']   ?: null,
                'stage_id'     => $row['stage_id']     ?: null,
                'plan_start'   => $row['plan_start']   ?: null,
                'plan_end'     => $row['plan_end']     ?: null,
                'actual_start' => $old->actual_start ?? null,
                'actual_end'   => $old->actual_end   ?? null,
                'notes'        => $row['notes']        ?? null,
            ]);
        }
    }

    private function syncPics(Project $project, Request $request): void
    {
        $ids = array_merge(
            (array) $request->input('pics_internal', []),
            (array) $request->input('pics_customer', [])
        );
        $ids = array_values(array_unique(array_filter($ids)));

        $project->pics()->sync($ids);
    }

    /**
     * Sinkron Access Group mana saja yang punya akses ke PO ini — dipilih
     * langsung dari form Create/Edit PO, bukan lewat menu Access Group lagi.
     * access_group_details bisa juga menyimpan baris cost_center (bukan project),
     * jadi di sini cuma menambah/menghapus baris project_id milik PO ini saja.
     */
    private function syncAccessGroups(Project $project, Request $request): void
    {
        $selectedIds = array_map('intval', (array) $request->input('access_group_ids', []));
        $currentIds  = $project->accessGroupLinks()->pluck('access_group_id')->all();

        $toAdd    = array_diff($selectedIds, $currentIds);
        $toRemove = array_diff($currentIds, $selectedIds);

        foreach ($toAdd as $accessGroupId) {
            \App\Models\AccessGroupDetail::create([
                'access_group_id' => $accessGroupId,
                'project_id'      => $project->id,
                'cost_center_id'  => null,
            ]);
        }

        if (!empty($toRemove)) {
            $project->accessGroupLinks()->whereIn('access_group_id', $toRemove)->delete();
        }
    }

    private function storeAttachments(Project $project, Request $request, string $type = 'general'): void
    {
        $key = $type === 'general' ? 'attachments' : 'closing_attachments';

        // Format: {key}[i][title] + {key}[i][file]
        $fileRows  = $request->file($key, []);
        $inputRows = $request->input($key, []);

        foreach ($fileRows as $i => $row) {
            $file = $row['file'] ?? null;
            if (!$file || !$file->isValid()) continue;

            $title = $inputRows[$i]['title'] ?? $file->getClientOriginalName();

            $path = $file->store('project_attachments', 'secure');
            $project->attachments()->create([
                'type'      => $type,
                'title'     => strtoupper($title),
                'file_path' => $path,
                'file_name' => basename($path),
                'file_type' => $file->getClientMimeType(),
            ]);
        }
    }

    /**
     * Tambah baris No. PO baru (append-only — dipanggil dari form Create & Edit).
     * Status baris ada siklus hidupnya sendiri (RELEASED -> WORK_DONE -> INVOICED
     * -> PAID) yang diubah lewat endpoint terpisah dari show modal, jadi form
     * utama TIDAK pernah menghapus/menimpa baris yang sudah ada.
     */
    private function storePoNumbers(Project $project, Request $request): void
    {
        $fileRows  = $request->file('po_numbers', []);
        $inputRows = (array) $request->input('po_numbers', []);

        foreach ($inputRows as $i => $row) {
            if (blank($row['po_number'] ?? null)) continue;

            $poNumber = $project->poNumbers()->create([
                'po_number'    => strtoupper(trim($row['po_number'])),
                'nominal'      => $this->normalizeMoney($row['nominal'] ?? 0),
                'release_date' => $row['release_date'] ?: null,
                'status'       => 'RELEASED',
                'input_by'     => Auth::id(),
            ]);

            $file = $fileRows[$i]['file'] ?? null;
            if ($file && $file->isValid()) {
                $path = $file->store('project_attachments', 'secure');
                $poNumber->attachments()->create([
                    'project_id' => $project->id,
                    'type'       => 'po_number',
                    'title'      => strtoupper($poNumber->po_number),
                    'file_path'  => $path,
                    'file_name'  => basename($path),
                    'file_type'  => $file->getClientMimeType(),
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $query = Project::with(['customer', 'inputBy', 'manager', 'budgetPlans', 'poNumbers', 'invoices', 'details.stage']);

        /*
        | Ringkasan dihitung agregat supaya tetap benar walau daftar barisnya
        | dibatasi (lihat Concerns\CapsLargeLists). Angka stepper harus
        | mencerminkan seluruh data; kalau ikut dipotong, ia salah tanpa ada
        | tanda apa pun bahwa ia salah.
        */
        $statusCounts = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        [$projects, $truncated, $total] = $this->cappedRows($query, 'id');
        $maxRows = $this->maxRows;

        /*
        | Badge R/B/I/P (PO Received/BAST/Invoice/Payment) diselaraskan dengan
        | status milestone Project Monitoring saat ini, supaya tidak "nyangkut"
        | abu-abu kalau milestone-nya sudah Done tapi kolom header belum
        | ter-sync.
        |
        | Penyelarasan ini sekarang HANYA DIHITUNG, tidak disimpan. Versi lama
        | memanggil reconcileMonitoringStatuses() yang mengeluarkan UPDATE per
        | baris: membuka daftar PO — permintaan GET biasa — diam-diam mengubah
        | data, sekali per proyek yang nilainya berbeda, tanpa jejak audit dan
        | makin berat seiring bertambahnya proyek. Penulisan tetap terjadi di
        | tempatnya: saat status milestone benar-benar diubah di Project
        | Monitoring (ProjectMonitoringController::syncMonitoringHeaderStatus).
        */
        $projects->each(function ($p) {
            foreach ($p->monitoringStatusesAsComputed() as $kolom => $nilai) {
                // setAttribute tanpa save(): nilai benar untuk ditampilkan,
                // tidak ada yang tertulis ke basis data.
                $p->setAttribute($kolom, $nilai);
            }
        });

        $summary = [
            'total'         => (int) $statusCounts->sum(),
            'draft'         => (int) ($statusCounts['DRAFT'] ?? 0),
            'submitted'     => (int) ($statusCounts['SUBMITTED'] ?? 0),
            'waiting_po'    => (int) ($statusCounts['WAITING_PO'] ?? 0),
            'active'        => (int) ($statusCounts['ACTIVE'] ?? 0),
            'progress_done' => (int) ($statusCounts['PROGRESS_DONE'] ?? 0),
            'paid'          => (int) ($statusCounts['PAID'] ?? 0),
            'closing'       => (int) ($statusCounts['CLOSING'] ?? 0),
            'closed'        => (int) ($statusCounts['CLOSED'] ?? 0),
        ];

        return view('project.index', compact('projects', 'summary', 'truncated', 'total', 'maxRows'));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL — daftar PO (superadmin & approver, gerbang di route)
    |--------------------------------------------------------------------------
    */

    public function exportExcel(Request $request)
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

        $projects = Project::with(['customer', 'manager', 'budgetPlans', 'poNumbers', 'invoices'])
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->latest()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Purchase Order');

        $headers = [
            'Code', 'Scope of Work', 'Status', 'Customer', 'Manager', 'Financing', 'Progress %',
            'PO Value', 'Contract', 'Budget', 'Variable Cost', 'Received Payment', 'Gross Profit',
            'Overhead', 'Fixed Cost', 'Net Profit', 'Tipe', 'PO Quantity',
        ];
        $cols = range('A', 'R');
        foreach ($headers as $i => $label) {
            $c = $cols[$i];
            $sheet->setCellValue($c . '1', $label);
            $sheet->getStyle($c . '1')->getFont()->setBold(true);
            $sheet->getStyle($c . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($c . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($projects as $p) {
            $variable = $p->variableCostTotal();
            $payment  = $p->paymentTotal();
            $overhead = (int) ($p->overhead ?? 0);
            $fixed    = (int) ($p->fixed_cost ?? 0);
            $gross    = $payment - $variable;
            $net      = $gross - $overhead - $fixed;

            $sheet->setCellValue('A' . $row, $p->code);
            $sheet->setCellValue('B' . $row, $p->name);
            $sheet->setCellValue('C' . $row, $p->status_label);
            $sheet->setCellValue('D' . $row, $p->customer->name ?? '-');
            $sheet->setCellValue('E' . $row, strtoupper($p->pm_internal_name ?: '-'));
            $sheet->setCellValue('F' . $row, $p->financing_label);
            $sheet->setCellValue('G' . $row, $p->implementationProgress());
            $sheet->setCellValue('H' . $row, (int) ($p->value ?? 0));
            $sheet->setCellValue('I' . $row, (int) ($p->contract ?? 0));
            $sheet->setCellValue('J' . $row, $p->budgetPlanTotal());
            $sheet->setCellValue('K' . $row, $variable);
            $sheet->setCellValue('L' . $row, $payment);
            $sheet->setCellValue('M' . $row, $gross);
            $sheet->setCellValue('N' . $row, $overhead);
            $sheet->setCellValue('O' . $row, $fixed);
            $sheet->setCellValue('P' . $row, $net);
            $sheet->setCellValue('Q' . $row, $p->type_label ?? '-');
            $sheet->setCellValue('R' . $row, $p->po_quantity);
            $row++;
        }

        foreach ($cols as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'purchase-order-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

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
        $customers     = Customer::where('status', 'ACTIVE')->orderBy('name')->get();
        $picsInternal  = MasterPic::where('type', 'internal')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $picsCustomer  = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $accessGroups  = \App\Models\AccessGroup::with(['users' => function ($q) {
            $q->whereHas('role', fn ($r) => $r->where('name', 'manager'))->orderBy('name');
        }])->orderBy('name')->get();
        $managers      = $this->managerOptions();

        return view('project.create', compact('customers', 'picsInternal', 'picsCustomer', 'accessGroups', 'managers'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'customer_id'    => 'nullable|exists:customers,id',
            'manager_id'     => 'nullable|exists:users,id',
            'po_quantity'    => 'nullable|integer|min:0',
            'contract'       => 'nullable',
            'overhead'       => 'nullable',
            'fixed_cost'     => 'nullable',
            'term_of_payment' => 'nullable|string|max:255',
            'financing'      => 'nullable|in:' . implode(',', Project::FINANCING_TYPES),
            'type'           => 'nullable|in:' . implode(',', array_keys(Project::TYPES)),
            'notes'          => 'nullable|string',
            'attachments'    => 'nullable|array',
            'attachments.*.title' => 'nullable|string|max:255',
            'attachments.*.file'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'po_numbers'                 => 'nullable|array',
            'po_numbers.*.po_number'     => 'nullable|string|max:255',
            'po_numbers.*.nominal'       => 'nullable',
            'po_numbers.*.release_date'  => 'nullable|date',
            'po_numbers.*.file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'access_group_ids'   => 'nullable|array',
            'access_group_ids.*' => 'exists:access_groups,id',
        ]);

        $code = Project::generateNextCode();

        DB::transaction(function () use ($request, $code) {
            $project = Project::create([
                'code'           => $code,
                'name'           => strtoupper(trim($request->name)),
                'customer_id'    => $request->customer_id,
                'manager_id'     => $request->manager_id,
                'po_quantity'    => $request->filled('po_quantity') ? (int) $request->po_quantity : null,
                'contract'       => $this->normalizeMoney($request->contract),
                'overhead'       => $this->normalizeMoney($request->overhead),
                'fixed_cost'     => $this->normalizeMoney($request->fixed_cost),
                'term_of_payment'     => $request->term_of_payment,
                'financing'      => $request->financing ?? 'NL',
                'type'           => $request->filled('type') ? $request->type : null,
                'notes'          => $request->notes,
                'status'         => 'DRAFT',
                'input_by'       => Auth::id(),
            ]);

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');
            $this->storePoNumbers($project, $request);
            $this->syncAccessGroups($project, $request);
            $project->addLog('DRAFT', 'DRAFT', 'created');
        });

        return redirect('/project')->with('success', 'PO berhasil dibuat dengan kode ' . $code);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON — AJAX detail modal
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $project = Project::with([
            'customer', 'inputBy', 'approvedBy', 'closedBy', 'manager',
            'attachments', 'logs',
            'details.cluster', 'details.stage',
            'pics',
            'invoices' => fn ($q) => $q->with('cashReceipts')->orderBy('invoice_date', 'desc'),
            'poNumbers.attachments',
        ])->findOrFail($id);

        abort_unless($this->canViewProjectJson($project), 403);

        $rp = fn ($v) => number_format((int) $v, 0, ',', '.');

        // Cost Request (Budget Request) yang tertaut ke Budget Plan PO ini —
        // dipakai tab "Cost". Kalau pengajuan sudah ada realisasinya
        // (REALIZED/CLOSED + punya baris realisasi), Variable Cost pakai nilai
        // realisasi & selisihnya jadi Refund. Kalau belum ada realisasi,
        // Variable Cost pakai nilai pengajuan penuh (Refund 0).
        $costRequests = \App\Models\BudgetRequest::whereIn('budget_plan_id', $project->budgetPlans->pluck('id'))
            ->whereIn('status', ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED'])
            ->with(['beneficiary:id,nickname', 'realizations'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($cr) {
                // P-01: refund baru nyata setelah settlement — REALIZED
                // (realisasi terinput, belum disetujui) masih refund 0.
                $cr->refund_calc = $cr->refundAmount();
                return $cr;
            });

        $totalRequest = (int) $costRequests->sum('amount');
        $totalRefund  = (int) $costRequests->sum('refund_calc');
        $variableCost = $totalRequest - $totalRefund;
        $paymentTotal = $project->paymentTotal();
        $overhead     = (int) ($project->overhead ?? 0);
        $fixedCost    = (int) ($project->fixed_cost ?? 0);
        $totalCost    = $variableCost + $overhead + $fixedCost;
        $grossProfit  = $paymentTotal - $variableCost;
        $netProfit    = $grossProfit - $overhead - $fixedCost;

        // Users with access to this project via access groups
        $accessUsers = \App\Models\User::whereHas('accessGroup.details', function($q) use ($project) {
            $q->where('project_id', $project->id);
        })->with('role')->get();

        // Budget plans linked to this project
        $budgetPlans = \App\Models\BudgetPlan::where('project_id', $project->id)
            ->withSum('details', 'amount')
            ->with('details.account')
            ->get();

        return response()->json([
            'id'              => $project->id,
            'code'            => $project->code,
            'name'            => $project->name,
            'po_number'       => $project->po_number,
            'financing'       => $project->financing,
            'financing_label' => $project->financing_label,
            'financing_color' => $project->financing_color,
            'type'            => $project->type,
            'type_label'      => $project->type_label,
            'type_color'      => $project->type_color,
            'value'           => $project->value,
            'value_formatted' => $project->value_formatted,
            'po_quantity'              => $project->po_quantity,
            'po_numbers_count'         => $project->poNumbers->count(),
            'po_adjustment_required'   => $project->poAdjustmentRequired(),
            'contract'            => $project->contract,
            'contract_formatted'  => $project->contract_formatted,
            'overhead'            => $project->overhead,
            'overhead_formatted'  => $project->overhead_formatted,
            'fixed_cost'           => $project->fixed_cost,
            'fixed_cost_formatted' => $project->fixed_cost_formatted,
            'manager'            => $project->manager?->username ?? '-',
            'term_of_payment' => $project->term_of_payment,
            'status'          => $project->status,
            'status_label'    => $project->status_label,
            'status_color'    => $project->status_color,
            'notes'           => $project->notes,
            'closing_notes'   => $project->closing_notes,
            'customer'        => $project->customer->name ?? '-',
            'input_by'        => $project->inputBy->name ?? '-',
            'approved_by'     => $project->approvedBy->name ?? '-',
            'approved_at'     => $project->approved_at?->format('d M Y H:i'),
            'closed_by'       => $project->closedBy->name ?? '-',
            'closed_at'       => $project->closed_at?->format('d M Y H:i'),
            'submitted_at'    => $project->submitted_at?->format('d M Y H:i'),
            'created_at'      => $project->created_at->format('d M Y H:i'),

            'attachments' => $project->attachments->groupBy('type')->map(
                fn($g) => $g->map(fn($a) => [
                    'title'     => $a->title ?? $a->file_name,
                    'file_name' => $a->file_name,
                    'path'      => $a->file_path,
                    'type'      => $a->type,
                    'is_image'  => $a->isImage(),
                    'is_pdf'    => $a->isPdf(),
                ])
            ),

            'details' => $project->details->map(fn($d) => [
                'id'               => $d->id,
                'cluster'          => $d->cluster->name ?? '-',
                'stage'            => $d->stage->name ?? '-',
                'plan_start'       => $d->plan_start?->format('d M Y'),
                'plan_end'         => $d->plan_end?->format('d M Y'),
                'actual_start'     => $d->actual_start?->format('d M Y'),
                'actual_end'       => $d->actual_end?->format('d M Y'),
                'plan_start_raw'   => $d->plan_start?->format('Y-m-d'),
                'plan_end_raw'     => $d->plan_end?->format('Y-m-d'),
                'actual_start_raw' => $d->actual_start?->format('Y-m-d'),
                'actual_end_raw'   => $d->actual_end?->format('Y-m-d'),
                'status'           => $d->status,
                'status_label'     => $d->status_label,
                'status_color'     => $d->status_color,
                'notes'            => $d->notes,
            ]),

            'pics_internal' => $project->pics->where('type', 'internal')->values()->map(fn($p) => [
                'pic'      => $p->pic,
                'position' => $p->position,
                'phone'    => $p->phone,
                'email'    => $p->email,
            ]),

            'pics_customer' => $project->pics->where('type', 'customer')->values()->map(fn($p) => [
                'pic'      => $p->pic,
                'position' => $p->position,
                'phone'    => $p->phone,
                'email'    => $p->email,
            ]),

            'access_groups' => $project->accessGroups()->pluck('name'),

            'access_users' => $accessUsers->map(fn($u) => [
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role->name ?? '-',
                'access_group' => $u->accessGroup->name ?? '-',
            ]),

            'budget_plans' => $budgetPlans->map(fn($bp) => [
                'id'               => $bp->id,
                'code'             => $bp->code,
                'description'      => $bp->description,
                'status'           => $bp->status,
                'status_color'     => $bp->status_color,
                'amount'           => (int) ($bp->amount ?? 0),
                'amount_formatted' => $bp->amount_formatted,
                // Terpakai oleh Cost Request (VERIFIED s/d CLOSED) — untuk kolom Used & Var
                'used'             => (int) $bp->usedAmount(),
                'details' => $bp->details->map(function ($d) use ($bp) {
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
                                    'cr_id'                  => $rd->budgetRequest->id ?? null,
                                    'cr_code'                => $rd->budgetRequest->code ?? '-',
                                    'cr_date_formatted'      => $rd->budgetRequest->created_at?->format('d M Y') ?? '-',
                                    'beneficiary'            => $rd->budgetRequest->beneficiary->nickname ?? '-',
                                    'notes'                  => $rd->notes ?: '-',
                                    'unit_price_formatted'   => number_format((int) $rd->amount, 0, ',', '.'),
                                    'qty'                    => (float) $rd->qty,
                                    'amount'                 => $amount,
                                    'amount_formatted'       => number_format($amount, 0, ',', '.'),
                                ];
                            })
                            ->values();
                    }

                    return [
                        'budget_plan_code'    => $bp->code,
                        'account'             => $d->account->name ?? '-',
                        'deskripsi'           => $d->deskripsi,
                        'amount_formatted'    => number_format($d->amount, 0, ',', '.'),
                        'qty'                 => (float) $d->qty,
                        'unit'                => $d->unit,
                        'total'               => $d->total,
                        'total_formatted'     => number_format($d->total, 0, ',', '.'),
                        'used'                => $used,
                        'used_formatted'      => number_format($used, 0, ',', '.'),
                        'remaining'           => $remaining,
                        'remaining_formatted' => number_format($remaining, 0, ',', '.'),
                        'cr_details'          => $crDetails,
                    ];
                }),
            ]),

            'po_numbers' => $project->poNumbers->map(fn($p) => [
                'id'                  => $p->id,
                'po_number'           => $p->po_number,
                'nominal'             => (int) $p->nominal,
                'nominal_formatted'   => $p->nominal_formatted,
                'release_date'        => $p->release_date?->format('d M Y'),
                'status'              => $p->status,
                'status_label'        => $p->status_label,
                'status_color'        => $p->status_color,
                'no_bast'             => $p->no_bast,
                'tanggal_bast'        => $p->tanggal_bast?->format('d M Y'),
                'tanggal_bast_raw'    => $p->tanggal_bast?->format('Y-m-d'),
                'nilai_bast_formatted' => $p->nilai_bast_formatted,
                'bast_notes'          => $p->bast_notes,
                'attachments'         => $p->attachments->where('type', '!=', 'po_number_bast')->values()->map(fn($a) => [
                    'title'    => $a->title ?? $a->file_name,
                    'path'     => $a->file_path,
                    'is_image' => $a->isImage(),
                    'is_pdf'   => $a->isPdf(),
                ]),
                'bast_attachments'    => $p->attachments->where('type', 'po_number_bast')->values()->map(fn($a) => [
                    'title'    => $a->title ?? $a->file_name,
                    'path'     => $a->file_path,
                    'is_image' => $a->isImage(),
                    'is_pdf'   => $a->isPdf(),
                ]),
            ]),

            'work_progress_status'    => $project->workProgressStatus(),
            'billing_progress_status' => $project->billingProgressStatus(),
            'payment_progress_status' => $project->paymentProgressStatus(),

            'invoices' => $project->invoices->map(function ($inv) use ($project, $rp) {
                $received = (int) $inv->cashReceipts->sum('amount');
                $lastReceipt = $inv->cashReceipts->sortByDesc('received_date')->first();
                $receivedDate = $lastReceipt && $lastReceipt->received_date
                    ? $lastReceipt->received_date->format('d M Y')
                    : null;
                $poNumber = $project->poNumbers->firstWhere('id', $inv->project_po_number_id);
                return [
                    'id'                => $inv->id,
                    'code'              => $inv->code,
                    'po_number_id'      => $inv->project_po_number_id,
                    'po_number'         => $poNumber->po_number ?? null,
                    'payment_term'      => $inv->payment_term_label,
                    'invoice_date'      => $inv->invoice_date?->format('d M Y'),
                    'due_date'          => $inv->due_date?->format('d M Y'),
                    'captured_total'    => (int) $inv->captured_total,
                    'captured_total_formatted' => $inv->captured_total_formatted,
                    'received'          => $received,
                    'received_formatted' => $rp($received),
                    'received_date'     => $receivedDate,
                    'status'            => $inv->status,
                    'display_status'    => $inv->display_status,
                    'is_overdue'        => $inv->display_status === 'OVERDUE',
                ];
            }),

            // Tab "Cost" — ringkasan biaya + daftar Cost Request PO ini.
            'cost_summary' => [
                'total_request'          => $totalRequest,
                'total_request_formatted'=> $rp($totalRequest),
                'total_refund'           => $totalRefund,
                'total_refund_formatted' => $rp($totalRefund),
                'variable_cost'          => $variableCost,
                'variable_cost_formatted'=> $rp($variableCost),
                'overhead'               => $overhead,
                'overhead_formatted'     => $rp($overhead),
                'fixed_cost'             => $fixedCost,
                'fixed_cost_formatted'   => $rp($fixedCost),
                'total_cost'             => $totalCost,
                'total_cost_formatted'   => $rp($totalCost),
                'payment_total'          => $paymentTotal,
                'payment_total_formatted'=> $rp($paymentTotal),
                'gross_profit'           => $grossProfit,
                'gross_profit_formatted' => $rp($grossProfit),
                'net_profit'             => $netProfit,
                'net_profit_formatted'   => $rp($netProfit),
            ],
            'cost_requests' => $costRequests->map(fn ($cr) => [
                'code'         => $cr->code,
                'date'         => $cr->created_at?->format('d M Y'),
                'description'  => $cr->description,
                'beneficiary'  => $cr->beneficiary->nickname ?? '-',
                'request_formatted' => $rp($cr->amount),
                'refund'            => (int) $cr->refund_calc,
                'refund_formatted'  => $rp($cr->refund_calc),
                'status'       => $cr->status,
                'status_label' => $cr->status_label,
                'status_color' => $cr->status_color,
            ]),

            'logs' => $project->logs->map(fn($l) => [
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from,
                'status_to'    => $l->status_to,
                'actor'        => $l->user_name ?? '-',
                'user_id'      => $l->user_id,
                'role'         => $l->role ?? '-',
                'notes'        => $l->notes,
                'date'         => $l->created_at->format('d M Y H:i'),
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $project   = Project::with(['attachments', 'pics', 'poNumbers.attachments'])->findOrFail($id);
        $user      = Auth::user();
        $role      = $user->role->name ?? '';

        // Saat WAITING_PO, semua role (superadmin/finance/head) diarahkan ke halaman
        // terpisah yang cuma berisi Detail No. PO — bukan form edit lengkap lagi
        // (lihat editPoNumbers()).
        if ($project->status === 'WAITING_PO' && in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project/' . $project->id . '/edit-po-numbers');
        }

        if ($role === 'head' && !$project->isEditableByHead()) {
            return redirect('/project')
                ->with('error', 'PO hanya dapat diedit oleh Head saat berstatus DRAFT.');
        }

        // Finance bisa edit DRAFT, SUBMITTED, WAITING_PO
        if (in_array($role, ['finance', 'finance_manager']) && !$project->isEditableByFinance()) {
            return redirect('/project')
                ->with('error', 'PO tidak dapat diedit pada status ini.');
        }

        // ACTIVE & CLOSED tidak bisa diedit siapapun
        if (in_array($project->status, ['ACTIVE', 'CLOSED']) && !in_array($role, ['superadmin'])) {
            return redirect('/project')
                ->with('error', 'PO berstatus ACTIVE atau CLOSED tidak dapat diedit.');
        }

        $customers    = Customer::where('status', 'ACTIVE')->orderBy('name')->get();
        $clusters     = \App\Models\Cluster::orderBy('name')->get();
        $stages       = \App\Models\Stage::orderBy('name')->get();
        $picsInternal = MasterPic::where('type', 'internal')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $picsCustomer = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $accessGroups = \App\Models\AccessGroup::with(['users' => function ($q) {
            $q->whereHas('role', fn ($r) => $r->where('name', 'manager'))->orderBy('name');
        }])->orderBy('name')->get();
        $selectedAccessGroupIds = $project->accessGroupLinks()->pluck('access_group_id')->all();
        $managers = $this->managerOptions();

        return view('project.edit', compact(
            'project', 'customers', 'clusters', 'stages', 'picsInternal', 'picsCustomer',
            'accessGroups', 'selectedAccessGroupIds', 'managers'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $user    = Auth::user();
        $role    = $user->role->name ?? '';

        // Cek akses edit
        if (in_array($role, ['head']) && !$project->isEditableByHead()) {
            return redirect('/project')->with('error', 'Tidak dapat mengedit PO pada status ini.');
        }

        $request->validate([
            'name'                   => 'required|string|max:255',
            'customer_id'            => 'nullable|exists:customers,id',
            'manager_id'             => 'nullable|exists:users,id',
            'po_quantity'            => 'nullable|integer|min:0',
            'contract'               => 'nullable',
            'overhead'               => 'nullable',
            'fixed_cost'             => 'nullable',
            'term_of_payment'         => 'nullable|string|max:255',
            'financing'              => 'nullable|in:' . implode(',', Project::FINANCING_TYPES),
            'type'                   => 'nullable|in:' . implode(',', array_keys(Project::TYPES)),
            'po_number'              => 'nullable|string|max:255',
            'notes'                  => 'nullable|string',
            'attachments'            => 'nullable|array',
            'attachments.*.title'    => 'nullable|string|max:255',
            'attachments.*.file'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments'     => 'nullable|array',
            'po_numbers'                 => 'nullable|array',
            'po_numbers.*.po_number'     => 'nullable|string|max:255',
            'po_numbers.*.nominal'       => 'nullable',
            'po_numbers.*.release_date'  => 'nullable|date',
            'po_numbers.*.file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'access_group_ids'   => 'nullable|array',
            'access_group_ids.*' => 'exists:access_groups,id',
        ]);

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'name'           => strtoupper(trim($request->name)),
                'customer_id'    => $request->customer_id,
                'manager_id'     => $request->manager_id,
                'po_quantity'    => $request->filled('po_quantity') ? (int) $request->po_quantity : null,
                'contract'       => $this->normalizeMoney($request->contract),
                'overhead'       => $this->normalizeMoney($request->overhead),
                'fixed_cost'     => $this->normalizeMoney($request->fixed_cost),
                'term_of_payment'     => $request->term_of_payment,
                'financing'      => $request->filled('financing') ? $request->financing : $project->financing,
                'type'           => $request->filled('type') ? $request->type : null,
                'po_number'      => $request->po_number,
                'notes'          => $request->notes,
            ]);

            $this->syncAccessGroups($project, $request);

            // Hapus attachment
            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = ProjectAttachment::where('project_id', $project->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('secure')->delete($att->file_path);
                    $att->delete();
                }
            }

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');
            $this->storePoNumbers($project, $request);
        });

        return redirect('/project')->with('success', 'PO berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT PO NUMBERS — halaman terpisah untuk Head saat WAITING_PO.
    | Cuma berisi Detail No. PO (tambah baris baru), field lain PO ini
    | sama sekali tidak bisa disentuh dari halaman ini.
    |--------------------------------------------------------------------------
    */

    public function editPoNumbers($id)
    {
        $project = Project::with(['poNumbers.attachments'])->findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head']) || $project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Halaman ini hanya untuk Superadmin/Finance/Head pada PO berstatus Waiting PO.');
        }

        return view('project.edit-po-numbers', compact('project'));
    }

    public function updatePoNumbers(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head']) || $project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Halaman ini hanya untuk Superadmin/Finance/Head pada PO berstatus Waiting PO.');
        }

        $request->validate([
            'po_numbers'                 => 'nullable|array',
            'po_numbers.*.po_number'     => 'nullable|string|max:255',
            'po_numbers.*.nominal'       => 'nullable',
            'po_numbers.*.release_date'  => 'nullable|date',
            'po_numbers.*.file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $this->storePoNumbers($project, $request);

        return redirect('/project')->with('success', 'No. PO berhasil ditambahkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — No. PO
    |--------------------------------------------------------------------------
    | Sama polanya dengan import Project & Milestone: import TIDAK langsung
    | simpan ke database, cuma parsing & balikin JSON — JS di form yang mengisi
    | ulang tabel "Tambah No. PO Baru". Simpan tetap lewat tombol "Simpan No. PO".
    | Lampiran tidak bisa diimport lewat Excel, tetap diupload manual per baris.
    */

    private const PO_NUMBER_IMPORT_HEADERS = ['No. PO', 'Nominal PO', 'Tanggal Rilis (dd-mm-yyyy)'];

    /**
     * GET /project/po-number/import-template
     */
    public function downloadPoNumberTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('No. PO');

        foreach (self::PO_NUMBER_IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $sheet->setCellValue('A2', 'PO-2026-001');
        $sheet->setCellValue('B2', 10000000);
        $sheet->setCellValue('C2', now()->format('d-m-Y'));

        foreach (['A' => 26, 'B' => 20, 'C' => 26] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'template-no-po-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /project/po-number/import-preview
     */
    public function importPoNumberPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('No. PO') ?? $spreadsheet->getActiveSheet();
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
            if ($i === 0) continue; // header

            [$poNumber, $nominal, $releaseDate] = array_pad($row, 3, null);

            if (blank($poNumber) && blank($nominal) && blank($releaseDate)) {
                continue;
            }

            $rowNum = $i + 1;

            if (blank($poNumber)) {
                $errors[] = "Baris {$rowNum}: No. PO wajib diisi.";
            }

            $result[] = [
                'row'          => $rowNum,
                'po_number'    => trim((string) $poNumber),
                'nominal'      => is_numeric($nominal) ? (string) $nominal : preg_replace('/\D/', '', (string) $nominal),
                'release_date' => $this->parseImportDate($releaseDate),
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

    /*
    |--------------------------------------------------------------------------
    | SUBMIT — DRAFT → SUBMITTED
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $project = Project::findOrFail($id);

        if ($project->status !== 'DRAFT') {
            return back()->with('error', 'Hanya PO berstatus DRAFT yang dapat disubmit.');
        }

        $project->update([
            'status'       => 'SUBMITTED',
            'submitted_at' => now(),
        ]);
        $project->addLog('DRAFT', 'SUBMITTED', 'submitted');

        NotifyService::send(
            $this->financeUsers(),
            'Purchase Order menunggu approval',
            Auth::user()->name . ' mengajukan PO "' . $project->name . '" untuk disetujui.',
            route('project.index'),
            'workflow'
        );

        return redirect('/project')->with('success', 'PO berhasil diajukan ke Finance.');
    }

    /** Role Finance — penerima notifikasi workflow Purchase Order. */
    private function financeUsers()
    {
        // Satu sumber daftar peran: App\Services\RoleDirectory.
        return app(RoleDirectory::class)->finance();
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE APPROVE — SUBMITTED → WAITING_PO atau ACTIVE
    | Finance memilih via dropdown
    |--------------------------------------------------------------------------
    */

    public function financeApprove(Request $request, $id)
    {
        $request->validate([
            'approval_type' => 'required|in:WAITING_PO,ACTIVE',
            'notes'         => 'nullable|string|max:500',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'SUBMITTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $newStatus = $request->approval_type;

        if ($newStatus === 'ACTIVE' && $project->poNumbers()->count() === 0) {
            return back()->with('error', 'Tambahkan minimal 1 No. PO dulu sebelum langsung disetujui ke status Active. Pilih Waiting PO kalau No. PO belum ada.');
        }

        $action = $newStatus === 'WAITING_PO' ? 'approved_waiting' : 'approved_active';

        $updateData = [
            'status'      => $newStatus,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ];

        $project->update($updateData);
        $project->addLog('SUBMITTED', $newStatus, $action, $request->notes);

        $msg = $newStatus === 'WAITING_PO'
            ? 'PO disetujui dengan status Waiting PO.'
            : 'PO disetujui dan sekarang ACTIVE.';

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'Purchase Order disetujui',
                'PO "' . $project->name . '" disetujui Finance — status: ' . str_replace('_', ' ', $newStatus) . '.',
                route('project.index'),
                'workflow'
            );
        }

        return redirect('/project')->with('success', $msg);
    }

    /*
    |--------------------------------------------------------------------------
    | WAITING PO → ACTIVE — Finance input no PO
    |--------------------------------------------------------------------------
    */

    public function activateFromWaiting(Request $request, $id)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project')
                ->with('error', 'Hanya Head/Finance yang dapat mengaktifkan PO.');
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'WAITING_PO') {
            return back()->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        if ($project->poNumbers()->count() === 0) {
            return back()->with('error', 'Tambahkan minimal 1 No. PO dulu (lewat Edit) sebelum PO ini bisa di-Active-kan.');
        }

        $project->update(['status' => 'ACTIVE']);
        $project->addLog('WAITING_PO', 'ACTIVE', 'waiting_to_active', $request->notes);

        // Kalau semua No. PO kebetulan sudah BAST/Paid sebelum diaktifkan,
        // langsung lanjutkan ke PROGRESS_DONE/PAID saat itu juga.
        $project->syncStatusFromPoNumbers();

        return redirect('/project')->with('success', 'PO berhasil diaktifkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE FORM — WAITING_PO → ACTIVE (form edit lengkap oleh Finance)
    |--------------------------------------------------------------------------
    */

    public function activateForm($id)
    {
        $project = Project::with(['customer', 'attachments', 'details.cluster', 'details.stage', 'pics', 'poNumbers.attachments'])
            ->findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project')
                ->with('error', 'Hanya Head/Finance yang dapat mengaktifkan PO.');
        }

        if ($project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        return view('project.activate', compact('project'));
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE — simpan perubahan + ubah status WAITING_PO → ACTIVE
    |--------------------------------------------------------------------------
    */

    public function activate(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project')
                ->with('error', 'Hanya Head/Finance yang dapat mengaktifkan PO.');
        }

        if ($project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        if ($project->poNumbers()->count() === 0) {
            return redirect('/project')
                ->with('error', 'Tambahkan minimal 1 No. PO dulu sebelum PO ini bisa di-Active-kan.');
        }

        // Halaman ini murni konfirmasi — tidak ada field apapun (name/customer/
        // value/milestone/PIC/lampiran/No. PO) yang bisa diubah dari sini.
        // No. PO ditambah/diperbaiki lewat halaman Edit sebelum sampai ke sini.
        $request->validate([
            'activation_notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $project) {
            $project->update(['status' => 'ACTIVE']);
            $project->addLog('WAITING_PO', 'ACTIVE', 'waiting_to_active', $request->activation_notes);

            // Kalau semua No. PO kebetulan sudah BAST/Paid sebelum diaktifkan,
            // langsung lanjutkan ke PROGRESS_DONE/PAID saat itu juga.
            $project->syncStatusFromPoNumbers();
        });

        return redirect('/project')
            ->with('success', 'PO berhasil diaktifkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REVISE — SUBMITTED → DRAFT
    |--------------------------------------------------------------------------
    */

    public function financeRevise(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $project = Project::findOrFail($id);

        if ($project->status !== 'SUBMITTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $project->update(['status' => 'DRAFT']);
        $project->addLog('SUBMITTED', 'DRAFT', 'revised', $request->notes);

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'Purchase Order perlu direvisi',
                'PO "' . $project->name . '" dikembalikan Finance untuk direvisi: ' . $request->notes,
                route('project.index'),
                'workflow'
            );
        }

        return redirect('/project')->with('success', 'PO dikembalikan ke Head untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE — ACTIVE → CLOSED (Finance, upload MoM)
    |--------------------------------------------------------------------------
    */

    public function close(Request $request, $id)
    {
        $request->validate([
            'closing_notes'          => 'nullable|string|max:1000',
            'closing_attachments'          => 'required|array|min:1',
            'closing_attachments.*.title'  => 'required|string|max:255',
            'closing_attachments.*.file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'ACTIVE') {
            return back()->with('error', 'Hanya PO berstatus ACTIVE yang dapat ditutup.');
        }

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'        => 'CLOSED',
                'closed_by'     => Auth::id(),
                'closed_at'     => now(),
                'closing_notes' => $request->closing_notes,
            ]);

            $this->storeAttachments($project, $request, 'closing');
            $project->addLog('ACTIVE', 'CLOSED', 'closed', $request->closing_notes);
        });

        return redirect('/project')->with('success', 'PO berhasil ditutup.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CLOSING — PAID → CLOSING (head upload MoM)
    |--------------------------------------------------------------------------
    */

    public function submitClosing(Request $request, $id)
    {
        $request->validate([
            'closing_notes'          => 'nullable|string|max:1000',
            'closing_attachments'          => 'required|array|min:1',
            'closing_attachments.*.title'  => 'required|string|max:255',
            'closing_attachments.*.file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'PAID') {
            return back()->with('error', 'Hanya PO berstatus PAID yang dapat diajukan closing.');
        }

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'        => 'CLOSING',
                'closing_notes' => $request->closing_notes,
            ]);

            $this->storeAttachments($project, $request, 'closing');

            $project->addLog('PAID', 'CLOSING', 'closing_submitted', $request->closing_notes);
        });

        NotifyService::send(
            $this->financeUsers(),
            'MoM Closing menunggu verifikasi',
            Auth::user()->name . ' mengajukan MoM Closing untuk PO "' . $project->name . '".',
            route('project.index'),
            'workflow'
        );

        return redirect('/project')->with('success', 'MoM Closing berhasil diajukan ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY CLOSING — CLOSING → CLOSED (finance approve)
    |--------------------------------------------------------------------------
    */

    public function verifyClosing(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        if ($project->status !== 'CLOSING') {
            return back()->with('error', 'Status tidak valid.');
        }

        $project->update([
            'status'    => 'CLOSED',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);
        $project->addLog('CLOSING', 'CLOSED', 'closing_approved', $request->notes);

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'Purchase Order ditutup',
                'PO "' . $project->name . '" telah diverifikasi Finance dan sekarang CLOSED.',
                route('project.index'),
                'workflow'
            );
        }

        return redirect('/project')->with('success', 'PO berhasil ditutup.');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT CLOSING — CLOSING → PAID (finance tolak MoM)
    |--------------------------------------------------------------------------
    */

    public function rejectClosing(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $project = Project::findOrFail($id);

        if ($project->status !== 'CLOSING') {
            return back()->with('error', 'Status tidak valid.');
        }

        $project->update(['status' => 'PAID']);
        $project->addLog('CLOSING', 'PAID', 'closing_rejected', $request->notes);

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'MoM Closing ditolak',
                'MoM Closing untuk PO "' . $project->name . '" ditolak: ' . $request->notes,
                route('project.index'),
                'workflow'
            );
        }

        return redirect('/project')->with('success', 'MoM Closing ditolak. PO dikembalikan ke status PAID.');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — Project & Milestone
    |--------------------------------------------------------------------------
    | Sama polanya dengan import Detail Budget: import TIDAK langsung simpan ke
    | database, cuma parsing & balikin JSON — JS di form yang mengisi ulang
    | tabel Project & Milestone. Simpan tetap lewat tombol "Simpan" biasa.
    */

    private const DETAIL_IMPORT_HEADERS = ['Project', 'Milestone', 'Plan Start (dd-mm-yyyy)', 'Plan Finish (dd-mm-yyyy)'];

    /**
     * GET /project/import-template
     */
    public function downloadDetailTemplate()
    {
        $clusters = Cluster::orderBy('name')->get(['id', 'name']);
        $stages   = Stage::orderBy('name')->get(['id', 'name']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Project & Milestone');

        foreach (self::DETAIL_IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $sheet->setCellValue('A2', $clusters->first()->name ?? 'NAMA PROJECT');
        $sheet->setCellValue('B2', $stages->first()->name ?? 'NAMA MILESTONE');
        $sheet->setCellValue('C2', now()->format('d-m-Y'));
        $sheet->setCellValue('D2', now()->addDays(7)->format('d-m-Y'));

        foreach (['A' => 32, 'B' => 26, 'C' => 22, 'D' => 22] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Sheet referensi untuk dropdown
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $refSheet->setCellValue('A1', 'Daftar Project Valid');
        $refSheet->setCellValue('B1', 'Daftar Milestone Valid');
        $refSheet->getStyle('A1:B1')->getFont()->setBold(true);

        foreach ($clusters->values() as $i => $c) {
            $refSheet->setCellValue('A' . ($i + 2), $c->name);
        }
        foreach ($stages->values() as $i => $s) {
            $refSheet->setCellValue('B' . ($i + 2), $s->name);
        }
        $refSheet->getColumnDimension('A')->setWidth(32);
        $refSheet->getColumnDimension('B')->setWidth(26);

        $clusterCount = max($clusters->count(), 1);
        $stageCount   = max($stages->count(), 1);

        for ($row = 2; $row <= 200; $row++) {
            $vCluster = $sheet->getCell('A' . $row)->getDataValidation();
            $vCluster->setType(DataValidation::TYPE_LIST);
            $vCluster->setErrorStyle(DataValidation::STYLE_WARNING);
            $vCluster->setAllowBlank(true);
            $vCluster->setShowInputMessage(true);
            $vCluster->setShowErrorMessage(true);
            $vCluster->setShowDropDown(false);
            $vCluster->setPromptTitle('Pilih Project');
            $vCluster->setPrompt('Pilih dari daftar di sheet Referensi.');
            $vCluster->setFormula1("Referensi!\$A\$2:\$A\$" . ($clusterCount + 1));

            $vStage = $sheet->getCell('B' . $row)->getDataValidation();
            $vStage->setType(DataValidation::TYPE_LIST);
            $vStage->setErrorStyle(DataValidation::STYLE_WARNING);
            $vStage->setAllowBlank(true);
            $vStage->setShowInputMessage(true);
            $vStage->setShowErrorMessage(true);
            $vStage->setShowDropDown(false);
            $vStage->setPromptTitle('Pilih Milestone');
            $vStage->setPrompt('Pilih dari daftar di sheet Referensi.');
            $vStage->setFormula1("Referensi!\$B\$2:\$B\$" . ($stageCount + 1));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template-project-milestone-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /project/import-preview
     */
    public function importDetailPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $clusters = Cluster::orderBy('name')->get()->keyBy(fn($c) => strtoupper(trim($c->name)));
        $stages   = Stage::orderBy('name')->get()->keyBy(fn($s) => strtoupper(trim($s->name)));

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Project & Milestone') ?? $spreadsheet->getActiveSheet();
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
            if ($i === 0) continue; // header

            [$clusterName, $stageName, $planStart, $planEnd] = array_pad($row, 4, null);

            if (blank($clusterName) && blank($stageName) && blank($planStart) && blank($planEnd)) {
                continue;
            }

            $rowNum = $i + 1;

            $clusterKey = strtoupper(trim((string) $clusterName));
            $stageKey   = strtoupper(trim((string) $stageName));
            $cluster    = $clusters->get($clusterKey);
            $stage      = $stages->get($stageKey);

            if (!$cluster && filled($clusterName)) {
                $errors[] = "Baris {$rowNum}: Project \"{$clusterName}\" tidak ditemukan.";
            }
            if (!$stage && filled($stageName)) {
                $errors[] = "Baris {$rowNum}: Milestone \"{$stageName}\" tidak ditemukan.";
            }

            $result[] = [
                'row'          => $rowNum,
                'cluster_id'   => $cluster->id ?? null,
                'cluster_name' => $cluster->name ?? $clusterName,
                'stage_id'     => $stage->id ?? null,
                'stage_name'   => $stage->name ?? $stageName,
                'plan_start'   => $this->parseImportDate($planStart),
                'plan_end'     => $this->parseImportDate($planEnd),
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

    /**
     * Parsing tanggal fleksibel dari Excel: bisa berupa Excel date serial (angka)
     * atau string dd-mm-yyyy / yyyy-mm-dd. Balikin format Y-m-d untuk <input type="date">.
     */
    private function parseImportDate($value): ?string
    {
        if (blank($value)) return null;

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $value);
            if ($d) return $d->format('Y-m-d');
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $project = Project::with('attachments')->findOrFail($id);

        if ($project->status !== 'DRAFT') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($project) {
            foreach ($project->attachments as $att) {
                Storage::disk('secure')->delete($att->file_path);
            }
            $project->delete();
        });

        return redirect('/project')->with('success', 'PO berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | PO NUMBER — update status siklus hidup satu baris No. PO
    |--------------------------------------------------------------------------
    */

    public function updatePoNumberStatus(Request $request, $poNumberId)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', \App\Models\ProjectPoNumber::STATUSES),
        ]);

        $poNumber = \App\Models\ProjectPoNumber::findOrFail($poNumberId);
        $poNumber->update(['status' => $request->status]);
        $poNumber->project->syncStatusFromPoNumbers();

        return back()->with('success', 'Status No. PO ' . $poNumber->po_number . ' diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT BAST — RELEASED -> PARTIAL -> BAST_SUBMITTED, bisa multi No. PO
    |--------------------------------------------------------------------------
    */

    /**
     * Halaman terpisah untuk submit BAST — bisa banyak No. PO sekaligus dalam
     * 1 kali submit (mis. PO A dapat 2 BAST, PO B dapat 1 BAST bersamaan),
     * supaya tidak perlu buka-modal-submit-tutup berulang per No. PO.
     */
    public function showBastPage($projectId)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'head'])) {
            abort(403, 'Hanya Head/Superadmin yang dapat submit BAST.');
        }

        $project = \App\Models\Project::with(['customer', 'poNumbers' => function ($q) {
            $q->orderBy('po_number');
        }, 'poNumbers.bastAttachments'])->findOrFail($projectId);

        return view('project.submit-bast', compact('project'));
    }

    /**
     * Submit BAST untuk banyak No. PO sekaligus. Baris yang tidak dilampiri
     * file dilewati (tidak divalidasi wajib) — supaya user boleh isi No. PO
     * yang relevan saja dalam 1 form besar.
     */
    public function storeBastBatch(Request $request, $projectId)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'head'])) {
            return back()->with('error', 'Hanya Head/Superadmin yang dapat submit BAST.');
        }

        $project = \App\Models\Project::findOrFail($projectId);

        $rows  = (array) $request->input('po_numbers', []);
        $files = (array) $request->file('po_numbers', []);

        $processed = [];
        $skipped   = [];

        DB::transaction(function () use ($rows, $files, $project, &$processed, &$skipped) {
            foreach ($rows as $poNumberId => $row) {
                $fileList = array_values(array_filter(
                    $files[$poNumberId]['attachments'] ?? [],
                    fn ($file) => $file && $file->isValid()
                ));

                // Baris tanpa lampiran = tidak diisi user, lewati diam-diam.
                if (empty($fileList)) {
                    continue;
                }

                $poNumber = \App\Models\ProjectPoNumber::where('project_id', $project->id)->find($poNumberId);
                $noBast      = trim($row['no_bast'] ?? '');
                $tanggalBast = $row['tanggal_bast'] ?? null;
                $nominalBast = $this->normalizeMoney($row['nominal_bast'] ?? null);

                if (!$poNumber || in_array($poNumber->status, ['INVOICED', 'PAID']) || !$noBast || !$tanggalBast || $nominalBast <= 0) {
                    $skipped[] = $poNumber->po_number ?? ('#' . $poNumberId);
                    continue;
                }

                $this->applyBastSubmission($poNumber, $noBast, $tanggalBast, $nominalBast, $fileList, !empty($row['mark_complete']));
                $processed[] = $poNumber->po_number;
            }
        });

        if (empty($processed)) {
            return back()->with('error', 'Tidak ada No. PO yang disubmit — pastikan minimal 1 No. PO diisi No. BAST, Tanggal BAST, Nominal BAST, dan lampiran.');
        }

        $msg = count($processed) . ' No. PO berhasil disubmit BAST-nya: ' . implode(', ', $processed) . '.';
        if ($skipped) {
            $msg .= ' Dilewati (tidak valid/sudah Invoiced-Paid): ' . implode(', ', $skipped) . '.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Terapkan 1 submission BAST ke 1 No. PO: update no_bast/tanggal_bast,
     * simpan lampiran, dan set status PARTIAL (atau BAST_SUBMITTED kalau
     * user sekalian menandai lengkap). Dipakai oleh storeBastBatch().
     */
    private function applyBastSubmission(\App\Models\ProjectPoNumber $poNumber, string $noBast, string $tanggalBast, int $nominalBast, array $files, bool $markComplete): void
    {
        $poNumber->update([
            'status'            => $markComplete ? 'BAST_SUBMITTED' : 'PARTIAL',
            'no_bast'           => $noBast,
            'tanggal_bast'      => $tanggalBast,
            // Akumulasi — 1 No. PO Partial bisa disubmit BAST-nya beberapa kali,
            // nilai_bast merepresentasikan total nilai BAST yang sudah masuk sejauh ini.
            'nilai_bast'        => (int) $poNumber->nilai_bast + $nominalBast,
            'bast_submitted_by' => Auth::id(),
            'bast_submitted_at' => now(),
        ]);

        $attCount = count($files);
        foreach ($files as $i => $file) {
            $title = $attCount > 1 ? $noBast . ' - ' . ($i + 1) : $noBast;
            $path  = $file->store('project_attachments', 'secure');

            $poNumber->attachments()->create([
                'project_id' => $poNumber->project_id,
                'type'       => 'po_number_bast',
                'title'      => strtoupper($title),
                'file_path'  => $path,
                'file_name'  => basename($path),
                'file_type'  => $file->getClientMimeType(),
            ]);
        }

        $note = 'BAST No. PO ' . $poNumber->po_number . ' disubmit' . ($markComplete ? ' dan ditandai lengkap.' : ' (partial).');
        $poNumber->project->addLog('ACTIVE', 'ACTIVE', 'bast_submitted', $note);
        $poNumber->project->syncStatusFromPoNumbers();
    }

    /**
     * Tandai BAST sebuah No. PO sebagai lengkap (PARTIAL -> BAST_SUBMITTED).
     * Tidak butuh file baru — cuma konfirmasi manual dari user bahwa semua
     * dokumen BAST (1-4 dokumen) untuk No. PO ini sudah selesai di-upload.
     */
    public function completePoNumberBast($poNumberId)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'head'])) {
            return back()->with('error', 'Hanya Head/Superadmin yang dapat menandai BAST lengkap.');
        }

        $poNumber = \App\Models\ProjectPoNumber::with('project')->findOrFail($poNumberId);

        if ($poNumber->status !== 'PARTIAL') {
            return back()->with('error', 'Hanya No. PO berstatus Partial yang bisa ditandai lengkap.');
        }

        $poNumber->update(['status' => 'BAST_SUBMITTED']);
        $poNumber->project->addLog('ACTIVE', 'ACTIVE', 'bast_completed', 'BAST No. PO ' . $poNumber->po_number . ' ditandai lengkap.');
        $poNumber->project->syncStatusFromPoNumbers();

        return back()->with('success', 'No. PO ' . $poNumber->po_number . ' ditandai BAST lengkap.');
    }

    public function destroyPoNumber($poNumberId)
    {
        $poNumber = \App\Models\ProjectPoNumber::with('attachments')->findOrFail($poNumberId);

        if ($poNumber->status !== 'RELEASED') {
            return back()->with('error', 'No. PO yang sudah berjalan (bukan RELEASED) tidak dapat dihapus.');
        }

        foreach ($poNumber->attachments as $att) {
            Storage::disk('secure')->delete($att->file_path);
        }
        $poNumber->delete();

        return back()->with('success', 'No. PO berhasil dihapus.');
    }
}