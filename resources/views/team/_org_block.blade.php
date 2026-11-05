{{--
    Satu blok di bawah kartu: 'tiers' (anggota sebuah team per baris level)
    atau 'leader' (team yang diinduk, lengkap dengan kartu leader-nya).
    Variabel: $blok, $parentKey (kunci kartu di atasnya).
--}}
@if($blok['kind'] === 'leader')
    @include('team._org_card', ['card' => $blok['card'], 'up' => 'card:' . $parentKey])
@else
    @php $rowBase = $parentKey . '-t' . $blok['team']['id']; @endphp
    <div class="oc-block" id="team-{{ $blok['team']['id'] }}">
        <a href="{{ url('/team/' . $blok['team']['id']) }}" class="oc-team-tag" title="Buka detail team {{ $blok['team']['name'] }}">
            {{ $blok['team']['name'] }} <span>{{ $blok['team']['code'] }}@if($blok['team']['department']) · {{ $blok['team']['department'] }}@endif</span>
        </a>
        @forelse($blok['rows'] as $i => $row)
        <div class="oc-row" data-row="{{ $rowBase }}-r{{ $i }}">
            @foreach($row as $c)
                @include('team._org_card', ['card' => $c, 'up' => $i === 0 ? 'card:' . $parentKey : 'row:' . $rowBase . '-r' . ($i - 1)])
            @endforeach
        </div>
        @empty
        <div class="oc-empty">Belum ada anggota selain leader.</div>
        @endforelse
    </div>
@endif
