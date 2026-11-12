@extends('layouts.app')

@section('title', 'Inspeksi Aset')

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title' => 'Inspeksi Aset',
        'desc'  => 'Catat hasil pemeriksaan fisik aset. Kondisi aset diperbarui mengikuti hasil inspeksi.',
    ])

    <div class="ast-panel">
        <form method="GET" class="ast-toolbar">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nomor / aset / inspektor…" style="min-width:220px;">
            <select name="result" class="form-control form-control-sm"><option value="">Semua hasil</option>
                @foreach(\App\Models\AssetInspection::RESULTS as $k => $v)<option value="{{ $k }}" @selected(($filters['result'] ?? '') === $k)>{{ $v }}</option>@endforeach</select>
            <button class="btn btn-secondary btn-sm">Filter</button>
            <button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#insNew"><i class="fas fa-plus mr-1"></i>Catat Inspeksi</button>
        </form>
        @if($capped)<div class="alert alert-info m-2 py-1 small">Menampilkan 1.000 dari {{ $total }} data terbaru.</div>@endif
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Nomor</th><th>Aset</th><th>Tanggal</th><th>Inspektor</th><th>Kondisi</th><th>Hasil</th><th>Temuan</th><th>Tindak Lanjut</th></tr></thead>
            <tbody>
            @forelse($rows as $i)
                <tr>
                    <td class="ast-code">{{ $i->number }}</td>
                    <td>@if($i->asset)<a href="{{ route('asset.show', $i->asset_id) }}" class="ast-code">{{ $i->asset->code }}</a><div class="ast-sub">{{ $i->asset->name }}</div>@endif</td>
                    <td>{{ $i->inspection_date->format('d M Y') }}</td><td>{{ $i->inspector_name }}</td>
                    <td>{{ \App\Models\Asset::CONDITIONS[$i->condition] ?? $i->condition }}</td>
                    <td><span class="ts-status ts-status-{{ \App\Support\WorkflowStatus::tone($i->result, 'asset') }} ts-status-sm">{{ \App\Models\AssetInspection::RESULTS[$i->result] ?? $i->result }}</span></td>
                    <td style="white-space:normal;">{{ $i->findings ?: '-' }}</td><td style="white-space:normal;">{{ $i->follow_up ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty title="Belum ada inspeksi" message="Catat inspeksi rutin untuk memantau kondisi aset." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div></div>

<div class="modal fade" id="insNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('asset.inspection.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Catat Inspeksi</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-8"><label class="small font-weight-bold">Aset <span class="text-danger">*</span></label>
                    @include('asset._asset_select', ['assets' => $assets, 'id' => 'insAsset'])</div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="inspection_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Inspektor <span class="text-danger">*</span></label>
                    <input name="inspector_name" class="form-control form-control-sm" value="{{ auth()->user()->name }}" maxlength="255" required></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Kondisi <span class="text-danger">*</span></label>
                    <select name="condition" class="form-control form-control-sm" required>@foreach(\App\Models\Asset::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected($k === 'GOOD')>{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Hasil <span class="text-danger">*</span></label>
                    <select name="result" class="form-control form-control-sm" required>@foreach(\App\Models\AssetInspection::RESULTS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-6 mb-0"><label class="small font-weight-bold">Temuan</label><textarea name="findings" class="form-control form-control-sm" rows="3" maxlength="3000"></textarea></div>
                <div class="form-group col-md-6 mb-0"><label class="small font-weight-bold">Tindak Lanjut</label><textarea name="follow_up" class="form-control form-control-sm" rows="3" maxlength="3000"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div>
</div>

@include('asset._scripts')
@endsection
