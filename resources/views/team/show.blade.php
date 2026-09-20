@extends('layouts.app')

@section('title', 'Detail Team')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">{{ $team->name }}</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/team') }}">Team</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="row">
            <!-- INFO CARD -->
            <div class="col-md-4">
                <div class="card shadow-sm" style="background:var(--charcoal);color:#fff;">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <span class="badge badge-dark px-2 py-1">{{ $team->code }}</span>
                            <h4 class="mt-2 mb-0" style="color:var(--accent);">{{ $team->name }}</h4>
                            <span class="badge badge-{{ $team->status_color }} mt-1">{{ $team->status }}</span>
                        </div>
                        <hr style="border-color:rgba(255,255,255,0.08);">
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Department</span><div class="text-white mt-1">{{ $team->department->name ?? '-' }}</div></div>
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Leader</span><div class="text-white mt-1">{{ strtoupper($team->leader->username ?? '-') }}</div></div>
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Member Aktif</span><div class="text-white mt-1">{{ $team->members->where('status', 'ACTIVE')->count() }}</div></div>
                        @if(Auth::user()->hasRole('superadmin') || (int) Auth::id() === (int) $team->leader_id)
                        <a href="{{ url('/team/' . $team->id . '/edit') }}" class="btn btn-outline-warning btn-sm btn-block mt-3">
                            <i class="fas fa-edit mr-1"></i> Edit Team
                        </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- CONTENT CARD -->
            <div class="col-md-8">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="teamTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#paneOrgChart">Organization Chart</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneMembers">Team Members</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">

                            <!-- ORG CHART -->
                            <div class="tab-pane fade show active" id="paneOrgChart">
                                @forelse($orgChart as $tier)
                                <div class="text-center mb-4">
                                    <div class="text-muted mb-2" style="font-size:11px;text-transform:uppercase;font-weight:700;">Level {{ $tier['level'] }}</div>
                                    <div class="d-flex flex-wrap justify-content-center" style="gap:12px;">
                                        @foreach($tier['members'] as $m)
                                        <div class="card shadow-sm" style="min-width:170px;border-radius:var(--radius-lg);border-top:3px solid var(--accent);">
                                            <div class="card-body p-2 text-center">
                                                <div style="font-weight:700;font-size:12.5px;">{{ strtoupper($m->user->username ?? '-') }}</div>
                                                <div class="text-muted" style="font-size:11px;">{{ $m->position ?? '-' }}</div>
                                                <span class="badge badge-{{ $m->status === 'ACTIVE' ? 'success' : 'secondary' }} mt-1" style="font-size:9px;">{{ $m->status }}</span>
                                                @if($m->ca_limit)
                                                <div class="text-muted mt-1" style="font-size:10px;">CA Limit: {{ $m->ca_limit_formatted }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @empty
                                <p class="text-muted text-center py-4">Belum ada member aktif.</p>
                                @endforelse
                            </div>

                            <!-- MEMBERS TABLE -->
                            <div class="tab-pane fade" id="paneMembers">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0" style="font-size:12.5px;">
                                        <thead class="bg-light">
                                            <tr>
                                                <th scope="col">User</th><th scope="col">Position</th><th scope="col" class="text-center">Level</th>
                                                <th scope="col" class="text-right">CA Limit</th><th scope="col">Join</th><th scope="col">Left</th><th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($team->members as $m)
                                            <tr>
                                                <td>{{ strtoupper($m->user->username ?? '-') }}</td>
                                                <td>{{ $m->position ?? '-' }}</td>
                                                <td class="text-center">{{ $m->level }}</td>
                                                <td class="text-right">{{ $m->ca_limit_formatted ?? '-' }}</td>
                                                <td>{{ $m->join_at?->format('d M Y') ?? '-' }}</td>
                                                <td>{{ $m->left_at?->format('d M Y') ?? '-' }}</td>
                                                <td><span class="badge badge-{{ $m->status === 'ACTIVE' ? 'success' : 'secondary' }}">{{ $m->status }}</span></td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada member.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
