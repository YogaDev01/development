{{--
    Tombol team di Leader View: membuka team itu di mode Detail Member.
    Variabel: $teams (daftar ['id', 'name', 'count']).
--}}
<div class="oc-teams">
    @foreach($teams as $t)
    <button type="button" class="oc-teamlink" id="team-{{ $t['id'] }}" data-team="{{ $t['id'] }}" title="Leader {{ $t['name'] }}: lihat anggota team">
        {{ $t['name'] }} <span>{{ $t['count'] }}</span>
    </button>
    @endforeach
</div>
