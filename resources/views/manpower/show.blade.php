@extends('layouts.app')

@section('title', $mp->request_no)

@section('content')
@php
    $badges  = \App\Models\ManpowerRequest::STATUS_BADGES;
    $ds      = $mp->displayStatus();
    $flow    = ['DRAFT', 'SUBMITTED', 'APPROVED', 'PROCESS', 'PARTIALLY DONE', 'DONE'];
    $cur     = array_search($mp->status === 'REJECTED' ? 'DRAFT' : $ds, $flow, true);
    $allowed = $mp->allowedActions();
    $btn = [
        'submit'  => ['Ajukan', 'btn-primary', 'Ajukan pengajuan ini ke HR?', false],
        'approve' => ['Setujui', 'btn-primary', 'Setujui pengajuan ini?', false],
        'reject'  => ['Tolak', 'btn-outline-danger', 'Alasan penolakan', true],
        'cancel'  => ['Batalkan', 'btn-outline-danger', 'Alasan pembatalan', true],
    ];
    $mine = (int) $mp->requester_id === (int) auth()->id();
@endphp
<div class="container-fluid">
    <a href="{{ route('manpower.index') }}" class="small">&larr; Kembali ke daftar</a>

    <div class="d-flex align-items-start my-2" style="gap:12px">
        <div>
            <h4 class="mb-1">{{ $mp->request_no }} <span class="badge badge-{{ $badges[$ds] ?? 'secondary' }}">{{ $ds }}</span></h4>
            <div class="text-muted small">Diajukan {{ $mp->requester->name ?? '-' }} &middot; {{ $mp->request_date->format('d M Y') }}</div>
        </div>
        <div class="ml-auto d-flex flex-wrap" style="gap:6px">
            @if ($mp->canEdit())
                <a href="{{ route('manpower.edit', $mp) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            @endif
            @foreach ($allowed as $a)
                @continue(in_array($a, ['approve', 'reject'], true) && (! $isHr || $mp->isSelfApprovalBlockedFor(auth()->user())))
                @continue(in_array($a, ['submit', 'cancel'], true) && ! $isHr && ! $mine)
                <button type="button" class="btn btn-sm {{ $btn[$a][1] }}" data-toggle="modal" data-target="#modalAksi"
                        data-action="{{ route('manpower.action', [$mp, $a]) }}" data-title="{{ $btn[$a][0] }}"
                        data-text="{{ $btn[$a][2] }}" data-note="{{ $btn[$a][3] ? 1 : 0 }}">{{ $btn[$a][0] }}</button>
            @endforeach
        </div>
    </div>

    @if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif

    <div class="card mb-3"><div class="card-body py-2 d-flex flex-wrap align-items-center" style="gap:8px">
        @foreach ($flow as $i => $s)
            <span class="badge {{ $mp->status !== 'CANCEL' && $cur !== false && $i < $cur ? 'badge-success' : ($mp->status !== 'CANCEL' && $i === $cur ? 'badge-primary' : 'badge-light') }}">{{ $s }}</span>
            @if (! $loop->last)<span class="text-muted">&rsaquo;</span>@endif
        @endforeach
        @if ($mp->status === 'CANCEL')<span class="badge badge-dark">CANCEL</span>@endif
        @if ($mp->status === 'REJECTED')<span class="badge badge-danger">REJECTED — perbaiki lalu ajukan ulang</span>@endif
    </div></div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><b>Informasi</b></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Pemohon</dt><dd class="col-sm-8">{{ $mp->requester->name ?? '-' }}</dd>
                        <dt class="col-sm-4">Divisi</dt><dd class="col-sm-8">{{ $mp->department->name ?? '-' }}</dd>
                        <dt class="col-sm-4">Project</dt><dd class="col-sm-8">{{ $mp->project->name ?? '-' }}</dd>
                        <dt class="col-sm-4">Tanggal dibutuhkan</dt><dd class="col-sm-8">{{ $mp->needed_date?->format('d M Y') ?? '-' }}</dd>
                        <dt class="col-sm-4">Catatan</dt><dd class="col-sm-8">{{ $mp->note ?: '-' }}</dd>
                        @if ($mp->approved_by)
                            <dt class="col-sm-4">Disetujui</dt><dd class="col-sm-8">{{ $mp->approver->name ?? '-' }} &middot; {{ $mp->approved_at?->format('d M Y') }}@if ($mp->approval_note)<div class="small text-muted">{{ $mp->approval_note }}</div>@endif</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><b>Kebutuhan Pegawai</b> <span class="text-muted small">({{ $mp->details->count() }})</span></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>#</th><th>Posisi</th><th>Grade</th><th>Tipe</th><th>Menggantikan</th><th>Status</th><th>Terisi oleh</th></tr></thead>
                        <tbody>
                        @foreach ($mp->details as $i => $d)
                            <tr>
                                <td>{{ $i + 1 }}</td><td>{{ $d->position }}</td><td>{{ $d->grade }}</td>
                                <td><span class="badge badge-{{ $d->type === 'REPLACE' ? 'warning' : 'info' }}">{{ $d->type }}</span></td>
                                <td>{{ $d->replacedEmployee->name ?? '-' }}</td>
                                <td>{{ $d->status }}</td>
                                <td>{{ $d->filledEmployee->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><b>Diskusi &amp; Riwayat</b></div>
                <div class="card-body" style="max-height:480px;overflow:auto">
                    @forelse ($mp->logs as $g)
                        @if ($g->is_system)
                            <div class="small text-muted border-left pl-2 mb-3">
                                <b>{{ $g->event }}</b> &middot; {{ $g->user->name ?? 'System' }} &middot; {{ $g->created_at->format('d M H:i') }}
                                @if ($g->text)<div>{{ $g->text }}</div>@endif
                            </div>
                        @else
                            <div class="mb-3">
                                <div class="small"><b>{{ $g->user->name ?? '-' }}</b> <span class="text-muted">{{ $g->created_at->format('d M H:i') }}</span></div>
                                <div style="white-space:pre-wrap">{{ $g->text }}</div>
                            </div>
                        @endif
                    @empty
                        <div class="text-muted text-center">Belum ada riwayat</div>
                    @endforelse
                </div>
                <form method="post" action="{{ route('manpower.chat', $mp) }}" class="card-footer d-flex" style="gap:8px">
                    @csrf
                    <input name="text" class="form-control form-control-sm" placeholder="Tulis pesan ke HR / pemohon..." maxlength="2000" required>
                    <button class="btn btn-sm btn-primary">Kirim</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAksi" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content" id="formAksi">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="aksiJudul"></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <p id="aksiTeks" class="mb-2"></p>
                <textarea name="note" id="aksiNote" class="form-control" rows="3" placeholder="Catatan"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Ya, lanjutkan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$('#modalAksi').on('show.bs.modal', function (e) {
    var b = $(e.relatedTarget), wajib = String(b.data('note')) === '1';
    $('#formAksi').attr('action', b.data('action'));
    $('#aksiJudul').text(b.data('title'));
    $('#aksiTeks').text(b.data('text'));
    $('#aksiNote').prop('required', wajib).attr('placeholder', wajib ? 'Wajib diisi' : 'Catatan (opsional)').val('');
});
</script>
@endpush
