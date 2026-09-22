<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryNoteController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $search   = trim((string) $request->query('q', ''));
        $status   = $request->query('status');
        $customer = $request->query('customer_id');

        /*
         | MN-08 — pencarian di daftar Surat Jalan menembus relasi sampai ke
         | KODE INVOICE dan NO. PO:
         |
         |     Surat Jalan -> Invoice -> No. PO
         |
         | Itu yang benar-benar dicari orang: mereka memegang kode invoice atau
         | nomor PO dari customer, lalu ingin tahu surat jalan mana yang
         | membawanya. Mencari lewat kode surat jalan saja praktis tidak pernah
         | terpakai karena kode itu justru yang sedang dicari.
         |
         | Penyaringannya dikerjakan di SQL (whereHas), bukan dengan memuat
         | semua baris lalu memfilter di PHP — daftar ini tumbuh terus.
         */
        $baseQuery = DeliveryNote::with(['customer:id,name', 'items.invoice:id,code'])
            ->withCount('items')
            ->when(in_array($status, DeliveryNote::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($customer, fn ($q) => $q->where('customer_id', $customer))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where(function ($w) use ($like) {
                    $w->where('code', 'like', $like)
                      ->orWhereHas('customer', fn ($r) => $r->where('name', 'like', $like))
                      ->orWhereHas('items.invoice', fn ($r) => $r->where('code', 'like', $like))
                      ->orWhereHas('items.invoice.poNumber', fn ($r) => $r->where('po_number', 'like', $like))
                      ->orWhereHas('items.invoice.po', fn ($r) => $r->where('code', 'like', $like)
                                                                    ->orWhere('name', 'like', $like));
                });
            });

        // Ringkasan dihitung dari himpunan yang SAMA dengan daftar (sebelum
        // dipotong halaman), bukan dari query lain — supaya kartu di atas tidak
        // pernah bercerita lain dari isi tabel.
        $scoped = (clone $baseQuery)->get(['id', 'status']);

        $summary = [
            'total'    => $scoped->count(),
            'sent'     => $scoped->where('status', 'SENT')->count(),
            'received' => $scoped->where('status', 'RECEIVED')->count(),
        ];

        $deliveryNotes = $baseQuery->latest()->paginate(20)->withQueryString();

        $customers = \App\Models\Customer::orderBy('name')->get(['id', 'name']);

        $filters = ['q' => $search, 'status' => $status, 'customer_id' => $customer];

        return view('delivery-note.index', compact('deliveryNotes', 'summary', 'customers', 'filters'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / STORE
    |--------------------------------------------------------------------------
    | Invoice yang bisa dimasukkan ke Surat Jalan: status APPROVED dan belum
    | pernah dipakai di Surat Jalan lain. Satu Surat Jalan = satu customer
    | (satu alamat tujuan pengiriman).
    */

    public function create()
    {
        $approvedInvoices = Invoice::where('status', 'APPROVED')
            ->whereDoesntHave('deliveryNoteItem')
            ->with('po.customer')
            ->orderBy('invoice_date')
            ->get();

        $groupedByCustomer = $approvedInvoices->groupBy(fn ($inv) => $inv->po->customer_id ?? 0);

        return view('delivery-note.create', compact('groupedByCustomer'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_ids'   => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $invoices = Invoice::whereIn('id', $request->invoice_ids)->with('po.customer')->get();

        if ($invoices->contains(fn ($inv) => $inv->status !== 'APPROVED')) {
            return back()->withInput()->with('error', 'Semua invoice yang dipilih harus berstatus APPROVED.');
        }

        $customerIds = $invoices->pluck('po.customer_id')->filter()->unique();
        if ($customerIds->count() > 1) {
            return back()->withInput()->with('error', 'Semua invoice dalam satu Surat Jalan harus dari customer yang sama.');
        }
        if ($customerIds->isEmpty()) {
            return back()->withInput()->with('error', 'Customer pada invoice yang dipilih tidak valid.');
        }

        $alreadyUsed = DeliveryNoteItem::whereIn('invoice_id', $invoices->pluck('id'))->exists();
        if ($alreadyUsed) {
            return back()->withInput()->with('error', 'Salah satu invoice yang dipilih sudah termasuk dalam Surat Jalan lain.');
        }

        $deliveryNote = DB::transaction(function () use ($request, $invoices, $customerIds) {
            $note = DeliveryNote::create([
                'code'        => DeliveryNote::generateCode(),
                'customer_id' => $customerIds->first(),
                'status'      => 'SENT',
                'notes'       => $request->notes,
                'created_by'  => Auth::id(),
                'sent_at'     => now(),
            ]);

            foreach ($invoices as $invoice) {
                $note->items()->create(['invoice_id' => $invoice->id]);

                $invoice->update([
                    'status'  => 'SENT',
                    'sent_by' => Auth::id(),
                    'sent_at' => now(),
                ]);
            }

            $note->addLog('SENT', $request->notes);

            return $note;
        });

        return redirect('/delivery-note/' . $deliveryNote->id)
            ->with('success', 'Surat Jalan ' . $deliveryNote->code . ' berhasil dibuat. ' . $invoices->count() . ' invoice ditandai Sent.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW / PRINT
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        /*
        | MN-08 — pencarian di halaman ini menjangkau Kode Invoice DAN No. PO,
        | yang berarti relasi Surat Jalan -> Invoice -> No. PO harus ikut
        | dimuat. Semuanya di-eager-load di satu tempat ini; tanpa poNumber,
        | setiap baris tabel akan memicu query sendiri (N+1).
        */
        $deliveryNote = DeliveryNote::with([
            'customer',
            'items.invoice.po:id,code,name',
            'items.invoice.poNumber:id,po_number,project_id',
            'logs.actor',
            'logs.attachments',
            'createdBy',
            'receivedBy',
        ])->findOrFail($id);

        return view('delivery-note.show', compact('deliveryNote'));
    }

    public function printView($id)
    {
        $deliveryNote = DeliveryNote::with(['customer', 'items.invoice.po', 'items.invoice.poNumber'])->findOrFail($id);

        return view('delivery-note.print', compact('deliveryNote'));
    }

    /*
    |--------------------------------------------------------------------------
    | RECEIVE — SENT -> RECEIVED (wajib lampirkan evidence)
    |--------------------------------------------------------------------------
    | Cascade: setiap invoice di dalam Surat Jalan ini yang statusnya masih
    | SENT ikut ditandai RECEIVED. Invoice yang sudah lanjut (PARTIALLY_PAID/
    | PAID) tidak diturunkan/diubah — hanya dicatat evidence-nya di Surat Jalan.
    */

    public function receive(Request $request, $id)
    {
        $deliveryNote = DeliveryNote::with('items.invoice')->findOrFail($id);

        if ($deliveryNote->status !== 'SENT') {
            return back()->with('error', 'Hanya Surat Jalan berstatus SENT yang dapat ditandai diterima.');
        }

        $request->validate([
            'notes'       => 'nullable|string|max:1000',
            'evidence'    => 'required|array|min:1',
            'evidence.*'  => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        DB::transaction(function () use ($request, $deliveryNote) {
            $deliveryNote->update([
                'status'      => 'RECEIVED',
                'received_by' => Auth::id(),
                'received_at' => now(),
            ]);

            $log = $deliveryNote->addLog('RECEIVED', $request->notes);

            foreach ($request->file('evidence', []) as $file) {
                if (!$file->isValid()) continue;

                $path = $file->store('delivery_note_evidence', 'secure');
                $deliveryNote->attachments()->create([
                    'delivery_note_log_id' => $log->id,
                    'type'                 => 'evidence',
                    'title'                => $file->getClientOriginalName(),
                    'file_path'            => $path,
                    'uploaded_by'          => Auth::id(),
                ]);
            }

            foreach ($deliveryNote->items as $item) {
                $invoice = $item->invoice;
                if ($invoice && $invoice->status === 'SENT') {
                    $invoice->update([
                        'status'       => 'RECEIVED',
                        'received_by'  => Auth::id(),
                        'received_at'  => now(),
                    ]);
                }
            }
        });

        return redirect('/delivery-note/' . $deliveryNote->id)
            ->with('success', 'Surat Jalan ' . $deliveryNote->code . ' ditandai diterima.');
    }
}
