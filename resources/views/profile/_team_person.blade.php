{{--
    Satu kartu orang di tab Team profil. Gayanya sama dengan bagan organisasi
    (team._org_styles), tetapi bukan tombol karena di sini tidak membuka drawer.
    Variabel: $orang (User), $jabatan, $pimpin (bool), $user (pemilik profil).
--}}
@php $sendiri = (int) $orang->id === (int) $user->id; @endphp
<div class="oc-card {{ $sendiri ? 'is-me' : '' }} {{ $pimpin ? 'is-leader' : '' }}">
    <span class="oc-photo">
        @if($orang->avatar_url)<img src="{{ $orang->avatar_url }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr(trim((string) $orang->name) !== '' ? $orang->name : '?', 0, 2)) }}@endif
    </span>
    <span class="oc-label">
        <strong>{{ $jabatan ?: ($pimpin ? 'Leader' : 'Anggota') }}</strong>
        <span>{{ $orang->name }}</span>
    </span>
    @if($sendiri)
    <span class="oc-tags"><span class="oc-tag is-me">Anda</span></span>
    @endif
</div>
