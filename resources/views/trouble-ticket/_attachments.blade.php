{{-- Kartu lampiran. Variabel: $items (koleksi TroubleTicketAttachment).
     Gambar tampil sebagai pratinjau (inline=1); lainnya diunduh. --}}
@if($items->isNotEmpty())
    <div class="tt-att">
        @foreach($items as $a)
            @if($a->is_image)
                <a href="{{ $a->url }}?inline=1" target="_blank" rel="noopener" title="{{ $a->original_name }} ({{ $a->size_label }})" style="padding:2px;">
                    <img src="{{ $a->url }}?inline=1" alt="{{ $a->original_name }}" loading="lazy">
                </a>
            @else
                <a href="{{ $a->url }}{{ $a->is_inline ? '?inline=1' : '' }}" @if($a->is_inline) target="_blank" rel="noopener" @endif title="{{ $a->original_name }}">
                    <i class="fas {{ $a->mime_type === 'application/pdf' ? 'fa-file-pdf' : ($a->is_inline ? 'fa-file-video' : 'fa-file-download') }}" aria-hidden="true"></i>
                    <span>{{ $a->original_name }}</span>
                    <small class="text-muted">{{ $a->size_label }}</small>
                </a>
            @endif
        @endforeach
    </div>
@endif
