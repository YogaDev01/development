{{-- $groups dari config, $activeSlug opsional --}}
<nav class="pnd-side">
    <a href="{{ route('panduan.index') }}" class="{{ empty($activeSlug) ? 'active' : '' }}">
        <i class="fas fa-home"></i> Beranda Panduan
    </a>
    @foreach($groups as $group)
        <div class="pnd-side-group">{{ $group['label'] }}</div>
        @foreach($group['topics'] as $t)
            <a href="{{ route('panduan.show', $t['slug']) }}"
               class="{{ ($activeSlug ?? null) === $t['slug'] ? 'active' : '' }}">
                <i class="fas {{ $t['icon'] }}"></i> {{ $t['title'] }}
            </a>
        @endforeach
    @endforeach
</nav>
