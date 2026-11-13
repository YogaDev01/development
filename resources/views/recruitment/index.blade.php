@extends('layouts.app')

@section('title', 'Recruitment')

@section('content')
@php
    $stages = \App\Models\Recruitment::STAGES;
    $statusBadge = ['ON_PROCESS' => 'primary', 'APPROVED' => 'success', 'REJECTED' => 'danger', 'CANCELED' => 'dark'];
    $countStage = fn ($s) => $all->filter(fn ($r) => ($r->isActive() || $r->status === 'APPROVED') && $r->stage === $s)->count();
    $closed = $all->filter(fn ($r) => in_array($r->status, ['REJECTED', 'CANCELED'], true))->count();
@endphp
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Recruitment</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">HRIS</li>
                <li class="breadcrumb-item active">Recruitment</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Recruitment Management" title="Recruitment" description="Proses rekrutmen kandidat untuk kebutuhan pegawai yang sudah disetujui.">
            <x-slot:actions>
                <x-tw.button type="button" data-toggle="modal" data-target="#modalBaru"><i class="fas fa-plus" aria-hidden="true"></i> Recruitment Baru</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        @if ($errors->any())<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ $errors->first() }}</div>@endif

        @php
            $stColor = ['SCREENING' => 'secondary', 'INTERVIEW HR' => 'warning', 'INTERVIEW USER' => 'info', 'TRAINING' => 'primary', 'KONTRAK' => 'primary', 'ONBOARD' => 'success'];
            $steps = collect($stages)->map(fn ($st) => [$st, $countStage($st), $stColor[$st] ?? 'secondary', $st])->push(['CLOSE', $closed, 'dark', 'CLOSE'])->all();
            $groups = [
                ['Fase 1: Seleksi',    ['SCREENING', 'INTERVIEW HR', 'INTERVIEW USER']],
                ['Fase 2: Penempatan', ['TRAINING', 'KONTRAK', 'ONBOARD']],
                ['Fase 3: Selesai',    ['CLOSE']],
            ];
        @endphp
        <x-workflow-stepper :steps="$steps" :compact="true" :groups="$groups" table="tblRecruitment" field="stage" />

        @include('partials.list-toolbar', [
            'prefix' => 'rc',
            'filters' => [['id' => 'Position', 'label' => 'Posisi'], ['id' => 'State', 'label' => 'Status']],
            'placeholder' => 'Kandidat / kebutuhan',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblRecruitment" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col">Kandidat</th><th scope="col">Kebutuhan</th><th scope="col">Tahap</th><th scope="col">Status</th><th scope="col" class="text-nowrap">Jadwal</th><th scope="col" class="text-right"><span class="sr-only">Aksi</span></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $r)
                        @php $stLabel = $r->status === 'APPROVED' ? 'ONBOARD' : str_replace('_', ' ', $r->status); @endphp
                        <tr data-stage="{{ in_array($r->status, ['REJECTED', 'CANCELED'], true) ? 'CLOSE' : $r->stage }}" data-position="{{ $r->detail->position }}" data-state="{{ $stLabel }}">
                            <td><a href="{{ route('candidates.show', $r->candidate) }}" class="font-weight-bold">{{ $r->candidate->name }}</a><div class="small text-muted">{{ $r->candidate->code }}</div></td>
                            <td>{{ $r->detail->position }}<div class="small text-muted">{{ $r->detail->request->request_no }} · {{ $r->detail->grade }}</div></td>
                            <td class="text-nowrap">{{ $r->stage }}</td>
                            <td><span class="badge badge-{{ $statusBadge[$r->status] ?? 'secondary' }}">{{ $stLabel }}</span></td>
                            <td class="small text-nowrap" data-order="{{ $r->scheduled?->format('Y-m-d') }}">{{ $r->scheduled?->format('d M Y') ?? '-' }}</td>
                            <td class="text-right text-nowrap">
@if ($r->isActive())
                                @php $nx = $r->nextStage(); @endphp
                                @if ($nx)
                                    <button class="btn btn-sm btn-primary js-move" data-toggle="modal" data-target="#modalMove"
                                            data-action="{{ route('recruitment.move', [$r, 'next']) }}" data-title="Maju ke {{ $nx }}" data-note="0"
                                            data-text="{{ $r->candidate->name }}: {{ $r->stage }} → {{ $nx }}{{ $nx === 'ONBOARD' ? ' (karyawan baru akan dibuat)' : '' }}">{{ $nx === 'ONBOARD' ? 'Onboard' : 'Maju' }}</button>
                                @endif
                                <button class="btn btn-sm btn-outline-danger js-move" data-toggle="modal" data-target="#modalMove"
                                        data-action="{{ route('recruitment.move', [$r, 'reject']) }}" data-title="Tolak kandidat" data-note="1" data-text="Alasan penolakan">Tolak</button>
                                <button class="btn btn-sm btn-outline-secondary js-move" data-toggle="modal" data-target="#modalMove"
                                        data-action="{{ route('recruitment.move', [$r, 'cancel']) }}" data-title="Batalkan proses" data-note="1" data-text="Alasan pembatalan (kandidat mundur)">Batal</button>
                            @endif
                        </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Belum ada proses rekrutmen" message="Mulai dengan Recruitment Baru." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

<div class="modal fade" id="modalBaru" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('recruitment.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Recruitment Baru</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>Kandidat (tersedia) *</label>
                    <select name="candidate_id" class="form-control" required>
                        <option value="">- pilih -</option>
                        @foreach ($available as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->code }} · {{ $c->position }}</option>@endforeach
                    </select></div>
                <div class="form-group"><label>Kebutuhan pegawai (pengajuan disetujui) *</label>
                    <select name="detail_id" class="form-control" required>
                        <option value="">- pilih -</option>
                        @foreach ($openDetails as $d)<option value="{{ $d->id }}">{{ $d->request->request_no }} · {{ $d->position }} · {{ $d->grade }} · {{ $d->type }}</option>@endforeach
                    </select></div>
                <div class="form-group mb-0"><label>Catatan</label><textarea name="note" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Mulai SCREENING</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalMove" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content" id="formMove">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="moveJudul"></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body"><p id="moveTeks" class="mb-2"></p><textarea name="note" id="moveNote" class="form-control" rows="3"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Ya, lanjutkan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$('#modalMove').on('show.bs.modal', function (e) {
    var b = $(e.relatedTarget), wajib = String(b.data('note')) === '1';
    $('#formMove').attr('action', b.data('action'));
    $('#moveJudul').text(b.data('title'));
    $('#moveTeks').text(b.data('text'));
    $('#moveNote').prop('required', wajib).attr('placeholder', wajib ? 'Wajib diisi' : 'Catatan (opsional)').val('');
});
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
        table: '#tblRecruitment', order: [], search: '#rcSearch', clear: '#rcClear',
        filters: [
            { target: '#rcFilterPosition', key: 'position', label: 'Semua Posisi' },
            { target: '#rcFilterState', key: 'state', label: 'Semua Status', search: false }
        ]
    });
});
</script>
@endpush
