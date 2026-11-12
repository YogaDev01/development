@extends('layouts.app')

@section('title', $opname->number)

@php $edit = $opname->status === 'COUNTING'; @endphp

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title'   => 'Stock Opname ' . $opname->number,
        'desc'    => ($opname->location?->name ?? '-') . ' · ' . $opname->opname_date->format('d M Y') . ' · ' . $opname->items->count() . ' aset',
        'actions' => '<a href="' . route('asset.opname.index') . '" class="btn btn-secondary btn-sm">Kembali</a>',
    ])

    <div class="mb-3"><x-status :status="$opname->status" context="asset" size="md" />
        @if($opname->notes)<span class="ast-sub ml-2">{{ $opname->notes }}</span>@endif</div>

    <form method="POST" action="{{ route('asset.opname.update', $opname) }}">
        @csrf @method('PUT')
        <div class="ast-panel">
            @if($edit)
                <div class="ast-toolbar"><button type="button" class="btn btn-secondary btn-sm" id="soAllMatch">Tandai semua "Sesuai"</button>
                    <span class="ast-sub align-self-center">Simpan berkali-kali boleh. Aset "Tidak Ditemukan" ditindaklanjuti lewat Disposal.</span></div>
            @endif
            <div class="table-responsive"><table class="ast-table">
                <thead><tr><th>Kode</th><th>Aset</th><th>Serial</th><th>Kondisi Tercatat</th><th>Hasil</th><th>Kondisi Fisik</th><th>Catatan</th></tr></thead>
                <tbody>
                @forelse($opname->items as $it)
                    <tr>
                        <td class="ast-code">{{ $it->asset?->code }}</td><td>{{ $it->asset?->name }}</td><td>{{ $it->asset?->serial_number ?: '-' }}</td>
                        <td>{{ \App\Models\Asset::CONDITIONS[$it->asset?->condition] ?? '-' }}</td>
                        @if($edit)
                            <td><select name="items[{{ $it->id }}][result]" class="form-control form-control-sm js-so-result"><option value="">—</option>
                                @foreach(\App\Models\AssetOpnameItem::RESULTS as $k => $v)<option value="{{ $k }}" @selected($it->result === $k)>{{ $v }}</option>@endforeach</select></td>
                            <td><select name="items[{{ $it->id }}][condition]" class="form-control form-control-sm"><option value="">(tetap)</option>
                                @foreach(\App\Models\Asset::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected($it->condition === $k)>{{ $v }}</option>@endforeach</select></td>
                            <td><input name="items[{{ $it->id }}][notes]" value="{{ $it->notes }}" class="form-control form-control-sm" maxlength="500"></td>
                        @else
                            <td>@if($it->result)<x-status :status="$it->result" context="asset" />@else - @endif</td>
                            <td>{{ \App\Models\Asset::CONDITIONS[$it->condition] ?? '-' }}</td><td>{{ $it->notes ?: '-' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty title="Tidak ada aset di lokasi ini" message="Saat opname dibuat, tidak ada aset aktif yang tercatat di lokasi tersebut." /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        @if($edit && $opname->items->isNotEmpty())
            <div class="d-flex justify-content-end mb-3"><button class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan Hasil</button></div>
        @endif
    </form>

    @if($edit && $canManage)
        <form method="POST" action="{{ route('asset.opname.complete', $opname) }}" class="d-flex justify-content-end mb-4" onsubmit="return confirm('Selesaikan opname? Hasil tidak bisa diubah lagi.')">
            @csrf<button class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i>Selesaikan Opname</button>
        </form>
    @endif
</div></div>
@endsection

@push('scripts')
<script>
$('#soAllMatch').on('click', function () { $('.js-so-result').each(function () { if (!this.value) this.value = 'MATCHED'; }); });
</script>
@endpush
