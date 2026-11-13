@extends('layouts.app')

@section('title', $rec->number)

@php
    $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs((int) $n), 0, ',', '.');
    $badge = ['DRAFT' => 'badge-secondary', 'SUBMITTED' => 'badge-warning', 'APPROVED' => 'badge-success'];
    $lbl   = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Menunggu approval', 'APPROVED' => 'Disetujui · terkunci'];
    $lineBadge = ['MATCHED' => 'badge-success', 'PARTIAL' => 'badge-warning', 'UNMATCHED' => 'badge-danger', 'INVESTIGATING' => 'badge-info'];
    $lineLbl   = ['MATCHED' => 'Matched', 'PARTIAL' => 'Partially Matched', 'UNMATCHED' => 'Unmatched in TESYS', 'INVESTIGATING' => 'Under Investigation'];
    $editable = $rec->isEditable() && $canWork;
    $uid = auth()->id();
    $diffOk = $summary['difference'] === 0;
    $tabs = ['summary' => 'Ringkasan', 'lines' => 'Rekening Koran & Pencocokan', 'tesys' => 'Belum Cocok di TESYS', 'import' => 'Impor', 'history' => 'Riwayat'];
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger rounded-0 shadow-sm mb-0"><ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="tw:sr-only">
            <h1>{{ $rec->number }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-reconciliation.index') }}">Reconciliation</a></li>
                <li class="breadcrumb-item active">{{ $rec->number }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Reconciliation · {{ $lbl[$rec->status] ?? $rec->status }}" title="{{ $rec->number }} — {{ $rec->sourceFund->name ?? '-' }}"
            description="{{ $rec->period_start->format('d M Y') }} – {{ $rec->period_end->format('d M Y') }}" />

        {{-- KPI --}}
        <x-stat-cards :cards="[
            ['Saldo Rekening Koran', $rp($summary['statement_balance']), 'secondary', 'fas fa-file-invoice'],
            ['Saldo Ledger TESYS', $rp($summary['ledger_balance']), 'primary', 'fas fa-book'],
            ['Selisih', $rp($summary['difference']), $diffOk ? 'success' : 'danger', 'fas fa-balance-scale'],
            ['Baris Belum Cocok', $summary['unresolved_lines'], $summary['unresolved_lines'] ? 'warning' : 'success', 'fas fa-exclamation-circle'],
        ]" />

        {{-- AKSI WORKFLOW --}}
        <x-tw.card padding="sm">
            <div class="d-flex flex-wrap align-items-start" style="gap:12px;">
                @if($rec->status === 'DRAFT' && $canWork)
                    <form method="POST" action="{{ route('cash-reconciliation.submit', $rec->id) }}" class="form-inline" style="gap:6px;" onsubmit="return confirm('Submit rekonsiliasi untuk persetujuan? Data tidak bisa diubah selama menunggu.');">
                        @csrf
                        @if($summary['needs_exception'])
                            <input type="text" name="exception_reason" class="form-control form-control-sm" style="min-width:320px;" placeholder="Alasan pengecualian (selisih / baris belum cocok)" maxlength="500" required>
                        @endif
                        <button class="btn btn-primary btn-sm">Submit untuk Approval</button>
                    </form>
                @endif

                @if($rec->status === 'SUBMITTED')
                    @if($rec->exception_reason)<div class="text-warning" style="font-size:13px;"><i class="fas fa-exclamation-triangle"></i> Pengecualian diajukan: {{ $rec->exception_reason }}</div>@endif
                    @if($canApprove)
                        @if(! in_array((int) $uid, [(int) $rec->created_by, (int) $rec->submitted_by], true))
                            <form method="POST" action="{{ route('cash-reconciliation.approve', $rec->id) }}" onsubmit="return confirm('Setujui dan KUNCI periode ini?');">@csrf<button class="btn btn-success btn-sm">Setujui &amp; Kunci Periode</button></form>
                        @else
                            <span class="text-muted" style="font-size:12px;">Persetujuan harus oleh pengguna lain (bukan pembuat/pengirim).</span>
                        @endif
                        <form method="POST" action="{{ route('cash-reconciliation.return', $rec->id) }}" class="form-inline" style="gap:6px;">@csrf
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Alasan dikembalikan" required maxlength="255"><button class="btn btn-outline-danger btn-sm">Kembalikan ke Draft</button></form>
                    @endif
                @endif

                @if($rec->status === 'APPROVED')
                    <div class="text-success" style="font-size:13px;"><i class="fas fa-lock"></i> Terkunci — disetujui {{ $rec->approver->username ?? '-' }} {{ $rec->approved_at?->format('d M Y H:i') }}
                        @if($rec->exception_reason)<br><span class="text-warning">Dengan pengecualian: {{ $rec->exception_reason }}</span>@endif</div>
                    @if($canReopen)
                        <form method="POST" action="{{ route('cash-reconciliation.reopen', $rec->id) }}" class="form-inline" style="gap:6px;" onsubmit="return confirm('Buka ulang periode terkunci? Tindakan ini tercatat di audit.');">@csrf
                            <input type="text" name="reason" class="form-control form-control-sm" style="min-width:260px;" placeholder="Alasan membuka ulang (wajib)" required minlength="5" maxlength="255"><button class="btn btn-outline-danger btn-sm">Buka Ulang</button></form>
                    @endif
                @endif
            </div>
        </x-tw.card>

        <ul class="nav nav-tabs ts-pill-tabs ts-tabs-sticky" role="tablist">
            @foreach($tabs as $k => $label)
                <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" data-toggle="tab" href="#tab-{{ $k }}" role="tab">{{ $label }}</a></li>
            @endforeach
        </ul>

        <div class="tab-content">

            {{-- RINGKASAN --}}
            <div class="tab-pane fade {{ $tab === 'summary' ? 'show active' : '' }}" id="tab-summary" role="tabpanel">
                <x-tw.card padding="sm">
                    <table class="table table-sm mb-0" style="font-size:13px; max-width:720px;">
                        <tr><td>Saldo akhir rekening koran</td><td class="text-right">{{ $rp($summary['statement_balance']) }}</td></tr>
                        <tr><td>+ Outstanding Deposits <span class="text-muted">(masuk di TESYS, belum di bank)</span></td><td class="text-right">{{ $rp($summary['outstanding_deposits']) }}</td></tr>
                        <tr><td>− Outstanding Payments <span class="text-muted">(keluar di TESYS, belum di bank)</span></td><td class="text-right">{{ $rp($summary['outstanding_payments']) }}</td></tr>
                        <tr class="font-weight-bold"><td>Saldo bank disesuaikan</td><td class="text-right">{{ $rp($summary['adjusted_bank']) }}</td></tr>
                        <tr><td colspan="2"></td></tr>
                        <tr><td>Saldo ledger TESYS per {{ $rec->period_end->format('d M Y') }}</td><td class="text-right">{{ $rp($summary['ledger_balance']) }}</td></tr>
                        <tr><td>+ Kredit bank belum tercatat di TESYS <span class="text-muted">(mis. bunga)</span></td><td class="text-right">{{ $rp($summary['bank_credits_not_booked']) }}</td></tr>
                        <tr><td>− Debit bank belum tercatat di TESYS <span class="text-muted">(mis. biaya bank)</span></td><td class="text-right">{{ $rp($summary['bank_debits_not_booked']) }}</td></tr>
                        <tr class="font-weight-bold"><td>Saldo TESYS disesuaikan</td><td class="text-right">{{ $rp($summary['adjusted_book']) }}</td></tr>
                        <tr><td colspan="2"></td></tr>
                        <tr class="font-weight-bold {{ $diffOk ? 'text-success' : 'text-danger' }}"><td>Selisih rekonsiliasi</td><td class="text-right">{{ $rp($summary['difference']) }}</td></tr>
                    </table>
                    <div class="text-muted mt-2" style="font-size:12px;">
                        Baris bank: Matched {{ $summary['by_status']['MATCHED'] }} · Partial {{ $summary['by_status']['PARTIAL'] }} · Unmatched {{ $summary['by_status']['UNMATCHED'] }} · Investigating {{ $summary['by_status']['INVESTIGATING'] }}.
                        Rekonsiliasi hanya bisa diselesaikan bila selisih nol dan semua baris cocok, atau dengan pengecualian berizin yang disetujui.
                        Baris bank yang belum tercatat di TESYS sebaiknya dicatat lewat Cash Adjustment lalu dicocokkan.
                    </div>
                </x-tw.card>
            </div>

            {{-- BARIS BANK --}}
            <div class="tab-pane fade {{ $tab === 'lines' ? 'show active' : '' }}" id="tab-lines" role="tabpanel">
                <x-tw.card padding="sm">
                    @if($editable)
                        <form method="POST" action="{{ route('cash-reconciliation.auto-match', $rec->id) }}" class="mb-2">@csrf
                            <button class="btn btn-outline-primary btn-sm"><i class="fas fa-magic"></i> Cocokkan Otomatis</button>
                            <span class="text-muted" style="font-size:12px;">Arah &amp; nominal sama persis, selisih tanggal maks. {{ \App\Services\ReconciliationService::DATE_TOLERANCE_DAYS }} hari; referensi yang cocok diprioritaskan.</span>
                        </form>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 ts-idx-tbl" style="font-size:13px;">
                            <thead class="bg-light"><tr><th>Tanggal</th><th>Deskripsi</th><th>Referensi</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Status</th><th>Pasangan TESYS</th></tr></thead>
                            <tbody>
                            @forelse($lines as $l)
                                <tr>
                                    <td class="text-nowrap">{{ $l->txn_date->format('d M Y') }}</td>
                                    <td>{{ $l->description }} @if($l->note)<div class="text-muted" style="font-size:11px;">{{ $l->note }}</div>@endif</td>
                                    <td>{{ $l->reference }}</td>
                                    <td class="text-right">{{ $l->debit ? $rp($l->debit) : '' }}</td>
                                    <td class="text-right">{{ $l->credit ? $rp($l->credit) : '' }}</td>
                                    <td><span class="badge {{ $lineBadge[$l->status_label] }}">{{ $lineLbl[$l->status_label] }}</span></td>
                                    <td>
                                        @foreach($l->matches as $m)
                                            <div style="font-size:12px;">{{ $m->ledger_reference ?: $m->ledger_module }} · {{ $rp($m->ledger_amount) }} · {{ $m->ledger_date->format('d M') }} <span class="text-muted">({{ strtolower($m->method) }})</span></div>
                                        @endforeach
                                        @if($editable)
                                            @if($l->matches->isNotEmpty())
                                                <form method="POST" action="{{ route('cash-reconciliation.unmatch', [$rec->id, $l->id]) }}" class="d-inline">@csrf<button class="btn btn-link btn-sm p-0">Lepas</button></form>
                                            @endif
                                            @if($l->status_label !== 'MATCHED')
                                                <form method="POST" action="{{ route('cash-reconciliation.investigate', [$rec->id, $l->id]) }}" class="d-inline">@csrf
                                                    <input type="hidden" name="note" value="">
                                                    <button class="btn btn-link btn-sm p-0">{{ $l->investigating ? 'Lepas investigasi' : 'Investigasi' }}</button></form>
                                                @if($l->suggestions->isNotEmpty())
                                                    <details class="mt-1"><summary style="font-size:12px; cursor:pointer;">Cocokkan manual…</summary>
                                                        <form method="POST" action="{{ route('cash-reconciliation.match', $rec->id) }}">@csrf
                                                            <input type="hidden" name="bank_line_id" value="{{ $l->id }}">
                                                            @foreach($l->suggestions as $s)
                                                                <label class="d-block mb-0" style="font-size:12px;">
                                                                    <input type="checkbox" name="ledger_keys[]" value="{{ $s['key'] }}">
                                                                    {{ $s['date']->format('d M') }} · {{ $rp($s['amount']) }} · {{ $s['reference_number'] }} · {{ \Illuminate\Support\Str::limit($s['description'], 40) }}
                                                                    @if((int) $s['amount'] === $l->amount)<span class="badge badge-success">nominal sama</span>@endif
                                                                </label>
                                                            @endforeach
                                                            <button class="btn btn-primary btn-sm mt-1">Cocokkan</button>
                                                        </form>
                                                    </details>
                                                @else
                                                    <div class="text-muted" style="font-size:12px;">Tidak ada kandidat di TESYS (arah sama).</div>
                                                @endif
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty title="Belum ada baris rekening koran" message="Impor CSV di tab Impor." /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-tw.card>
            </div>

            {{-- BELUM COCOK DI TESYS --}}
            <div class="tab-pane fade {{ $tab === 'tesys' ? 'show active' : '' }}" id="tab-tesys" role="tabpanel">
                <x-tw.card padding="sm">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 ts-idx-tbl" style="font-size:13px;">
                            <thead class="bg-light"><tr><th>Tanggal</th><th>Referensi</th><th>Deskripsi</th><th>Sumber</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th></tr></thead>
                            <tbody>
                            @forelse($summary['unmatched_ledger']->sortBy(fn ($r) => $r['date']->format('Y-m-d')) as $r)
                                <tr>
                                    <td class="text-nowrap">{{ $r['date']->format('d M Y') }}</td>
                                    <td>@if($r['url'])<a href="{{ $r['url'] }}">{{ $r['reference_number'] }}</a>@else{{ $r['reference_number'] }}@endif</td>
                                    <td>{{ $r['description'] }}</td><td>{{ $r['category'] }}</td>
                                    <td class="text-right">{{ $r['direction'] === 'IN' ? $rp($r['amount']) : '' }}</td>
                                    <td class="text-right">{{ $r['direction'] === 'OUT' ? $rp($r['amount']) : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty title="Semua transaksi TESYS sudah cocok" /></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-tw.card>
            </div>

            {{-- IMPOR --}}
            <div class="tab-pane fade {{ $tab === 'import' ? 'show active' : '' }}" id="tab-import" role="tabpanel">
                @if($editable)
                <x-tw.card padding="sm">
                    <form method="POST" action="{{ route('cash-reconciliation.import-preview', $rec->id) }}" enctype="multipart/form-data" class="form-inline" style="gap:8px;">
                        @csrf
                        <input type="file" name="file" accept=".csv,.txt" class="form-control-file" required>
                        <button class="btn btn-primary btn-sm">Pratinjau</button>
                        <a href="{{ route('cash-reconciliation.template') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download"></i> Template CSV</a>
                    </form>
                    <div class="text-muted mt-1" style="font-size:12px;">Kolom: date, description, reference, debit (uang keluar), credit (uang masuk), balance. Maks. 1.000 baris; tanggal harus dalam periode; baris yang sudah pernah diimpor dilewati.</div>
                </x-tw.card>
                @endif

                @if($preview)
                    @php $rows = collect($preview['rows']); @endphp
                    <x-tw.card padding="sm" class="mt-3">
                        <x-slot:header>Pratinjau: {{ $preview['filename'] }} — OK {{ $rows->where('state', 'OK')->count() }}, duplikat {{ $rows->where('state', 'DUPLICATE')->count() }}, galat {{ $rows->where('state', 'ERROR')->count() }}</x-slot:header>
                        <div class="table-responsive" style="max-height:360px; overflow:auto;">
                            <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                <thead class="bg-light"><tr><th>Baris</th><th>Status</th><th>Tanggal</th><th>Deskripsi</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Catatan</th></tr></thead>
                                <tbody>
                                @foreach($rows as $r)
                                    <tr class="{{ $r['state'] === 'OK' ? '' : ($r['state'] === 'DUPLICATE' ? 'text-muted' : 'text-danger') }}">
                                        <td>{{ $r['line'] }}</td><td>{{ $r['state'] }}</td><td>{{ $r['data']['date'] }}</td><td>{{ $r['data']['description'] }}</td>
                                        <td class="text-right">{{ $r['data']['debit'] ?: '' }}</td><td class="text-right">{{ $r['data']['credit'] ?: '' }}</td><td>{{ $r['message'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($editable)
                            <div class="d-flex mt-2" style="gap:8px;">
                                <form method="POST" action="{{ route('cash-reconciliation.import-confirm', $rec->id) }}">@csrf
                                    <button class="btn btn-success btn-sm" @disabled($rows->where('state', 'OK')->isEmpty())>Impor {{ $rows->where('state', 'OK')->count() }} baris OK</button></form>
                                <form method="POST" action="{{ route('cash-reconciliation.import-cancel', $rec->id) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Batal</button></form>
                            </div>
                        @endif
                    </x-tw.card>
                @endif

                <x-tw.card padding="sm" class="mt-3">
                    <x-slot:header>Riwayat Impor</x-slot:header>
                    <table class="table table-sm table-bordered mb-0" style="font-size:13px;">
                        <thead class="bg-light"><tr><th>File</th><th>Diimpor</th><th>Dilewati</th><th>Oleh</th><th>Waktu</th><th></th></tr></thead>
                        <tbody>
                        @forelse($rec->imports as $i)
                            <tr><td>{{ $i->filename }}</td><td>{{ $i->rows_imported }}</td><td>{{ $i->rows_skipped }}</td><td>{{ $i->importer->username ?? '-' }}</td><td>{{ $i->created_at->format('d M Y H:i') }}</td>
                                <td>@if($editable)<form method="POST" action="{{ route('cash-reconciliation.import-destroy', [$rec->id, $i->id]) }}" onsubmit="return confirm('Batalkan impor ini? Baris-barisnya dihapus.');">@csrf<button class="btn btn-link btn-sm p-0 text-danger">Batalkan</button></form>@endif</td></tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Belum ada impor.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </x-tw.card>
            </div>

            {{-- RIWAYAT --}}
            <div class="tab-pane fade {{ $tab === 'history' ? 'show active' : '' }}" id="tab-history" role="tabpanel">
                <x-tw.card padding="sm">
                    <table class="table table-sm table-bordered mb-0" style="font-size:13px;">
                        <thead class="bg-light"><tr><th>Waktu</th><th>Aksi</th><th>Catatan</th><th>Oleh</th></tr></thead>
                        <tbody>
                        @foreach($rec->logs as $lg)
                            <tr><td class="text-nowrap">{{ $lg->created_at?->format('d M Y H:i') }}</td><td>{{ $lg->action }}</td><td>{{ $lg->notes }}</td><td>{{ $lg->user->username ?? '-' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </x-tw.card>
            </div>
        </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
