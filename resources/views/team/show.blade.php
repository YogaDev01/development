@extends('layouts.app')

@section('title', 'Detail Team')

@push('styles')
<style>
    .tm-profile { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden; }
    .tm-profile-head { padding: var(--sp-6) var(--sp-5) var(--sp-4); text-align: center; border-bottom: 1px solid var(--border); border-top: 3px solid var(--accent); }
    .tm-avatar { display: inline-flex; align-items: center; justify-content: center; border-radius: var(--radius-pill); background: var(--accent-tint); color: var(--accent-text); border: 1px solid var(--accent-tint-2); font-weight: 700; flex: 0 0 auto; }
    .tm-avatar-lg { width: 56px; height: 56px; font-size: 20px; margin-bottom: var(--sp-3); }
    .tm-avatar-md { width: 40px; height: 40px; font-size: 14px; margin-bottom: var(--sp-2); }
    .tm-avatar-sm { width: 28px; height: 28px; font-size: 11px; }
    .tm-code { display: inline-block; font-size: var(--fs-caption); font-weight: 600; letter-spacing: var(--ls-micro); color: var(--text-dim); background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-pill); padding: 2px 10px; }
    .tm-name { margin: var(--sp-2) 0 var(--sp-2); font-family: var(--font-display); font-size: 20px; font-weight: var(--fw-heading); color: var(--text); }
    .tm-info { padding: var(--sp-2) var(--sp-5); }
    .tm-info-row { display: flex; align-items: center; gap: var(--sp-3); padding: var(--sp-3) 0; border-bottom: 1px solid var(--border); }
    .tm-info-row:last-child { border-bottom: 0; }
    .tm-info-ico { width: 32px; height: 32px; border-radius: var(--radius-sm); background: var(--surface-2); color: var(--text-dim); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; flex: 0 0 auto; }
    .tm-info-label { font-size: var(--fs-caption); text-transform: uppercase; letter-spacing: var(--ls-micro); font-weight: 600; color: var(--text-faint); }
    .tm-info-value { font-size: var(--fs-body); font-weight: 600; color: var(--text); word-break: break-word; }
    .tm-profile-foot { padding: var(--sp-3) var(--sp-5) var(--sp-5); }

    .tm-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
    .tm-tabs { display: flex; gap: var(--sp-1); padding: 0 var(--sp-4); border-bottom: 1px solid var(--border); margin: 0; list-style: none; overflow-x: auto; }
    .tm-tabs .nav-link { border: 0; border-bottom: 2px solid transparent; border-radius: 0; padding: var(--sp-4) var(--sp-3); font-size: var(--fs-body); font-weight: 600; color: var(--text-dim); white-space: nowrap; }
    .tm-tabs .nav-link:hover { color: var(--accent-text); }
    .tm-tabs .nav-link.active { color: var(--accent-text); border-bottom-color: var(--accent); background: transparent; }
    .tm-tab-count { display: inline-block; min-width: 20px; margin-left: 6px; padding: 0 6px; border-radius: var(--radius-pill); background: var(--surface-2); color: var(--text-dim); font-size: var(--fs-caption); line-height: 18px; text-align: center; }
    .tm-tabs .nav-link.active .tm-tab-count { background: var(--accent-tint); color: var(--accent-text); }
    .tm-panel-body { padding: var(--sp-6) var(--sp-5); }

    .tm-tier { position: relative; text-align: center; padding-top: var(--sp-2); }
    .tm-tier + .tm-tier { margin-top: var(--sp-6); }
    .tm-tier + .tm-tier::before { content: ''; display: block; width: 1px; height: var(--sp-6); background: var(--border-strong); margin: calc(var(--sp-6) * -1) auto var(--sp-3); }
    .tm-tier-label { display: inline-block; margin-bottom: var(--sp-3); padding: 2px 12px; border-radius: var(--radius-pill); background: var(--surface-2); border: 1px solid var(--border); font-size: var(--fs-caption); font-weight: 700; letter-spacing: var(--ls-micro); text-transform: uppercase; color: var(--text-dim); }
    .tm-tier-members { display: flex; flex-wrap: wrap; justify-content: center; gap: var(--sp-3); }
    .tm-node { width: 200px; padding: var(--sp-4) var(--sp-3); background: var(--surface); border: 1px solid var(--border); border-top: 3px solid var(--accent); border-radius: var(--radius-md); box-shadow: var(--shadow-xs); transition: box-shadow var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease); }
    .tm-node:hover { box-shadow: var(--shadow-md); border-color: var(--accent-soft); border-top-color: var(--accent); }
    .tm-node.is-inactive { border-top-color: var(--border-strong); opacity: .75; }
    .tm-node.is-inactive .tm-avatar { background: var(--surface-2); color: var(--text-dim); border-color: var(--border); }
    .tm-node-name { font-size: var(--fs-body); font-weight: 700; color: var(--text); word-break: break-word; }
    .tm-node-pos { font-size: var(--fs-label); color: var(--text-dim); margin-bottom: var(--sp-2); }

    .tm-log { list-style: none; margin: 0; padding: 0; }
    .tm-log li { display: flex; gap: var(--sp-3); padding: var(--sp-3) 0; border-bottom: 1px solid var(--border); }
    .tm-log li:last-child { border-bottom: 0; }
    .tm-log-time { flex: 0 0 120px; font-size: var(--fs-caption); color: var(--text-faint); font-variant-numeric: tabular-nums; }
    .tm-log-title { font-size: var(--fs-body); font-weight: 600; color: var(--text); }
    .tm-log-meta { font-size: var(--fs-caption); color: var(--text-dim); }
    .tm-log-diff { margin-top: 2px; font-size: var(--fs-label); color: var(--text-dim); word-break: break-word; }
    .tm-log-note { margin-top: 2px; font-size: var(--fs-label); color: var(--text); font-style: italic; }
    .tm-sub { display: inline-block; margin: 0 4px 4px 0; }

    .tm-chip { display: inline-block; padding: 1px 8px; border-radius: var(--radius-pill); font-size: var(--fs-caption); font-weight: 600; }
    .tm-chip-ok { background: var(--success-bg); color: var(--success); }
    .tm-chip-off { background: var(--darkbadge-bg); color: var(--darkbadge); }

    .tm-table { width: 100%; margin: 0; font-size: var(--fs-data); }
    .tm-table th { background: var(--surface-2); color: var(--text-dim); font-size: var(--fs-label); font-weight: 600; text-transform: uppercase; letter-spacing: var(--ls-micro); padding: var(--sp-3); border-top: 0; border-bottom: 1px solid var(--border); white-space: nowrap; }
    .tm-table td { padding: var(--sp-3); border-top: 0; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
    .tm-table tbody tr:last-child td { border-bottom: 0; }
    .tm-table tbody tr:hover td { background: var(--surface-2); }
    .tm-user { display: flex; align-items: center; gap: var(--sp-2); font-weight: 600; }
    .tm-empty { text-align: center; padding: var(--sp-10) var(--sp-4); color: var(--text-faint); font-size: var(--fs-body); }
    .tm-empty i { display: block; font-size: 28px; margin-bottom: var(--sp-2); color: var(--border-strong); }

    @media (max-width: 640px) {
        .tm-panel-body { padding: var(--sp-4) var(--sp-3); }
        .tm-node { width: 100%; max-width: 320px; }
        .tm-log li { flex-direction: column; gap: 2px; }
        .tm-log-time { flex: none; }
    }
</style>
@endpush

@section('content')

@php
    $activeCount = $team->members->where('status', 'ACTIVE')->count();
    $leaderName = strtoupper($team->leader->username ?? '-');
    $initial = fn ($name) => mb_strtoupper(mb_substr(trim((string) $name) !== '' ? $name : '-', 0, 1));
    $denganStruktur = \App\Models\Team::supportsStructure();
    // Nilai riwayat ditampilkan apa adanya; array [lama, baru] jadi "lama → baru".
    $fmtLog = function ($v) {
        if (is_array($v) && array_is_list($v) && count($v) === 2) {
            return ($v[0] === null || $v[0] === '' ? '—' : (is_bool($v[0]) ? ($v[0] ? 'Ya' : 'Tidak') : $v[0]))
                . ' → '
                . ($v[1] === null || $v[1] === '' ? '—' : (is_bool($v[1]) ? ($v[1] ? 'Ya' : 'Tidak') : $v[1]));
        }
        return is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? $x : json_encode($x), $v)) : (string) $v;
    };
    $labelField = ['name' => 'Nama', 'department_id' => 'Department', 'customer_id' => 'Customer', 'leader' => 'Leader',
        'parent' => 'Induk', 'status' => 'Status', 'position' => 'Posisi', 'level' => 'Level', 'is_primary' => 'Primary',
        'user_id' => 'User', 'members' => 'Anggota', 'ditambah' => 'Ditambah', 'dicabut' => 'Dicabut'];
