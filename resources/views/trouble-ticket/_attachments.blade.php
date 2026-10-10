{{-- Lampiran satu pesan. Variabel: $items (koleksi TroubleTicketAttachment).
     Gambar: grid thumbnail persegi seragam (maks. 4 per baris); lebih dari
     8 gambar → 7 tampil + petak "+N" yang membuka sisanya. Berkas lain:
     baris ringkas (ikon · nama · ukuran). Pratinjau = inline, lainnya diunduh. --}}
@if($items->isNotEmpty())
    @php
        $gambar = $items->filter(fn ($a) => $a->is_image)->values();
        $berkas = $items->reject(fn ($a) => $a->is_image)->values();
        $batas  = $gambar->count() > 8 ? 7 : 8;
    @endphp
    <div class="tt-att">
        @if($gambar->isNotEmpty())
            <div class="tt-att-grid tt-att-n{{ min($gambar->count(), 4) }}">
                @foreach($gambar as $a)
                    <a href="{{ $a->url }}?inline=1" target="_blank" rel="noopener"
                       class="tt-att-thumb {{ $loop->index >= $batas ? 'is-more' : '' }}"
                       title="{{ $a->original_name }} ({{ $a->size_label }})">
                        <img src="{{ $a->url }}?inline=1" alt="{{ $a->original_name }}" loading="lazy">
                    </a>
                @endforeach
                @if($gambar->count() > $batas)
                    <button type="button" class="tt-att-thumb tt-att-moretile" title="Tampilkan semua gambar">+{{ $gambar->count() - $batas }}</button>
                @endif
            </div>
        @endif
        @foreach($berkas as $a)
            <a href="{{ $a->url }}{{ $a->is_inline ? '?inline=1' : '' }}" @if($a->is_inline) target="_blank" rel="noopener" @endif class="tt-att-file" title="{{ $a->original_name }}">
                <span class="tt-att-ic"><i class="fas {{ $a->mime_type === 'application/pdf' ? 'fa-file-pdf' : ($a->is_inline ? 'fa-file-video' : 'fa-file-alt') }}" aria-hidden="true"></i></span>
                <span class="tt-att-name">{{ $a->original_name }}</span>
                <small class="tt-att-size">{{ $a->size_label }}</small>
                <i class="fas {{ $a->is_inline ? 'fa-external-link-alt' : 'fa-download' }} tt-att-go" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif
