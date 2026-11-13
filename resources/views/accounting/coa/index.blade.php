@extends('layouts.app')

@section('title', 'Chart of Accounts')

@php
    $typeBadge = ['ASSET' => 'badge-primary', 'LIABILITY' => 'badge-warning', 'EQUITY' => 'badge-info', 'REVENUE' => 'badge-success', 'EXPENSE' => 'badge-danger'];
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        <div class="tw:sr-only">
            <h1>Chart of Accounts</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">Chart of Accounts</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="Chart of Accounts"
            description="Daftar akun yang dikelola akuntan. Akun yang sudah dipakai jurnal tidak bisa dihapus, dan tipenya terkunci.">
            <x-slot:actions>
                @if($canManage)<x-tw.button id="btnAddAcc"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Akun</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        <x-tw.card padding="sm">
            <div class="d-flex flex-wrap mb-3" style="gap:8px;">
                <input type="search" id="accSearch" class="form-control form-control-sm" style="max-width:260px" placeholder="Cari kode / nama akun">
                <select id="accType" class="form-control form-control-sm" style="max-width:180px">
                    <option value="">Semua tipe</option>
                    @foreach($types as $k => $t)<option value="{{ $k }}">{{ $t[0] }}</option>@endforeach
                </select>
                <select id="accStatus" class="form-control form-control-sm" style="max-width:150px">
                    <option value="">Semua status</option>
                    <option value="ACTIVE">Aktif</option>
                    <option value="INACTIVE">Nonaktif</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl" id="accTable">
                    <thead class="bg-light"><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Kategori</th><th>Saldo Normal</th><th>Rekening Bank</th><th>Status</th>@if($canManage)<th class="text-center">Aksi</th>@endif</tr></thead>
                    <tbody>
                    @forelse($accounts as $a)
                        <tr data-type="{{ $a->account_type }}" data-status="{{ $a->status }}" data-text="{{ strtolower($a->code . ' ' . $a->name) }}">
                            <td class="text-nowrap"><strong>{{ $a->code }}</strong></td>
                            <td style="padding-left:{{ 8 + $a->depth * 18 }}px">
                                {{ $a->name }}
                                @unless($a->is_postable)<span class="badge badge-light border ml-1" title="Akun induk — tidak bisa dipakai di jurnal">Induk</span>@endunless
                            </td>
                            <td><span class="badge {{ $typeBadge[$a->account_type] ?? 'badge-secondary' }}">{{ $types[$a->account_type][0] ?? $a->account_type }}</span></td>
                            <td>{{ $a->category ?: '-' }}</td>
                            <td>{{ $a->normal_balance === 'DEBIT' ? 'Debit' : 'Kredit' }}</td>
                            <td>{{ $a->sourceFund->name ?? '-' }}</td>
                            <td><span class="badge {{ $a->status === 'ACTIVE' ? 'badge-success' : 'badge-secondary' }}">{{ $a->status === 'ACTIVE' ? 'Aktif' : 'Nonaktif' }}</span></td>
                            @if($canManage)
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary js-edit-acc"
                                    data-id="{{ $a->id }}" data-code="{{ $a->code }}" data-name="{{ $a->name }}"
                                    data-type="{{ $a->account_type }}" data-category="{{ $a->category }}"
                                    data-parent="{{ $a->parent_id }}" data-normal="{{ $a->normal_balance }}"
                                    data-description="{{ $a->description }}" data-postable="{{ $a->is_postable ? 1 : 0 }}"
                                    data-fund="{{ $a->source_fund_id }}" data-status="{{ $a->status }}"
                                    data-section="{{ $a->statement_section }}" data-activity="{{ $a->cash_flow_activity }}"
                                    data-used="{{ $a->journal_lines_count > 0 ? 1 : 0 }}" title="Edit"><i class="fas fa-pen"></i></button>
                                @if($a->journal_lines_count == 0 && $a->children_count == 0)
                                <form action="{{ route('accounting.coa.destroy', $a->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus akun {{ $a->code }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada akun" message="Tambahkan akun pertama untuk memulai chart of accounts." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

