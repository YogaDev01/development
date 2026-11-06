{{--
    Tab TEAM — Organization Chart.
    Menampilkan setiap Team tempat user ini jadi member aktif: leader di atas,
    anggota per level di bawahnya, dengan gaya yang sama seperti Detail Member di
    tab Organisasi (team._org_styles). Bila ada, team induk (1 tingkat di atas)
    tampil di atasnya dan sub-team langsung (1 tingkat di bawah) di bawahnya. Kalau user belum tergabung di team
    manapun, tampilkan empty state.
--}}

@if($myTeams->isEmpty())
<div class="empty-state">
    <i class="fas fa-sitemap" style="font-size:26px;"></i>
    <div class="et">Belum tergabung dalam Team</div>
    <div class="ed">Anda belum menjadi member aktif di team manapun. Hubungi Superadmin atau Team Leader untuk ditambahkan.</div>
</div>
@else
@include('team._org_styles')
@push('styles')
<style>
    /* Rangkaian bagan: team induk (atas) → team user → sub-team (bawah). */
    .ot-chain { display: flex; flex-direction: column; }
    .ot-chain + .ot-chain { margin-top: var(--sp-6); padding-top: var(--sp-4); border-top: 1px dashed var(--border); }
    .ot-link { align-self: center; height: 22px; border-left: 2px dashed var(--accent); }
    .ot-rel { font-size: var(--fs-caption); font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 2px; }
    .ot-view.is-related { background: var(--surface-2); }
    .ot-view.is-mine { border-color: var(--accent-tint-2); }
    .ot-subs { display: flex; flex-wrap: wrap; gap: var(--sp-3); }
    .ot-subs > .ot-view { flex: 1 1 320px; min-width: 0; }
</style>
@endpush
@foreach($myTeams as $t)
@php
    $induk = $t->relationLoaded('parent') ? $t->parent : null;
    $subTeams = $t->relationLoaded('children') ? $t->children : collect();
@endphp
<div class="ot-chain">
    @if($induk)
        @include('profile._team_chart', ['t' => $induk, 'peran' => 'induk'])
        <div class="ot-link"></div>
    @endif

    @include('profile._team_chart', ['t' => $t, 'peran' => null])

    @if($subTeams->isNotEmpty())
        <div class="ot-link"></div>
        <div class="ot-subs">
            @foreach($subTeams as $sub)
                @include('profile._team_chart', ['t' => $sub, 'peran' => 'sub'])
            @endforeach
        </div>
    @endif
</div>
@endforeach
@endif
