{{--
    Baris komparasi bulan sebelumnya di kaki KPI card.
    Param:
      $prevLabel   string
      $prevHasData bool
      salah satu dari:
        $pair  ['qty' => metric, 'amount' => metric]   — kartu qty+nominal
        $m     metric                                   — kartu tunggal (Collection Rate)
--}}
@php
    $deltaTxt = function ($mm) use ($prevHasData) {
        if (!$prevHasData)            return 'n/a';
        if ($mm['pp'] !== null)       return ($mm['pp'] > 0 ? '+' : '') . $mm['pp'] . ' poin';
        if ($mm['pct'] === null)      return 'baru';
        $p = rtrim(rtrim(number_format($mm['pct'], 1, ',', '.'), '0'), ',');
        return ($mm['pct'] > 0 ? '+' : '') . $p . '%';
    };
    $cls = fn ($mm) => (!$prevHasData || $mm['good'] === null) ? 'neu' : ($mm['good'] ? 'up' : 'down');
    $ico = fn ($mm) => $mm['dir'] === 'up' ? 'fa-caret-up' : ($mm['dir'] === 'down' ? 'fa-caret-down' : 'fa-minus');

    $items = isset($pair)
        ? [['Qty', $pair['qty']], ['Nilai', $pair['amount']]]
        : [[null, $m]];
@endphp
<div class="col-kpi-compare">
    @foreach($items as [$lbl, $mm])
    <span class="col-kpi-cmp-item col-kpi-cmp-{{ $cls($mm) }}">
        @if($lbl)<span class="col-kpi-cmp-lbl">{{ $lbl }}</span>@endif
        <i class="fas {{ $prevHasData ? $ico($mm) : 'fa-minus' }}"></i>{{ $deltaTxt($mm) }}
    </span>
    @endforeach
    <span class="col-kpi-compare-ref">vs {{ $prevLabel }}</span>
</div>
