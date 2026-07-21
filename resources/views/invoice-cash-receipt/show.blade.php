@extends('layouts.app')

@section('title', 'Detail Invoice')

@php
    $dStatus = $invoice->display_status;
    $sc = [
        'DRAFT'           => 'secondary',
        'SUBMITTED'       => 'warning',
        'VERIFIED'        => 'info',
        'APPROVED'        => 'primary',
        'SENT'            => 'indigo',
        'PARTIALLY_PAID'  => 'warning',
        'OVERDUE'         => 'danger',
        'PAID'            => 'success',
        'CANCELLED'       => 'dark',
    ][$dStatus] ?? 'secondary';

    $received = $invoice->totalReceived();
    $balance  = $invoice->remainingBalance();
    $paidPct  = $invoice->captured_total > 0 ? round(($received / $invoice->captured_total) * 100, 1) : 0;
    $paidPctWidth = min($paidPct, 100);
    $paidBarColor = $paidPct >= 100 ? '#16a34a' : '#F5A623';

    $canEditDraft = $invoice->status === 'DRAFT'      && Auth::user()->hasRole(['superadmin','admin','finance']);
    $canSubmit    = $invoice->status === 'DRAFT'      && Auth::user()->hasRole(['superadmin','admin','finance']);
    $canVerify    = $invoice->status === 'SUBMITTED'  && Auth::user()->hasRole(['superadmin','finance_manager']);
    $canApprove   = $invoice->status === 'VERIFIED'   && Auth::user()->hasRole(['superadmin','approver']);
    $canSent      = $invoice->status === 'APPROVED'   && Auth::user()->hasRole(['superadmin','admin','finance']);
    $canRevise    = in_array($invoice->status, ['SUBMITTED','VERIFIED']) && Auth::user()->hasRole(['superadmin','finance_manager','approver']);
    $canCancel    = !in_array($invoice->status, ['PAID','CANCELLED']) && Auth::user()->hasRole(['superadmin','finance_manager','approver']);
    $canAddCr     = in_array($invoice->status, ['SENT','PARTIALLY_PAID']) && Auth::user()->hasRole(['superadmin','admin','finance']);
    $canDeleteCr  = Auth::user()->hasRole(['superadmin','admin','finance']);
