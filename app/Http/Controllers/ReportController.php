<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\SourceFund;
use App\Models\Invoice;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\RequestDetail;
use App\Models\CashReceipt;
use App\Models\DailyTransaction;

class ReportController extends Controller
{
    /** Status CR yang dianggap sudah membebani anggaran (selaras FinanceSummaryController). */
    /** Arti tiap kelompok status ada di App\Support\BudgetConsumption. */
    private const USED_STATUSES = \App\Support\BudgetConsumption::REPORTED;

    public function index(Request $request)
    {
        // Diurutkan ascending berdasarkan Nama Customer lalu Kode, supaya
        // urutan di dropdown "Customer - Kode - Scope of Work" logis/mudah dicari.
        $projects = Project::with('customer:id,name')
            ->get(['id', 'code', 'name', 'customer_id'])
            ->sortBy(fn ($p) => strtolower(($p->customer->name ?? '') . '-' . $p->code))
            ->values();

        $sourceFunds = SourceFund::with('bank')
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        $reportType = $request->query('report_type');
        $result = null;

        if ($reportType === 'po' && $request->filled('po_id')) {
            // PO Statement selalu menampilkan SELURUH data yang terhubung ke PO
            // tersebut, tidak dibatasi periode — periode hanya relevan untuk
            // laporan Bank/Account Statement.
            $project = Project::with(['customer', 'manager'])->find($request->query('po_id'));
            if ($project) {
                $result = $this->buildPoStatement($project);
            }
        } elseif ($reportType === 'account' && $request->filled('source_fund_id')) {
            [$from, $to] = $this->resolvePeriod($request);
            $fund = SourceFund::with('bank')->find($request->query('source_fund_id'));
            if ($fund) {
                $result = $this->buildAccountStatement($fund, $from, $to);
                $result['period_label'] = $from->format('d M Y') . ' – ' . $to->format('d M Y');
            }
        }

        return view('reports.index', compact('projects', 'sourceFunds', 'result', 'reportType'));
    }

    private function resolvePeriod(Request $request): array
    {
        $period = $request->query('period', 'this_month');

        if ($period === 'last_month') {
            $from = now()->subMonthNoOverflow()->startOfMonth();
            $to   = now()->subMonthNoOverflow()->endOfMonth();
        } elseif ($period === 'custom' && $request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->query('date_from'))->startOfDay();
            $to   = Carbon::parse($request->query('date_to'))->endOfDay();
        } else {
            $from = now()->startOfMonth();
            $to   = now()->endOfMonth();
        }

