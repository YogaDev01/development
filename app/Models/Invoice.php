<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /*
    | Penghapusan catatan keuangan ditandai, bukan dilenyapkan - lihat migrasi
    | 2026_09_18_000002. Baris yang ditandai otomatis keluar dari semua query
    | (termasuk penjumlahan pembayaran), tapi tetap ada untuk ditelusuri.
    */
    use SoftDeletes;

    protected $fillable = [
        'deleted_by',
        'delete_reason',
        'code',
        'faktur_number',
        'po_id',
        'project_po_number_id',
        'project_po_bast_id',
        'source_fund_id',
        'payment_term_type',
        'termin_number',
        'invoice_date',
        'due_date',
        'actual_due_date',
        'tax_rate',
        'billing_percentage',
        'subtotal',
        'billed_amount',
        'tax_amount',
        'withholding_tax',
        'captured_total',
        'total_deduction',
        'is_additional',
        'additional_amount',
        'remark',
        'status',
        'created_by',
        'submitted_by', 'submitted_at',
        'verified_by', 'verified_at',
        'approved_by', 'approved_at',
        'sent_by', 'sent_at',
        'received_by', 'received_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'invoice_date'       => 'date',
        'due_date'           => 'date',
        'actual_due_date'    => 'date',
        'tax_rate'           => 'decimal:2',
        'billing_percentage' => 'decimal:2',
        'subtotal'           => 'integer',
        'billed_amount'      => 'integer',
        'tax_amount'         => 'integer',
        'withholding_tax'    => 'integer',
        'captured_total'     => 'integer',
        'total_deduction'    => 'integer',
        'is_additional'      => 'boolean',
        'additional_amount'  => 'integer',
        'submitted_at'       => 'datetime',
        'verified_at'        => 'datetime',
        'approved_at'        => 'datetime',
        'sent_at'            => 'datetime',
        'received_at'        => 'datetime',
        'cancelled_at'       => 'datetime',
    ];

    public const PAYMENT_TERM_TYPES = ['Down Payment', 'Termin', 'Full Payment', 'Final Payment', 'Tagihan'];

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT', 'RECEIVED',
        'PARTIALLY_PAID', 'PAID', 'CANCELLED',
    ];

    /** Status yang boleh dianggap "belum dibayar" untuk keperluan Overdue. */
    public const UNPAID_STATUSES = ['SENT', 'RECEIVED', 'PARTIALLY_PAID'];

    /**
     * actual_due_date dihitung otomatis begitu received_at terisi/berubah —
     * bukan diinput manual. Panjang termin ASLI (due_date - invoice_date)
     * dipertahankan, cuma titik mulainya dipindah dari invoice_date ke
     * received_at (tanggal invoice benar-benar diterima customer). Ini
     * dipilih karena app ini tidak punya kolom "payment term (hari)"
     * tersendiri — payment_term_type di sini adalah KATEGORI (Down
     * Payment/Termin/dst), bukan durasi.
     */
    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice) {
            if ($invoice->isDirty('received_at') && $invoice->received_at) {
                $invoice->actual_due_date = $invoice->syncActualDueDate();
            }
        });
    }

    public function syncActualDueDate(): ?\Illuminate\Support\Carbon
    {
        if (!$this->received_at || !$this->invoice_date || !$this->due_date) {
            return null;
        }

        $termDays = $this->invoice_date->diffInDays($this->due_date);

        return $this->received_at->copy()->startOfDay()->addDays($termDays);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function po()
    {
        return $this->belongsTo(Project::class, 'po_id');
    }

    public function bast()
    {
        return $this->belongsTo(ProjectPoBast::class, 'project_po_bast_id')->withTrashed();
    }

    public function poNumber()
    {
        return $this->belongsTo(ProjectPoNumber::class, 'project_po_number_id');
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class)->orderBy('id');
    }

    public function attachments()
    {
        return $this->hasMany(InvoiceAttachment::class);
    }

    public function comments()
    {
        return $this->hasMany(InvoiceComment::class)->orderBy('created_at');
    }

    public function cashReceipts()
    {
        return $this->hasMany(CashReceipt::class)->orderBy('received_date');
    }

    public function deliveryNoteItem()
    {
        return $this->hasOne(DeliveryNoteItem::class);
    }

    public function deliveryNote()
    {
        return $this->hasOneThrough(
            DeliveryNote::class,
            DeliveryNoteItem::class,
            'invoice_id',       // FK di delivery_note_items -> invoices
            'id',                // FK di delivery_notes
            'id',                // local key di invoices
            'delivery_note_id'   // local key di delivery_note_items
        );
    }

    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function submittedBy()  { return $this->belongsTo(User::class, 'submitted_by'); }
    public function verifiedBy()   { return $this->belongsTo(User::class, 'verified_by'); }
    public function approvedBy()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function sentBy()       { return $this->belongsTo(User::class, 'sent_by'); }
    public function receivedBy()   { return $this->belongsTo(User::class, 'received_by'); }
    public function cancelledBy()  { return $this->belongsTo(User::class, 'cancelled_by'); }

    /*
    |--------------------------------------------------------------------------
    | KODE INVOICE
    |--------------------------------------------------------------------------
    | Format: {urutan_global}/{initial_customer}-{urutan_per_customer}/MM/YY
    | Contoh: 0001/KPA-0001/04/26
    | Reset tiap tahun kalender (berdasar tahun invoice_date).
    */
    public static function generateCode(int $poId, ?\DateTimeInterface $date = null): string
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $mm   = $date->format('m');
        $yy   = $date->format('y');

        $po       = Project::with('customer')->findOrFail($poId);
        $customer = $po->customer;
        $initial  = $customer->initial ?: 'GEN';

        /*
        | Nomor diambil dari penghitung terkunci (DocumentSequence), bukan dari
        | COUNT(*) + 1. Dengan COUNT, dua invoice yang disimpan bersamaan
        | mendapat nomor yang sama - salah satunya gagal karena kolom code unik,
        | dan pengguna hanya melihat error 500. Penghitungnya disemai dari data
        | yang sudah ada, jadi penomoran melanjutkan, tidak mengulang dari satu.
        */
        $globalNo = DocumentSequence::next(
            "invoice:{$year}",
            fn () => static::whereYear('invoice_date', $year)->count(),
        );

        $customerNo = DocumentSequence::next(
            "invoice:{$year}:customer:" . ($customer?->id ?? 0),
            fn () => static::whereYear('invoice_date', $year)
                ->whereHas('po', fn ($q) => $q->where('customer_id', $customer?->id))
                ->count(),
        );

        $globalSeq   = str_pad((string) $globalNo, 4, '0', STR_PAD_LEFT);
        $customerSeq = str_pad((string) $customerNo, 4, '0', STR_PAD_LEFT);

        return "{$globalSeq}/{$initial}-{$customerSeq}/{$mm}/{$yy}";
    }

    /*
    |--------------------------------------------------------------------------
    | KALKULASI
    |--------------------------------------------------------------------------
    */

    /**
     * Hitung ulang subtotal/tax/captured_total/deduction dari baris detail,
     * lalu cek apakah tagihan ini "Additional" (melebihi sisa nilai kontrak PO).
     */
    public function recalculateTotals(): void
    {
        $subtotal  = (int) $this->details()->sum('total');
        $deduction = (int) $this->details()->sum('deduction');

        // Persentase penagihan (mis. Termin 50%) — NULL/100 berarti tagih penuh.
        // Pajak & Total Tagihan dihitung dari nominal SETELAH persentase, karena
        // itu yang benar-benar ditagihkan ke customer saat ini.
        // MN-07: rumusnya tinggal satu tempat — InvoiceCalculationService.
        // Jangan menulis ulang perhitungan di sini, di controller, atau di JS
        // form; panggil service-nya supaya semua titik pasti sama.
        $calc = app(\App\Services\InvoiceCalculationService::class)->compute(
            $subtotal,
            $this->billing_percentage !== null ? (float) $this->billing_percentage : null,
            (float) $this->tax_rate,
            (int) $this->withholding_tax,
        );

        $billedAmount = $calc['billed_amount'];
        $taxAmount    = $calc['tax_amount'];
        $captured     = $calc['captured_total'];

        // Sisa yang belum ditagih = nominal No. PO (atau nilai kontrak PO untuk invoice lama
        // yang belum terhubung ke No. PO manapun) - total invoice lain yang tidak Cancelled
        // (exclude diri sendiri). Dibandingkan dari billed_amount (nominal setelah persentase,
        // sebelum PPN) — bukan subtotal — supaya invoice bertermin (1, 2, 3, ...) pada No. PO
        // yang sama tidak saling dianggap "Additional" walau baris detailnya merujuk scope
        // penuh yang sama tiap kali.
        // Invoice ber-BAST (1 invoice = 1 BAST) tidak pernah "Additional": nilainya
        // dikunci sama dengan nilai BAST oleh controller. Invoice lama tetap memakai logika di bawah.
        if ($this->project_po_bast_id) {
            $remainingContract = PHP_INT_MAX;
        } elseif ($this->project_po_number_id) {
            $poNumber = $this->poNumber ?? ProjectPoNumber::find($this->project_po_number_id);
            $remainingContract = $poNumber->remainingValue($this->id);
        } else {
            $po = $this->po ?? Project::find($this->po_id);
            $remainingContract = $po->remainingContractValue($this->id);
        }
        $isAdditional       = $billedAmount > $remainingContract;
        $additionalAmount   = $isAdditional ? ($billedAmount - max(0, $remainingContract)) : 0;

        $this->update([
            'subtotal'          => $subtotal,
            'billed_amount'     => $billedAmount,
            'tax_amount'        => $taxAmount,
            'withholding_tax'   => $calc['withholding_tax'],
            'captured_total'    => $captured,
            'total_deduction'   => $deduction,
            'is_additional'     => $isAdditional,
            'additional_amount' => $additionalAmount,
        ]);
    }

    /**
     * Total dianggap "diterima": Cash Receipt fisik + PPh Pasal 4(2) yang sudah
     * di-settle (selisih kecil karena pemotongan pajak, bukan uang yang hilang).
     */
    public function totalReceived(): int
    {
        return self::hitungDiterima(
            (int) $this->cashReceipts()->sum('amount'),
            (int) $this->cashReceipts()->sum('withholding_tax'),
            (int) $this->withholding_tax,
        );
    }

    /**
     * SATU-SATUNYA rumus "diterima" sebuah invoice (dipakai totalReceived(),
     * Closing, Summary Collection, Customer, dst.).
     *
     * Rezim sinkron: PPh yang diinput di Cash Receipt disalin ke invoices.withholding_tax
     * (sinkronPphDariCashReceipt), sehingga ia SUDAH mengurangi captured_total. Kalau
     * PPh receipt juga dijumlahkan sebagai "diterima", PPh terhitung dua kali.
     * Jadi bila PPh invoice sama persis dengan total PPh receipt (dan > 0), PPh receipt
     * tidak ditambahkan lagi. Selain itu (data lama: PPh di muka saja, atau PPh receipt
     * saja) rumusnya tetap amount + PPh receipt seperti sebelumnya.
     */
    public static function hitungDiterima(int $sumAmount, int $sumWht, int $invWht): int
    {
        return $sumAmount + ($invWht > 0 && $invWht === $sumWht ? 0 : $sumWht);
    }

    /**
     * Sisa tagihan BRUTO (Invoice + PPN), dasar batas input Cash Receipt:
     * billed_amount + tax_amount - amount - PPh pada receipt aktif. Tidak memakai
     * captured_total, karena captured_total ikut bergeser saat PPh disinkronkan.
     * $kecualiReceiptId mengecualikan satu receipt (batas settle PPh receipt itu).
     */
    public function sisaBruto(?int $kecualiReceiptId = null): int
    {
        $relasi = $this->cashReceipts();
        if ($kecualiReceiptId) {
            $relasi->where('id', '!=', $kecualiReceiptId);
        }

        return self::hitungSisaBruto(
            (int) $this->billed_amount,
            (int) $this->tax_amount,
            (int) (clone $relasi)->sum('amount'),
            (int) (clone $relasi)->sum('withholding_tax'),
        );
    }

    /** Rumus sisa bruto untuk pemanggil yang sudah punya jumlah receipt dari relasi ter-eager-load (tanpa N+1). */
    public static function hitungSisaBruto(int $billed, int $tax, int $sumAmount, int $sumWht): int
    {
        return max(0, $billed + $tax - $sumAmount - $sumWht);
    }

    /**
     * Samakan invoices.withholding_tax dengan PPh aktual pada Cash Receipt, lalu hitung
     * ulang captured_total lewat InvoiceCalculationService (rumus tidak ditulis ulang di sini).
     *
     * Target = total PPh receipt aktif + $pphTertunda (selisih receipt yang SEDANG
     * disimpan/diubah/dihapus dan belum tercermin di database: +PPh baru, atau -PPh yang
     * akan dibalik). Aturan:
     *  - target > 0 -> PPh invoice = target;
     *  - target = 0 -> PPh invoice jadi 0 hanya bila pembalikan receipt ber-PPh atau
     *    receipt ini melunasi sisa bruto dengan PPh 0; selain itu PPh di muka dipertahankan.
     *  - PEMBALIKAN hanya berlaku bila PPh invoice memang hasil sinkron (PPh invoice ==
     *    total PPh receipt aktif SEBELUM pembalikan). Contoh: PPh di muka 2.000.000, receipt
     *    lama R1 ber-PPh 1 -> dihapus: 2.000.000 != 1, bukan hasil sinkron, PPh invoice tidak
     *    disentuh. Contoh sinkron: PPh invoice 1.000 == PPh R1 1.000 -> dihapus -> PPh invoice 0.
     *  - Pada pembalikan, invoice yang tidak konsisten TIDAK memblokir: sinkron dilewati dan dicatat.
     * Wajib dipanggil di dalam transaksi dengan baris invoice terkunci.
     *
     * @param  string  $sumber  asal perubahan untuk jejak, mis. "penerimaan CR/2026/10/0001"
     * @param  int  $amountBaru  nominal diterima receipt yang SEDANG ditambahkan (belum di database);
     *                           dipakai menolak kelebihan bayar. 0 untuk settle/pembalikan.
     * @return array{dari:int, ke:int}|null  null bila tidak ada perubahan
     * @throws \DomainException bila nilai tersimpan tidak cocok dengan hasil service (bukan pembalikan)
     *                          atau penerimaan membuat total diterima melebihi captured_total
     */
    public function sinkronPphDariCashReceipt(string $sumber, int $pphTertunda = 0, bool $melunasiBruto = false, bool $pembalikan = false, int $amountBaru = 0): ?array
    {
        $sumPph = (int) $this->cashReceipts()->sum('withholding_tax');
        $target = max(0, $sumPph + $pphTertunda);
        $lama   = (int) $this->withholding_tax;

        if ($pembalikan) {
            // PPh invoice bukan hasil sinkron (mis. PPh di muka legacy): biarkan apa adanya.
            if ($lama !== $sumPph) {
                return null;
            }
            $baru = $target;
        } elseif ($target > 0) {
            $baru = $target;
        } elseif ($melunasiBruto) {
            $baru = 0;
        } else {
            $baru = $lama;
        }

        // Kelebihan bayar: setelah PPh invoice menjadi $baru, total diterima tidak boleh melebihi
        // captured_total. Contoh: PPh di muka 2.000.000, bruto 111.000.000 (captured 109.000.000),
        // diterima 110.000.000 tanpa PPh -> bukan pelunasan bruto, PPh di muka tetap -> kelebihan 1.000.000.
        if (! $pembalikan && $amountBaru > 0) {
            $capturedBaru = max(0, (int) $this->billed_amount + (int) $this->tax_amount - $baru);
            $diterima     = self::hitungDiterima(
                (int) $this->cashReceipts()->sum('amount') + $amountBaru,
                $sumPph + $pphTertunda,
                $baru,
            );
            if ($diterima > $capturedBaru) {
                $maks = max(0, $capturedBaru - (int) $this->cashReceipts()->sum('amount'));
                throw new \DomainException('Invoice ' . $this->code . ' memiliki PPh di muka, sehingga nominal tanpa PPh maksimal ' . number_format($maks, 0, ',', '.')
                    . '. Isi PPh pada penerimaan ini, atau lunasi sisa bruto penuh (' . number_format($this->sisaBruto(), 0, ',', '.') . ') dengan PPh 0 untuk menolkan PPh di muka.');
            }
        }

        $service = app(\App\Services\InvoiceCalculationService::class);
        $cek     = $service->computeFor($this);

        // PPh tidak berubah. Tetap perbaiki captured_total yang basi (PPh invoice sudah terisi tetapi
        // total belum dihitung ulang, mis. data lama). Data tagihan/PPN yang tidak konsisten tidak
        // disentuh di jalur ini: dibiarkan seperti semula, tanpa memblokir penerimaan.
        if ($baru === $lama) {
            $konsisten = $cek['billed_amount'] === (int) $this->billed_amount
                && $cek['tax_amount'] === (int) $this->tax_amount;
            if (! $konsisten || $cek['captured_total'] === (int) $this->captured_total) {
                return null;
            }
        }

        // Pra-cek: nilai tagihan & PPN tersimpan harus sama dengan hasil service, kalau tidak
        // captured_total hasil hitung ulang akan ikut menggeser angka lain secara diam-diam.
        if ($cek['billed_amount'] !== (int) $this->billed_amount || $cek['tax_amount'] !== (int) $this->tax_amount) {
            if ($pembalikan) {
                // Keputusan konservatif: pembatalan/penghapusan tidak boleh terblokir oleh data invoice lama.
                $pesan = 'PPh invoice tidak disinkron karena data invoice tidak konsisten (' . $sumber . '), periksa manual.';
                $this->comments()->create(['user_id' => \Illuminate\Support\Facades\Auth::id(), 'comment' => $pesan]);
                UserActivityLog::record('invoice', 'update', $pesan . ' - Invoice #' . $this->code, $this);

                return null;
            }
            throw new \DomainException('Invoice ' . $this->code . ' tidak konsisten (nilai tagihan/PPN tersimpan berbeda dari hasil hitung), PPh tidak dapat disinkronkan. Perbaiki invoice secara manual.');
        }

        $this->withholding_tax = $baru;
        $calc = $service->computeFor($this);
        $this->update([
            'withholding_tax' => $calc['withholding_tax'],
            'captured_total'  => $calc['captured_total'],
        ]);

        // Status disinkronkan di sini hanya bila tidak ada perubahan receipt yang tertunda. Bila ada
        // ($pphTertunda != 0), pemanggil langsung menyimpan/menghapus receipt sesudahnya dan hook
        // CashReceipt::saved/deleted yang menyinkronkan status dari data yang sudah konsisten
        // (menghindari status sementara yang salah, yang bisa ikut menggeser status No. PO).
        $this->refresh();
        if ($pphTertunda === 0) {
            $this->syncPaymentStatus();
        }

        $fmt  = fn (int $v) => number_format($v, 0, ',', '.');
        $pesan = $baru === $lama
            ? 'Total invoice dihitung ulang sesuai PPh ' . $fmt($lama) . ' oleh ' . $sumber
            : 'PPh invoice diubah dari ' . $fmt($lama) . ' ke ' . $fmt((int) $this->withholding_tax)
                . ' oleh ' . $sumber . ' (PPh aktual)';

        $this->comments()->create(['user_id' => \Illuminate\Support\Facades\Auth::id(), 'comment' => $pesan]);
        UserActivityLog::record('invoice', 'update', $pesan . ' - Invoice #' . $this->code, $this);

        return ['dari' => $lama, 'ke' => (int) $this->withholding_tax];
    }

    public function remainingBalance(): int
    {
        return max(0, $this->captured_total - $this->totalReceived());
    }

    /**
     * Sinkron status Paid/Partially Paid berdasarkan akumulasi Cash Receipt.
     * Dipanggil tiap kali Cash Receipt ditambah/dihapus.
     */
    public function syncPaymentStatus(): void
    {
        if (!in_array($this->status, ['SENT', 'RECEIVED', 'PARTIALLY_PAID', 'PAID'])) {
            return; // belum di tahap penagihan aktif, jangan diubah otomatis
        }

        $received = $this->totalReceived();
        $statusLama = $this->status;

        // Kalau belum ada Cash Receipt sama sekali, jangan turunkan status kembali
        // ke SENT — pertahankan RECEIVED (evidence surat jalan) bila sudah ada.
        $newStatus = $this->status;
        if ($received <= 0) {
            $newStatus = $this->status === 'RECEIVED' ? 'RECEIVED' : 'SENT';
        } elseif ($received < $this->captured_total) {
            $newStatus = 'PARTIALLY_PAID';
        } else {
            $newStatus = 'PAID';
        }

        if ($newStatus !== $this->status) {
            $this->update(['status' => $newStatus]);
        }

        if ($this->project_po_number_id) {
            $poNumber = $this->poNumber ?? ProjectPoNumber::find($this->project_po_number_id);
            if ($poNumber && $poNumber->usesBastBilling()) {
                // Penagihan per BAST: satu termin Paid TIDAK membuat No. PO Paid —
                // status dihitung dari seluruh BAST (ProjectPoNumber::syncInvoiceStatus()).
                if ($newStatus !== $statusLama) {
                    $poNumber->syncInvoiceStatus();
                }
            } elseif ($newStatus === 'PAID' && $poNumber && $poNumber->status !== 'PAID') {
                // No. PO lama tanpa BAST — perilaku lama: No. PO jadi Paid,
                // lalu status header Project disinkron dari agregat semua No. PO.
                $poNumber->update(['status' => 'PAID']);
                $poNumber->project?->syncStatusFromPoNumbers();
            }
        } elseif ($newStatus === 'PAID') {
            if (in_array($this->payment_term_type, ['Full Payment', 'Final Payment'])) {
                // Invoice lama tanpa No. PO — perilaku lama dipertahankan.
                $po = $this->po ?? Project::find($this->po_id);
                if ($po && $po->status !== 'PAID') {
                    $po->update(['status' => 'PAID']);
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    /**
     * Status yang ditampilkan ke user — OVERDUE dihitung on-the-fly dari
     * due_date, TIDAK disimpan sebagai perubahan kolom status di database.
     */
    /**
     * Overdue = actual_due_date (bukan due_date/invoice date) sudah lewat.
     * Invoice yang belum punya received_at (belum ada tanggal terima) TIDAK
     * boleh otomatis dianggap overdue — lihat isMissingReceiptDate().
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->isOverdue()) {
            return 'OVERDUE';
        }

        return $this->status;
    }

    /**
     * Kata untuk sebuah kode display_status — dipakai juga oleh
     * App\Support\WorkflowStatus supaya istilahnya tidak bercabang.
     * RECEIVED sengaja disebut UNPAID: invoice sudah diterima customer
     * tetapi belum dibayar. Pola yang sama dengan BudgetRequest::statusLabelFor().
     */
    public static function displayStatusLabelFor(?string $status): string
    {
        if ($status === null || $status === '') {
            return '-';
        }

        return [
            'RECEIVED' => 'UNPAID',
        ][$status] ?? str_replace('_', ' ', $status);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::UNPAID_STATUSES, true)
            && $this->actual_due_date
            && $this->actual_due_date->isPast();
    }

    /** Belum ada tanggal terima diisi — Finance perlu diberi tahu untuk mengisinya. */
    public function isMissingReceiptDate(): bool
    {
        return in_array($this->status, self::UNPAID_STATUSES, true) && !$this->received_at;
    }

    public function getPaymentTermLabelAttribute(): string
    {
        if ($this->payment_term_type === 'Termin' && $this->termin_number) {
            return 'Termin-' . $this->termin_number;
        }

        return $this->payment_term_type;
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return number_format($this->subtotal, 0, ',', '.');
    }

    public function getBilledAmountFormattedAttribute(): string
    {
        return number_format($this->billed_amount, 0, ',', '.');
    }

    /** "Termin 1 (50%)" — dipakai sebagai label baris persentase di summary/cetak. */
    public function getBillingPercentageLabelAttribute(): ?string
    {
        if ($this->billing_percentage === null) {
            return null;
        }

        $pct = rtrim(rtrim(number_format((float) $this->billing_percentage, 2, ',', '.'), '0'), ',');

        return $this->payment_term_label . ' (' . $pct . '%)';
    }

    public function getTaxAmountFormattedAttribute(): string
    {
        return number_format($this->tax_amount, 0, ',', '.');
    }

    public function getCapturedTotalFormattedAttribute(): string
    {
        return number_format($this->captured_total, 0, ',', '.');
    }

    public function getTotalDeductionFormattedAttribute(): string
    {
        return number_format($this->total_deduction, 0, ',', '.');
    }

    public function getAdditionalAmountFormattedAttribute(): string
    {
        return number_format($this->additional_amount, 0, ',', '.');
    }
}