@endphp

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-8">
                <div class="d-flex align-items-center">
                    <a href="{{ url('/invoice-cash-receipt') }}" class="btn btn-secondary btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="m-0 font-weight-bold">
                            {{ $invoice->code }}
                            <span class="badge badge-{{ $sc }} px-2 py-1 align-middle ml-1" style="font-size:12px;">
                                {{ str_replace('_', ' ', $dStatus) }}
                            </span>
                            @if($invoice->is_additional)
                                <span class="badge badge-warning align-middle" style="font-size:10px;" title="Melebihi sisa nilai kontrak PO">ADDITIONAL</span>
                            @endif
                        </h1>
                        <small class="text-muted">{{ $invoice->po->name ?? '-' }} — {{ $invoice->po->customer->name ?? '-' }}</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/invoice-cash-receipt') }}">Invoice & Cash Receipt</a></li>
                    <li class="breadcrumb-item active">Detail</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        {{-- TOOLBAR AKSI --}}
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap" style="gap:8px;">

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

                @if($canSent)
                <form action="{{ route('invoice-cash-receipt.sent', $invoice->id) }}" method="POST" class="frmSentInv d-inline">
                    @csrf
                    <button type="submit" class="btn btn-indigo btn-sm"><i class="fas fa-share mr-1"></i> Tandai Terkirim</button>
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

                @if($canEditDraft)
                <form action="{{ route('invoice-cash-receipt.destroy', $invoice->id) }}" method="POST" class="frmDeleteInv d-inline ml-auto">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash mr-1"></i> Hapus</button>
                </form>
                @endif

            </div>
        </div>

        <div class="row">

            <!-- KIRI: INFORMASI & RINGKASAN -->
            <div class="col-md-4">

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Invoice</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0" style="font-size:13px;">
                            <tr>
                                <th style="width:40%;color:#6B7280;font-weight:600;">Purchase Order</th>
                                <td>{{ $invoice->po->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:#6B7280;font-weight:600;">Customer</th>
                                <td>{{ $invoice->po->customer->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:#6B7280;font-weight:600;">Payment Term</th>
                                <td>{{ $invoice->payment_term_label }}</td>
                            </tr>
                            <tr>
                                <th style="color:#6B7280;font-weight:600;">Tanggal Invoice</th>
                                <td>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:#6B7280;font-weight:600;">Jatuh Tempo</th>
                                <td class="{{ $dStatus === 'OVERDUE' ? 'text-danger font-weight-600' : '' }}">
                                    {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                                    @if($dStatus === 'OVERDUE')<i class="fas fa-exclamation-circle ml-1"></i>@endif
                                </td>
                            </tr>
                            <tr>
                                <th style="color:#6B7280;font-weight:600;">Tax Rate</th>
                                <td>{{ rtrim(rtrim(number_format($invoice->tax_rate, 2, ',', '.'), '0'), ',') }}%</td>
                            </tr>
                            <tr>
                                <th style="color:#6B7280;font-weight:600;vertical-align:top;">Remark</th>
                                <td style="white-space:pre-line;">{{ $invoice->remark ?: '-' }}</td>
                            </tr>
                            @if($invoice->status === 'CANCELLED')
                            <tr>
                                <th style="color:#6B7280;font-weight:600;vertical-align:top;">Alasan Batal</th>
                                <td class="text-danger" style="white-space:pre-line;">{{ $invoice->cancel_reason ?: '-' }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calculator mr-1"></i> Ringkasan Keuangan</h3>
                    </div>
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="text-muted" style="font-size:12px;">Subtotal</span>
                            <strong style="font-size:13px;">{{ $invoice->subtotal_formatted }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="text-muted" style="font-size:12px;">Total Deduction</span>
                            <strong class="text-danger" style="font-size:13px;">{{ $invoice->total_deduction_formatted }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="text-muted" style="font-size:12px;">Tax ({{ rtrim(rtrim(number_format($invoice->tax_rate,2,',','.'),'0'),',') }}%)</span>
                            <strong style="font-size:13px;">{{ $invoice->tax_amount_formatted }}</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <span class="font-weight-600" style="font-size:13px;">Captured Total</span>
                            <strong class="text-primary" style="font-size:16px;">{{ $invoice->captured_total_formatted }}</strong>
                        </div>

                        @if($invoice->is_additional)
                        <div class="alert alert-warning mb-2 py-2" style="font-size:11px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>ADDITIONAL</strong> — melebihi sisa kontrak PO sebesar
                            Rp {{ number_format($invoice->additional_amount, 0, ',', '.') }}.
                        </div>
                        @endif

                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="text-muted" style="font-size:12px;">Diterima (Cash Receipt)</span>
                            <strong class="text-success" style="font-size:13px;">Rp {{ number_format($received, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <span class="font-weight-600" style="font-size:13px;">Sisa Tagihan</span>
                            <strong class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}" style="font-size:15px;">
                                Rp {{ number_format($balance, 0, ',', '.') }}
                            </strong>
                        </div>
                        <div style="height:8px;background:#E9ECEF;border-radius:99px;overflow:hidden;">
                            <div id="paidProgressBar" style="height:100%;border-radius:99px;transition:width .4s;"></div>
                        </div>
                        <small class="text-muted d-block mt-1 text-right">{{ rtrim(rtrim(number_format($paidPct, 1, ',', '.'), '0'), ',') }}% terbayar</small>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-1"></i> Log & Approval</h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline-compact">
                            <div class="d-flex mb-3">
                                <span class="badge badge-secondary px-2 py-1 mr-2" style="font-size:9px;height:fit-content;">DRAFT</span>
                                <div>
                                    <div style="font-size:12px;font-weight:600;">{{ $invoice->createdBy->name ?? '-' }}</div>
                                    <div style="font-size:11px;color:#6B7280;">{{ $invoice->created_at?->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                            @if($invoice->submitted_at)
                            <div class="d-flex mb-3">
                                <span class="badge badge-warning px-2 py-1 mr-2" style="font-size:9px;height:fit-content;">SUBMIT</span>
                                <div>
                                    <div style="font-size:12px;font-weight:600;">{{ $invoice->submittedBy->name ?? '-' }}</div>
                                    <div style="font-size:11px;color:#6B7280;">{{ $invoice->submitted_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                            @endif
                            @if($invoice->verified_at)
                            <div class="d-flex mb-3">
                                <span class="badge badge-info px-2 py-1 mr-2" style="font-size:9px;height:fit-content;">VERIFY</span>
                                <div>
                                    <div style="font-size:12px;font-weight:600;">{{ $invoice->verifiedBy->name ?? '-' }}</div>
                                    <div style="font-size:11px;color:#6B7280;">{{ $invoice->verified_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                            @endif
                            @if($invoice->approved_at)
                            <div class="d-flex mb-3">
                                <span class="badge badge-primary px-2 py-1 mr-2" style="font-size:9px;height:fit-content;">APPROVE</span>
                                <div>
                                    <div style="font-size:12px;font-weight:600;">{{ $invoice->approvedBy->name ?? '-' }}</div>
                                    <div style="font-size:11px;color:#6B7280;">{{ $invoice->approved_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                            @endif
                            @if($invoice->sent_at)
                            <div class="d-flex mb-3">
                                <span class="badge badge-indigo px-2 py-1 mr-2" style="font-size:9px;height:fit-content;">SENT</span>
                                <div>
                                    <div style="font-size:12px;font-weight:600;">{{ $invoice->sentBy->name ?? '-' }}</div>
                                    <div style="font-size:11px;color:#6B7280;">{{ $invoice->sent_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                            @endif
                            @if($invoice->cancelled_at)
                            <div class="d-flex mb-0">
                                <span class="badge badge-dark px-2 py-1 mr-2" style="font-size:9px;height:fit-content;">CANCELLED</span>
                                <div>
                                    <div style="font-size:12px;font-weight:600;">{{ $invoice->cancelledBy->name ?? '-' }}</div>
                                    <div style="font-size:11px;color:#6B7280;">{{ $invoice->cancelled_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            <!-- KANAN: RINCIAN, LAMPIRAN, CASH RECEIPT -->
            <div class="col-md-8">

                <!-- RINCIAN -->
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian Invoice</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:4%;" class="text-center">No</th>
                                        <th style="width:34%;">Deskripsi</th>
                                        <th style="width:16%;" class="text-right">Harga Satuan</th>
                                        <th style="width:8%;" class="text-center">Qty</th>
                                        <th style="width:16%;" class="text-right">Deduction</th>
                                        <th style="width:16%;" class="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($invoice->details as $d)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $d->description }}</td>
                                        <td class="text-right">{{ $d->unit_price_formatted }}</td>
                                        <td class="text-center">{{ $d->qty }}</td>
                                        <td class="text-right text-danger">{{ $d->deduction_formatted }}</td>
                                        <td class="text-right"><strong>{{ $d->total_formatted }}</strong></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada rincian.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="background:#F9FAFB;">
                                        <th colspan="5" class="text-right" style="font-size:12px;">Captured Total</th>
                                        <th class="text-right" style="font-size:12px;color:#2563EB;">{{ $invoice->captured_total_formatted }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- LAMPIRAN PO -->
                @php $poAttachments = $invoice->po->attachments->where('type', 'general')->values() ?? collect(); @endphp
                <div class="card card-info card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran Purchase Order</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:5%;" class="text-center">No</th>
                                        <th>Judul</th>
                                        <th style="width:10%;" class="text-center">Aksi</th>
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
                </div>

                <!-- LAMPIRAN INVOICE -->
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Dokumen Pendukung Invoice</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:5%;" class="text-center">No</th>
                                        <th>Judul</th>
                                        <th style="width:20%;">Diupload Oleh</th>
                                        <th style="width:10%;" class="text-center">Aksi</th>
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
                </div>

                <!-- CASH RECEIPT -->
                <div class="card card-success card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title"><i class="fas fa-money-check-alt mr-1"></i> Cash Receipt</h3>
                        @if($canAddCr)
                        <button type="button" class="btn btn-success btn-sm ml-auto" id="btnAddCashReceipt">
                            <i class="fas fa-plus mr-1"></i> Tambah Cash Receipt
                        </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:4%;" class="text-center">No</th>
                                        <th style="width:12%;">Tanggal</th>
                                        <th style="width:16%;" class="text-right">Jumlah</th>
                                        <th style="width:18%;">Sumber Dana</th>
                                        <th>Catatan</th>
                                        <th style="width:12%;">Input Oleh</th>
                                        <th style="width:10%;" class="text-center">Bukti</th>
                                        @if($canDeleteCr)
                                        <th style="width:6%;" class="text-center">Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($invoice->cashReceipts as $cr)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $cr->received_date ? $cr->received_date->format('d/m/Y') : '-' }}</td>
                                        <td class="text-right"><strong class="text-success">{{ $cr->amount_formatted }}</strong></td>
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
                                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                        @endif
                                    </tr>
                                    @empty
                                    <tr><td colspan="{{ $canDeleteCr ? 8 : 7 }}" class="text-center text-muted py-3">Belum ada pembayaran diterima.</td></tr>
                                    @endforelse
                                </tbody>
                                @if($invoice->cashReceipts->count())
                                <tfoot>
                                    <tr style="background:#F9FAFB;">
                                        <th colspan="2" class="text-right" style="font-size:12px;">Total Diterima</th>
                                        <th class="text-right" style="font-size:12px;color:#16a34a;">Rp {{ number_format($received, 0, ',', '.') }}</th>
                                        <th colspan="{{ $canDeleteCr ? 5 : 4 }}"></th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                    @if(!$canAddCr && !in_array($invoice->status, ['PAID']))
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Cash Receipt hanya bisa ditambahkan untuk invoice berstatus <strong>SENT</strong> atau <strong>PARTIALLY PAID</strong>.
                        </small>
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>

{{-- MODAL TAMBAH CASH RECEIPT --}}
<div class="modal fade" id="mdlAddCashReceipt" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-money-check-alt mr-2" style="color:#F5A623;"></i> Tambah Cash Receipt</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('invoice-cash-receipt.cash-receipt.store', $invoice->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">

                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        Sisa tagihan saat ini: <strong>Rp {{ number_format($balance, 0, ',', '.') }}</strong>
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
                        <input type="file" name="proof_file" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>

                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL REVISE --}}
<div class="modal fade" id="mdlReviseInv" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-undo mr-2" style="color:#F5A623;"></i> Kembalikan untuk Revisi</h5>
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
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-ban mr-2" style="color:#F5A623;"></i> Batalkan Invoice</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('invoice-cash-receipt.cancel', $invoice->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Invoice <strong>{{ $invoice->code }}</strong> akan dibatalkan dan tidak dapat diproses lagi.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="3"
                                  placeholder="Tuliskan alasan pembatalan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
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
.badge-indigo     { color:#fff; background-color:#6610f2; }
.btn-indigo       { color:#fff; background-color:#6610f2; border-color:#6610f2; }
.btn-indigo:hover { color:#fff; background-color:#560bd0; border-color:#5209c4; }
.font-weight-600  { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }
.timeline-compact { position:relative; }
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

    $('#btnAddCashReceipt').on('click', function () { $('#mdlAddCashReceipt').modal('show'); });
    $('#btnReviseInv').on('click', function () { $('#mdlReviseInv').modal('show'); });
    $('#btnCancelInv').on('click', function () { $('#mdlCancelInv').modal('show'); });

    /* CONFIRMATIONS */
    $(document).on('submit', '.frmSubmitInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit Invoice?', text:'Invoice akan dikirim untuk diverifikasi Finance Manager.', icon:'question', showCancelButton:true, confirmButtonColor:'#007bff', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmVerifyInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Verifikasi Invoice?', text:'Status akan berubah menjadi VERIFIED.', icon:'question', showCancelButton:true, confirmButtonColor:'#28a745', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Verifikasi', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmApproveInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Approve Invoice?', text:'Status akan berubah menjadi APPROVED.', icon:'question', showCancelButton:true, confirmButtonColor:'#28a745', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Approve', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmSentInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Tandai Sudah Terkirim?', text:'Invoice akan ditandai sudah dikirim ke customer.', icon:'question', showCancelButton:true, confirmButtonColor:'#6610f2', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Kirim', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmDeleteInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Invoice?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmDeleteCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Cash Receipt?', text:'Status invoice akan disesuaikan ulang setelah data ini dihapus.', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

});
</script>
@endpush