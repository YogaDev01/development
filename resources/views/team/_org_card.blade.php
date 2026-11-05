{{--
    Satu kartu orang di bagan organisasi (rekursif lewat _org_block).
    Variabel: $card (TeamController::buildOrgChart), $up ('card:KEY' / 'row:ID' / null).
    Garis penghubung digambar JS dari data-up — bukan dari markup.
--}}
@php
    $inisial = mb_strtoupper(mb_substr(trim((string) $card['name']) !== '' ? $card['name'] : '?', 0, 2));
    $judul = $card['position'] ?: ($card['role'] === 'leader' ? 'Leader' : 'Anggota');
    // Team yang dipimpin dari kartu ini (blok di bawahnya).
    $dipimpin = collect($card['children'])->map(fn ($b) => $b['kind'] === 'tiers' ? $b['team']['name'] : null)->filter()->values();
    $cari = mb_strtolower(implode(' ', array_filter([$card['name'], $card['position'], $card['team_name'], $card['team_code'], ...$dipimpin->all()])));
@endphp
<div class="oc-node">
    <button type="button" class="oc-card {{ $card['is_me'] ? 'is-me' : '' }} {{ $card['role'] === 'leader' || $dipimpin->isNotEmpty() ? 'is-leader' : '' }}"
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
            @if($card['role'] === 'member')<span class="oc-tag">{{ $card['team_name'] }}</span>@endif
            @foreach($dipimpin as $nama)<span class="oc-tag is-lead">Leader {{ $nama }}</span>@endforeach
            <span class="oc-tag is-dup" hidden></span>
        </span>
    </button>

    @if(! empty($card['children']))
    <button type="button" class="oc-toggle" aria-expanded="true" title="Ciutkan / bentangkan di bawah {{ $card['name'] }}">
        <i class="fas fa-minus" aria-hidden="true"></i>
    </button>
    <div class="oc-children">
        @foreach($card['children'] as $blok)
            @include('team._org_block', ['blok' => $blok, 'parentKey' => $card['key']])
        @endforeach
    </div>
    @endif
</div>
