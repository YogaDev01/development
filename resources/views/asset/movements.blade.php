@extends('layouts.app')

@section('title', 'Serah Terima & Transfer Aset')

@php
    $tabs = ['handover' => 'Serah Terima', 'transfer' => 'Transfer', 'checkout' => 'Check-out / Check-in', 'disposal' => 'Disposal'];
    $cond = \App\Models\Asset::CONDITIONS;
    $today = now()->toDateString();
@endphp

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title' => 'Serah Terima & Transfer Aset',
        'desc'  => 'Serah terima ke karyawan, pengembalian, transfer lokasi, peminjaman sementara, dan disposal aset.',
    ])

    <ul class="nav ast-tabs">
        @foreach($tabs as $k => $l)
            <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('asset.movements.index', ['tab' => $k]) }}">{{ $l }}</a></li>
        @endforeach
    </ul>

    <div class="ast-panel">
        <form method="GET" class="ast-toolbar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nomor / aset…" style="min-width:220px;">
            <select name="status" class="form-control form-control-sm">
                <option value="">Semua status</option>
                @foreach(['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'CHECKED_OUT', 'RETURNED'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ \App\Support\WorkflowStatus::label($s, 'asset') }}</option>@endforeach
            </select>
            <button class="btn btn-secondary btn-sm">Filter</button>
            <div class="ml-auto d-flex" style="gap:6px;">
                @if($tab === 'handover')
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#mvHandover"><i class="fas fa-user-check mr-1"></i>Serah Terima</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#mvReturn"><i class="fas fa-undo mr-1"></i>Pengembalian</button>
                @elseif($tab === 'transfer')
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#mvTransfer"><i class="fas fa-exchange-alt mr-1"></i>Ajukan Transfer</button>
                @elseif($tab === 'checkout')
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#mvCheckout"><i class="fas fa-sign-out-alt mr-1"></i>Check-out</button>
                @else
                    <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#mvDisposal"><i class="fas fa-trash-alt mr-1"></i>Ajukan Disposal</button>
                @endif
            </div>
        </form>

        @if($capped)<div class="alert alert-info m-2 py-1 small">Menampilkan 1.000 dari {{ $total }} data terbaru.</div>@endif

        <div class="table-responsive"><table class="ast-table">
            <thead><tr>
                <th>Nomor</th><th>Aset</th><th>Tanggal</th>
                @if($tab === 'handover')<th>Jenis</th><th>Karyawan</th><th>Lokasi</th>
                @elseif($tab === 'transfer')<th>Dari</th><th>Ke</th><th>Alasan</th>
                @elseif($tab === 'checkout')<th>Peminjam</th><th>Rencana Kembali</th><th>Dikembalikan</th>
                @else<th>Metode</th><th class="num">Nilai</th><th>Alasan</th>@endif
                <th>Status</th><th class="text-right">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $m)
                <tr>
                    <td class="ast-code">{{ $m->number }}<div class="ast-sub">{{ $m->requester?->name }}</div></td>
                    <td>@if($m->asset)<a href="{{ route('asset.show', $m->asset_id) }}" class="ast-code">{{ $m->asset->code }}</a><div class="ast-sub">{{ $m->asset->name }}</div>@endif</td>
                    <td>{{ $m->movement_date->format('d M Y') }}</td>
                    @if($tab === 'handover')
                        <td>{{ \App\Models\AssetMovement::KINDS[$m->kind] }}</td>
                        <td>{{ $m->kind === 'HANDOVER' ? $m->toEmployee?->name : $m->fromEmployee?->name }}</td>
                        <td>{{ $m->toLocation?->name ?? $m->fromLocation?->name ?? '-' }}</td>
                    @elseif($tab === 'transfer')
                        <td>{{ $m->fromLocation?->name ?? '-' }}<div class="ast-sub">{{ $m->fromEmployee?->name }}</div></td>
                        <td>{{ $m->toLocation?->name ?? '-' }}<div class="ast-sub">{{ $m->toEmployee?->name }}</div></td>
                        <td>{{ $m->reason }}@if($m->reject_reason)<div class="ast-sub text-danger">Ditolak: {{ $m->reject_reason }}</div>@endif</td>
                    @elseif($tab === 'checkout')
                        <td>{{ $m->toEmployee?->name }}<div class="ast-sub">{{ $m->notes }}</div></td>
                        <td>{{ $m->expected_return_date?->format('d M Y') }}</td>
                        <td>{{ $m->returned_date?->format('d M Y') ?? '-' }}@if($m->condition_after)<div class="ast-sub">{{ $cond[$m->condition_after] ?? '' }}</div>@endif</td>
                    @else
                        <td>{{ \App\Models\AssetMovement::DISPOSAL_METHODS[$m->disposal_method] ?? '-' }}</td>
                        <td class="num">{{ number_format((int) $m->disposal_value, 0, ',', '.') }}</td>
                        <td>{{ $m->reason }}@if($m->reject_reason)<div class="ast-sub text-danger">Ditolak: {{ $m->reject_reason }}</div>@endif</td>
                    @endif
                    <td><x-status :status="$m->isOverdue() ? 'OVERDUE' : $m->status" context="asset" />@if($m->approver)<div class="ast-sub">{{ $m->approver->name }}</div>@endif</td>
                    <td class="text-right text-nowrap">
                        @if($m->status === 'PENDING' && $canManage)
                            <form method="POST" action="{{ route('asset.movements.approve', $m) }}" class="d-inline" onsubmit="return confirm('Setujui {{ $m->number }}?')">@csrf<button class="btn btn-success btn-sm">Setujui</button></form>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-reject-url="{{ route('asset.movements.reject', $m) }}" data-reject-title="Tolak {{ $m->number }}">Tolak</button>
                        @endif
                        @if($m->kind === 'CHECKOUT' && $m->status === 'CHECKED_OUT')
                            <button type="button" class="btn btn-primary btn-sm js-checkin" data-url="{{ route('asset.movements.checkin', $m) }}" data-no="{{ $m->number }}" data-min="{{ $m->movement_date->toDateString() }}">Check-in</button>
                        @endif
                        @if($m->kind !== 'DISPOSAL')
                            <a href="{{ route('asset.movements.document', $m) }}" target="_blank" class="btn btn-link btn-sm" title="Cetak dokumen"><i class="fas fa-print"></i></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty title="Belum ada data {{ strtolower($tabs[$tab]) }}" message="Gunakan tombol di kanan atas untuk mencatat transaksi baru." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div></div>

{{-- ============ MODAL ============ --}}
@foreach([
    ['mvHandover', 'Serah Terima Aset', 'handover', ['AVAILABLE', 'IN_USE']],
    ['mvReturn', 'Pengembalian Aset', 'return', ['ASSIGNED']],
    ['mvTransfer', 'Ajukan Transfer Aset', 'transfer', ['AVAILABLE', 'ASSIGNED', 'IN_USE']],
    ['mvCheckout', 'Check-out Aset', 'checkout', ['AVAILABLE', 'IN_USE']],
    ['mvDisposal', 'Ajukan Disposal Aset', 'disposal', ['AVAILABLE', 'ASSIGNED', 'IN_USE']],
] as [$id, $title, $kind, $only])
<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('asset.movements.store', $kind) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ $title }}</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-8"><label class="small font-weight-bold">Aset <span class="text-danger">*</span></label>
                    @include('asset._asset_select', ['assets' => $assets, 'id' => $id . 'Asset', 'only' => $only])
                    <div class="ast-sub">Hanya aset berstatus {{ implode(' / ', $only) }}.</div></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="movement_date" class="form-control form-control-sm" value="{{ old('movement_date', $today) }}" max="{{ $today }}" required></div>

                @if(in_array($kind, ['handover', 'checkout', 'transfer'], true))
                    <div class="form-group col-md-6"><label class="small font-weight-bold">{{ $kind === 'transfer' ? 'Pemegang Baru (opsional)' : ($kind === 'checkout' ? 'Peminjam' : 'Diserahkan kepada') }} @if($kind !== 'transfer')<span class="text-danger">*</span>@endif</label>
                        <select name="to_employee_id" class="form-control form-control-sm" {{ $kind !== 'transfer' ? 'required' : '' }}>
                            <option value="">—</option>
                            @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}{{ $e->employee_number ? ' (' . $e->employee_number . ')' : '' }}</option>@endforeach
                        </select></div>
                @endif
                @if(in_array($kind, ['handover', 'return', 'transfer'], true))
                    <div class="form-group col-md-6"><label class="small font-weight-bold">{{ $kind === 'handover' ? 'Lokasi Pemakaian (opsional)' : ($kind === 'return' ? 'Dikembalikan ke Lokasi' : 'Lokasi Tujuan') }} @if($kind !== 'handover')<span class="text-danger">*</span>@endif</label>
                        <select name="to_location_id" class="form-control form-control-sm" {{ $kind !== 'handover' ? 'required' : '' }}>
                            <option value="">—</option>
                            @foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                        </select></div>
                @endif
                @if($kind === 'checkout')
                    <div class="form-group col-md-6"><label class="small font-weight-bold">Rencana Kembali <span class="text-danger">*</span></label>
                        <input type="date" name="expected_return_date" class="form-control form-control-sm" min="{{ $today }}" required></div>
                @endif
                @if(in_array($kind, ['handover', 'return'], true))
                    <div class="form-group col-md-6"><label class="small font-weight-bold">Kondisi saat {{ $kind === 'return' ? 'dikembalikan' : 'diserahkan' }}</label>
                        <select name="condition" class="form-control form-control-sm"><option value="">(tetap)</option>
                            @foreach($cond as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                @endif
                @if($kind === 'disposal')
                    <div class="form-group col-md-6"><label class="small font-weight-bold">Metode <span class="text-danger">*</span></label>
                        <select name="disposal_method" class="form-control form-control-sm" required>
                            @foreach(\App\Models\AssetMovement::DISPOSAL_METHODS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group col-md-6"><label class="small font-weight-bold">Nilai Jual / Pelepasan (Rp)</label>
                        <input type="number" name="disposal_value" class="form-control form-control-sm" min="0" step="1" placeholder="Wajib bila Dijual">
                        <div class="ast-sub">Hanya dicatat; tidak membuat Cash Receipt.</div></div>
                @endif
                @if(in_array($kind, ['transfer', 'disposal'], true))
                    <div class="form-group col-12"><label class="small font-weight-bold">Alasan <span class="text-danger">*</span></label>
                        <input name="reason" class="form-control form-control-sm" maxlength="500" required></div>
                @endif
                <div class="form-group col-12 mb-0"><label class="small font-weight-bold">Catatan</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
                @if(in_array($kind, ['transfer', 'disposal'], true))
                    <div class="col-12 ast-sub mt-2">Pengajuan ini menunggu persetujuan Asset Admin sebelum aset berpindah.</div>
                @endif
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button class="btn {{ $kind === 'disposal' ? 'btn-danger' : 'btn-primary' }} btn-sm">Simpan</button></div>
        </form>
    </div>
</div>
@endforeach

<div class="modal fade" id="mvCheckin" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="mvCheckinForm" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Check-in <span id="mvCheckinNo"></span></h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="small font-weight-bold">Tanggal Kembali <span class="text-danger">*</span></label>
                    <input type="date" name="returned_date" id="mvCheckinDate" class="form-control form-control-sm" value="{{ $today }}" max="{{ $today }}" required></div>
                <div class="form-group"><label class="small font-weight-bold">Kondisi <span class="text-danger">*</span></label>
                    <select name="condition" class="form-control form-control-sm" required>@foreach($cond as $k => $v)<option value="{{ $k }}" @selected($k === 'GOOD')>{{ $v }}</option>@endforeach</select></div>
                <div class="form-group mb-0"><label class="small font-weight-bold">Catatan</label><textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Check-in</button></div>
        </form>
    </div>
</div>

@include('asset._scripts')
@endsection

@push('scripts')
<script>
$(document).on('click', '.js-checkin', function () {
    $('#mvCheckinForm').attr('action', $(this).data('url'));
    $('#mvCheckinNo').text($(this).data('no'));
    $('#mvCheckinDate').attr('min', $(this).data('min'));
    $('#mvCheckin').modal('show');
});
</script>
@endpush
