{{--
    <x-skeleton> — placeholder saat data sedang dimuat.

    Bentuknya mengikuti bentuk konten yang akan menggantikannya, sehingga
    tidak ada lompatan tata letak saat data tiba.

    Props:
      type : 'table' | 'lines' | 'card'  (default 'lines')
      rows : jumlah baris (default 5)
      cols : jumlah kolom, hanya untuk type="table" (default 5)

    Contoh:
      <x-skeleton type="table" :rows="8" :cols="6" />
      <x-skeleton type="lines" :rows="3" />
--}}
@props([
    'type' => 'lines',
    'rows' => 5,
    'cols' => 5,
])

<div {{ $attributes->merge(['class' => 'ts-skeleton ts-skeleton-' . $type]) }}
     role="status" aria-live="polite" aria-label="Memuat data">

    @if($type === 'table')
        @for($r = 0; $r < (int) $rows; $r++)
            <div class="ts-sk-row">
                @for($c = 0; $c < (int) $cols; $c++)
                    <span class="ts-sk-cell"></span>
                @endfor
            </div>
        @endfor

    @elseif($type === 'card')
        <div class="ts-sk-card">
            <span class="ts-sk-line" style="width:42%"></span>
            <span class="ts-sk-line" style="width:88%"></span>
            <span class="ts-sk-line" style="width:70%"></span>
        </div>

    @else
        @for($r = 0; $r < (int) $rows; $r++)
            <span class="ts-sk-line" style="width:{{ [92, 76, 84, 64, 88][$r % 5] }}%"></span>
        @endfor
    @endif

    <span class="sr-only">Memuat data…</span>
</div>
