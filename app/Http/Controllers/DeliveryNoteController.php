<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DeliveryNoteController extends Controller
{
    use Concerns\FiltersByUpdatedRange;

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        /*
         | Penyaringan dikerjakan di browser (DataTables + TesysColumnFilter),
         | sama seperti Cash Receipt: tidak ada tombol "Terapkan" dan tidak ada
         | muat ulang halaman — mengetik atau mencentang langsung menyaring.
         |
         | Kartu ringkasan tetap tidak boleh bercerita lain dari tabelnya; itu
         | dijaga di sisi klien dengan menghitung ulang kartu dari baris yang
         | lolos filter (refreshCards di view), bukan lagi dengan memaksa
         | query ulang ke server.
         */
        // poNumber ikut dimuat karena kotak cari di browser juga harus
        // menemukan Surat Jalan lewat No. PO — nomornya ditulis tersembunyi di
        // kolom Invoice agar bisa dicari tanpa memenuhi layar.
        $deliveryNotes = DeliveryNote::with([
                'customer:id,name',
                'items.invoice:id,code,project_po_number_id',
                'items.invoice.poNumber:id,po_number',
            ])
            ->withCount('items');

        // Filter "Last Update Time" (opsional; tanpa parameter = semua data).
        [$dateFrom, $dateTo] = $this->applyUpdatedRange($deliveryNotes, $request);

        $deliveryNotes = $deliveryNotes->latest()->get();

        $summary = [
            'total'    => $deliveryNotes->count(),
            'sent'     => $deliveryNotes->where('status', 'SENT')->count(),
            'received' => $deliveryNotes->where('status', 'RECEIVED')->count(),
        ];

        return view('delivery-note.index', compact('deliveryNotes', 'summary', 'dateFrom', 'dateTo'));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL
    |--------------------------------------------------------------------------
    | Mengikuti filter aktif di tabel: browser mengirim 'ids' (lihat
    | public/js/tesys-export-filtered.js). Tanpa 'ids' = seluruh data.
    | Hanya memuat kolom yang sama dengan daftar (tanpa nominal keuangan).
    */

    public function exportList(Request $request)
    {
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        $deliveryNotes = DeliveryNote::with([
                'customer:id,name',
                'items.invoice:id,code,project_po_number_id',
                'items.invoice.poNumber:id,po_number',
            ])
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->latest()
            ->get();

        $columns = ['Kode Surat Jalan', 'Customer', 'Jml Invoice', 'Kode Invoice', 'No. PO', 'Tanggal Kirim', 'Tanggal Terima', 'Status', 'Catatan'];
        $lastCol = chr(ord('A') + count($columns) - 1);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Surat Jalan');
        $sheet->fromArray($columns, null, 'A1');
        $header = $sheet->getStyle('A1:' . $lastCol . '1');
        $header->getFont()->setBold(true);
        $header->getFont()->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');

        $row = 2;
        foreach ($deliveryNotes as $dn) {
            $sheet->fromArray([
                $dn->code,
                $dn->customer->name ?? '-',
                $dn->items->count(),
                $dn->items->pluck('invoice.code')->filter()->implode(', ') ?: '-',
                $dn->items->pluck('invoice.poNumber.po_number')->filter()->unique()->implode(', ') ?: '-',
                $dn->sent_at ? $dn->sent_at->format('d/m/Y') : '-',
                $dn->received_at ? $dn->received_at->format('d/m/Y') : '-',
                $dn->status_label,
                $dn->notes ?: '-',
            ], null, 'A' . $row);
            $row++;
        }

        $widths = ['A'=>18,'B'=>34,'C'=>12,'D'=>40,'E'=>30,'F'=>14,'G'=>14,'H'=>12,'I'=>40];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'surat_jalan_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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

    /**
     * Edit Surat Jalan oleh Superadmin di status apa pun (keputusan 2026-11-12):
     * Catatan, Tanggal kirim, Tanggal terima (bila sudah diterima). Daftar invoice
     * TIDAK diubah, dan tanggal invoice (sent_at / received_at / jatuh tempo aktual)
     * TIDAK ikut berubah. Rincian perubahan dicatat di log Surat Jalan.
     */
    public function superUpdate(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(['superadmin']), 403);

        $data = $request->validate([
            'notes'       => 'nullable|string|max:1000',
            'sent_at'     => 'required|date|before_or_equal:now',
            'received_at' => 'nullable|date|before_or_equal:now|after_or_equal:sent_at',
        ]);

        $ubah = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $id) {
            $dn = DeliveryNote::whereKey($id)->lockForUpdate()->firstOrFail();
            $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y H:i') : '-';

            $baru = [
                'notes'   => $data['notes'] ?? null,
                'sent_at' => \Illuminate\Support\Carbon::parse($data['sent_at']),
            ];
            // Tanggal terima hanya relevan untuk Surat Jalan yang sudah diterima.
            if ($dn->status === 'RECEIVED' && ! empty($data['received_at'])) {
                $baru['received_at'] = \Illuminate\Support\Carbon::parse($data['received_at']);
            }

            $ubah = [];
            if ((string) ($dn->notes ?? '') !== (string) ($baru['notes'] ?? '')) {
                $ubah[] = 'Catatan: ' . ($dn->notes ?: '-') . ' -> ' . ($baru['notes'] ?: '-');
            }
            if ($fmt($dn->sent_at) !== $fmt($baru['sent_at'])) {
                $ubah[] = 'Tanggal kirim: ' . $fmt($dn->sent_at) . ' -> ' . $fmt($baru['sent_at']);
            }
            if (isset($baru['received_at']) && $fmt($dn->received_at) !== $fmt($baru['received_at'])) {
                $ubah[] = 'Tanggal terima: ' . $fmt($dn->received_at) . ' -> ' . $fmt($baru['received_at']);
            }
            if (! $ubah) {
                return [];
            }

            $dn->update($baru);
            $dn->addLog('SUPERADMIN_EDITED', mb_substr('[status ' . $dn->status . '] ' . implode('; ', $ubah), 0, 2000));

            return $ubah;
        });

        if (! $ubah) {
            return back()->with('success', 'Tidak ada perubahan pada Surat Jalan.');
        }

        \App\Models\UserActivityLog::record('delivery_note', 'update',
            '[Superadmin] Mengedit Surat Jalan ' . DeliveryNote::whereKey($id)->value('code'),
            DeliveryNote::find($id), implode('; ', $ubah));

        return back()->with('success', 'Surat Jalan diperbarui (' . count($ubah) . ' perubahan, tercatat di log).');
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

        try {
        $deliveryNote = DB::transaction(function () use ($request, $invoices, $customerIds) {
            /*
            | Kunci invoice lalu periksa ulang: pemeriksaan "APPROVED" dan
            | "belum ada di Surat Jalan lain" di atas berjalan tanpa kunci, jadi
            | dua Surat Jalan yang dibuat bersamaan bisa memuat invoice yang sama.
            */
            $terkunci = Invoice::whereIn('id', $invoices->pluck('id'))
                ->orderBy('id')->lockForUpdate()->get();

            if ($terkunci->contains(fn ($inv) => $inv->status !== 'APPROVED')
                || DeliveryNoteItem::whereIn('invoice_id', $terkunci->pluck('id'))->exists()) {
                throw new \DomainException('Salah satu invoice baru saja diproses pengguna lain — muat ulang halaman.');
            }

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
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

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
