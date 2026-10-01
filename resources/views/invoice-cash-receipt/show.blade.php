@extends('layouts.app')

@section('title', 'Detail Invoice')

@php
    $dStatus = $invoice->display_status;
    $received = $invoice->totalReceived();
    $balance  = $invoice->remainingBalance();
    $paidPct  = $invoice->captured_total > 0 ? round(($received / $invoice->captured_total) * 100, 1) : 0;
    $paidPctWidth = min($paidPct, 100);
    $paidBarColor = $paidPct >= 100 ? 'var(--success)' : 'var(--warning)';

    $canEditDraft = $invoice->status === 'DRAFT'      && Auth::user()->hasRole(['superadmin','admin','finance','finance_manager']);
    $canSubmit    = $invoice->status === 'DRAFT'      && Auth::user()->hasRole(['superadmin','admin','finance','finance_manager']);
    $canVerify    = $invoice->status === 'SUBMITTED'  && Auth::user()->hasRole(['superadmin','finance_manager','accounting']);
    $canApprove   = $invoice->status === 'VERIFIED'   && Auth::user()->hasRole(['superadmin','finance_manager']);
    $canRevise    = in_array($invoice->status, ['SUBMITTED','VERIFIED']) && Auth::user()->hasRole(['superadmin','finance_manager','approver','accounting']);
    $canCancel    = !in_array($invoice->status, ['PAID','CANCELLED']) && Auth::user()->hasRole(['superadmin','finance_manager','approver']);
    $canReviseCancel = $invoice->status === 'SENT' && Auth::user()->hasRole(['superadmin','finance_manager']);
    $canMarkReceived = in_array($invoice->status, ['SENT','RECEIVED']) && !$invoice->received_at && Auth::user()->hasRole(['superadmin','admin','finance','finance_manager','collection']);
    $canAddCr     = in_array($invoice->status, ['SENT','RECEIVED','PARTIALLY_PAID']) && Auth::user()->hasRole(['superadmin','admin','finance','finance_manager','collection']);
    $canDeleteCr  = Auth::user()->hasRole(['superadmin','admin','finance','finance_manager']);

    // Manager & Head: fokus ke Log & Approval saja — ringkasan keuangan &
    // tab Rincian/Lampiran/Cash Receipt disembunyikan (lihat juga index
    // Daftar Invoice yang kolom finansialnya dibatasi untuk role ini).
    $invRestricted = Auth::user()->hasRole(['manager','head']);

    // Tombol "Monitoring" — deep-link ke milestone "Invoice" (setiap PO pasti
    // punya milestone ini) di Project Monitoring, pakai mekanisme ?detail=
    // yang sudah ada di project-monitoring/show.blade.php.
    $invoiceMilestone = $invoice->po
        ? \App\Models\ProjectDetail::whereHas('stage', fn ($q) => $q->where('name', 'Invoice'))
            ->where('project_id', $invoice->po_id)
            ->first()
        : null;
    $monitoringUrl = $invoice->po
        ? route('project-monitoring.show', $invoice->po_id) . ($invoiceMilestone ? '?detail=' . $invoiceMilestone->id : '')
        : null;
