<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceAttachment;
use App\Models\CashReceipt;
use App\Models\Project;
use App\Models\ProjectPoBast;
use App\Models\SourceFund;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\NotifyService;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InvoiceCashReceiptController extends Controller
{
    use Concerns\ScopesInvoiceVisibility;

    // Transisi status terkunci - lihat Concerns\TransitionsStatus.
    use Concerns\TransitionsStatus;

    // Urutan kunci No. PO -> BAST -> invoice - lihat Concerns\LocksPoNumbers.
    use Concerns\LocksPoNumbers;

    use \App\Http\Controllers\Concerns\CapsLargeLists;

    /** Role — penerima notifikasi tiap tahap workflow invoice. */
    private function usersWithRole(string ...$roles)
    {
        return User::whereHas('role', fn ($r) => $r->whereRaw(
            'LOWER(name) IN (' . implode(',', array_fill(0, count($roles), '?')) . ')',
            array_map('strtolower', $roles)
        ))->where('status', 'ACTIVE')->get();
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    /**
     * Batasi $query Invoice pada yang boleh dilihat user yang login.
     *
     * Dipindahkan apa adanya dari index() supaya Global Search memakai aturan
     * yang sama persis. Manager & Head cuma boleh lihat invoice milik PO yang
     * ada di Access Group mereka (pola sama seperti scopeByRole() di Cost
     * Request / BuildsCrWorklist); role finance/approval lain tetap lihat
     * semua invoice seperti sebelumnya.
     */
    // scopeVisibleTo() pindah ke Concerns\ScopesInvoiceVisibility - lihat
    // catatan di sana soal kenapa GlobalSearchController tidak boleh
    // me-resolve controller ini dari container.

    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        // Manager & Head cuma boleh lihat invoice milik PO yang ada di Access
        // Group mereka (pola sama seperti scopeByRole() di Cost Request /
        // BuildsCrWorklist) — role finance/approval lain tetap lihat semua
        // invoice seperti sebelumnya.
        $query = Invoice::with(['po.customer', 'po.manager', 'poNumber', 'bast', 'cashReceipts']);
        $this->scopeInvoiceVisibleTo($query);

        /*
        | Ringkasan dihitung lewat agregat, bukan dari koleksi baris.
        | Barisnya dibatasi (lihat Concerns\CapsLargeLists) supaya halaman tidak
        | pernah menggambar ribuan baris sekaligus ke DOM; kalau angka ringkasan
        | ikut dihitung dari koleksi yang sudah dipotong, stepper status akan
        | menampilkan jumlah yang lebih kecil dari kenyataan — salah diam-diam,
        | yang lebih buruk daripada halaman yang lambat.
        */
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        /*
        | Invoice yang lewat jatuh tempo ditampilkan sebagai OVERDUE (lihat
        | Invoice::display_status) — kolom status di DB tidak berubah. Supaya
        | angka stepper sama dengan isi tabel saat chip-nya diklik, jumlah
        | overdue per status dikurangkan dari Sent/Unpaid/Partially Paid.
        */
        $overdueByStatus = (clone $query)
            ->whereIn('status', Invoice::UNPAID_STATUSES)
            ->whereNotNull('actual_due_date')
            // actual_due_date di-cast 'date' (jam 00:00), jadi isOverdue()
            // sudah true sejak hari jatuh tempo itu sendiri — batasnya dibuat
            // '<=' supaya hitungan ini persis sama dengan label di tabel.
            ->whereDate('actual_due_date', '<=', now()->toDateString())
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $overdue = (int) $overdueByStatus->sum();

        $belumJatuhTempo = static function (string $status) use ($statusCounts, $overdueByStatus): int {
            return (int) ($statusCounts[$status] ?? 0) - (int) ($overdueByStatus[$status] ?? 0);
        };

        [$invoices, $truncated, $total] = $this->cappedRows($query, 'created_at');
        $maxRows = $this->maxRows;

        $summary = [
            'total'           => (int) $statusCounts->sum(),
            'draft'           => (int) ($statusCounts['DRAFT'] ?? 0),
            'submitted'       => (int) ($statusCounts['SUBMITTED'] ?? 0),
            'verified'        => (int) ($statusCounts['VERIFIED'] ?? 0),
            'approved'        => (int) ($statusCounts['APPROVED'] ?? 0),
            'sent'            => $belumJatuhTempo('SENT'),
            'received'        => $belumJatuhTempo('RECEIVED'),
            'partially_paid'  => $belumJatuhTempo('PARTIALLY_PAID'),
            'paid'            => (int) ($statusCounts['PAID'] ?? 0),
            'cancelled'       => (int) ($statusCounts['CANCELLED'] ?? 0),
            'overdue'         => $overdue,
        ];

        return view('invoice-cash-receipt.index', compact('invoices', 'summary', 'truncated', 'total', 'maxRows'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / STORE
    |--------------------------------------------------------------------------
    */

    /**
     * Selisih maksimum (rupiah) antara billed_amount invoice dan nilai BAST yang diterima form: 0 = harus
     * persis sama (keputusan bisnis P1). Toleransi Rp 1.000 hanya dipakai converter (invoice:link-bast,
     * invoice:split-synthetic-bast) untuk klasifikasi data lama, bukan oleh form.
     */
    private const BAST_TOLERANCE = 0;

    public function create()
    {
        // 1 Invoice = 1 BAST: daftar BAST yang belum punya invoice aktif (CANCELLED tidak dihitung).
        $availableBasts = $this->availableBasts();
        $poPayload      = $this->buildBastPayload($availableBasts);
        // Hanya Source Fund rekening PT (bukan rekening pribadi/lain) yang boleh
        // dipakai di Invoice/Cash Receipt — difilter di query, bukan disembunyikan
        // di frontend setelah data termuat.
        $sourceFunds = SourceFund::where('status', 'ACTIVE')
            ->whereRaw('LOWER(name) LIKE ?', ['%pt%'])
            ->orderBy('name')->get();

        return view('invoice-cash-receipt.create', compact('availableBasts', 'poPayload', 'sourceFunds'));
    }

    /**
     * BAST yang bisa ditagih: belum punya invoice aktif, tidak bergantung status
     * No. PO. $sertakanBastId dipakai saat edit supaya BAST milik invoice itu
     * sendiri tetap muncul. Diurutkan per No. PO (dikelompokkan di dropdown).
     */
    private function availableBasts(?int $sertakanBastId = null, ?int $kecualiInvoiceId = null)
    {
        return ProjectPoBast::with(['poNumber.project.customer', 'poNumber.project.attachments', 'poNumber.attachments', 'attachments'])
            ->where(function ($q) use ($sertakanBastId) {
                $q->whereDoesntHave('invoices', fn ($i) => $i->where('status', '!=', 'CANCELLED'));
                if ($sertakanBastId) {
                    $q->orWhere('id', $sertakanBastId);
                }
            })
            // BAST pada No. PO yang masih punya invoice lama aktif yang belum tertaut adalah BAST
            // sintetis hasil backfill: belum boleh ditagih (lihat ProjectPoNumber::punyaInvoiceLamaBelumTertaut()).
            // Draft lama yang sedang diedit dikecualikan supaya bisa memilih BAST No. PO-nya sendiri.
            ->whereDoesntHave('poNumber', fn ($po) => $po->whereHas('invoices', fn ($i) => $i
                ->where('status', '!=', 'CANCELLED')
                ->whereNull('project_po_bast_id')
                ->when($kecualiInvoiceId, fn ($q) => $q->where('id', '!=', $kecualiInvoiceId))))
            ->get()
            ->sortBy(fn ($b) => [$b->poNumber->po_number ?? '', $b->tanggal_bast, $b->id])
            ->values();
    }

    /**
     * Payload JSON per BAST (kunci = id BAST): info customer/PO, nilai BAST, &
     * lampiran PO/BAST (dipakai JS form create/edit tanpa AJAX round-trip terpisah).
     */
    private function buildBastPayload($basts): array
    {
        $poPayload = $this->buildPoPayload($basts->pluck('poNumber')->filter()->unique('id'));

        $payload = [];
        foreach ($basts as $bast) {
            $po = $poPayload[$bast->project_po_number_id] ?? null;
            if (!$po) {
                continue;
            }
            $payload[$bast->id] = array_merge($po, [
                'no_bast'              => $bast->no_bast,
                'nilai_bast'           => (int) $bast->nilai_bast,
                'nilai_bast_formatted' => number_format((int) $bast->nilai_bast, 0, ',', '.'),
                'bast_attachments'     => $bast->attachments->map(fn ($a) => [
                    'title' => $a->title ?? $a->file_name,
                    'path'  => $a->file_path,
                ])->values(),
            ]);
        }

        return $payload;
    }

    /**
     * Payload JSON per No. PO: info customer/PO & lampiran PO.
     */
    private function buildPoPayload($poNumbers): array
    {
        $payload = [];
        foreach ($poNumbers as $poNumber) {
            $project = $poNumber->project;
            $payload[$poNumber->id] = [
                'po_number'         => $poNumber->po_number,
                'project_code'      => $project->code ?? '-',
                'project_name'      => $project->name ?? '-',
                'customer'          => $project->customer->name ?? '-',
                'customer_initial'  => $project->customer->initial ?? '-',
                'term_of_payment'   => $project->term_of_payment ?: '-',
                'nominal'           => (int) $poNumber->nominal,
                'nominal_formatted' => $poNumber->nominal_formatted,
                // Lampiran terpisah: (a) PO header/project-level, (b) No. PO (non-BAST).
                'po_attachments'    => $project?->attachments->where('type', 'general')->values()->map(fn ($a) => [
                    'title' => $a->title ?? $a->file_name,
                    'path'  => $a->file_path,
                ]) ?? [],
                // Filter dari relasi 'attachments' yang sudah eager-loaded (hindari query per-PO).
                'no_po_attachments' => $poNumber->attachments->where('type', '!=', 'po_number_bast')->values()->map(fn ($a) => [
                    'title' => $a->title ?? $a->file_name,
                    'path'  => $a->file_path,
                ]),
            ];
        }
        return $payload;
    }

    /**
     * Urutan kunci di SEMUA jalur penagihan/penghapusan BAST: (1) baris No. PO, (2) baris BAST,
     * (3) baru invoice (hanya per id, tidak pernah range/gap lock). Urutan yang sama mencegah deadlock.
     *
     * Harus dipanggil sebagai pernyataan PERTAMA di DB::transaction(): snapshot pembacaan MySQL baru
     * terbentuk pada pembacaan biasa pertama, sehingga cek invoice di bawah (pembacaan biasa) melihat
     * invoice yang baru di-commit transaksi lain yang tadi kita tunggu kuncinya. Karena itu $poNumberId
     * dicari SEBELUM transaksi (poIdDariBast) atau diambil dari invoice yang sudah dimuat.
     *
     * Memastikan BAST belum dipakai invoice aktif lain (kecuali $kecualiInvoiceId) dan No. PO-nya tidak
     * punya invoice lama aktif yang belum tertaut.
     */
    private function kunciBastUntukTagihan(int $poNumberId, int $bastId, ?int $kecualiInvoiceId = null): ProjectPoBast
    {
        $poNumber = \App\Models\ProjectPoNumber::whereKey($poNumberId)->lockForUpdate()->first();   // 1) No. PO
        $bast     = ProjectPoBast::whereKey($bastId)->lockForUpdate()->first();                       // 2) BAST
        if (!$poNumber || !$bast || (int) $bast->project_po_number_id !== (int) $poNumber->id) {
            throw new \DomainException('BAST tidak ditemukan atau sudah dihapus.');
        }

        if ($poNumber->punyaInvoiceLamaBelumTertaut($kecualiInvoiceId)) {
            throw new \DomainException(
                'No. PO ' . $poNumber->po_number . ' masih punya invoice lama yang belum tertaut ke BAST. '
                . 'Tautkan dulu (invoice:link-bast) sebelum membuat invoice per BAST.'
            );
        }

        $lain = $bast->activeInvoices()
            ->when($kecualiInvoiceId, fn ($q) => $q->where('id', '!=', $kecualiInvoiceId))
            ->first();
        if ($lain) {
            throw new \DomainException('BAST ' . $bast->no_bast . ' sudah ditagih pada invoice ' . $lain->code . '.');
        }

        return $bast;
    }

    /** No. PO milik sebuah BAST — dibaca SEBELUM transaksi dimulai (lihat kunciBastUntukTagihan). */
    private function poIdDariBast(int $bastId): ?int
    {
        return ProjectPoBast::whereKey($bastId)->value('project_po_number_id');
    }

    /** Nilai ditagih (billed_amount, tanpa PPN) harus sama dengan nilai BAST, toleransi kecil. */
    private function pastikanSesuaiBast(Invoice $invoice, ProjectPoBast $bast): void
    {
        $selisih = abs((int) $invoice->billed_amount - (int) $bast->nilai_bast);
        if ($selisih > self::BAST_TOLERANCE) {
            throw new \DomainException(
                'Nilai ditagih Rp ' . number_format((int) $invoice->billed_amount, 0, ',', '.')
                . ' tidak sesuai nilai BAST ' . $bast->no_bast . ' Rp ' . number_format((int) $bast->nilai_bast, 0, ',', '.')
                . ' (selisih Rp ' . number_format($selisih, 0, ',', '.') . '). Nilai ditagih harus sama persis dengan nilai BAST.'
            );
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_po_bast_id' => ['required', Rule::exists('project_po_basts', 'id')->whereNull('deleted_at')],
            'source_fund_id'     => 'required|exists:source_funds,id',
            'faktur_number'      => 'required|string|max:255',
            'payment_term_type'  => 'required|in:' . implode(',', Invoice::PAYMENT_TERM_TYPES),
            'termin_number'      => 'nullable|integer|min:1',
            'invoice_date'       => 'required|date',
            'due_date'           => 'required|date|after_or_equal:invoice_date',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            // MN-07 — PPh yang dipotong di muka. Dikirim sebagai teks berformat
            // ribuan dari form, jadi dinormalkan lewat normalizeMoney() di
            // bawah, bukan divalidasi sebagai numeric.
            'withholding_tax'    => 'nullable|string',
            'billing_percentage' => 'nullable|numeric|min:0.01|max:100',
            'remark'             => 'nullable|string|max:2000',
            'details'                    => 'required|array|min:1',
            'details.*.description'     => 'required|string|max:255',
            'details.*.unit_price'      => 'required',
            'details.*.qty'             => 'required|integer|min:1',
            'details.*.unit'            => 'nullable|string|max:50',
            'details.*.deduction'       => 'nullable',
            'attachments'                => 'nullable|array',
            'attachments.*.title'        => 'nullable|string|max:255',
            'attachments.*.file'         => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $poId = $this->poIdDariBast((int) $request->project_po_bast_id);
        if (!$poId) {
            return back()->withInput()->with('error', 'BAST tidak ditemukan atau sudah dihapus.');
        }

        try {
            $invoice = DB::transaction(function () use ($request, $poId) {
                $bast     = $this->kunciBastUntukTagihan($poId, (int) $request->project_po_bast_id);
                $poNumber = $bast->poNumber;

                $code = Invoice::generateCode((int) $poNumber->project_id, \Carbon\Carbon::parse($request->invoice_date));

                $invoice = Invoice::create([
                    'code'                 => $code,
                    'faktur_number'        => $request->faktur_number,
                    'po_id'                => $poNumber->project_id,
                    'project_po_number_id' => $poNumber->id,
                    'project_po_bast_id'   => $bast->id,
                    'source_fund_id'       => $request->source_fund_id,
                    'payment_term_type'    => $request->payment_term_type,
                    'termin_number'        => $request->payment_term_type === 'Termin' ? $request->termin_number : null,
                    'invoice_date'         => $request->invoice_date,
                    'due_date'             => $request->due_date,
                    'tax_rate'             => $request->tax_rate ?? 11,
                    'withholding_tax'      => $this->normalizeMoney($request->withholding_tax),
                    'billing_percentage'   => $request->billing_percentage,
                    'remark'               => $request->remark,
                    'status'               => 'DRAFT',
                    'created_by'           => Auth::id(),
                ]);

                $this->syncDetails($invoice, $request);
                $invoice->recalculateTotals();

                // Dicek sebelum lampiran disimpan ke disk, supaya penolakan tidak meninggalkan file yatim.
                $this->pastikanSesuaiBast($invoice, $bast);
                $this->storeAttachments($invoice, $request);

                // Status No. PO dihitung dari seluruh BAST-nya (bukan sisa nominal).
                $poNumber->syncInvoiceStatus();

                return $invoice;
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        UserActivityLog::record('invoice', 'create', 'Membuat Invoice #' . $invoice->code, $invoice);

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
        $invoice = $this->findVisibleInvoice($id, ['details', 'attachments', 'po.customer']);

        if ($invoice->status !== 'DRAFT') {
            return redirect('/invoice-cash-receipt/' . $id)
                ->with('error', 'Hanya invoice berstatus DRAFT yang dapat diedit.');
        }

        // BAST yang belum ditagih + BAST milik invoice ini sendiri.
        $availableBasts = $this->availableBasts($invoice->project_po_bast_id, $invoice->id);
        $poPayload      = $this->buildBastPayload($availableBasts);
        // Hanya Source Fund rekening PT (bukan rekening pribadi/lain) yang boleh
        // dipakai di Invoice/Cash Receipt — difilter di query, bukan disembunyikan
        // di frontend setelah data termuat.
        $sourceFunds = SourceFund::where('status', 'ACTIVE')
            ->whereRaw('LOWER(name) LIKE ?', ['%pt%'])
            ->orderBy('name')->get();

        return view('invoice-cash-receipt.edit', compact('invoice', 'availableBasts', 'poPayload', 'sourceFunds'));
    }

    public function update(Request $request, $id)
    {
        $invoice = $this->findVisibleInvoice($id);

        if ($invoice->status !== 'DRAFT') {
            return back()->with('error', 'Hanya invoice berstatus DRAFT yang dapat diedit.');
        }

        $request->validate([
            'project_po_bast_id' => ['required', Rule::exists('project_po_basts', 'id')->whereNull('deleted_at')],
            'source_fund_id'     => 'required|exists:source_funds,id',
            'faktur_number'      => 'required|string|max:255',
            'payment_term_type'  => 'required|in:' . implode(',', Invoice::PAYMENT_TERM_TYPES),
            'termin_number'      => 'nullable|integer|min:1',
            'invoice_date'       => 'required|date',
            'due_date'           => 'required|date|after_or_equal:invoice_date',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            // MN-07 — PPh yang dipotong di muka. Dikirim sebagai teks berformat
            // ribuan dari form, jadi dinormalkan lewat normalizeMoney() di
            // bawah, bukan divalidasi sebagai numeric.
            'withholding_tax'    => 'nullable|string',
            'billing_percentage' => 'nullable|numeric|min:0.01|max:100',
            'remark'             => 'nullable|string|max:2000',
            'details'                    => 'required|array|min:1',
            'details.*.description'     => 'required|string|max:255',
            'details.*.unit_price'      => 'required',
            'details.*.qty'             => 'required|integer|min:1',
            'details.*.unit'            => 'nullable|string|max:50',
            'details.*.deduction'       => 'nullable',
            'attachments'                => 'nullable|array',
            'attachments.*.title'        => 'nullable|string|max:255',
            'attachments.*.file'         => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments'         => 'nullable|array',
        ]);

        $poId = $this->poIdDariBast((int) $request->project_po_bast_id);
        if (!$poId) {
            return back()->withInput()->with('error', 'BAST tidak ditemukan atau sudah dihapus.');
        }

        try {
            DB::transaction(function () use ($request, $invoice, $poId) {
                // Update lintas No. PO: kunci No. PO asal dan tujuan berurutan id naik (mencegah deadlock silang),
                // baru BAST tujuan (kunciBastUntukTagihan mengunci ulang No. PO tujuan — aman dalam transaksi yang sama).
                $this->kunciNoPo($invoice->project_po_number_id, $poId);
                $bast          = $this->kunciBastUntukTagihan($poId, (int) $request->project_po_bast_id, $invoice->id);
                $newPoNumber   = $bast->poNumber;
                $oldPoNumberId = $invoice->project_po_number_id;

                $invoice->update([
                    'faktur_number'        => $request->faktur_number,
                    'po_id'                => $newPoNumber->project_id,
                    'project_po_number_id' => $newPoNumber->id,
                    'project_po_bast_id'   => $bast->id,
                    'source_fund_id'       => $request->source_fund_id,
                    'payment_term_type'    => $request->payment_term_type,
                    'termin_number'        => $request->payment_term_type === 'Termin' ? $request->termin_number : null,
                    'invoice_date'         => $request->invoice_date,
                    'due_date'             => $request->due_date,
                    'tax_rate'             => $request->tax_rate ?? 11,
                    'withholding_tax'      => $this->normalizeMoney($request->withholding_tax),
                    'billing_percentage'   => $request->billing_percentage,
                    'remark'               => $request->remark,
                ]);

                $invoice->details()->delete();
                $this->syncDetails($invoice, $request);
                $invoice->recalculateTotals();

                // Dicek sebelum berkas di disk disentuh, supaya penolakan membatalkan semuanya.
                $this->pastikanSesuaiBast($invoice, $bast);

                foreach ((array) $request->input('delete_attachments', []) as $attId) {
                    $att = InvoiceAttachment::where('invoice_id', $invoice->id)->find($attId);
                    if ($att) {
                        Storage::disk('secure')->delete($att->file_path);
                        $att->delete();
                    }
                }
                $this->storeAttachments($invoice, $request);

                // Sinkron status kedua No. PO (lama & baru) dari BAST-nya.
                if ($oldPoNumberId && $oldPoNumberId !== $newPoNumber->id) {
                    \App\Models\ProjectPoNumber::find($oldPoNumberId)?->syncInvoiceStatus();
                }
                $newPoNumber->syncInvoiceStatus();
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        UserActivityLog::record('invoice', 'update', 'Memperbarui Invoice #' . $invoice->code, $invoice);

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
                'unit'        => $row['unit'] ?? null,
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

            $path = $file->store('invoice_attachments', 'secure');
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

    /**
     * Manager & Head hanya boleh melihat invoice milik PO yang ada di Access
     * Group mereka — cermin dari scoping index()/exportList(). Role finance/
     * approval lain tetap lihat semua seperti sebelumnya.
     *
     * Dipanggil di SETIAP aksi yang menerima {id}, bukan hanya show(). Dulu
     * hanya show() dan printView() yang memanggilnya, sehingga daftar invoice
     * memang tersaring tapi /invoice-cash-receipt/{id}/edit tetap terbuka bagi
     * siapa pun yang mengetik id-nya langsung — penyaringan di daftar jadi
     * tidak ada artinya.
     */
    private function assertCanViewInvoice(Invoice $invoice): void
    {
        $user = Auth::user();
        if ($user->hasRole(['manager', 'head'])) {
            $projectIds = $user->accessibleProjectIds()->all();
            abort_unless(in_array($invoice->po_id, $projectIds), 403);
        }
    }

    /**
     * Ambil invoice + pastikan user berhak. Satu jalan masuk untuk semua aksi
     * ber-{id}, supaya tidak ada lagi metode yang lupa memeriksa.
     */
    private function findVisibleInvoice($id, array $with = []): Invoice
    {
        $invoice = Invoice::with($with)->findOrFail($id);
        $this->assertCanViewInvoice($invoice);

        return $invoice;
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        /*
        | Relasi yang dibutuhkan TIMELINE diambil dari kelasnya sendiri
        | (InvoiceTimeline::relasiDibutuhkan()), bukan diketik ulang di sini.
        |
        | Daftar lama melewatkan 'receivedBy' dan 'deliveryNote' — keduanya
        | dibaca saat menyusun peristiwa RECEIVED, jadi halaman detail invoice
        | yang sudah diterima mengeluarkan dua query tambahan per pemuatan tanpa
        | ada yang menyadarinya. Itu tepat jenis penyimpangan yang terjadi kalau
        | "apa yang dibaca view" dan "apa yang dimuat controller" dipelihara di
        | dua tempat berbeda.
        */
        $invoice = Invoice::with(array_merge([
            'po.customer', 'po.attachments',
            'poNumber.attachments',
            'sourceFund.bank',
            'details', 'attachments.inputBy',
        ], \App\Support\InvoiceTimeline::relasiDibutuhkan()))->findOrFail($id);

        $this->assertCanViewInvoice($invoice);

        // Hanya Source Fund rekening PT (bukan rekening pribadi/lain) yang boleh
        // dipakai di Invoice/Cash Receipt — difilter di query, bukan disembunyikan
        // di frontend setelah data termuat.
        $sourceFunds = SourceFund::where('status', 'ACTIVE')
            ->whereRaw('LOWER(name) LIKE ?', ['%pt%'])
            ->orderBy('name')->get();

        return view('invoice-cash-receipt.show', compact('invoice', 'sourceFunds'));
    }

    /*
    |--------------------------------------------------------------------------
    | MODAL SURAT JALAN (dibuka dari tab Log detail invoice)
    |--------------------------------------------------------------------------
    | Mengembalikan potongan HTML, bukan halaman penuh: dari detail invoice,
    | Surat Jalan hanya perlu dilihat sekilas, dan pindah halaman membuat
    | pengguna kehilangan posisi di tab Log.
    |
    | Dua pembatas yang tidak boleh dilepas:
    |   1. Hak lihat invoice diperiksa dengan aturan yang sama seperti show().
    |   2. Surat Jalan yang boleh dibuka hanya yang memang terpaut pada invoice
    |      tersebut — bukan sembarang id Surat Jalan dari URL.
    | Nilai tagihan sengaja tidak ditampilkan di modal ini.
    */

    public function deliveryNoteModal($id, $dnId)
    {
        $invoice = Invoice::with('deliveryNote')->findOrFail($id);

        $this->assertCanViewInvoice($invoice);

        if (! $invoice->deliveryNote || (string) $invoice->deliveryNote->id !== (string) $dnId) {
            abort(404);
        }

        $deliveryNote = DeliveryNote::with([
            'customer',
            'items.invoice:id,code,status,project_po_number_id,po_id',
            'items.invoice.po:id,code,name',
            'items.invoice.poNumber:id,po_number,project_id',
            'logs.attachments',
            'createdBy',
            'receivedBy',
        ])->findOrFail($dnId);

        return view('invoice-cash-receipt._delivery_note_modal', compact('deliveryNote', 'invoice'));
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT
    |--------------------------------------------------------------------------
    */

    public function printView($id)
    {
        $invoice = Invoice::with([
            'po.customer',
            'po.attachments',
            'poNumber',
            'sourceFund.bank',
            'details',
            'attachments',
        ])->findOrFail($id);

        $this->assertCanViewInvoice($invoice);

        return view('invoice-cash-receipt.print', compact('invoice'));
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $invoice = $this->findVisibleInvoice($id, ['attachments']);

        if ($invoice->status !== 'DRAFT') {
            return back()->with('error', 'Hanya invoice berstatus DRAFT yang dapat dihapus.');
        }

        $invoiceCode = $invoice->code;

        DB::transaction(function () use ($invoice) {
            $this->kunciNoPo($invoice->project_po_number_id);

            foreach ($invoice->attachments as $att) {
                Storage::disk('secure')->delete($att->file_path);
            }

            $poNumberId = $invoice->project_po_number_id;
            $invoice->delete();

            // Hitung ulang SETELAH invoice ini terhapus, supaya sisa nominal No. PO
            // yang lain (Termin 1, 2, 3, ...) ikut terhitung benar.
            if ($poNumberId) {
                \App\Models\ProjectPoNumber::find($poNumberId)?->syncInvoiceStatus();
            }
        });

        UserActivityLog::record('invoice', 'delete', 'Menghapus Invoice #' . $invoiceCode);

        return redirect('/invoice-cash-receipt')->with('success', 'Invoice berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: SUBMIT — DRAFT -> SUBMITTED
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $invoice = $this->findVisibleInvoice($id);

        if ($invoice->status !== 'DRAFT') {
            return back()->with('error', 'Hanya invoice berstatus DRAFT yang dapat disubmit.');
        }

        // Transisi terkunci: klik ganda / dua pengguna tidak lagi menggandakan
        // notifikasi atau menimpa submitted_by. BAST dicek lagi: jangan sampai
        // sudah dipakai invoice aktif lain atau sudah dihapus.
        try {
            $berhasil = DB::transaction(function () use ($invoice) {
                if ($invoice->project_po_bast_id) {
                    $this->kunciBastUntukTagihan((int) $invoice->project_po_number_id, (int) $invoice->project_po_bast_id, $invoice->id);
                }

                return $this->transitionStatus(Invoice::class, $invoice->id, 'DRAFT', [
                    'status'       => 'SUBMITTED',
                    'submitted_by' => Auth::id(),
                    'submitted_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $berhasil) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        NotifyService::send(
            $this->usersWithRole('accounting', 'finance_manager'),
            'Invoice menunggu verifikasi',
            Auth::user()->name . ' mengajukan Invoice ' . $invoice->code . ' untuk diverifikasi.',
            route('invoice-cash-receipt.index'),
            'workflow'
        );

        UserActivityLog::record('invoice', 'submit', 'Mengajukan Invoice #' . $invoice->code, $invoice);

        return back()->with('success', 'Invoice ' . $invoice->code . ' berhasil disubmit.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: VERIFY (Finance Manager) — SUBMITTED -> VERIFIED
    |--------------------------------------------------------------------------
    */

    public function verify($id)
    {
        $invoice = $this->findVisibleInvoice($id);

        if ($invoice->status !== 'SUBMITTED') {
            return back()->with('error', 'Hanya invoice berstatus SUBMITTED yang dapat diverifikasi.');
        }

        if (! $this->transitionStatus(Invoice::class, $invoice->id, 'SUBMITTED', [
            'status'      => 'VERIFIED',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ])) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        NotifyService::send(
            // Approval invoice milik Finance Manager; Approver tidak lagi
            // diberi tahu karena tahap ini bukan tugasnya.
            $this->usersWithRole('finance_manager'),
            'Invoice menunggu approval',
            Auth::user()->name . ' memverifikasi Invoice ' . $invoice->code . ' — menunggu approval.',
            route('invoice-cash-receipt.index'),
            'workflow'
        );

        UserActivityLog::record('invoice', 'verify', 'Memverifikasi Invoice #' . $invoice->code, $invoice);

        return back()->with('success', 'Invoice ' . $invoice->code . ' berhasil diverifikasi.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: APPROVE (Finance Manager) — VERIFIED -> APPROVED
    |--------------------------------------------------------------------------
    */

    public function approve($id)
    {
        $invoice = $this->findVisibleInvoice($id);

        if ($invoice->status !== 'VERIFIED') {
            return back()->with('error', 'Hanya invoice berstatus VERIFIED yang dapat di-approve.');
        }

        if (! $this->transitionStatus(Invoice::class, $invoice->id, 'VERIFIED', [
            'status'      => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ])) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        if ($invoice->submittedBy) {
            NotifyService::send(
                [$invoice->submittedBy],
                'Invoice disetujui',
                'Invoice ' . $invoice->code . ' telah di-approve.',
                route('invoice-cash-receipt.index'),
                'workflow'
            );
        }

        UserActivityLog::record('invoice', 'approve', 'Menyetujui Invoice #' . $invoice->code, $invoice);

        return back()->with('success', 'Invoice ' . $invoice->code . ' berhasil di-approve.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: REVISE — mundur satu tahap
    |   SUBMITTED -> DRAFT     (oleh Finance Manager / Accounting)
    |   VERIFIED  -> SUBMITTED (pasangan mundur dari approve(), kini Finance
    |                           Manager — Approver masih ikut di route revise)
    |--------------------------------------------------------------------------
    */

    public function revise(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:1000']);

        $invoice = $this->findVisibleInvoice($id);

        $map = [
            'SUBMITTED' => 'DRAFT',
            'VERIFIED'  => 'SUBMITTED',
            'CANCELLED' => 'DRAFT',
        ];

        if (!isset($map[$invoice->status])) {
            return back()->with('error', 'Invoice berstatus ' . $invoice->status . ' tidak dapat direvisi.');
        }

        // CANCELLED -> DRAFT khusus Finance Manager (sama seperti syarat
        // Cancel & Revise dari SENT — lihat cancel()).
        if ($invoice->status === 'CANCELLED' && !Auth::user()->hasRole(['superadmin', 'finance_manager'])) {
            abort(403, 'Hanya Finance Manager yang dapat merevisi invoice CANCELLED ke DRAFT.');
        }

        $oldStatus = $invoice->status;

        try {
            $berhasil = DB::transaction(function () use ($invoice, $map, $oldStatus, $request) {
                if ($oldStatus === 'CANCELLED' && $invoice->project_po_bast_id) {
                    // CANCELLED -> DRAFT mengambil kembali BAST-nya: pastikan belum dipakai invoice aktif lain.
                    $this->kunciBastUntukTagihan((int) $invoice->project_po_number_id, (int) $invoice->project_po_bast_id, $invoice->id);
                } elseif ($oldStatus === 'CANCELLED' && $invoice->project_po_number_id) {
                    // Invoice lama yang belum tertaut ke BAST: kunci No. PO dulu, lalu tolak bila No. PO ini
                    // sudah punya BAST yang ditagih invoice aktif lain (penagihan per BAST sudah berjalan di sana).
                    $this->kunciNoPo($invoice->project_po_number_id);
                    $dipakai = Invoice::whereIn('project_po_bast_id', ProjectPoBast::where('project_po_number_id', $invoice->project_po_number_id)->select('id'))
                        ->where('status', '!=', 'CANCELLED')
                        ->where('id', '!=', $invoice->id)
                        ->first();
                    if ($dipakai) {
                        throw new \DomainException(
                            'Invoice ini tidak dapat dikembalikan ke DRAFT: No. PO-nya sudah punya BAST yang ditagih pada invoice '
                            . $dipakai->code . '. Buat invoice baru atas BAST yang belum ditagih.'
                        );
                    }
                }

                $hasil = $this->transitionStatus(Invoice::class, $invoice->id, $oldStatus, [
                    'status' => $map[$oldStatus],
                    'remark' => trim(($invoice->remark ? $invoice->remark . "\n\n" : '') . '[REVISI] ' . $request->notes),
                ]);

                if ($hasil && $oldStatus === 'CANCELLED') {
                    $invoice->poNumber?->syncInvoiceStatus();
                }

                return $hasil;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $berhasil) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        // SUBMITTED->DRAFT: kembalikan ke yang submit. VERIFIED->SUBMITTED: kembalikan ke yang verifikasi.
        // CANCELLED->DRAFT: kembalikan ke yang cancel.
        $recipient = match ($oldStatus) {
            'SUBMITTED' => $invoice->submittedBy,
            'VERIFIED'  => $invoice->verifiedBy,
            'CANCELLED' => $invoice->cancelledBy,
            default     => null,
        };
        if ($recipient) {
            NotifyService::send(
                [$recipient],
                'Invoice perlu direvisi',
                'Invoice ' . $invoice->code . ' dikembalikan untuk direvisi: ' . $request->notes,
                route('invoice-cash-receipt.index'),
                'workflow'
            );
        }

        UserActivityLog::record('invoice', 'revise', 'Meminta revisi Invoice #' . $invoice->code, $invoice, $request->notes);

        return back()->with('success', 'Invoice ' . $invoice->code . ' dikembalikan untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: CANCEL
    |--------------------------------------------------------------------------
    */

    /**
     * Tandai invoice sudah diterima customer — mengisi received_at/received_by
     * yang sudah ada di kolomnya tapi belum pernah ditulis controller manapun.
     * Men-trigger Invoice::syncActualDueDate() lewat model event (booted()).
     */
    public function markReceived($id)
    {
        $invoice = $this->findVisibleInvoice($id);

        if (!in_array($invoice->status, ['SENT', 'RECEIVED'])) {
            return back()->with('error', 'Hanya invoice berstatus SENT yang dapat ditandai diterima.');
        }
        if ($invoice->received_at) {
            return back()->with('error', 'Invoice ini sudah ditandai diterima.');
        }

        $invoice->update([
            'status'      => 'RECEIVED',
            'received_by' => Auth::id(),
            'received_at' => now(),
        ]);

        UserActivityLog::record('invoice', 'process', 'Menandai Invoice #' . $invoice->code . ' diterima pelanggan', $invoice);

        return back()->with('success', 'Invoice ' . $invoice->code . ' ditandai diterima. Jatuh tempo aktual dihitung otomatis.');
    }

    public function storeComment(Request $request, $id)
    {
        $request->validate(['comment' => 'required|string|max:2000']);

        $invoice = Invoice::findOrFail($id);
        $invoice->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $request->comment,
        ]);

        return back()->with('success', 'Komentar untuk invoice ' . $invoice->code . ' tersimpan di Log & Approval.');
    }

    public function cancel(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:1000',
            'revise'        => 'nullable|boolean',
        ]);

        $invoice = $this->findVisibleInvoice($id);

        if (in_array($invoice->status, ['PAID', 'CANCELLED'])) {
            return back()->with('error', 'Invoice berstatus ' . $invoice->status . ' tidak dapat dibatalkan.');
        }

        // "Cancel & Revise" cuma tersedia untuk invoice SENT, khusus Finance
        // Manager — jalur cancel biasa (statuses lain / role lain) tetap
        // berlaku seperti sekarang.
        $revise = (bool) $request->boolean('revise');
        if ($revise) {
            if ($invoice->status !== 'SENT') {
                return back()->with('error', 'Cancel & Revise hanya berlaku untuk invoice berstatus SENT.');
            }
            if (!Auth::user()->hasRole(['superadmin', 'finance_manager'])) {
                abort(403, 'Hanya Finance Manager yang dapat melakukan Cancel & Revise.');
            }
        }

        try {
        DB::transaction(function () use ($invoice, $request, $revise) {
            /*
            | Kunci No. PO dulu, baru invoice (per id) - urutan yang sama dengan
            | storeCashReceipt/settleCashReceipt/destroyCashReceipt dan semua jalur
            | BAST (lihat kunciNoPo). Penguncian invoice itu sendiri tetap perlu:
            | tanpanya pembayaran yang dicatat bersamaan bisa tetap masuk ke invoice
            | yang baru saja dibatalkan.
            */
            $this->kunciNoPo($invoice->project_po_number_id);
            $terkunci = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            if (! $terkunci || in_array($terkunci->status, ['PAID', 'CANCELLED'], true)
                || ($revise && $terkunci->status !== 'SENT')) {
                throw new \DomainException('Status sudah berubah — muat ulang halaman.');
            }

            $invoice->update([
                'status'        => 'CANCELLED',
                'cancelled_by'  => Auth::id(),
                'cancelled_at'  => now(),
                'cancel_reason' => $request->cancel_reason,
            ]);

            // Keluarkan dari relasi Surat Jalan — TIDAK hard delete Surat
            // Jalannya, cukup lepas tautannya ke invoice yang dibatalkan ini.
            \App\Models\DeliveryNoteItem::where('invoice_id', $invoice->id)->delete();

            if ($revise) {
                // SENT -> CANCELLED -> DRAFT: histori cancel (cancelled_by/at/
                // cancel_reason) DIPERTAHANKAN sebagai jejak kenapa invoice ini
                // direvisi, statusnya balik ke DRAFT supaya bisa diedit & terbit ulang.
                $invoice->update(['status' => 'DRAFT']);
            }

            // Sinkron SETELAH status akhir invoice (CANCELLED atau DRAFT) — pada
            // Cancel & Revise BAST-nya masih dipegang invoice ini.
            if ($invoice->project_po_number_id) {
                \App\Models\ProjectPoNumber::find($invoice->project_po_number_id)?->syncInvoiceStatus();
            }
        });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $revise
            ? 'Invoice ' . $invoice->code . ' dibatalkan dan dikembalikan ke DRAFT untuk direvisi.'
            : 'Invoice ' . $invoice->code . ' dibatalkan.';

        UserActivityLog::record('invoice', 'cancel', 'Membatalkan Invoice #' . $invoice->code, $invoice, $request->notes);

        return back()->with('success', $message);
    }

    /*
    |--------------------------------------------------------------------------
    | CASH RECEIPT
    |--------------------------------------------------------------------------
    */

    public function storeCashReceipt(Request $request, $id)
    {
        $invoice = $this->findVisibleInvoice($id);

        if (!in_array($invoice->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'])) {
            return back()->with('error', 'Cash Receipt hanya bisa ditambahkan untuk invoice berstatus SENT, RECEIVED, atau PARTIALLY PAID.');
        }

        $request->validate([
            'received_date'  => 'required|date',
            // 'required' saja tidak cukup: normalizeMoney() membuang semua
            // karakter non-angka, jadi "abc" lolos validasi dan menjadi 0 -
            // pembayaran bernilai nol yang tetap tercatat sebagai pembayaran.
            'amount'         => 'required|regex:/[0-9]/',
            'source_fund_id' => 'nullable|exists:source_funds,id',
            'proof_file'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $amount = $this->normalizeMoney($request->amount);

        if ($amount < 1) {
            return back()->with('error', 'Nominal pembayaran harus lebih dari nol.');
        }

        $proofPath = null;
        if ($request->hasFile('proof_file') && $request->file('proof_file')->isValid()) {
            $proofPath = $request->file('proof_file')->store('cash_receipt_proofs', 'secure');
        }

        /*
        | Pemeriksaan "melebihi sisa tagihan" dilakukan DI DALAM transaksi
        | dengan baris invoice terkunci. Sebelumnya pemeriksaan dan penyimpanan
        | terpisah: dua pembayaran yang dicatat bersamaan sama-sama melihat
        | sisa tagihan penuh, keduanya lolos, dan invoice menerima lebih dari
        | nilainya sendiri - lalu statusnya melompat ke PAID dengan angka yang
        | tidak bisa dijelaskan.
        */
        $gagal = DB::transaction(function () use ($invoice, $amount, $request, $proofPath) {
            // No. PO dulu, baru invoice: pembayaran memicu sinkron status No. PO (syncPaymentStatus),
            // jadi urutannya harus sama dengan jalur BAST/cancel agar tidak berpapasan.
            $this->kunciNoPo($invoice->project_po_number_id);
            $terkunci = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            // Status diperiksa ulang di dalam kunci: invoice bisa saja baru
            // dibatalkan sejak pemeriksaan di atas.
            if (! in_array($terkunci->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'], true)) {
                return 'Cash Receipt hanya bisa ditambahkan untuk invoice berstatus SENT, RECEIVED, atau PARTIALLY PAID.';
            }

            if ($amount > $terkunci->remainingBalance()) {
                return 'Nominal melebihi sisa tagihan (' . number_format($terkunci->remainingBalance(), 0, ',', '.') . ').';
            }

            CashReceipt::create([
                'invoice_id'      => $terkunci->id,
                'received_date'   => $request->received_date,
                'amount'          => $amount,
                'source_fund_id'  => $request->source_fund_id,
                'proof_file'      => $proofPath,
                'notes'           => $request->notes,
                'input_by'        => Auth::id(),
            ]);

            return null;
        });

        if ($gagal) {
            // Berkas bukti yang terlanjur tersimpan ikut dibersihkan supaya
            // tidak menumpuk sebagai berkas yatim.
            if ($proofPath) {
                Storage::disk('secure')->delete($proofPath);
            }

            return back()->with('error', $gagal);
        }

        UserActivityLog::record('cash_receipt', 'pay', 'Mencatat Cash Receipt untuk Invoice #' . $invoice->code, $invoice);

        return back()->with('success', 'Cash Receipt berhasil dicatat.');
    }

    /**
     * Settle selisih kecil Cash Receipt akibat pemotongan PPh Pasal 4(2) (2%)
     * oleh customer — isi nominal PPh pada Cash Receipt terkait supaya
     * cash-in + PPh = full payment, tanpa perlu uang tambahan masuk.
     */
    public function settleCashReceipt(Request $request, $id, $crId)
    {
        $invoice = $this->findVisibleInvoice($id);
        $cr = CashReceipt::where('invoice_id', $id)->findOrFail($crId);

        if (!in_array($invoice->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'])) {
            return back()->with('error', 'Settle PPh hanya bisa dilakukan untuk invoice yang belum lunas.');
        }

        $request->validate([
            'withholding_tax' => 'required',
        ]);

        $tax = $this->normalizeMoney($request->withholding_tax);

        /*
        | Dihitung di dalam kunci invoice: dua settle PPh bersamaan pada receipt
        | berbeda dulu sama-sama lolos batas maxTax, sehingga total diterima
        | bisa melampaui tagihan. Rumus dan pesan tidak berubah.
        */
        $error = DB::transaction(function () use ($invoice, $cr, $tax) {
            $this->kunciNoPo($invoice->project_po_number_id); // No. PO dulu, baru invoice (urutan global)
            $terkunci = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            if (! in_array($terkunci->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID'], true)) {
                return 'Settle PPh hanya bisa dilakukan untuk invoice yang belum lunas.';
            }

            $cr->refresh();
            $otherReceived = $terkunci->totalReceived() - $cr->amount - $cr->withholding_tax;
            $maxTax = max(0, $terkunci->captured_total - $otherReceived - $cr->amount);

            if ($tax > $maxTax) {
                return 'Nominal PPh melebihi sisa tagihan (maks ' . number_format($maxTax, 0, ',', '.') . ').';
            }

            $cr->update(['withholding_tax' => $tax]);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        UserActivityLog::record('cash_receipt', 'settle', 'Settle PPh Pasal 4(2) Cash Receipt Invoice #' . $invoice->code, $invoice);

        return back()->with('success', 'PPh Pasal 4(2) berhasil di-settle.');
    }

    public function destroyCashReceipt(Request $request, $id, $crId)
    {
        $invoice = $this->findVisibleInvoice($id);
        $cr = CashReceipt::where('invoice_id', $id)->findOrFail($crId);

        /*
        | Menghapus catatan uang masuk adalah pembalikan transaksi keuangan,
        | jadi diperlakukan seperti itu:
        |
        |  - alasan wajib diisi dan tersimpan di barisnya;
        |  - barisnya di-soft delete, bukan dilenyapkan, sehingga tetap bisa
        |    ditelusuri saat rekonsiliasi - sebelumnya pembayaran bisa hilang
        |    tanpa bekas dan status invoice diam-diam turun;
        |  - berkas buktinya TIDAK ikut dihapus; bukti transfer justru yang
        |    paling dibutuhkan ketika sebuah pembalikan dipertanyakan;
        |  - dijalankan dalam satu transaksi dengan baris invoice terkunci,
        |    agar tidak berpapasan dengan pencatatan pembayaran lain.
        */
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan penghapusan wajib diisi.',
            'reason.min'      => 'Alasan penghapusan terlalu singkat.',
        ]);

        DB::transaction(function () use ($cr, $invoice, $request) {
            $this->kunciNoPo($invoice->project_po_number_id); // No. PO dulu, baru invoice (urutan global)
            Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            $cr->update([
                'deleted_by'    => Auth::id(),
                'delete_reason' => $request->input('reason'),
            ]);

            $cr->delete(); // syncPaymentStatus() otomatis jalan lewat model event
        });

        Log::warning('Cash Receipt dihapus', [
            'invoice_id'      => $invoice->id,
            'cash_receipt_id' => $cr->id,
            'amount'          => $cr->amount,
            'user_id'         => Auth::id(),
            'reason'          => $request->input('reason'),
        ]);

        UserActivityLog::record('cash_receipt', 'delete', 'Menghapus Cash Receipt Invoice #' . $invoice->code, $invoice, $request->reason);

        return back()->with('success', 'Cash Receipt dihapus dan alasannya dicatat.');
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT LIST — daftar Invoice (index)
    |--------------------------------------------------------------------------
    */

    public function exportList(Request $request)
    {
        /*
         | Export mengikuti hasil filter yang sedang aktif di tabel: browser
         | mengirim id baris yang lolos (lihat public/js/tesys-export-filtered.js).
         | Tanpa 'ids' — diakses langsung, atau memang tidak ada filter aktif —
         | perilakunya persis seperti sebelumnya: seluruh data.
         */
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        /** @var User $user */
        $user = Auth::user();

        // Manager & Head: sama seperti index() (Access Group) & halaman detail
        // (tanpa Ringkasan Keuangan) — export-nya juga TIDAK boleh bawa nilai
        // nominal (Invoice/Tax/Total/Payment), supaya tidak jadi jalan pintas
        // mengambil data finansial yang di UI sudah disembunyikan.
        $restricted = $user->hasRole(['manager', 'head']);

        $query = Invoice::with(['po.customer', 'po.manager', 'poNumber', 'cashReceipts']);
        if ($restricted) {
            $projectIds = $user->accessibleProjectIds()->all();
            $query->whereIn('po_id', $projectIds);
        }
        $invoices = $query->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->latest()->get();

        $columns = $restricted
            ? ['No Invoice', 'Purchase Order', 'Customer', 'Date', 'Due Date (Print)', 'Due Date Actual', 'Received Date', 'Aging', 'Payment Date', 'Status']
            : ['No Invoice', 'Faktur', 'Purchase Order', 'Customer', 'Manager', 'Date', 'Due Date (Print)', 'Due Date Actual', 'Received Date', 'Invoice', 'Tax', 'Withholding Tax', 'Total', 'Payment', 'Payment Date', 'Status'];
        $lastCol = chr(ord('A') + count($columns) - 1);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Invoice');
        $sheet->fromArray($columns, null, 'A1');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($invoices as $invoice) {
            if ($restricted) {
                $dStatus = $invoice->display_status;
                $isOverdue = $invoice->isOverdue();
                $overdueDays = $isOverdue ? (int) $invoice->actual_due_date->startOfDay()->diffInDays(now()->startOfDay()) : 0;
                $lastCr = $invoice->cashReceipts
                    ->filter(fn ($cr) => $cr->received_date)
                    ->sortByDesc('received_date')
                    ->first();

                $sheet->fromArray([
                    $invoice->code,
                    ($invoice->poNumber->po_number ?? '-') . ' - ' . ($invoice->po->name ?? '-'),
                    $invoice->po->customer->name ?? '-',
                    $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-',
                    $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                    $invoice->actual_due_date ? $invoice->actual_due_date->format('d/m/Y') : '-',
                    $invoice->received_at ? $invoice->received_at->format('d/m/Y') : '-',
                    $isOverdue ? '+' . $overdueDays . ' hari' : '-',
                    $lastCr ? $lastCr->received_date->format('d/m/Y') : '-',
                    str_replace('_', ' ', $dStatus),
                ], null, 'A' . $row);
                $row++;
                continue;
            }

            $received = $invoice->cashReceipts->sum('amount') + $invoice->cashReceipts->sum('withholding_tax');
            $pmNames  = $invoice->po?->pm_internal_name ?: '-';
            $lastCr = $invoice->cashReceipts
                ->filter(fn ($cr) => $cr->received_date)
                ->sortByDesc('received_date')
                ->first();

            $sheet->fromArray([
                $invoice->code,
                $invoice->faktur_number ?: '-',
                ($invoice->poNumber->po_number ?? '-') . ' - ' . ($invoice->po->name ?? '-'),
                $invoice->po->customer->name ?? '-',
                $pmNames,
                $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-',
                $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                $invoice->actual_due_date ? $invoice->actual_due_date->format('d/m/Y') : '-',
                $invoice->received_at ? $invoice->received_at->format('d/m/Y') : '-',
                (int) $invoice->billed_amount,
                (int) $invoice->tax_amount,
                // Total = Invoice + Tax - Withholding Tax (captured_total).
                (int) $invoice->withholding_tax,
                (int) $invoice->captured_total,
                (int) $received,
                $lastCr ? $lastCr->received_date->format('d/m/Y') : '-',
                str_replace('_', ' ', $invoice->display_status),
            ], null, 'A' . $row);
            $row++;
        }

        $widths = $restricted
            ? ['A'=>16,'B'=>40,'C'=>26,'D'=>12,'E'=>12,'F'=>12,'G'=>14,'H'=>14,'I'=>16,'J'=>16]
            : ['A'=>16,'B'=>16,'C'=>40,'D'=>26,'E'=>20,'F'=>12,'G'=>12,'H'=>12,'I'=>14,'J'=>16,'K'=>14,'L'=>16,'M'=>16,'N'=>16,'O'=>16,'P'=>16];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'invoice_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — Rincian Invoice
    |--------------------------------------------------------------------------
    | Template & parser untuk mengisi baris "Rincian Invoice" (create/edit)
    | secara massal dari file Excel. Import TIDAK langsung menyimpan ke
    | database — hanya mem-parsing & mengembalikan JSON, lalu JS di form
    | yang mengisi ulang tabel Rincian Invoice. Simpan tetap lewat tombol
    | "Simpan" seperti biasa, supaya alur validasi & DB transaction sama
    | persis dengan input manual.
    */

    private const IMPORT_HEADERS = ['Deskripsi', 'Harga Satuan', 'Qty', 'Satuan', 'Deduction'];

    /**
     * GET /invoice-cash-receipt/import-template
     * Download template Excel siap isi untuk Rincian Invoice.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rincian Invoice');

        foreach (self::IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i); // A, B, C, D
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        // Contoh baris
        $sheet->setCellValue('A2', 'Contoh deskripsi item');
        $sheet->setCellValue('B2', 100000);
        $sheet->setCellValue('C2', 1);
        $sheet->setCellValue('D2', 'unit');
        $sheet->setCellValue('E2', 0);

        foreach (['A' => 40, 'B' => 16, 'C' => 8, 'D' => 14, 'E' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'template-rincian-invoice-' . now()->format('Ymd-His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /invoice-cash-receipt/import-preview
     * Body: file (xlsx)
     * Response JSON: { rows: [...], errors: [...] }
     * Tidak menyimpan apa pun ke database — hanya parsing untuk ditampilkan
     * & diisi ulang oleh JS ke tabel Rincian Invoice di form.
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Rincian Invoice') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows'   => [],
                'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            // Baris 0 = header, lewati
            if ($i === 0) continue;

            [$description, $unitPrice, $qty, $unit, $deduction] = array_pad($row, 5, null);

            // Lewati baris kosong total
            if (blank($description) && blank($unitPrice) && blank($qty) && blank($deduction)) {
                continue;
            }

            $rowNum = $i + 1; // nomor baris asli di Excel (1-based, +1 karena header)

            if (blank($description)) {
                $errors[] = "Baris {$rowNum}: Deskripsi wajib diisi.";
            }

            $priceNum = is_numeric($unitPrice) ? (int) round((float) $unitPrice) : (int) $this->normalizeMoney((string) $unitPrice);
            $qtyNum   = is_numeric($qty) ? (int) $qty : 1;
            if ($qtyNum <= 0) $qtyNum = 1;
            $deductionNum = is_numeric($deduction) ? (int) round((float) $deduction) : (int) $this->normalizeMoney((string) $deduction);

            $result[] = [
                'row'         => $rowNum,
                'description' => (string) $description,
                'unit_price'  => $priceNum,
                'qty'         => $qtyNum,
                'unit'        => $unit ? strtolower(trim((string) $unit)) : null,
                'deduction'   => $deductionNum,
            ];
        }

        if (empty($result) && empty($errors)) {
            $errors[] = 'Tidak ada baris data yang terbaca dari file. Pastikan mengisi mulai baris ke-2.';
        }

        return response()->json([
            'rows'   => $result,
            'errors' => $errors,
        ]);
    }
}