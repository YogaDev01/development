{{--
    Isi mode Detail Member untuk satu team. Dibungkus <template> supaya hanya
    team terpilih yang masuk ke halaman (JS menyalinnya ke #ocTeamView).
    Variabel: $team (TeamController::buildTeamViews).
--}}
<template id="ocTeamTpl-{{ $team['id'] }}">
    <div class="ot-head">
        <div>
            <div class="ot-title">{{ $team['name'] }} <span class="os-code">{{ $team['code'] }}</span></div>
            <div class="ot-sub">{{ $team['department'] ?: 'Tanpa department' }} · {{ $team['count'] }} orang</div>
        </div>
        <a href="{{ url('/team/' . $team['id']) }}" class="btn btn-outline-secondary btn-sm">Buka detail team</a>
    </div>

    @if($team['up'] || $team['subs'])
    <div class="ot-nav">
        @if($team['up'])
        <span class="ot-nav-group">
            <span class="ot-nav-label">Di bawah</span>
            <button type="button" class="oc-teamlink" data-team="{{ $team['up']['id'] }}">{{ $team['up']['name'] }}</button>
        </span>
        @endif
        @if($team['subs'])
        <span class="ot-nav-group">
            <span class="ot-nav-label">Membawahi</span>
            @foreach($team['subs'] as $sub)
            <button type="button" class="oc-teamlink" data-team="{{ $sub['id'] }}">{{ $sub['name'] }}</button>
            @endforeach
        </span>
        @endif
    </div>
    @endif

    <div class="ot-body">
        @if($team['leader'])
            @include('team._org_card', ['card' => $team['leader'], 'up' => null])
        @else
            <div class="oc-empty">Team ini belum punya leader.</div>
        @endif

        @forelse($team['tiers'] as $tier)
        <div class="ot-tier">
            <div class="ot-tier-label">Level {{ $tier['level'] }} · {{ count($tier['cards']) }} orang</div>
            <div class="ot-cards">
                @foreach($tier['cards'] as $c)
                    @include('team._org_card', ['card' => $c, 'up' => null])
                @endforeach
            </div>
        </div>
        @empty
        <div class="oc-empty">Belum ada anggota selain leader.</div>
        @endforelse
    </div>
</template>
