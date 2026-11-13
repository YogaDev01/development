@extends('layouts.app')

@section('title', 'Jurnal ' . $j->journal_number)

@php
    use App\Support\AccountingMoney as M;
    $badge = ['DRAFT' => 'badge-secondary', 'SUBMITTED' => 'badge-warning', 'POSTED' => 'badge-success', 'REVERSED' => 'badge-dark'];
    $rows = old('lines') ?? $j->lines->map(fn ($l) => [
        'coa_account_id' => $l->coa_account_id, 'description' => $l->description,
        'debit' => $l->debit ? M::format($l->debit) : '', 'credit' => $l->credit ? M::format($l->credit) : '',
        'cost_center_id' => $l->cost_center_id, 'project_id' => $l->project_id,
        'suggested' => $l->suggested_account_id,
    ])->all();
    $suggestedMap = $j->lines->pluck('suggested_account_id', 'coa_account_id');
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        <div class="tw:sr-only">
            <h1>Jurnal {{ $j->journal_number }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('accounting.journal.index') }}">Journal Entries</a></li>
                <li class="breadcrumb-item active">{{ $j->journal_number }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Journal Entries" title="{{ $j->journal_number }}"
            description="{{ $j->trashed() ? 'Draft ini sudah dihapus.' : ($j->description ?: 'Jurnal') }}">
            <x-slot:actions>
                <span class="badge {{ $badge[$j->status] ?? 'badge-secondary' }} p-2">{{ $j->trashed() ? 'Dihapus' : $j->status }}</span>
            </x-slot:actions>
        </x-tw.page-header>

        @if($j->return_reason && $j->status === 'DRAFT')<div class="alert alert-warning rounded-0 mb-0"><strong>Dikembalikan untuk revisi:</strong> {{ $j->return_reason }}</div>@endif
        @if($j->journal_type !== 'SOURCE')<div class="alert alert-info rounded-0 mb-0">Jenis: <strong>{{ \App\Models\Journal::MANUAL_TYPES[$j->journal_type] ?? ($j->journal_type === 'REVERSAL' ? 'Jurnal Pembalik' : $j->journal_type) }}</strong>
            @if($j->reversesJournal) — membalik <a href="{{ route('accounting.journal.show', $j->reverses_journal_id) }}">{{ $j->reversesJournal->journal_number }}</a>@endif
            @if($j->auto_reverse_date) — dibalik otomatis pada {{ $j->auto_reverse_date->format('d M Y') }}@endif</div>@endif
        @if($j->status === 'REVERSED' || $activeReversal)<div class="alert alert-secondary rounded-0 mb-0">Jurnal pembalik:
            @foreach($j->reversals as $r)<a href="{{ route('accounting.journal.show', $r->id) }}">{{ $r->journal_number }}</a> ({{ $r->status }}) @endforeach</div>@endif
        @if($drift)<div class="alert alert-danger rounded-0 mb-0"><strong>Sumber berubah:</strong> {{ $drift }}</div>@endif
        @if($blocker && ! $j->trashed())<div class="alert alert-warning rounded-0 mb-0">{{ $blocker }}</div>@endif

        <x-tw.card padding="sm">
            <form method="POST" action="{{ route('accounting.journal.update', $j->id) }}" id="formJournal">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Tanggal Jurnal</label>
                        <input type="date" name="journal_date" class="form-control" value="{{ old('journal_date', $j->journal_date->format('Y-m-d')) }}" @disabled(! $canEdit) required>
                        <small class="text-muted">Boleh backdate selama periodenya Open.</small></div>
                    <div class="form-group col-md-3"><label>Periode</label><input class="form-control" value="{{ $j->period->name ?? '— belum ada —' }}" disabled></div>
                    <div class="form-group col-md-3"><label>Referensi</label><input name="reference_number" class="form-control" value="{{ old('reference_number', $j->reference_number) }}" maxlength="60" @disabled(! $canEdit)></div>
                    <div class="form-group col-md-3"><label>Dibuat Oleh</label><input class="form-control" value="{{ $j->creator->username ?? '-' }}" disabled></div>
                </div>
                <div class="form-group"><label>Deskripsi</label><input name="description" class="form-control" value="{{ old('description', $j->description) }}" maxlength="500" @disabled(! $canEdit)></div>

                @if($j->sources->isNotEmpty())
                <p class="mb-2 text-muted small">Sumber: {{ $j->sources->pluck('source_number')->filter()->unique()->implode(', ') }}</p>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered mb-2 ts-idx-tbl" id="lineTable">
                        <thead class="bg-light"><tr><th style="min-width:230px">Akun</th><th>Deskripsi</th><th style="width:150px" class="text-right">Debit</th><th style="width:150px" class="text-right">Kredit</th><th style="min-width:130px">Cost Center</th><th style="min-width:130px">Project</th>@if($canEdit)<th style="width:1%"></th>@endif</tr></thead>
                        <tbody id="lineBody">
                        @foreach($rows as $i => $r)
                            <tr>
                                <td>
                                    <select name="lines[{{ $i }}][coa_account_id]" class="form-control form-control-sm" @disabled(! $canEdit) required>
                                        <option value="">— pilih akun —</option>
                                        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) ($r['coa_account_id'] ?? 0) === $a->id)>{{ $a->code }} — {{ $a->name }}@if($a->status !== 'ACTIVE' || ! $a->is_postable) (nonaktif / tidak bisa diposting)@endif</option>@endforeach
                                    </select>
                                    @if(! empty($r['suggested']) && (int) $r['suggested'] !== (int) $r['coa_account_id'])<small class="text-warning">Diubah dari akun usulan</small>@endif
                                </td>
                                <td><input name="lines[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $r['description'] ?? '' }}" maxlength="255" @disabled(! $canEdit)></td>
                                <td><input name="lines[{{ $i }}][debit]" class="form-control form-control-sm text-right js-dr" value="{{ $r['debit'] ?? '' }}" inputmode="decimal" placeholder="0,00" @disabled(! $canEdit)></td>
                                <td><input name="lines[{{ $i }}][credit]" class="form-control form-control-sm text-right js-cr" value="{{ $r['credit'] ?? '' }}" inputmode="decimal" placeholder="0,00" @disabled(! $canEdit)></td>
                                <td><select name="lines[{{ $i }}][cost_center_id]" class="form-control form-control-sm" @disabled(! $canEdit)><option value="">—</option>@foreach($centers as $c)<option value="{{ $c->id }}" @selected((int) ($r['cost_center_id'] ?? 0) === $c->id)>{{ $c->code }}</option>@endforeach</select></td>
                                <td><select name="lines[{{ $i }}][project_id]" class="form-control form-control-sm" @disabled(! $canEdit)><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected((int) ($r['project_id'] ?? 0) === $p->id)>{{ \Illuminate\Support\Str::limit($p->name, 28) }}</option>@endforeach</select></td>
                                @if($canEdit)<td><button type="button" class="btn btn-sm btn-outline-danger js-del" title="Hapus baris"><i class="fas fa-times"></i></button></td>@endif
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr><th colspan="2" class="text-right">Total</th><th class="text-right" id="totDr">0,00</th><th class="text-right" id="totCr">0,00</th><th colspan="{{ $canEdit ? 3 : 2 }}"><span id="balState"></span></th></tr>
                        </tfoot>
                    </table>
                </div>

                @if($canEdit)
                <div class="d-flex justify-content-between flex-wrap" style="gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddLine"><i class="fas fa-plus"></i> Tambah Baris</button>
                    <button class="btn btn-primary">Simpan Draft</button>
                </div>
                @endif
            </form>
        </x-tw.card>

        {{-- Aksi alur kerja --}}
        @unless($j->trashed())
        <x-tw.card padding="sm">
            <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                @if($j->status === 'DRAFT' && $canSubmit)
                    <form action="{{ route('accounting.journal.submit', $j->id) }}" method="POST" onsubmit="return confirm('Submit jurnal ini ke Finance Manager? Setelah submit, jurnal tidak bisa diedit.')">@csrf
                        <button class="btn btn-success">Submit</button></form>
                    <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modalDelete">Hapus Draft</button>
                @endif
                @if($j->status === 'SUBMITTED' && $canPost)
                    <form action="{{ route('accounting.journal.post', $j->id) }}" method="POST" onsubmit="return confirm('Posting jurnal ke General Ledger? Jurnal yang sudah diposting tidak bisa diubah.')">@csrf
                        <button class="btn btn-success" @disabled($drift || $blocker || $sodBlocked)>Posting</button></form>
                    @if($sodBlocked)<span class="text-muted small">Anda pembuat/editor jurnal ini — minta pengguna lain yang memposting.</span>@endif
                    <button type="button" class="btn btn-outline-warning" data-toggle="modal" data-target="#modalReturn">Kembalikan ke Draft</button>
                @endif
                @if($canReverse)<button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modalReverse">Buat Jurnal Pembalik</button>@endif
                @if($j->status === 'POSTED')<span class="text-muted">Diposting oleh {{ optional($j->posted_by ? \App\Models\User::find($j->posted_by) : null)->username }} pada {{ $j->posted_at?->format('d M Y H:i') }}. Koreksi dilakukan dengan jurnal pembalik.</span>@endif
            </div>
        </x-tw.card>
        @endunless

        <x-tw.card padding="sm">
            <h6 class="mb-2">Audit Trail</h6>
            <table class="table table-sm table-bordered mb-0">
                <thead class="bg-light"><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Status</th><th>Alasan</th></tr></thead>
                <tbody>
                @forelse($logs as $l)
                    <tr><td class="text-nowrap">{{ $l->created_at?->format('d M Y H:i') }}</td><td>{{ $l->user->username ?? '-' }}</td><td>{{ $l->action }}</td>
                        <td>{{ $l->from_status ? $l->from_status . ' → ' . $l->to_status : ($l->to_status ?? '') }}</td><td>{{ $l->reason }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">Belum ada catatan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-tw.card>
    </div>
  </div>
</div>

@if($canReverse)
<div class="modal fade" id="modalReverse" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" action="{{ route('accounting.journal.reverse', $j->id) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Buat Jurnal Pembalik</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
      <div class="modal-body">
        <p class="text-muted">Dibuat Draft dengan debit/kredit ditukar. Jurnal asal tetap Posted sampai pembaliknya diposting. Untuk jurnal dari Treasury/Cash Receipt, sumbernya baru bisa dijurnal ulang (jurnal pengganti) setelah pembalik diposting.</p>
        <div class="form-group"><label>Tanggal pembalik <span class="text-danger">*</span></label><input type="date" name="journal_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
        <div class="form-group mb-0"><label>Alasan <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="2" minlength="5" maxlength="255" required></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-danger">Buat Draft Pembalik</button></div>
    </form>
  </div></div>
</div>
@endif

<div class="modal fade" id="modalReturn" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" action="{{ route('accounting.journal.return', $j->id) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Kembalikan ke Draft</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
      <div class="modal-body"><label>Alasan revisi <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="2" minlength="5" maxlength="255" required></textarea></div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-warning">Kembalikan</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" action="{{ route('accounting.journal.destroy', $j->id) }}">@csrf @method('DELETE')
      <div class="modal-header"><h5 class="modal-title">Hapus Draft</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
      <div class="modal-body"><p class="text-muted">Draft dihapus (tercatat di audit). Sumber transaksinya bisa dijurnal ulang.</p><label>Alasan</label><input name="reason" class="form-control" maxlength="255"></div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-danger">Hapus</button></div>
    </form>
  </div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Hanya tampilan: selisih debit/kredit dihitung dari teks input dalam SEN (integer, tanpa float).
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
    document.getElementById('lineBody').addEventListener('input', totals);
    totals();

    var add = document.getElementById('btnAddLine');
    if (!add) return;
    add.addEventListener('click', function () {
        var body = document.getElementById('lineBody'), first = body.rows[0], idx = body.rows.length;
        var tr = first.cloneNode(true);
        tr.querySelectorAll('[name]').forEach(function (e) {
            e.name = e.name.replace(/lines\[\d+\]/, 'lines[' + idx + ']');
            if (e.tagName === 'INPUT') e.value = '';
            else if (e.tagName === 'SELECT') e.selectedIndex = 0;
        });
        tr.querySelectorAll('small').forEach(function (s) { s.remove(); });
        body.appendChild(tr);
    });
    document.getElementById('lineBody').addEventListener('click', function (e) {
        var b = e.target.closest('.js-del');
        if (!b) return;
        var body = document.getElementById('lineBody');
        if (body.rows.length <= 2) return alert('Jurnal minimal dua baris.');
        b.closest('tr').remove();
        // Susun ulang indeks agar tetap berurutan.
        Array.prototype.forEach.call(body.rows, function (tr, i) {
            tr.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']'); });
        });
        totals();
    });
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
