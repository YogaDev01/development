@extends('layouts.app')

@section('title', 'Maintenance Aset')

@php
    $types = \App\Models\AssetMaintenance::TYPES;
    $today = now()->toDateString();
    $rp = fn ($v) => number_format((int) $v, 0, ',', '.');
@endphp

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title' => 'Maintenance Aset',
        'desc'  => 'Pekerjaan perbaikan/perawatan dan jadwal maintenance berkala. Biaya hanya dicatat — pembayaran tetap lewat Cost Request.',
    ])

    <ul class="nav ast-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab === 'records' ? 'active' : '' }}" href="{{ route('asset.maintenance.index') }}">Pekerjaan Maintenance</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'schedule' ? 'active' : '' }}" href="{{ route('asset.maintenance.index', ['tab' => 'schedule']) }}">Jadwal Maintenance</a></li>
    </ul>

    @if($tab === 'records')
    <div class="ast-panel">
        <form method="GET" class="ast-toolbar">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nomor / aset / vendor…" style="min-width:220px;">
            <select name="status" class="form-control form-control-sm"><option value="">Semua status</option>
                @foreach(\App\Models\AssetMaintenance::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ \App\Support\WorkflowStatus::label($s, 'asset') }}</option>@endforeach</select>
            <button class="btn btn-secondary btn-sm">Filter</button>
            <button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#mtNew"><i class="fas fa-plus mr-1"></i>Buat Maintenance</button>
        </form>
        @if($capped)<div class="alert alert-info m-2 py-1 small">Menampilkan 1.000 dari {{ $total }} data terbaru.</div>@endif
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Nomor</th><th>Aset</th><th>Jenis</th><th>Masalah / Pekerjaan</th><th>Vendor</th><th>Mulai</th><th>Selesai</th><th class="num">Estimasi</th><th class="num">Aktual</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $m)
                <tr>
                    <td class="ast-code">{{ $m->number }}</td>
                    <td>@if($m->asset)<a href="{{ route('asset.show', $m->asset_id) }}" class="ast-code">{{ $m->asset->code }}</a><div class="ast-sub">{{ $m->asset->name }}</div>@endif</td>
                    <td>{{ $types[$m->maintenance_type] ?? $m->maintenance_type }}</td>
                    <td style="white-space:normal;min-width:200px;">{{ $m->problem }}@if($m->result_notes)<div class="ast-sub">{{ $m->result_notes }}</div>@endif</td>
                    <td>{{ $m->vendor ?: '-' }}</td>
                    <td>{{ $m->start_date->format('d M Y') }}</td>
                    <td>{{ $m->end_date?->format('d M Y') ?? '-' }}</td>
                    <td class="num">{{ $rp($m->estimated_cost) }}</td>
                    <td class="num">{{ $rp($m->actual_cost) }}</td>
                    <td><x-status :status="$m->status" context="asset" /></td>
                    <td class="text-right">
                        @if(in_array($m->status, \App\Models\AssetMaintenance::OPEN_STATUSES, true))
                            <button type="button" class="btn btn-primary btn-sm js-mt-update" data-url="{{ route('asset.maintenance.update', $m) }}" data-no="{{ $m->number }}" data-min="{{ $m->start_date->toDateString() }}" data-status="{{ $m->status }}">Update</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11"><x-empty title="Belum ada maintenance" message="Buat maintenance saat aset perlu diperbaiki atau dirawat." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @else
    <div class="ast-panel">
        <div class="ast-toolbar"><span class="ast-sub align-self-center">Status jatuh tempo dihitung dari tanggal hari ini ("Due Soon" = ≤ {{ \App\Models\AssetMaintenanceSchedule::DUE_SOON_DAYS }} hari).</span>
            <button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#scNew"><i class="fas fa-plus mr-1"></i>Tambah Jadwal</button></div>
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Aset</th><th>Jadwal</th><th>Jenis</th><th>Interval</th><th>Terakhir</th><th>Berikutnya</th><th>Vendor</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($schedules as $s)
                <tr>
                    <td>@if($s->asset)<a href="{{ route('asset.show', $s->asset_id) }}" class="ast-code">{{ $s->asset->code }}</a><div class="ast-sub">{{ $s->asset->name }}</div>@endif</td>
                    <td>{{ $s->title }}</td><td>{{ $types[$s->maintenance_type] ?? $s->maintenance_type }}</td>
                    <td>{{ $s->interval_days }} hari</td><td>{{ $s->last_done_date?->format('d M Y') ?? '-' }}</td>
                    <td>{{ $s->next_due_date->format('d M Y') }}</td><td>{{ $s->vendor ?: '-' }}</td>
                    <td><x-status :status="$s->dueState()" context="asset" /></td>
                    <td class="text-right text-nowrap">
                        @if($s->status === 'ACTIVE')
                            <button type="button" class="btn btn-primary btn-sm js-sc-start" data-asset="{{ $s->asset_id }}" data-schedule="{{ $s->id }}" data-type="{{ $s->maintenance_type }}" data-title="{{ $s->title }}" data-vendor="{{ $s->vendor }}">Mulai</button>
                        @endif
                        <button type="button" class="btn btn-secondary btn-sm js-sc-edit" data-url="{{ route('asset.schedule.update', $s) }}" data-json="{{ json_encode($s->only(['title', 'maintenance_type', 'interval_days', 'vendor', 'status']) + ['next_due_date' => $s->next_due_date->toDateString()]) }}">Ubah</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty title="Belum ada jadwal" message="Tambahkan jadwal untuk maintenance berkala (genset, AC, kalibrasi alat ukur, …)." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @endif
