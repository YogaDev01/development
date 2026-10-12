{{-- Satu sel SLA. Variabel: $m (TroubleTicket::slaMetric()), $compact (bool, opsional).
     Angkanya hasil perhitungan server saat halaman dibuka; tidak ada penghitung
     yang berdetak di layar (keputusan 12 Okt 2026). --}}
@php
    // Target SLA tidak ditampilkan lagi (keputusan 12 Okt 2026) — hanya durasinya.
    $compact = $compact ?? false;
@endphp
@if($m['state'] === 'na')
    <span class="tt-sla tt-sla-na" title="Belum berjalan">—</span>
@else
    {{-- Tanpa penghitung berjalan (keputusan 12 Okt 2026): angkanya dihitung
         server saat halaman dibuka, tidak berdetak di layar. --}}
    <span class="tt-sla tt-sla-{{ $m['level'] }}" title="{{ $m['state'] === 'running' ? 'Masih berjalan' : 'Durasi final' }}">
        @if($m['level'] === 'critical')<i class="fas fa-exclamation-circle" aria-hidden="true"></i>@endif
        <span class="tt-sla-val">{{ \App\Models\TroubleTicket::formatDuration($m['seconds']) }}</span>
        @if($m['level'] === 'critical' && ! $compact)<span class="tt-sla-tag">Terlambat</span>@endif
    </span>
@endif
