<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\BudgetRequestRealizationAttachment;
use App\Models\RequestDetail;
use App\Models\RequestAttachment;
use App\Models\RequestType;
use App\Models\BudgetPlan;
use App\Models\Cluster;
use App\Models\Beneficiary;
use App\Models\Account;
use App\Models\CostCenter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BudgetRequestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI SUMBER DANA
    |--------------------------------------------------------------------------
    | Cost Request mengikuti sumber dana dari Budget Plan-nya:
    |   - Budget proyek     -> variable cost, di-scope lewat access group
    |   - Budget cost center -> overhead / payroll, di-scope lewat ROLE
    */

    /** Kategori account per jenis sumber dana (harus sama dgn accounts.category) */
    private const ACCOUNT_CATEGORY = [
        'PROJECT'  => 'Variable Cost',
        'OVERHEAD' => 'Overhead',
        'PAYROLL'  => 'Fixed Cost',
    ];

    /** Role yang boleh melihat / mengajukan CR OVERHEAD */
    /*
    | OVERHEAD:
    |   - Role privileged  -> lihat SEMUA overhead
    |   - Role lain        -> lihat overhead HANYA untuk cost center yang ada
    |                          di access group user (ditentukan per-orang, seperti PO)
    */
    private const OVERHEAD_PRIVILEGED_ROLES = ['superadmin', 'approver'];

    /** PAYROLL tetap role-gated (rahasia, tidak lewat access group) */
    private const PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager'];

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
     * Pastikan semua budget_detail_id yang dikirim benar-benar milik
     * Budget Plan yang dipilih (cegah user memilih sub-item dari budget lain).
     */
    private function detailsBelongToPlan(BudgetPlan $bp, Request $request): bool
    {
        $ids = collect((array) $request->input('details', []))
            ->pluck('budget_detail_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) return true;

        $validIds = $bp->details()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id);

        return $ids->diff($validIds)->isEmpty();
    }

    private function syncDetails(BudgetRequest $cr, Request $request): void
    {
        $cr->details()->delete();

        foreach ((array) $request->input('details', []) as $row) {
            $amount = $this->normalizeMoney($row['amount'] ?? null);
            $qty    = (int) ($row['qty'] ?? 1);
            $total  = $amount * $qty;

            $budgetDetailId = $row['budget_detail_id'] ?? null;
            if (!$amount && blank($budgetDetailId)) continue;

            $budgetDetail = $budgetDetailId
                ? \App\Models\BudgetPlanDetail::with('account')->find($budgetDetailId)
                : null;

            // Tandai [NOB][selisih] di notes bila total baris melebihi sisa sub-item budget
            // (safety net server-side — cermin dari logic yang sama di JS create/edit)
            $notes = $row['notes'] ?? null;
            $notesClean = preg_replace('/^\[NOB\]\[[\d.,]+\]\s*/i', '', (string) $notes);
            $remaining = $budgetDetail?->remainingAmount() ?? 0;
            $isRowNob = $budgetDetail && $total > $remaining;
            $notes = $isRowNob
                ? trim('[NOB][' . number_format($total - $remaining, 0, ',', '.') . '] ' . $notesClean)
                : ($notesClean !== '' ? $notesClean : null);

            $cr->details()->create([
                'budget_detail_id' => $budgetDetail->id ?? null,
                'account_id'       => $budgetDetail->account_id ?? null,
                'description'      => $budgetDetail
                    ? trim(($budgetDetail->account->name ?? '-') . ($budgetDetail->deskripsi ? ' — ' . $budgetDetail->deskripsi : ''))
                    : ($row['description'] ?? null),
                'amount'           => $amount,
                'qty'              => $qty,
                'total'            => $total,
                'notes'            => $notes,
            ]);
        }

        $cr->recalculateAmount();
    }

    private function syncRealizations(BudgetRequest $cr, Request $request): void
    {
        $cr->realizations()->delete();

        foreach ((array) $request->input('realizations', []) as $row) {
            $amount   = $this->normalizeMoney($row['amount'] ?? null);
            $detailId = $row['request_detail_id'] ?? null;

            if (!$amount && blank($detailId)) continue;

            $detail = $detailId ? RequestDetail::find($detailId) : null;

            $cr->realizations()->create([
                'request_detail_id' => $detail->id ?? null,
                'description'       => $detail->description ?? null,
                'account_id'        => $detail->account_id ?? null,
                'amount'            => $amount,
                'qty'               => 1,
                'total'             => $amount,
                'notes'             => $row['notes'] ?? null,
            ]);
        }
    }

    private function storeAttachments(BudgetRequest $cr, Request $request): void
    {
        // File upload ada di $request->file(), BUKAN $request->input().
        // Struktur field: attachments[i][file]  ->  file('attachments')[i]['file']
        $groups = $request->file('attachments', []);

        foreach ((array) $groups as $row) {
            // Tiap baris berupa ['file' => UploadedFile] (atau langsung UploadedFile)
            $file = is_array($row) ? ($row['file'] ?? null) : $row;

            if (!$file || !$file->isValid()) continue;

            $path = $file->store('cost_request_attachments', 'public');
            $cr->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    private function storeRealizationAttachments(BudgetRequest $cr, Request $request): void
    {
        foreach ($request->file('realization_attachments', []) as $file) {
            if (!$file || !$file->isValid()) continue;
            $path = $file->store('cost_request_realizations', 'public');
            $cr->realizationAttachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    private function scopeByRole($query): void
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        // Role istimewa melihat semuanya
        if (in_array($role, ['superadmin', 'approver', 'admin'])) return;

        $canOverhead = $this->canSeeOverhead();
        $canPayroll  = $this->canSeePayroll();

        $overheadPrivileged = $this->overheadPrivileged();
        $overheadCcIds      = $this->accessibleOverheadCostCenterIds();

        $projectIds = $user->accessGroup
            ? $user->accessGroup->details->pluck('project_id')->filter()->values()
            : collect();

        /*
        | Tiga jalur visibilitas, digabung OR:
        |   1) CR proyek   -> access group (atau CR milik sendiri bila tanpa group)
        |   2) CR overhead -> privileged lihat semua; lainnya per access group cost center
        |   3) CR payroll  -> berdasarkan role (rahasia)
        | CR yang tidak masuk salah satu jalur TIDAK terlihat.
        */
        $query->where(function ($q) use (
            $user, $projectIds, $canOverhead, $canPayroll, $overheadPrivileged, $overheadCcIds
        ) {

            // 1) CR yang bersumber dari budget PROYEK
            $q->where(function ($qq) use ($user, $projectIds) {
                if ($projectIds->isNotEmpty()) {
                    $qq->whereHas('budgetPlan.project', fn ($p) => $p->whereIn('id', $projectIds));
                } else {
                    // Tanpa access group project: hanya CR miliknya sendiri
                    $qq->where('applicant_id', $user->id)
                       ->whereHas('budgetPlan', fn ($b) => $b->whereNotNull('project_id'));
                }
            });

            // 2) CR OVERHEAD
            if ($overheadPrivileged) {
                // Privileged: semua overhead
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) => $c->where('type', 'OVERHEAD'));
            } elseif ($overheadCcIds->isNotEmpty()) {
                // Lainnya: hanya cost center overhead di access group
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) =>
                    $c->where('type', 'OVERHEAD')->whereIn('id', $overheadCcIds)
                );
            }

            // 3) CR PAYROLL
            if ($canPayroll) {
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HAK AKSES SUMBER DANA INTERNAL
    |--------------------------------------------------------------------------
    */

    /** Role yang lihat SEMUA overhead tanpa access group */
    private function overheadPrivileged(): bool
    {
        return in_array(strtolower(Auth::user()->role->name ?? ''), self::OVERHEAD_PRIVILEGED_ROLES);
    }

    /** Apakah user boleh melihat overhead sama sekali (privileged ATAU punya cost center di access group) */
    private function canSeeOverhead(): bool
    {
        if ($this->overheadPrivileged()) return true;

        return $this->accessibleOverheadCostCenterIds()->isNotEmpty();
    }

    /** ID cost center overhead yang boleh diakses user (kosong = tak ada) */
    private function accessibleOverheadCostCenterIds(): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        return $user->accessGroup ? $user->accessGroup->costCenterIds() : collect();
    }

    private function canSeePayroll(): bool
    {
        return in_array(strtolower(Auth::user()->role->name ?? ''), self::PAYROLL_ROLES);
    }

    /** Budget plan ACTIVE yang boleh dipakai user ini untuk mengajukan CR */
    private function getAvailableBudgetPlans()
    {
        $canPayroll         = $this->canSeePayroll();
        $overheadPrivileged = $this->overheadPrivileged();
        $overheadCcIds      = $this->accessibleOverheadCostCenterIds();

        return BudgetPlan::where('status', 'ACTIVE')
            ->where(function ($q) use ($canPayroll, $overheadPrivileged, $overheadCcIds) {

                // Budget proyek: PO harus WAITING_PO / ACTIVE
                $q->whereHas('project', fn ($p) => $p->whereIn('status', ['WAITING_PO', 'ACTIVE']));

                // Budget overhead: privileged lihat semua; lainnya per access group
                if ($overheadPrivileged) {
                    $q->orWhereHas('costCenter', fn ($c) =>
                        $c->where('type', 'OVERHEAD')->where('status', 'ACTIVE')
                    );
                } elseif ($overheadCcIds->isNotEmpty()) {
                    $q->orWhereHas('costCenter', fn ($c) =>
                        $c->where('type', 'OVERHEAD')->where('status', 'ACTIVE')
                          ->whereIn('id', $overheadCcIds)
                    );
                }

                // Budget payroll: role-gated
                if ($canPayroll) {
                    $q->orWhereHas('costCenter', fn ($c) =>
                        $c->where('type', 'PAYROLL')->where('status', 'ACTIVE')
                    );
                }
            })
            ->with(['project.details.cluster', 'costCenter'])
            ->orderBy('code')
            ->get();
    }

    /** Account sesuai jenis sumber dana */
    private function getAccountsFor(string $sourceType)
    {
        $category = self::ACCOUNT_CATEGORY[strtoupper($sourceType)] ?? null;

        $query = Account::active()->orderBy('name');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->get();
    }

    /*
    |--------------------------------------------------------------------------
    | NOB CHECK — apakah total melebihi sisa budget
    |--------------------------------------------------------------------------
    */

    private function isNOB(BudgetPlan $bp, int $amount, ?int $excludeCrId = null): bool
    {
        $used = BudgetRequest::where('budget_plan_id', $bp->id)
            ->whereIn('status', ['SUBMITTED','APPROVED','VERIFIED','QUEUED','PAID','REALIZED','CLOSED'])
            ->when($excludeCrId, fn($q) => $q->where('id', '!=', $excludeCrId))
            ->sum('amount');

        return ($used + $amount) > ($bp->amount ?? 0);
    }

    private function applyNOBLabel(string $description, bool $isNob): string
    {
        $clean = preg_replace('/^\[NOB\]\s*/i', '', $description);
        return $isNob ? '[NOB] ' . $clean : $clean;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $user = Auth::user();
        $role = $user->role->name ?? '';

        $query = BudgetRequest::with([
            'type', 'cluster', 'budgetPlan.project',
            'applicant',
        ]);

        $this->scopeByRole($query);

        $budgetRequests = $query->latest()->get();

        $summary = [
            'total'     => $budgetRequests->count(),
            'draft'     => $budgetRequests->where('status', 'DRAFT')->count(),
            'submitted' => $budgetRequests->where('status', 'SUBMITTED')->count(),
            'approved'  => $budgetRequests->where('status', 'APPROVED')->count(),
            'verified'  => $budgetRequests->where('status', 'VERIFIED')->count(),
            'paid'      => $budgetRequests->where('status', 'PAID')->count(),
            'realized'  => $budgetRequests->where('status', 'REALIZED')->count(),
            'closed'    => $budgetRequests->where('status', 'CLOSED')->count(),
        ];

        return view('budget-request.index', compact('budgetRequests', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $types = RequestType::orderBy('name')->get();

        // Budget plan ACTIVE: budget proyek (PO WAITING_PO/ACTIVE) + budget
        // cost center sesuai hak role. Tidak dibatasi access group.
        $budgetPlans = $this->getAvailableBudgetPlans();

        $clusters      = Cluster::where('status', 'ACTIVE')->orderBy('name')->get();
        $beneficiaries = Beneficiary::orderBy('complete_name')->get();

        // Account dikelompokkan per jenis sumber; dropdown di-swap oleh JS
        $accountsBySource = [
            'PROJECT'  => $this->getAccountsFor('PROJECT'),
            'OVERHEAD' => $this->getAccountsFor('OVERHEAD'),
            'PAYROLL'  => $this->getAccountsFor('PAYROLL'),
        ];

        $accounts = $accountsBySource['PROJECT'];   // kompatibilitas

        return view('budget-request.create', compact(
            'types', 'budgetPlans', 'clusters', 'beneficiaries', 'accounts', 'accountsBySource'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        // Cluster (Project) hanya relevan untuk CR yang bersumber dari budget PROYEK.
        // Budget cost center (overhead/payroll) tidak punya cluster.
        $sourcePlan = BudgetPlan::with('costCenter')->find($request->budget_plan_id);
        $needCluster = $sourcePlan ? $sourcePlan->isProjectBudget() : true;

        $request->validate([
            'type_id'               => 'required|exists:request_type,id',
            'budget_plan_id'        => 'required|exists:budget_plans,id',
            'cluster_id'            => ($needCluster ? 'required' : 'nullable') . '|exists:clusters,id',
            'beneficiary_id'        => 'required|exists:beneficiaries,id',
            'description'           => 'required|string|max:500',
            'email'                 => 'nullable|string|max:500',
            'notes'                 => 'nullable|string',
            'details'               => 'required|array|min:1',
            'details.*.budget_detail_id' => 'required|exists:budget_plan_details,id',
            'details.*.amount'           => 'required',
            'details.*.qty'              => 'required|integer|min:1',
        ]);

        $budgetPlan = BudgetPlan::with(['project', 'costCenter'])->findOrFail($request->budget_plan_id);

        if (!$this->detailsBelongToPlan($budgetPlan, $request)) {
            return back()->withInput()
                ->with('error', 'Sub-item budget yang dipilih tidak sesuai dengan Budget Plan ini.');
        }

        // GUARD: cegah pengajuan CR pada budget yang tidak berhak diakses user
        if ($budgetPlan->isCostCenterBudget()) {

            if ($budgetPlan->source_type === 'PAYROLL') {
                $allowed = $this->canSeePayroll();
            } else {
                // Overhead: privileged boleh semua; lainnya harus punya cost center
                // spesifik ini di access group-nya.
                $allowed = $this->overheadPrivileged()
                    || $this->accessibleOverheadCostCenterIds()->contains((int) $budgetPlan->cost_center_id);
            }

            if (!$allowed) {
                return back()->withInput()
                    ->with('error', 'Anda tidak berhak mengajukan Cost Request pada budget ini.');
            }
        }

        // Cluster hanya ada pada CR budget proyek
        $cluster = $request->cluster_id ? Cluster::find($request->cluster_id) : null;

        // Hitung total dari details
        $totalAmount = 0;
        foreach ((array) $request->input('details', []) as $row) {
            $amt = $this->normalizeMoney($row['amount'] ?? null);
            $totalAmount += $amt * (int) ($row['qty'] ?? 1);
        }

        $isNob = $this->isNOB($budgetPlan, $totalAmount);
        $description = $this->applyNOBLabel(
            strtoupper(trim($request->description)),
            $isNob
        );

        /*
        | Kode CR: CR-{MM}{YY}{sumber(4)}{cluster(4)}{seq(4)}
        |   - Budget proyek     -> slot sumber = kode PO,          cluster = kode cluster
        |   - Budget cost center -> slot sumber = angka kode CC,   cluster = 0000
        | Keunikan tetap dijamin oleh counter + pengecekan exists di generateCode().
        */
        if ($budgetPlan->isCostCenterBudget()) {
            $sourceCode = preg_replace('/\D/', '', $budgetPlan->costCenter->code ?? '');
            $sourceCode = $sourceCode !== '' ? $sourceCode : '0000';
        } else {
            $sourceCode = $budgetPlan->project->code ?? '0000';
        }

        $code = BudgetRequest::generateCode(
            $sourceCode,
            $cluster->code ?? '0000'
        );

        DB::transaction(function () use ($request, $code, $description) {
            $cr = BudgetRequest::create([
                'code'           => $code,
                'description'    => $description,
                'amount'         => 0,
                'notes'          => $request->notes,
                'email'          => $request->email,
                'applicant_id'   => Auth::id(),
                'beneficiary_id' => $request->beneficiary_id,
                'type_id'        => $request->type_id,
                'cluster_id'     => $request->cluster_id,
                'budget_plan_id' => $request->budget_plan_id,
                'status'         => 'DRAFT',
            ]);

            $this->syncDetails($cr, $request);
            $this->storeAttachments($cr, $request);
            $cr->addLog('DRAFT', 'DRAFT', 'created');
        });

        return redirect('/budget-request')
            ->with('success', 'Cost Request berhasil dibuat: ' . $code . ($isNob ? ' [NOB]' : ''));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $cr = BudgetRequest::with([
            'type', 'cluster', 'budgetPlan.project',
            'applicant', 'beneficiary.bank',
            'details.account',
            'attachments',
            'realizations.account', 'realizations.requestDetail',
            'realizationAttachments',
            'statusLogs',
        ])->findOrFail($id);

        return response()->json([
            'id'               => $cr->id,
            'code'             => $cr->code,
            'description'      => $cr->description,
            'amount'           => $cr->amount,
            'amount_formatted' => $cr->amount_formatted,
            'notes'            => $cr->notes,
            'email'            => $cr->email,
            'status'           => $cr->status,
            'status_color'     => $cr->status_color,
            'is_nob'           => str_starts_with($cr->description, '[NOB]'),
            'type'             => $cr->type->name ?? '-',
            'cluster'          => $cr->cluster->name ?? '-',
            'budget_plan'      => $cr->budgetPlan
                ? '[' . $cr->budgetPlan->code . '] ' . $cr->budgetPlan->description
                : '-',
            'project'          => $cr->budgetPlan->project->name ?? '-',
            'applicant'        => $cr->applicant->name ?? '-',
            'beneficiary'      => $cr->beneficiary->complete_name ?? '-',
            'bank'             => $cr->beneficiary->bank->name ?? '-',
            'account_number'   => $cr->beneficiary->account_number ?? '-',
            'created_at'       => $cr->created_at->format('d M Y H:i'),
            'submitted_at'     => $cr->submitted_at?->format('d M Y H:i'),
            'approved_at'      => $cr->approved_at?->format('d M Y H:i'),
            'paid_at'          => $cr->paid_at?->format('d M Y H:i'),

            'details' => $cr->details->map(fn($d) => [
                'id'               => $d->id,
                'description'      => $d->description,
                'account'          => $d->account->name ?? '-',
                'amount'           => $d->amount,
                'amount_formatted' => 'Rp ' . number_format($d->amount, 0, ',', '.'),
                'qty'              => $d->qty,
                'total'            => $d->total,
                'total_formatted'  => 'Rp ' . number_format($d->total, 0, ',', '.'),
                'notes'            => $d->notes,
            ]),

            'realizations' => $cr->realizations->map(fn($r) => [
                'request_detail_id' => $r->request_detail_id,
                'cr_item'           => $r->requestDetail->description ?? ($r->description ?? '-'),
                'total_cr'          => $r->requestDetail->total ?? 0,
                'total_cr_formatted'=> 'Rp ' . number_format($r->requestDetail->total ?? 0, 0, ',', '.'),
                'actual'            => $r->amount,
                'actual_formatted'  => 'Rp ' . number_format($r->amount, 0, ',', '.'),
                'notes'             => $r->notes,
                // Kolom lama dipertahankan agar kompatibel bila ada kode lain yang masih memakainya
                'description'       => $r->description,
                'account'           => $r->account->name ?? '-',
                'amount'            => $r->amount,
                'amount_formatted'  => 'Rp ' . number_format($r->amount, 0, ',', '.'),
                'qty'               => $r->qty,
                'total'             => $r->total,
                'total_formatted'   => 'Rp ' . number_format($r->total, 0, ',', '.'),
            ]),
            'total_realization'           => $cr->total_realization,
            'total_realization_formatted' => $cr->total_realization_formatted,

            'realization_attachments' => $cr->realizationAttachments->map(fn($a) => [
                'original_name' => $a->original_name,
                'path'          => $a->path,
                'size_formatted'=> $a->size_formatted,
            ]),

            'attachments_count' => $cr->attachments->count(),
            'attachments' => $cr->attachments->map(fn($a) => [
                'original_name'  => $a->original_name,
                'path'           => $a->path,
                'size_formatted' => $a->size_formatted ?? null,
            ])->values(),

            'logs' => $cr->statusLogs->map(fn($l) => [
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from,
                'status_to'    => $l->status_to,
                'actor'        => $l->actor_name ?? 'System',
                'role'         => $l->actor_role,
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
        $cr = BudgetRequest::with(['details.account', 'attachments', 'budgetPlan.costCenter'])
            ->findOrFail($id);

        if (!$cr->isEditable()) {
            return redirect('/budget-request')
                ->with('error', 'Cost Request hanya dapat diedit saat berstatus DRAFT.');
        }

        $types         = RequestType::orderBy('name')->get();
        $budgetPlans   = $this->getAvailableBudgetPlans();
        $clusters      = Cluster::where('status', 'ACTIVE')->orderBy('name')->get();
        $beneficiaries = Beneficiary::orderBy('complete_name')->get();

        $accountsBySource = [
            'PROJECT'  => $this->getAccountsFor('PROJECT'),
            'OVERHEAD' => $this->getAccountsFor('OVERHEAD'),
            'PAYROLL'  => $this->getAccountsFor('PAYROLL'),
        ];

        // Account mengikuti sumber dana budget plan CR ini
        $accounts = $this->getAccountsFor($cr->budgetPlan->source_type ?? 'PROJECT');

        return view('budget-request.edit', compact(
            'cr', 'types', 'budgetPlans', 'clusters', 'beneficiaries', 'accounts', 'accountsBySource'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $cr = BudgetRequest::findOrFail($id);

        if (!$cr->isEditable()) {
            return redirect('/budget-request')
                ->with('error', 'Cost Request hanya dapat diedit saat berstatus DRAFT.');
        }

        // Cluster hanya wajib bila budget plan bersumber dari PROYEK
        $sourcePlan  = BudgetPlan::with('costCenter')->find($request->budget_plan_id);
        $needCluster = $sourcePlan ? $sourcePlan->isProjectBudget() : true;

        $request->validate([
            'type_id'               => 'required|exists:request_type,id',
            'budget_plan_id'        => 'required|exists:budget_plans,id',
            'cluster_id'            => ($needCluster ? 'required' : 'nullable') . '|exists:clusters,id',
            'beneficiary_id'        => 'required|exists:beneficiaries,id',
            'description'           => 'required|string|max:500',
            'email'                 => 'nullable|string|max:500',
            'notes'                 => 'nullable|string',
            'details'               => 'required|array|min:1',
            'details.*.budget_detail_id' => 'required|exists:budget_plan_details,id',
            'details.*.amount'           => 'required',
            'details.*.qty'              => 'required|integer|min:1',
            'delete_attachments'    => 'nullable|array',
        ]);

        $budgetPlan = BudgetPlan::findOrFail($request->budget_plan_id);

        if (!$this->detailsBelongToPlan($budgetPlan, $request)) {
            return back()->withInput()
                ->with('error', 'Sub-item budget yang dipilih tidak sesuai dengan Budget Plan ini.');
        }

        $totalAmount = 0;
        foreach ((array) $request->input('details', []) as $row) {
            $amt = $this->normalizeMoney($row['amount'] ?? null);
            $totalAmount += $amt * (int) ($row['qty'] ?? 1);
        }

        $isNob = $this->isNOB($budgetPlan, $totalAmount, $cr->id);
        $description = $this->applyNOBLabel(
            strtoupper(trim($request->description)),
            $isNob
        );

        DB::transaction(function () use ($request, $cr, $description) {
            $cr->update([
                'description'    => $description,
                'notes'          => $request->notes,
                'email'          => $request->email,
                'beneficiary_id' => $request->beneficiary_id,
                'type_id'        => $request->type_id,
                'cluster_id'     => $request->cluster_id,
                'budget_plan_id' => $request->budget_plan_id,
            ]);

            $this->syncDetails($cr, $request);

            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = RequestAttachment::where('budget_request_id', $cr->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('public')->delete($att->path);
                    $att->delete();
                }
            }

            $this->storeAttachments($cr, $request);
        });

        return redirect('/budget-request')
            ->with('success', 'Cost Request berhasil diupdate.' . ($isNob ? ' [NOB]' : ''));
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT — DRAFT → SUBMITTED
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $cr = BudgetRequest::findOrFail($id);

        if ($cr->status !== 'DRAFT') {
            return back()->with('error', 'Hanya CR berstatus DRAFT yang dapat disubmit.');
        }

        $cr->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
        $cr->addLog('DRAFT', 'SUBMITTED', 'submitted');

        return redirect('/budget-request')
            ->with('success', 'Cost Request berhasil diajukan ke Head.');
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD APPROVE — SUBMITTED → APPROVED
    |--------------------------------------------------------------------------
    */

    public function headApprove(Request $request, $id)
    {
        $cr = BudgetRequest::findOrFail($id);
        if ($cr->status !== 'SUBMITTED') return back()->with('error', 'Status tidak valid.');

        $cr->update(['status' => 'APPROVED']);
        $cr->addLog('SUBMITTED', 'APPROVED', 'head_approved', $request->notes);

        return redirect('/budget-request')
            ->with('success', 'CR disetujui Head dan diteruskan ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD REVISE — SUBMITTED → DRAFT
    |--------------------------------------------------------------------------
    */

    public function headRevise(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);
        $cr = BudgetRequest::findOrFail($id);
        if ($cr->status !== 'SUBMITTED') return back()->with('error', 'Status tidak valid.');

        $cr->update(['status' => 'DRAFT']);
        $cr->addLog('SUBMITTED', 'DRAFT', 'head_revised', $request->notes);

        return redirect('/budget-request')
            ->with('success', 'CR dikembalikan ke Manager untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD REJECT — SUBMITTED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function headReject(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);
        $cr = BudgetRequest::findOrFail($id);
        if ($cr->status !== 'SUBMITTED') return back()->with('error', 'Status tidak valid.');

        $cr->update(['status' => 'REJECTED']);
        $cr->addLog('SUBMITTED', 'REJECTED', 'head_rejected', $request->notes);

        return redirect('/budget-request')->with('success', 'CR ditolak.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE VERIFY — APPROVED → VERIFIED
    |--------------------------------------------------------------------------
    */

    public function financeVerify(Request $request, $id)
    {
        $cr = BudgetRequest::findOrFail($id);
        if ($cr->status !== 'APPROVED') return back()->with('error', 'Status tidak valid.');

        $cr->update(['status' => 'VERIFIED', 'approved_at' => now()]);
        $cr->addLog('APPROVED', 'VERIFIED', 'finance_approved', $request->notes);

        return redirect('/budget-request')
            ->with('success', 'CR diverifikasi Finance dan siap masuk batch.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REVISE — APPROVED → DRAFT
    |--------------------------------------------------------------------------
    */

    public function financeRevise(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);
        $cr = BudgetRequest::findOrFail($id);
        if ($cr->status !== 'APPROVED') return back()->with('error', 'Status tidak valid.');

        $cr->update(['status' => 'DRAFT']);
        $cr->addLog('APPROVED', 'DRAFT', 'finance_revised', $request->notes);

        return redirect('/budget-request')
            ->with('success', 'CR dikembalikan ke Manager untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REJECT — APPROVED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function financeReject(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);
        $cr = BudgetRequest::findOrFail($id);
        if ($cr->status !== 'APPROVED') return back()->with('error', 'Status tidak valid.');

        $cr->update(['status' => 'REJECTED']);
        $cr->addLog('APPROVED', 'REJECTED', 'finance_rejected', $request->notes);

        return redirect('/budget-request')->with('success', 'CR ditolak.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT REALISASI — PAID → REALIZED (manager)
    |--------------------------------------------------------------------------
    */

    public function submitRealization(Request $request, $id)
    {
        $request->validate([
            'realizations'                     => 'required|array|min:1',
            'realizations.*.request_detail_id' => 'required|exists:request_detail,id',
            'realizations.*.amount'            => 'required',
            'realization_attachments'          => 'nullable|array',
            'realization_attachments.*'        => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $cr = BudgetRequest::findOrFail($id);

        if ($cr->status !== 'PAID') {
            return back()->with('error', 'Hanya CR berstatus PAID yang dapat diajukan realisasi.');
        }

        $validDetailIds = $cr->details()->pluck('id')->map(fn ($i) => (int) $i);
        $submittedIds   = collect((array) $request->input('realizations', []))
            ->pluck('request_detail_id')->filter()->map(fn ($i) => (int) $i);

        if ($submittedIds->diff($validDetailIds)->isNotEmpty()) {
            return back()->withInput()
                ->with('error', 'Item realisasi yang dipilih tidak sesuai dengan Cost Request ini.');
        }

        DB::transaction(function () use ($request, $cr) {
            // Delete old realizations & attachments jika ada
            $cr->realizationAttachments()->each(function ($att) {
                Storage::disk('public')->delete($att->path);
                $att->delete();
            });

            $this->syncRealizations($cr, $request);
            $this->storeRealizationAttachments($cr, $request);

            $cr->update([
                'status'      => 'REALIZED',
                'realized_at' => now(),
            ]);

            $cr->addLog('PAID', 'REALIZED', 'realization_submitted',
                'Total realisasi: Rp ' . number_format($cr->fresh()->total_realization, 0, ',', '.')
            );
        });

        return redirect('/budget-request')
            ->with('success', 'Realisasi berhasil diajukan. Status CR menjadi REALIZED.');
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY REALIZATION — REALIZED → CLOSED (finance)
    |--------------------------------------------------------------------------
    */

    public function verifyRealization(Request $request, $id)
    {
        $cr = BudgetRequest::findOrFail($id);

        if ($cr->status !== 'REALIZED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $cr->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $cr->addLog('REALIZED', 'CLOSED', 'realization_approved', $request->notes);

        return redirect('/budget-request')
            ->with('success', 'Realisasi diverifikasi. CR sekarang CLOSED.');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT REALIZATION — REALIZED → PAID (finance)
    |--------------------------------------------------------------------------
    */

    public function rejectRealization(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);
        $cr = BudgetRequest::findOrFail($id);

        if ($cr->status !== 'REALIZED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $cr->update(['status' => 'PAID']);
        $cr->addLog('REALIZED', 'PAID', 'realization_rejected', $request->notes);

        return redirect('/budget-request')
            ->with('success', 'Realisasi ditolak. CR dikembalikan ke status PAID.');
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK NOB — AJAX
    |--------------------------------------------------------------------------
    */

    public function checkNob(Request $request)
    {
        $bp     = BudgetPlan::find($request->budget_plan_id);
        $amount = (int) $request->amount;
        $crId   = $request->cr_id ? (int) $request->cr_id : null;

        if (!$bp) return response()->json(['is_nob' => false, 'remaining' => 0]);

        $isNob     = $this->isNOB($bp, $amount, $crId);
        $remaining = $bp->remainingAmount();

        return response()->json([
            'is_nob'            => $isNob,
            'remaining'         => $remaining,
            'remaining_formatted' => 'Rp ' . number_format($remaining, 0, ',', '.'),
            'budget_amount'     => $bp->amount,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT — daftar Cost Request ke Excel
    |--------------------------------------------------------------------------
    */

    public function export()
    {
        $query = BudgetRequest::with([
            'type', 'cluster', 'budgetPlan.project', 'budgetPlan.costCenter',
            'applicant', 'beneficiary',
        ]);

        $this->scopeByRole($query);

        $budgetRequests = $query->latest()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cost Request');

        $headers = [
            'Kode', 'Tanggal', 'Deskripsi', 'Tipe', 'Sumber Dana', 'Beneficiary',
            'Pemohon', 'Amount', 'Status', 'NOB',
        ];

        foreach ($headers as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($budgetRequests as $cr) {
            $isNob = str_starts_with($cr->description, '[NOB]');
            $desc  = $isNob ? ltrim(preg_replace('/^\[NOB\]\s*/i', '', $cr->description)) : $cr->description;

            $sumber = $cr->budgetPlan?->isProjectBudget()
                ? ($cr->budgetPlan->project->name ?? '-')
                : ($cr->budgetPlan?->costCenter->name ?? '-');

            $sheet->setCellValue('A' . $row, $cr->code);
            $sheet->setCellValue('B' . $row, $cr->created_at?->format('d-m-Y H:i'));
            $sheet->setCellValue('C' . $row, $desc);
            $sheet->setCellValue('D' . $row, $cr->type->name ?? '-');
            $sheet->setCellValue('E' . $row, $sumber);
            $sheet->setCellValue('F' . $row, $cr->beneficiary->complete_name ?? '-');
            $sheet->setCellValue('G' . $row, strtoupper($cr->applicant->username ?? '-'));
            $sheet->setCellValue('H' . $row, (int) $cr->amount);
            $sheet->setCellValue('I' . $row, $cr->status);
            $sheet->setCellValue('J' . $row, $isNob ? 'YA' : '-');
            $row++;
        }

        foreach (['A'=>16,'B'=>16,'C'=>40,'D'=>16,'E'=>28,'F'=>24,'G'=>16,'H'=>16,'I'=>12,'J'=>8] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'cost-request-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — DRAFT only
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $cr = BudgetRequest::with('attachments')->findOrFail($id);

        if ($cr->status !== 'DRAFT') {
            return redirect('/budget-request')
                ->with('error', 'Hanya CR berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($cr) {
            foreach ($cr->attachments as $att) {
                Storage::disk('public')->delete($att->path);
            }
            $cr->delete();
        });

        return redirect('/budget-request')
            ->with('success', 'Cost Request berhasil dihapus.');
    }
}