@extends('layouts.app')

@section('title', 'Detail Batch')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <a href="{{ url('/daily-transaction') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold">Detail Batch</h1>
                    <small class="text-muted">
                        <span class="badge badge-dark">{{ $batch->code }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $batch->status_color }}">{{ $batch->status_label }}</span>
                        &nbsp;·&nbsp;
                        {{ $batch->date->format('d M Y') }}
                    </small>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/daily-transaction') }}">Daily Transaction</a></li>
                    <li class="breadcrumb-item active">Detail</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">

            <!-- ============================================================ -->
            <!-- KIRI: INFO BATCH                                              -->
            <!-- ============================================================ -->
            <div class="col-md-3">
                <div class="card shadow-sm" style="border-radius:10px;overflow:hidden;">
                    <div style="background:#0D1B2A;padding:24px 20px;">

                        <div class="text-center mb-3">
                            <div style="width:60px;height:60px;background:#1D2D3F;border:3px solid #F5A623;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-layer-group" style="color:#F5A623;font-size:22px;"></i>
                            </div>
                            <div class="mt-2" style="font-size:10px;color:#5A7080;word-break:break-all;">{{ $batch->code }}</div>
                            <div class="mt-1">
                                <span class="badge badge-{{ $batch->status_color }} px-3 py-1">{{ $batch->status_label }}</span>
                            </div>
                        </div>

                        <hr style="border-color:rgba(255,255,255,0.08);">

                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Tanggal</span>
                            <div class="text-white mt-1" style="font-size:13px;">{{ $batch->date->format('d M Y') }}</div>
                        </div>
                        @if($batch->description)
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Deskripsi</span>
                            <div class="text-white mt-1" style="font-size:12px;">{{ $batch->description }}</div>
                        </div>
                        @endif
                        @if($batch->sourceFund)
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Source Fund</span>
                            <div class="text-white mt-1" style="font-size:12px;">
                                {{ $batch->sourceFund->name }}
                                <div style="color:#8A9BB0;font-size:11px;">
                                    {{ $batch->sourceFund->bank->name ?? '-' }} — {{ $batch->sourceFund->no_account ?? '-' }}
                                </div>
                            </div>
                        </div>
                        @endif
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total CR</span>
                            <div style="color:#F5A623;font-size:24px;font-weight:700;margin-top:4px;">
                                {{ $batch->includedItems()->count() }}
                                <span style="font-size:12px;color:#5A7080;">/ {{ $batch->items->count() }}</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Nilai Batch</span>
                            <div style="color:#F5A623;font-size:16px;font-weight:700;margin-top:4px;">{{ $batch->captured_amount_formatted }}</div>
                        </div>
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Charge</span>
                            <div class="text-white mt-1" style="font-size:14px;font-weight:600;">{{ $batch->charge_formatted }}</div>
                        </div>
                        <div class="mb-3 pb-3" style="border-bottom:1px solid rgba(255,255,255,0.08);">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total Ditransfer <small>(Nilai Batch + Charge)</small></span>
                            <div style="color:#28a745;font-size:18px;font-weight:700;margin-top:4px;">{{ $batch->grand_total_formatted }}</div>
                        </div>
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Dibuat Oleh</span>
                            <div class="text-white mt-1" style="font-size:12px;">{{ $batch->createdBy->name ?? '-' }}</div>
                        </div>
                        @if($batch->approvedBy)
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Approved Oleh</span>
                            <div class="text-white mt-1" style="font-size:12px;">{{ $batch->approvedBy->name }}</div>
                            <div style="color:#8A9BB0;font-size:11px;">{{ $batch->approved_at?->format('d M Y H:i') }}</div>
                        </div>
                        @endif
                        @if($batch->paidBy)
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Paid Oleh</span>
                            <div class="text-white mt-1" style="font-size:12px;">{{ $batch->paidBy->name }}</div>
                            <div style="color:#8A9BB0;font-size:11px;">{{ $batch->paid_at?->format('d M Y H:i') }}</div>
                        </div>
                        @endif
                        @if($batch->notes)
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Notes</span>
                            <div class="text-white mt-1" style="font-size:12px;">{{ $batch->notes }}</div>
                        </div>
                        @endif
                        @if($batch->payment_notes)
                        <div class="mb-3">
                            <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Payment Notes</span>
                            <div class="text-white mt-1" style="font-size:12px;">{{ $batch->payment_notes }}</div>
                        </div>
                        @endif

                    </div>

                    {{-- APPROVER ACTIONS --}}
                    @if($batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']))
                    <div class="card-footer p-3" style="background:#f8f9fa;">
                        <button type="button" class="btn btn-success btn-block mb-2 btn-approve-batch"
                                data-id="{{ $batch->id }}" data-code="{{ $batch->code }}">
                            <i class="fas fa-check mr-1"></i> Approve ({{ $batch->includedItems()->count() }} CR)
                        </button>
                        <button type="button" class="btn btn-danger btn-block btn-reject-batch"
                                data-id="{{ $batch->id }}" data-code="{{ $batch->code }}">
                            <i class="fas fa-times mr-1"></i> Tolak Seluruh Batch
                        </button>
                    </div>
                    @endif

                    {{-- MARK PAID — finance, batch APPROVED --}}
                    @if($batch->isApproved() && Auth::user()->hasRole(['superadmin','finance']))
                    <div class="card-footer p-3" style="background:#f8f9fa;">
                        <button type="button" class="btn btn-success btn-block" id="btnMarkPaid">
                            <i class="fas fa-money-bill-wave mr-1"></i>
                            Tandai PAID ({{ $batch->includedItems()->count() }} CR)
                        </button>
                    </div>
                    @endif

                </div>

                {{-- LAMPIRAN BATCH (jika sudah PAID) --}}
                @if($batch->attachments->count() > 0)
                <div class="card shadow-sm mt-3">
                    <div class="card-header py-2">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-paperclip mr-1"></i> Lampiran Pembayaran
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        @foreach($batch->attachments as $att)
                        <div class="d-flex align-items-center justify-content-between p-2"
                             style="border-bottom:1px solid #f0f0f0;">
                            <div>
                                <div style="font-size:11px;font-weight:600;">{{ $att->original_name }}</div>
                                <div style="font-size:10px;color:#6B7280;">{{ $att->size_formatted }}</div>
                            </div>
                            <a href="{{ asset('storage/' . $att->path) }}" target="_blank"
                               class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- TAMBAH CR (finance, batch PENDING_APPROVER) --}}
                @if($batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','finance']) && $availableCRs->isNotEmpty())
                <div class="card card-success card-outline shadow-sm mt-3">
                    <div class="card-header py-2">
                        <h6 class="card-title mb-0"><i class="fas fa-plus-circle mr-1"></i> Tambah CR</h6>
                    </div>
                    <form action="{{ url('/daily-transaction/' . $batch->id . '/add-item') }}" method="POST">
                        @csrf
                        <div class="card-body p-0">
                            <div style="max-height:300px;overflow-y:auto;">
                                @foreach($availableCRs as $cr)
                                <label class="d-flex align-items-start p-2 mb-0"
                                       style="border-bottom:1px solid #f0f0f0;cursor:pointer;gap:8px;">
                                    <input type="checkbox" name="cr_ids[]" value="{{ $cr->id }}"
                                           style="width:14px;height:14px;margin-top:3px;flex-shrink:0;accent-color:#28a745;">
                                    <div>
                                        <div style="font-size:10px;font-weight:700;">{{ $cr->code }}</div>
                                        <div style="font-size:10px;color:#374151;">{{ Str::limit($cr->description, 30) }}</div>
                                        <div style="font-size:10px;color:#2563EB;font-weight:700;">{{ $cr->amount_formatted }}</div>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-footer p-2">
                            <button type="submit" class="btn btn-success btn-sm btn-block">
                                <i class="fas fa-plus mr-1"></i> Tambahkan
                            </button>
                        </div>
                    </form>
                </div>
                @endif

            </div>

            <!-- ============================================================ -->
            <!-- KANAN: DAFTAR CR DALAM BATCH                                  -->
            <!-- ============================================================ -->
            <div class="col-md-9">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> Daftar CR dalam Batch</h3>

                        @php $canExport = $batch->status === 'APPROVED'; @endphp
                        <div class="ml-auto">
                            @if($canExport)
                                <a href="{{ url('/daily-transaction/' . $batch->id . '/export') }}"
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel mr-1"></i> Export Batch
                                </a>
                            @else
                                <button type="button" class="btn btn-success btn-sm" disabled
                                        title="Export hanya tersedia saat batch berstatus APPROVED">
                                    <i class="fas fa-file-excel mr-1"></i> Export Batch
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-nowrap" style="width:1%;">No</th>
                                        <th>Deskripsi</th>
                                        <th class="text-right text-nowrap" style="width:1%;">Amount</th>
                                        <th class="text-nowrap" style="width:1%;">Item</th>
                                        <th class="text-nowrap" style="width:1%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($batch->items as $i => $item)
                                    @php /** @var \App\Models\DailyTransactionItem $item */ @endphp
                                    <tr class="{{ $item->status === 'REJECTED' ? 'table-danger' : '' }}">
                                        <td class="text-nowrap">{{ $i + 1 }}</td>
                                        <td>
                                            {{-- Deskripsi + kode CR --}}
                                            <div style="font-size:13px;font-weight:600;<?php echo $item->status === 'REJECTED' ? 'text-decoration:line-through;color:#9CA3AF;' : ''; ?>">
                                                {{ Str::limit($item->budgetRequest->description ?? '-', 45) }}
                                            </div>
                                            @php
                                                $metaParts = [];
                                                $metaParts[] = $item->budgetRequest->code ?? '-';
                                                if ($item->budgetRequest->applicant ?? null) {
                                                    $metaParts[] = strtoupper($item->budgetRequest->applicant->username ?? $item->budgetRequest->applicant->name);
                                                }
                                                if ($item->budgetRequest->beneficiary ?? null) {
                                                    $metaParts[] = $item->budgetRequest->beneficiary->complete_name;
                                                }
                                            @endphp
                                            <small class="text-muted">{{ implode(' · ', $metaParts) }}</small>
                                        </td>
                                        <td class="text-right text-nowrap">
                                            <strong style="font-size:12px;">{{ $item->budgetRequest->amount_formatted ?? '-' }}</strong>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-{{ $item->status === 'REJECTED' ? 'danger' : 'success' }} px-2 py-1" style="font-size:9px;">
                                                {{ $item->status }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <div class="d-flex" style="gap:4px;">
                                                {{-- VIEW CR --}}
                                                <button type="button"
                                                        class="btn btn-info btn-xs btn-detail-cr"
                                                        data-id="{{ $item->budget_request_id }}"
                                                        title="Detail CR">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                {{-- TAKEOUT --}}
                                                @if($item->status === 'INCLUDED' && $batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']))
                                                <button type="button"
                                                        class="btn btn-danger btn-xs btn-reject-item"
                                                        data-batch-id="{{ $batch->id }}"
                                                        data-item-id="{{ $item->id }}"
                                                        data-cr-code="{{ $item->budgetRequest->code ?? '-' }}">
                                                    <i class="fas fa-times mr-1"></i> Takeout
                                                </button>
                                                @endif

                                                {{-- CATATAN JIKA REJECTED --}}
                                                @if($item->status === 'REJECTED' && $item->notes)
                                                <small class="text-danger align-self-center">
                                                    <i class="fas fa-comment-alt mr-1"></i>{{ Str::limit($item->notes, 20) }}
                                                </small>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Tidak ada CR dalam batch ini.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="background:#F9FAFB;">
                                        <th colspan="2" class="text-right">Total (INCLUDED)</th>
                                        <th class="text-right" style="color:#2563EB;">{{ $batch->total_amount_formatted }}</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL MARK PAID -->
<div class="modal fade" id="mdlMarkPaid" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-money-bill-wave mr-2" style="color:#F5A623;"></i> Tandai Batch sebagai PAID
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmMarkPaid" action="{{ url('/daily-transaction/' . $batch->id . '/mark-paid') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-info mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ $batch->includedItems()->count() }} CR dalam batch ini akan berstatus <strong>PAID</strong>.
                        Upload minimal 1 file bukti pembayaran.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Catatan Pembayaran</label>
                        <input type="text" name="payment_notes" class="form-control"
                               placeholder="Nomor referensi / catatan transfer...">
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600">
                            Lampiran Bukti Pembayaran <span class="text-danger">*</span>
                        </label>
                        <div id="attachmentList">
                            <div class="att-row mb-2 d-flex align-items-center" style="gap:8px;">
                                <input type="file" name="attachments[]"
                                       class="form-control-file flex-grow-1"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                                <button type="button" class="btn btn-xs btn-danger btn-remove-att">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1" id="btnAddAtt">
                            <i class="fas fa-plus mr-1"></i> Tambah File
                        </button>
                        <small class="text-muted d-block mt-1">Format: pdf, jpg, png. Maks 10 MB per file.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-money-bill-wave mr-1"></i> Tandai PAID
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL APPROVE BATCH -->
<div class="modal fade" id="mdlApproveBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:#F5A623;"></i> Approve Batch</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmApproveBatch" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="approveBatchText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT BATCH -->
<div class="modal fade" id="mdlRejectBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:#F5A623;"></i> Tolak Seluruh Batch</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectBatch" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan penolakan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT ITEM -->
<div class="modal fade" id="mdlRejectItem" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-minus-circle mr-2" style="color:#F5A623;"></i> Takeout CR</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectItem" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="rejectItemText" class="text-muted mb-3"></p>
                    <div class="alert alert-info mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        CR akan dikembalikan ke status <strong>VERIFIED</strong> dan bisa masuk batch berikutnya.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan takeout..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-minus-circle mr-1"></i> Takeout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL CR -->
<div class="modal fade" id="mdlDetailCR" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:#F5A623;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-file-invoice-dollar" style="color:#0D1B2A;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="crModalTitle">Detail CR</h5>
                        <small id="crModalSubtitle" style="color:#8A9BB0;font-size:11px;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.7;"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;background:#F0F2F5;">
                <div id="crDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#F5A623;"></i>
                    <p class="text-muted mt-3 mb-0">Memuat data...</p>
                </div>
                <div id="crDetailContent" style="display:none;">
                    <div class="row no-gutters">
                        <div class="col-md-3" style="background:#0D1B2A;padding:28px 20px;min-height:400px;">
                            <div class="text-center mb-3">
                                <div style="width:56px;height:56px;background:#1D2D3F;border:3px solid #F5A623;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-file-invoice-dollar" style="color:#F5A623;font-size:20px;"></i>
                                </div>
                                <div id="crCode" class="mt-2" style="font-size:10px;color:#5A7080;word-break:break-all;"></div>
                                <div id="crStatusBadge" class="mt-1"></div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Tipe</span><div id="crType" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total</span><div id="crAmount" style="color:#F5A623;font-size:18px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span><div id="crBeneficiary" class="text-white mt-1" style="font-size:12px;"></div><div id="crBank" style="color:#8A9BB0;font-size:11px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Pemohon</span><div id="crApplicant" class="text-white mt-1" style="font-size:12px;"></div></div>
                        </div>
                        <div class="col-md-9" style="padding:20px;">
                            <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid #E5E7EB;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo" style="font-size:13px;font-weight:600;"><i class="fas fa-info-circle mr-1"></i> Informasi</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails" style="font-size:13px;font-weight:600;"><i class="fas fa-list-ul mr-1"></i> Rincian <span id="crDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs" style="font-size:13px;font-weight:600;"><i class="fas fa-history mr-1"></i> Log</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="crPaneInfo">
                                    <table class="table table-bordered mb-0" style="font-size:13px;">
                                        <tr><th width="30%" style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Kode</th><td id="infoCode"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Deskripsi</th><td id="infoDesc"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Tipe</th><td id="infoType"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Scope of Work</th><td id="infoScope"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Project</th><td id="infoCluster"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Budget Plan</th><td id="infoBudgetPlan"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Total</th><td id="infoAmount"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Status</th><td id="infoStatus"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Beneficiary</th><td id="infoBeneficiary"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Bank / Rekening</th><td id="infoBank"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Email Notif</th><td id="infoEmail"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Notes</th><td id="infoNotes" style="white-space:pre-line;"></td></tr>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="crPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:13px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Deskripsi</th>
                                                    <th style="color:#6B7280;font-size:11px;">Account</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:15%;">Satuan</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:center;width:6%;">Qty</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:15%;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:#F9FAFB;"><th colspan="5" class="text-right">Total</th><th class="text-right" id="crDetailTotal" style="color:#2563EB;"></th></tr></tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneLogs">
                                    <div id="crLogBody"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#F8F9FA;padding:12px 24px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="includedCountHolder" value="{{ $batch->includedItems()->count() }}">

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td,.table th { vertical-align:middle !important; }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:4px; }
#crDetailTabs .nav-link { color:#6B7280; border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#crDetailTabs .nav-link.active { color:#F5A623 !important; border-bottom-color:#F5A623 !important; background:transparent !important; font-weight:700; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    // MARK PAID — buka modal
    $('#btnMarkPaid').on('click', function () {
        $('#mdlMarkPaid').modal('show');
    });

    // MARK PAID — tambah file
    $('#btnAddAtt').on('click', function () {
        $('#attachmentList').append(
            '<div class="att-row mb-2 d-flex align-items-center" style="gap:8px;">' +
            '<input type="file" name="attachments[]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png">' +
            '<button type="button" class="btn btn-xs btn-danger btn-remove-att"><i class="fas fa-trash"></i></button>' +
            '</div>'
        );
    });

    $(document).on('click', '.btn-remove-att', function () {
        if ($('.att-row').length > 1) $(this).closest('.att-row').remove();
    });

    // APPROVE BATCH
    $(document).on('click', '.btn-approve-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        var count = parseInt($('#includedCountHolder').val()) || 0;
        $('#approveBatchText').text('Approve batch ' + code + '? ' + count + ' CR akan berstatus QUEUED.');
        $('#frmApproveBatch').attr('action', '/daily-transaction/' + id + '/approve');
        $('#frmApproveBatch textarea[name="notes"]').val('');
        $('#mdlApproveBatch').modal('show');
    });

    // REJECT BATCH
    $(document).on('click', '.btn-reject-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#frmRejectBatch').attr('action', '/daily-transaction/' + id + '/reject-batch');
        $('#frmRejectBatch textarea[name="notes"]').val('');
        $('#mdlRejectBatch').modal('show');
    });

    // REJECT ITEM (takeout)
    $(document).on('click', '.btn-reject-item', function () {
        var batchId = $(this).data('batch-id');
        var itemId  = $(this).data('item-id');
        var crCode  = $(this).data('cr-code');
        $('#rejectItemText').text('Takeout CR ' + crCode + ' dari batch ini?');
        $('#frmRejectItem').attr('action', '/daily-transaction/' + batchId + '/reject-item/' + itemId);
        $('#frmRejectItem textarea[name="notes"]').val('');
        $('#mdlRejectItem').modal('show');
    });

    // DETAIL CR
    var crStatusColors = {
        'DRAFT':'secondary','SUBMITTED':'warning','APPROVED':'info',
        'VERIFIED':'primary','QUEUED':'warning','PAID':'success',
        'REALIZED':'success','CLOSED':'dark','REJECTED':'danger'
    };

    $(document).on('click', '.btn-detail-cr', function () {
        var id = $(this).data('id');
        $('#crDetailLoading').show();
        $('#crDetailContent').hide();
        $('#mdlDetailCR').modal('show');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#crModalTitle').text(d.description);
            $('#crModalSubtitle').text(d.code);
            $('#crCode').text(d.code);
            $('#crStatusBadge').html('<span class="badge badge-' + (crStatusColors[d.status]||'secondary') + ' px-3 py-1">' + d.status + '</span>');
            $('#crType').text(d.type);
            $('#crAmount').text(d.amount_formatted);
            $('#crBeneficiary').text(d.beneficiary);
            $('#crBank').text(d.bank + ' — ' + d.account_number);
            $('#crApplicant').text(d.applicant);

            $('#infoCode').html('<span class="badge badge-dark px-2 py-1">' + d.code + '</span>');
            $('#infoDesc').text(d.description);
            $('#infoType').html('<span class="badge badge-info px-2 py-1">' + d.type + '</span>');
            $('#infoScope').text(d.project || '-');
            $('#infoCluster').text(d.cluster || '-');
            $('#infoBudgetPlan').text(d.budget_plan || '-');
            $('#infoAmount').html('<strong style="color:#2563EB;">' + d.amount_formatted + '</strong>');
            $('#infoStatus').html('<span class="badge badge-' + (d.status_color || 'secondary') + ' px-2 py-1">' + d.status + '</span>');
            $('#infoBeneficiary').text(d.beneficiary);
            $('#infoBank').text(d.bank + ' — ' + d.account_number);
            $('#infoEmail').text(d.email || '-');
            $('#infoNotes').text(d.notes || '-');

            $('#crDetailCount').text(d.details.length);
            var dHtml = '', grand = 0;
            d.details.forEach(function(det, i) {
                grand += det.total;
                dHtml += '<tr><td class="text-center">' + (i+1) + '</td><td>' + det.description + '</td><td>' + det.account + '</td><td class="text-right">' + det.amount_formatted + '</td><td class="text-center">' + det.qty + '</td><td class="text-right"><strong>' + det.total_formatted + '</strong></td></tr>';
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada rincian.</td></tr>');
            $('#crDetailTotal').text('Rp ' + grand.toLocaleString('id-ID'));

            var logHtml = '';
            d.logs.forEach(function(log) {
                logHtml += '<div class="d-flex align-items-start mb-3" style="gap:10px;">' +
                    '<span class="badge badge-' + log.action_color + ' mt-1 px-2 py-1" style="font-size:10px;white-space:nowrap;">' + log.action_label + '</span>' +
                    '<div><div style="font-size:12px;font-weight:600;">' + log.actor + ' <span style="color:#9CA3AF;font-weight:400;">(' + (log.role||'system') + ')</span></div>' +
                    '<div style="font-size:11px;color:#6B7280;">' + log.date + '</div>' +
                    (log.notes ? '<div style="font-size:12px;margin-top:4px;padding:6px 10px;background:#F9FAFB;border-radius:6px;border:1px solid #E5E7EB;">' + log.notes + '</div>' : '') +
                    '</div></div>';
            });
            $('#crLogBody').html(logHtml || '<p class="text-muted py-3 mb-0">Belum ada log.</p>');

            $('#crDetailTabs a:first').tab('show');
            $('#crDetailLoading').hide();
            $('#crDetailContent').show();
        }).fail(function () {
            $('#crDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    $('#mdlDetailCR').on('hidden.bs.modal', function () {
        $('#crDetailContent').hide();
        $('#crDetailLoading').show();
    });

});
</script>
@endpush