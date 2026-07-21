<?php

namespace App\Http\Controllers;

use App\Models\BudgetRequest;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Auth;
use Exception;

class BudgetRequestApprovalController extends Controller
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Cegah bypass via URL: user hanya boleh mengakses request dalam scope project-nya.
     */
    private function authorizeScope(BudgetRequest $budgetRequest): void
    {
        abort_unless(
            $budgetRequest->isAccessibleBy(Auth::user()),
            403,
            'Anda tidak memiliki akses ke budget request ini.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAGE RENDERERS (GET) — tampilkan halaman aksi
    |--------------------------------------------------------------------------
    */

    private function loadDetail(BudgetRequest $budgetRequest): BudgetRequest
    {
        return $budgetRequest->load([
            'applicant', 'budgetPlan', 'cluster', 'beneficiary', 'type',
            'details', 'attachments', 'statusLogs',
        ]);
    }

    public function reviewPage(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        if (!in_array($budgetRequest->status, ['PENDING_APPROVER', 'PENDING_FINANCE'])) {
            return redirect()->route('budget-request.index')
                ->with('error', 'Request ini tidak berada pada tahap yang bisa di-review.');
        }

        return view('budgetrequest.review', ['budgetRequest' => $this->loadDetail($budgetRequest)]);
    }

    public function payPage(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        if (!$budgetRequest->canPay()) {
            return redirect()->route('budget-request.index')
                ->with('error', 'Request harus berstatus APPROVED untuk dibayar.');
        }

        return view('budgetrequest.pay', ['budgetRequest' => $this->loadDetail($budgetRequest)]);
    }

    public function realizePage(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        if (!$budgetRequest->canRealize()) {
            return redirect()->route('budget-request.index')
                ->with('error', 'Request harus berstatus PAID untuk direalisasikan.');
        }

        return view('budgetrequest.realize', ['budgetRequest' => $this->loadDetail($budgetRequest)]);
    }

    public function closePage(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        if (!$budgetRequest->canClose()) {
            return redirect()->route('budget-request.index')
                ->with('error', 'Request harus berstatus REALIZED untuk ditutup.');
        }

        return view('budgetrequest.close', ['budgetRequest' => $this->loadDetail($budgetRequest)]);
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW ACTIONS (POST) — redirect + flash message
    |--------------------------------------------------------------------------
    */

    public function submit(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            $this->workflowService->submitRequest($budgetRequest, Auth::user(), request('notes'));
            return redirect()->route('budget-request.index')
                ->with('success', 'Request berhasil disubmit untuk approval.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function approve(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            $this->workflowService->approveRequest($budgetRequest, Auth::user(), request('notes'));
            return redirect()->route('budget-request.index')
                ->with('success', 'Request di-approve dan diteruskan ke Finance.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function financeApprove(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            $this->workflowService->financeApproveRequest($budgetRequest, Auth::user(), request('notes'));
            return redirect()->route('budget-request.index')
                ->with('success', 'Request di-approve oleh Finance.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            // View review mengirim alasan via field 'notes'; fallback ke 'rejection_reason'.
            $reason = (string) (request('notes') ?: request('rejection_reason'));
            $this->workflowService->rejectRequest($budgetRequest, Auth::user(), $reason);
            return redirect()->route('budget-request.index')
                ->with('success', 'Request telah ditolak.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function pay(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            $this->workflowService->markAsPaid($budgetRequest, Auth::user(), request('notes'), request()->file('receipt'));
            return redirect()->route('budget-request.index')
                ->with('success', 'Request ditandai sebagai PAID.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function realize(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            $this->workflowService->submitRealization($budgetRequest, Auth::user(), request('notes'), request()->file('realization'));
            return redirect()->route('budget-request.index')
                ->with('success', 'Realisasi berhasil disubmit.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function close(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        try {
            $this->workflowService->closeRequest($budgetRequest, Auth::user(), request('notes'));
            return redirect()->route('budget-request.index')
                ->with('success', 'Request berhasil ditutup (CLOSED).');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | READ-ONLY (JSON) — dipakai untuk fetch detail / audit trail
    |--------------------------------------------------------------------------
    */

    public function show(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        return response()->json(['success' => true, 'data' => $this->loadDetail($budgetRequest)]);
    }

    public function getStatusLogs(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        $logs = $budgetRequest->statusLogs()->with('actor')->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function getAttachments(BudgetRequest $budgetRequest)
    {
        $this->authorizeScope($budgetRequest);

        $attachments = $budgetRequest->attachments()->with('uploader')->orderBy('type')->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $attachments]);
    }

    public function getAttachmentsByType(BudgetRequest $budgetRequest, string $type)
    {
        $this->authorizeScope($budgetRequest);

        return response()->json(['success' => true, 'type' => $type, 'data' => $budgetRequest->getAttachmentsByType($type)]);
    }
}