<?php

namespace App\Http\Controllers;

use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SourceFund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * M-03 — Cash Receipt (Header + Detail).
 *
 * Halaman ini untuk penerimaan uang yang menutup BEBERAPA invoice sekaligus.
 * Pembayaran satu invoice tunggal tetap dilakukan dari halaman invoice
 * (InvoiceCashReceiptController::storeCashReceipt) seperti sebelumnya —
 * keduanya menulis ke tabel yang sama, jadi tidak ada angka yang bercabang.
 *
 * Status invoice TIDAK ditentukan oleh ada/tidaknya Cash Receipt, melainkan
 * oleh nominal: lihat Invoice::syncPaymentStatus(), yang dipanggil otomatis
 * dari CashReceipt::booted(). Itu sudah sesuai aturan M-03 dan tidak diubah.
 */
class CashReceiptController extends Controller
{
    /*
    | Daftar + ringkasan dikirim UTUH ke halaman, lalu penyaringan dikerjakan
    | di browser (DataTables + TesysColumnFilter), sama seperti halaman index
    | lain. Sebelumnya penyaringan diproses di server lewat GET; itu berarti
    | tiap kali status atau customer dipilih, halaman dimuat ulang.
    |
    | Yang dulu jadi alasan bertahan di server adalah kartu ringkasan di atas
    | tabel: kalau tabel disaring di browser sementara kartunya tetap angka
    | server, keduanya bercerita hal berbeda. Itu diselesaikan di sisi tampilan
    | — kartunya dihitung ulang dari baris yang terlihat setiap kali filter
    | berubah (lihat resources/views/cash-receipt/index.blade.php), bukan
    | dengan memaksa halaman reload.
    |
    | Angka di sini tinggal jadi keadaan awal (tanpa filter apa pun).
    */
    public function index(Request $request)
    {
        $headers = CashReceiptHeader::with([
                'customer:id,name', 'sourceFund:id,name', 'createdBy:id,username', 'details.invoice:id,code',
            ])
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'count'         => $headers->count(),
            'total'         => (int) $headers->sum('total_amount'),
            'invoice_count' => (int) $headers->sum(fn ($h) => $h->details->count()),
            'allocated'     => (int) $headers->sum(fn ($h) => $h->allocatedAmount()),
            'unallocated'   => (int) $headers->sum(fn ($h) => max(0, $h->unallocatedAmount())),
        ];

