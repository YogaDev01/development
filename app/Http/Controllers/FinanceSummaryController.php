<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Project;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\RequestDetail;
use App\Models\Account;

class FinanceSummaryController extends Controller
{
    /** Status CR yang dianggap sudah membebani anggaran (selaras BudgetPlan::usedAmount()) */
    private const USED_STATUSES = ['VERIFIED', 'QUEUED', 'PAID', 'REALIZED', 'CLOSED'];

    /** Role yang boleh melihat nilai PO */
    private const PO_VALUE_ROLES = ['superadmin', 'approver', 'finance', 'head'];

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | SCOPE AKSES
        |--------------------------------------------------------------------------
        */
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        // Manager tidak boleh melihat informasi PO sama sekali di Finance Summary.
        $hidePo = $role === 'manager';

        $scopedProjectIds = null;

        if (!in_array($role, ['superadmin', 'finance', 'approver', 'admin'])) {
            $scopedProjectIds = $user->accessGroup
                ? $user->accessGroup->details->pluck('project_id')->all()
                : [];
        }

        /*
        |--------------------------------------------------------------------------
        | PO (Project)
        |--------------------------------------------------------------------------
        */
        $poQuery = Project::with('customer:id,name');
        if (is_array($scopedProjectIds)) {
            $poQuery->whereIn('id', $scopedProjectIds ?: [0]);
        }
        $projects = $poQuery->get(['id', 'name', 'value', 'status', 'customer_id']);

        $po = [
            'total'         => $projects->count(),
            'total_value'   => (int) $projects->sum('value'),
            'draft'         => $projects->where('status', 'DRAFT')->count(),
            'submitted'     => $projects->where('status', 'SUBMITTED')->count(),
            'waiting_po'    => $projects->where('status', 'WAITING_PO')->count(),
            'active'        => $projects->where('status', 'ACTIVE')->count(),
            'progress_done' => $projects->where('status', 'PROGRESS_DONE')->count(),
            'billed'        => $projects->where('status', 'BILLED')->count(),
            'paid'          => $projects->where('status', 'PAID')->count(),
            'closing'       => $projects->where('status', 'CLOSING')->count(),
            'closed'        => $projects->where('status', 'CLOSED')->count(),
        ];

        // PRIVASI: nilai PO di-null-kan di lapisan data bila role tak berhak.
        $canSeePoValue = in_array($role, self::PO_VALUE_ROLES);
        if (!$canSeePoValue) {
            $po['total_value'] = null;
        }
        $po['can_see_value'] = $canSeePoValue;

        /*
        |--------------------------------------------------------------------------
        | BUDGETING (BudgetPlan)
        |--------------------------------------------------------------------------
        */
        $bpQuery = BudgetPlan::query();
        if (is_array($scopedProjectIds)) {
            $bpQuery->whereIn('project_id', $scopedProjectIds ?: [0]);
        }
        $budgetPlans = $bpQuery->get(['id', 'code', 'description', 'amount', 'status']);

