@extends('layouts.app')

@section('title', 'Journal Entries')

@php
    use App\Support\AccountingMoney as M;
    $badge = ['DRAFT' => 'badge-secondary', 'SUBMITTED' => 'badge-warning', 'POSTED' => 'badge-success', 'REVERSED' => 'badge-dark', 'PARTIAL' => 'badge-info', 'NONE' => 'badge-light border'];
    $lbl   = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Submitted', 'POSTED' => 'Posted', 'REVERSED' => 'Reversed', 'PARTIAL' => 'Sebagian', 'NONE' => 'Belum dijurnal'];
    $k = fn ($s) => (int) ($kpi[$s]->c ?? 0);
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif

        <div class="tw:sr-only">
            <h1>Journal Entries</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">Journal Entries</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="Journal Entries"
            description="Jurnal dibuat otomatis dari Treasury dan Cash Receipt, ditinjau, di-submit, lalu diposting Finance Manager.">
            <x-slot:actions>
                @if($canCreate)<x-tw.button href="{{ route('accounting.journal.create') }}" variant="secondary"><i class="fas fa-pen" aria-hidden="true"></i> Jurnal Manual</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        @unless($cutover)
            <div class="alert alert-warning rounded-0 mb-0">Belum ada periode akuntansi. Buat periode di <a href="{{ route('accounting.period.index') }}">Accounting Period</a>; tanggal mulai periode pertama menjadi tanggal mulai pembukuan.</div>
        @endunless

        <x-stat-cards :cards="[
            ['Draft', $k('DRAFT'), 'secondary', 'fas fa-pen'],
            ['Menunggu Posting', $k('SUBMITTED'), 'warning', 'fas fa-hourglass-half'],
            ['Posted', $k('POSTED'), 'success', 'fas fa-check-circle'],
            ['Nilai Posted', M::format((int) ($kpi['POSTED']->v ?? 0)), 'primary', 'fas fa-coins'],
        ]" />

        <ul class="nav nav-tabs">
            <li class="nav-item"><a class="nav-link {{ $tab === 'journals' ? 'active' : '' }}" href="{{ route('accounting.journal.index') }}">Jurnal</a></li>
            <li class="nav-item"><a class="nav-link {{ $tab === 'sources' ? 'active' : '' }}" href="{{ route('accounting.journal.index', ['tab' => 'sources']) }}">
                Transaksi Sumber @if($tab === 'sources' && $awaiting)<span class="badge badge-danger">{{ $awaiting }}</span>@endif</a></li>
        </ul>

        @if($tab === 'journals')
        <x-tw.card padding="sm">
            <form method="GET" class="d-flex flex-wrap mb-3" style="gap:8px;">
                <input name="number" value="{{ request('number') }}" class="form-control form-control-sm" style="max-width:180px" placeholder="No. jurnal">
                <select name="status" class="form-control form-control-sm" style="max-width:160px">
                    <option value="">Semua status</option>
                    @foreach(['DRAFT','SUBMITTED','POSTED','REVERSED'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $lbl[$s] }}</option>@endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="max-width:150px">
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="max-width:150px">
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr><th>No. Jurnal</th><th>Tanggal</th><th>Sumber</th><th>Deskripsi</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th>Status</th><th>Dibuat</th></tr></thead>
                    <tbody>
                    @forelse($journals as $j)
                        <tr>
                            <td><a href="{{ route('accounting.journal.show', $j->id) }}">{{ $j->journal_number }}</a>@if($j->needs_review)<i class="fas fa-exclamation-triangle text-warning ml-1" title="{{ $j->review_reason }}"></i>@endif</td>
                            <td class="text-nowrap">{{ $j->journal_date->format('d M Y') }}</td>
                            <td>{{ $j->reference_number ?: '-' }}@if($j->journal_type !== 'SOURCE')<br><span class="badge badge-light border">{{ $j->journal_type === 'REVERSAL' ? 'Pembalik' : (\App\Models\Journal::MANUAL_TYPES[$j->journal_type] ?? $j->journal_type) }}</span>@endif</td>
                            <td>{{ \Illuminate\Support\Str::limit($j->description, 60) }}</td>
                            <td class="text-right text-nowrap">{{ M::format((int) $j->total_debit) }}</td>
                            <td class="text-right text-nowrap">{{ M::format((int) $j->total_credit) }}</td>
                            <td><span class="badge {{ $badge[$j->status] ?? 'badge-secondary' }}">{{ $lbl[$j->status] ?? $j->status }}</span></td>
                            <td>{{ $j->creator->username ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada jurnal" message="Buka tab Transaksi Sumber untuk membuat jurnal dari Treasury atau Cash Receipt." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
        @else
        @if($truncated)
            <div class="alert alert-warning rounded-0 mb-0"><strong>Data belum lengkap:</strong> daftar dibatasi 400 transaksi terbaru per jenis, sehingga transaksi yang lebih lama TIDAK tampil.
                Persempit rentang tanggal di bawah (mis. per bulan) agar semua transaksi periode itu terlihat dan bisa dijurnal.</div>
        @endif
        <x-tw.card padding="sm">
            <form method="GET" class="d-flex flex-wrap align-items-end mb-3" style="gap:8px;">
                <input type="hidden" name="tab" value="sources">
                <div><label class="small mb-0">Dari tanggal</label><input type="date" name="src_from" value="{{ request('src_from') }}" class="form-control form-control-sm"></div>
                <div><label class="small mb-0">Sampai</label><input type="date" name="src_to" value="{{ request('src_to') }}" class="form-control form-control-sm"></div>
                <button class="btn btn-sm btn-outline-secondary">Terapkan</button>
                <small class="text-muted">Hanya transaksi sejak tanggal mulai pembukuan ({{ $cutover?->format('d M Y') ?? '—' }}) yang ditampilkan.</small>
            </form>
            <div class="d-flex flex-wrap mb-3" style="gap:8px;">
                <input id="srcSearch" class="form-control form-control-sm" style="max-width:240px" placeholder="Cari nomor / deskripsi">
                <select id="srcModule" class="form-control form-control-sm" style="max-width:170px">
                    <option value="">Semua sumber</option><option value="TREASURY">Treasury</option><option value="CASH_RECEIPT">Cash Receipt</option>
                </select>
                <select id="srcStatus" class="form-control form-control-sm" style="max-width:190px">
                    <option value="">Semua status jurnal</option><option value="NONE">Belum dijurnal</option><option value="DRAFT">Draft</option>
                    <option value="SUBMITTED">Submitted</option><option value="POSTED">Posted</option><option value="PARTIAL">Sebagian</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl" id="srcTable">
                    <thead class="bg-light"><tr><th>Tanggal</th><th>No. Transaksi</th><th>Sumber</th><th>Deskripsi</th><th>Pihak</th><th>Kategori</th><th>Rekening</th><th class="text-right">Nominal</th><th>Status Jurnal</th><th></th></tr></thead>
                    <tbody>
                    @forelse($sources as $s)
                        <tr data-module="{{ $s['module'] }}" data-status="{{ $s['journal_status'] }}" data-text="{{ strtolower($s['number'] . ' ' . $s['description']) }}">
                            <td class="text-nowrap">{{ $s['date']?->format('d M Y') }}</td>
                            <td class="text-nowrap">{{ $s['number'] }}</td>
                            <td>{{ $s['module'] === 'TREASURY' ? 'Treasury' : 'Cash Receipt' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($s['description'], 55) }}@if($s['note'])<br><small class="text-muted">{{ $s['note'] }}</small>@endif</td>
                            <td>{{ $s['counterparty'] }}</td>
                            <td>{{ $s['category'] }}</td>
                            <td>{{ $s['bank'] }}</td>
                            <td class="text-right text-nowrap">{{ M::format($s['amount']) }}</td>
                            <td><span class="badge {{ $badge[$s['journal_status']] ?? 'badge-secondary' }}">{{ $lbl[$s['journal_status']] ?? $s['journal_status'] }}</span></td>
                            <td class="text-center text-nowrap">
                                @if($s['journal_id'])
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.journal.show', $s['journal_id']) }}">Lihat</a>
                                @endif
                                @if($canCreate && $s['eligible'])
                                    <form action="{{ route('accounting.journal.generate') }}" method="POST" class="d-inline js-gen">
                                        @csrf
                                        <input type="hidden" name="source_type" value="{{ $s['source_type'] }}">
                                        <input type="hidden" name="source_id" value="{{ $s['source_id'] }}">
                                        <button class="btn btn-sm btn-primary">Buat Jurnal</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty title="Tidak ada transaksi sumber" message="Batch Treasury berstatus PAID dan penerimaan kas akan tampil di sini." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
        @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Cegah klik ganda "Buat Jurnal" (pagar utama tetap UNIQUE di database).
    document.querySelectorAll('.js-gen').forEach(function (f) {
        f.addEventListener('submit', function () { f.querySelector('button').disabled = true; });
    });
    var rows = document.querySelectorAll('#srcTable tbody tr[data-module]');
    if (!rows.length) return;
    function filter() {
        var q = document.getElementById('srcSearch').value.toLowerCase();
        var m = document.getElementById('srcModule').value, s = document.getElementById('srcStatus').value;
        rows.forEach(function (r) {
            r.style.display = (!q || r.dataset.text.indexOf(q) > -1) && (!m || r.dataset.module === m) && (!s || r.dataset.status === s) ? '' : 'none';
        });
    }
    ['srcSearch', 'srcModule', 'srcStatus'].forEach(function (id) { document.getElementById(id).addEventListener('input', filter); });
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
