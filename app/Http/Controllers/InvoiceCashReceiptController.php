<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceAttachment;
use App\Models\CashReceipt;
use App\Models\Project;
use App\Models\SourceFund;

class InvoiceCashReceiptController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $invoices = Invoice::with(['po.customer', 'cashReceipts'])->latest()->get();

        $summary = [
            'total'           => $invoices->count(),
            'draft'           => $invoices->where('status', 'DRAFT')->count(),
            'submitted'       => $invoices->where('status', 'SUBMITTED')->count(),
            'verified'        => $invoices->where('status', 'VERIFIED')->count(),
            'approved'        => $invoices->where('status', 'APPROVED')->count(),
            'sent'            => $invoices->where('status', 'SENT')->count(),
            'partially_paid'  => $invoices->where('status', 'PARTIALLY_PAID')->count(),
            'paid'            => $invoices->where('status', 'PAID')->count(),
            'cancelled'       => $invoices->where('status', 'CANCELLED')->count(),
            'overdue'         => $invoices->filter(fn ($i) => $i->display_status === 'OVERDUE')->count(),
        ];

        return view('invoice-cash-receipt.index', compact('invoices', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / STORE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        // Hanya PO berstatus PROGRESS_DONE yang siap ditagihkan
        $availablePOs = Project::with(['customer', 'attachments'])
            ->where('status', 'PROGRESS_DONE')
            ->orderBy('name')
            ->get();

        $poPayload = $this->buildPoPayload($availablePOs);

        return view('invoice-cash-receipt.create', compact('availablePOs', 'poPayload'));
    }

    /**
     * Payload JSON per PO: info customer, sisa nilai kontrak, & lampiran PO
     * (dipakai JS form create/edit tanpa perlu AJAX round-trip terpisah).
     */
    private function buildPoPayload($pos): array
    {
        $payload = [];
        foreach ($pos as $po) {
            $payload[$po->id] = [
                'name'              => $po->name,
                'customer'          => $po->customer->name ?? '-',
                'value'             => (int) ($po->value ?? 0),
                'value_formatted'   => 'Rp ' . number_format($po->value ?? 0, 0, ',', '.'),
                'remaining'         => $po->remainingContractValue(),
                'remaining_formatted' => 'Rp ' . number_format($po->remainingContractValue(), 0, ',', '.'),
                'attachments'       => $po->attachments->where('type', 'general')->values()->map(fn ($a) => [
                    'title' => $a->title ?? $a->file_name,
                    'path'  => $a->file_path,
                ]),
            ];
        }
        return $payload;
    }

    public function store(Request $request)
    {
        $request->validate([
            'po_id'              => 'required|exists:projects,id',
            'payment_term_type'  => 'required|in:' . implode(',', Invoice::PAYMENT_TERM_TYPES),
            'termin_number'      => 'nullable|integer|min:1',
            'invoice_date'       => 'required|date',
            'due_date'           => 'required|date|after_or_equal:invoice_date',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            'remark'             => 'nullable|string|max:2000',
            'details'                    => 'required|array|min:1',
            'details.*.description'     => 'required|string|max:255',
            'details.*.unit_price'      => 'required',
            'details.*.qty'             => 'required|integer|min:1',
            'details.*.deduction'       => 'nullable',
            'attachments'                => 'nullable|array',
            'attachments.*.title'        => 'nullable|string|max:255',
            'attachments.*.file'         => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $invoice = DB::transaction(function () use ($request) {
            $code = Invoice::generateCode((int) $request->po_id, \Carbon\Carbon::parse($request->invoice_date));

            $invoice = Invoice::create([
                'code'               => $code,
                'po_id'              => $request->po_id,
                'payment_term_type'  => $request->payment_term_type,
                'termin_number'      => $request->payment_term_type === 'Termin' ? $request->termin_number : null,
                'invoice_date'       => $request->invoice_date,
                'due_date'           => $request->due_date,
                'tax_rate'           => $request->tax_rate ?? 11,
                'remark'             => $request->remark,
                'status'             => 'DRAFT',
                'created_by'         => Auth::id(),
            ]);

            $this->syncDetails($invoice, $request);
            $this->storeAttachments($invoice, $request);

            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect('/invoice-cash-receipt/' . $invoice->id)
            ->with('success', 'Invoice ' . $invoice->code . ' berhasil dibuat sebagai Draft.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT / UPDATE — hanya DRAFT (revisi dari Submitted/Verified balik ke sini)
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $invoice = Invoice::with(['details', 'attachments', 'po.customer'])->findOrFail($id);

        if ($invoice->status !== 'DRAFT') {
            return redirect('/invoice-cash-receipt/' . $id)
                ->with('error', 'Hanya invoice berstatus DRAFT yang dapat diedit.');
        }

        $availablePOs = Project::with(['customer', 'attachments'])
            ->where(fn ($q) => $q->where('status', 'PROGRESS_DONE')->orWhere('id', $invoice->po_id))
            ->orderBy('name')->get();

        $poPayload = $this->buildPoPayload($availablePOs);

        return view('invoice-cash-receipt.edit', compact('invoice', 'availablePOs', 'poPayload'));
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'DRAFT') {
            return back()->with('error', 'Hanya invoice berstatus DRAFT yang dapat diedit.');
        }

        $request->validate([
            'po_id'              => 'required|exists:projects,id',
            'payment_term_type'  => 'required|in:' . implode(',', Invoice::PAYMENT_TERM_TYPES),
            'termin_number'      => 'nullable|integer|min:1',
            'invoice_date'       => 'required|date',
            'due_date'           => 'required|date|after_or_equal:invoice_date',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            'remark'             => 'nullable|string|max:2000',
            'details'                    => 'required|array|min:1',
            'details.*.description'     => 'required|string|max:255',
            'details.*.unit_price'      => 'required',
            'details.*.qty'             => 'required|integer|min:1',
            'details.*.deduction'       => 'nullable',
            'attachments'                => 'nullable|array',
            'attachments.*.title'        => 'nullable|string|max:255',
            'attachments.*.file'         => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments'         => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            $invoice->update([
                'po_id'              => $request->po_id,
                'payment_term_type'  => $request->payment_term_type,
                'termin_number'      => $request->payment_term_type === 'Termin' ? $request->termin_number : null,
                'invoice_date'       => $request->invoice_date,
                'due_date'           => $request->due_date,
                'tax_rate'           => $request->tax_rate ?? 11,
                'remark'             => $request->remark,
            ]);

            foreach ((array) $request->input('delete_attachments', []) as $attId) {
                $att = InvoiceAttachment::where('invoice_id', $invoice->id)->find($attId);
                if ($att) {
                    Storage::disk('public')->delete($att->file_path);
                    $att->delete();
                }
            }

            $invoice->details()->delete();
            $this->syncDetails($invoice, $request);
            $this->storeAttachments($invoice, $request);

            $invoice->recalculateTotals();
        });

        return redirect('/invoice-cash-receipt/' . $invoice->id)
            ->with('success', 'Invoice ' . $invoice->code . ' berhasil diperbarui.');
    }

    private function syncDetails(Invoice $invoice, Request $request): void
    {
        foreach ((array) $request->input('details', []) as $row) {
            $unitPrice = $this->normalizeMoney($row['unit_price'] ?? null);
            $qty       = (int) ($row['qty'] ?? 1);
            $deduction = $this->normalizeMoney($row['deduction'] ?? null);

            if (blank($row['description'] ?? null) && !$unitPrice) continue;

            $total = ($unitPrice * $qty) - $deduction;

            $invoice->details()->create([
                'description' => $row['description'] ?? null,
                'unit_price'  => $unitPrice,
                'qty'         => $qty,
                'deduction'   => $deduction,
                'total'       => max(0, $total),
            ]);
        }
    }

    private function storeAttachments(Invoice $invoice, Request $request): void
    {
        $fileRows  = $request->file('attachments', []);
        $inputRows = $request->input('attachments', []);

        foreach ($fileRows as $i => $row) {
            $file = $row['file'] ?? null;
            if (!$file || !$file->isValid()) continue;

            $title = $inputRows[$i]['title'] ?? $file->getClientOriginalName();

            $path = $file->store('invoice_attachments', 'public');
            $invoice->attachments()->create([
                'title'     => strtoupper($title),
                'file_path' => $path,
                'file_name' => basename($path),
                'file_type' => $file->getClientMimeType(),
                'input_by'  => Auth::id(),
            ]);
        }
    }

    private function normalizeMoney($value): int
    {
        if (blank($value)) return 0;
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $invoice = Invoice::with([
            'po.customer', 'po.attachments',
            'details', 'attachments.inputBy',
            'cashReceipts.sourceFund', 'cashReceipts.inputBy',
            'createdBy', 'submittedBy', 'verifiedBy', 'approvedBy', 'sentBy', 'cancelledBy',
        ])->findOrFail($id);

        $sourceFunds = SourceFund::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('invoice-cash-receipt.show', compact('invoice', 'sourceFunds'));
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $invoice = Invoice::with('attachments')->findOrFail($id);

        if ($invoice->status !== 'DRAFT') {
            return back()->with('error', 'Hanya invoice berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->attachments as $att) {
                Storage::disk('public')->delete($att->file_path);
            }
            $invoice->delete();
        });

        return redirect('/invoice-cash-receipt')->with('success', 'Invoice berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: SUBMIT — DRAFT -> SUBMITTED
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'DRAFT') {
            return back()->with('error', 'Hanya invoice berstatus DRAFT yang dapat disubmit.');
        }

        $invoice->update([
            'status'       => 'SUBMITTED',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Invoice ' . $invoice->code . ' berhasil disubmit.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: VERIFY (Finance Manager) — SUBMITTED -> VERIFIED
    |--------------------------------------------------------------------------
    */

    public function verify($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'SUBMITTED') {
            return back()->with('error', 'Hanya invoice berstatus SUBMITTED yang dapat diverifikasi.');
        }

        $invoice->update([
            'status'      => 'VERIFIED',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Invoice ' . $invoice->code . ' berhasil diverifikasi.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: APPROVE (Approver) — VERIFIED -> APPROVED
    |--------------------------------------------------------------------------
    */

    public function approve($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'VERIFIED') {
            return back()->with('error', 'Hanya invoice berstatus VERIFIED yang dapat di-approve.');
        }

        $invoice->update([
            'status'      => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Invoice ' . $invoice->code . ' berhasil di-approve.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: REVISE — mundur satu tahap
    |   SUBMITTED -> DRAFT     (oleh Finance Manager)
    |   VERIFIED  -> SUBMITTED (oleh Approver)
    |--------------------------------------------------------------------------
    */

    public function revise(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:1000']);

        $invoice = Invoice::findOrFail($id);

        $map = [
            'SUBMITTED' => 'DRAFT',
            'VERIFIED'  => 'SUBMITTED',
        ];

        if (!isset($map[$invoice->status])) {
            return back()->with('error', 'Invoice berstatus ' . $invoice->status . ' tidak dapat direvisi.');
        }

        $invoice->update([
            'status' => $map[$invoice->status],
            'remark' => trim(($invoice->remark ? $invoice->remark . "\n\n" : '') . '[REVISI] ' . $request->notes),
        ]);

        return back()->with('success', 'Invoice ' . $invoice->code . ' dikembalikan untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: SENT — APPROVED -> SENT
    |--------------------------------------------------------------------------
    */

    public function sent($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'APPROVED') {
            return back()->with('error', 'Hanya invoice berstatus APPROVED yang dapat ditandai Sent.');
        }

        $invoice->update([
            'status'  => 'SENT',
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        return back()->with('success', 'Invoice ' . $invoice->code . ' ditandai sudah dikirim ke customer.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: CANCEL
    |--------------------------------------------------------------------------
    */

    public function cancel(Request $request, $id)
    {
        $request->validate(['cancel_reason' => 'required|string|max:1000']);

        $invoice = Invoice::findOrFail($id);

        if (in_array($invoice->status, ['PAID', 'CANCELLED'])) {
            return back()->with('error', 'Invoice berstatus ' . $invoice->status . ' tidak dapat dibatalkan.');
        }

        $invoice->update([
            'status'        => 'CANCELLED',
            'cancelled_by'  => Auth::id(),
            'cancelled_at'  => now(),
            'cancel_reason' => $request->cancel_reason,
        ]);

        return back()->with('success', 'Invoice ' . $invoice->code . ' dibatalkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | CASH RECEIPT
    |--------------------------------------------------------------------------
    */

    public function storeCashReceipt(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        if (!in_array($invoice->status, ['SENT', 'PARTIALLY_PAID'])) {
            return back()->with('error', 'Cash Receipt hanya bisa ditambahkan untuk invoice berstatus SENT atau PARTIALLY PAID.');
        }

        $request->validate([
            'received_date'  => 'required|date',
            'amount'         => 'required',
            'source_fund_id' => 'nullable|exists:source_funds,id',
            'proof_file'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $amount = $this->normalizeMoney($request->amount);

        if ($amount > $invoice->remainingBalance()) {
            return back()->with('error',
                'Nominal melebihi sisa tagihan (Rp ' . number_format($invoice->remainingBalance(), 0, ',', '.') . ').'
            );
        }

        $proofPath = null;
        if ($request->hasFile('proof_file') && $request->file('proof_file')->isValid()) {
            $proofPath = $request->file('proof_file')->store('cash_receipt_proofs', 'public');
        }

        CashReceipt::create([
            'invoice_id'      => $invoice->id,
            'received_date'   => $request->received_date,
            'amount'          => $amount,
            'source_fund_id'  => $request->source_fund_id,
            'proof_file'      => $proofPath,
            'notes'           => $request->notes,
            'input_by'        => Auth::id(),
        ]);

        return back()->with('success', 'Cash Receipt berhasil dicatat.');
    }

    public function destroyCashReceipt($id, $crId)
    {
        $cr = CashReceipt::where('invoice_id', $id)->findOrFail($crId);

        if ($cr->proof_file) {
            Storage::disk('public')->delete($cr->proof_file);
        }
        $cr->delete(); // syncPaymentStatus() otomatis jalan lewat model event

        return back()->with('success', 'Cash Receipt berhasil dihapus.');
    }
}