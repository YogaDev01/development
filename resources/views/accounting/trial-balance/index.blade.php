@extends('layouts.app')

@section('title', 'Trial Balance')

@php use App\Support\AccountingMoney as M; $z = fn ($n) => M::format($n, true); @endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Trial Balance</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">Trial Balance</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="Trial Balance"
            description="Saldo awal, mutasi, dan saldo akhir per akun dari jurnal yang sudah diposting. Klik akun untuk membuka buku besarnya." />

        <x-tw.card padding="sm">
            <form method="GET" class="form-row align-items-end">
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Periode</label>
                    <select name="period_id" class="form-control form-control-sm"><option value="">Rentang tanggal</option>
                        @foreach($periods as $p)<option value="{{ $p->id }}" @selected(request('period_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Dari</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Sampai</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Tipe</label>
                    <select name="type" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($types as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $t[0] }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Kategori</label>
                    <select name="category" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($categories as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="zero" name="zero" value="1" @checked(request()->boolean('zero'))><label class="custom-control-label small" for="zero">Tampilkan akun nol</label></div></div>
                <div class="form-group col-12 mb-2 d-flex flex-wrap" style="gap:6px;">
                    <button class="btn btn-sm btn-primary">Tampilkan</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExpand">Buka semua</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCollapse">Tutup semua</button>
                    @if($canExport)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['csv' => 1]) }}"><i class="fas fa-file-csv"></i> CSV</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    @endif
                </div>
            </form>
        </x-tw.card>

        @if(! $tb['balanced'])
            <div class="alert alert-danger rounded-0 mb-0">Trial Balance TIDAK seimbang (total debit ≠ kredit). Ini tidak seharusnya terjadi — hubungi tim IT sebelum memakai laporan ini.</div>
        @endif

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 ts-idx-tbl" id="tbTable">
                    <thead class="bg-light">
                        <tr><th rowspan="2" class="align-middle">Kode</th><th rowspan="2" class="align-middle">Nama Akun</th>
                            <th colspan="2" class="text-center">Saldo Awal</th><th colspan="2" class="text-center">Mutasi</th><th colspan="2" class="text-center">Saldo Akhir</th></tr>
                        <tr><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr>
                    </thead>
                    <tbody>
                    @forelse($tb['rows'] as $r)
                        @php $a = $r['account']; @endphp
                        <tr data-id="{{ $a->id }}" data-parent="{{ $a->parent_id }}" class="{{ $r['is_parent'] ? 'font-weight-bold table-light' : '' }}">
                            <td class="text-nowrap">{{ $a->code }}</td>
                            <td style="padding-left:{{ 8 + $r['depth'] * 18 }}px">
                                @if($r['is_parent'])<a href="#" class="js-toggle mr-1 text-muted" aria-label="Buka/tutup"><i class="fas fa-caret-down"></i></a>@endif
                                @unless($r['is_parent'])<a href="{{ route('accounting.ledger.index', ['account_id' => $a->id, 'from' => $from, 'to' => $to]) }}">{{ $a->name }}</a>@else{{ $a->name }}@endunless
                            </td>
                            <td class="text-right text-nowrap">{{ $z($r['od']) }}</td><td class="text-right text-nowrap">{{ $z($r['oc']) }}</td>
                            <td class="text-right text-nowrap">{{ $z($r['pd']) }}</td><td class="text-right text-nowrap">{{ $z($r['pc']) }}</td>
                            <td class="text-right text-nowrap">{{ $z($r['ed']) }}</td><td class="text-right text-nowrap">{{ $z($r['ec']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada saldo" message="Belum ada jurnal yang diposting pada rentang ini." /></td></tr>
                    @endforelse
                    </tbody>
                    <tfoot class="bg-light"><tr><th colspan="2" class="text-right">Total (seluruh akun)</th>
                        @foreach(['od','oc','pd','pc','ed','ec'] as $k)<th class="text-right text-nowrap">{{ M::format($tb['sum'][$k]) }}</th>@endforeach</tr></tfoot>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('#tbTable tbody tr[data-id]'));
    var closed = {};
    function descendants(id, out) {
        rows.forEach(function (r) { if (r.dataset.parent === String(id)) { out.push(r); descendants(r.dataset.id, out); } });
        return out;
    }
    function apply() {
        rows.forEach(function (r) { r.style.display = ''; });
        Object.keys(closed).forEach(function (id) {
            if (closed[id]) descendants(id, []).forEach(function (r) { r.style.display = 'none'; });
        });
    }
    document.querySelectorAll('.js-toggle').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var id = a.closest('tr').dataset.id; closed[id] = !closed[id]; apply();
            a.querySelector('i').className = closed[id] ? 'fas fa-caret-right' : 'fas fa-caret-down';
        });
    });
    function all(state) {
        document.querySelectorAll('.js-toggle').forEach(function (a) {
            closed[a.closest('tr').dataset.id] = state;
            a.querySelector('i').className = state ? 'fas fa-caret-right' : 'fas fa-caret-down';
        });
        apply();
    }
    document.getElementById('btnExpand').addEventListener('click', function () { all(false); });
    document.getElementById('btnCollapse').addEventListener('click', function () { all(true); });
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
