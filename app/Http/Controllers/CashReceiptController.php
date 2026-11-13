<?php

namespace App\Http\Controllers;

use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SourceFund;
use App\Models\UserActivityLog;
use App\Support\PeriodLock;
use App\Exceptions\PeriodLockedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
    // Urutan kunci No. PO -> BAST -> invoice (per id) -> header/detail - lihat Concerns\LocksPoNumbers.
    use Concerns\LocksPoNumbers;

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
        // Rentang tanggal terima dikerjakan di server (default: bulan berjalan); sisanya (cari, Status,
        // Customer) tetap di browser. Kolom tanggalnya DATE, jadi dibandingkan langsung sebagai Y-m-d.
        [$dateFrom, $dateTo, $tanggalValid] = $this->rentangTanggal($request);
        $range = in_array($request->query('range'), ['1d', '3d', '7d', '30d', '1y'], true)
            ? $request->query('range') : '';

        if ($range !== '') {
            // Rentang cepat dihitung dengan jam SERVER (bukan jam browser), relatif terhadap hari ini.
            $mulai = match ($range) {
                '1d'  => now()->subDay(),
                '3d'  => now()->subDays(3),
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
                '1y'  => now()->subYear(),
            };
            $dateFrom = $mulai->format('Y-m-d');
            $dateTo   = now()->format('Y-m-d');
        } elseif ($tanggalValid) {
            $range = 'custom'; // tanggal eksplisit yang valid; tanggal tidak valid = bawaan (bulan berjalan)
        }

        $headers = CashReceiptHeader::with([
                'customer:id,name', 'sourceFund:id,name', 'createdBy:id,username',
                'details.invoice:id,code,billed_amount,tax_amount',
            ])
            ->where('received_date', '>=', $dateFrom)
            ->where('received_date', '<=', $dateTo)
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();

        /*
        | Kolom uang diturunkan dari DETAIL aktif (relasi details sudah tanpa baris soft-deleted),
        | bukan dari total_amount - total_amount = diterima + PPh, dan total_amount tetap dipakai
        | hanya untuk sisa belum teralokasi. Tagihan = Invoice + PPN dari invoice UNIK pada header
        | (satu invoice boleh muncul di beberapa detail). Header batal: seluruh kolom uang 0.
        */
        $headers->each(function ($h) {
            $aktif = $h->isCancelled() ? collect() : $h->details;

            $h->setAttribute('kolom_diterima', (int) $aktif->sum('amount'));
            $h->setAttribute('kolom_pph', (int) $aktif->sum('withholding_tax'));
            $h->setAttribute('kolom_tagihan', (int) $aktif->pluck('invoice')->filter()->unique('id')
                ->sum(fn ($inv) => (int) $inv->billed_amount + (int) $inv->tax_amount));
        });

        $summary = [
            'count'         => $headers->count(),
            'total'         => (int) $headers->sum('kolom_diterima'),
            'invoice_count' => (int) $headers->sum(fn ($h) => $h->details->count()),
            'allocated'     => (int) $headers->sum(fn ($h) => $h->allocatedAmount()),
            'unallocated'   => (int) $headers->sum(fn ($h) => max(0, $h->unallocatedAmount())),
        ];

        return view('cash-receipt.index', [
            'headers'  => $headers,
            'summary'  => $summary,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'range'    => $range,
        ]);
    }

    /**
     * @return array{0:string,1:string,2:bool} [dari, sampai] format Y-m-d inklusif, dan apakah tanggal dari request valid.
     * Default bulan berjalan; nilai tidak valid atau sampai < dari kembali ke default.
     * (Pola crDateRange BudgetRequestController, sengaja disalin kecil - tidak dipanggil dari sana.)
     */
    private function rentangTanggal(Request $request): array
    {
        $awal  = now()->startOfMonth()->format('Y-m-d');
        $akhir = now()->endOfMonth()->format('Y-m-d');

        $valid = Validator::make($request->only('date_from', 'date_to'), [
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d',
        ])->passes();

        if (! $valid) {
            return [$awal, $akhir, false];
        }

        // Dinormalkan lewat Carbon supaya perbandingan string Y-m-d selalu terurut (mis. "2026-1-5" -> "2026-01-05").
        $dari   = \Carbon\Carbon::createFromFormat('Y-m-d', $request->query('date_from'))->format('Y-m-d');
        $sampai = \Carbon\Carbon::createFromFormat('Y-m-d', $request->query('date_to'))->format('Y-m-d');

        return $sampai < $dari ? [$awal, $akhir, false] : [$dari, $sampai, true];
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
        /*
        | Daftar invoice TIDAK lagi disiapkan di sini. Dulu halaman ini dimuat
        | ulang setiap kali customer dipilih (form GET + onchange submit), jadi
        | langkah pertama selalu memutus pekerjaan yang sedang berjalan. Sekarang
        | daftarnya diambil halaman lewat openInvoices() di bawah, dan hanya
        | bagian tabelnya yang berubah.
        |
        | ?customer_id= tetap dilayani supaya tautan lama/bookmark masih jalan —
        | nilainya dipakai untuk memilih opsi awal, lalu daftarnya diambil JS.
        */
        return view('cash-receipt.create', [
            'customers'   => Customer::orderBy('name')->get(['id', 'name']),
            'sourceFunds' => SourceFund::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            'customerId'  => $request->query('customer_id'),
        ]);
    }

    /**
     * Invoice terbuka milik satu customer — sumber data tabel "2. Pilih Invoice".
     *
     * Dipisah jadi endpoint sendiri supaya pemilihan customer tidak perlu memuat
     * ulang halaman. Isinya PERSIS query yang dulu dipakai create(), termasuk
     * penyaringan berdasarkan sisa tagihan: invoice yang nominalnya sudah tertutup
     * tidak boleh muncul walau statusnya belum PAID (M-03 — lunas disimpulkan dari
     * nominal, bukan dari label status).
     */
    public function openInvoices(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $invoices = Invoice::with(['cashReceipts', 'poNumber:id,po_number', 'po:id,code,name'])
            ->whereIn('status', ['SENT', 'RECEIVED', 'PARTIALLY_PAID'])
            ->whereHas('po', fn ($q) => $q->where('customer_id', $request->query('customer_id')))
            ->orderBy('due_date')
            ->get()
            ->map(function ($inv) {
                // Sisa BRUTO (Invoice + PPN - diterima - PPh receipt), bukan captured_total.
                $sumPph = (int) $inv->cashReceipts->sum('withholding_tax');
                $inv->computed_balance = Invoice::hitungSisaBruto(
                    (int) $inv->billed_amount,
                    (int) $inv->tax_amount,
                    (int) $inv->cashReceipts->sum('amount'),
                    $sumPph,
                );
                // Hint PPh di muka: hanya bila PPh invoice belum berasal dari PPh receipt (akan diganti PPh aktual).
                $inv->wht_muka = ((int) $inv->withholding_tax > 0 && (int) $inv->withholding_tax !== $sumPph)
                    ? (int) $inv->withholding_tax : 0;
                return $inv;
            })
            ->filter(fn ($inv) => $inv->computed_balance > 0)
            ->values()
            ->map(fn ($inv) => [
                'id'        => $inv->id,
                'code'      => $inv->code,
                'po_number' => $inv->poNumber->po_number ?? '-',
                'project'   => $inv->po->name ?? '-',
                'due_date'  => ($inv->actual_due_date ?? $inv->due_date)?->format('d M Y'),
                'overdue'   => $inv->isOverdue(),
                'status'    => $inv->status,
                'balance'   => (int) $inv->computed_balance,
                'dpp'       => (int) $inv->billed_amount,
                'wht_muka'  => (int) $inv->wht_muka,
            ]);

        return response()->json(['invoices' => $invoices]);
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
            'partial_note'        => 'nullable|string|max:1000',
            'invoice_ids'         => 'required|array|min:1',
            // distinct: id ganda membuat dua receipt lolos cek sisa tagihan yang sama (dihitung sebelum receipt dibuat).
            'invoice_ids.*'       => 'distinct|exists:invoices,id',
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

        // Invoice yang diisi nominalnya, dan No. PO-nya - dicari SEBELUM transaksi (lihat Concerns\LocksPoNumbers).
        $berisi = collect($invoiceIds)
            ->filter(fn ($id) => $this->normalizeMoney($amounts[$id] ?? 0)
                + $this->normalizeMoney($withholding[$id] ?? 0) > 0)
            ->unique()->values();
        $poIds = $this->noPoDariInvoice($berisi);

        /*
        | Seluruh pemeriksaan dilakukan DI DALAM satu transaksi dengan invoice
        | terkunci: dua penerimaan yang dicatat bersamaan atas invoice yang sama
        | bisa sama-sama lolos pengecekan sisa tagihan kalau dibaca di luar
        | kunci, dan invoice jadi terbayar melebihi tagihannya.
        */
        try {
            $hasil = DB::transaction(function () use ($request, $invoiceIds, $amounts, $withholding, $proofPath, $berisi, $poIds) {
                $baris = [];
                $total = 0;
                $parsial = [];

                // Urutan kunci: No. PO (id naik) -> BAST -> invoice (id naik) -> header. Pembayaran memicu
                // sinkron status No. PO (Invoice::syncPaymentStatus), jadi No. PO harus dikunci lebih dulu.
                $this->kunciNoPo($poIds);

                /*
                | Semua invoice yang diisi nominalnya dikunci SEKALIGUS, urut id,
                | sebelum ada pembacaan lain. Dulu dikunci satu per satu mengikuti
                | urutan request, sehingga:
                |  - dua penerimaan [A, B] dan [B, A] bersamaan bisa deadlock;
                |  - invoice kedua dst. dikunci setelah ada baca biasa (relasi po,
                |    remainingBalance) - di REPEATABLE READ baca biasa pertama
                |    menetapkan snapshot, jadi sisa tagihan invoice berikutnya bisa
                |    terbaca dari snapshot lama dan pembayaran yang baru masuk
                |    terlewat.
                | Pemeriksaan & pesan tetap mengikuti urutan request.
                */
                $terkunci = Invoice::whereIn('id', $berisi)
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                foreach ($invoiceIds as $id) {
                    $nominal = $this->normalizeMoney($amounts[$id] ?? 0);
                    $pph     = $this->normalizeMoney($withholding[$id] ?? 0);

                    // Baris bernilai nol tidak menambah informasi apa pun.
                    if ($nominal + $pph <= 0) {
                        continue;
                    }

                    $invoice = $terkunci[$id] ?? null;

                    if (!$invoice) {
                        return ['error' => 'Invoice tidak ditemukan.'];
                    }

                    if ($invoice->po?->customer_id !== (int) $request->customer_id) {
                        return ['error' => 'Invoice ' . $invoice->code . ' bukan milik customer yang dipilih.'];
                    }

                    if (!in_array($invoice->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'], true)) {
                        return ['error' => 'Invoice ' . $invoice->code . ' berstatus ' . $invoice->status . ' dan tidak menerima pembayaran.'];
                    }

                    // Batas = sisa tagihan BRUTO (Invoice + PPN - diterima - PPh receipt), dihitung di dalam kunci.
                    $sisa = $invoice->sisaBruto();

                    if ($nominal + $pph > $sisa) {
                        return ['error' => 'Nominal untuk invoice ' . $invoice->code . ' melebihi sisa tagihannya (' .
                            number_format($sisa, 0, ',', '.') . ').'];
                    }

                    if ($nominal + $pph < $sisa) {
                        $parsial[] = ['invoice' => $invoice, 'sisa' => $sisa, 'amount' => $nominal, 'withholding' => $pph];
                    }

                    $baris[] = ['invoice' => $invoice, 'amount' => $nominal, 'withholding' => $pph, 'sisa' => $sisa];
                    $total  += $nominal + $pph;
                }

                if (!$baris) {
                    return ['error' => 'Isi nominal untuk minimal satu invoice.'];
                }

                // Pembayaran tidak penuh wajib disertai komentar; komentarnya masuk ke Log & Approval invoice terkait.
                $catatanParsial = trim((string) $request->partial_note);
                if ($parsial && $catatanParsial === '') {
                    return ['error' => 'Ada invoice yang dibayar tidak penuh. Tulis komentar alasannya.'];
                }

                // Kunci periode: tanggal terima tidak boleh jatuh di periode Closing CLOSED.
                PeriodLock::assertOpen($request->received_date, 'Penerimaan', $request, null, null, true);

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
                    // PPh aktual disalin ke PPh invoice + captured_total dihitung ulang SEBELUM receipt disimpan,
                    // supaya hook CashReceipt::saved menyinkronkan status dari invoice yang sudah konsisten.
                    // Penerimaan yang melunasi sisa bruto dengan PPh 0 menolkan PPh di muka.
                    $b['invoice']->sinkronPphDariCashReceipt(
                        'penerimaan ' . $header->code,
                        $b['withholding'],
                        $b['withholding'] === 0 && $b['amount'] >= $b['sisa'],
                        false,
                        $b['amount'],
                    );

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

                foreach ($parsial as $p) {
                    $p['invoice']->comments()->create([
                        'user_id' => Auth::id(),
                        'comment' => 'Penerimaan ' . $header->code . ' tidak penuh (diterima Rp ' . number_format($p['amount'], 0, ',', '.')
                            . ' + PPh Rp ' . number_format($p['withholding'], 0, ',', '.')
                            . ' dari sisa tagihan Rp ' . number_format($p['sisa'], 0, ',', '.') . '): ' . $catatanParsial,
                    ]);
                }

                return ['header' => $header];
            });
        } catch (\DomainException|PeriodLockedException $e) {
            // Sinkron PPh ditolak / periode terkunci (mis. invoice tidak konsisten): seluruh transaksi sudah dibatalkan.
            $hasil = ['error' => $e->getMessage()];
        }

        if (isset($hasil['error'])) {
            // Berkas bukti yang terlanjur tersimpan ikut dibersihkan supaya
            // tidak menumpuk sebagai berkas yatim.
            if ($proofPath) {
                Storage::disk('secure')->delete($proofPath);
            }

            return back()->withInput()->with('error', $hasil['error']);
        }

        $header = $hasil['header'];

        UserActivityLog::record('cash_receipt', 'create', 'Mencatat penerimaan #' . $header->code, $header);

        return redirect()->route('cash-receipt.show', $header->id)
            ->with('success', 'Penerimaan ' . $header->code . ' tercatat: ' .
                $header->details()->count() . ' invoice, diterima Rp ' . number_format((int) $header->details()->sum('amount'), 0, ',', '.') . '.');
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
                $inv->computed_received = Invoice::hitungDiterima(
                    (int) $inv->cashReceipts->sum('amount'),
                    (int) $inv->cashReceipts->sum('withholding_tax'),
                    (int) $inv->withholding_tax,
                );
                // Sisa BRUTO (Invoice + PPN - diterima - PPh receipt) - sama dengan batas input di store/allocate.
                $inv->computed_balance  = Invoice::hitungSisaBruto(
                    (int) $inv->billed_amount,
                    (int) $inv->tax_amount,
                    (int) $inv->cashReceipts->sum('amount'),
                    (int) $inv->cashReceipts->sum('withholding_tax'),
                );
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

        $poIds = $this->noPoDariInvoice([$request->invoice_id]); // sebelum transaksi (Concerns\LocksPoNumbers)

        // Dikunci di dalam transaksi: dua alokasi bersamaan atas invoice yang
        // sama bisa sama-sama lolos pengecekan sisa tagihan kalau dibaca di
        // luar kunci, dan invoice jadi terbayar melebihi tagihannya.
        try {
            $error = DB::transaction(function () use ($request, $header, $amount, $withholding, $poIds) {
                // Urutan kunci: No. PO -> BAST -> invoice -> header (alokasi memicu sinkron status No. PO).
                $this->kunciNoPo($poIds);

                /*
                | Invoice dikunci dulu, lalu HEADER. Sebelumnya hanya invoice yang
                | dikunci, jadi dua alokasi dari bukti terima yang sama ke dua
                | invoice BERBEDA tidak saling menunggu: keduanya membaca sisa uang
                | yang sama dan sama-sama lolos - total alokasi melebihi uang yang
                | benar-benar diterima. Dengan header ikut terkunci, alokasi
                | berikutnya antre dan melihat sisa yang sudah berkurang.
                |
                | Urutan No. PO -> invoice -> header WAJIB sama di semua jalur: hapus/settle
                | receipt (removeAllocation, destroyCashReceipt, settleCashReceipt)
                | mengunci invoice lalu header lewat hook CashReceipt::saved/deleted,
                | dan cancel() mengikuti urutan yang sama. Urutan terbalik membuat
                | dua jalur itu bisa saling deadlock.
                */
                $invoice = Invoice::where('id', $request->invoice_id)->lockForUpdate()->first();

                if (!$invoice) {
                    return 'Invoice tidak ditemukan.';
                }

                $fresh = CashReceiptHeader::whereKey($header->id)->lockForUpdate()->first();

                if (! $fresh || $fresh->isCancelled()) {
                    return 'Penerimaan yang sudah dibatalkan tidak bisa dialokasikan.';
                }

                // Kunci periode: alokasi membuat baris Cash Receipt bertanggal header.
                PeriodLock::assertOpen($fresh->received_date, 'Penerimaan', $request, $header, null, true);

                if ($invoice->po?->customer_id !== $header->customer_id) {
                    return 'Invoice ini bukan milik customer pada bukti penerimaan.';
                }

                if (!in_array($invoice->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'], true)) {
                    return 'Invoice berstatus ' . $invoice->status . ' tidak menerima alokasi pembayaran.';
                }

                // Batas = sisa tagihan BRUTO (Invoice + PPN - diterima - PPh receipt), dihitung di dalam kunci.
                $sisa = $invoice->sisaBruto();
                if ($amount + $withholding > $sisa) {
                    return 'Alokasi melebihi sisa tagihan invoice (' .
                        number_format($sisa, 0, ',', '.') . ').';
                }

                $fresh->load('details');
                if ($amount + $withholding > $fresh->unallocatedAmount()) {
                    return 'Alokasi melebihi sisa uang yang belum dialokasikan (' .
                        number_format($fresh->unallocatedAmount(), 0, ',', '.') . ').';
                }

                // PPh aktual disalin ke PPh invoice SEBELUM receipt disimpan (lihat store()).
                $invoice->sinkronPphDariCashReceipt(
                    'alokasi penerimaan ' . $header->code,
                    $withholding,
                    $withholding === 0 && $amount >= $sisa,
                    false,
                    $amount,
                );

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
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($error) {
            return back()->with('error', $error);
        }

        UserActivityLog::record('cash_receipt', 'process', 'Mengalokasikan pembayaran penerimaan #' . $header->code, $header);

        return back()->with('success', 'Alokasi pembayaran berhasil dicatat.');
    }

    public function removeAllocation(Request $request, $id, $detailId)
    {
        $header = CashReceiptHeader::findOrFail($id);

        $detail = CashReceipt::where('cash_receipt_header_id', $header->id)->findOrFail($detailId);

        $poIds = $this->noPoDariInvoice([$detail->invoice_id]); // sebelum transaksi (Concerns\LocksPoNumbers)

        // Satu transaksi dengan urutan kunci No. PO -> invoice -> detail (sama dengan allocate/cancel).
        // Soft delete: status invoice ikut disinkronkan otomatis lewat CashReceipt::booted(),
        // dan jejak pembatalannya tetap ada.
        try {
            DB::transaction(function () use ($header, $detail, $poIds, $request) {
                $this->kunciNoPo($poIds);
                $invoice = Invoice::whereKey($detail->invoice_id)->lockForUpdate()->first();

                $terkunci = CashReceipt::whereKey($detail->id)->lockForUpdate()->first();
                if (! $terkunci) {
                    return; // sudah dihapus request lain
                }

                // Tanpa bypass (UI tidak menyediakan alasan): Superadmin membuka periode lebih dulu.
                PeriodLock::assertOpen($terkunci->received_date, 'Penerimaan', null, $header, null, true);

                // PPh alokasi ini dibalik dari PPh invoice SEBELUM barisnya dihapus (selisih -PPh dilewatkan
                // sebagai PPh tertunda), supaya hook CashReceipt::deleted membaca invoice yang sudah konsisten.
                if ($invoice && (int) $terkunci->withholding_tax > 0) {
                    $invoice->sinkronPphDariCashReceipt(
                        'pembatalan alokasi penerimaan ' . $header->code,
                        -(int) $terkunci->withholding_tax,
                        false,
                        true,
                    );
                }

                // Penanda hapus disimpan tanpa event; sinkron status cukup dari delete() di bawah.
                $terkunci->fill(['deleted_by' => Auth::id(), 'delete_reason' => 'Alokasi dibatalkan dari bukti terima ' . $header->code])->saveQuietly();
                $terkunci->delete();
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        UserActivityLog::record('cash_receipt', 'update', 'Membatalkan alokasi penerimaan #' . $header->code, $header);

        return back()->with('success', 'Alokasi dibatalkan.');
    }

    public function cancel(Request $request, $id)
    {
        $request->validate(['cancel_reason' => 'required|string|max:255']);

        $header = CashReceiptHeader::with('details')->findOrFail($id);

        if ($header->isCancelled()) {
            return back()->with('error', 'Penerimaan ini sudah dibatalkan.');
        }

        // No. PO dari alokasi saat ini - dicari SEBELUM transaksi (Concerns\LocksPoNumbers).
        $poIds = $this->noPoDariInvoice(
            CashReceipt::where('cash_receipt_header_id', $header->id)->pluck('invoice_id')
        );

        try {
            DB::transaction(function () use ($header, $request, $poIds) {
                $this->kunciNoPo($poIds); // No. PO (id naik) lebih dulu - pembatalan memicu sinkron status No. PO

                /*
                | Urutan kunci sama dengan allocate(): No. PO, invoice-invoice alokasinya
                | (id naik), lalu header. Tanpa kunci header, alokasi yang
                | sedang berjalan bisa tetap tercatat pada bukti terima yang di
                | saat bersamaan dibatalkan. Daftar alokasi dibaca dengan kunci
                | (bukan baca biasa) supaya alokasi yang baru saja masuk ikut
                | terbaca dan ikut dibatalkan. Bila sudah dibatalkan request lain,
                | tidak ada yang perlu dikerjakan lagi.
                */
                $invoiceIds = CashReceipt::where('cash_receipt_header_id', $header->id)
                    ->lockForUpdate()->pluck('invoice_id')->unique()->sort()->values();

                // Alokasi baru ke invoice di No. PO lain bisa masuk antara pembacaan awal dan kunci:
                // No. PO-nya belum terkunci, jadi minta diulang daripada mengunci dengan urutan terbalik.
                if (array_diff($this->noPoDariInvoice($invoiceIds), $poIds)) {
                    throw new \DomainException('Alokasi pada bukti terima ini berubah saat diproses. Muat ulang halaman lalu ulangi.');
                }

                $invoices = Invoice::whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $header = CashReceiptHeader::whereKey($header->id)->lockForUpdate()->first();

                if (! $header || $header->isCancelled()) {
                    return;
                }

                $header->setRelation('details',
                    CashReceipt::where('cash_receipt_header_id', $header->id)->lockForUpdate()->get());

                // Kunci periode: tanggal header DAN tanggal tiap alokasi (bisa berbeda setelah super-update Cash Receipt).
                foreach ($header->details->pluck('received_date')->push($header->received_date)->filter()
                    ->map(fn ($d) => $d->toDateString())->unique() as $tgl) {
                    PeriodLock::assertOpen($tgl, 'Penerimaan', $request, $header, null, true);
                }

                // Seluruh alokasinya ikut dibatalkan — kalau tidak, invoice akan
                // tetap terlihat terbayar dari uang yang pembuktiannya dianulir.
                foreach ($header->details as $detail) {
                    // PPh tiap alokasi dibalik dari PPh invoice-nya SEBELUM baris itu dihapus, satu per satu,
                    // sehingga setiap hook delete() membaca invoice yang konsisten. Seluruhnya satu transaksi:
                    // bila satu invoice ditolak (tidak konsisten), tidak ada alokasi yang ikut dibatalkan.
                    if ((int) $detail->withholding_tax > 0 && isset($invoices[$detail->invoice_id])) {
                        $invoices[$detail->invoice_id]->sinkronPphDariCashReceipt(
                            'pembatalan penerimaan ' . $header->code,
                            -(int) $detail->withholding_tax,
                            false,
                            true,
                        );
                    }

                    // Penanda hapus disimpan tanpa event; sinkron status cukup dari delete().
                    $detail->fill([
                        'deleted_by'    => Auth::id(),
                        'delete_reason' => 'Bukti terima ' . $header->code . ' dibatalkan',
                    ])->saveQuietly();
                    $detail->delete();
                }

                $header->update([
                    'status'        => 'CANCELLED',
                    'cancelled_by'  => Auth::id(),
                    'cancelled_at'  => now(),
                    'cancel_reason' => $request->cancel_reason,
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        UserActivityLog::record('cash_receipt', 'cancel', 'Membatalkan penerimaan #' . $header->code, $header, $request->cancel_reason);

        return back()->with('success', 'Penerimaan ' . $header->code . ' dibatalkan.');
    }

    public function destroy($id)
    {
        $header = CashReceiptHeader::with('details')->findOrFail($id);

        if ($header->details->isNotEmpty()) {
            return back()->with('error', 'Penerimaan yang sudah punya alokasi tidak bisa dihapus — batalkan saja supaya jejaknya tetap ada.');
        }

        // Header batal / pernah punya alokasi (detail soft-delete tidak terbaca di relasi) adalah jejak
        // keuangan: tidak dihapus, dan berkas buktinya tidak boleh dilenyapkan.
        if ($header->isCancelled() || $header->details()->withTrashed()->exists()) {
            return back()->with('error', 'Penerimaan yang sudah dibatalkan atau pernah punya alokasi tidak bisa dihapus — jejaknya harus tetap ada.');
        }

        if ($header->proof_file) {
            Storage::disk('secure')->delete($header->proof_file);
        }

        $headerCode = $header->code;
        $header->delete();

        UserActivityLog::record('cash_receipt', 'delete', 'Menghapus penerimaan #' . $headerCode);

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
