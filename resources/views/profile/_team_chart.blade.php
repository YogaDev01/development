{{--
    Satu bagan team di tab Team profil: leader di atas, anggota per level di
    bawahnya. Variabel: $t (Team, dengan department/leader/activeMembers.user
    sudah di-eager-load), $peran (null | 'induk' | 'sub'), $user (pemilik profil).
--}}
@php
    $barisLeader = $t->activeMembers->first(fn ($m) => (int) $m->user_id === (int) $t->leader_id);
    $tingkat = $t->activeMembers
        ->filter(fn ($m) => $m->user && (int) $m->user_id !== (int) $t->leader_id)
        ->sortBy(fn ($m) => [(int) $m->level, $m->user->name])
        ->groupBy(fn ($m) => (int) $m->level);
@endphp
<div class="ot-view {{ $peran ? 'is-related' : 'is-mine' }}">
    <div class="ot-head">
        <div>
            @if($peran === 'induk')
                <div class="ot-rel"><i class="fas fa-level-up-alt"></i> Team Induk</div>
            @elseif($peran === 'sub')
                <div class="ot-rel"><i class="fas fa-level-down-alt"></i> Sub-team</div>
            @endif
            <div class="ot-title">
                {{ $t->name }} <span class="os-code">{{ $t->code }}</span>
                <span class="badge badge-{{ $t->status_color }} ml-1">{{ $t->status }}</span>
            </div>
            <div class="ot-sub">{{ $t->department->name ?? 'Tanpa department' }} · {{ $t->activeMembers->count() }} orang</div>
        </div>
    </div>

    <div class="ot-body">
        @if($t->leader)
            @include('profile._team_person', ['orang' => $t->leader, 'jabatan' => $barisLeader?->position, 'pimpin' => true])
        @else
            <div class="oc-empty">Team ini belum punya leader.</div>
        @endif

        @forelse($tingkat as $level => $anggota)
        <div class="ot-tier">
            <div class="ot-tier-label">Level {{ $level }} · {{ $anggota->count() }} orang</div>
            <div class="ot-cards">
                @foreach($anggota as $m)
                    @include('profile._team_person', ['orang' => $m->user, 'jabatan' => $m->position, 'pimpin' => false])
                @endforeach
            </div>
        </div>
        @empty
        <div class="oc-empty">Belum ada anggota selain leader.</div>
        @endforelse
    </div>
</div>
