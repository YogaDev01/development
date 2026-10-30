{{--
    <x-tw.stat> — ubin ringkasan angka Tailwind (prefix tw): label kecil kapital dengan titik
    warna, angka besar, petunjuk opsional. Hanya <div>/<span>.

    Props:
      label : label singkat (WAJIB; di-escape)
      value : angka/teks nilai (di-escape; pemanggil memformat)
      tone  : accent | neutral | attention | progress | positive | done | danger
              (warna titik; 6 nada = WorkflowStatus seperti x-tw.badge; accent = warna merek).
              Tak dikenal -> neutral.
      hint  : keterangan satu baris di bawah angka; kosong = tanpa

    Atribut lain (id, class, data-*, title) diteruskan ke <div> terluar. Komponen tidak
    menghitung apa pun: angka datang dari pemanggil (mis. dihitung di Blade dari data yang
    sudah ada). Titik hanya dekorasi (aria-hidden); makna ada pada label teks.

    Kontras: label text-dim, angka text di atas surface (>= 6,79:1 terukur di galeri F2).
--}}
@props([
    'label' => '',
    'value' => 0,
    'tone'  => 'neutral',
    'hint'  => null,
])

@php
    $titik = [
        'accent'    => 'tw:bg-accent',
        'neutral'   => 'tw:bg-text-faint',
        'attention' => 'tw:bg-warning',
        'progress'  => 'tw:bg-info',
        'positive'  => 'tw:bg-success',
        'done'      => 'tw:bg-darkbadge',
        'danger'    => 'tw:bg-danger',
    ];
    $kunci = isset($titik[$tone]) ? $tone : 'neutral';
@endphp

<div {{ $attributes->merge([
    'class' => 'tw:min-w-0 tw:bg-surface tw:border tw:border-border tw:rounded-md tw:px-4 tw:py-3',
]) }}>
    <div class="tw:flex tw:items-center tw:gap-2 tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">
        <span aria-hidden="true" class="tw:h-2 tw:w-2 tw:shrink-0 tw:rounded-pill {{ $titik[$kunci] }}"></span>
        <span class="tw:min-w-0">{{ $label }}</span>
    </div>
    <div class="tw:mt-1 tw:text-kpi tw:font-heading tw:leading-tight tw:text-text">{{ $value }}</div>
    @if (filled($hint))
        <div class="tw:mt-1 tw:text-caption tw:text-text-dim">{{ $hint }}</div>
    @endif
</div>