@if($canManage)
<div class="modal fade" id="modalAcc" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="formAcc" method="POST" action="{{ route('accounting.coa.store') }}">
        @csrf
        <input type="hidden" name="_method" id="accMethod" value="POST">
        <div class="modal-header"><h5 class="modal-title" id="accTitle">Tambah Akun</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-md-4"><label>Kode <span class="text-danger">*</span></label><input name="code" id="fCode" class="form-control" maxlength="20" required></div>
            <div class="form-group col-md-8"><label>Nama <span class="text-danger">*</span></label><input name="name" id="fName" class="form-control" required></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4"><label>Tipe <span class="text-danger">*</span></label>
              <select name="account_type" id="fType" class="form-control" required>
                @foreach($types as $k => $t)<option value="{{ $k }}" data-normal="{{ $t[1] }}">{{ $t[0] }}</option>@endforeach
              </select><small class="text-muted" id="fTypeNote"></small></div>
            <div class="form-group col-md-4"><label>Saldo Normal</label>
              <select name="normal_balance" id="fNormal" class="form-control"><option value="DEBIT">Debit</option><option value="CREDIT">Kredit</option></select></div>
            <div class="form-group col-md-4"><label>Kategori</label><input name="category" id="fCategory" class="form-control" maxlength="100" placeholder="mis. Current Assets"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Akun Induk</label>
              <select name="parent_id" id="fParent" class="form-control"><option value="">— Tanpa induk —</option>
                @foreach($accounts as $p)<option value="{{ $p->id }}" data-type="{{ $p->account_type }}">{{ $p->code }} — {{ $p->name }}</option>@endforeach
              </select></div>
            <div class="form-group col-md-6"><label>Tautkan ke Rekening Cash &amp; Bank</label>
              <select name="source_fund_id" id="fFund" class="form-control"><option value="">— Tidak —</option>
                @foreach($funds as $f)<option value="{{ $f->id }}" @if(in_array($f->id, $usedFunds)) data-taken="1" @endif>{{ $f->name }}</option>@endforeach
              </select><small class="text-muted">Dipakai Account Mapping untuk sisi bank. Satu rekening = satu akun.</small></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Penempatan di Laporan</label>
              <select name="statement_section" id="fSection" class="form-control"><option value="">— Bawaan menurut tipe —</option>
                @foreach($sections as $t => $list)@foreach($list as $code => $label)<option value="{{ $code }}" data-type="{{ $t }}">{{ $label }}</option>@endforeach @endforeach
              </select><small class="text-muted">Menentukan baris di Laba Rugi, Neraca, dan Perubahan Ekuitas.</small></div>
            <div class="form-group col-md-6"><label>Aktivitas Arus Kas</label>
              <select name="cash_flow_activity" id="fActivity" class="form-control"><option value="">— Bawaan —</option>
                @foreach($activities as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
              </select><small class="text-muted">Dipakai bila akun ini lawan transaksi kas.</small></div>
          </div>
          <div class="form-group"><label>Deskripsi</label><textarea name="description" id="fDesc" class="form-control" rows="2" maxlength="1000"></textarea></div>
          <div class="form-row">
            <div class="form-group col-md-6"><div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" name="is_postable" id="fPostable" value="1" checked>
              <label class="custom-control-label" for="fPostable">Bisa dipakai di jurnal (bukan akun induk)</label></div></div>
            <div class="form-group col-md-6"><label class="mb-0">Status</label>
              <select name="status" id="fStatus" class="form-control"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Nonaktif</option></select></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var rows = document.querySelectorAll('#accTable tbody tr[data-type]');
    function filter() {
        var q = document.getElementById('accSearch').value.toLowerCase();
        var t = document.getElementById('accType').value, s = document.getElementById('accStatus').value;
        rows.forEach(function (r) {
            r.style.display = (!q || r.dataset.text.indexOf(q) > -1) && (!t || r.dataset.type === t) && (!s || r.dataset.status === s) ? '' : 'none';
        });
    }
    ['accSearch', 'accType', 'accStatus'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', filter);
    });

    var form = document.getElementById('formAcc');
    if (!form) return;
    var storeUrl = form.getAttribute('action');
    var updateUrl = @json(route('accounting.coa.update', ['id' => '__ID__']));
    var $ = window.jQuery;

    function reset(editing) {
        document.getElementById('fType').disabled = false;
        document.getElementById('fNormal').disabled = false;
        document.getElementById('fTypeNote').textContent = '';
        if (!editing) { form.reset(); document.getElementById('fPostable').checked = true; }
    }
    // Opsi penempatan hanya yang sesuai tipe akun terpilih.
    function filterSections() {
        var t = document.getElementById('fType').value, sel = document.getElementById('fSection');
        Array.prototype.forEach.call(sel.options, function (o) {
            var show = !o.dataset.type || o.dataset.type === t;
            o.hidden = !show; o.disabled = !show;
        });
        if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) sel.value = '';
    }
    document.getElementById('fType').addEventListener('change', function () {
        var n = this.options[this.selectedIndex].dataset.normal;
        if (n) document.getElementById('fNormal').value = n;
        filterSections();
    });

    document.getElementById('btnAddAcc').addEventListener('click', function () {
        reset(false); filterSections();
        form.action = storeUrl; document.getElementById('accMethod').value = 'POST';
        document.getElementById('accTitle').textContent = 'Tambah Akun';
        $('#modalAcc').modal('show');
    });

    document.querySelectorAll('.js-edit-acc').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = b.dataset;
            reset(true);
            form.action = updateUrl.replace('__ID__', d.id); document.getElementById('accMethod').value = 'PUT';
            document.getElementById('accTitle').textContent = 'Edit Akun ' + d.code;
            document.getElementById('fCode').value = d.code; document.getElementById('fName').value = d.name;
            document.getElementById('fType').value = d.type; document.getElementById('fNormal').value = d.normal;
            document.getElementById('fCategory').value = d.category || ''; document.getElementById('fParent').value = d.parent || '';
            document.getElementById('fFund').value = d.fund || ''; document.getElementById('fDesc').value = d.description || '';
            document.getElementById('fPostable').checked = d.postable === '1'; document.getElementById('fStatus').value = d.status;
            filterSections();
            document.getElementById('fSection').value = d.section || ''; document.getElementById('fActivity').value = d.activity || '';
            if (d.used === '1') {
                // Tipe & saldo normal terkunci; select disabled tidak terkirim, jadi kirim lewat hidden.
                document.getElementById('fTypeNote').textContent = 'Terkunci: akun sudah dipakai jurnal.';
                ['fType', 'fNormal'].forEach(function (id) {
                    var el = document.getElementById(id), h = document.createElement('input');
                    h.type = 'hidden'; h.name = el.name; h.value = el.value; h.className = 'js-lock-hidden';
                    el.disabled = true; el.parentNode.appendChild(h);
                });
            }
            $('#modalAcc').modal('show');
        });
    });
    $('#modalAcc').on('hidden.bs.modal', function () {
        document.querySelectorAll('.js-lock-hidden').forEach(function (h) { h.remove(); });
    });
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
