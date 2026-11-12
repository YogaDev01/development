@extends('layouts.app')

@section('title', 'All Assets')

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title'   => 'All Assets',
        'desc'    => 'Register seluruh aset tetap perusahaan.',
        'actions' => '<a href="' . route('asset.export', request()->query()) . '" class="btn btn-secondary btn-sm"><i class="fas fa-file-csv mr-1"></i>Export</a>'
            . '<a href="' . route('asset.create') . '" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Registrasi Aset</a>',
    ])

    <div class="ast-panel">
        <form method="GET" class="ast-toolbar">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari kode, nama, serial, merek…" style="min-width:220px;">
            <select name="category" class="form-control form-control-sm">
                <option value="">Semua kategori</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <select name="location" class="form-control form-control-sm">
                <option value="">Semua lokasi</option>
                @foreach($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
            </select>
            <select name="status" class="form-control form-control-sm">
                <option value="">Semua status</option>
                @foreach(\App\Models\Asset::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ \App\Support\WorkflowStatus::label($s, 'asset') }}</option>@endforeach
            </select>
            <select name="condition" class="form-control form-control-sm">
                <option value="">Semua kondisi</option>
                @foreach(\App\Models\Asset::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected(($filters['condition'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i>Filter</button>
            @if(array_filter($filters))<a href="{{ route('asset.index') }}" class="btn btn-link btn-sm">Reset</a>@endif
        </form>

        @if($capped)
            <div class="alert alert-info m-2 py-1 small">Menampilkan 1.000 dari {{ number_format($total, 0, ',', '.') }} aset terbaru. Persempit filter untuk melihat sisanya.</div>
        @endif

        <div class="table-responsive">
            <table class="ast-table">
                <thead><tr>
                    <th>Kode</th><th>Aset</th><th>Kategori</th><th>Lokasi</th><th>Pemegang</th>
                    <th class="num">Harga Perolehan</th><th class="num">Nilai Buku</th><th>Kondisi</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($assets as $a)
                    <tr>
                        <td><a href="{{ route('asset.show', $a) }}" class="ast-code">{{ $a->code }}</a></td>
                        <td>{{ $a->name }}<div class="ast-sub">{{ trim($a->brand . ' ' . $a->model) ?: '-' }}{{ $a->serial_number ? ' · SN ' . $a->serial_number : '' }}</div></td>
                        <td>{{ $a->category?->name }}</td>
                        <td>{{ $a->location?->name ?? '-' }}</td>
                        <td>{{ $a->holderName() }}<div class="ast-sub">{{ $a->department?->name }}</div></td>
                        <td class="num">{{ number_format($a->cost, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($a->bookValue(), 0, ',', '.') }}</td>
                        <td>{{ $a->conditionLabel() }}</td>
                        <td><x-status :status="$a->status" context="asset" /></td>
                    </tr>
                @empty
                    <tr><td colspan="9">
                        <x-empty title="Belum ada aset" message="Tidak ada aset yang cocok dengan filter, atau belum ada aset terdaftar.">
                            <x-slot:action><a href="{{ route('asset.create') }}" class="btn btn-primary btn-sm">Registrasi Aset</a></x-slot:action>
                        </x-empty>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div></div>
@endsection
