@php
    $map = [
        'success' => ['bg' => 'var(--success-bg)', 'fg' => 'var(--success)', 'ic' => '✓'],
        'error'   => ['bg' => 'var(--danger-bg)',  'fg' => 'var(--danger)',  'ic' => '!'],
        'warning' => ['bg' => 'var(--warning-bg)', 'fg' => 'var(--warning)', 'ic' => '!'],
        'info'    => ['bg' => 'var(--info-bg)',    'fg' => 'var(--info)',    'ic' => 'i'],
    ];
    $s = $map[$type] ?? $map['info'];
@endphp
<div class="card" style="display:flex;align-items:center;gap:11px;border-color:{{ $s['fg'] }}20;">
    <div class="list-ic" style="background:{{ $s['bg'] }};color:{{ $s['fg'] }};font-weight:700;">{{ $s['ic'] }}</div>
    <div style="flex:1;font-size:12.5px;">{{ $message }}</div>
</div>
