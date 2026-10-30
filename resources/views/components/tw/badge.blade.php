{{--
    <x-tw.badge> — lencana kecil (span) Tailwind, prefix tw.

    Props:
      tone : neutral | attention | progress | positive | done | danger
             (6 nada WorkflowStatus; warna sama dengan .ts-status-* di
             tesys-ui-components.css:47-52) ATAU alias Bootstrap:
             primary, secondary, success, warning, info, dark, light, danger
             yang dipetakan eksplisit ke nada di bawah. Tak dikenal -> neutral.
      size : sm | md (default sm)
      dot  : bool, default false. true -> titik bulat kecil (aria-hidden) di depan teks,
             warnanya mengikuti warna teks nada (bg-current) sehingga tak ada pemetaan
             warna tambahan. Tanpa dot, keluaran sama persis dengan versi sebelumnya.

    Atribut lain (title, id, data-*, aria-*) diteruskan ke <span>. Isi = slot.

    JANGAN menggantikan <x-status> (status workflow, label dari WorkflowStatus)
    maupun badge-{status_color} massal; ini hanya untuk label/penanda umum.

    Pemetaan alias (keputusan awal):
      primary -> progress, info -> progress, success -> positive,
      warning -> attention, secondary/light -> neutral,
      dark -> neutral  [UNKNOWN / NEEDS CONFIRMATION: desain untuk "dark";
      badge-dark legacy tema terang = latar solid --darkbadge + teks putih
      (tesys-ui-compat.css:150, luxe.css:665); nada neutral = abu muda, dan nada done
      = latar --darkbadge-bg + teks --darkbadge (kebalikan badge-dark terang; di tema
      gelap luxe.css:673-676 badge-dark memang memakai pasangan yang sama dengan done)].

    Hasil ukur (E2b, docs/ui/tailwind-evidence): warna/ukuran/bobot sesuai token di 4
    keadaan; kontras teks nada vs latar >= 4,81:1 (terang) dan >= 6,11:1 (gelap), lulus 4,5:1.
    Ukuran sm = 12px (caption), lebih besar dari .ts-status-sm legacy (10,5px): memang
    komponen berbeda (label umum, bukan status workflow).
--}}
@props([
    'tone' => 'neutral',
    'size' => 'sm',
    'dot'  => false,
])

@php
    $alias = [
        'primary'   => 'progress',
        'secondary' => 'neutral',
        'success'   => 'positive',
        'warning'   => 'attention',
        'info'      => 'progress',
        'dark'      => 'neutral',
        'light'     => 'neutral',
    ];

    $nada = [
        'neutral'   => 'tw:bg-surface-2 tw:text-text-dim tw:border-border',
        'attention' => 'tw:bg-warning-bg tw:text-warning tw:border-transparent',
        'progress'  => 'tw:bg-info-bg tw:text-info tw:border-transparent',
        'positive'  => 'tw:bg-success-bg tw:text-success tw:border-transparent',
        'done'      => 'tw:bg-darkbadge-bg tw:text-darkbadge tw:border-transparent',
        'danger'    => 'tw:bg-danger-bg tw:text-danger tw:border-transparent',
    ];

    $ukuran = [
        'sm' => 'tw:px-2 tw:py-1 tw:text-caption',
        'md' => 'tw:px-3 tw:py-1 tw:text-data',
    ];

    $kunci = $alias[$tone] ?? $tone;
    $kunci = isset($nada[$kunci]) ? $kunci : 'neutral';
@endphp

<span {{ $attributes->merge([
    'class' => 'tw:inline-flex tw:items-center tw:border tw:rounded-pill tw:font-heading tw:leading-none tw:whitespace-nowrap '
        . $nada[$kunci] . ' ' . ($ukuran[$size] ?? $ukuran['sm']) . ($dot ? ' tw:gap-2' : ''),
]) }}>@if ($dot)<span aria-hidden="true" class="tw:h-2 tw:w-2 tw:shrink-0 tw:rounded-pill tw:bg-current"></span>@endif{{ $slot }}</span>
