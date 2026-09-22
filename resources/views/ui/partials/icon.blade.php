@php
    /* Ikon outline inline (tanpa dependensi FontAwesome). Dipakai: @include('ui.partials.icon', ['name' => 'chart']) */
    $paths = [
        'grid'      => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
        'file'      => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h4"/>',
        'bars'      => '<path d="M4 19h16M7 15v-5M12 15V6M17 15v-8"/>',
        'receipt'   => '<path d="M6 3h9l3 3v15H6z"/><path d="M9 9h6M9 13h6M9 17h3"/>',
        'wallet'    => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'clipboard' => '<path d="M9 3h6v4H9z"/><path d="M6 7h12v14H6z"/><path d="M9 12h6M9 16h6"/>',
        'trend'     => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'truck'     => '<path d="M3 6h13v10H3z"/><path d="M16 10h3l2 3v3h-5z"/><circle cx="7" cy="18" r="1.6"/><circle cx="18" cy="18" r="1.6"/>',
        'search'    => '<circle cx="10" cy="10" r="6"/><path d="M20 20l-5.5-5.5"/>',
        'cart'      => '<path d="M3 6h18M6 6l1 13h10l1-13"/><path d="M9 10v5M15 10v5"/>',
        'pulse'     => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'users'     => '<circle cx="9" cy="8" r="3"/><path d="M2 20c0-3.3 3.1-5 7-5s7 1.7 7 5"/><circle cx="18" cy="7" r="2.3"/><path d="M15.5 13c2.8.3 5 1.8 5 4.2"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>',
        'list'      => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'bell'      => '<path d="M18 8a6 6 0 00-12 0c0 5-2 6-2 6h16s-2-1-2-6"/><path d="M10 20a2 2 0 004 0"/>',
    ];
@endphp
<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
    {!! $paths[$name] ?? $paths['file'] !!}
</svg>
