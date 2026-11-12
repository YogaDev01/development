@extends('layouts.app')

@section('title', 'Stock Opname Aset')

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title'   => 'Stock Opname Aset',
        'desc'    => 'Hitung fisik aset per lokasi. Daftar aset diambil dari data saat opname dibuat.',
        'actions' => '<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#soNew"><i class="fas fa-plus mr-1"></i>Buat Opname</button>',
    ])

    <div class="ast-panel"><div class="table-responsive"><table class="ast-table">
        <thead><tr><th>Nomor</th><th>Lokasi</th><th>Tanggal</th><th class="num">Aset</th><th class="num">Sesuai</th><th class="num">Tidak Ditemukan</th><th class="num">Selisih</th><th>Dibuat</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($opnames as $o)
            <tr>
                <td class="ast-code">{{ $o->number }}</td><td>{{ $o->location?->name }}</td><td>{{ $o->opname_date->format('d M Y') }}</td>
                <td class="num">{{ $o->items_count }}</td><td class="num">{{ $o->matched_count }}</td>
                <td class="num">{{ $o->missing_count }}</td><td class="num">{{ $o->discrepancy_count }}</td>
                <td>{{ $o->creator?->name }}</td><td><x-status :status="$o->status" context="asset" /></td>
                <td class="text-right"><a href="{{ route('asset.opname.show', $o) }}" class="btn btn-secondary btn-sm">{{ $o->status === 'COUNTING' ? 'Isi Hasil' : 'Lihat' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="10"><x-empty title="Belum ada stock opname" message="Buat opname untuk mencocokkan data aset dengan kondisi fisik di lokasi." /></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div></div>

<div class="modal fade" id="soNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('asset.opname.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Buat Stock Opname</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="small font-weight-bold">Lokasi <span class="text-danger">*</span></label>
                    <select name="asset_location_id" class="form-control form-control-sm" required><option value="">— Pilih —</option>
                        @foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
                <div class="form-group"><label class="small font-weight-bold">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="opname_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                <div class="form-group mb-0"><label class="small font-weight-bold">Catatan</label><textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Buat</button></div>
        </form>
    </div>
</div>
@endsection
