{{--
    Panel detail satu team di tab Database, lalu panel sub-team-nya (rekursif).
    JS hanya menampilkan panel team terpilih.
    Variabel: $node (TeamController::buildTeamTree), $atas (Team di atasnya / null),
    $denganInduk.
--}}
@php
    $team = $node['team'];
    $bolehEditTeam  = Auth::user()->hasRole('superadmin') || (int) Auth::id() === (int) $team->leader_id;
    $bolehStatusTeam = Auth::user()->hasRole('superadmin');
    $keAktif = $team->status !== 'ACTIVE';
    $anggota = $team->activeMembers->filter(fn ($m) => $m->user)
        ->sortBy(fn ($m) => [(int) $m->user_id === (int) $team->leader_id ? 0 : 1, (int) $m->level, $m->user->name]);
@endphp
<div class="td-panel" id="tdPanel-{{ $team->id }}" hidden>
    <div class="td-box">
        <div class="td-box-head">
            <span class="td-title">{{ $team->name }}</span>
            <span class="badge badge-dark px-2 py-1">{{ $team->code }}</span>
            <span class="badge badge-{{ $team->status_color }} px-2 py-1">{{ $team->status }}</span>
            <div class="td-actions">
                <a href="{{ url('/team/' . $team->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye mr-1"></i> Detail</a>
                @if($bolehEditTeam)
                <a href="{{ url('/team/' . $team->id . '/edit') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen mr-1"></i> Edit</a>
                @endif
                {{-- Team tidak dihapus permanen: riwayat anggotanya ikut hilang.
                     Gantinya Aktifkan / Nonaktifkan. --}}
                @if($bolehStatusTeam)
                <form action="{{ route('team.status', $team->id) }}" method="POST" class="status-form"
                      data-team="{{ $team->name }}" data-aktif="{{ $keAktif ? 1 : 0 }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $keAktif ? 'ACTIVE' : 'INACTIVE' }}">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="fas {{ $keAktif ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-1"></i> {{ $keAktif ? 'Aktifkan' : 'Nonaktifkan' }}
                    </button>
                </form>
                @endif
            </div>
        </div>
        <dl class="td-info">
            <dt>Department</dt><dd>{{ $team->department->name ?? '-' }}</dd>
            <dt>Leader</dt><dd>{{ $team->leader->name ?? '-' }}</dd>
            @if($denganInduk)
            <dt>Induk Team</dt><dd>{{ $team->parent->name ?? '-' }}</dd>
            @endif
            <dt>Di bawah</dt>
            <dd>
                @if($atas)<button type="button" class="td-link" data-team="{{ $atas->id }}">{{ $atas->name }}</button>@else - @endif
            </dd>
            <dt>Sub-team</dt>
            <dd>
                @forelse($node['children'] as $anak)
                    <button type="button" class="td-link" data-team="{{ $anak['team']->id }}">{{ $anak['team']->name }}</button>@if(! $loop->last), @endif
                @empty
                    -
                @endforelse
            </dd>
            <dt>Jumlah orang</dt><dd>{{ $team->active_members_count }} di team ini · {{ count($node['orang']) }} termasuk sub-team</dd>
            <dt>Akses PO</dt><dd>{{ $team->access_group_links_count ? $team->access_group_links_count . ' Access Group' : '-' }}</dd>
        </dl>
    </div>

    <div class="td-box">
        <div class="td-box-head"><span class="td-title">Anggota team ini</span></div>
        @if($anggota->isEmpty())
            <x-empty icon="fas fa-users" title="Belum ada anggota" message="Tambahkan anggota lewat Edit team." compact />
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0" data-ts-card="off">
                <thead class="bg-light">
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Jabatan</th>
                        <th scope="col" class="text-nowrap" style="width:1%;">Level</th>
                        <th scope="col" class="text-nowrap" style="width:1%;">Peran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($anggota as $m)
                    <tr>
                        <td>
                            <div class="td-person">
                                <span class="td-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $m->user->name, 0, 2)) }}</span>
                                <span><strong>{{ $m->user->name }}</strong><span>{{ strtoupper((string) $m->user->username) }}</span></span>
                            </div>
                        </td>
                        <td>{{ $m->position ?: '-' }}</td>
                        <td class="text-center">{{ $m->level }}</td>
                        <td class="text-nowrap">{{ (int) $m->user_id === (int) $team->leader_id ? 'Leader' : 'Anggota' }}@if($m->is_primary ?? false) · Primary @endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@foreach($node['children'] as $anak)
    @include('team._db_panel', ['node' => $anak, 'atas' => $team])
@endforeach
