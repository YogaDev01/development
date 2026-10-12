@extends('layouts.app')

@section('title', 'Pengaturan Trouble Ticket')

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="tt-title">Pengaturan SLA Trouble Ticket</h1>
                <div class="tt-sub">Target disalin ke tiket saat tiket dibuat — mengubahnya tidak memengaruhi tiket lama.</div>
            </div>
            <a href="{{ route('trouble-ticket.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2" style="font-size:13px;">{{ $errors->first() }}</div>
        @endif

        <div class="alert alert-light border" style="font-size:12.5px;">
            <i class="fas fa-database mr-1"></i>
            Master <strong>Severity</strong> ({{ $severities->count() }}) dan <strong>Problem</strong> ({{ $problems->count() }}) tidak punya layar:
            diisi langsung di tabel <code>trouble_ticket_severities</code> dan <code>trouble_ticket_problems</code>.
        </div>

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-stopwatch mr-1"></i> Kebijakan SLA (menit)</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('trouble-ticket.settings.sla.store') }}" class="form-row align-items-end mb-3 tt-filter">
                    @csrf
                    <div class="col-md-2 col-6 mb-2">
                        <label>Severity</label>
                        <select name="severity" class="form-control form-control-sm">
                            <option value="">Semua</option>
                            @foreach($severities->where('status', 'ACTIVE') as $sv)<option value="{{ $sv->code }}">{{ $sv->name }}</option>@endforeach
                        </select>
                        <small class="form-text text-muted">Tiket baru memilih kebijakan per Problem saja.</small>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <label>Problem</label>
                        <select name="problem_id" class="form-control form-control-sm">
                            <option value="">Semua</option>
                            @foreach($problems->where('status', 'ACTIVE') as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-1 col-4 mb-2"><label>Response</label><input type="number" min="1" name="response_minutes" class="form-control form-control-sm"></div>
                    <div class="col-md-1 col-4 mb-2"><label>Travel</label><input type="number" min="1" name="travel_minutes" class="form-control form-control-sm"></div>
                    <div class="col-md-1 col-4 mb-2"><label>Resolution</label><input type="number" min="1" name="resolution_minutes" class="form-control form-control-sm"></div>
                    <div class="col-md-2 col-6 mb-2"><label>Berlaku mulai</label><input type="date" name="effective_from" class="form-control form-control-sm"></div>
                    <div class="col-md-2 col-6 mb-2"><button class="btn btn-success btn-sm btn-block" @disabled($severities->isEmpty())><i class="fas fa-plus mr-1"></i> Tambah</button></div>
                </form>
                <p class="text-muted" style="font-size:12px;">Kebijakan spesifik problem didahulukan dari "Semua"; di antara yang setara, yang paling baru berlaku. Angka kebijakan tidak bisa diubah — nonaktifkan lalu buat yang baru.</p>

                <div class="table-responsive">
                    <table class="table table-sm mb-0 tt-table">
                        <thead><tr><th>Severity</th><th>Problem</th><th>Response</th><th>Travel</th><th>Resolution</th><th>Berlaku</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse($policies as $p)
                                <tr style="cursor:default;">
                                    <td><span class="tt-sev tt-sev-{{ $p->severityRef?->level ?? 9 }}">{{ $p->severityRef?->name ?? $p->severity ?? 'Semua' }}</span></td>
                                    <td>{{ $p->problem?->name ?? 'Semua' }}</td>
                                    <td>{{ $p->response_minutes ?? '—' }}</td>
                                    <td>{{ $p->travel_minutes ?? '—' }}</td>
                                    <td>{{ $p->resolution_minutes ?? '—' }}</td>
                                    <td>{{ $p->effective_from?->format('d/m/Y') ?? 'Segera' }}</td>
                                    <td><x-status :status="$p->status" /></td>
                                    <td>
                                        <form method="POST" action="{{ route('trouble-ticket.settings.sla.toggle', $p) }}">
                                            @csrf
                                            <button class="btn btn-outline-secondary btn-xs">{{ $p->status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><x-empty title="Belum ada target SLA" message="Tanpa target, durasi tetap dicatat tetapi tiket tidak pernah ditandai terlambat." icon="fas fa-stopwatch" compact /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
