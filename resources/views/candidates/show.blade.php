@extends('layouts.app')

@section('title', $c->name)

@section('content')
@php
    $av = $c->availability();
    $availBadge = ['TERSEDIA' => 'success', 'DIPROSES' => 'primary', 'ONBOARD' => 'dark'];
    $statusBadge = ['ON_PROCESS' => 'primary', 'APPROVED' => 'success', 'REJECTED' => 'danger', 'CANCELED' => 'dark', 'RETURNED' => 'secondary'];
@endphp
<div class="container-fluid">
    <a href="{{ route('candidates.index') }}" class="small">&larr; Kembali ke daftar kandidat</a>
    <h4 class="my-2">{{ $c->name }} <small class="text-muted">{{ $c->code }}</small> <span class="badge badge-{{ $availBadge[$av] }}">{{ $av }}</span></h4>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-header"><b>Biodata</b></div><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Telepon</dt><dd class="col-sm-8">{{ $c->phone }}</dd>
                    <dt class="col-sm-4">Tempat / tgl lahir</dt><dd class="col-sm-8">{{ $c->birth_place ?: '-' }}, {{ $c->birth_date->format('d M Y') }}</dd>
                    <dt class="col-sm-4">Gender</dt><dd class="col-sm-8">{{ \App\Models\Candidate::GENDERS[$c->gender] ?? '-' }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $c->marital_status ?: '-' }}</dd>
                    <dt class="col-sm-4">Pendidikan</dt><dd class="col-sm-8">{{ $c->education ?: '-' }}</dd>
                    <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $c->address ?: '-' }}</dd>
                    <dt class="col-sm-4">Posisi / grade</dt><dd class="col-sm-8">{{ $c->position ?: '-' }} · {{ $c->grade ?: '-' }}</dd>
                    <dt class="col-sm-4">Sumber</dt><dd class="col-sm-8">{{ $c->source ?: '-' }}</dd>
                    @if ($c->hiredEmployee)<dt class="col-sm-4">ID karyawan</dt><dd class="col-sm-8">EMP-{{ str_pad($c->hired_employee_id, 4, '0', STR_PAD_LEFT) }} <span class="small text-muted">({{ $c->hiredEmployee->status }})</span></dd>@endif
                </dl>
            </div></div>

            <div class="card mb-3"><div class="card-header"><b>Dokumen</b></div><div class="card-body">
                @forelse ($c->documents as $d)
                    <div class="d-flex align-items-center border-bottom py-2" style="gap:8px">
                        <div class="flex-grow-1">{{ $d->original_name }}<div class="small text-muted">{{ number_format($d->size / 1024, 1) }} KB</div></div>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('candidates.documents.download', [$c, $d]) }}">Buka</a>
                        <form method="post" action="{{ route('candidates.documents.destroy', [$c, $d]) }}" onsubmit="return confirm('Hapus dokumen ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                    </div>
                @empty
                    <div class="text-muted text-center">Belum ada dokumen</div>
                @endforelse
                <form method="post" action="{{ route('candidates.documents.store', $c) }}" enctype="multipart/form-data" class="form-inline mt-3" style="gap:8px">
                    @csrf
                    <input type="file" name="file" class="form-control-file" required>
                    <button class="btn btn-sm btn-primary">+ Unggah</button>
                </form>
                @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div></div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-header"><b>Riwayat Recruitment</b></div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Kebutuhan</th><th>Tahap</th><th>Status</th></tr></thead><tbody>
                    @forelse ($c->recruitments as $r)
                        <tr><td>{{ $r->detail->position }} <span class="small text-muted">{{ $r->detail->request->request_no }}</span></td><td>{{ $r->stage }}</td>
                            <td><span class="badge badge-{{ $statusBadge[$r->status] ?? 'secondary' }}">{{ str_replace('_', ' ', $r->status) }}</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Belum pernah diproses</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="card"><div class="card-header"><b>Log Aktivitas</b></div>
                <div class="card-body" style="max-height:420px;overflow:auto">
                    @forelse ($c->logs as $g)
                        <div class="border-left pl-2 mb-3 small"><div>{{ $g->description }}</div>
                            <div class="text-muted">{{ $g->user->name ?? 'System' }} · {{ $g->created_at->format('d M Y H:i') }}</div></div>
                    @empty
                        <div class="text-muted text-center">Belum ada log</div>
                    @endforelse
                </div></div>
        </div>
    </div>
</div>
@endsection
