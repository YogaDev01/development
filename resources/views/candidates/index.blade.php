@extends('layouts.app')

@section('title', 'Kandidat')

@section('content')
@php
    $availBadge = ['TERSEDIA' => 'success', 'DIPROSES' => 'primary', 'ONBOARD' => 'dark'];
    $g = \App\Models\Candidate::GENDERS;
@endphp
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Talentpool</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">HRIS</li>
                <li class="breadcrumb-item active">Talentpool</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Recruitment Management" title="Talentpool" description="Semua pelamar, ketersediaan, dan dokumennya.">
            <x-slot:actions>
                <x-tw.button href="{{ route('candidates.template') }}" variant="secondary">Template Excel</x-tw.button>
                <x-tw.button type="button" variant="secondary" data-toggle="modal" data-target="#modalImport">Impor Excel</x-tw.button>
                <x-tw.button type="button" data-toggle="modal" data-target="#modalTambah"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Kandidat</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        @if ($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        @php
            $avCount = $rows->countBy(fn ($c) => $c->availability());
            $steps = [
                ['Tersedia', (int) ($avCount['TERSEDIA'] ?? 0), 'success', 'TERSEDIA'],
                ['Diproses', (int) ($avCount['DIPROSES'] ?? 0), 'primary', 'DIPROSES'],
                ['Onboard',  (int) ($avCount['ONBOARD'] ?? 0),  'dark',    'ONBOARD'],
            ];
            $groups = [['Ketersediaan Kandidat', ['TERSEDIA', 'DIPROSES', 'ONBOARD']]];
        @endphp
        <x-workflow-stepper :steps="$steps" :compact="true" :groups="$groups" table="tblTalentpool" field="availability" />

        @include('partials.list-toolbar', [
            'prefix' => 'tp',
            'filters' => [['id' => 'Position', 'label' => 'Posisi'], ['id' => 'Source', 'label' => 'Sumber'], ['id' => 'Education', 'label' => 'Pendidikan']],
            'placeholder' => 'Nama / kode / telepon',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblTalentpool" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col">Kandidat</th><th scope="col">Posisi</th><th scope="col">Pendidikan</th><th scope="col">Sumber</th><th scope="col">Ketersediaan</th><th scope="col">Aktivitas terakhir</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $c)
                        @php $av = $c->availability(); @endphp
                        <tr data-availability="{{ $av }}" data-position="{{ $c->position ?: '-' }}" data-source="{{ $c->source ?: '-' }}" data-education="{{ $c->education ?: '-' }}">
                            <td><a href="{{ route('candidates.show', $c) }}" class="font-weight-bold">{{ $c->name }}</a><div class="small text-muted">{{ $c->code }} · {{ $c->phone }}</div></td>
                            <td>{{ $c->position ?: '-' }}<div class="small text-muted">{{ $c->grade }}</div></td>
                            <td>{{ $c->education ?: '-' }}</td>
                            <td>{{ $c->source ?: '-' }}</td>
                            <td><span class="badge badge-{{ $availBadge[$av] }}">{{ $av }}</span></td>
                            <td class="small text-muted">{{ $c->logs->first()->description ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Belum ada kandidat" message="Tambahkan kandidat atau impor dari Excel." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" action="{{ route('candidates.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah Kandidat</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-6"><label>Name *</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
                <div class="form-group col-md-6"><label>Phone Number *</label><input name="phone" class="form-control" value="{{ old('phone') }}" required></div>
                <div class="form-group col-md-6"><label>Place of Birth</label><input name="birth_place" class="form-control" value="{{ old('birth_place') }}"></div>
                <div class="form-group col-md-6"><label>Date of Birth *</label><input type="date" name="birth_date" class="form-control" value="{{ old('birth_date') }}" required></div>
                <div class="form-group col-md-12"><label>Address</label><input name="address" class="form-control" value="{{ old('address') }}"></div>
                <div class="form-group col-md-4"><label>Gender</label><select name="gender" class="form-control"><option value="">-</option>@foreach ($g as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label>Marital Status</label><select name="marital_status" class="form-control"><option value="">-</option>@foreach (\App\Models\Candidate::MARITAL as $v)<option>{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label>Last Education</label><select name="education" class="form-control"><option value="">-</option>@foreach (\App\Models\Candidate::EDUCATION as $v)<option>{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label>Posisi dilamar</label><input name="position" list="posList" class="form-control" value="{{ old('position') }}"></div>
                <div class="form-group col-md-4"><label>Grade</label><select name="grade" class="form-control"><option value="">-</option>@foreach (\App\Models\ManpowerRequest::GRADES as $v)<option>{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label>Sumber</label><select name="source" class="form-control"><option value="">-</option>@foreach (\App\Models\Candidate::SOURCES as $v)<option>{{ $v }}</option>@endforeach</select></div>
            </div></div>
            <datalist id="posList">@foreach (\App\Models\ManpowerRequest::POSITIONS as $p)<option value="{{ $p }}">@endforeach</datalist>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('candidates.import') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Impor Kandidat dari Excel</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <p class="small text-muted">Gunakan <a href="{{ route('candidates.template') }}">template</a> (kolom wajib: name, phone_number, date_of_birth dengan format YYYY-MM-DD). Format .csv / .xls / .xlsx, maksimal 500 baris.</p>
                <input type="file" name="file" class="form-control-file" accept=".csv,.xls,.xlsx" required>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Impor</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>@if ($errors->any()) $('#modalTambah').modal('show'); @endif</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

@push('scripts')
<script src="@aset('js/tesys-list-table.js')"></script>
<script>
$(function () {
    TesysListTable.init({
        table: '#tblTalentpool', order: [[0, 'asc']], search: '#tpSearch', clear: '#tpClear',
        filters: [
            { target: '#tpFilterPosition', key: 'position', label: 'Semua Posisi' },
            { target: '#tpFilterSource', key: 'source', label: 'Semua Sumber', search: false },
            { target: '#tpFilterEducation', key: 'education', label: 'Semua Pendidikan', search: false }
        ]
    });
});
</script>
@endpush