        return [$from, $to];
    }

    /*
    |--------------------------------------------------------------------------
    | PO STATEMENT
    |--------------------------------------------------------------------------
    | Seluruh data yang terhubung ke satu Project (PO) — tidak dibatasi periode.
    | Profit dihitung reuse method Project model yang sudah dipakai Summary
    | Executive, supaya angkanya konsisten di seluruh app:
    |   - Gross Profit   = Project::grossProfit()   (Nilai PO - Budget)
    |   - Project Profit = Budget - Realisasi CR    (Project::projectMargin() utk %)
    |   - Net Profit     = Nilai PO - (Variable + Fixed + Overhead cost), dari
    |                       kategori Account pada line-item Cost Request.
    */
    private function buildPoStatement(Project $project): array
    {
        $planIds = BudgetPlan::where('project_id', $project->id)->pluck('id');

        $crIds = BudgetRequest::whereIn('budget_plan_id', $planIds)
            ->whereIn('status', self::USED_STATUSES)
            ->pluck('id');

        $details = RequestDetail::whereIn('budget_request_id', $crIds)
            ->with(['account', 'budgetRequest:id,code,description,amount'])
            ->get(['id', 'budget_request_id', 'account_id', 'total']);

        $byCategory = $details->groupBy(fn ($d) => $d->account->category ?? 'Variable Cost');

        $variableDetails = $byCategory->get('Variable Cost', collect());
        $variableCost = (int) $variableDetails->sum('total');

        // Fixed Cost & Overhead TIDAK diambil dari Cost Request (kategori Account) —
        // keduanya butuh perhitungan manual di luar sistem (prorate personel utk Fixed
        // Cost, alokasi pool utk Overhead) yang divalidasi lewat meeting & approval CEO,
        // lalu dicatat lewat modul Cost Register. Lihat Project::fixedCostTotal()/overheadCostTotal().
        $costRegisters = $project->costRegisters()->with('attachments')->latest()->get();
        $fixedCost     = (int) $costRegisters->where('type', 'FIXED_COST')->sum('amount');
        $overheadCost  = (int) $costRegisters->where('type', 'OVERHEAD')->sum('amount');

        $variableBreakdown = $variableDetails
            ->groupBy(fn ($d) => $d->account->name ?? 'Tanpa Account')
            ->map(function ($rows, $accountName) {
                return [
                    'account' => $accountName,
                    'used'    => (int) $rows->sum('total'),
                    'items'   => $rows->map(fn ($d) => [
                        'cr_code'     => $d->budgetRequest->code ?? '-',
                        'description' => $d->budgetRequest->description ?? '-',
                        'amount'      => (int) $d->total,
                    ])->values(),
                ];
            })
            ->sortByDesc('used')
            ->values();

        $value           = (int) ($project->value ?? 0);
        $budget          = $project->budgetPlanTotal();
        $costRealization = $project->costRealizationTotal();

        $grossProfit      = $project->grossProfit();
        $grossMarginPct   = $project->grossMargin();
        $projectProfit    = $budget - $costRealization;
        $projectMarginPct = $project->projectMargin();
        $netProfit        = $value - $variableCost - $fixedCost - $overheadCost;
        $netProfitPct     = $value > 0 ? round($netProfit / $value * 100, 1) : 0.0;

        $pmInternal = $project->pm_internal_name ?: '-';
        $picOthers = $project->picsInternal()
            ->where('position', 'not like', '%project manager%')
            ->get()
            ->map(fn ($p) => $p->pic . ($p->position ? ' (' . $p->position . ')' : ''))
            ->implode(', ') ?: '-';

        $picInternalList = $project->picsInternal()->get()->map(fn ($p) => [
            'name'     => $p->pic,
            'position' => $p->position,
            'phone'    => $p->phone,
            'email'    => $p->email,
        ]);
        $picCustomerList = $project->picsCustomer()->get()->map(fn ($p) => [
            'name'     => $p->pic,
            'position' => $p->position,
            'phone'    => $p->phone,
            'email'    => $p->email,
        ]);

        $planStart = ProjectDetail::where('project_id', $project->id)->min('plan_start');
        $planEnd   = ProjectDetail::where('project_id', $project->id)->max('plan_end');
        $durationDays = ($planStart && $planEnd)
            ? Carbon::parse($planStart)->diffInDays(Carbon::parse($planEnd))
            : null;

        $invoices = Invoice::where('po_id', $project->id)
            ->orderBy('invoice_date')
            ->get();

        $milestones = ProjectDetail::where('project_id', $project->id)
            ->with('stage')
            ->orderBy('plan_start')
            ->get()
            ->map(function ($d) {
                $isLate = false;
                $lateDays = 0;
                if ($d->status !== 'DONE' && $d->plan_end && Carbon::parse($d->plan_end)->isPast()) {
                    $isLate = true;
                    $lateDays = Carbon::parse($d->plan_end)->diffInDays(now());
                } elseif ($d->actual_end && $d->plan_end && Carbon::parse($d->actual_end)->gt(Carbon::parse($d->plan_end))) {
                    $isLate = true;
                    $lateDays = Carbon::parse($d->plan_end)->diffInDays(Carbon::parse($d->actual_end));
                }

                return [
                    'stage'        => $d->stage->name ?? '-',
                    'status'       => $d->status,
                    'status_label' => $d->status_label,
                    'status_color' => $d->status_color,
                    'plan_start'   => $d->plan_start,
                    'plan_end'     => $d->plan_end,
                    'actual_start' => $d->actual_start,
                    'actual_end'   => $d->actual_end,
                    'is_late'      => $isLate,
                    'late_days'    => $lateDays,
                    'notes'        => $d->notes,
                ];
            });

        $budgetPlans = BudgetPlan::where('project_id', $project->id)
            ->with('details.account')
            ->get()
            ->map(function ($bp) {
                $groups = $bp->details
                    ->groupBy(fn ($d) => $d->account->name ?? 'Tanpa Account')
                    ->map(function ($rows, $accountName) {
                        $groupBudget = (int) $rows->sum('total');
                        $groupUsed   = (int) $rows->sum(fn ($d) => $d->usedAmount());

                        $items = $rows->map(function ($d) {
                            $itemBudget = (int) $d->total;
                            $itemUsed   = (int) $d->usedAmount();

                            return [
                                'description' => $d->deskripsi,
                                'price'       => (int) $d->amount,
                                'qty'         => $d->qty,
                                'unit'        => $d->unit,
                                'budget'      => $itemBudget,
                                'used'        => $itemUsed,
                                'variance'    => (int) $d->remainingAmount(),
                                'is_nob'      => $itemUsed > $itemBudget,
                            ];
                        })->values();

                        $isNobFull    = $groupUsed > $groupBudget;
                        $isNobPartial = !$isNobFull && $items->contains('is_nob', true);

                        return [
                            'account'       => $accountName,
                            'budget'        => $groupBudget,
                            'used'          => $groupUsed,
                            'is_nob_full'    => $isNobFull,
                            'is_nob_partial' => $isNobPartial,
                            'items'         => $items,
                        ];
                    })
                    ->values();

                return [
                    'code'         => $bp->code,
                    'description'  => $bp->description,
                    'status'       => $bp->status,
                    'status_color' => $bp->status_color,
                    'amount'       => (int) $bp->amount,
                    'used'         => (int) $bp->usedAmount(),
                    'variance'     => (int) $bp->remainingAmount(),
                    'groups'       => $groups,
                ];
            });

        $poNumbers = $project->poNumbers()->with(['attachments', 'bastAttachments'])->orderBy('po_number')->get();

        $logs = $project->logs()->latest()->get()->map(fn ($l) => [
            'action_label' => $l->action_label,
            'action_color' => $l->action_color,
            'actor'        => $l->user_name ?? '-',
            'role'         => $l->role ?? '-',
            'notes'        => $l->notes,
            'date'         => $l->created_at->format('d M Y H:i'),
        ]);

        return [
            'type'               => 'po',
            'project'            => $project,
            'value'              => $value,
            'budget'             => $budget,
            'gross_profit'       => $grossProfit,
            'gross_margin_pct'   => $grossMarginPct,
            'project_profit'     => $projectProfit,
            'project_margin_pct' => $projectMarginPct,
            'variable_cost'      => $variableCost,
            'fixed_cost'         => $fixedCost,
            'overhead_cost'      => $overheadCost,
            'net_profit'         => $netProfit,
            'net_profit_pct'     => $netProfitPct,
            'pm_internal'        => $pmInternal,
            'pic_others'         => $picOthers,
            'pic_internal_list'  => $picInternalList,
            'pic_customer_list'  => $picCustomerList,
            'term_of_payment'    => $project->term_of_payment ?: '-',
            'financing_label'    => $project->financing_label ?? '-',
            'plan_start'         => $planStart,
            'plan_end'           => $planEnd,
            'duration_days'      => $durationDays,
            'variable_breakdown' => $variableBreakdown,
            'cost_registers'     => $costRegisters,
            'invoices'           => $invoices,
            'milestones'         => $milestones,
            'budget_plans'       => $budgetPlans,
            'po_numbers'         => $poNumbers,
            'logs'               => $logs,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BANK / ACCOUNT STATEMENT
    |--------------------------------------------------------------------------
    | Mutasi kas masuk (Cash Receipt) & keluar (Daily Transaction PAID — Cash
    | Out & Top Up) untuk satu Source Fund, di-scope ke rentang periode.
    */
    private function buildAccountStatement(SourceFund $fund, Carbon $from, Carbon $to): array
    {
        $cashReceipts = CashReceipt::where('source_fund_id', $fund->id)
            ->whereBetween('received_date', [$from, $to])
            ->with('invoice.po.customer')
            ->orderBy('received_date')
            ->get();

        $cashOutBatches = DailyTransaction::where('source_fund_id', $fund->id)
            ->where('type', 'CASH_OUT')
            ->where('status', 'PAID')
            ->whereBetween('paid_at', [$from, $to])
            ->with([
                'includedItems.budgetRequest.budgetPlan.project.customer',
                'includedItems.budgetRequest.budgetPlan.costCenter',
            ])
            ->orderBy('paid_at')
            ->get();

        $topUpOut = DailyTransaction::where('source_fund_id', $fund->id)
            ->where('type', 'TOP_UP')
            ->where('status', 'PAID')
            ->whereBetween('paid_at', [$from, $to])
            ->with('toSourceFund')
            ->orderBy('paid_at')
            ->get();

        $topUpIn = DailyTransaction::where('to_source_fund_id', $fund->id)
            ->where('type', 'TOP_UP')
            ->where('status', 'PAID')
            ->whereBetween('paid_at', [$from, $to])
            ->with('sourceFund')
            ->orderBy('paid_at')
            ->get();

        // ── FLAT ROWS — satu baris = satu pergerakan uang, dipakai bersama
        // untuk tabel ringkasan Customer/PO dan tabel Isi (mutasi). ──
        $flatRows = collect();

        foreach ($cashReceipts as $cr) {
            $project = $cr->invoice->po ?? null;
            $flatRows->push([
                'date'        => $cr->received_date,
                'source'      => 'Invoice',
                'badge'       => 'success',
                'description' => ($project ? $project->customer->name . ' - ' . $project->code . ' - ' : '')
                    . ($cr->invoice->code ?? '-'),
                'debit'       => 0,
                'credit'      => (int) $cr->amount,
                'customer'    => $project->customer->name ?? null,
                'po'          => $project ? ($project->code . ' — ' . $project->name) : null,
                'batch_code'  => null,
            ]);
        }

        foreach ($cashOutBatches as $batch) {
            foreach ($batch->includedItems as $item) {
                $br = $item->budgetRequest;
                if (!$br) continue;

                $plan    = $br->budgetPlan;
                $project = $plan?->project;
                $customerName = $project?->customer?->name
                    ?? ($plan?->costCenter?->name ? 'Overhead: ' . $plan->costCenter->name : null);

                $flatRows->push([
                    'date'        => $batch->paid_at,
                    'source'      => 'Cost Request',
                    'badge'       => 'danger',
                    'description' => ($customerName ? $customerName . ' - ' : '') . $br->code . ' ' . $br->description,
                    'debit'       => (int) $br->amount,
                    'credit'      => 0,
                    'customer'    => $customerName,
                    'po'          => $project ? ($project->code . ' — ' . $project->name) : null,
                    'batch_code'  => $batch->code,
                ]);
            }

            if ((int) $batch->charge > 0) {
                $flatRows->push([
                    'date'        => $batch->paid_at,
                    'source'      => 'Charge',
                    'badge'       => 'secondary',
                    'description' => 'Biaya admin bank — batch ' . $batch->code,
                    'debit'       => (int) $batch->charge,
                    'credit'      => 0,
                    'customer'    => null,
                    'po'          => null,
                    'batch_code'  => $batch->code,
                ]);
            }
        }

        foreach ($topUpOut as $t) {
            $flatRows->push([
                'date'        => $t->paid_at,
                'source'      => 'Top Up',
                'badge'       => 'info',
                'description' => 'Transfer ke ' . ($t->toSourceFund->name ?? '-') . ($t->description ? ' — ' . $t->description : ''),
                'debit'       => (int) $t->captured_amount,
                'credit'      => 0,
                'customer'    => 'Top Up / Internal Transfer',
                'po'          => 'Ke ' . ($t->toSourceFund->name ?? '-'),
                'batch_code'  => $t->code,
            ]);

            if ((int) $t->charge > 0) {
                $flatRows->push([
                    'date'        => $t->paid_at,
                    'source'      => 'Charge',
                    'badge'       => 'secondary',
                    'description' => 'Biaya admin bank — Top Up ' . $t->code,
                    'debit'       => (int) $t->charge,
                    'credit'      => 0,
                    'customer'    => null,
                    'po'          => null,
                    'batch_code'  => $t->code,
                ]);
            }
        }

        foreach ($topUpIn as $t) {
            $flatRows->push([
                'date'        => $t->paid_at,
                'source'      => 'Top Up',
                'badge'       => 'info',
                'description' => 'Transfer dari ' . ($t->sourceFund->name ?? '-') . ($t->description ? ' — ' . $t->description : ''),
                'debit'       => 0,
                'credit'      => (int) $t->captured_amount,
                'customer'    => 'Top Up / Internal Transfer',
                'po'          => 'Dari ' . ($t->sourceFund->name ?? '-'),
                'batch_code'  => $t->code,
            ]);
        }

        $flatRows = $flatRows->sortBy('date')->values();

        // ── RINGKASAN Customer / PO (baris tanpa customer, mis. Charge, tidak masuk) ──
        $customerSummary = $flatRows
            ->filter(fn ($r) => $r['customer'])
            ->groupBy('customer')
            ->map(function ($rowsForCustomer, $customerName) {
                $byPo = $rowsForCustomer->groupBy(fn ($r) => $r['po'] ?? '-')
                    ->map(function ($rowsForPo, $poLabel) {
                        return [
                            'po'   => $poLabel,
                            'in'   => (int) $rowsForPo->sum('credit'),
                            'out'  => (int) $rowsForPo->sum('debit'),
                        ];
                    })
                    ->values();

                return [
                    'customer' => $customerName,
                    'pos'      => $byPo,
                    'in'       => (int) $rowsForCustomer->sum('credit'),
                    'out'      => (int) $rowsForCustomer->sum('debit'),
                ];
            })
            ->sortByDesc(fn ($c) => $c['in'] + $c['out'])
            ->values();

        // ── ISI — mutasi, dikelompokkan per Kode Batch (Treasury) kalau ada,
        // baris tanpa batch (Cash Receipt langsung) ditampilkan berdiri sendiri. ──
        $isiGroups = collect();
        $seenBatches = collect();

        foreach ($flatRows as $row) {
            if ($row['batch_code']) {
                if (!$seenBatches->has($row['batch_code'])) {
                    $seenBatches->put($row['batch_code'], $isiGroups->count());
                    $isiGroups->push([
                        'type'  => 'batch',
                        'date'  => $row['date'],
                        'label' => 'Kode Batch: ' . $row['batch_code'],
                        'rows'  => collect(),
                    ]);
                }
                $idx = $seenBatches->get($row['batch_code']);
                $isiGroups[$idx]['rows']->push($row);
            } else {
                $isiGroups->push(['type' => 'standalone', 'row' => $row]);
            }
        }

        $isiGroups = $isiGroups->map(function ($g) {
            if ($g['type'] === 'batch') {
                $g['debit_total']  = (int) $g['rows']->sum('debit');
                $g['credit_total'] = (int) $g['rows']->sum('credit');
            }
            return $g;
        });

        $cashIn   = (int) $cashReceipts->sum('amount') + (int) $topUpIn->sum('captured_amount');
        $cashOut  = (int) $cashOutBatches->sum('captured_amount') + (int) $topUpOut->sum('captured_amount');
        $charge   = (int) $cashOutBatches->sum('charge') + (int) $topUpOut->sum('charge');
        $available = $cashIn - $cashOut - $charge;

        return [
            'type'             => 'account',
            'fund'             => $fund,
            'cash_in'          => $cashIn,
            'cash_out'         => $cashOut,
            'charge'           => $charge,
            'available'        => $available,
            'customer_summary' => $customerSummary,
            'isi_groups'       => $isiGroups,
        ];
    }
}