</div></div>

{{-- Buat maintenance (juga dipakai tombol "Mulai" dari jadwal) --}}
<div class="modal fade" id="mtNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('asset.maintenance.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="asset_maintenance_schedule_id" id="mtSchedule">
            <div class="modal-header"><h5 class="modal-title">Buat Maintenance</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-8"><label class="small font-weight-bold">Aset <span class="text-danger">*</span></label>
                    @include('asset._asset_select', ['assets' => $assets, 'id' => 'mtAsset', 'only' => ['AVAILABLE', 'ASSIGNED', 'IN_USE']])
                    <div class="ast-sub">Aset berstatus Maintenance selama pekerjaan berjalan.</div></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $today }}" max="{{ $today }}" required></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Jenis <span class="text-danger">*</span></label>
                    <select name="maintenance_type" id="mtType" class="form-control form-control-sm" required>@foreach($types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Vendor</label><input name="vendor" id="mtVendor" class="form-control form-control-sm" maxlength="255"></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Estimasi Biaya (Rp)</label><input type="number" name="estimated_cost" class="form-control form-control-sm" min="0" step="1"></div>
                <div class="form-group col-12 mb-0"><label class="small font-weight-bold">Masalah / Pekerjaan <span class="text-danger">*</span></label>
                    <textarea name="problem" id="mtProblem" class="form-control form-control-sm" rows="2" maxlength="1000" required></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="mtUpdate" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="mtUpdateForm" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Update <span id="mtUpdateNo"></span></h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="small font-weight-bold">Status <span class="text-danger">*</span></label>
                    <select name="status" id="mtUpdateStatus" class="form-control form-control-sm" required>
                        <option value="IN_PROGRESS">In Progress</option><option value="WAITING_PARTS">Waiting Parts</option>
                        <option value="COMPLETED">Completed — selesai</option><option value="CANCELLED">Cancelled — dibatalkan</option>
                    </select></div>
                <div class="js-mt-done">
                    <div class="form-group"><label class="small font-weight-bold">Tanggal Selesai</label><input type="date" name="end_date" id="mtUpdateEnd" class="form-control form-control-sm" value="{{ $today }}" max="{{ $today }}"></div>
                    <div class="form-group"><label class="small font-weight-bold">Biaya Aktual (Rp)</label><input type="number" name="actual_cost" class="form-control form-control-sm" min="0" step="1"></div>
                    <div class="form-group"><label class="small font-weight-bold">Kondisi Setelah Maintenance</label>
                        <select name="condition" class="form-control form-control-sm">@foreach(\App\Models\Asset::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected($k === 'GOOD')>{{ $v }}</option>@endforeach</select></div>
                </div>
                <div class="form-group mb-0"><label class="small font-weight-bold">Catatan Hasil</label><textarea name="result_notes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="scNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" id="scForm" action="{{ route('asset.schedule.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" id="scMethod" value="POST">
            <div class="modal-header"><h5 class="modal-title" id="scTitle">Tambah Jadwal Maintenance</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-12 js-sc-asset"><label class="small font-weight-bold">Aset <span class="text-danger">*</span></label>
                    @include('asset._asset_select', ['assets' => $assets, 'id' => 'scAsset'])</div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Nama Jadwal <span class="text-danger">*</span></label><input name="title" class="form-control form-control-sm" maxlength="255" required placeholder="Service 250 jam"></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Jenis <span class="text-danger">*</span></label>
                    <select name="maintenance_type" class="form-control form-control-sm" required>@foreach($types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Interval (hari) <span class="text-danger">*</span></label><input type="number" name="interval_days" class="form-control form-control-sm" min="1" max="3650" value="90" required></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Jatuh Tempo Berikutnya <span class="text-danger">*</span></label><input type="date" name="next_due_date" class="form-control form-control-sm" required></div>
                <div class="form-group col-md-5"><label class="small font-weight-bold">Vendor</label><input name="vendor" class="form-control form-control-sm" maxlength="255"></div>
                <div class="form-group col-md-3 js-sc-status d-none"><label class="small font-weight-bold">Status</label>
                    <select name="status" class="form-control form-control-sm" disabled><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div>
</div>

@include('asset._scripts')
@endsection

@push('scripts')
<script>
(function () {
    function toggleDone() { $('.js-mt-done').toggle($('#mtUpdateStatus').val() === 'COMPLETED'); }
    $('#mtUpdateStatus').on('change', toggleDone);
    $(document).on('click', '.js-mt-update', function () {
        $('#mtUpdateForm').attr('action', $(this).data('url'));
        $('#mtUpdateNo').text($(this).data('no'));
        $('#mtUpdateEnd').attr('min', $(this).data('min'));
        $('#mtUpdateStatus').val('COMPLETED'); toggleDone();
        $('#mtUpdate').modal('show');
    });
    $(document).on('click', '.js-sc-start', function () {
        var b = $(this);
        $('#mtSchedule').val(b.data('schedule'));
        $('#mtAsset').val(String(b.data('asset'))).trigger('change');
        $('#mtType').val(b.data('type'));
        $('#mtVendor').val(b.data('vendor') || '');
        $('#mtProblem').val(b.data('title'));
        $('#mtNew').modal('show');
    });
    $('#mtNew').on('hidden.bs.modal', function () { $('#mtSchedule').val(''); });
    $(document).on('click', '.js-sc-edit', function () {
        var d = $(this).data('json'), f = $('#scForm');
        f.attr('action', $(this).data('url')); $('#scMethod').val('PUT'); $('#scTitle').text('Ubah Jadwal');
        $('.js-sc-asset').hide().find('select').prop('disabled', true);
        $('.js-sc-status').removeClass('d-none').find('select').prop('disabled', false).val(d.status);
        ['title', 'maintenance_type', 'interval_days', 'vendor', 'next_due_date'].forEach(function (k) { f.find('[name=' + k + ']').val(d[k] || ''); });
        $('#scNew').modal('show');
    });
})();
</script>
@endpush