@endphp

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/invoice-cash-receipt') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                        {{ $invoice->code }}
                        {{-- Label, keterangan, dan warna status dari App\Support\WorkflowStatus,
                             sama dengan yang dipakai daftar Invoice dan modul lain. --}}
                        <x-status :status="$dStatus" context="invoice" size="md" class="align-middle ml-1" />
                        @if($invoice->is_additional)
                            <span class="badge badge-warning align-middle" style="font-size:10px;" title="Melebihi sisa nilai kontrak PO">ADDITIONAL</span>
                        @endif
                    </h1>
                    <small class="text-muted">{{ $invoice->po->name ?? '-' }} — {{ $invoice->po->customer->name ?? '-' }}</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/invoice-cash-receipt') }}">Invoice & Cash Receipt</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        {{-- Posisi invoice ini di dalam alurnya — komponen yang sama dipakai
             Cost Request & Budget Plan. --}}
        <x-workflow-flow :status="$invoice->status" context="invoice" class="mb-3" />

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        {{-- TOOLBAR AKSI --}}
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap" style="gap:8px;">

                @unless($invRestricted)
                <a href="{{ route('invoice-cash-receipt.print', $invoice->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-print mr-1"></i> Cetak
                </a>
                @endunless

                @if($monitoringUrl)
                <a href="{{ $monitoringUrl }}" class="btn btn-outline-secondary btn-sm ml-auto" title="Buka milestone Invoice di Project Monitoring">
                    <i class="fas fa-binoculars mr-1"></i> Monitoring
                </a>
                @endif

                @if($canEditDraft)
                <a href="{{ route('invoice-cash-receipt.edit', $invoice->id) }}" class="btn btn-warning btn-sm">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
                @endif

                @if($canSubmit)
                <form action="{{ route('invoice-cash-receipt.submit', $invoice->id) }}" method="POST" class="frmSubmitInv d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
                </form>
                @endif

                @if($canVerify)
                <form action="{{ route('invoice-cash-receipt.verify', $invoice->id) }}" method="POST" class="frmVerifyInv d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Verifikasi</button>
                </form>
                @endif

                @if($canApprove)
                <form action="{{ route('invoice-cash-receipt.approve', $invoice->id) }}" method="POST" class="frmApproveInv d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-double mr-1"></i> Approve</button>
                </form>
                @endif

                @if($canRevise)
                <button type="button" class="btn btn-secondary btn-sm" id="btnReviseInv">
                    <i class="fas fa-undo mr-1"></i> Kembalikan / Revisi
                </button>
                @endif

                @if($canCancel)
                <button type="button" class="btn btn-danger btn-sm" id="btnCancelInv">
                    <i class="fas fa-ban mr-1"></i> Batalkan
                </button>
                @endif

                @if($canMarkReceived)
                <form action="{{ route('invoice-cash-receipt.mark-received', $invoice->id) }}" method="POST" class="d-inline frmMarkReceived">
                    @csrf
                    <button type="submit" class="btn btn-outline-purple btn-sm" style="border-color:var(--purple);color:var(--purple);">
                        <i class="fas fa-inbox mr-1"></i> Tandai Diterima
                    </button>
                </form>
                @endif

                @if($canEditDraft)
                <form action="{{ route('invoice-cash-receipt.destroy', $invoice->id) }}" method="POST" class="frmDeleteInv d-inline ml-auto">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash mr-1"></i> Hapus</button>
                </form>
                @endif

            </div>
        </div>

        {{--
            Tata letak menurun, sama seperti form tambah/ubah: identitas dulu,
            lalu ringkasan uang, lalu rincian. Versi lama menaruh keduanya di
            kolom sempit 4/12 — tabel dua kolom di lebar segitu memaksa nilai
            panjang (Scope of Work, nama rekening) terpotong, sementara tab
            rincian di kanan kehilangan lebar yang justru paling dibutuhkan.
        --}}

        <!-- 1. INFORMASI -->
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Invoice</h3>
            </div>
            <div class="card-body">
                <div class="inv-facts">

                    <div class="inv-fact inv-fact-wide">
                        <span class="inv-fact-label">Purchase Order</span>
                        <span class="inv-fact-value">
                            {{ $invoice->po ? '[' . $invoice->po->code . '] ' . $invoice->po->name : '-' }}
                            @if($invoice->po)
                            <button aria-label="Kontak PIC Internal &amp; Customer" type="button" class="btn btn-outline-secondary btn-sm ml-1" style="padding:1px 7px;" data-toggle="modal" data-target="#mdlPicContact" title="Kontak PIC Internal &amp; Customer">
                                <i class="fas fa-address-book"></i>
                            </button>
                            @endif
                        </span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">Customer</span>
                        <span class="inv-fact-value">{{ $invoice->po->customer->name ?? '-' }}</span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">No. PO</span>
                        <span class="inv-fact-value">{{ $invoice->poNumber->po_number ?? '-' }}</span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">No. BAST</span>
                        <span class="inv-fact-value">{{ $invoice->poNumber->no_bast ?? '-' }}</span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">No. Faktur Pajak</span>
                        <span class="inv-fact-value">{{ $invoice->faktur_number ?: '-' }}</span>
                    </div>

                    @php $dnLink = $invoice->deliveryNote; @endphp
                    @if($dnLink)
                    <div class="inv-fact">
                        <span class="inv-fact-label">Surat Jalan</span>
                        <span class="inv-fact-value">
                            <a href="{{ route('delivery-note.show', $dnLink->id) }}" target="_blank">{{ $dnLink->code }}</a>
                            <span class="badge badge-{{ $dnLink->status_color }} ml-1" style="font-size:9px;">{{ $dnLink->status_label }}</span>
                        </span>
                    </div>
                    @endif

                    <div class="inv-fact">
                        <span class="inv-fact-label">Payment Term</span>
                        <span class="inv-fact-value">{{ $invoice->payment_term_label }}</span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">Tanggal Invoice</span>
                        <span class="inv-fact-value">{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}</span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">Jatuh Tempo (Print)</span>
                        <span class="inv-fact-value">{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">Tanggal Terima</span>
                        <span class="inv-fact-value">
                            {{ $invoice->received_at ? $invoice->received_at->format('d/m/Y') : '-' }}
                            @if(!$invoice->received_at && in_array($invoice->status, ['SENT','RECEIVED','PARTIALLY_PAID']))
                            <span class="badge badge-secondary ml-1" style="font-size:9px;">Belum ada</span>
                            @endif
                        </span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">Jatuh Tempo Aktual</span>
                        <span class="inv-fact-value {{ $dStatus === 'OVERDUE' ? 'text-danger font-weight-600' : '' }}">
                            {{ $invoice->actual_due_date ? $invoice->actual_due_date->format('d/m/Y') : '-' }}
                            @if($dStatus === 'OVERDUE')<i class="fas fa-exclamation-circle ml-1"></i>@endif
                        </span>
                    </div>

                    <div class="inv-fact">
                        <span class="inv-fact-label">Tax Rate</span>
                        <span class="inv-fact-value">{{ rtrim(rtrim(number_format($invoice->tax_rate, 2, ',', '.'), '0'), ',') }}%</span>
                    </div>

                    <div class="inv-fact inv-fact-wide">
                        <span class="inv-fact-label">Remark</span>
                        <span class="inv-fact-value" style="white-space:pre-line;">{{ $invoice->remark ?: '-' }}</span>
                    </div>

                    @if($invoice->status === 'CANCELLED')
                    <div class="inv-fact inv-fact-wide">
                        <span class="inv-fact-label">Alasan Batal</span>
                        <span class="inv-fact-value text-danger" style="white-space:pre-line;">{{ $invoice->cancel_reason ?: '-' }}</span>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        @unless($invRestricted)
        <!-- 2. RINGKASAN KEUANGAN -->
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                <h3 class="card-title"><i class="fas fa-calculator mr-1"></i> Ringkasan Keuangan</h3>
                <small class="text-muted ml-auto font-italic">Seluruh nominal dalam Rupiah (IDR)</small>
            </div>
            <div class="card-body">

                {{-- Angka penyusun berjajar, hasil akhirnya dipisah ke kanan:
                     yang dibaca berulang kali adalah Captured Total dan Sisa
                     Tagihan, bukan komponennya. --}}
                <div class="inv-money">

                    <div class="inv-money-parts">
                        <div class="inv-money-item">
                            <span class="inv-fact-label">Subtotal</span>
                            <span class="inv-money-num">{{ number_format($invoice->subtotal + $invoice->total_deduction, 0, ',', '.') }}</span>
                        </div>
                        @if($invoice->billing_percentage_label)
                        <div class="inv-money-item">
                            <span class="inv-fact-label">{{ $invoice->billing_percentage_label }}</span>
                            <span class="inv-money-num">{{ number_format($invoice->billed_amount, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <div class="inv-money-item">
                            <span class="inv-fact-label">Total Deduction</span>
                            <span class="inv-money-num text-danger">{{ $invoice->total_deduction > 0 ? '- ' . number_format($invoice->total_deduction, 0, ',', '.') : number_format(0, 0, ',', '.') }}</span>
                        </div>
                        <div class="inv-money-item">
                            <span class="inv-fact-label">DPP</span>
                            <span class="inv-money-num">{{ number_format($invoice->billed_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="inv-money-item">
                            <span class="inv-fact-label">Tax</span>
                            <span class="inv-money-num">{{ number_format($invoice->tax_amount, 0, ',', '.') }}</span>
                        </div>
                        {{-- MN-07 — PPh dipotong di muka. Hanya ditampilkan kalau
                             ada nilainya, supaya invoice tanpa potongan tetap
                             ringkas seperti sebelumnya. --}}
                        @if($invoice->withholding_tax > 0)
                        <div class="inv-money-item">
                            <span class="inv-fact-label">Withholding Tax (PPh)</span>
                            <span class="inv-money-num text-danger">- {{ number_format($invoice->withholding_tax, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <div class="inv-money-item">
                            <span class="inv-fact-label">Diterima (Cash Receipt)</span>
                            <span class="inv-money-num text-success">{{ number_format($received, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="inv-money-totals">
                        <div class="inv-money-total">
                            <span class="inv-fact-label">Captured Total</span>
                            <strong class="text-primary">{{ number_format($invoice->captured_total, 0, ',', '.') }}</strong>
                        </div>
                        <div class="inv-money-total">
                            <span class="inv-fact-label">Sisa Tagihan</span>
                            <strong class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($balance, 0, ',', '.') }}</strong>
                        </div>
                        <div class="inv-progress">
                            <div style="height:8px;background:var(--surface-3);border-radius:var(--radius-pill);overflow:hidden;">
                                <div id="paidProgressBar" style="height:100%;border-radius:var(--radius-pill);transition:width .4s;"></div>
                            </div>
                            <small class="text-muted d-block mt-1 text-right">{{ rtrim(rtrim(number_format($paidPct, 1, ',', '.'), '0'), ',') }}% terbayar</small>
                        </div>
                    </div>

                </div>

                @if($invoice->is_additional)
                <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size:11px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>ADDITIONAL</strong> — melebihi sisa kontrak PO sebesar
                    {{ number_format($invoice->additional_amount, 0, ',', '.') }}.
                </div>
                @endif

                @if($invoice->sourceFund)
                <div class="inv-account mt-3">
                    <span class="inv-fact-label">Rekening Pembayaran</span>
                    <span class="inv-fact-value">
                        <strong>{{ $invoice->sourceFund->bank->name ?? '-' }}</strong> — {{ $invoice->sourceFund->no_account ?? '-' }}
                        <span class="text-muted">· a.n. {{ $invoice->sourceFund->name }}</span>
                    </span>
                </div>
                @endif

            </div>
        </div>
        @endunless

        <!-- 3. RINCIAN, LAMPIRAN, CASH RECEIPT, LOG (tab) -->
                @php
                    $poAttachments = $invoice->po->attachments->where('type', 'general')->values() ?? collect();
                    $noPoAttachments = $invoice->poNumber ? $invoice->poNumber->attachments->where('type', '!=', 'po_number_bast')->values() : collect();
                    $bastAttachments = $invoice->poNumber ? $invoice->poNumber->attachments->where('type', 'po_number_bast')->values() : collect();
                    $lampiranPoCount = $poAttachments->count() + $noPoAttachments->count() + $bastAttachments->count();
                @endphp

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="invDetailTabs">
                            @unless($invRestricted)
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#tabRincian">
                                    <i class="fas fa-list-ul mr-1"></i> Rincian
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tabLampiranPO">
                                    <i class="fas fa-paperclip mr-1"></i> Lampiran PO
                                    <span class="badge badge-secondary ml-1">{{ $lampiranPoCount }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tabLampiranInvoice">
                                    <i class="fas fa-paperclip mr-1"></i> Lampiran Invoice
                                    <span class="badge badge-secondary ml-1">{{ $invoice->attachments->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tabCashReceipt">
                                    <i class="fas fa-money-check-alt mr-1"></i> Cash Receipt
                                </a>
                            </li>
                            @endunless
                            <li class="nav-item">
                                <a class="nav-link {{ $invRestricted ? 'active' : '' }}" data-toggle="tab" href="#tabLog">
                                    <i class="fas fa-history mr-1"></i> Log &amp; Approval
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content">

                            @unless($invRestricted)
                            <!-- RINCIAN -->
                            <div class="tab-pane fade show active" id="tabRincian">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th scope="col" style="width:4%;" class="text-center">No</th>
                                                <th scope="col" style="width:34%;">Deskripsi</th>
                                                <th scope="col" style="width:16%;" class="text-right">Harga Satuan</th>
                                                <th scope="col" style="width:8%;" class="text-center">Qty</th>
                                                <th scope="col" style="width:16%;" class="text-right">Deduction</th>
                                                <th scope="col" style="width:16%;" class="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($invoice->details as $d)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>{{ $d->description }}</td>
                                                <td class="text-right">{{ number_format($d->unit_price, 0, ',', '.') }}</td>
                                                <td class="text-center">{{ $d->qty_unit_label }}</td>
                                                <td class="text-right text-danger">{{ $d->deduction > 0 ? '- ' . number_format($d->deduction, 0, ',', '.') : number_format(0, 0, ',', '.') }}</td>
                                                <td class="text-right"><strong>{{ number_format($d->total, 0, ',', '.') }}</strong></td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada rincian.</td></tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr style="background:var(--surface-2);">
                                                <th colspan="5" class="text-right" style="font-size:12px;">Captured Total</th>
                                                <th class="text-right" style="font-size:12px;color:var(--info);">{{ number_format($invoice->captured_total, 0, ',', '.') }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- LAMPIRAN PO (PO + No. PO + BAST) -->
                            <div class="tab-pane fade" id="tabLampiranPO">
                                <div class="att-group-section">
                                    <div class="att-group-title">Purchase Order</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                                    <th scope="col">Judul</th>
                                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($poAttachments as $a)
                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td>{{ $a->title ?? $a->file_name }}</td>
                                                    <td class="text-center">
                                                        <a href="/storage/{{ $a->file_path }}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="3" class="text-center text-muted py-3">PO ini belum punya lampiran.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="att-group-section">
                                    <div class="att-group-title">No. PO</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                                    <th scope="col">Judul</th>
                                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($noPoAttachments as $a)
                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td>{{ $a->title ?? $a->file_name }}</td>
                                                    <td class="text-center">
                                                        <a href="/storage/{{ $a->file_path }}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="3" class="text-center text-muted py-3">No. PO ini belum punya lampiran.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="att-group-section">
                                    <div class="att-group-title">BAST</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                                    <th scope="col">Judul</th>
                                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($bastAttachments as $a)
                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td>{{ $a->title ?? $a->file_name }}</td>
                                                    <td class="text-center">
                                                        <a href="/storage/{{ $a->file_path }}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="3" class="text-center text-muted py-3">No. PO ini belum punya lampiran BAST.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- LAMPIRAN INVOICE -->
                            <div class="tab-pane fade" id="tabLampiranInvoice">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th scope="col" style="width:5%;" class="text-center">No</th>
                                                <th scope="col">Judul</th>
                                                <th scope="col" style="width:20%;">Diupload Oleh</th>
                                                <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($invoice->attachments as $a)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>{{ $a->title ?? $a->file_name }}</td>
                                                <td><small class="text-muted">{{ $a->inputBy->name ?? '-' }}</small></td>
                                                <td class="text-center">
                                                    <a href="/storage/{{ $a->file_path }}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada dokumen pendukung.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- CASH RECEIPT -->
                            <div class="tab-pane fade" id="tabCashReceipt">
                                @if($canAddCr)
                                <div class="d-flex justify-content-end p-2 border-bottom">
                                    <button type="button" class="btn btn-success btn-sm" id="btnAddCashReceipt">
                                        <i class="fas fa-plus mr-1"></i> Tambah Cash Receipt
                                    </button>
                                </div>
                                @endif
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th scope="col" style="width:4%;" class="text-center">No</th>
                                                <th scope="col" style="width:11%;">Tanggal</th>
                                                <th scope="col" style="width:13%;" class="text-right">Jumlah</th>
                                                <th scope="col" style="width:14%;" class="text-right">PPh Ps.4(2)</th>
                                                <th scope="col" style="width:16%;">Sumber Dana</th>
                                                <th scope="col">Catatan</th>
                                                <th scope="col" style="width:11%;">Input Oleh</th>
                                                <th scope="col" style="width:8%;" class="text-center">Bukti</th>
                                                @if($canDeleteCr)
                                                <th scope="col" style="width:8%;" class="text-center">Aksi</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($invoice->cashReceipts as $cr)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>{{ $cr->received_date ? $cr->received_date->format('d/m/Y') : '-' }}</td>
                                                <td class="text-right"><strong class="text-success">{{ number_format($cr->amount, 0, ',', '.') }}</strong></td>
                                                <td class="text-right">
                                                    @if($cr->withholding_tax > 0)
                                                        <span class="text-info">{{ number_format($cr->withholding_tax, 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                    @if($canAddCr && $invoice->status === 'PARTIALLY_PAID')
                                                    <button type="button" class="btn btn-outline-info btn-sm btn-settle-cr d-block ml-auto mt-1"
                                                            style="font-size:10px;padding:1px 6px;"
                                                            data-invoice-id="{{ $invoice->id }}" data-cr-id="{{ $cr->id }}"
                                                            data-current-tax="{{ $cr->withholding_tax }}"
                                                            data-max-tax="{{ max(0, $invoice->captured_total - ($received - $cr->amount - $cr->withholding_tax) - $cr->amount) }}">
                                                        <i class="fas fa-percentage mr-1"></i>Settle
                                                    </button>
                                                    @endif
                                                </td>
                                                <td>{{ $cr->sourceFund->name ?? '-' }}</td>
                                                <td><small>{{ $cr->notes ?: '-' }}</small></td>
                                                <td><small class="text-muted">{{ $cr->inputBy->name ?? '-' }}</small></td>
                                                <td class="text-center">
                                                    @if($cr->proof_file)
                                                    <a href="/storage/{{ $cr->proof_file }}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                                    @else
                                                    <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                @if($canDeleteCr)
                                                <td class="text-center">
                                                    <form action="{{ route('invoice-cash-receipt.cash-receipt.destroy', [$invoice->id, $cr->id]) }}"
                                                          method="POST" class="frmDeleteCr d-inline">
                                                        @csrf @method('DELETE')
                                                        <button aria-label="Hapus" type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </td>
                                                @endif
                                            </tr>
                                            @empty
                                            <tr><td colspan="{{ $canDeleteCr ? 9 : 8 }}" class="text-center text-muted py-3">Belum ada pembayaran diterima.</td></tr>
                                            @endforelse
                                        </tbody>
                                        @if($invoice->cashReceipts->count())
                                        <tfoot>
                                            <tr style="background:var(--surface-2);">
                                                <th colspan="2" class="text-right" style="font-size:12px;">Total Diterima</th>
                                                <th class="text-right" style="font-size:12px;color:var(--success);">{{ number_format($received, 0, ',', '.') }}</th>
                                                <th colspan="{{ $canDeleteCr ? 6 : 5 }}"></th>
                                            </tr>
                                        </tfoot>
                                        @endif
                                    </table>
                                </div>
                                @if(!$canAddCr && !in_array($invoice->status, ['PAID']))
                                <div class="p-2 border-top">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Cash Receipt hanya bisa ditambahkan untuk invoice berstatus <strong>SENT</strong> atau <strong>PARTIALLY PAID</strong>.
                                    </small>
                                </div>
                                @endif
                            </div>
                            @endunless

                            <!-- LOG & APPROVAL -->
                            <div class="tab-pane fade {{ $invRestricted ? 'show active' : '' }}" id="tabLog">
                @php
                                    /*
                                     | Penyusunan timeline dipindah ke
                                     | App\Support\InvoiceTimeline.
                                     |
                                     | Sebelumnya ~95 baris PHP di sini membangun
                                     | koleksi peristiwa, membaca relasi, memformat
                                     | mata uang, dan MENYUSUN POTONGAN HTML sebagai
                                     | string yang dikeluarkan dengan {!! !!}.
                                     | Semuanya sudah ter-escape, jadi bukan lubang
                                     | keamanan - tapi logika urutan peristiwa dan
                                     | perhitungan PPh tidak bisa diuji tanpa
                                     | merender halaman, dan relasi yang dibaca di
                                     | dalam view membuat halaman ini diam-diam
                                     | menjadi N+1 setiap kali eager load di
                                     | controller berubah.
                                     |
                                     | Kelas itu mengembalikan DATA, bukan HTML,
                                     | sehingga {!! !!} tidak diperlukan lagi di
                                     | bawah.
                                     */
                                    $timelineEvents = \App\Support\InvoiceTimeline::untuk($invoice);
                                                                @endphp
                                <div class="inv-timeline p-3">
                                    @forelse($timelineEvents as $ev)
                                    <div class="inv-timeline-item">
                                        <span class="inv-timeline-dot badge-{{ $ev['color'] }}"></span>
                                        <div class="inv-timeline-body">
                                            <span class="badge badge-{{ $ev['color'] }} px-2 py-1 mr-2" style="font-size:9px;">{{ $ev['badge'] }}</span>
                                            <strong>{{ $ev['actor'] }}</strong>
                                            <div class="inv-timeline-date">{{ $ev['label'] }}</div>
                                            @if(!empty($ev['lines']))
                                                <div style="font-size:12px;margin-top:2px;">
                                                    @foreach($ev['lines'] as $baris)
                                                        <div>
                                                            @if(!empty($baris['ikon']))
                                                                <i class="{{ $baris['ikon'] }} mr-1"></i>
                                                            @endif
                                                            {{ $baris['teks'] }}
                                                            @if(!empty($baris['tautan']))
                                                                <a href="{{ $baris['tautan']['url'] }}" target="_blank">{{ $baris['tautan']['label'] }}</a>
                                                            @endif
                                                            @if(!empty($baris['sesudah']))
                                                                {{ $baris['sesudah'] }}
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    @empty
                                    <div class="text-center text-muted py-3">Belum ada log.</div>
                                    @endforelse
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

    </div>
</div>

{{-- MODAL KONTAK PIC — Internal & Customer dari PO invoice ini (Master PIC) --}}
@if($invoice->po)
@php
    $picsInternal = $invoice->po->picsInternal;
    $picsCustomer = $invoice->po->picsCustomer;
@endphp
<div class="modal fade" id="mdlPicContact" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white mb-0"><i class="fas fa-address-book mr-2" style="color:var(--accent);"></i>Kontak PIC — {{ $invoice->po->name }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:20px 24px;max-height:65vh;overflow-y:auto;">

                <div style="font-size:11px;font-weight:700;color:var(--text-faint);text-transform:uppercase;margin-bottom:8px;">
                    <i class="fas fa-building mr-1"></i> PIC Internal
                </div>
                @forelse($picsInternal as $pic)
                <div class="d-flex justify-content-between align-items-start pb-2 mb-2" style="border-bottom:1px solid var(--border);font-size:13px;">
                    <div>
                        <strong>{{ $pic->pic }}</strong>
                        <div class="text-muted" style="font-size:11.5px;">{{ $pic->position ?: '-' }}</div>
                    </div>
                    <div class="text-right" style="font-size:11.5px;">
                        @if($pic->phone)<div><i class="fas fa-phone-alt mr-1 text-muted"></i>{{ $pic->phone }}</div>@endif
                        @if($pic->email)<div><i class="fas fa-envelope mr-1 text-muted"></i>{{ $pic->email }}</div>@endif
                    </div>
                </div>
                @empty
                <div class="text-muted mb-3" style="font-size:12.5px;">Belum ada PIC Internal untuk PO ini.</div>
                @endforelse

                <div style="font-size:11px;font-weight:700;color:var(--text-faint);text-transform:uppercase;margin:14px 0 8px;">
                    <i class="fas fa-user-tie mr-1"></i> PIC Customer
                </div>
                @forelse($picsCustomer as $pic)
                <div class="d-flex justify-content-between align-items-start pb-2 mb-2" style="border-bottom:1px solid var(--border);font-size:13px;">
                    <div>
                        <strong>{{ $pic->pic }}</strong>
                        <div class="text-muted" style="font-size:11.5px;">{{ $pic->position ?: '-' }}</div>
                    </div>
                    <div class="text-right" style="font-size:11.5px;">
                        @if($pic->phone)<div><i class="fas fa-phone-alt mr-1 text-muted"></i>{{ $pic->phone }}</div>@endif
                        @if($pic->email)<div><i class="fas fa-envelope mr-1 text-muted"></i>{{ $pic->email }}</div>@endif
                    </div>
                </div>
                @empty
                <div class="text-muted" style="font-size:12.5px;">Belum ada PIC Customer untuk PO ini.</div>
                @endforelse

            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- MODAL TAMBAH CASH RECEIPT --}}
<div class="modal fade" id="mdlAddCashReceipt" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-money-check-alt mr-2" style="color:var(--accent);"></i> Tambah Cash Receipt</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('invoice-cash-receipt.cash-receipt.store', $invoice->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">

                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        Sisa tagihan saat ini (Rupiah): <strong>{{ number_format($balance, 0, ',', '.') }}</strong>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Tanggal Terima <span class="text-danger">*</span></label>
                        <input type="date" name="received_date" class="form-control form-control-sm"
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Jumlah <span class="text-danger">*</span></label>
                        <input type="text" name="amount" id="inpCrAmount" class="form-control form-control-sm text-right"
                               placeholder="0" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Sumber Dana</label>
                        <select name="source_fund_id" class="form-control form-control-sm">
                            <option value="">— Pilih Sumber Dana —</option>
                            @foreach($sourceFunds as $sf)
                                <option value="{{ $sf->id }}">{{ $sf->label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Bukti Transfer</label>
                        <div id="dropZoneCashReceipt" class="att-dropzone-real mb-1" tabindex="0">
                            <i class="fas fa-cloud-upload-alt mb-1 d-block"></i>
                            <small>Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseCashReceipt">klik untuk pilih</span></small>
                            <input type="file" name="proof_file" id="dropZoneInputCashReceipt" style="display:none;" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div id="cashReceiptFileName" class="text-muted" style="font-size:12px;"></div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>

                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL SETTLE PPH PASAL 4(2) --}}
<div class="modal fade" id="mdlSettleCr" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-percentage mr-2" style="color:var(--accent);"></i> Settle PPh Pasal 4(2)</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmSettleCr" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        Cash Receipt terkadang memiliki selisih kecil dari nilai tagihan karena dipotong PPh Pasal 4(2) (2%)
                        oleh customer. Isi nominal PPh di sini supaya Cash In + PPh = Full Payment, tanpa perlu uang tambahan masuk.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Nominal PPh Pasal 4(2) <span class="text-danger">*</span></label>
                        <input type="text" name="withholding_tax" id="inpSettleTax" class="form-control form-control-sm text-right"
                               placeholder="0" required>
                        <small class="text-muted">Maks: Rp <span id="settleMaxTax">0</span></small>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save mr-1"></i> Simpan</button>
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
            <form action="{{ route('invoice-cash-receipt.revise', $invoice->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p class="text-muted mb-3">Kembalikan invoice {{ $invoice->code }} ke tahap sebelumnya untuk direvisi.</p>
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
            <form action="{{ route('invoice-cash-receipt.cancel', $invoice->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Invoice <strong>{{ $invoice->code }}</strong> akan dibatalkan.
                    </div>
                    @if($canReviseCancel)
                    <div class="form-group">
                        <label class="font-weight-600 d-block">Pilihan Pembatalan <span class="text-danger">*</span></label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="cancelNoRevise" name="revise" value="0" class="custom-control-input" checked>
                            <label class="custom-control-label" for="cancelNoRevise">Cancel & Do Not Revise — berhenti di CANCELLED, tidak dapat diproses lagi.</label>
                        </div>
                        <div class="custom-control custom-radio mt-1">
                            <input type="radio" id="cancelRevise" name="revise" value="1" class="custom-control-input">
                            <label class="custom-control-label" for="cancelRevise">Cancel & Revise — kembali ke DRAFT, bisa diedit & diterbitkan ulang.</label>
                        </div>
                    </div>
                    @endif
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
.badge-indigo     { color:#fff; background-color:var(--purple); }
.badge-purple     { color:#fff; background-color:var(--purple); }
.btn-indigo       { color:#fff; background-color:var(--purple); border-color:var(--purple); }
.btn-indigo:hover { color:#fff; background-color:var(--purple); border-color:var(--purple); }
.font-weight-600  { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }
.timeline-compact { position:relative; }

.inv-timeline { position:relative; padding-left:26px; }
.inv-timeline::before { content:""; position:absolute; left:9px; top:6px; bottom:6px; width:2px; background:var(--surface-3); }
.inv-timeline-item { position:relative; padding-bottom:20px; }
.inv-timeline-item:last-child { padding-bottom:0; }
.inv-timeline-dot {
    position:absolute; left:-26px; top:2px; width:16px; height:16px; border-radius:50%;
    border:3px solid var(--border); box-shadow:0 0 0 1px var(--border);
}
.inv-timeline-body { font-size:12px; }
.inv-timeline-date { font-size:11px; color:var(--text-faint); margin-top:3px; }

.att-group-section { padding:14px 16px; border-bottom:1px solid var(--border); }
.att-group-section:last-child { border-bottom:none; }
.att-group-title {
    font-size:11px; font-weight:700; letter-spacing:.4px;
    text-transform:uppercase; color:var(--text-faint); margin-bottom:8px;
}

#invDetailTabs .nav-link { font-size:13px; font-weight:600; color:var(--text-faint); }
#invDetailTabs .nav-link.active { color:var(--accent); }
#invDetailTabs .badge { font-size:10px; }

.att-dropzone-real {
    border: 2px dashed var(--border); border-radius: var(--radius-sm); padding: 14px; text-align: center;
    color: var(--text-faint); cursor: pointer; transition: all .2s ease; background: var(--surface-2);
}
.att-dropzone-real:hover { border-color:var(--accent); background:var(--warning-bg); }
.att-dropzone-real.dragover { border-color:var(--accent); background:var(--warning-bg); color:var(--accent-dark); }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var pb = document.getElementById('paidProgressBar');
    if (pb) {
        pb.style.width      = '{{ $paidPctWidth }}%';
        pb.style.background = '{{ $paidBarColor }}';
    }

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    $('#inpCrAmount').on('input', function () { this.value = fmt(this.value); });
    $('#inpSettleTax').on('input', function () { this.value = fmt(this.value); });

    $('#btnAddCashReceipt').on('click', function () {
        $('#dropZoneInputCashReceipt').val('');
        $('#cashReceiptFileName').empty();
        $('#mdlAddCashReceipt').modal('show');
    });

    /* ── DROPZONE BUKTI TRANSFER — drag & drop + paste gambar (Ctrl+V) ── */
    (function () {
        var zone   = document.getElementById('dropZoneCashReceipt');
        var input  = document.getElementById('dropZoneInputCashReceipt');
        var browse = document.getElementById('dropZoneBrowseCashReceipt');
        if (!zone || !input) return;

        function setFile(file) {
            if (!file) return;
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) { /* browser lama — biarkan user pilih manual via klik */ }
            $('#cashReceiptFileName').html('<i class="fas fa-paperclip mr-1"></i>' + (input.files[0] ? input.files[0].name : file.name));
        }

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) { e.stopPropagation(); input.click(); });

        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                $('#cashReceiptFileName').html('<i class="fas fa-paperclip mr-1"></i>' + this.files[0].name);
            }
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (e) {
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length) setFile(files[0]);
        });

        function handlePasteProof(e) {
            var items = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).items : null;
            if (!items) return;
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var f = items[i].getAsFile();
                    if (f) {
                        var ext = (f.type.split('/')[1] || 'png');
                        setFile(new File([f], 'pasted-image-' + Date.now() + '.' + ext, { type: f.type }));
                        e.preventDefault();
                        break;
                    }
                }
            }
        }
        zone.addEventListener('paste', handlePasteProof);
        document.addEventListener('paste', function (e) {
            if (!$('#mdlAddCashReceipt').hasClass('show')) return;
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;
            handlePasteProof(e);
        });
    })();

    /* ── SETTLE PPh PASAL 4(2) ── */
    $(document).on('click', '.btn-settle-cr', function () {
        var invoiceId = $(this).data('invoice-id');
        var crId      = $(this).data('cr-id');
        var currentTax = $(this).data('current-tax') || 0;
        var maxTax     = $(this).data('max-tax') || 0;

        $('#frmSettleCr').attr('action', '/invoice-cash-receipt/' + invoiceId + '/cash-receipt/' + crId + '/settle');
        $('#inpSettleTax').val(fmt(String(currentTax > 0 ? currentTax : maxTax)));
        $('#settleMaxTax').text(fmt(String(maxTax)));
        $('#mdlSettleCr').modal('show');
    });
    $('#btnReviseInv').on('click', function () { $('#mdlReviseInv').modal('show'); });
    $('#btnCancelInv').on('click', function () { $('#mdlCancelInv').modal('show'); });

    /* CONFIRMATIONS */
    $(document).on('submit', '.frmSubmitInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit Invoice?', text:'Invoice akan dikirim untuk diverifikasi Accounting / Finance Manager.', icon:'question', showCancelButton:true, confirmButtonColor:'#42627d', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmVerifyInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Verifikasi Invoice?', text:'Status akan berubah menjadi VERIFIED.', icon:'question', showCancelButton:true, confirmButtonColor:'var(--success)', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Verifikasi', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmApproveInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Approve Invoice?', text:'Status akan berubah menjadi APPROVED.', icon:'question', showCancelButton:true, confirmButtonColor:'var(--success)', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Approve', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmDeleteInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Invoice?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:'#9a3030', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* Menghapus Cash Receipt = membalikkan catatan uang masuk. Alasannya wajib
       (divalidasi juga di server) dan ikut tersimpan pada barisnya, supaya
       pembalikan bisa dijelaskan saat rekonsiliasi. Datanya di-soft delete,
       jadi teksnya tidak lagi menjanjikan penghapusan permanen. */
    $(document).on('submit', '.frmDeleteCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({
            title: 'Hapus Cash Receipt?',
            html: 'Status invoice akan dihitung ulang. Catatan ini tetap tersimpan untuk audit, beserta alasan di bawah.',
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Alasan penghapusan (wajib)',
            inputPlaceholder: 'mis. salah input nominal, dobel dengan CR-0007',
            inputAttributes: { 'aria-label': 'Alasan penghapusan' },
            inputValidator: function (value) {
                if (!value || value.trim().length < 5) return 'Tuliskan alasan minimal 5 karakter.';
            },
            showCancelButton: true,
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $('<input>').attr({ type: 'hidden', name: 'reason' }).val(r.value).appendTo(form);
            form.submit();
        });
    });

    $(document).on('submit', '.frmMarkReceived', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Tandai Diterima?', text:'Actual Due Date akan dihitung otomatis dari tanggal ini.', icon:'question', showCancelButton:true, confirmButtonColor:'#6f42c1', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Tandai', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

});
</script>
@endpush