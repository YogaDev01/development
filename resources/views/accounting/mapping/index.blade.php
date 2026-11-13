@extends('layouts.app')

@section('title', 'Account Mapping')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        <div class="tw:sr-only">
            <h1>Account Mapping</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">Account Mapping</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="Account Mapping"
            description="Akun usulan saat jurnal dibuat dari Treasury / Cash Receipt. Sisi bank otomatis memakai akun yang tertaut ke rekening transaksi. User tetap bisa mengganti akun per baris saat review jurnal.">
            <x-slot:actions>
                @if($canManage)<x-tw.button id="btnAddMap"><i class="fas fa-plus" aria-hidden="true"></i> Mapping Baru</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr><th>Sumber</th><th>Tipe Transaksi</th><th>Pembeda</th><th>Debit</th><th>Kredit</th><th>Berlaku</th><th>Status</th>@if($canManage)<th class="text-center">Aksi</th>@endif</tr></thead>
                    <tbody>
                    @forelse($mappings as $m)
                        <tr>
                            <td>{{ $modules[$m->source_module] ?? $m->source_module }}</td>
                            <td>{{ $m->transaction_label }}</td>
                            <td>{{ $m->match_key ?: 'Umum' }}</td>
                            <td>{!! $m->debit_is_bank ? '<em class="text-muted">Rekening bank transaksi</em>' : e(($m->debitAccount->code ?? '?') . ' — ' . ($m->debitAccount->name ?? '?')) !!}</td>
                            <td>{!! $m->credit_is_bank ? '<em class="text-muted">Rekening bank transaksi</em>' : e(($m->creditAccount->code ?? '?') . ' — ' . ($m->creditAccount->name ?? '?')) !!}</td>
                            <td class="text-nowrap">{{ $m->effective_from->format('d M Y') }} – {{ $m->effective_to ? $m->effective_to->format('d M Y') : 'seterusnya' }}</td>
                            <td><span class="badge {{ $m->status === 'ACTIVE' ? 'badge-success' : 'badge-secondary' }}">{{ $m->status === 'ACTIVE' ? 'Aktif' : 'Nonaktif' }}</span></td>
                            @if($canManage)
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary js-edit-map"
                                    data-id="{{ $m->id }}" data-module="{{ $m->source_module }}" data-type="{{ $m->transaction_type }}"
                                    data-key="{{ $m->match_key }}" data-debit="{{ $m->debit_account_id }}" data-credit="{{ $m->credit_account_id }}"
                                    data-from="{{ $m->effective_from->format('Y-m-d') }}" data-to="{{ $m->effective_to?->format('Y-m-d') }}"
                                    data-status="{{ $m->status }}" data-desc="{{ $m->description }}" title="Edit"><i class="fas fa-pen"></i></button>
                                <form action="{{ route('accounting.mapping.destroy', $m->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus mapping ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada mapping" message="Tanpa mapping, transaksi belum bisa dijadikan jurnal otomatis." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

@if($canManage)
<div class="modal fade" id="modalMap" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form id="formMap" method="POST" action="{{ route('accounting.mapping.store') }}">@csrf
      <input type="hidden" name="_method" id="mMethod" value="POST">
      <div class="modal-header"><h5 class="modal-title" id="mTitle">Mapping Baru</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4"><label>Sumber <span class="text-danger">*</span></label>
            <select name="source_module" id="mModule" class="form-control" required>
              @foreach($modules as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select></div>
          <div class="form-group col-md-8"><label>Tipe Transaksi <span class="text-danger">*</span></label>
            <select name="transaction_type" id="mType" class="form-control" required></select></div>
        </div>
        <div class="form-group"><label>Pembeda (opsional)</label>
          <input name="match_key" id="mKey" class="form-control" maxlength="60" placeholder="mis. request_type:5 — kosongkan untuk berlaku umum">
          <small class="text-muted">Mapping dengan pembeda lebih spesifik dari yang umum.</small></div>
        <div class="form-row">
          <div class="form-group col-md-6" id="boxDebit"><label>Akun Debit</label>
            <select name="debit_account_id" id="mDebit" class="form-control"><option value="">— pilih —</option>
              @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select>
            <small class="text-muted" id="noteDebit"></small></div>
          <div class="form-group col-md-6" id="boxCredit"><label>Akun Kredit</label>
            <select name="credit_account_id" id="mCredit" class="form-control"><option value="">— pilih —</option>
              @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select>
            <small class="text-muted" id="noteCredit"></small></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label>Berlaku Mulai <span class="text-danger">*</span></label><input type="date" name="effective_from" id="mFrom" class="form-control" required></div>
          <div class="form-group col-md-4"><label>Sampai</label><input type="date" name="effective_to" id="mTo" class="form-control"></div>
          <div class="form-group col-md-4"><label>Status</label><select name="status" id="mStatus" class="form-control"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Nonaktif</option></select></div>
        </div>
        <div class="form-group mb-0"><label>Keterangan</label><input name="description" id="mDesc" class="form-control" maxlength="255"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div></div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('formMap');
    if (!form) return;
    var $ = window.jQuery;
    var catalog = @json($catalog);
    var storeUrl = form.getAttribute('action');
    var updateUrl = @json(route('accounting.mapping.update', ['id' => '__ID__']));
    var el = function (id) { return document.getElementById(id); };

    function fillTypes(module, selected) {
        var sel = el('mType'); sel.innerHTML = '';
        Object.keys(catalog[module] || {}).forEach(function (k) {
            var o = document.createElement('option'); o.value = k; o.textContent = catalog[module][k][0]; sel.appendChild(o);
        });
        if (selected) sel.value = selected;
        applySides();
    }
    function applySides() {
        var def = (catalog[el('mModule').value] || {})[el('mType').value];
        var dBank = def && def[1], cBank = def && def[2];
        el('mDebit').disabled = !!dBank; el('mCredit').disabled = !!cBank;
        el('noteDebit').textContent = dBank ? 'Otomatis: rekening bank transaksi.' : '';
        el('noteCredit').textContent = cBank ? 'Otomatis: rekening bank transaksi.' : '';
    }
    el('mModule').addEventListener('change', function () { fillTypes(this.value); });
    el('mType').addEventListener('change', applySides);

    el('btnAddMap').addEventListener('click', function () {
        form.reset(); form.action = storeUrl; el('mMethod').value = 'POST'; el('mTitle').textContent = 'Mapping Baru';
        fillTypes(el('mModule').value); $('#modalMap').modal('show');
    });
    document.querySelectorAll('.js-edit-map').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = b.dataset;
            form.reset(); form.action = updateUrl.replace('__ID__', d.id); el('mMethod').value = 'PUT'; el('mTitle').textContent = 'Edit Mapping';
            el('mModule').value = d.module; fillTypes(d.module, d.type);
            el('mKey').value = d.key || ''; el('mDebit').value = d.debit || ''; el('mCredit').value = d.credit || '';
            el('mFrom').value = d.from; el('mTo').value = d.to || ''; el('mStatus').value = d.status; el('mDesc').value = d.desc || '';
            $('#modalMap').modal('show');
        });
    });
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