        $bp = [
            'total'         => $budgetPlans->count(),
            'total_amount'  => (int) $budgetPlans->sum('amount'),
            'active_amount' => (int) $budgetPlans->where('status', 'ACTIVE')->sum('amount'),
            'draft'         => $budgetPlans->where('status', 'DRAFT')->count(),
            'submitted'     => $budgetPlans->where('status', 'SUBMITTED')->count(),
            'active'        => $budgetPlans->where('status', 'ACTIVE')->count(),
            'closing'       => $budgetPlans->where('status', 'CLOSING')->count(),
            'closed'        => $budgetPlans->where('status', 'CLOSED')->count(),
            'rejected'      => $budgetPlans->where('status', 'REJECTED')->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | COST REQUEST (BudgetRequest)
        |--------------------------------------------------------------------------
        */
        $crQuery = BudgetRequest::with(['applicant:id,username', 'type:id,name']);
        if (is_array($scopedProjectIds)) {
            $ids = $scopedProjectIds ?: [0];
            $crQuery->whereHas('budgetPlan.project', fn ($q) => $q->whereIn('id', $ids));
        }
        $costRequests = $crQuery->get(['id', 'description', 'amount', 'status', 'budget_plan_id', 'applicant_id', 'type_id']);

        $cr = [
            'total'        => $costRequests->count(),
            'total_amount' => (int) $costRequests->sum('amount'),
            'draft'        => $costRequests->where('status', 'DRAFT')->count(),
            'submitted'    => $costRequests->where('status', 'SUBMITTED')->count(),
            'approved'     => $costRequests->where('status', 'APPROVED')->count(),
            'verified'     => $costRequests->where('status', 'VERIFIED')->count(),
            'queued'       => $costRequests->where('status', 'QUEUED')->count(),
            'paid'         => $costRequests->where('status', 'PAID')->count(),
            'realized'     => $costRequests->where('status', 'REALIZED')->count(),
            'closed'       => $costRequests->where('status', 'CLOSED')->count(),
            'rejected'     => $costRequests->where('status', 'REJECTED')->count(),
        ];

        // Total realisasi GLOBAL (semua CR dalam scope akses, TIDAK terikat filter
        // budget plan di panel kanan) — dipakai untuk card KPI "Total Realisasi".
        $allCrIds = $costRequests->pluck('id');
        $globalRealized = $allCrIds->isEmpty()
            ? 0
            : (int) BudgetRequestRealization::whereIn('budget_request_id', $allCrIds)->sum('total');
        $cr['realized_amount'] = $globalRealized;

        /*
        |--------------------------------------------------------------------------
        | SERAPAN ANGGARAN — dengan filter budget plan
        |--------------------------------------------------------------------------
        | Pilihan: 'all' (semua budget ACTIVE) atau satu budget plan ACTIVE.
        */
        $activeBudgetPlans = $budgetPlans
            ->where('status', 'ACTIVE')
            ->sortBy('code')
            ->values();

        $selectedBudgetId = $request->query('budget_plan_id', 'all');

        $selectedPlan = null;
        if ($selectedBudgetId !== 'all') {
            $selectedPlan = $activeBudgetPlans->firstWhere('id', (int) $selectedBudgetId);
            if (!$selectedPlan) {
                $selectedBudgetId = 'all';   // id tak valid -> fallback ke semua
            }
        }

        $targetPlans   = $selectedPlan ? collect([$selectedPlan]) : $activeBudgetPlans;
        $targetPlanIds = $targetPlans->pluck('id');

        $totalBudget = (int) $targetPlans->sum('amount');

        $scopedCRs  = $costRequests->whereIn('budget_plan_id', $targetPlanIds);
        $usedCRs    = $scopedCRs->whereIn('status', self::USED_STATUSES);
        $usedCrIds  = $usedCRs->pluck('id');
        $usedAmount = (int) $usedCRs->sum('amount');

        $realizedAmount = $usedCrIds->isEmpty()
            ? 0
            : (int) BudgetRequestRealization::whereIn('budget_request_id', $usedCrIds)->sum('total');

        $remainingBudget = max(0, $totalBudget - $usedAmount);

        $absorptionPct = $totalBudget > 0 ? round($usedAmount / $totalBudget * 100, 1) : 0;
        $remainingPct  = $totalBudget > 0 ? round($remainingBudget / $totalBudget * 100, 1) : 0;

        /*
        |--------------------------------------------------------------------------
        | RINCIAN TERSERAP PER ACCOUNT (Akomodasi, Transport, dll)
        |--------------------------------------------------------------------------
        | Dikelompokkan di PHP agar tidak bergantung pada nama tabel raw.
        */
        $accountBreakdown = collect();

        if ($usedCrIds->isNotEmpty()) {
            $details = RequestDetail::whereIn('budget_request_id', $usedCrIds)
                ->get(['account_id', 'total']);

            $accountNames = Account::whereIn(
                'id',
                $details->pluck('account_id')->filter()->unique()->values()
            )->pluck('name', 'id');

            $accountBreakdown = $details
                ->groupBy('account_id')
                ->map(function ($rows, $accountId) use ($accountNames) {
                    return [
                        'name'  => $accountNames[$accountId] ?? 'Tanpa Account',
                        'total' => (int) $rows->sum('total'),
                    ];
                })
                ->sortByDesc('total')
                ->values();
        }

        $breakdownTotal = (int) $accountBreakdown->sum('total');

        $realizedOfUsedPct = $usedAmount > 0 ? round(($realizedAmount / $usedAmount) * 100, 1) : 0.0;

        $finance = [
            'total_budget'         => $totalBudget,
            'used'                 => $usedAmount,
            'remaining'            => $remainingBudget,
            'realized'             => $realizedAmount,
            'absorption_pct'       => $absorptionPct,
            'remaining_pct'        => $remainingPct,
            'realized_of_used_pct' => $realizedOfUsedPct,
        ];

        /*
        |--------------------------------------------------------------------------
        | LIST RINGKAS PER STATUS — dipakai modal timeline (tanpa pindah halaman)
        |--------------------------------------------------------------------------
        */
        $poList = $hidePo ? collect() : $projects->map(fn ($p) => [
            'status'    => $p->status,
            'title'     => $p->name,
            'subtitle'  => $p->customer->name ?? '-',
            'value'     => 'Rp ' . number_format($p->value ?? 0, 0, ',', '.'),
            'value_raw' => (int) ($p->value ?? 0),
            'url'       => url('/project'),
        ])->groupBy('status');

        $crList = $costRequests->map(function ($c) {
            $isNob = str_starts_with($c->description ?? '', '[NOB]');
            $desc  = $isNob ? ltrim(preg_replace('/^\[NOB\]\s*/i', '', $c->description)) : $c->description;

            return [
                'status'    => $c->status,
                'title'     => $desc,
                'subtitle'  => strtoupper($c->applicant->username ?? '-') . ' • ' . ($c->type->name ?? '-'),
                'value'     => 'Rp ' . number_format($c->amount ?? 0, 0, ',', '.'),
                'value_raw' => (int) ($c->amount ?? 0),
                'is_nob'    => $isNob,
                'url'       => url('/budget-request'),
            ];
        })->groupBy('status');

        return view('finance-summary.index', compact(
            'po',
            'bp',
            'cr',
            'finance',
            'activeBudgetPlans',
            'selectedBudgetId',
            'accountBreakdown',
            'breakdownTotal',
            'poList',
            'crList',
            'hidePo'
        ));
    }
}