@endphp

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
            <div class="col-lg-4 mb-3">
                <div class="tm-profile">
                    <div class="tm-profile-head">
                        <span class="tm-avatar tm-avatar-lg">{{ $initial($team->name) }}</span>
                        <div><span class="tm-code">{{ $team->code }}</span></div>
                        <h4 class="tm-name">{{ $team->name }}</h4>
                        <span class="badge badge-{{ $team->status_color }}">{{ $team->status }}</span>
                    </div>
                    <div class="tm-info">
                        <div class="tm-info-row">
                            <span class="tm-info-ico"><i class="fas fa-building"></i></span>
                            <div><div class="tm-info-label">Department</div><div class="tm-info-value">{{ $team->department->name ?? '-' }}</div></div>
                        </div>
                        <div class="tm-info-row">
                            <span class="tm-info-ico"><i class="fas fa-user-tie"></i></span>
                            <div><div class="tm-info-label">Leader</div><div class="tm-info-value">{{ $leaderName }}</div></div>
                        </div>
                        <div class="tm-info-row">
                            <span class="tm-info-ico"><i class="fas fa-users"></i></span>
                            <div><div class="tm-info-label">Total Member Aktif</div><div class="tm-info-value">{{ $activeCount }}</div></div>
                        </div>
                        <div class="tm-info-row">
                            <span class="tm-info-ico"><i class="fas fa-key"></i></span>
                            <div>
                                <div class="tm-info-label">Akses PO (Access Group)</div>
                                <div class="tm-info-value">
                                    @forelse($team->accessGroupLinks->pluck('accessGroup')->filter()->unique('id') as $ag)
                                    <span class="tm-sub">{{ $ag->name }} <span class="text-muted">· {{ $ag->po_count }} PO</span></span>
                                    @empty
                                    -
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        @if($denganStruktur)
                        <div class="tm-info-row">
                            <span class="tm-info-ico"><i class="fas fa-level-up-alt"></i></span>
                            <div>
                                <div class="tm-info-label">Induk Team</div>
                                <div class="tm-info-value">
                                    @if($team->parent)
                                    <a href="{{ url('/team/' . $team->parent->id) }}">{{ $team->parent->name }}</a>
                                    @else
                                    -
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="tm-info-row">
                            <span class="tm-info-ico"><i class="fas fa-project-diagram"></i></span>
                            <div>
                                <div class="tm-info-label">Sub-Team</div>
                                <div class="tm-info-value">
                                    @forelse($team->children as $c)
                                    <a href="{{ url('/team/' . $c->id) }}" class="tm-sub">{{ $c->name }}@if($c->status !== 'ACTIVE') <span class="text-muted">(INACTIVE)</span>@endif</a>
                                    @empty
                                    -
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @if(Auth::user()->hasRole('superadmin') || (int) Auth::id() === (int) $team->leader_id)
                    <div class="tm-profile-foot">
                        <a href="{{ url('/team/' . $team->id . '/edit') }}" class="btn btn-outline-secondary btn-sm btn-block">
                            <i class="fas fa-edit mr-1"></i> Edit Team
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            <!-- CONTENT CARD -->
            <div class="col-lg-8 mb-3">
                <div class="tm-panel">
                    <ul class="nav tm-tabs" id="teamTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#paneOrgChart"><i class="fas fa-sitemap mr-1"></i> Organization Chart</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneMembers"><i class="fas fa-users mr-1"></i> Team Members<span class="tm-tab-count">{{ $team->members->count() }}</span></a></li>
                        @if($denganStruktur)
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneHistory"><i class="fas fa-history mr-1"></i> Riwayat<span class="tm-tab-count">{{ $team->logs->count() }}</span></a></li>
                        @endif
                    </ul>
                    <div class="tm-panel-body">
                        <div class="tab-content">

                            <!-- ORG CHART -->
                            <div class="tab-pane fade show active" id="paneOrgChart">
                                @forelse($orgChart as $tier)
                                <div class="tm-tier">
                                    <span class="tm-tier-label">Level {{ $tier['level'] }}</span>
                                    <div class="tm-tier-members">
                                        @foreach($tier['members'] as $m)
                                        @php $uname = strtoupper($m->user->username ?? '-'); @endphp
                                        <div class="tm-node {{ $m->status === 'ACTIVE' ? '' : 'is-inactive' }}">
                                            <span class="tm-avatar tm-avatar-md">{{ $initial($uname) }}</span>
                                            <div class="tm-node-name">{{ $uname }}</div>
                                            <div class="tm-node-pos">{{ $m->position ?? '-' }}</div>
                                            <span class="tm-chip {{ $m->status === 'ACTIVE' ? 'tm-chip-ok' : 'tm-chip-off' }}">{{ $m->status }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @empty
                                <div class="tm-empty"><i class="fas fa-sitemap"></i>Belum ada member aktif.</div>
                                @endforelse
                            </div>

                            <!-- MEMBERS TABLE -->
                            <div class="tab-pane fade" id="paneMembers">
                                <div class="table-responsive">
                                    <table class="table tm-table" data-ts-card="off">
                                        <thead>
                                            <tr>
                                                <th scope="col">User</th><th scope="col">Position</th><th scope="col" class="text-center">Level</th>
                                                @if($denganStruktur)<th scope="col" class="text-center">Primary</th>@endif
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- Anggota aktif dulu, lalu riwayat (INACTIVE). --}}
                                            @forelse($team->members->sortBy(fn ($m) => [$m->status === 'ACTIVE' ? 0 : 1, $m->level]) as $m)
                                            @php $uname = strtoupper($m->user->username ?? '-'); @endphp
                                            <tr>
                                                <td><span class="tm-user"><span class="tm-avatar tm-avatar-sm">{{ $initial($uname) }}</span>{{ $uname }}</span></td>
                                                <td>{{ $m->position ?? '-' }}</td>
                                                <td class="text-center">{{ $m->level }}</td>
                                                @if($denganStruktur)
                                                <td class="text-center">@if($m->is_primary)<span class="tm-chip tm-chip-ok">Primary</span>@else - @endif</td>
                                                @endif
                                                <td><span class="tm-chip {{ $m->status === 'ACTIVE' ? 'tm-chip-ok' : 'tm-chip-off' }}">{{ $m->status }}</span></td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="{{ $denganStruktur ? 5 : 4 }}"><div class="tm-empty"><i class="fas fa-users"></i>Belum ada member.</div></td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if($denganStruktur)
                            <!-- RIWAYAT -->
                            <div class="tab-pane fade" id="paneHistory">
                                @if($team->logs->isEmpty())
                                <div class="tm-empty"><i class="fas fa-history"></i>Belum ada riwayat perubahan. Riwayat mulai tercatat sejak pembaruan struktur Team.</div>
                                @else
                                <ul class="tm-log">
                                    @foreach($team->logs as $log)
                                    <li>
                                        <div class="tm-log-time">{{ $log->created_at?->format('d M Y H:i') }}</div>
                                        <div style="min-width:0;">
                                            <div class="tm-log-title">{{ $log->action_label }}@if(!empty($log->changes['user'])) &mdash; {{ $log->changes['user'] }}@endif</div>
                                            <div class="tm-log-meta">oleh {{ $log->user_name ?? 'System' }}@if($log->role) ({{ $log->role }})@endif</div>
                                            @foreach(($log->changes['fields'] ?? collect($log->changes ?? [])->except('user')->all()) as $field => $val)
                                            <div class="tm-log-diff">{{ $labelField[$field] ?? $field }}: {{ $fmtLog($val) }}</div>
                                            @endforeach
                                            @if($log->notes)<div class="tm-log-note">{{ $log->notes }}</div>@endif
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                                @endif
                            </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
