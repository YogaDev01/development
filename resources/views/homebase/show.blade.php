@extends('layouts.app')

@section('title', 'Detail Homebase')

@section('content')
<div class="content master-page">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0" style="font-size:20px;font-weight:700;color:var(--text);">Detail Homebase</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/homebase') }}">Homebase</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="mb-3">
            <a href="{{ url('/homebase') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-warehouse mr-1"></i> Informasi Homebase</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 detail-table">
                    <tr><th>Alamat</th><td>{{ $homebase->address }}</td></tr>
                    <tr><th>Region</th><td>{{ $homebase->region?->region_name ?? '-' }}</td></tr>
                    <tr><th>Pulau</th><td>{{ $homebase->region?->pulau?->name ?? '-' }}</td></tr>
                    <tr><th>Koordinat</th><td>{{ $homebase->latitude !== null ? $homebase->latitude . ', ' . $homebase->longitude : '-' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user-tag mr-1"></i> PIC Homebase ({{ $homebase->details->count() }})</h3></div>
            <div class="card-body">
                <form action="{{ url('/homebase/' . $homebase->id . '/pic') }}" method="POST" class="form-row align-items-start mb-3">
                    @csrf
                    <div class="col-md-8 mb-2">
                        <select name="pic_id" id="selPic" class="form-control @error('pic_id') is-invalid @enderror">
                            <option value="">— Pilih PIC —</option>
                            @foreach($availablePics as $pic)
                                <option value="{{ $pic->id }}" @selected((string) old('pic_id') === (string) $pic->id)>
                                    {{ $pic->pic }}{{ $pic->position ? ' — ' . $pic->position : '' }} ({{ \App\Models\MasterPic::TYPES[$pic->type] ?? $pic->type }})
                                </option>
                            @endforeach
                        </select>
                        @error('pic_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-2">
                        <button type="submit" class="btn btn-success btn-block" @disabled($availablePics->isEmpty())>
                            <i class="fas fa-plus mr-1"></i> Tambah PIC
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr><th width="5%">No</th><th>Nama PIC</th><th>Jabatan</th><th>Tipe</th><th>Telepon</th><th>Email</th><th class="text-center" width="90">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($homebase->details as $detail)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $detail->pic?->pic }}</strong></td>
                                    <td>{{ $detail->pic?->position ?? '-' }}</td>
                                    <td>{{ \App\Models\MasterPic::TYPES[$detail->pic?->type] ?? $detail->pic?->type }}</td>
                                    <td>{{ $detail->pic?->phone ?? '-' }}</td>
                                    <td>{{ $detail->pic?->email ?? '-' }}</td>
                                    <td class="text-center">
                                        <form action="{{ url('/homebase/' . $homebase->id . '/pic/' . $detail->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas PIC dari homebase?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas PIC">
                                                <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty title="Belum ada PIC di homebase ini" icon="fas fa-user-tag" compact /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@include('master-data._styles')
@include('master-data._confirm_delete')

@push('scripts')
<script>
    TesysSearchableSelect.init('#selPic', { placeholder: '— Pilih PIC —', searchPlaceholder: 'Cari PIC...' });
</script>
@endpush
