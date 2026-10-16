@extends('layouts.app')

@section('title', 'Collection')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;">Collection</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Collection</li>
            </ol>
        </div>

        <!-- SUMMARY WORKFLOW (clickable, filter tabel Invoice) -->
        @php
            $invSteps = [
                ['Draft',          $summary['draft'],          'secondary', 'DRAFT'],
                ['Submitted',      $summary['submitted'],      'warning',   'SUBMITTED'],
                ['Verified',       $summary['verified'],       'info',      'VERIFIED'],
                ['Approved',       $summary['approved'],       'primary',   'APPROVED'],
                ['Sent',           $summary['sent'],           'indigo',    'SENT'],
                ['Unpaid',         $summary['received'] ?? 0,  'purple',    'RECEIVED'],
                ['Partially Paid', $summary['partially_paid'], 'warning',   'PARTIALLY_PAID'],
                ['Overdue',        $summary['overdue'],        'danger',    'OVERDUE'],
                ['Paid',           $summary['paid'],           'success',   'PAID'],
                ['Cancelled',      $summary['cancelled'],      'dark',      'CANCELLED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$invSteps" :compact="true" table="tblInvoice" field="status" />

        <div class="card card-primary card-outline shadow-sm mt-3">

            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Daftar Invoice</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="btnRefreshInv" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">

                {{-- Daftar dibatasi 1.000 baris terbaru (lihat Concerns\CapsLargeLists).
                     Tanpa batas, rentang data yang lebar menggambar ribuan baris ke
                     DOM dan halaman membeku sebelum sempat tampil. Angka pada
                     stepper status tetap dihitung dari seluruh data, bukan dari
                     baris yang tampil. --}}
                @if(!empty($truncated))
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> invoice terbaru dari total
                    <strong>{{ number_format($total, 0, ',', '.') }}</strong>.
                    Gunakan pencarian/filter untuk mempersempit, atau <strong>Export Excel</strong> untuk data lengkap.
                </div>
                @endif

                <!-- FILTER -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="invSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterInv" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="invRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap align-items-center" style="gap:8px;">
                        @php
                            // Manager & Head cuma butuh lihat ringkasan non-finansial
                            // (No Invoice, PO, Date, Due Date (Print), Due Date Actual,
                            // Received Date, Aging, Payment Date, Status) — tombol
                            // "Kolom" di-disable supaya kolom nominal
                            // (Manager/Invoice/Tax/Total/Payment) tidak bisa
                            // dimunculkan sendiri lewat checklist.
                            $invColRestricted = Auth::user()->hasRole(['manager', 'head']);
                        @endphp
                        <div class="dropdown" id="invColVisDropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" data-boundary="viewport" @if($invColRestricted) disabled title="Kolom finansial tidak tersedia untuk role Anda" @endif>
                                <i class="fas fa-columns mr-1"></i> Kolom
                            </button>
                            @unless($invColRestricted)
                            <div class="dropdown-menu dropdown-menu-right p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                                @php
                                    $invHideableCols = [
                                        'manager'       => 'Manager',
                                        'date'          => 'Date',
                                        'duedate'       => 'Due Date (Print)',
                                        'duedateactual' => 'Due Date Actual',
                                        'receiveddate'  => 'Received Date',
                                        'aging'         => 'Aging',
                                        'invoice'       => 'Invoice',
                                        'tax'           => 'PPN',
                                        'wht'           => 'PPH',
                                        'total'         => 'Total',
                                        'received'      => 'Payment',
                                        'lastcr'        => 'Payment Date',
                                    ];
                                @endphp
                                @foreach($invHideableCols as $key => $label)
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input inv-col-vis-toggle" id="invColVis_{{ $key }}" data-column="{{ $key }}">
                                    <label class="custom-control-label" for="invColVis_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                                </div>
                                @endforeach
                            </div>
                            @endunless
                        </div>
                        <a href="{{ route('invoice-cash-receipt.export') }}" id="btnExportInv" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Export to Excel
                        </a>
                        @if(Auth::user()->hasRole(['superadmin','admin','finance','finance_manager','collection']))
                        <a href="{{ route('delivery-note.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-truck mr-1"></i> Surat Jalan
                        </a>
                        <a href="{{ route('invoice-cash-receipt.create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Buat Invoice
                        </a>
                        @endif
                    </div>
                </div>

                <!-- TABLE -->
                <div class="inv-table-wrap" id="invTableWrap">
                    <table id="tblInvoice" class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap inv-col-code" style="width:1%;">No Invoice</th>
                                <th scope="col">Purchase Order</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Manager</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Date</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Due Date (Print)</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Due Date Actual</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Received Date</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Invoice</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">PPN</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;" title="PPh yang dipotong di muka — mengurangi Total">PPH</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;" title="Invoice + PPN &minus; PPH">Total</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Payment</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Payment Date</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                            @php
                                $dStatus  = $invoice->display_status;
                                $received = $invoice->cashReceipts->sum('amount') + $invoice->cashReceipts->sum('withholding_tax');
                                $gap      = ($invoice->captured_total ?? 0) - $received;
                                $pmNames  = $invoice->po?->pm_internal_name && $invoice->po->pm_internal_name !== '-' ? $invoice->po->pm_internal_name : '';
                                $pmUpper  = $pmNames ? strtoupper($pmNames) : '-';

                                $isOverdue = $invoice->isOverdue();
                                $overdueDays = $isOverdue
                                    ? (int) $invoice->actual_due_date->startOfDay()->diffInDays(now()->startOfDay())
                                    : 0;
                                // Belum ditandai Diterima DAN due date cetak sudah lewat — Aging/Overdue
                                // tidak bisa dihitung (actual_due_date masih null) sampai di-Tandai Diterima.
                                $needsReceipt = $invoice->isMissingReceiptDate() && $invoice->due_date && $invoice->due_date->isPast();

                                $lastCr = $invoice->cashReceipts
                                    ->filter(fn ($cr) => $cr->received_date)
                                    ->sortByDesc('received_date')
                                    ->first();
                            @endphp
                            <tr data-id="{{ $invoice->id }}"
                                data-status="{{ $dStatus }}"
                                data-po="{{ $invoice->po ? '[' . $invoice->po->code . '] ' . $invoice->po->name : '' }}"
                                data-customer="{{ $invoice->po->customer->name ?? '' }}"
                                data-pm="{{ $pmUpper !== '-' ? $pmUpper : '' }}">

                                <td class="text-nowrap inv-col-code">
                                    <a href="{{ route('invoice-cash-receipt.show', $invoice->id) }}" target="_blank" class="inv-code-link">{{ $invoice->code }}</a>
                                    @if($invoice->is_additional)
                                        <span class="badge badge-warning" style="font-size:9px;" title="Melebihi sisa nilai kontrak PO">ADD</span>
                                    @endif
                                </td>

                                <td>
                                    <strong>{{ $invoice->poNumber->po_number ?? '-' }} - {{ Str::limit($invoice->po->name ?? '-', 50) }}</strong>
                                    <div style="font-size:11px;color:var(--text-faint);margin-top:3px;">
                                        {{ $invoice->po->code ?? '-' }} — {{ $invoice->po->customer->name ?? '-' }} — {{ $invoice->payment_term_label }}
                                    </div>
                                </td>

                                <td class="text-nowrap" style="font-weight:700;color:var(--text);">
                                    {{ $pmUpper }}
                                </td>

                                <td class="text-nowrap" data-order="{{ $invoice->invoice_date?->format('Y-m-d') ?? '' }}">
                                    {{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}
                                </td>

                                <td class="text-nowrap" data-order="{{ $invoice->due_date?->format('Y-m-d') ?? '' }}">
                                    {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                                </td>

                                <td class="text-nowrap {{ $isOverdue ? 'inv-neg' : '' }}" data-order="{{ $invoice->actual_due_date?->format('Y-m-d') ?? '' }}">
                                    @if($invoice->actual_due_date)
                                        {{ $invoice->actual_due_date->format('d/m/Y') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <td class="text-nowrap" data-order="{{ $invoice->received_at?->format('Y-m-d') ?? '' }}">
                                    @if($invoice->received_at)
                                        {{ $invoice->received_at->format('d/m/Y') }}
                                    @elseif($needsReceipt)
                                        <span class="badge badge-warning px-2 py-1" style="font-size:9.5px;" title="Due date cetak sudah lewat tapi invoice belum ditandai Diterima — Aging/Overdue tidak bisa dihitung sampai diisi.">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Belum Diterima
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <td class="text-center text-nowrap" data-order="{{ $overdueDays }}">
                                    @if($isOverdue)
                                        <span class="badge badge-danger px-2 py-1" style="font-size:10px;" title="Terlambat {{ $overdueDays }} hari dari due date">
                                            +{{ $overdueDays }} hari
                                        </span>
                                    @elseif($needsReceipt)
                                        <span class="text-warning" style="font-size:10px;" title="Belum ditandai Diterima">
                                            <i class="fas fa-question-circle"></i>
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <td class="text-right text-nowrap col-raw-invoice" data-raw="{{ $invoice->billed_amount ?? 0 }}">
                                    {{ number_format($invoice->billed_amount, 0, ',', '.') }}
                                </td>

                                <td class="text-right text-nowrap col-raw-tax" data-raw="{{ $invoice->tax_amount ?? 0 }}">
                                    {{ number_format($invoice->tax_amount, 0, ',', '.') }}
                                </td>

                                {{-- Total = Invoice + Tax - Withholding Tax (captured_total,
                                     dihitung InvoiceCalculationService). WHT ditampilkan sebagai
                                     kolom tersendiri supaya selisih itu terlihat, bukan cuma
                                     muncul diam-diam sebagai Total yang lebih kecil. --}}
                                <td class="text-right text-nowrap col-raw-wht" data-raw="{{ $invoice->withholding_tax ?? 0 }}">
                                    @if(($invoice->withholding_tax ?? 0) > 0)
                                        <span style="color:var(--danger);">&minus;{{ number_format($invoice->withholding_tax, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>

                                <td class="text-right text-nowrap col-raw-total" data-raw="{{ $invoice->captured_total ?? 0 }}">
                                    <strong>{{ number_format($invoice->captured_total, 0, ',', '.') }}</strong>
                                </td>

                                <td class="text-right text-nowrap col-raw-received" data-raw="{{ $received }}">
                                    {{ number_format($received, 0, ',', '.') }}
                                    @if($gap != 0 && $dStatus === 'PAID')
                                    <div style="font-size:11px;margin-top:2px;" class="{{ $gap > 0 ? 'text-warning' : 'text-danger' }}">
                                        {{ number_format($gap, 0, ',', '.') }}
                                    </div>
                                    @endif
                                </td>

                                <td class="text-nowrap" data-order="{{ $lastCr?->received_date?->format('Y-m-d') ?? '' }}">
                                    @if($lastCr)
                                        {{ $lastCr->received_date->format('d/m/Y') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <td class="text-center text-nowrap">
                                    <x-status :status="$dStatus" context="invoice" />
                                </td>

                                <td class="text-center action-column">
                                    @php
                                        /*
                                          Gerbang peran disalin apa adanya dari versi sebelumnya kolom ini.
                                          Perhatikan: aksi memakai $invoice->status (kolom asli), bukan
                                          $dStatus (display_status) yang bisa berubah jadi OVERDUE —
                                          bedanya dipertahankan persis seperti semula.
                                        */
                                        $pengguna = Auth::user();
                                        $st = $invoice->status;

                                        $bolehDraft   = $st === 'DRAFT' && $pengguna->hasRole(['superadmin','admin','finance','finance_manager','collection']);
                                        $bolehVerify  = $st === 'SUBMITTED' && $pengguna->hasRole(['superadmin','finance_manager','accounting']);
                                        $bolehApprove = $st === 'VERIFIED' && $pengguna->hasRole(['superadmin','finance_manager']);
                                        $bolehRevisi  = in_array($st, ['SUBMITTED','VERIFIED']) && $pengguna->hasRole(['superadmin','finance_manager','approver','accounting']);
                                        $bolehPulihkan = $st === 'CANCELLED' && $pengguna->hasRole(['superadmin','finance_manager']);
                                        $bolehBatal   = !in_array($st, ['PAID','CANCELLED']) && $pengguna->hasRole(['superadmin','finance_manager','approver']);

                                        $adaAksi = $bolehDraft || $bolehVerify || $bolehApprove
                                            || $bolehRevisi || $bolehPulihkan || $bolehBatal;
                                        // Aksi pertama selalu jadi tombol utama; sisanya (minimal Komentar)
                                        // selalu masuk ke titik tiga.
                                        $adaMenu = true;
                                    @endphp

                                    <div class="action-buttons ts-actions">

                                        {{-- AKSI UTAMA — satu per baris, selalu bernama --}}

                                        @if($bolehDraft)
                                        <form action="{{ route('invoice-cash-receipt.submit', $invoice->id) }}"
                                              method="POST" class="frmSubmitInv d-inline">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Submit">
                                                <i class="fas fa-paper-plane"></i><span class="ts-al">Submit</span>
                                            </button>
                                        </form>
                                        @elseif($bolehVerify)
                                        <form action="{{ route('invoice-cash-receipt.verify', $invoice->id) }}"
                                              method="POST" class="frmVerifyInv d-inline">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Verifikasi">
                                                <i class="fas fa-check"></i><span class="ts-al">Verify</span>
                                            </button>
                                        </form>
                                        @elseif($bolehApprove)
                                        <form action="{{ route('invoice-cash-receipt.approve', $invoice->id) }}"
                                              method="POST" class="frmApproveInv d-inline">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Approve">
                                                <i class="fas fa-check-double"></i><span class="ts-al">Approve</span>
                                            </button>
                                        </form>
                                        @elseif($bolehPulihkan)
                                        <button type="button" class="ts-action-primary is-neutral btn-revise-inv"
                                                data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}"
                                                title="Kembalikan ke Draft">
                                            <i class="fas fa-undo"></i><span class="ts-al">Kembalikan</span>
                                        </button>
                                        @endif

                                        {{-- Tanpa aksi, barisnya tetap punya jalan masuk ke detail --}}
                                        @unless($adaAksi)
                                        <a href="{{ url('/invoice-cash-receipt/' . $invoice->id) }}"
                                           class="ts-action-primary is-neutral"
                                           title="Lihat detail" aria-label="Lihat detail {{ $invoice->code }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                        </a>
                                        @endunless

                                        {{-- AKSI LAIN — termasuk semua aksi mundur/merusak --}}
                                        @if($adaMenu)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $invoice->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            @if($adaAksi)
                                            <a href="{{ url('/invoice-cash-receipt/' . $invoice->id) }}" class="dropdown-item">
                                                <i class="fas fa-eye"></i>Detail
                                            </a>
                                            @endif

                                            <button type="button" class="dropdown-item btn-comment-inv"
                                                    data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}">
                                                <i class="fas fa-comment"></i>Komentar
                                            </button>

                                            @if($bolehDraft)
                                            <a href="{{ route('invoice-cash-receipt.edit', $invoice->id) }}" class="dropdown-item">
                                                <i class="fas fa-pen"></i>Edit
                                            </a>
                                            @endif

                                            @if($bolehRevisi)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-revise-inv"
                                                    data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}">
                                                <i class="fas fa-undo"></i>Kembalikan untuk Revisi
                                            </button>
                                            @endif

                                            @if($bolehBatal)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-cancel-inv"
                                                    data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}">
                                                <i class="fas fa-ban"></i>Batalkan
                                            </button>
                                            @endif

                                            @if($bolehDraft)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('invoice-cash-receipt.destroy', $invoice->id) }}"
                                                  method="POST" class="frmDeleteInv">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>
                                            @endif

                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            {{-- Sengaja tidak ada baris di sini. Tabel ini dikelola DataTables dan
                                 baris kosong buatan sendiri akan dihitung sebagai baris data lalu
                                 selnya dikosongkan; keadaan kosongnya diisi language.emptyTable. --}}
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--surface-2);">
                                <th colspan="8" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right" id="invFooterInvoice" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right" id="invFooterTax" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right" id="invFooterWht" style="font-size:12px;color:var(--danger);"></th>
                                <th class="text-right" id="invFooterTotal" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right" id="invFooterReceived" style="font-size:12px;color:var(--info);"></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL KOMENTAR --}}
<div class="modal fade" id="mdlCommentInv" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-comment mr-2" style="color:var(--accent);"></i> Komentar Invoice</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmCommentInv" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="commentInvText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Komentar <span class="text-danger">*</span></label>
                        <textarea name="comment" class="form-control" rows="4" maxlength="2000"
                                  placeholder="Tulis komentar..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL REVISE --}}
<div class="modal fade" id="mdlReviseInv" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-undo mr-2" style="color:var(--accent);"></i> Kembalikan untuk Revisi</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmReviseInv" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="reviseInvText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan Revisi <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="4"
                                  placeholder="Tuliskan alasan atau catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo mr-1"></i> Kembalikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL CANCEL --}}
<div class="modal fade" id="mdlCancelInv" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-ban mr-2" style="color:var(--accent);"></i> Batalkan Invoice</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmCancelInv" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Invoice <strong id="cancelInvCode"></strong> akan dibatalkan dan tidak dapat diproses lagi.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="3"
                                  placeholder="Tuliskan alasan pembatalan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban mr-1"></i> Ya, Batalkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.inv-code-link {
    display:inline-block; padding:2px 8px; border:0; border-radius:var(--radius-sm);
    background:transparent; font-family:inherit; font-weight:700; font-size:13px; line-height:1.4;
    color:var(--text); text-decoration:none; cursor:pointer;
    transition:background-color .12s ease, color .12s ease;
}
.inv-code-link:hover, .inv-code-link:focus {
    background:var(--charcoal); color:#fff; text-decoration:none; outline:none;
}
.inv-neg { color:var(--danger) !important; font-weight:700; }

.badge-indigo           { color:#fff; background-color:var(--purple); }
.badge-purple           { color:#fff; background-color:var(--purple); }
.bg-purple              { background-color:var(--purple) !important; }
.btn-indigo              { color:#fff; background-color:var(--purple); border-color:var(--purple); }
.btn-indigo:hover        { color:#fff; background-color:var(--purple); border-color:var(--purple); }
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#invSearch input     { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
.ts-control .item {
    max-width: 240px;
    display: flex;
    align-items: center;
    overflow: visible;
}
.ts-control .item > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}
.ts-control .item .remove { flex-shrink: 0; }
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; width:1% !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }

/* Warna bersih: semua baris putih, tanpa efek hover row */
#tblInvoice tbody tr,
#tblInvoice tbody tr:hover { background-color:var(--surface) !important; }
#tblInvoice tbody td { background-color:var(--surface); }

/* ── TABEL: scroll horizontal, kolom No Invoice & Action beku ── */
.inv-hscroll { overflow-x:auto; }
.inv-hscroll #tblInvoice { width:auto !important; min-width:100%; margin-bottom:0; }
.inv-hscroll #tblInvoice th,
.inv-hscroll #tblInvoice td { white-space:nowrap; }

/* Kolom Code & Action dibekukan di tepi kiri/kanan — termasuk sel header dan
   footer-nya. position:sticky pada <th> perlu !important karena CSS DataTables
   memasang position:relative pada setiap header yang bisa diurutkan
   (table.dataTable>thead .sorting), yang kalau menang akan membatalkan freeze
   di baris header saja: datanya beku, judul kolomnya ikut menggeser.
   Latar sel wajib pekat, kalau tidak isi kolom lain terlihat menembusnya. */
.inv-hscroll #tblInvoice th.inv-col-code,
.inv-hscroll #tblInvoice td.inv-col-code {
    position:sticky !important; left:0; z-index:3;
    background:var(--surface);
    box-shadow:2px 0 6px -2px rgba(0,0,0,.18);
}
.inv-hscroll #tblInvoice th.action-column,
.inv-hscroll #tblInvoice td.action-column {
    position:sticky !important; right:0; z-index:3;
    background:var(--surface);
    box-shadow:-2px 0 6px -2px rgba(0,0,0,.18);
}
.inv-hscroll #tblInvoice thead th.inv-col-code,
.inv-hscroll #tblInvoice thead th.action-column,
.inv-hscroll #tblInvoice tfoot th.inv-col-code,
.inv-hscroll #tblInvoice tfoot th.action-column {
    background:var(--surface-2) !important; z-index:4;
}
.inv-hscroll #tblInvoice tbody tr:hover > td.inv-col-code,
.inv-hscroll #tblInvoice tbody tr:hover > td.action-column { background:var(--surface-2); }

.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
@media (max-width:576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    .custom-toolbar-right .dropdown { width:100%; }
    #btnClearFilterInv { width:100%; }
    #invRowFilters { width:100%; }
    #invRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #invSearch input     { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
    .card-header { flex-direction:column; align-items:stretch !important; gap:10px; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .card-header .ml-auto .btn { flex:1 1 auto; }
    #invRowFilters .tcf-row-btn { flex:1 1 auto; }
}

/* --- Filter dropdown: bedakan header grup vs item --- */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text) !important;
    background: var(--surface-2) !important;
    padding: 8px 12px !important;
    border-top: 1px solid var(--border);
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header {
    border-top: none;
}
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option {
    padding-left: 26px !important;
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /*
    |--------------------------------------------------------------------------
    | KOLOM TOGGLE — beberapa kolom bisa disembunyikan lewat dropdown "Kolom".
    | Preferensi disimpan di localStorage.
    |--------------------------------------------------------------------------
    */
    // Manager & Head: kolom finansial (Manager/Invoice/Tax/Total/Payment)
    // dipaksa tersembunyi terlepas dari localStorage — cuma No Invoice/PO/
    // Date/Due Date (Print)/Due Date Actual/Received Date/Aging/Payment Date/
    // Status yang tampil (lihat $invColRestricted di Blade, tombol "Kolom"
    // juga di-disable).
    var invColRestricted = "{{ $invColRestricted ? '1' : '0' }}" === '1';
    var invColRestrictedVisible = ['date', 'duedate', 'duedateactual', 'receiveddate', 'aging', 'lastcr'];

    var invColVisMap = {
        manager: 2, date: 3, duedate: 4, duedateactual: 5, receiveddate: 6, aging: 7, invoice: 8, tax: 9, wht: 10, total: 11, received: 12, lastcr: 13
    };
    var invColVisDefault = {
        manager: true, date: true, duedate: true, duedateactual: true, receiveddate: true, aging: true,
        invoice: true, tax: false, wht: false, total: true, received: true, lastcr: true
    };
    function getInvColVis(key) {
        if (invColRestricted) return invColRestrictedVisible.indexOf(key) !== -1;
        var stored = localStorage.getItem('invColVis_' + key);
        return stored === null ? !!invColVisDefault[key] : stored === '1';
    }
    function setInvColVis(key, visible) {
        localStorage.setItem('invColVis_' + key, visible ? '1' : '0');
    }

    /* DATATABLE */
    var table = $('#tblInvoice').DataTable({
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [[3, 'desc']],
        columnDefs: [
            { orderable: false, targets: [-1] },
            { targets: [2],  visible: getInvColVis('manager') },
            { targets: [3],  visible: getInvColVis('date') },
            { targets: [4],  visible: getInvColVis('duedate') },
            { targets: [5],  visible: getInvColVis('duedateactual') },
            { targets: [6],  visible: getInvColVis('receiveddate') },
            { targets: [7],  visible: getInvColVis('aging') },
            { targets: [8],  visible: getInvColVis('invoice') },
            { targets: [9],  visible: getInvColVis('tax') },
            { targets: [10], visible: getInvColVis('wht') },
            { targets: [11], visible: getInvColVis('total') },
            { targets: [12], visible: getInvColVis('received') },
            { targets: [13], visible: getInvColVis('lastcr') },
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            /* emptyTable = belum ada data sama sekali; zeroRecords = ada data tapi
               tidak ada yang cocok dengan pencarian/filter. Dua keadaan berbeda. */
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada Invoice</p>'
                + '<p class="ts-empty-msg">Belum ada invoice pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada invoice yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#tblInvoice_filter').hide();
    $('#invSearch').html('<input type="search" id="invSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#invSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    /*
    | Pencarian awal dari URL (?q=...), dipakai kolom Invoice/PPN/PPH/Total di
    | Daftar Purchase Order yang menautkan ke sini dengan kode PO-nya. Diisikan
    | ke kotak cari, bukan disaring diam-diam, supaya user melihat kenapa
    | daftarnya terpotong dan bisa menghapusnya.
    */
    (function applySearchFromUrl() {
        var q = new URLSearchParams(window.location.search).get('q');
        if (!q) return;
        $('#invSearchInput').val(q);
        table.search(q).draw();
    })();

    // Bungkus HANYA tabelnya dengan scroller horizontal, kolom No Invoice & Action beku.
    $('#tblInvoice').wrap('<div class="inv-hscroll table-responsive"></div>');

    // Auto-filter status via ?status=... di URL (mis. dari card Summary Collection
    // "Overdue Invoice" -> /invoice-cash-receipt?status=OVERDUE) — pola sama
    // seperti applyStatusFilterFromUrl() di budget-request/index.blade.php.
    (function applyStatusFilterFromUrl() {
        var status = new URLSearchParams(window.location.search).get('status');
        if (!status) return;
        var $step = $('[data-wf-table="tblInvoice"] .wf-tl-step[data-status="' + status + '"]');
        if ($step.length) $step.trigger('click');
    })();

    // Sinkronkan checkbox "Kolom" & wire toggle-nya.
    $('.inv-col-vis-toggle').each(function () {
        var key = $(this).data('column');
        if (invColVisMap[key] == null) return;
        $(this).prop('checked', table.column(invColVisMap[key]).visible());
    });
    $('.inv-col-vis-toggle').on('change', function () {
        var key = $(this).data('column');
        if (invColVisMap[key] == null) return;
        var visible = $(this).is(':checked');
        table.column(invColVisMap[key]).visible(visible);
        setInvColVis(key, visible);
        try { table.columns.adjust(); } catch (e) {}
    });
    $('#invColVisDropdown .dropdown-menu').on('click', function (e) { e.stopPropagation(); });
    table.on('column-visibility.dt', function () { try { table.columns.adjust(); } catch (e) {} });

    /*
    | FOOTER KALKULASI — total Invoice/Tax/Total/Received dari SEMUA baris yang lolos
    | filter/search saat ini (bukan cuma baris di halaman aktif).
    */
    function recalcFooter() {
        var totalInvoice = 0, totalTax = 0, totalWht = 0, totalAmount = 0, totalReceived = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            totalInvoice  += parseFloat(node.querySelector('.col-raw-invoice')?.dataset.raw) || 0;
            totalTax      += parseFloat(node.querySelector('.col-raw-tax')?.dataset.raw) || 0;
            totalWht      += parseFloat(node.querySelector('.col-raw-wht')?.dataset.raw) || 0;
            totalAmount   += parseFloat(node.querySelector('.col-raw-total')?.dataset.raw) || 0;
            totalReceived += parseFloat(node.querySelector('.col-raw-received')?.dataset.raw) || 0;
        });
        $('#invFooterInvoice').text(Math.round(totalInvoice).toLocaleString('id-ID'));
        $('#invFooterTax').text(Math.round(totalTax).toLocaleString('id-ID'));
        $('#invFooterWht').text(totalWht > 0 ? '-' + Math.round(totalWht).toLocaleString('id-ID') : '0');
        $('#invFooterTotal').text(Math.round(totalAmount).toLocaleString('id-ID'));
        $('#invFooterReceived').text(Math.round(totalReceived).toLocaleString('id-ID'));
    }

    /* Export Excel mengikuti filter yang SEDANG aktif di tabel — lihat
       public/js/tesys-export-filtered.js untuk alasan & perilaku fallback-nya. */
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnExportInv', table, '{{ route('invoice-cash-receipt.export') }}');
    }

    table.on('draw', recalcFooter);
    recalcFooter();

    TesysColumnFilter.initRowFilters('#tblInvoice', '#invRowFilters', [
        { key: 'po',       label: 'PO' },
        { key: 'customer', label: 'Customer' },
        { key: 'pm',       label: 'Manager' },
        { key: 'status',   label: 'Status', valueLabels: {
            'DRAFT': 'Draft', 'SUBMITTED': 'Submitted', 'VERIFIED': 'Verified',
            'APPROVED': 'Approved', 'SENT': 'Sent', 'RECEIVED': 'Unpaid',
            'PARTIALLY_PAID': 'Partially Paid', 'OVERDUE': 'Overdue',
            'PAID': 'Paid', 'CANCELLED': 'Cancelled',
        } },
    ]);

    // Auto-filter Manager/Customer via ?manager=...&customer=... di URL (mis.
    // dari klik nilai Overdue di Summary Collection) — dipanggil setelah
    // initRowFilters() supaya tombol/chip filter-nya sudah siap dipakai.
    // data-pm tersimpan huruf besar semua, jadi value dari URL di-uppercase
    // dulu supaya match persis.
    (function applyRowFiltersFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var manager = params.get('manager');
        var customer = params.get('customer');
        var statuses = params.get('statuses'); // daftar status dipisah koma, mis. "SENT,RECEIVED,PARTIALLY_PAID"
        if (manager) TesysColumnFilter.setRowFilter('#tblInvoice', 'pm', manager.toUpperCase());
        if (customer) TesysColumnFilter.setRowFilter('#tblInvoice', 'customer', customer);
        if (statuses) {
            statuses.split(',').forEach(function (s) {
                if (s) TesysColumnFilter.setRowFilter('#tblInvoice', 'status', s.trim());
            });
        }
    })();

    $('#btnRefreshInv').on('click', function () { location.reload(); });

    $('#btnClearFilterInv').on('click', function () {
        TesysColumnFilter.clearAll('#tblInvoice');
        TesysWorkflowStepper.clear('tblInvoice');
    });

    /*
      Warna tombol konfirmasi dan keadaan sibuk — pola yang sama dengan Cost
      Request. Semua aksi di sini adalah POST biasa yang memuat ulang halaman,
      jadi tanda sibuk mencegah tombol tertekan dua kali.
    */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_SETUJU = tokenWarna('--success', '#227a53');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    function sibukkan(form) {
        if (form.target && form.target !== '_self') return;
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit', '#frmReviseInv, #frmCancelInv, #frmCommentInv', function () { sibukkan(this); });

    /* SUBMIT CONFIRM */
    $(document).on('submit', '.frmSubmitInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit Invoice?', text:'Invoice akan dikirim untuk diverifikasi Accounting / Finance Manager.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_UTAMA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* VERIFY CONFIRM */
    $(document).on('submit', '.frmVerifyInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Verifikasi Invoice?', text:'Status akan berubah menjadi VERIFIED.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_SETUJU, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Verifikasi', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* APPROVE CONFIRM */
    $(document).on('submit', '.frmApproveInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Approve Invoice?', text:'Status akan berubah menjadi APPROVED.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_SETUJU, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Approve', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* DELETE CONFIRM */
    $(document).on('submit', '.frmDeleteInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Invoice?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:WARNA_BAHAYA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* ── KOMENTAR ── */
    $(document).on('click', '.btn-comment-inv', function () {
        var id = $(this).data('id');
        $('#commentInvText').text('Komentar untuk invoice ' + $(this).data('code') + ' akan tercatat di Log & Approval.');
        $('#frmCommentInv').attr('action', '/invoice-cash-receipt/' + id + '/comment');
        $('#frmCommentInv textarea[name="comment"]').val('');
        $('#mdlCommentInv').modal('show');
    });

    /* ── REVISE ── */
    $(document).on('click', '.btn-revise-inv', function () {
        var id   = $(this).data('id');
        var code = $(this).data('code');
        $('#reviseInvText').text('Kembalikan invoice ' + code + ' ke tahap sebelumnya untuk direvisi.');
        $('#frmReviseInv').attr('action', '/invoice-cash-receipt/' + id + '/revise');
        $('#frmReviseInv textarea[name="notes"]').val('');
        $('#mdlReviseInv').modal('show');
    });

    /* ── CANCEL ── */
    $(document).on('click', '.btn-cancel-inv', function () {
        var id   = $(this).data('id');
        var code = $(this).data('code');
        $('#cancelInvCode').text(code);
        $('#frmCancelInv').attr('action', '/invoice-cash-receipt/' + id + '/cancel');
        $('#frmCancelInv textarea[name="cancel_reason"]').val('');
        $('#mdlCancelInv').modal('show');
    });

});
</script>
@endpush
