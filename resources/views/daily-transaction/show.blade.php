@extends('layouts.app')

@section('title', 'Detail Batch')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/daily-transaction') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Batch</h1>
                    <small class="text-muted">
                        <span class="badge badge-dark">{{ $batch->code }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $batch->type_color }}">{{ $batch->type_label }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $batch->status_color }}">{{ $batch->status_label }}</span>
                        &nbsp;·&nbsp;
                        {{ $batch->date->format('d M Y') }}
                    </small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/daily-transaction') }}">Daily Transaction</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="row">

            <!-- ============================================================ -->
            <!-- ATAS: INFO BATCH (full width)                                 -->
            <!-- ============================================================ -->
            <div class="col-12">
                <div class="card shadow-sm mb-3" style="border-radius:var(--radius-lg);overflow:hidden;">
                    <div style="background:var(--charcoal);padding:20px 24px;">
                        <div class="d-flex flex-wrap align-items-center" style="gap:20px;">

                            <div class="d-flex align-items-center" style="gap:12px;flex-shrink:0;">
                                <div style="width:50px;height:50px;background:var(--charcoal-2);border:3px solid var(--accent);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-layer-group" style="color:var(--accent);font-size:18px;"></i>
                                </div>
                                <div>
                                    <div style="font-size:10px;color:var(--text-faint);">{{ $batch->code }}</div>
                                    <span class="badge badge-{{ $batch->status_color }} px-2 py-1 mt-1" style="font-size:10px;">{{ $batch->status_label }}</span>
                                </div>
                            </div>

                            <div style="width:1px;align-self:stretch;background:rgba(255,255,255,0.08);"></div>

                            <div class="d-flex flex-wrap align-items-start" style="gap:24px;flex:1;">
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Tanggal</span>
                                    <div class="text-white mt-1" style="font-size:13px;">{{ $batch->date->format('d M Y') }}</div>
                                </div>
                                @if($batch->description)
                                <div style="max-width:220px;">
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Deskripsi</span>
                                    <div class="text-white mt-1" style="font-size:12px;">{{ $batch->description }}</div>
                                </div>
                                @endif
                                @if($batch->isTopUp())
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Dari (Main Account)</span>
                                    <div class="text-white mt-1" style="font-size:12px;">
                                        {{ $batch->sourceFund->name ?? '-' }}
                                        <div style="color:var(--text-faint);font-size:11px;">
                                            {{ $batch->sourceFund->bank->name ?? '-' }} — {{ $batch->sourceFund->no_account ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Ke (Rekening Operasional)</span>
                                    <div class="text-white mt-1" style="font-size:12px;">
                                        {{ $batch->toSourceFund->name ?? '-' }}
                                        <div style="color:var(--text-faint);font-size:11px;">
                                            {{ $batch->toSourceFund->bank->name ?? '-' }} — {{ $batch->toSourceFund->no_account ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                                @else
                                    @if($batch->sourceFund)
                                    <div>
                                        <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Source Fund</span>
                                        <div class="text-white mt-1" style="font-size:12px;">
                                            {{ $batch->sourceFund->name }}
                                            <div style="color:var(--text-faint);font-size:11px;">
                                                {{ $batch->sourceFund->bank->name ?? '-' }} — {{ $batch->sourceFund->no_account ?? '-' }}
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    <div>
                                        <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total CR</span>
                                        <div style="color:var(--accent);font-size:20px;font-weight:700;margin-top:2px;">
                                            {{ $batch->includedItems()->count() }}
                                            <span style="font-size:12px;color:var(--text-faint);">/ {{ $batch->items->count() }}</span>
                                        </div>
                                    </div>
                                @endif
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Nilai Batch</span>
                                    <div style="color:var(--accent);font-size:15px;font-weight:700;margin-top:2px;">{{ $batch->captured_amount_formatted }}</div>
                                </div>
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Charge</span>
                                    <div class="text-white mt-1" style="font-size:13px;font-weight:600;">{{ $batch->charge_formatted }}</div>
                                </div>
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Ditransfer <small>(Nilai Batch + Charge)</small></span>
                                    <div style="color:var(--success);font-size:15px;font-weight:700;margin-top:2px;">{{ $batch->grand_total_formatted }}</div>
                                </div>
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Dibuat Oleh</span>
                                    <div class="text-white mt-1" style="font-size:12px;">{{ $batch->createdBy->name ?? '-' }}</div>
                                </div>
                                @if($batch->approvedBy)
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Approved Oleh</span>
                                    <div class="text-white mt-1" style="font-size:12px;">{{ $batch->approvedBy->name }}</div>
                                    <div style="color:var(--text-faint);font-size:11px;">{{ $batch->approved_at?->format('d M Y H:i') }}</div>
                                </div>
                                @endif
                                @if($batch->paidBy)
                                <div>
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Paid Oleh</span>
                                    <div class="text-white mt-1" style="font-size:12px;">{{ $batch->paidBy->name }}</div>
                                    <div style="color:var(--text-faint);font-size:11px;">{{ $batch->paid_at?->format('d M Y H:i') }}</div>
                                </div>
                                @endif
                                @if($batch->notes)
                                <div style="max-width:220px;">
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Notes</span>
                                    <div class="text-white mt-1" style="font-size:12px;">{{ $batch->notes }}</div>
                                </div>
                                @endif
                                @if($batch->payment_notes)
                                <div style="max-width:220px;">
                                    <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Payment Notes</span>
                                    <div class="text-white mt-1" style="font-size:12px;">{{ $batch->payment_notes }}</div>
                                </div>
                                @endif
                            </div>

                        </div>
                    </div>

                    {{-- APPROVER ACTIONS --}}
                    @if($batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']))
                    <div class="card-footer p-3 d-flex flex-wrap" style="background:var(--surface-2);gap:10px;">
                        <button type="button" class="btn btn-success btn-approve-batch"
                                data-id="{{ $batch->id }}" data-code="{{ $batch->code }}">
                            <i class="fas fa-check mr-1"></i> Approve ({{ $batch->isTopUp() ? 'Top Up' : $batch->includedItems()->count() . ' CR' }})
                        </button>
                        <button type="button" class="btn btn-danger btn-reject-batch"
                                data-id="{{ $batch->id }}" data-code="{{ $batch->code }}">
                            <i class="fas fa-times mr-1"></i> Tolak Seluruh Batch
                        </button>
                    </div>
                    @endif

                    {{-- MARK PAID — finance/treasury, batch DONE --}}
                    @if($batch->isDone() && Auth::user()->hasRole(['superadmin','finance','finance_manager','treasury']))
                    <div class="card-footer p-3" style="background:var(--surface-2);">
                        <button type="button" class="btn btn-success" id="btnMarkPaid">
                            <i class="fas fa-money-bill-wave mr-1"></i>
                            Tandai PAID ({{ $batch->isTopUp() ? 'Top Up' : $batch->includedItems()->count() . ' CR' }})
                        </button>
                    </div>
                    @endif

                </div>

                <div class="row">
                    {{-- LAMPIRAN BATCH (jika sudah PAID) --}}
                    @if($batch->attachments->count() > 0)
                    <div class="col-md-6 mb-3">
                        <div class="card shadow-sm h-100 mb-0">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-paperclip mr-1"></i> Lampiran Pembayaran
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                @foreach($batch->attachments as $att)
                                <div class="d-flex align-items-center justify-content-between p-2"
                                     style="border-bottom:1px solid var(--border);">
                                    <div>
                                        <div style="font-size:11px;font-weight:600;">{{ $att->original_name }}</div>
                                        <div style="font-size:10px;color:var(--text-faint);">{{ $att->size_formatted }}</div>
                                    </div>
                                    <a href="{{ asset('storage/' . $att->path) }}" target="_blank"
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- {{-- TAMBAH CR (finance, batch PENDING_APPROVER) --}}
                    @if($batch->isCashOut() && $batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','finance','finance_manager']) && $availableCRs->isNotEmpty())
                    <div class="col-md-6 mb-3">
                        <div class="card card-success card-outline shadow-sm h-100 mb-0">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0"><i class="fas fa-plus-circle mr-1"></i> Tambah CR</h6>
                            </div>
                            <form action="{{ url('/daily-transaction/' . $batch->id . '/add-item') }}" method="POST">
                                @csrf
                                <div class="card-body p-0">
                                    <div style="max-height:300px;overflow-y:auto;">
                                        @foreach($availableCRs as $cr)
                                        <label class="d-flex align-items-start p-2 mb-0"
                                               style="border-bottom:1px solid var(--border);cursor:pointer;gap:8px;">
                                            <input type="checkbox" name="cr_ids[]" value="{{ $cr->id }}"
                                                   style="width:14px;height:14px;margin-top:3px;flex-shrink:0;accent-color:var(--success);">
                                            <div>
                                                <div style="font-size:10px;font-weight:700;">{{ $cr->code }}</div>
                                                <div style="font-size:10px;color:var(--text);">{{ Str::limit($cr->description, 30) }}</div>
                                                <div style="font-size:10px;color:var(--info);font-weight:700;">{{ $cr->amount_formatted }}</div>
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
                    </div>
                    @endif -->
                </div>

            </div>

            <!-- ============================================================ -->
            <!-- DAFTAR CR DALAM BATCH (full width)                            -->
            <!-- ============================================================ -->
            <div class="col-12">
                @if($batch->isTopUp())
                <div class="card card-info card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-exchange-alt mr-1"></i> Detail Transfer</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-5">
                                <div style="font-size:10px;color:var(--text-faint);text-transform:uppercase;font-weight:700;">Dari</div>
                                <div style="font-size:15px;font-weight:700;margin-top:4px;">{{ $batch->sourceFund->name ?? '-' }}</div>
                                <div style="font-size:11px;color:var(--text-faint);">{{ $batch->sourceFund->bank->name ?? '-' }} — {{ $batch->sourceFund->no_account ?? '-' }}</div>
                            </div>
                            <div class="col-md-2 d-flex align-items-center justify-content-center">
                                <i class="fas fa-arrow-right" style="font-size:20px;color:var(--accent);"></i>
                            </div>
                            <div class="col-md-5">
                                <div style="font-size:10px;color:var(--text-faint);text-transform:uppercase;font-weight:700;">Ke</div>
                                <div style="font-size:15px;font-weight:700;margin-top:4px;">{{ $batch->toSourceFund->name ?? '-' }}</div>
                                <div style="font-size:11px;color:var(--text-faint);">{{ $batch->toSourceFund->bank->name ?? '-' }} — {{ $batch->toSourceFund->no_account ?? '-' }}</div>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <div style="font-size:10px;color:var(--text-faint);text-transform:uppercase;font-weight:700;">Jumlah Transfer</div>
                            <div style="font-size:24px;font-weight:700;color:var(--text);margin-top:4px;">{{ $batch->captured_amount_formatted }}</div>
                        </div>
                    </div>
                </div>
                @else
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> Daftar CR dalam Batch</h3>

                        @php $canExport = !in_array($batch->status, ['DONE', 'PAID']); @endphp
                        <div class="ml-auto d-flex" style="gap:8px;">
                            <a href="{{ url('/daily-transaction/' . $batch->id . '/export-data') }}"
                               class="btn btn-info btn-sm">
                                <i class="fas fa-file-excel mr-1"></i> Export Data
                            </a>
                            @if($canExport)
                                <a href="{{ url('/daily-transaction/' . $batch->id . '/export') }}"
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel mr-1"></i> Export Batch
                                </a>
                            @else
                                <button type="button" class="btn btn-success btn-sm" disabled
                                        title="Export tidak tersedia lagi setelah batch DONE/PAID">
                                    <i class="fas fa-file-excel mr-1"></i> Export Batch
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body" style="padding:18px 20px;">
                        @php
                            $NOB_RED = 'var(--danger)'; $NOB_YELLOW = 'var(--warning)'; $OK_GREEN = 'var(--success)';
                            $fmt = fn ($v) => number_format($v, 0, ',', '.');
                        @endphp
                        <div style="padding-bottom:6px;">
                            <button type="button" class="btn btn-link dt-toggle-all p-0" style="font-size:11.5px;font-weight:600;text-decoration:none;" data-state="collapsed">
                                <i class="fas fa-expand-alt mr-1"></i>Expand All
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                        <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                        <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="dtItemsBody">
                                    @forelse($groupedItems as $g => $group)
                                    <tr class="dt-group-row" data-target="dt-grp-{{ $g }}" style="cursor:pointer;background:var(--info-bg);">
                                        <td colspan="3" style="font-weight:700;">
                                            <i class="fas fa-chevron-right dt-caret mr-2" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>
                                            {{ $group['label'] }}
                                            <span class="text-muted" style="font-size:11px;">({{ $group['pm'] }})</span>
                                            <span class="badge badge-secondary ml-1" style="font-size:9px;">{{ $group['items']->count() }} CR</span>
                                        </td>
                                        <td class="text-right text-nowrap" style="font-weight:700;">{{ $fmt($group['subtotal']) }}</td>
                                        <td class="text-center text-nowrap">
                                            <button aria-label="View Budget" type="button" class="btn btn-outline-secondary btn-sm dt-view-budget" data-target="#mdlDtCoa-{{ $g }}" style="padding:1px 8px;font-size:10.5px;" title="View Budget">
                                                <i class="fas fa-chart-pie"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    @foreach($group['items'] as $item)
                                    @php
                                        // MN-09 — baris batch bisa berasal dari Cost
                                        // Request ATAU Cost Register. Perbedaannya
                                        // diselesaikan di sini sekali, lewat helper
                                        // seragam di DailyTransactionItem.
                                        $isRegister = $item->isCostRegister();
                                        $isSecondPay = $item->isSecondPayment();
                                        $cr         = $item->budgetRequest;
                                        $isTakeout  = $item->status === 'REJECTED';
                                        $rowCode    = $item->sourceCode();
                                        $rowDesc    = $item->sourceDescription();
                                        $rowBen     = $item->sourceBeneficiaryName();
                                        $rowAmount  = number_format($item->sourceAmount(), 0, ',', '.');
                                        $rowUrl     = $isRegister
                                            ? url('/cost-register')
                                            : ('/budget-request?open=' . ($cr->id ?? ''));
                                    @endphp
                                    <tr class="dt-grp-{{ $g }} {{ $isTakeout ? 'dt-takeout-row' : '' }}" style="display:none;">
                                        <td class="text-nowrap">
                                            <a href="{{ $rowUrl }}" target="_blank" class="cr-number-btn">{{ $rowCode }}</a>
                                        </td>
                                        <td class="{{ $isTakeout ? 'dt-takeout-desc' : '' }}">
                                            @if($isRegister)
                                                <span class="badge badge-info mr-1" style="font-size:9px;">NON-CR</span>
                                            @elseif($isSecondPay)
                                                {{-- Baris ini membayar SELISIH Settlement, bukan nilai CR.
                                                     Ditandai jelas supaya tidak dibaca sebagai transfer ganda. --}}
                                                <span class="badge badge-warning mr-1" style="font-size:9px;" title="Kekurangan bayar hasil Settlement">2ND PAYMENT</span>
                                            @endif
                                            {{ Str::limit($rowDesc, 130) }}
                                            @if($isTakeout)<span class="badge badge-light border ml-1" style="font-size:9px;">Takeout</span>@endif
                                        </td>
                                        <td class="text-nowrap">{{ $rowBen }}</td>
                                        <td class="text-right text-nowrap"><strong>{{ $rowAmount }}</strong></td>
                                        <td class="text-center text-nowrap">
                                            @php $bolehTakeout = !$isTakeout && $batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']); @endphp
                                            <div class="action-buttons ts-actions">

                                                <a href="{{ $rowUrl }}" target="_blank"
                                                   class="ts-action-primary is-neutral"
                                                   title="{{ $isRegister ? 'Lihat Cost Register' : 'Lihat Cost Request' }}">
                                                    <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Lihat</span>
                                                </a>

                                                @if($bolehTakeout)
                                                <button type="button" class="ts-action-more"
                                                        aria-haspopup="true" aria-expanded="false"
                                                        title="Aksi lain" aria-label="Aksi lain untuk {{ $rowCode }}">
                                                    <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                                </button>
                                                <div class="ts-action-menu dropdown-menu" hidden>
                                                    <button type="button" class="dropdown-item is-danger btn-takeout-cr-item"
                                                            data-item-id="{{ $item->id }}" data-code="{{ $rowCode }}">
                                                        <i class="fas fa-times"></i>Takeout
                                                    </button>
                                                </div>
                                                @endif

                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Tidak ada CR dalam batch ini.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="background:var(--surface-2);">
                                        <th colspan="3" class="text-right">Total (INCLUDED)</th>
                                        <th class="text-right text-nowrap">{{ $fmt($groupedItems->sum('subtotal')) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- MODAL VIEW BUDGET — breakdown Chart of Account per grup (PO/Budget Plan), pola sama seperti Todo List --}}
                @foreach($groupedItems as $g => $group)
                <div class="modal fade" id="mdlDtCoa-{{ $g }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-xl" role="document">
                        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
                            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                                <div>
                                    <h5 class="modal-title text-white mb-0">{{ $group['label'] }} <small style="color:var(--text-faint);">({{ $group['pm'] }})</small></h5>
                                    @if($group['po_code'])
                                    <div style="color:var(--text-faint);font-size:11px;margin-top:2px;">PO Reference: <strong style="color:var(--accent);">{{ $group['po_code'] }}</strong></div>
                                    @endif
                                </div>
                                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body p-0" style="max-height:75vh;overflow-y:auto;">
                                <table class="table table-bordered mb-0" style="font-size:12px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account</th>
                                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Price</th>
                                            <th scope="col" class="text-center text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Qty</th>
                                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Budget</th>
                                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Used</th>
                                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Request</th>
                                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Variance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($group['coa_groups'] as $ci => $coa)
                                        @php
                                            $coaColorClass = $coa['remaining'] >= 0 ? 'dt-variance-pos' : 'dt-variance-neg';
                                            $coaNobClass = $coa['has_nob'] ? 'dt-variance-neg' : '';
                                            $coaClass = 'dtcoa-' . $g . '-' . $ci;
                                            $coaHasRequest = $coa['items']->contains(fn ($i) => $i['request_amount'] > 0);
                                        @endphp
                                        <tr class="dt-coa-row" data-target="{{ $coaClass }}" style="cursor:pointer;background:var(--surface-3);">
                                            <td colspan="3" class="{{ $coaNobClass }}" style="font-weight:700;">
                                                <button type="button" class="btn btn-outline-secondary" style="padding:1px 6px;margin-right:6px;" tabindex="-1">
                                                    <i class="fas fa-chevron-right dt-coa-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>
                                                </button>
                                                {{ $coa['account'] }}
                                                <span class="badge badge-secondary ml-1" style="font-size:9px;">{{ $coa['items']->count() }}</span>
                                                @if($coa['has_nob'])<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                                @if($coaHasRequest)<span class="badge badge-warning ml-1" style="font-size:9px;">Request</span>@endif
                                            </td>
                                            <td class="text-right text-nowrap {{ $coaNobClass }}" style="font-weight:700;">{{ $fmt($coa['budget']) }}</td>
                                            <td class="text-right text-nowrap {{ $coaNobClass }}" style="font-weight:700;">{{ $fmt($coa['used']) }}</td>
                                            <td class="text-right text-nowrap" style="font-weight:700;color:var(--warning);">{{ $coa['request'] > 0 ? $fmt($coa['request']) : '-' }}</td>
                                            <td class="text-right text-nowrap {{ $coaColorClass }}" style="font-weight:700;">{{ $fmt($coa['remaining']) }}</td>
                                        </tr>

                                        @foreach($coa['items'] as $si => $sub)
                                        @php
                                            $isNobItem = $sub['used'] > $sub['total'];
                                            $subColorClass = $isNobItem ? 'dt-variance-neg' : ($sub['remaining'] >= 0 ? 'dt-variance-pos' : 'dt-variance-neg');
                                            $subRowNobClass = $isNobItem ? 'dt-variance-neg' : '';
                                            $subClass = $coaClass . '-si-' . $si;
                                        @endphp
                                        <tr class="{{ $coaClass }} {{ $subRowNobClass }}" style="display:none;">
                                            <td style="padding-left:24px;">
                                                {{ $sub['deskripsi'] ?: '-' }}
                                                @if($isNobItem)<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                                @if($sub['request_amount'] > 0)
                                                <span class="badge badge-warning ml-1" style="font-size:9px;">Request</span>
                                                @if(count($sub['cr_items']))
                                                <button type="button" class="btn btn-outline-secondary dt-si-toggle" data-target="{{ $subClass }}-row" style="font-size:9px;padding:1px 7px;margin-left:6px;">
                                                    {{ count($sub['cr_items']) }} CR <i class="fas fa-chevron-down ml-1"></i>
                                                </button>
                                                @endif
                                                @endif
                                            </td>
                                            <td class="text-right text-nowrap">{{ $sub['amount_formatted'] }}</td>
                                            <td class="text-center text-nowrap">{{ rtrim(rtrim(number_format($sub['qty'], 2, ',', '.'), '0'), ',') }} {{ $sub['unit'] }}</td>
                                            <td class="text-right text-nowrap"><strong>{{ $sub['total_formatted'] }}</strong></td>
                                            <td class="text-right text-nowrap">{{ $sub['used_formatted'] }}</td>
                                            <td class="text-right text-nowrap" style="color:var(--warning);">{{ $sub['request_formatted'] ?? '-' }}</td>
                                            <td class="text-right text-nowrap {{ $subColorClass }}">{{ $sub['remaining_formatted'] }}</td>
                                        </tr>

                                        @if(count($sub['cr_items']))
                                        <tr class="{{ $subClass }}-row" style="display:none;">
                                            <td colspan="7" style="background:var(--surface-2);padding:10px 14px 10px 48px;">
                                                <table class="table table-sm table-bordered mb-0" style="font-size:11px;color:var(--text);width:auto;max-width:100%;">
                                                    <thead style="background:var(--surface-2);">
                                                        <tr>
                                                            <th scope="col" class="text-nowrap" style="width:1%;">Date</th>
                                                            <th scope="col">Description</th>
                                                            <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Price</th>
                                                            <th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>
                                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Total</th>
                                                            <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($sub['cr_items'] as $cri)
                                                        <tr @if($cri['item_status'] === 'REJECTED') style="color:var(--text-faint);text-decoration:line-through;" @endif>
                                                            <td class="text-nowrap">{{ $cri['date_formatted'] }}</td>
                                                            <td>
                                                                {{ $cri['description'] }}
                                                                @if($cri['item_status'] === 'REJECTED')
                                                                <span class="badge badge-light border" style="font-size:8px;text-decoration:none;display:inline-block;">Takeout</span>
                                                                @elseif($cri['item_status'] === 'HISTORY')
                                                                <span class="badge badge-light border" style="font-size:8px;text-decoration:none;display:inline-block;">Settled</span>
                                                                @else
                                                                <span class="badge badge-warning" style="font-size:8px;display:inline-block;">Request</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-nowrap">{{ $cri['beneficiary'] }}</td>
                                                            <td class="text-right text-nowrap">{{ $cri['price_formatted'] }}</td>
                                                            <td class="text-center text-nowrap">{{ $cri['qty'] }}</td>
                                                            <td class="text-right text-nowrap"><strong>{{ $cri['amount_formatted'] }}</strong></td>
                                                            <td class="text-center text-nowrap">
                                                                @php $bolehTakeoutItem = $cri['item_status'] === 'INCLUDED' && $batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']); @endphp
                                                                <div class="action-buttons ts-actions">

                                                                    <a href="/budget-request?open={{ $cri['cr_id'] }}" target="_blank"
                                                                       class="ts-action-primary is-neutral" title="Lihat Cost Request">
                                                                        <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Lihat</span>
                                                                    </a>

                                                                    @if($bolehTakeoutItem)
                                                                    <button type="button" class="ts-action-more"
                                                                            aria-haspopup="true" aria-expanded="false"
                                                                            title="Aksi lain" aria-label="Aksi lain untuk {{ $cri['cr_code'] }}">
                                                                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                                                    </button>
                                                                    <div class="ts-action-menu dropdown-menu" hidden>
                                                                        <button aria-label="Takeout" type="button"
                                                                                class="dropdown-item is-danger btn-takeout-cr-item"
                                                                                data-item-id="{{ $cri['item_id'] }}" data-code="{{ $cri['cr_code'] }}">
                                                                            <i class="fas fa-times"></i>Takeout
                                                                        </button>
                                                                    </div>
                                                                    @endif

                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        @endif
                                        @endforeach
                                        @empty
                                        <tr><td colspan="7" class="text-center text-muted py-3">{{ $g ? 'Belum ada detail budget.' : 'CR ini tidak terhubung ke Budget Plan manapun.' }}</td></tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr style="background:var(--surface-2);">
                                            <th colspan="3" class="text-right">Total</th>
                                            <th class="text-right" style="color:var(--info);">{{ $fmt($group['coa_groups']->sum('budget')) }}</th>
                                            <th class="text-right">{{ $fmt($group['coa_groups']->sum('used')) }}</th>
                                            <th class="text-right" style="color:var(--warning);">{{ $fmt($group['coa_groups']->sum('request')) }}</th>
                                            <th class="text-right">{{ $fmt($group['coa_groups']->sum('remaining')) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="modal-footer" style="background:var(--surface-2);">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @endif
            </div>

        </div>
    </div>
</div>

<!-- MODAL MARK PAID -->
<div class="modal fade" id="mdlMarkPaid" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-money-bill-wave mr-2" style="color:var(--accent);"></i> Tandai Batch sebagai PAID
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmMarkPaid" action="{{ url('/daily-transaction/' . $batch->id . '/mark-paid') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-info mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        @if($batch->isTopUp())
                            Top Up ini akan ditandai <strong>PAID</strong> (transfer selesai).
                        @else
                            {{ $batch->includedItems()->count() }} CR dalam batch ini akan berstatus <strong>PAID</strong>.
                        @endif
                        Upload minimal 1 file bukti pembayaran.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Charge (Biaya Admin Transfer)</label>
                        <input type="text" name="charge" id="inpMarkPaidCharge" class="form-control text-right"
                               value="0" placeholder="0">
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
                <div class="modal-footer" style="background:var(--surface-2);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Approve Batch</h5>
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
                <div class="modal-footer" style="background:var(--surface-2);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak Seluruh Batch</h5>
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
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT ITEM (TAKEOUT) -->
<div class="modal fade" id="mdlRejectItem" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:var(--accent);"></i> Takeout CR <span id="rejectItemCode"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectItem" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan takeout..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Takeout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL CR -->
<div class="modal fade" id="mdlDetailCR" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-xxl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            {{-- Tidak ada pita header di sini: judul, kode, dan tombol tutup
                 pindah ke kepala panel ringkas gelap. Sama dengan modal
                 detail Cost Request di halaman Cost Request. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface);">
                <div id="crDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent);"></i>
                </div>
                <div id="crDetailContent" style="display:none;">
                    <div class="cr-modal-flex">
                        <div class="cr-sidebar dm-side">

                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="crCode" class="dm-code"></div>
                                <h5 class="dm-title" id="crModalTitle">Cost Request Detail</h5>
                                <small id="crModalSubtitle" class="dm-sub-hidden"></small>

                                <div class="dm-badges">
                                    <span id="crStatusBadge"></span>
                                    <span id="crNobBadge"></span>
                                </div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Type</span><div id="crType" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Request</span><div id="crAmount" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span><div id="crBeneficiary" class="text-white mt-1" style="font-size:12px;"></div><div id="crBank" style="color:var(--text-faint);font-size:11px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Applicant</span><div id="crApplicant" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="cr-sidebar-spacer"></div>
                        </div>
                        <div class="cr-main-panel">
                            <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-list-ul mr-1"></i> Request <span id="crDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneBudget" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-money-check-alt mr-1"></i> Budget</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-history mr-1"></i> Log</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="crPaneInfo">
                                    {{-- Satu informasi satu baris (.ts-deflist), sama dengan
                                         modal Purchase Order, Budgeting & Cost Request. --}}
                                    <div class="ts-deflist mb-3">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Reference</span>
                                            <span id="infoCode" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Description</span>
                                            <span id="infoDesc" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Type</span>
                                            <span id="infoType" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Total</span>
                                            <span id="infoAmount" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Email Notification</span>
                                            <span id="infoEmail" class="ts-deflist-value" style="word-break:break-all;"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Budget Plan</span>
                                            <span id="infoBudgetPlan" class="ts-deflist-value"></span>
                                        </div>
                                    </div>

                                    <div class="ts-deflist mb-3" id="crScopeRow">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Scope of Work</span>
                                            <span id="infoScope" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Project</span>
                                            <span id="infoCluster" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row" id="crPaymentNotesCard" style="display:none;">
                                            <span class="ts-deflist-label">Payment Notes</span>
                                            <span id="infoPaymentNotes" class="ts-deflist-value"></span>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-link p-0" id="crNotesToggle" style="font-size:12px;color:var(--text-faint);text-decoration:none;">
                                        <i class="fas fa-chevron-right mr-1" id="crNotesToggleIcon" style="font-size:10px;transition:transform .15s;display:inline-block;"></i> Notes
                                    </button>
                                    <div id="crNotesCollapse" style="display:none;" class="mt-2">
                                        <div class="ts-deflist">
                                            <div class="ts-deflist-row">
                                                <span class="ts-deflist-label">Notes</span>
                                                <span id="infoNotes" class="ts-deflist-value" style="white-space:pre-line;"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Description</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Budget</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Used</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Request</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Remaining Budget</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:var(--surface-2);"><th class="text-right">Total</th><th></th><th></th><th class="text-right" id="crDetailTotal" style="color:var(--info);"></th><th></th></tr></tfoot>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">
                                            <i class="fas fa-paperclip mr-1"></i> Attachment <span id="crAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                        </h6>
                                        <div id="crAttBody" class="cr-att-grid"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneBudget">
                                    <div id="crBudgetBody"></div>
                                </div>
                                <div class="tab-pane fade" id="crPaneLogs">
                                    <div id="crLogBody" class="cr-timeline"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="includedCountHolder" value="{{ $batch->includedItems()->count() }}">
<input type="hidden" id="batchTypeHolder" value="{{ $batch->type }}">

<!-- IMAGE VIEWER (carousel + zoom) -->
<div id="imgViewerOverlay">
    <button aria-label="Close" type="button" id="imgViewerClose" title="Close"><i class="fas fa-times"></i></button>
    <div id="imgViewerName"></div>
    <div id="imgViewerStage">
        <button aria-label="Previous" type="button" class="imgViewerNav" id="imgViewerPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
        <img id="imgViewerImg" src="" alt="">
        <button aria-label="Next" type="button" class="imgViewerNav" id="imgViewerNext" title="Next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div id="imgViewerCounter"></div>
</div>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td,.table th { vertical-align:middle !important; }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:var(--radius-sm); }
#crDetailTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#crDetailTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }

/* Modal detail CR — ukuran & style disamakan dengan modal Cost Request. */
.modal-dialog-xxl {
    max-width: calc(100vw - 32px);
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    margin: 16px auto;
}
.modal-dialog-xxl .modal-content {
    height: calc(100vh - 32px);
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
}
.modal-dialog-xxl .modal-header,
.modal-dialog-xxl .modal-footer { flex: 0 0 auto; }
.modal-dialog-xxl .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
.modal-dialog-xxl #crDetailContent { height: 100%; }

.cr-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.cr-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.cr-sidebar-spacer { flex:1 1 auto; }
.cr-main-panel { flex:1 1 auto; padding:24px; min-width:0; }
@media (max-width:767.98px) {
    .cr-modal-flex { flex-direction:column; }
    .cr-sidebar { width:100%; flex:0 0 auto; }
}

/* Log tab — vertical timeline */
.cr-timeline { position:relative; padding-left:28px; }
.cr-timeline::before { content:""; position:absolute; left:9px; top:6px; bottom:6px; width:2px; background:var(--surface-3); }
.cr-timeline-item { position:relative; padding-bottom:22px; }
.cr-timeline-item:last-child { padding-bottom:0; }
.cr-timeline-dot {
    position:absolute; left:-28px; top:2px; width:20px; height:20px; border-radius:50%;
    display:flex; align-items:center; justify-content:center; font-size:9px; color:#fff;
    border:3px solid var(--border); box-shadow:0 0 0 1px var(--border);
}
.cr-timeline-body { font-size:12px; }

/* Budget tab — pola pivot grup/NOB */
#crBudgetBody .bp-group-row { cursor:pointer; background:var(--info-bg) !important; }
#crBudgetBody .bp-group-row:hover { background:var(--surface-3) !important; }
#crBudgetBody .bp-group-row td { background:transparent !important; }
.bp-caret.rotated { transform:rotate(90deg); }
#crBudgetBody td.bp-indent { padding-left:32px !important; }
#crBudgetBody tr.cr-req-match td { background:var(--warning-bg) !important; }
.cr-req-badge { font-size:9px; }

/* TAB INFO — clean grid layout */
.cr-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.cr-info-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
.cr-info-grid-ref { display:grid; grid-template-columns:320px 1fr; gap:14px 18px; }
.cr-info-grid-scope { display:grid; grid-template-columns:3fr 1fr; gap:14px 18px; }
.cr-info-grid-scope.has-payment { grid-template-columns:12fr 5fr 3fr; }
.cr-info-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 16px; }
.cr-info-label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:5px; }
.cr-info-value { font-size:13px; color:var(--text); white-space:nowrap; }
.cr-info-grid-scope .cr-info-value { white-space:normal; }
.cr-info-highlight { background:var(--warning-bg); border-color:var(--border-strong); }
.cr-info-highlight .cr-info-value { font-weight:700; color:var(--warning); }
#crNotesToggleIcon.rotated { transform:rotate(90deg); }
@media (max-width:576px) {
    .cr-info-grid, .cr-info-grid-3, .cr-info-grid-ref, .cr-info-grid-scope, .cr-info-grid-scope.has-payment { grid-template-columns:1fr; }
    .cr-info-value { white-space:normal; }
}

/* Request tab — thumbnail grid lampiran */
.cr-att-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin-top:8px; }
.cr-att-card { border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; background:var(--surface); }
.cr-att-thumb { width:100%; height:120px; object-fit:cover; display:block; cursor:pointer; background:var(--surface-2); }
.cr-att-icon { width:100%; height:120px; display:flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-faint); font-size:30px; }
.cr-att-name { font-size:11px; padding:6px 8px; color:var(--text); word-break:break-word; border-top:1px solid var(--border); }

/* ── IMAGE VIEWER (carousel + zoom) ── */
#imgViewerOverlay {
    position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:2000;
    display:none; align-items:center; justify-content:center; flex-direction:column;
}
#imgViewerOverlay.show { display:flex; }
#imgViewerStage { position:relative; width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
#imgViewerImg {
    max-width:90vw; max-height:82vh; object-fit:contain; cursor:zoom-in;
    transition:transform .15s ease; transform-origin:center center; user-select:none; -webkit-user-drag:none;
}
#imgViewerImg.zoomed { cursor:grab; }
#imgViewerImg.dragging { cursor:grabbing; transition:none; }
#imgViewerClose {
    position:absolute; top:16px; right:20px; z-index:10; background:rgba(255,255,255,.12); border:none; color:#fff;
    width:38px; height:38px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
#imgViewerClose:hover { background:rgba(255,255,255,.25); }
.imgViewerNav {
    position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.12); border:none; color:#fff;
    width:44px; height:44px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.imgViewerNav:hover { background:rgba(255,255,255,.25); }
#imgViewerPrev { left:16px; }
#imgViewerNext { right:16px; }
#imgViewerCounter { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:#fff; font-size:12px; background:rgba(255,255,255,.12); padding:4px 12px; border-radius:var(--radius-lg); }
#imgViewerName { position:absolute; top:20px; left:20px; z-index:10; color:#fff; font-size:12px; opacity:.85; max-width:60vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
@media (max-width:576px) {
    .imgViewerNav { width:36px; height:36px; font-size:16px; }
    #imgViewerPrev { left:6px; } #imgViewerNext { right:6px; }
}

#dtItemsBody tr.dt-group-row { cursor:pointer; background:var(--info-bg) !important; }
#dtItemsBody tr.dt-group-row:hover { background:var(--surface-3) !important; }
#dtItemsBody tr.dt-group-row td { background:transparent !important; }
#dtItemsBody .dt-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
#dtItemsBody .dt-caret.rotated { transform:rotate(90deg); }

/* MODAL VIEW BUDGET — Chart of Account & sub-item, sama pola dengan Todo List */
.dt-coa-row { cursor:pointer; background:var(--surface-3); }
.dt-coa-row:hover { background:var(--surface-3); }
.dt-coa-caret { transition:transform .15s ease; }
.dt-coa-caret.rotated { transform:rotate(90deg); }
.dt-variance-pos { color:var(--success) !important; }
.dt-variance-neg { color:var(--danger) !important; }

.cr-number-btn { background:transparent;border:none;padding:2px 8px;border-radius:var(--radius-sm);color:var(--info);font-weight:700;font-size:12px;transition:background .15s ease;text-decoration:none;display:inline-block;cursor:pointer; }
.cr-number-btn:hover { background:rgba(37,99,235,0.1);color:#1d4ed8;text-decoration:none; }

/* CR yang di-takeout dari batch — tampilkan pudar & dicoret */
.dt-takeout-row { color:var(--text-faint); }
.dt-takeout-desc { text-decoration:line-through; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    // ── DAFTAR CR DALAM BATCH — List CR grouped per Budget Plan/PO (pola sama
    // seperti Todo List), dirender penuh di server (Blade), TIDAK ada AJAX.
    // Breakdown Chart of Account dipindah ke modal "View Budget" per grup.
    $(document).on('click', '#dtItemsBody .dt-group-row', function () {
        var target = $(this).data('target');
        var $rows = $('#dtItemsBody .' + target);
        var expanding = $rows.first().is(':hidden');
        $rows.toggle(expanding);
        $(this).find('.dt-caret').toggleClass('rotated', expanding);
    });

    $(document).on('click', '.dt-toggle-all', function () {
        var $btn = $(this);
        var expanding = $btn.data('state') !== 'expanded';
        $('#dtItemsBody .dt-group-row').each(function () {
            var target = $(this).data('target');
            $('#dtItemsBody .' + target).toggle(expanding);
            $(this).find('.dt-caret').toggleClass('rotated', expanding);
        });
        $btn.data('state', expanding ? 'expanded' : 'collapsed');
        $btn.html(expanding ? '<i class="fas fa-compress-alt mr-1"></i>Collapse All' : '<i class="fas fa-expand-alt mr-1"></i>Expand All');
    });

    // ── MODAL VIEW BUDGET — buka modal + collapse/expand Chart of Account & list CR di dalamnya ──
    $(document).on('click', '.dt-view-budget', function (e) {
        e.stopPropagation();
        $($(this).data('target')).modal('show');
    });

    $(document).on('click', '.dt-coa-row', function () {
        var target = $(this).data('target');
        var $rows = $('.' + target);
        var expanding = $rows.first().is(':hidden');
        if (expanding) {
            $rows.show();
        } else {
            // data-target ada di tombol .dt-si-toggle DI DALAM baris sub-item, bukan di
            // baris <tr>-nya sendiri — harus dicari lewat .find(), supaya daftar "N CR"
            // yang lagi expanded ikut disembunyikan saat Chart of Account-nya di-collapse.
            $rows.find('.dt-si-toggle').each(function () {
                var t = $(this).data('target');
                if (t) $('.' + t).hide();
                $(this).find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });
            $rows.hide();
        }
        $(this).find('.dt-coa-caret').toggleClass('rotated', expanding);
    });

    $(document).on('click', '.dt-si-toggle', function (e) {
        e.stopPropagation();
        var target = $(this).data('target');
        $('.' + target).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });


    // MARK PAID — buka modal
    $('#btnMarkPaid').on('click', function () {
        $('#mdlMarkPaid').modal('show');
    });

    $('#inpMarkPaidCharge').on('input', function () {
        var v = (this.value || '').replace(/[^\d]/g, '');
        this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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
        var isTopUp = $('#batchTypeHolder').val() === 'TOP_UP';
        $('#approveBatchText').text(isTopUp
            ? 'Approve Top Up ' + code + '?'
            : 'Approve batch ' + code + '? ' + count + ' CR siap diproses untuk pembayaran.');
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

    // TAKEOUT (REJECT ITEM) — dari list CR pada breakdown budget
    $(document).on('click', '.btn-takeout-cr-item', function (e) {
        e.stopPropagation();
        var itemId = $(this).data('item-id'), code = $(this).data('code');
        $('#frmRejectItem').attr('action', '/daily-transaction/{{ $batch->id }}/reject-item/' + itemId);
        $('#frmRejectItem textarea[name="notes"]').val('');
        $('#rejectItemCode').text(code);
        $('#mdlRejectItem').modal('show');
    });

    /* ── IMAGE VIEWER — carousel + zoom (ala WhatsApp), untuk tab Request ── */
    (function () {
        var overlay  = document.getElementById('imgViewerOverlay');
        var stageImg = document.getElementById('imgViewerImg');
        var counter  = document.getElementById('imgViewerCounter');
        var images   = [];
        var idx      = 0;
        var zoomed   = false;
        var isDragging = false, dragStartX = 0, dragStartY = 0, imgX = 0, imgY = 0;
        var justSwiped = false;   // menandai klik yang sebenarnya gerakan geser

        function renderViewer() {
            var item = images[idx];
            if (!item) return;
            resetZoom();
            stageImg.src = item.full;
            $('#imgViewerName').text(item.name || '');
            counter.textContent = (idx + 1) + ' / ' + images.length;
            /* Panah hanya ada kalau memang ada tujuannya: tidak tampil untuk
               lampiran tunggal, tidak ada panah kiri di lampiran pertama dan
               tidak ada panah kanan di lampiran terakhir. */
            var banyak = images.length > 1;
            $('#imgViewerPrev').css('display', (banyak && idx > 0) ? 'flex' : 'none');
            $('#imgViewerNext').css('display', (banyak && idx < images.length - 1) ? 'flex' : 'none');
        }

        function resetZoom() {
            zoomed = false;
            imgX = 0; imgY = 0;
            stageImg.classList.remove('zoomed', 'dragging');
            stageImg.style.transform = 'translate(0px, 0px) scale(1)';
            stageImg.style.cursor = 'zoom-in';
        }

        function applyTransform() {
            stageImg.style.transform = 'translate(' + imgX + 'px, ' + imgY + 'px) scale(' + (zoomed ? 2.4 : 1) + ')';
        }

        function openViewer(groupImages, startIndex) {
            images = groupImages;
            idx = startIndex;
            renderViewer();
            overlay.classList.add('show');
            $('body').css('overflow', 'hidden');
        }

        function closeViewer() {
            overlay.classList.remove('show');
            $('body').css('overflow', '');
        }

        function showNext() { if (idx < images.length - 1) { idx++; renderViewer(); } }
        function showPrev() { if (idx > 0) { idx--; renderViewer(); } }

        $(document).on('click', '.cr-att-thumb', function () {
            var $group = $(this).closest('.cr-att-grid');
            var groupImages = $group.find('.cr-att-thumb').map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $group.find('.cr-att-thumb').index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        $('#imgViewerClose').on('click', closeViewer);
        $('#imgViewerNext').on('click', showNext);
        $('#imgViewerPrev').on('click', showPrev);
        $(overlay).on('click', function (e) {
            // Klik di area kosong menutup viewer — kecuali kalau klik itu
            // ujung dari gerakan geser antar lampiran.
            if (justSwiped) { justSwiped = false; return; }
            if (e.target === overlay) closeViewer();
        });

        $(document).on('keydown', function (e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') closeViewer();
            else if (e.key === 'ArrowRight') showNext();
            else if (e.key === 'ArrowLeft') showPrev();
        });

        stageImg.addEventListener('dblclick', function () {
            zoomed = !zoomed;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        });
        stageImg.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomed = e.deltaY < 0 ? true : false;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        }, { passive: false });

        stageImg.addEventListener('mousedown', function (e) {
            if (!zoomed) return;
            isDragging = true;
            dragStartX = e.clientX - imgX;
            dragStartY = e.clientY - imgY;
            stageImg.classList.add('dragging');
        });
        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            imgX = e.clientX - dragStartX;
            imgY = e.clientY - dragStartY;
            applyTransform();
        });
        document.addEventListener('mouseup', function () {
            isDragging = false;
            stageImg.classList.remove('dragging');
        });

        /* ── GESER KIRI/KANAN untuk pindah lampiran ─────────────────────────
           Saat gambar TIDAK di-zoom, tarikan mendatar (mouse maupun sentuh)
           berpindah ke lampiran sebelum/sesudahnya — pola yang sama dengan
           galeri ponsel. Saat di-zoom, tarikan tetap menggeser gambar
           (pan), jadi keduanya tidak saling rebutan. */
        var SWIPE_MIN = 50;          // px, biar klik/getar kecil tidak terbaca geser
        var swipeX = null, swipeY = null;

        function swipeStart(x, y) { if (!zoomed) { swipeX = x; swipeY = y; } }

        function swipeEnd(x, y) {
            if (swipeX === null || images.length < 2) { swipeX = swipeY = null; return; }
            var dx = x - swipeX, dy = y - swipeY;
            swipeX = swipeY = null;
            if (Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy)) return;
            if (dx < 0 && idx >= images.length - 1) return;   // sudah di lampiran terakhir
            if (dx > 0 && idx <= 0) return;                   // sudah di lampiran pertama
            justSwiped = true;
            if (dx < 0) showNext(); else showPrev();
        }

        stageImg.addEventListener('mousedown', function (e) { swipeStart(e.clientX, e.clientY); });
        document.addEventListener('mouseup', function (e) {
            if (!overlay.classList.contains('show')) { swipeX = swipeY = null; return; }
            swipeEnd(e.clientX, e.clientY);
        });

        stageImg.addEventListener('touchstart', function (e) {
            var t = e.changedTouches[0];
            swipeStart(t.clientX, t.clientY);
        }, { passive: true });
        stageImg.addEventListener('touchend', function (e) {
            var t = e.changedTouches[0];
            swipeEnd(t.clientX, t.clientY);
        }, { passive: true });

        /* Geser juga boleh lewat area kosong di kiri/kanan gambar. */
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === stageImg) return;
            swipeStart(e.clientX, e.clientY);
        });
    })();

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

        // ?with_budget=1 — halaman ini MASIH punya tab Budget, jadi rincian
        // Budget Plan diminta eksplisit (lihat BudgetRequestController::showJson).
        $.get('/budget-request/' + id + '/detail-json?with_budget=1', function (d) {
            var isNobItem  = !d.is_nob && d.details.some(function (det) { return !!det.is_nob; });
            var headerColor = d.is_nob ? 'var(--danger)' : (isNobItem ? 'var(--warning)' : '');

            $('#crModalTitle').text(d.description).css('color', headerColor || '');
            $('#crModalSubtitle').text((d.code_reference || '-') + ' - ' + d.code);
            $('#crCode').text(d.code);
            $('#crStatusBadge').html('<span class="badge badge-' + (crStatusColors[d.status]||'secondary') + ' px-3 py-1">' + (d.status_label || d.status) + '</span>');
            $('#crNobBadge').html(d.is_nob ? '<span class="badge badge-danger px-2 py-1 mt-1">NOB</span>' : '');
            $('#crType').text(d.type);
            $('#crAmount').text(d.amount_formatted);
            $('#crBeneficiary').text(d.beneficiary);
            $('#crBank').text(d.bank + ' — ' + d.account_number);
            $('#crApplicant').text(d.applicant);

            var bpLink = d.budget_plan_id
                ? '<a href="/budget-plan?open=' + d.budget_plan_id + '" target="_blank" style="color:var(--accent);text-decoration:underline;">' + (d.budget_plan || '-') + ' <i class="fas fa-external-link-alt" style="font-size:9px;"></i></a>'
                : '-';

            // Info tab
            $('#infoCode').text((d.code_reference || '-') + ' - ' + d.code);
            $('#infoDesc').html(
                (d.is_nob ? '<span class="badge badge-danger mr-1" style="font-size:9px;">NOB</span>' : '') +
                d.description +
                ' <span class="badge badge-' + (d.status_color || 'secondary') + ' px-2 py-1 ml-1">' + (d.status_label || d.status) + '</span>'
            );
            $('#infoType').html('<span class="badge badge-info px-2 py-1">' + d.type + '</span>');
            $('#infoScope').text(d.project || '-');
            $('#infoCluster').text(d.cluster || '-');
            $('#infoBudgetPlan').html(bpLink);
            $('#infoAmount').html('<strong style="color:var(--info);">' + d.amount_formatted + '</strong>');
            $('#infoEmail').text(d.email || '-');
            $('#infoNotes').text(d.notes || '-');

            if (d.payment_notes) {
                $('#infoPaymentNotes').text(d.payment_notes);
                $('#crPaymentNotesCard').show();
                $('#crScopeRow').addClass('has-payment');
            } else {
                $('#crPaymentNotesCard').hide();
                $('#crScopeRow').removeClass('has-payment');
            }

            // Request tab
            $('#crDetailCount').text(d.details.length);
            var dHtml = '', grand = 0;
            d.details.forEach(function(det) {
                var isTakeout = !!det.is_takeout;
                if (!isTakeout) grand += det.total;
                var isNob    = !!det.is_nob; // kolom, bukan tebakan dari teks catatan
                var rowStyle = isTakeout ? ' style="color:var(--text-faint);"' : (isNob ? ' style="color:var(--danger);"' : '');
                var strike   = isTakeout ? 'text-decoration:line-through;' : '';
                var badge    = isTakeout ? ' <span class="badge badge-warning" style="font-size:8px;">Takeout &middot; ' + (det.takeout_stage_label || '-') + '</span>' : '';
                var descCell = det.description + badge + (det.notes ? '<br><small style="opacity:.75;text-decoration:none;">' + det.notes + '</small>' : '');
                dHtml += '<tr' + rowStyle + '><td style="' + strike + '">' + descCell + '</td><td class="text-right" style="' + strike + '">' + det.budget_formatted + '</td><td class="text-right" style="' + strike + '">' + det.used_formatted + '</td><td class="text-right" style="' + strike + '"><strong>' + det.total_formatted + '</strong></td><td class="text-right" style="' + strike + '">' + det.remaining_formatted + '</td></tr>';
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="5" class="text-center text-muted py-3">No item detail yet.</td></tr>');
            $('#crDetailTotal').text(grand.toLocaleString('id-ID'));

            // Attachment (di bawah tabel Request) — thumbnail grid + viewer
            function renderAttGrid(atts) {
                if (!atts || !atts.length) return '<p class="text-muted py-2 mb-0" style="font-size:12px;">No attachments yet.</p>';
                var html = '';
                atts.forEach(function (a) {
                    var url = '/storage/' + a.path;
                    if (a.is_image) {
                        html += '<div class="cr-att-card">' +
                            '<img loading="lazy" src="' + url + '" class="cr-att-thumb" data-full="' + url + '" data-name="' + a.original_name + '">' +
                            '<div class="cr-att-name">' + a.original_name + '</div>' +
                        '</div>';
                    } else {
                        var icon = a.is_pdf ? 'fa-file-pdf' : 'fa-file-alt';
                        html += '<div class="cr-att-card">' +
                            '<a href="' + url + '" target="_blank" rel="noopener" class="cr-att-icon"><i class="fas ' + icon + '"></i></a>' +
                            '<div class="cr-att-name">' + a.original_name + '</div>' +
                        '</div>';
                    }
                });
                return html;
            }
            $('#crAttCount').text(d.attachments.length);
            $('#crAttBody').html(renderAttGrid(d.attachments));

            // Budget tab — breakdown Budget Plan yang terhubung
            (function renderCrBudgetTab() {
                var bp = d.budget;
                if (!bp) {
                    $('#crBudgetBody').html('<p class="text-muted py-3 mb-0">No linked Budget Plan.</p>');
                    return;
                }

                var moneyFmt = function (v) { return Math.round(v).toLocaleString('id-ID'); };
                var NOB_RED = 'var(--danger)', NOB_YELLOW = 'var(--warning)', OK_GREEN = 'var(--success)';
                var nobBadge = '<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>';
                var bpStatusColors = { 'DRAFT':'secondary','SUBMITTED':'warning','ACTIVE':'success','CLOSING':'info','CLOSED':'dark','REJECTED':'danger' };

                var header = '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:8px;">' +
                    '<div>' +
                        '<span class="badge badge-dark px-2 py-1 mr-2">' + bp.code + '</span>' +
                        '<strong>' + bp.description + '</strong> ' +
                        '<span class="badge badge-' + (bpStatusColors[bp.status] || 'secondary') + ' px-2 py-1 ml-1">' + bp.status + '</span>' +
                    '</div>' +
                    '<div style="font-size:12px;">' +
                        '<span class="text-muted">Budget:</span> <strong style="color:var(--info);">' + bp.amount_formatted + '</strong> &nbsp;' +
                        '<span class="text-muted">Used:</span> <strong>' + bp.used_amount_formatted + '</strong> &nbsp;' +
                        '<button type="button" class="btn btn-xs btn-outline-secondary" id="btnExpandAllCrBudget"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button> ' +
                        '<button type="button" class="btn btn-xs btn-outline-secondary" id="btnCollapseAllCrBudget"><i class="fas fa-angle-double-up mr-1"></i>Collapse All</button>' +
                    '</div>' +
                '</div>';

                var requestedIds = {};
                var requestedAmounts = {};
                var requestedRows = {};
                (d.details || []).forEach(function (rd) {
                    if (!rd.budget_detail_id) return;
                    requestedIds[rd.budget_detail_id] = true;
                    requestedAmounts[rd.budget_detail_id] = (requestedAmounts[rd.budget_detail_id] || 0) + (parseFloat(rd.total) || 0);
                    (requestedRows[rd.budget_detail_id] = requestedRows[rd.budget_detail_id] || []).push(rd);
                });

                var groups = {}, groupOrder = [];
                (bp.details || []).forEach(function (det) {
                    var acc = det.account || '-';
                    if (!groups[acc]) { groups[acc] = []; groupOrder.push(acc); }
                    groups[acc].push(det);
                });
                groupOrder.sort(function (a, b) { return a.localeCompare(b); });

                var rows = '', totalBudget = 0, totalUsed = 0, totalRequest = 0, totalRemaining = 0;
                if (groupOrder.length) {
                    groupOrder.forEach(function (acc, gi) {
                        var items = groups[acc];
                        var gBudget = 0, gUsed = 0, gRequest = 0, gRemaining = 0, anyItemNob = false, anyItemRequested = false;
                        items.forEach(function (det) {
                            gBudget += det.total; gUsed += det.used; gRemaining += det.remaining;
                            gRequest += requestedAmounts[det.id] || 0;
                            if (det.used > det.total) anyItemNob = true;
                            if (requestedIds[det.id]) anyItemRequested = true;
                        });
                        totalBudget += gBudget; totalUsed += gUsed; totalRequest += gRequest; totalRemaining += gRemaining;

                        var groupClass = 'cr-bp-grp-' + gi;
                        var isNobCategoryFull = gUsed > gBudget;
                        var isNobCategoryPartial = !isNobCategoryFull && anyItemNob;
                        var gColor = isNobCategoryFull ? NOB_RED : (isNobCategoryPartial ? NOB_YELLOW : (gRemaining >= 0 ? OK_GREEN : NOB_RED));
                        var gTextColor = isNobCategoryFull ? NOB_RED : (isNobCategoryPartial ? NOB_YELLOW : '');
                        var childDisplay = anyItemRequested ? 'table-row' : 'none';
                        var caretClass = anyItemRequested ? 'bp-caret rotated' : 'bp-caret';

                        rows += '<tr class="bp-group-row" data-target="' + groupClass + '" style="cursor:pointer;">' +
                            '<td colspan="3" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' +
                                '<i class="fas fa-chevron-right mr-2 ' + caretClass + '" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>' +
                                acc + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>' +
                                (isNobCategoryFull ? nobBadge : '') +
                                (anyItemRequested ? '<span class="badge badge-warning ml-1 cr-req-badge">Request</span>' : '') +
                                ' <span class="bp-crctrl" style="display:' + (anyItemRequested ? 'inline' : 'none') + ';margin-left:10px;">' +
                                    '<button type="button" class="btn btn-outline-secondary bp-cr-expand-all" data-group="' + groupClass + '" style="font-size:9px;padding:1px 7px;">Expand CR</button> ' +
                                    '<button type="button" class="btn btn-outline-secondary bp-cr-collapse-all" data-group="' + groupClass + '" style="font-size:9px;padding:1px 7px;">Collapse CR</button>' +
                                '</span>' +
                            '</td>' +
                            '<td class="text-right" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' + moneyFmt(gBudget) + '</td>' +
                            '<td class="text-right" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' + moneyFmt(gUsed) + '</td>' +
                            '<td class="text-right" style="font-weight:700;color:var(--warning);">' + moneyFmt(gRequest) + '</td>' +
                            '<td class="text-right" style="font-weight:700;color:' + gColor + ';">' + moneyFmt(gRemaining) + '</td>' +
                        '</tr>';

                        items.forEach(function (det, ii) {
                            var isNobItem2 = det.used > det.total;
                            var isRequested = !!requestedIds[det.id];
                            var remColor = isNobItem2 ? NOB_RED : (det.remaining >= 0 ? OK_GREEN : NOB_RED);
                            var rowColor = isNobItem2 ? 'color:' + NOB_RED + ';' : '';
                            var qtyUnit = det.qty + (det.unit ? ' ' + det.unit : '');
                            var crDetails = det.cr_details || [];
                            var currentReqCount = (requestedRows[det.id] || []).length;
                            var crBlockClass = groupClass + '-cr-' + ii;

                            var crToggleBtn = (crDetails.length + currentReqCount)
                                ? '<button type="button" class="btn btn-outline-secondary bp-cr-toggle" data-target="' + crBlockClass + '-row" style="font-size:10px;padding:2px 8px;white-space:nowrap;">' +
                                    (crDetails.length + currentReqCount) + ' CR <i class="fas fa-chevron-down ml-1"></i></button>'
                                : '';

                            rows += '<tr class="' + groupClass + (isRequested ? ' cr-req-match' : '') + '" style="display:' + childDisplay + ';' + rowColor + '">' +
                                '<td class="bp-indent">' +
                                    '<div class="d-flex justify-content-between align-items-center" style="gap:8px;">' +
                                        '<span>' + (det.deskripsi || '-') + (isNobItem2 ? nobBadge : '') + (isRequested ? '<span class="badge badge-warning ml-1 cr-req-badge">Request</span>' : '') + '</span>' +
                                        crToggleBtn +
                                    '</div>' +
                                '</td>' +
                                '<td class="text-right">' + det.amount_formatted + '</td>' +
                                '<td class="text-center">' + qtyUnit + '</td>' +
                                '<td class="text-right"><strong>' + det.total_formatted + '</strong></td>' +
                                '<td class="text-right">' + det.used_formatted + '</td>' +
                                '<td class="text-right" style="color:var(--warning);">' + (requestedAmounts[det.id] ? moneyFmt(requestedAmounts[det.id]) : '-') + '</td>' +
                                '<td class="text-right" style="color:' + remColor + ';">' + det.remaining_formatted + '</td>' +
                            '</tr>';

                            if (crDetails.length || isRequested) {
                                var currentReqRows = (requestedRows[det.id] || []).map(function (rd) {
                                    return '<tr style="background:var(--warning-bg);">' +
                                        '<td class="text-nowrap">' + (d.created_at || '-') + '</td>' +
                                        '<td>' + (rd.notes || rd.description || '-') + ' <span class="badge badge-warning cr-req-badge">Request</span></td>' +
                                        '<td class="text-nowrap">' + (d.beneficiary || '-') + '</td>' +
                                        '<td class="text-right text-nowrap">' + rd.amount_formatted + '</td>' +
                                        '<td class="text-center text-nowrap">' + rd.qty + '</td>' +
                                        '<td class="text-right text-nowrap"><strong>' + rd.total_formatted + '</strong></td>' +
                                        '<td class="text-center text-nowrap">-</td>' +
                                    '</tr>';
                                }).join('');
                                var crRows = currentReqRows + crDetails.map(function (cr) {
                                    var viewBtn = cr.cr_id
                                        ? '<a href="/budget-request?open=' + cr.cr_id + '" target="_blank" class="btn btn-info" style="padding:1px 6px;font-size:10px;" title="View CR Detail"><i class="fas fa-eye"></i></a>'
                                        : '';
                                    return '<tr>' +
                                        '<td class="text-nowrap">' + cr.cr_date_formatted + '</td>' +
                                        '<td>' + (cr.notes || '-') + '</td>' +
                                        '<td class="text-nowrap">' + cr.beneficiary + '</td>' +
                                        '<td class="text-right text-nowrap">' + cr.unit_price_formatted + '</td>' +
                                        '<td class="text-center text-nowrap">' + cr.qty + '</td>' +
                                        '<td class="text-right text-nowrap"><strong>' + cr.amount_formatted + '</strong></td>' +
                                        '<td class="text-center text-nowrap">' + viewBtn + '</td>' +
                                    '</tr>';
                                }).join('');
                                var crTableHtml = '<table class="table table-sm table-bordered mb-0" style="font-size:11px;">' +
                                    '<thead style="background:var(--surface-2);"><tr>' +
                                        '<th scope="col" class="text-nowrap" style="width:1%;">Date</th><th scope="col">Description</th><th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>' +
                                        '<th scope="col" class="text-right text-nowrap" style="width:1%;">Price</th><th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>' +
                                        '<th scope="col" class="text-right text-nowrap" style="width:1%;">Total</th><th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>' +
                                    '</tr></thead>' +
                                    '<tbody>' + crRows + '</tbody>' +
                                '</table>';

                                rows += '<tr class="' + crBlockClass + '-row ' + groupClass + '-crall" style="display:none;">' +
                                    '<td colspan="7" style="background:var(--surface-2);padding:10px 14px;">' + crTableHtml + '</td>' +
                                '</tr>';
                            }
                        });
                    });
                } else {
                    rows = '<tr><td colspan="7" class="text-center text-muted py-3">No budget detail yet.</td></tr>';
                }

                var totalRemColor = totalRemaining >= 0 ? OK_GREEN : NOB_RED;
                var table = '<div class="table-responsive"><table class="table table-bordered mb-0" style="font-size:12px;">' +
                    '<thead style="background:var(--surface-2);"><tr>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Price</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:10%;">Qty</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Budget</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Used</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Request</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Variance</th>' +
                    '</tr></thead><tbody>' + rows + '</tbody>' +
                    '<tfoot><tr style="background:var(--surface-2);">' +
                        '<th colspan="3" class="text-right">Total</th>' +
                        '<th class="text-right" style="color:var(--info);">' + moneyFmt(totalBudget) + '</th>' +
                        '<th class="text-right">' + moneyFmt(totalUsed) + '</th>' +
                        '<th class="text-right" style="color:var(--warning);">' + moneyFmt(totalRequest) + '</th>' +
                        '<th class="text-right" style="color:' + totalRemColor + ';">' + moneyFmt(totalRemaining) + '</th>' +
                    '</tr></tfoot></table></div>';

                $('#crBudgetBody').html(header + table);
            })();

            // Log tab — vertical timeline
            var logHtml = '';
            d.logs.forEach(function(log) {
                logHtml += '<div class="cr-timeline-item">' +
                    '<span class="cr-timeline-dot badge-' + log.action_color + '"><i class="fas fa-circle" style="font-size:5px;"></i></span>' +
                    '<div class="cr-timeline-body">' +
                        '<span class="badge badge-' + log.action_color + ' px-2 py-1 mr-2" style="font-size:10px;">' + log.action_label + '</span>' +
                        '<strong>' + log.actor + '</strong> <span style="color:var(--text-faint);">(' + (log.role||'system') + ')</span>' +
                        '<div style="font-size:11px;color:var(--text-faint);margin-top:2px;">' + log.date + '</div>' +
                        (log.notes ? '<div style="font-size:12px;margin-top:6px;padding:8px 12px;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border);">' + log.notes + '</div>' : '') +
                    '</div>' +
                '</div>';
            });
            $('#crLogBody').html(logHtml || '<p class="text-muted py-3 mb-0">No log yet.</p>');

            $('#crDetailTabs a:first').tab('show');
            $('#crDetailLoading').hide();
            $('#crDetailContent').show();
        }).fail(function () {
            $('#crDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Failed to load data.</p>');
        });
    });

    /* Budget tab pada modal detail CR: expand/collapse grup per Chart of Account.
       Expand/Collapse di level ini CUMA buka/tutup baris sub-item — TIDAK ikut
       membuka dropdown CR di dalamnya. */
    function resetCrBpGroupCrState($groupRows) {
        $groupRows.find('.bp-cr-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }
    $(document).on('click', '#crBudgetBody .bp-group-row', function () {
        var target = $(this).data('target');
        var $subRows = $('#crBudgetBody .' + target);
        var expanding = $subRows.first().is(':hidden');
        if (expanding) {
            $subRows.show();
            $(this).find('.bp-crctrl').show();
        } else {
            $subRows.hide();
            $('#crBudgetBody .' + target + '-crall').hide();
            $(this).find('.bp-crctrl').hide();
            resetCrBpGroupCrState($subRows);
        }
        $(this).find('.bp-caret').toggleClass('rotated', expanding);
    });
    $(document).on('click', '#btnExpandAllCrBudget', function () {
        $('#crBudgetBody .bp-group-row').each(function () {
            var target = $(this).data('target');
            $('#crBudgetBody .' + target).show();
            $(this).find('.bp-crctrl').show();
            $(this).find('.bp-caret').addClass('rotated');
        });
    });
    $(document).on('click', '#btnCollapseAllCrBudget', function () {
        $('#crBudgetBody .bp-group-row').each(function () {
            var target = $(this).data('target');
            $('#crBudgetBody .' + target).hide();
            $('#crBudgetBody .' + target + '-crall').hide();
            $(this).find('.bp-crctrl').hide();
            $(this).find('.bp-caret').removeClass('rotated');
        });
        resetCrBpGroupCrState($('#crBudgetBody'));
    });
    $(document).on('click', '.bp-cr-expand-all', function (e) {
        e.stopPropagation();
        var grp = $(this).data('group');
        $('#crBudgetBody .' + grp + '-crall').show();
        $('#crBudgetBody .' + grp + ' .bp-cr-toggle i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    });
    $(document).on('click', '.bp-cr-collapse-all', function (e) {
        e.stopPropagation();
        var grp = $(this).data('group');
        $('#crBudgetBody .' + grp + '-crall').hide();
        $('#crBudgetBody .' + grp + ' .bp-cr-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });
    $(document).on('click', '.bp-cr-toggle', function (e) {
        e.stopPropagation();
        var target = $(this).data('target');
        $('#crBudgetBody .' + target).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    /* Info tab: Notes disembunyikan default (jarang diisi) */
    $(document).on('click', '#crNotesToggle', function () {
        $('#crNotesCollapse').slideToggle(150);
        $('#crNotesToggleIcon').toggleClass('rotated');
    });

    $('#mdlDetailCR').on('hidden.bs.modal', function () {
        $('#crDetailContent').hide();
        $('#crDetailLoading').show();
        $('#crNotesCollapse').hide(); $('#crNotesToggleIcon').removeClass('rotated');
    });

});
</script>
@endpush