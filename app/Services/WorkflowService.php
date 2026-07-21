<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\RequestStatusLog;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class WorkflowService
{
    /**
     * Submit budget request
     * DRAFT → PENDING_APPROVER
     */
    public function submitRequest(BudgetRequest $request, User $applicant, ?string $notes = null): BudgetRequest
    {
        // Validate status
        if (!$request->canSubmit()) {
            throw new Exception("Request harus dalam status DRAFT untuk bisa di-submit. Status saat ini: {$request->status}");
        }

        DB::beginTransaction();
        try {
            // Generate code jika belum ada
            if (!$request->code) {
                $request->code = BudgetRequest::generateCode();
            }

            // Update status
            $request->status = 'PENDING_APPROVER';
            $request->submitted_at = now();
            $request->save();

            // Create status log
            $this->createStatusLog(
                request: $request,
                statusFrom: 'DRAFT',
                statusTo: 'PENDING_APPROVER',
                actionType: 'submit',
                actedBy: $applicant,
                notes: $notes
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal submit request: {$e->getMessage()}");
        }
    }

    /**
     * Approve budget request by approver
     * PENDING_APPROVER → PENDING_FINANCE
     */
    public function approveRequest(BudgetRequest $request, User $approver, ?string $notes = null): BudgetRequest
    {
        // Validate status
        if (!$request->canApprove()) {
            throw new Exception("Request harus dalam status PENDING_APPROVER untuk bisa di-approve. Status saat ini: {$request->status}");
        }

        // Validate user is approver
        if (!$approver->hasRole('approver')) {
            throw new Exception("Hanya user dengan role 'approver' yang bisa approve request");
        }

        DB::beginTransaction();
        try {
            // Update status
            $request->status = 'PENDING_FINANCE';
            $request->approved_at = now();
            $request->save();

            // Create status log
            $this->createStatusLog(
                request: $request,
                statusFrom: 'PENDING_APPROVER',
                statusTo: 'PENDING_FINANCE',
                actionType: 'approve',
                actedBy: $approver,
                notes: $notes
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal approve request: {$e->getMessage()}");
        }
    }

    /**
     * Reject budget request
     * PENDING_APPROVER/FINANCE → REJECTED
     */
    public function rejectRequest(BudgetRequest $request, User $user, string $rejectionReason): BudgetRequest
    {
        // Validate status
        if (!$request->canReject()) {
            throw new Exception("Request tidak bisa di-reject dari status: {$request->status}");
        }

        // Validate user is approver or finance
        if (!$user->hasRole(['approver', 'finance'])) {
            throw new Exception("Hanya approver atau finance yang bisa reject request");
        }

        if (!$rejectionReason || strlen(trim($rejectionReason)) === 0) {
            throw new Exception("Alasan penolakan harus diisi");
        }

        DB::beginTransaction();
        try {
            $statusFrom = $request->status;

            // Update status
            $request->status = 'REJECTED';
            $request->save();

            // Create status log dengan rejection reason
            $this->createStatusLog(
                request: $request,
                statusFrom: $statusFrom,
                statusTo: 'REJECTED',
                actionType: 'reject',
                actedBy: $user,
                notes: $rejectionReason
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal reject request: {$e->getMessage()}");
        }
    }

    /**
     * Finance approve request
     * PENDING_FINANCE → APPROVED
     */
    public function financeApproveRequest(BudgetRequest $request, User $finance, ?string $notes = null): BudgetRequest
    {
        // Validate status
        if ($request->status !== 'PENDING_FINANCE') {
            throw new Exception("Request harus dalam status PENDING_FINANCE. Status saat ini: {$request->status}");
        }

        // Validate user is finance
        if (!$finance->hasRole('finance')) {
            throw new Exception("Hanya user dengan role 'finance' yang bisa approve request");
        }

        DB::beginTransaction();
        try {
            // Update status
            $request->status = 'APPROVED';
            $request->save();

            // Create status log
            $this->createStatusLog(
                request: $request,
                statusFrom: 'PENDING_FINANCE',
                statusTo: 'APPROVED',
                actionType: 'approve',
                actedBy: $finance,
                notes: $notes
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal approve request di finance: {$e->getMessage()}");
        }
    }

    /**
     * Mark request as paid + upload receipt
     * APPROVED → PAID
     */
    public function markAsPaid(
        BudgetRequest $request,
        User $finance,
        ?string $notes = null,
        $receiptFile = null
    ): BudgetRequest {
        // Validate status
        if (!$request->canPay()) {
            throw new Exception("Request harus dalam status APPROVED untuk di-mark as paid. Status saat ini: {$request->status}");
        }

        // Validate user is finance
        if (!$finance->hasRole('finance')) {
            throw new Exception("Hanya user dengan role 'finance' yang bisa mark as paid");
        }

        DB::beginTransaction();
        try {
            // Update status
            $request->status = 'PAID';
            $request->paid_at = now();
            $request->save();

            // Upload receipt if provided
            if ($receiptFile) {
                $this->uploadAttachment(
                    request: $request,
                    file: $receiptFile,
                    type: 'Receipt',
                    uploadedBy: $finance,
                    notes: $notes
                );
            }

            // Create status log
            $this->createStatusLog(
                request: $request,
                statusFrom: 'APPROVED',
                statusTo: 'PAID',
                actionType: 'pay',
                actedBy: $finance,
                notes: $notes
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal mark as paid: {$e->getMessage()}");
        }
    }

    /**
     * Submit realization + upload realization document
     * PAID → REALIZED
     */
    public function submitRealization(
        BudgetRequest $request,
        User $user,
        ?string $notes = null,
        $realizationFile = null
    ): BudgetRequest {
        // Validate status
        if (!$request->canRealize()) {
            throw new Exception("Request harus dalam status PAID untuk bisa di-realize. Status saat ini: {$request->status}");
        }

        DB::beginTransaction();
        try {
            // Update status
            $request->status = 'REALIZED';
            $request->realized_at = now();
            $request->save();

            // Upload realization document if provided
            if ($realizationFile) {
                $this->uploadAttachment(
                    request: $request,
                    file: $realizationFile,
                    type: 'Realization',
                    uploadedBy: $user,
                    notes: $notes
                );
            }

            // Create status log
            $this->createStatusLog(
                request: $request,
                statusFrom: 'PAID',
                statusTo: 'REALIZED',
                actionType: 'realize',
                actedBy: $user,
                notes: $notes
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal submit realization: {$e->getMessage()}");
        }
    }

    /**
     * Close budget request
     * REALIZED → CLOSED
     */
    public function closeRequest(BudgetRequest $request, User $user, ?string $notes = null): BudgetRequest
    {
        // Validate status
        if (!$request->canClose()) {
            throw new Exception("Request harus dalam status REALIZED untuk bisa di-close. Status saat ini: {$request->status}");
        }

        // Validate user is admin/superadmin
        if (!$user->hasRole(['finance', 'superadmin', 'admin'])) {
            throw new Exception("Hanya finance (atau admin/superadmin) yang bisa close request");
        }

        DB::beginTransaction();
        try {
            // Update status
            $request->status = 'CLOSED';
            $request->closed_at = now();
            $request->save();

            // Create status log
            $this->createStatusLog(
                request: $request,
                statusFrom: 'REALIZED',
                statusTo: 'CLOSED',
                actionType: 'close',
                actedBy: $user,
                notes: $notes
            );

            DB::commit();
            return $request->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Gagal close request: {$e->getMessage()}");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Create status log entry
     */
    private function createStatusLog(
        BudgetRequest $request,
        string $statusFrom,
        string $statusTo,
        string $actionType,
        User $actedBy,
        ?string $notes = null
    ): RequestStatusLog {
        return RequestStatusLog::create([
            'budget_request_id' => $request->id,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'action_type' => $actionType,
            'notes' => $notes,
            'acted_by' => $actedBy->id,
            'actor_name' => $actedBy->name,
            'actor_role' => $actedBy->role->name,
            'request_snapshot' => $request->toArray(),
            'created_at' => now(),
        ]);
    }

    /**
     * Upload attachment file
     */
    private function uploadAttachment(
        BudgetRequest $request,
        $file,
        string $type,
        User $uploadedBy,
        ?string $notes = null
    ): RequestAttachment {
        // Validate file
        if (!$file || !$file->isValid()) {
            throw new Exception("File tidak valid");
        }

        // Limit file size (10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new Exception("Ukuran file tidak boleh lebih dari 10MB");
        }

        // Generate filename
        $originalName = $file->getClientOriginalName();
        $storedName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        // Store file
        $path = $file->storeAs(
            'budget_request/' . $request->id,
            $storedName,
            'public'
        );

        // Create attachment record
        return RequestAttachment::create([
            'budget_request_id' => $request->id,
            'type' => $type,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy->id,
            'notes' => $notes,
        ]);
    }
}