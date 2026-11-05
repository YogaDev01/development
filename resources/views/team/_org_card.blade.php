{{--
    Satu kartu orang di bagan organisasi, dipakai dua mode:
      - Leader View (rekursif): $card['teams'] = team yang dipimpin (tombol ke
        Detail Member), $card['children'] = kartu leader di bawahnya.
      - Detail Member: $card['led'] = nama team lain yang dipimpin anggota ini.
    Variabel: $card (TeamController), $up ('card:KEY' / null).
    Garis penghubung digambar JS dari data-up — bukan dari markup.
--}}
@php
    $inisial = mb_strtoupper(mb_substr(trim((string) $card['name']) !== '' ? $card['name'] : '?', 0, 2));
    $judul = $card['position'] ?: ($card['role'] === 'leader' ? 'Leader' : 'Anggota');
    $dipimpin = $card['led'] ?? [];
    $teamLeader = $card['teams'] ?? [];
    $cari = mb_strtolower(implode(' ', array_filter([
        $card['name'], $card['position'], $card['team_name'], $card['team_code'],
        ...$dipimpin, ...array_column($teamLeader, 'name'), ...array_column($teamLeader, 'code'),
    ])));
@endphp
<div class="oc-node">
    <button type="button" class="oc-card {{ $card['is_me'] ? 'is-me' : '' }} {{ $card['role'] === 'leader' || $dipimpin ? 'is-leader' : '' }}"
            data-key="{{ $card['key'] }}" data-user="{{ $card['user_id'] }}" data-team="{{ $card['team_id'] }}" @if($up) data-up="{{ $up }}" @endif
            data-search="{{ $cari }}" title="Lihat profil organisasi {{ $card['name'] }}">
        <span class="oc-photo">
            @if($card['avatar'])<img src="{{ $card['avatar'] }}" alt="" loading="lazy">@else{{ $inisial }}@endif
        </span>
        <span class="oc-label">
            <strong>{{ $judul }}</strong>
            <span>{{ $card['name'] }}</span>
        </span>
        <span class="oc-tags">
            @if($card['is_me'])<span class="oc-tag is-me">Anda</span>@endif
            @foreach($dipimpin as $nama)<span class="oc-tag is-lead">Leader {{ $nama }}</span>@endforeach
            <span class="oc-tag is-dup" hidden></span>
        </span>
    </button>

    @if($teamLeader)
        @include('team._org_team_links', ['teams' => $teamLeader])
    @endif

    @if(! empty($card['children']))
    <button type="button" class="oc-toggle" aria-expanded="true" title="Ciutkan / bentangkan di bawah {{ $card['name'] }}">
        <i class="fas fa-minus" aria-hidden="true"></i>
    </button>
    <div class="oc-children">
        @foreach($card['children'] as $anak)
            @include('team._org_card', ['card' => $anak, 'up' => 'card:' . $card['key']])
        @endforeach
    </div>
    @endif
</div>
