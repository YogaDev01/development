<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\DailyTransactionAttachment;
use App\Models\BudgetRequest;

class DailyTransactionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $transactions = DailyTransaction::with([
            'createdBy',
            'approvedBy',
            'paidBy',
            'includedItems.budgetRequest',
            'sourceFund',
        ])->latest()->get();

        $summary = [
            'total'            => $transactions->count(),
            'pending_approver' => $transactions->where('status', 'PENDING_APPROVER')->count(),
            'approved'         => $transactions->where('status', 'APPROVED')->count(),
            'paid'             => $transactions->where('status', 'PAID')->count(),
            'rejected'         => $transactions->where('status', 'REJECTED')->count(),
        ];

        return view('daily-transaction.index', compact(
            'transactions', 'summary'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE — halaman terpisah untuk pembuatan batch baru
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $availableCRs = BudgetRequest::where('status', 'VERIFIED')
            ->whereDoesntHave('dailyTransactionItems', fn($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'APPROVED'])
                  )
            )
            ->with(['type', 'cluster', 'beneficiary', 'budgetPlan.project'])
            ->latest()->get();

        $sourceFunds = \App\Models\SourceFund::with('bank')
            ->where('status', 'ACTIVE')
            ->orderBy('name')->get();

        return view('daily-transaction.create', compact(
            'availableCRs', 'sourceFunds'
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
            'cr_ids'          => 'required|array|min:1',
            'cr_ids.*'        => 'exists:budget_request,id',
            'description'     => 'nullable|string|max:255',
            'source_fund_id'  => 'nullable|exists:source_funds,id',
            'charge'          => 'nullable',
            'notes'           => 'nullable|string|max:500',
        ]);

        $crIds = $request->input('cr_ids');

        $invalidCRs = BudgetRequest::whereIn('id', $crIds)
            ->where('status', '!=', 'VERIFIED')->pluck('code');

        if ($invalidCRs->isNotEmpty()) {
            return back()->with('error',
                'CR berikut tidak berstatus VERIFIED: ' . $invalidCRs->implode(', ')
            );
        }

        $alreadyBatched = BudgetRequest::whereIn('id', $crIds)
            ->whereHas('dailyTransactionItems', fn($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'APPROVED'])
                  )
            )->pluck('code');

        if ($alreadyBatched->isNotEmpty()) {
            return back()->with('error',
                'CR berikut sudah ada dalam batch aktif: ' . $alreadyBatched->implode(', ')
            );
        }

        $code   = DailyTransaction::generateCode(count($crIds));
        $charge = $this->normalizeMoney($request->charge);

        DB::transaction(function () use ($request, $crIds, $code, $charge) {
            $batch = DailyTransaction::create([
                'code'            => $code,
                'date'            => now()->toDateString(),
                'description'     => $request->description ? strtoupper($request->description) : null,
                'source_fund_id'  => $request->source_fund_id,
                'charge'          => $charge,
                'status'          => 'PENDING_APPROVER',
                'notes'           => $request->notes,
                'created_by'      => Auth::id(),
            ]);

            foreach ($crIds as $crId) {
                $batch->items()->create([
                    'budget_request_id' => $crId,
                    'status'            => 'INCLUDED',
                ]);
            }

            $batch->recalculateCapturedAmount();
        });

        return redirect('/daily-transaction')
            ->with('success', 'Batch ' . $code . ' berhasil dibuat dengan ' . count($crIds) . ' CR.');
    }

    /**
     * Normalisasi input uang berformat "1.234.567" atau "1234567" menjadi integer.
     */
    private function normalizeMoney($value): int
    {
        if (blank($value)) return 0;
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'createdBy', 'approvedBy', 'paidBy',
            'attachments.uploader',
            'items.budgetRequest.type',
            'items.budgetRequest.cluster',
            'items.budgetRequest.beneficiary.bank',
            'items.budgetRequest.applicant',
            'sourceFund.bank',
        ])->findOrFail($id);

        return response()->json([
            'id'                        => $batch->id,
            'code'                      => $batch->code,
            'date'                      => $batch->date->format('d M Y'),
            'description'               => $batch->description,
            'source_fund'               => $batch->sourceFund->name ?? '-',
            'source_fund_bank'          => $batch->sourceFund->bank->name ?? '-',
            'source_fund_no_account'    => $batch->sourceFund->no_account ?? '-',
            'charge_formatted'          => $batch->charge_formatted,
            'captured_amount_formatted' => $batch->captured_amount_formatted,
            'grand_total_formatted'     => $batch->grand_total_formatted,
            'status'                    => $batch->status,
            'status_label'           => $batch->status_label,
            'status_color'           => $batch->status_color,
            'notes'                  => $batch->notes,
            'payment_notes'          => $batch->payment_notes,
            'created_by'             => $batch->createdBy->name ?? '-',
            'approved_by'            => $batch->approvedBy->name ?? '-',
            'approved_at'            => $batch->approved_at?->format('d M Y H:i'),
            'paid_by'                => $batch->paidBy->name ?? '-',
            'paid_at'                => $batch->paid_at?->format('d M Y H:i'),
            'total_amount_formatted' => $batch->total_amount_formatted,
            'included_count'         => $batch->includedItems()->count(),
            'rejected_count'         => $batch->rejectedItems()->count(),
            'created_at'             => $batch->created_at->format('d M Y H:i'),

            'attachments' => $batch->attachments->map(fn($a) => [
                'original_name'  => $a->original_name,
                'path'           => $a->path,
                'size_formatted' => $a->size_formatted,
                'uploaded_by'    => $a->uploader->name ?? '-',
            ]),

            'items' => $batch->items->map(fn($item) => [
                'id'             => $item->id,
                'status'         => $item->status,
                'notes'          => $item->notes,
                'cr_id'          => $item->budget_request_id,
                'cr_code'        => $item->budgetRequest->code ?? '-',
                'cr_description' => $item->budgetRequest->description ?? '-',
                'cr_type'        => $item->budgetRequest->type->name ?? '-',
                'cr_cluster'     => $item->budgetRequest->cluster->name ?? '-',
                'cr_amount'      => $item->budgetRequest->amount ?? 0,
                'cr_amount_fmt'  => $item->budgetRequest->amount_formatted ?? '-',
                'cr_applicant'   => $item->budgetRequest->applicant->name ?? '-',
                'cr_status'      => $item->budgetRequest->status ?? '-',
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE — CEO approve → CR jadi QUEUED
    |--------------------------------------------------------------------------
    */

    public function approve(Request $request, $id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with('includedItems.budgetRequest')->findOrFail($id);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }
        if ($batch->includedItems()->count() === 0) {
            return back()->with('error', 'Tidak ada CR dalam batch ini.');
        }

        DB::transaction(function () use ($request, $batch) {
            foreach ($batch->includedItems as $item) {
                $item->budgetRequest->update(['status' => 'QUEUED']);
                $item->budgetRequest->addLog('VERIFIED', 'QUEUED', 'ceo_approved', $request->notes);
            }
            $batch->update([
                'status'      => 'APPROVED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        return redirect('/daily-transaction/' . $id)
            ->with('success', 'Batch disetujui. ' . $batch->includedItems()->count() . ' CR siap dibayar.');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT BATCH — seluruh batch ditolak → CR kembali ke VERIFIED
    |--------------------------------------------------------------------------
    */

    public function rejectBatch(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with('includedItems.budgetRequest')->findOrFail($id);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }

        DB::transaction(function () use ($request, $batch) {
            foreach ($batch->includedItems as $item) {
                $item->budgetRequest->update(['status' => 'VERIFIED']);
                $item->budgetRequest->addLog('VERIFIED', 'VERIFIED', 'ceo_rejected', $request->notes);
                $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
            }
            $batch->update([
                'status'      => 'REJECTED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        return redirect('/daily-transaction')
            ->with('success', 'Batch ditolak. Semua CR dikembalikan ke VERIFIED.');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT ITEM — CEO takeout 1 CR → CR kembali ke VERIFIED
    |--------------------------------------------------------------------------
    */

    public function rejectItem(Request $request, $batchId, $itemId)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $batch = DailyTransaction::findOrFail($batchId);
        $item  = DailyTransactionItem::where('daily_transaction_id', $batchId)->findOrFail($itemId);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }
        if ($item->status !== 'INCLUDED') {
            return back()->with('error', 'Item ini sudah di-takeout.');
        }

        DB::transaction(function () use ($request, $batch, $item) {
            $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
            $item->budgetRequest->update(['status' => 'VERIFIED']);
            $item->budgetRequest->addLog('VERIFIED', 'VERIFIED', 'ceo_rejected', $request->notes);
            $batch->refreshCode();
            $batch->recalculateCapturedAmount();
        });

        return redirect('/daily-transaction/' . $batchId)
            ->with('success', 'CR ' . ($item->budgetRequest->code ?? '-') . ' berhasil di-takeout.');
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */

    public function addItem(Request $request, $batchId)
    {
        $request->validate([
            'cr_ids'   => 'required|array|min:1',
            'cr_ids.*' => 'exists:budget_request,id',
        ]);

        $batch = DailyTransaction::findOrFail($batchId);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Hanya batch PENDING_APPROVER yang bisa ditambah item.');
        }

        $added = 0;

        DB::transaction(function () use ($request, $batch, &$added) {
            foreach ($request->cr_ids as $crId) {
                $cr = BudgetRequest::find($crId);
                if (!$cr || $cr->status !== 'VERIFIED') continue;

                $alreadyIn = DailyTransactionItem::where('budget_request_id', $crId)
                    ->where('status', 'INCLUDED')
                    ->whereHas('dailyTransaction', fn($q) =>
                        $q->whereIn('status', ['PENDING_APPROVER', 'APPROVED'])
                    )->exists();

                if ($alreadyIn) continue;

                $batch->items()->create([
                    'budget_request_id' => $crId,
                    'status'            => 'INCLUDED',
                ]);
                $added++;
            }
            $batch->refreshCode();
            $batch->recalculateCapturedAmount();
        });

        return back()->with('success', $added . ' CR berhasil ditambahkan ke batch.');
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAID — Finance upload bukti + tandai PAID
    | Batch: APPROVED → PAID
    | CR QUEUED → PAID
    | Lampiran disimpan ke daily_transaction_attachments
    |--------------------------------------------------------------------------
    */

    public function markPaid(Request $request, $id)
    {
        $request->validate([
            'payment_notes'   => 'nullable|string|max:500',
            'attachments'     => 'required|array|min:1',
            'attachments.*'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with('includedItems.budgetRequest')->findOrFail($id);

        if (!$batch->isApproved()) {
            return back()->with('error', 'Hanya batch APPROVED yang dapat ditandai PAID.');
        }

        DB::transaction(function () use ($request, $batch) {

            // Simpan lampiran ke daily_transaction_attachments
            foreach ($request->file('attachments', []) as $file) {
                if (!$file) continue;
                $path = $file->store('daily_transaction_attachments', 'public');
                $batch->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => Auth::id(),
                ]);
            }

            // CR QUEUED → PAID
            foreach ($batch->includedItems as $item) {
                if ($item->budgetRequest->status !== 'QUEUED') continue;
                $item->budgetRequest->update([
                    'status'  => 'PAID',
                    'paid_at' => now(),
                ]);
                $item->budgetRequest->addLog('QUEUED', 'PAID', 'paid', $request->payment_notes);
                $item->budgetRequest->budgetPlan?->checkAndAutoClose();
            }

            // Batch → PAID
            $batch->update([
                'status'        => 'PAID',
                'paid_by'       => Auth::id(),
                'paid_at'       => now(),
                'payment_notes' => $request->payment_notes,
            ]);
        });

        return redirect('/daily-transaction/' . $id)
            ->with('success', 'Batch ' . $batch->code . ' berhasil ditandai PAID.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'createdBy', 'approvedBy', 'paidBy',
            'attachments.uploader',
            'items.budgetRequest.type',
            'items.budgetRequest.cluster',
            'items.budgetRequest.beneficiary.bank',
            'items.budgetRequest.applicant',
        ])->findOrFail($id);

        $availableCRs = BudgetRequest::where('status', 'VERIFIED')
            ->whereDoesntHave('dailyTransactionItems', fn($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'APPROVED'])
                  )
            )
            ->with(['type', 'cluster'])
            ->latest()->get();

        return view('daily-transaction.show', compact('batch', 'availableCRs'));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT BATCH — file transfer bank (hanya batch APPROVED)
    |--------------------------------------------------------------------------
    */

    public function export($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'items.budgetRequest.beneficiary.bank',
        ])->findOrFail($id);

        // Hanya batch yang sudah disetujui yang boleh diexport
        if ($batch->status !== 'APPROVED') {
            return back()->with('error', 'Export hanya tersedia untuk batch berstatus APPROVED.');
        }

        // Hanya item yang INCLUDED (yang di-takeout/REJECTED tidak ikut ditransfer)
        $items = $batch->items->where('status', 'INCLUDED');

        $filename = 'batch_' . $batch->code . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ];

        $columns = [
            'Transfer Method',
            'Account Number',
            'Currency',
            'Transfer Amount',
            'Beneficiary',
            'Bank',
            'Kode Bank',
            'Reference',
            'Remark',
            'Email',
        ];

        $callback = function () use ($items, $columns) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, $columns);

            foreach ($items as $item) {
                $cr    = $item->budgetRequest;
                if (!$cr) continue;

                $benef = $cr->beneficiary;
                $bank  = $benef->bank ?? null;

                fputcsv($out, [
                    $bank->default_method ?? '',              // Transfer Method
                    $benef->account_number ?? '',             // Account Number
                    'IDR',                                    // Currency
                    (int) ($cr->amount ?? 0),                 // Transfer Amount
                    $benef->complete_name ?? '',              // Beneficiary
                    $bank->name ?? '',                        // Bank
                    $bank->code ?? '',                        // Kode Bank
                    $cr->code ?? '',                          // Reference
                    '',                                       // Remark (sengaja dikosongkan)
                    $cr->email ?? '',                         // Email notifikasi
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}