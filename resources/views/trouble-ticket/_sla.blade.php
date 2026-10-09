{{-- Satu sel SLA. Variabel: $m (TroubleTicket::slaMetric()), $compact (bool, opsional).
     Timer berjalan diperbarui tiap detik oleh JS halaman lewat data-since
     (jam server, ISO 8601) — tampilan ini tidak pernah memulai/mengubah timer. --}}
@php
    $compact = $compact ?? false;
    $judul = $m['target'] ? 'Target ' . \App\Models\TroubleTicket::formatDuration($m['target'] * 60) : 'Target belum diatur';
@endphp
@if($m['state'] === 'na')
    <span class="tt-sla tt-sla-na" title="Belum berjalan">—</span>
@else
    <span class="tt-sla tt-sla-{{ $m['level'] }} {{ $m['state'] === 'running' ? 'is-running' : '' }}"
          @if($m['state'] === 'running') data-since="{{ $m['since']->toIso8601String() }}" data-target="{{ $m['target'] ? $m['target'] * 60 : '' }}" @endif
          title="{{ $judul }}{{ $m['state'] === 'running' ? ' · berjalan' : '' }}">
        @if($m['level'] === 'critical')<i class="fas fa-exclamation-circle" aria-hidden="true"></i>@elseif($m['state'] === 'running')<i class="far fa-clock" aria-hidden="true"></i>@endif
        <span class="tt-sla-val">{{ \App\Models\TroubleTicket::formatDuration($m['seconds']) }}</span>
        @if($m['level'] === 'critical' && ! $compact)<span class="tt-sla-tag">Terlambat</span>@endif
    </span>
@endif