        return view('cash-receipt.index', [
            'headers' => $headers,
            'summary' => $summary,
        ]);
    }

    /**
     * Cash Receipt disusun dengan cara yang SAMA dengan Treasury:
     *
     *     Treasury      : batch PEMBAYARAN atas kumpulan Cost Request
     *     Cash Receipt  : batch PENERIMAAN atas kumpulan Invoice
     *
     * Jadi bentuknya pun sama — pilih dokumennya dulu lewat centang, isi
     * informasi batch, lalu simpan sekali. Versi sebelumnya meminta nominal
     * total lebih dulu baru mengalokasikan satu per satu; itu memaksa orang
     * menjumlah sendiri di luar sistem sebelum bisa mulai, dan menyisakan
     * "uang belum teralokasi" yang tidak pernah dimaksudkan ada.
     */
    public function create(Request $request)
    {
        $customerId = $request->query('customer_id');

        $invoices = collect();

        if ($customerId) {
            $invoices = Invoice::with(['cashReceipts', 'poNumber:id,po_number', 'po:id,code,name'])
                ->whereIn('status', ['SENT', 'RECEIVED', 'PARTIALLY_PAID'])
                ->whereHas('po', fn ($q) => $q->where('customer_id', $customerId))
                ->orderBy('due_date')
                ->get()
                ->map(function ($inv) {
                    $received = (int) $inv->cashReceipts->sum('amount') + (int) $inv->cashReceipts->sum('withholding_tax');
                    $inv->computed_received = $received;
                    $inv->computed_balance  = max(0, (int) $inv->captured_total - $received);
                    return $inv;
                })
                ->filter(fn ($inv) => $inv->computed_balance > 0)
                ->values();
        }

        return view('cash-receipt.create', [
            'customers'   => Customer::orderBy('name')->get(['id', 'name']),
            'sourceFunds' => SourceFund::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            'invoices'    => $invoices,
            'customerId'  => $customerId,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'received_date'       => 'required|date',
            'customer_id'         => 'required|exists:customers,id',
            'source_fund_id'      => 'nullable|exists:source_funds,id',
            'reference_number'    => 'nullable|string|max:255',
            'proof_file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'notes'               => 'nullable|string|max:1000',
            'invoice_ids'         => 'required|array|min:1',
            'invoice_ids.*'       => 'exists:invoices,id',
            'amounts'             => 'required|array',
            'withholding'         => 'nullable|array',
        ]);

        $invoiceIds  = (array) $request->input('invoice_ids', []);
        $amounts     = (array) $request->input('amounts', []);
        $withholding = (array) $request->input('withholding', []);

        $proofPath = null;
        if ($request->hasFile('proof_file') && $request->file('proof_file')->isValid()) {
            $proofPath = $request->file('proof_file')->store('cash_receipt_proofs', 'secure');
        }

        /*
        | Seluruh pemeriksaan dilakukan DI DALAM satu transaksi dengan invoice
        | terkunci: dua penerimaan yang dicatat bersamaan atas invoice yang sama
        | bisa sama-sama lolos pengecekan sisa tagihan kalau dibaca di luar
        | kunci, dan invoice jadi terbayar melebihi tagihannya.
        */
        $hasil = DB::transaction(function () use ($request, $invoiceIds, $amounts, $withholding, $proofPath) {
            $baris = [];
            $total = 0;

            foreach ($invoiceIds as $id) {
                $nominal = $this->normalizeMoney($amounts[$id] ?? 0);
                $pph     = $this->normalizeMoney($withholding[$id] ?? 0);

                // Baris bernilai nol tidak menambah informasi apa pun.
                if ($nominal + $pph <= 0) {
                    continue;
                }

                $invoice = Invoice::where('id', $id)->lockForUpdate()->first();

                if (!$invoice) {
                    return ['error' => 'Invoice tidak ditemukan.'];
                }

                if ($invoice->po?->customer_id !== (int) $request->customer_id) {
                    return ['error' => 'Invoice ' . $invoice->code . ' bukan milik customer yang dipilih.'];
                }

                if (!in_array($invoice->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'], true)) {
                    return ['error' => 'Invoice ' . $invoice->code . ' berstatus ' . $invoice->status . ' dan tidak menerima pembayaran.'];
                }

                if ($nominal + $pph > $invoice->remainingBalance()) {
                    return ['error' => 'Nominal untuk invoice ' . $invoice->code . ' melebihi sisa tagihannya (' .
                        number_format($invoice->remainingBalance(), 0, ',', '.') . ').'];
                }

                $baris[] = ['invoice' => $invoice, 'amount' => $nominal, 'withholding' => $pph];
                $total  += $nominal + $pph;
            }

            if (!$baris) {
                return ['error' => 'Isi nominal untuk minimal satu invoice.'];
            }

            $header = CashReceiptHeader::create([
                'code'             => CashReceiptHeader::generateCode(\Carbon\Carbon::parse($request->received_date)),
                'received_date'    => $request->received_date,
                'customer_id'      => $request->customer_id,
                'source_fund_id'   => $request->source_fund_id,
                'reference_number' => $request->reference_number,
                'proof_file'       => $proofPath,
                // Total batch = jumlah alokasinya, bukan angka yang diketik
                // terpisah — dengan begitu tidak mungkin ada selisih antara
                // "uang yang diterima" dan "uang yang dialokasikan".
                'total_amount'     => $total,
                'status'           => 'DRAFT',
                'notes'            => $request->notes,
                'created_by'       => Auth::id(),
            ]);

            foreach ($baris as $b) {
                CashReceipt::create([
                    'cash_receipt_header_id' => $header->id,
                    'invoice_id'             => $b['invoice']->id,
                    'received_date'          => $request->received_date,
                    'amount'                 => $b['amount'],
                    'withholding_tax'        => $b['withholding'],
                    'source_fund_id'         => $request->source_fund_id,
                    'input_by'               => Auth::id(),
                ]);
            }

            return ['header' => $header];
        });

        if (isset($hasil['error'])) {
            // Berkas bukti yang terlanjur tersimpan ikut dibersihkan supaya
            // tidak menumpuk sebagai berkas yatim.
            if ($proofPath) {
                Storage::disk('secure')->delete($proofPath);
            }

            return back()->withInput()->with('error', $hasil['error']);
        }

        $header = $hasil['header'];

        return redirect()->route('cash-receipt.show', $header->id)
            ->with('success', 'Penerimaan ' . $header->code . ' tercatat: ' .
                $header->details()->count() . ' invoice, Rp ' . number_format($header->total_amount, 0, ',', '.') . '.');
    }

    public function show($id)
    {
        $header = CashReceiptHeader::with([
            'customer', 'sourceFund', 'createdBy', 'allocatedBy', 'cancelledBy',
            'details.invoice.poNumber',
        ])->findOrFail($id);

        /*
        | Invoice yang bisa dialokasikan: milik customer yang sama, sudah masuk
        | tahap penagihan, dan masih punya sisa tagihan. Statusnya dibaca dari
        | nominal (remainingBalance), bukan dari label status — sesuai aturan
        | M-03 yang melarang menyimpulkan lunas hanya dari keberadaan receipt.
        */
        $openInvoices = Invoice::with(['cashReceipts', 'poNumber'])
            ->whereIn('status', ['SENT', 'RECEIVED', 'PARTIALLY_PAID'])
            ->whereHas('po', fn ($q) => $q->where('customer_id', $header->customer_id))
            ->orderBy('due_date')
            ->get()
            ->map(function ($inv) {
                $received = (int) $inv->cashReceipts->sum('amount') + (int) $inv->cashReceipts->sum('withholding_tax');
                $inv->computed_received = $received;
                $inv->computed_balance  = max(0, (int) $inv->captured_total - $received);
                return $inv;
            })
            ->filter(fn ($inv) => $inv->computed_balance > 0)
            ->values();

        return view('cash-receipt.show', compact('header', 'openInvoices'));
    }

    /*
    |--------------------------------------------------------------------------
    | ALOKASI KE INVOICE
    |--------------------------------------------------------------------------
    */

    public function allocate(Request $request, $id)
    {
        $header = CashReceiptHeader::with('details')->findOrFail($id);

        if ($header->isCancelled()) {
            return back()->with('error', 'Penerimaan yang sudah dibatalkan tidak bisa dialokasikan.');
        }

        $request->validate([
            'invoice_id'      => 'required|exists:invoices,id',
            'amount'          => 'required|regex:/[0-9]/',
            'withholding_tax' => 'nullable|string',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $amount      = $this->normalizeMoney($request->amount);
        $withholding = $this->normalizeMoney($request->withholding_tax);

        if ($amount + $withholding < 1) {
            return back()->with('error', 'Nominal alokasi harus lebih dari nol.');
        }

        // Dikunci di dalam transaksi: dua alokasi bersamaan atas invoice yang
        // sama bisa sama-sama lolos pengecekan sisa tagihan kalau dibaca di
        // luar kunci, dan invoice jadi terbayar melebihi tagihannya.
        $error = DB::transaction(function () use ($request, $header, $amount, $withholding) {
            /*
            | Header dikunci DULU, baru invoice. Sebelumnya hanya invoice yang
            | dikunci, jadi dua alokasi dari bukti terima yang sama ke dua
            | invoice BERBEDA tidak saling menunggu: keduanya membaca sisa uang
            | yang sama dan sama-sama lolos - total alokasi melebihi uang yang
            | benar-benar diterima. Dengan header terkunci, alokasi berikutnya
            | antre dan melihat sisa yang sudah berkurang. Urutan header ->
            | invoice juga dipakai cancel(), supaya keduanya tidak deadlock.
            */
            $fresh = CashReceiptHeader::whereKey($header->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->isCancelled()) {
                return 'Penerimaan yang sudah dibatalkan tidak bisa dialokasikan.';
            }

            $invoice = Invoice::where('id', $request->invoice_id)->lockForUpdate()->first();

            if (!$invoice) {
                return 'Invoice tidak ditemukan.';
            }

            if ($invoice->po?->customer_id !== $header->customer_id) {
                return 'Invoice ini bukan milik customer pada bukti penerimaan.';
            }

            if (!in_array($invoice->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'], true)) {
                return 'Invoice berstatus ' . $invoice->status . ' tidak menerima alokasi pembayaran.';
            }

            if ($amount + $withholding > $invoice->remainingBalance()) {
                return 'Alokasi melebihi sisa tagihan invoice (' .
                    number_format($invoice->remainingBalance(), 0, ',', '.') . ').';
            }

            $fresh->load('details');
            if ($amount + $withholding > $fresh->unallocatedAmount()) {
                return 'Alokasi melebihi sisa uang yang belum dialokasikan (' .
                    number_format($fresh->unallocatedAmount(), 0, ',', '.') . ').';
            }

            CashReceipt::create([
                'cash_receipt_header_id' => $header->id,
                'invoice_id'             => $invoice->id,
                'received_date'          => $header->received_date,
                'amount'                 => $amount,
                'withholding_tax'        => $withholding,
                'source_fund_id'         => $header->source_fund_id,
                'notes'                  => $request->notes,
                'input_by'               => Auth::id(),
            ]);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Alokasi pembayaran berhasil dicatat.');
    }

    public function removeAllocation($id, $detailId)
    {
        $header = CashReceiptHeader::findOrFail($id);

        $detail = CashReceipt::where('cash_receipt_header_id', $header->id)->findOrFail($detailId);

        // Soft delete: status invoice ikut disinkronkan otomatis lewat
        // CashReceipt::booted(), dan jejak pembatalannya tetap ada.
        $detail->update(['deleted_by' => Auth::id(), 'delete_reason' => 'Alokasi dibatalkan dari bukti terima ' . $header->code]);
        $detail->delete();

        return back()->with('success', 'Alokasi dibatalkan.');
    }

    public function cancel(Request $request, $id)
    {
        $request->validate(['cancel_reason' => 'required|string|max:255']);

        $header = CashReceiptHeader::with('details')->findOrFail($id);

        if ($header->isCancelled()) {
            return back()->with('error', 'Penerimaan ini sudah dibatalkan.');
        }

        DB::transaction(function () use ($header, $request) {
            /*
            | Kunci header lebih dulu (urutan sama dengan allocate()): tanpa
            | ini, alokasi yang sedang berjalan bisa tetap tercatat pada bukti
            | terima yang di saat bersamaan dibatalkan. Bila sudah dibatalkan
            | request lain, tidak ada yang perlu dikerjakan lagi.
            */
            $header = CashReceiptHeader::with('details')->whereKey($header->id)->lockForUpdate()->first();

            if (! $header || $header->isCancelled()) {
                return;
            }

            // Seluruh alokasinya ikut dibatalkan — kalau tidak, invoice akan
            // tetap terlihat terbayar dari uang yang pembuktiannya dianulir.
            foreach ($header->details as $detail) {
                $detail->update([
                    'deleted_by'    => Auth::id(),
                    'delete_reason' => 'Bukti terima ' . $header->code . ' dibatalkan',
                ]);
                $detail->delete();
            }

            $header->update([
                'status'        => 'CANCELLED',
                'cancelled_by'  => Auth::id(),
                'cancelled_at'  => now(),
                'cancel_reason' => $request->cancel_reason,
            ]);
        });

        return back()->with('success', 'Penerimaan ' . $header->code . ' dibatalkan.');
    }

    public function destroy($id)
    {
        $header = CashReceiptHeader::with('details')->findOrFail($id);

        if ($header->details->isNotEmpty()) {
            return back()->with('error', 'Penerimaan yang sudah punya alokasi tidak bisa dihapus — batalkan saja supaya jejaknya tetap ada.');
        }

        if ($header->proof_file) {
            Storage::disk('secure')->delete($header->proof_file);
        }

        $header->delete();

        return redirect()->route('cash-receipt.index')->with('success', 'Penerimaan dihapus.');
    }

    private function normalizeMoney($value): int
    {
        if (blank($value)) {
            return 0;
        }

        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
