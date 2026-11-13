@extends('layouts.app')

@section('title', 'Jurnal Manual')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        <div class="tw:sr-only">
            <h1>Jurnal Manual</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('accounting.journal.index') }}">Journal Entries</a></li>
                <li class="breadcrumb-item active">Jurnal Manual</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Journal Entries" title="Jurnal Manual"
            description="Untuk penyesuaian, akrual, reklasifikasi, penyusutan, dan saldo awal. Alur approval dan posting sama dengan jurnal dari Treasury/Cash Receipt; bukan pengganti jurnal otomatis." />

        <x-tw.card padding="sm">
            <form method="POST" action="{{ route('accounting.journal.manual') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Jenis Jurnal <span class="text-danger">*</span></label>
                        <select name="journal_type" id="jType" class="form-control" required>
                            @foreach($types as $k => $l)<option value="{{ $k }}" @selected(old('journal_type') === $k)>{{ $l }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-3"><label>Tanggal Jurnal <span class="text-danger">*</span></label>
                        <input type="date" name="journal_date" class="form-control" value="{{ old('journal_date', now()->toDateString()) }}" required>
                        <small class="text-muted">Boleh backdate selama periodenya Open.</small></div>
                    <div class="form-group col-md-3" id="boxReverse" style="display:none"><label>Dibalik Otomatis Pada</label>
                        <input type="date" name="auto_reverse_date" class="form-control" value="{{ old('auto_reverse_date') }}">
                        <small class="text-muted">Khusus akrual: Draft pembalik dibuat saat jurnal diposting.</small></div>
                    <div class="form-group col-md-2"><label>Referensi</label><input name="reference_number" class="form-control" maxlength="60" value="{{ old('reference_number') }}"></div>
                </div>
                <div class="form-group"><label>Deskripsi <span class="text-danger">*</span></label><input name="description" class="form-control" maxlength="500" value="{{ old('description') }}" required></div>

                @php $rows = old('lines') ?? [['coa_account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''], ['coa_account_id' => '', 'description' => '', 'debit' => '', 'credit' => '']]; @endphp
                <div class="table-responsive">
                    <table class="table table-bordered mb-2 ts-idx-tbl">
                        <thead class="bg-light"><tr><th style="min-width:230px">Akun</th><th>Deskripsi</th><th style="width:150px" class="text-right">Debit</th><th style="width:150px" class="text-right">Kredit</th><th style="min-width:130px">Cost Center</th><th style="min-width:130px">Project</th><th style="width:1%"></th></tr></thead>
                        <tbody id="lineBody">
                        @foreach($rows as $i => $r)
                            <tr>
                                <td><select name="lines[{{ $i }}][coa_account_id]" class="form-control form-control-sm" required>
                                    <option value="">— pilih akun —</option>
                                    @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) ($r['coa_account_id'] ?? 0) === $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
                                </select></td>
                                <td><input name="lines[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $r['description'] ?? '' }}" maxlength="255"></td>
                                <td><input name="lines[{{ $i }}][debit]" class="form-control form-control-sm text-right js-dr" value="{{ $r['debit'] ?? '' }}" inputmode="decimal" placeholder="0,00"></td>
                                <td><input name="lines[{{ $i }}][credit]" class="form-control form-control-sm text-right js-cr" value="{{ $r['credit'] ?? '' }}" inputmode="decimal" placeholder="0,00"></td>
                                <td><select name="lines[{{ $i }}][cost_center_id]" class="form-control form-control-sm"><option value="">—</option>@foreach($centers as $c)<option value="{{ $c->id }}" @selected((int) ($r['cost_center_id'] ?? 0) === $c->id)>{{ $c->code }}</option>@endforeach</select></td>
                                <td><select name="lines[{{ $i }}][project_id]" class="form-control form-control-sm"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected((int) ($r['project_id'] ?? 0) === $p->id)>{{ \Illuminate\Support\Str::limit($p->name, 28) }}</option>@endforeach</select></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger js-del" title="Hapus baris"><i class="fas fa-times"></i></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="bg-light"><tr><th colspan="2" class="text-right">Total</th><th class="text-right" id="totDr">0,00</th><th class="text-right" id="totCr">0,00</th><th colspan="3"><span id="balState"></span></th></tr></tfoot>
                    </table>
                </div>
                <div class="d-flex justify-content-between flex-wrap" style="gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddLine"><i class="fas fa-plus"></i> Tambah Baris</button>
                    <div><a href="{{ route('accounting.journal.index') }}" class="btn btn-light">Batal</a> <button class="btn btn-primary">Simpan sebagai Draft</button></div>
                </div>
            </form>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Tampilan saja: selisih dihitung dalam SEN (integer); validasi sebenarnya di server.
    function sen(v) {
        v = (v || '').trim().replace(/^Rp\s*/i, '');
        if (!/^((\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?|,\d{1,2})$/.test(v)) return 0;
        var p = v.split(','), i = (p[0] || '').replace(/\./g, '') || '0', d = ((p[1] || '') + '00').slice(0, 2);
        return parseInt(i, 10) * 100 + parseInt(d, 10);
    }
    function fmt(n) {
        var s = String(n % 100); if (s.length < 2) s = '0' + s;
        return Math.floor(n / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + s;
    }
    function totals() {
        var dr = 0, cr = 0;
        document.querySelectorAll('.js-dr').forEach(function (e) { dr += sen(e.value); });
        document.querySelectorAll('.js-cr').forEach(function (e) { cr += sen(e.value); });
        document.getElementById('totDr').textContent = fmt(dr);
        document.getElementById('totCr').textContent = fmt(cr);
        var s = document.getElementById('balState');
        if (dr === cr && dr > 0) { s.textContent = 'Seimbang'; s.className = 'text-success'; }
        else { s.textContent = 'Selisih ' + fmt(Math.abs(dr - cr)); s.className = 'text-danger'; }
    }
    var body = document.getElementById('lineBody');
    body.addEventListener('input', totals);
    totals();

    function reindex() {
        Array.prototype.forEach.call(body.rows, function (tr, i) {
            tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']'); });
        });
    }
    document.getElementById('btnAddLine').addEventListener('click', function () {
        var tr = body.rows[0].cloneNode(true);
        tr.querySelectorAll('input').forEach(function (e) { e.value = ''; });
        tr.querySelectorAll('select').forEach(function (e) { e.selectedIndex = 0; });
        body.appendChild(tr); reindex();
    });
    body.addEventListener('click', function (e) {
        var b = e.target.closest('.js-del');
        if (!b) return;
        if (body.rows.length <= 2) return alert('Jurnal minimal dua baris.');
        b.closest('tr').remove(); reindex(); totals();
    });

    var t = document.getElementById('jType');
    function sync() { document.getElementById('boxReverse').style.display = t.value === 'ACCRUAL' ? '' : 'none'; }
    t.addEventListener('change', sync); sync();
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
