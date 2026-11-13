@extends('layouts.app')

@section('title', 'Letter Management')

@section('content')
@php
    $statuses  = \App\Models\Letter::STATUSES;
    $isManager = auth()->user()->hasRole(['superadmin', 'hr']);
    $v = null;
@endphp
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Daftar Surat</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">HRIS</li>
                <li class="breadcrumb-item active">Daftar Surat</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Letter Management" title="Daftar Surat" description="Surat internal dan eksternal beserta status workflow-nya. Persetujuan mengikuti struktur team.">
            <x-slot:actions>
                @if ($isManager)<x-tw.button type="button" variant="secondary" data-toggle="modal" data-target="#modalKategori">Kategori</x-tw.button>@endif
                <x-tw.button type="button" data-toggle="modal" data-target="#modalBuat"><i class="fas fa-plus" aria-hidden="true"></i> Buat Surat</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        @if (session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif

        @php
            $steps = collect($statuses)->map(fn ($st, $k) => [$st[0], (int) ($statusCounts[$k] ?? 0), $st[1], $k])->values()->all();
            $groups = [
                ['Fase 1: Pengajuan',   ['draft', 'submitted', 'rejected']],
                ['Fase 2: Persetujuan', ['approved']],
                ['Fase 3: Penyelesaian', ['sent', 'complete', 'cancelled']],
            ];
        @endphp
        <x-workflow-stepper :steps="$steps" :compact="true" :groups="$groups" table="tblLetter" field="status" />

        @include('partials.list-toolbar', [
            'prefix' => 'lt',
            'filters' => [['id' => 'Type', 'label' => 'Tipe'], ['id' => 'Category', 'label' => 'Kategori'], ['id' => 'Creator', 'label' => 'Pembuat']],
            'placeholder' => 'No surat / referensi / penerima',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblLetter" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col" class="text-nowrap">No. Surat</th><th scope="col">Kategori</th><th scope="col">Tipe</th><th scope="col">Pembuat</th><th scope="col">Penerima</th><th scope="col">Status</th><th scope="col" class="text-right"><span class="sr-only">Aksi</span></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($letters as $l)
                        <tr data-status="{{ $l->status }}" data-type="{{ $l->type === 'internal' ? 'Internal' : 'External' }}" data-category="{{ $l->category->code }} - {{ $l->category->name }}" data-creator="{{ $l->creator->name ?? '-' }}">
                            <td class="text-nowrap" data-order="{{ $l->updated_at->format('Y-m-d H:i:s') }}">
                                <a href="{{ route('letter.show', $l) }}" class="font-weight-bold">{{ $l->letter_no }}</a>
                                @if ($l->reference_no)<div class="small text-muted">ref: {{ $l->reference_no }}</div>@endif
                            </td>
                            <td>{{ $l->category->code }}<div class="small text-muted">{{ $l->category->name }}</div></td>
                            <td><span class="badge badge-{{ $l->type === 'internal' ? 'info' : 'primary' }}">{{ $l->type === 'internal' ? 'Internal' : 'External' }}</span></td>
                            <td>{{ $l->creator->name ?? '-' }}</td>
                            <td>{{ $l->recipientName() }}</td>
                            <td><span class="badge badge-{{ $statuses[$l->status][1] }}">{{ $statuses[$l->status][0] }}</span></td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('letter.show', $l) }}" class="btn btn-sm btn-outline-secondary" title="Lihat"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('letter.print', $l) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak"><i class="fas fa-print"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="Belum ada surat" message="Surat yang dibuat akan tampil di sini." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

{{-- Buat surat --}}
<div class="modal fade" id="modalBuat" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" action="{{ route('letter.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Buat Surat Baru</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                @include('letter._info-fields')
                <div class="small text-muted">Nomor surat dibuat otomatis. Isi surat diisi pada tab <b>Isi Surat</b> setelah draft dibuat.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan sebagai Draft</button>
            </div>
        </form>
    </div>
</div>

@if ($isManager)
<div class="modal fade" id="modalKategori" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Kategori Surat</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <table class="table table-sm">
                @foreach ($categories as $c)
                    <tr>
                        <td class="font-weight-bold">{{ $c->code }}</td><td>{{ $c->name }}</td>
                        <td class="text-right">
                            <form method="post" action="{{ route('letter.categories.destroy', $c) }}" onsubmit="return confirm('Hapus kategori {{ $c->code }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
            <form method="post" action="{{ route('letter.categories.store') }}" class="form-row">
                @csrf
                <div class="col-4"><input name="code" class="form-control form-control-sm" placeholder="Kode" maxlength="10" required></div>
                <div class="col-6"><input name="name" class="form-control form-control-sm" placeholder="Nama kategori" required></div>
                <div class="col-2"><button class="btn btn-sm btn-primary btn-block">+</button></div>
            </form>
            <div class="small text-muted mt-2">Template isian khusus: SPH, PO, BAST, SK, SPK. Kode lain memakai template umum.</div>
        </div>
    </div></div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    function sync(form) {
        var t = form.querySelector('.js-letter-type').value;
        var c = form.querySelector('.js-wrap-customer'), e = form.querySelector('.js-wrap-employee');
        if (c) c.style.display = t === 'external' ? '' : 'none';
        if (e) e.style.display = t === 'internal' ? '' : 'none';
    }
    document.querySelectorAll('.js-letter-type').forEach(function (sel) {
        var f = sel.closest('form');
        sel.addEventListener('change', function () { sync(f); });
        sync(f);
    });
    @if ($errors->any()) $('#modalBuat').modal('show'); @endif
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

@push('scripts')
<script src="@aset('js/tesys-list-table.js')"></script>
<script>
$(function () {
    TesysListTable.init({
        table: '#tblLetter', order: [[0, 'desc']], search: '#ltSearch', clear: '#ltClear',
        filters: [
            { target: '#ltFilterType', key: 'type', label: 'Semua Tipe', search: false },
            { target: '#ltFilterCategory', key: 'category', label: 'Semua Kategori', search: false },
            { target: '#ltFilterCreator', key: 'creator', label: 'Semua Pembuat' }
        ]
    });
});
</script>
@endpush
