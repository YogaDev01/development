{{--
    Tab TEAM — Organization Chart.
    Menampilkan Organization Chart dari setiap Team tempat user ini jadi
    member aktif, disusun per level (Team::orgChart()). Kalau user belum
    tergabung di team manapun, tampilkan empty state.
--}}

@if($myTeams->isEmpty())
<div class="empty-state">
    <i class="fas fa-sitemap" style="font-size:26px;"></i>
    <div class="et">Belum tergabung dalam Team</div>
    <div class="ed">Anda belum menjadi member aktif di team manapun. Hubungi Superadmin atau Team Leader untuk ditambahkan.</div>
</div>
@else
@foreach($myTeams as $t)
<div class="org-team mb-4">
    {{-- HEADER TEAM --}}
    <div class="d-flex align-items-center flex-wrap mb-3" style="gap:10px;">
        <div class="org-team-ic"><i class="fas fa-sitemap"></i></div>
        <div style="flex:1;min-width:0;">
            <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                <span class="font-weight-bold" style="font-size:14px;">{{ $t->name }}</span>
                <span class="badge badge-light border">{{ $t->code }}</span>
                <span class="badge badge-{{ $t->status_color }}">{{ $t->status }}</span>
            </div>
            <div class="text-muted" style="font-size:11.5px;">
                Dept: {{ $t->department->name ?? '-' }}
                &nbsp;·&nbsp; Leader: {{ strtoupper($t->leader->username ?? '-') }}
                &nbsp;·&nbsp; {{ $t->activeMembers->count() }} member aktif
            </div>
        </div>
    </div>

    {{-- ORG CHART per LEVEL --}}
    @php($orgChart = $t->orgChart())
    @forelse($orgChart as $tier)
    <div class="org-tier">
        <div class="org-tier-label">Level {{ $tier['level'] }}</div>
        <div class="org-tier-cards">
            @foreach($tier['members'] as $m)
            <div class="org-card {{ (int)($m->user->id ?? 0) === (int)$user->id ? 'is-me' : '' }}">
                <div class="org-card-avatar">
                    @if($m->user && $m->user->avatar_url)
                        <img src="{{ $m->user->avatar_url }}" alt="">
                    @else
                        {{ strtoupper(Str::substr($m->user->name ?? ($m->user->username ?? '?'), 0, 2)) }}
                    @endif
                </div>
                <div class="org-card-name">{{ $m->user->name ?? strtoupper($m->user->username ?? '-') }}</div>
                <div class="org-card-pos">{{ $m->position ?? '-' }}</div>
                @if((int)($m->user->id ?? 0) === (int)$user->id)
                <span class="badge badge-warning mt-1" style="font-size:9px;">Anda</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <p class="text-muted text-center py-3" style="font-size:12px;">Belum ada member aktif pada team ini.</p>
    @endforelse
</div>
@endforeach
@endif
