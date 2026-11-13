@extends('layouts.app')

@section('title', 'Accounting Period')

@php
    $badge = ['OPEN' => 'badge-success', 'CLOSED' => 'badge-warning', 'LOCKED' => 'badge-secondary'];
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        <div class="tw:sr-only">
            <h1>Accounting Period</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">Accounting Period</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="Accounting Period"
            description="Jurnal hanya bisa diposting ke periode Open (backdate diizinkan selama periodenya masih Open). Closed dan Locked menolak posting.">
            <x-slot:actions>
                @if($canManage)<x-tw.button id="btnAddPeriod"><i class="fas fa-plus" aria-hidden="true"></i> Periode Baru</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr><th>Periode</th><th>Mulai</th><th>Selesai</th><th class="text-right">Jurnal</th><th>Status</th><th>Ditutup Oleh</th>@if($canManage)<th class="text-center">Aksi</th>@endif</tr></thead>
                    <tbody>
                    @forelse($periods as $p)
                        <tr>
                            <td><strong>{{ $p->name }}</strong>@if($p->notes)<br><small class="text-muted">{{ $p->notes }}</small>@endif</td>
                            <td class="text-nowrap">{{ $p->start_date->format('d M Y') }}</td>
                            <td class="text-nowrap">{{ $p->end_date->format('d M Y') }}</td>
                            <td class="text-right">{{ $p->journals_count }}</td>
                            <td><span class="badge {{ $badge[$p->status] ?? 'badge-secondary' }}">{{ $statuses[$p->status] ?? $p->status }}</span></td>
                            <td>@if($p->closedBy){{ $p->closedBy->username }}<br><small class="text-muted">{{ $p->closed_at?->format('d M Y H:i') }}</small>@else - @endif</td>
                            @if($canManage)
                            <td class="text-center text-nowrap">
                                @if($p->status !== 'LOCKED')
                                <form action="{{ route('accounting.period.close', $p->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('{{ $p->status === 'OPEN' ? 'Tutup' : 'Kunci' }} periode {{ $p->name }}?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning">{{ $p->status === 'OPEN' ? 'Tutup' : 'Kunci' }}</button>
                                </form>
                                @endif
                                @if($canReopen && $p->status !== 'OPEN')
                                <button type="button" class="btn btn-sm btn-outline-secondary js-reopen" data-id="{{ $p->id }}" data-name="{{ $p->name }}">Buka Kembali</button>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="Belum ada periode" message="Buat periode (mis. per bulan) agar jurnal bisa diposting." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

@if($canManage)
<div class="modal fade" id="modalPeriod" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" action="{{ route('accounting.period.store') }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Periode Baru</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>Nama <span class="text-danger">*</span></label><input name="name" class="form-control" maxlength="60" placeholder="mis. Januari 2027" required></div>
        <div class="form-row">
          <div class="form-group col-6"><label>Mulai <span class="text-danger">*</span></label><input type="date" name="start_date" class="form-control" required></div>
          <div class="form-group col-6"><label>Selesai <span class="text-danger">*</span></label><input type="date" name="end_date" class="form-control" required></div>
        </div>
        <div class="form-group"><label>Catatan</label><input name="notes" class="form-control" maxlength="500"></div>
        <div class="custom-control custom-checkbox mb-0">
          <input type="checkbox" class="custom-control-input" id="confirmCut" name="confirm_cutover" value="1">
          <label class="custom-control-label small" for="confirmCut">Periode ini lebih awal dari periode pertama — saya paham tanggal mulai pembukuan akan bergeser.</label>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div></div>
</div>
@endif

@if($canReopen)
<div class="modal fade" id="modalReopen" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" id="formReopen" action="">@csrf
      <div class="modal-header"><h5 class="modal-title" id="reopenTitle">Buka Kembali Periode</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button></div>
      <div class="modal-body">
        <p class="text-muted">Membuka kembali periode memungkinkan posting lagi. Tindakan ini tercatat di audit trail.</p>
        <div class="form-group mb-0"><label>Alasan <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="2" minlength="5" maxlength="500" required></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Batal</button><button class="btn btn-danger">Buka Kembali</button></div>
    </form>
  </div></div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var $ = window.jQuery;
    var add = document.getElementById('btnAddPeriod');
    if (add) add.addEventListener('click', function () { $('#modalPeriod').modal('show'); });
    var url = @json(route('accounting.period.reopen', ['id' => '__ID__']));
    document.querySelectorAll('.js-reopen').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('formReopen').action = url.replace('__ID__', b.dataset.id);
            document.getElementById('reopenTitle').textContent = 'Buka Kembali ' + b.dataset.name;
            $('#modalReopen').modal('show');
        });
    });
})();
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
