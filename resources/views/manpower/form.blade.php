@extends('layouts.app')

@section('title', $mp ? 'Edit ' . $mp->request_no : 'Ajukan Kebutuhan Pegawai')

@section('content')
@php
    $positions = \App\Models\ManpowerRequest::POSITIONS;
    $grades    = \App\Models\ManpowerRequest::GRADES;
    $rows = old('rows', $mp
        ? $mp->details->map(fn ($d) => ['position' => $d->position, 'grade' => $d->grade, 'type' => $d->type, 'replaced_id' => $d->replaced_employee_id])->all()
        : [['position' => $positions[0], 'grade' => $grades[0], 'type' => 'NEW', 'replaced_id' => null]]);
@endphp
<div class="container-fluid">
    <a href="{{ $mp ? route('manpower.show', $mp) : route('manpower.index') }}" class="small">&larr; Kembali</a>
    <h4 class="my-2">{{ $mp ? 'Edit ' . $mp->request_no : 'Ajukan Kebutuhan Pegawai' }}</h4>

    <form method="post" action="{{ $mp ? route('manpower.update', $mp) : route('manpower.store') }}">
        @csrf
        @if ($mp) @method('PUT') @endif

        <div class="card mb-3"><div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Pemohon</label>
                    <input class="form-control" value="{{ $mp ? ($mp->requester->name ?? '') : auth()->user()->name }}" disabled>
                </div>
                <div class="form-group col-md-4">
                    <label>Divisi *</label>
                    <select name="department_id" class="form-control" required>
                        <option value="">- pilih -</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id', $mp->department_id ?? null) == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Project</label>
                    <select name="project_id" class="form-control">
                        <option value="">- tidak ada -</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}" @selected(old('project_id', $mp->project_id ?? null) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Tanggal dibutuhkan *</label>
                    <input type="date" name="needed_date" class="form-control" value="{{ old('needed_date', $mp?->needed_date?->format('Y-m-d')) }}">
                </div>
                <div class="form-group col-md-8">
                    <label>Catatan / alasan kebutuhan</label>
                    <textarea name="note" class="form-control" rows="2">{{ old('note', $mp->note ?? '') }}</textarea>
                </div>
            </div>
        </div></div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><b>Kebutuhan Pegawai</b>
                <button type="button" class="btn btn-sm btn-outline-secondary ml-auto" id="addRow">+ Baris</button></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Posisi</th><th style="width:180px">Grade</th><th style="width:130px">Tipe</th><th>Karyawan yang digantikan</th><th style="width:40px"></th></tr></thead>
                    <tbody id="rows">
                    @foreach ($rows as $i => $r)
                        <tr class="js-row">
                            <td><input name="rows[{{ $i }}][position]" list="posList" class="form-control form-control-sm" value="{{ $r['position'] }}" required></td>
                            <td><select name="rows[{{ $i }}][grade]" class="form-control form-control-sm">@foreach ($grades as $g)<option @selected($r['grade'] === $g)>{{ $g }}</option>@endforeach</select></td>
                            <td><select name="rows[{{ $i }}][type]" class="form-control form-control-sm js-type"><option value="NEW" @selected($r['type'] === 'NEW')>NEW</option><option value="REPLACE" @selected($r['type'] === 'REPLACE')>REPLACE</option></select></td>
                            <td>
                                <select name="rows[{{ $i }}][replaced_id]" class="form-control form-control-sm js-repl">
                                    <option value="">- pilih karyawan -</option>
                                    @foreach ($employees as $e)
                                        @continue(in_array($e->id, $taken, true) && (string) ($r['replaced_id'] ?? '') !== (string) $e->id)
                                        <option value="{{ $e->id }}" @selected((string) ($r['replaced_id'] ?? '') === (string) $e->id)>{{ $e->name }} · EMP-{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <datalist id="posList">@foreach ($positions as $p)<option value="{{ $p }}">@endforeach</datalist>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <button name="mode" value="draft" class="btn btn-outline-secondary">Simpan Draft</button>
        <button name="mode" value="submit" class="btn btn-primary">Ajukan</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var tbody = document.getElementById('rows');
    function sync(tr) {
        var rep = tr.querySelector('.js-type').value === 'REPLACE', s = tr.querySelector('.js-repl');
        s.disabled = !rep; if (!rep) s.value = '';
    }
    tbody.querySelectorAll('tr').forEach(sync);
    tbody.addEventListener('change', function (e) { if (e.target.classList.contains('js-type')) sync(e.target.closest('tr')); });
    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.js-del') && tbody.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
    });
    document.getElementById('addRow').addEventListener('click', function () {
        var first = tbody.querySelector('tr'), tr = first.cloneNode(true), i = Date.now() % 100000;
        tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/rows\[\d+\]/, 'rows[' + i + ']'); });
        tr.querySelector('input').value = '';
        tr.querySelector('.js-type').value = 'NEW'; tr.querySelector('.js-repl').value = '';
        tbody.appendChild(tr); sync(tr);
    });
})();
</script>
@endpush
