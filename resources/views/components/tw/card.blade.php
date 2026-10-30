{{--
    <x-tw.card> — kartu Tailwind (prefix tw), div MURNI: tanpa kelas .card /
    .card-header / .card-body supaya JS toolbar/icon-button legacy
    (tesys-toolbar-actions, tesys-icon-buttons, tesys-row-actions) dan aturan
    .card legacy tidak aktif tak sengaja.

    Props:
      padding : md | sm | none  (default md) -> --card-pad / --card-pad-sm / 0
                (padding none: pemanggil mengatur jarak isi sendiri)

    Slot:
      header   : <x-slot:header>…</x-slot:header>  (opsional, berpembatas bawah)
      default  : isi
      footer   : <x-slot:footer>…</x-slot:footer>  (opsional, berpembatas atas)

    Atribut lain (id, role, aria-*, data-*, class tambahan) diteruskan ke <div>
    terluar. Kartu berisi toolbar/tabel DataTables dipakai di Cost Request (index);
    halaman lain yang belum dimigrasi tetap memakai .card legacy.

    Radius/bayangan memakai token (radius-md, shadow-xs); tidak ada gradien.

    Header/footer kartu SENGAJA <div>, bukan <header>/<footer>: di luar <section>/<article>/<main>
    elemen itu terpetakan sebagai landmark banner/contentinfo (terukur di Chromium, butir G4-landmark).

    Hasil ukur (E2b, docs/ui/tailwind-evidence): semua properti sama dengan token di 4
    keadaan tanpa !; tidak ada aturan legacy yang mengalahkan (div murni).
    Header memakai ukuran/bobot section; teks header vs latar >= 14:1.
--}}
@props([
    'padding' => 'md',
])

@php
    $badan = [
        'md'   => 'tw:p-card',
        'sm'   => 'tw:p-card-sm',
        'none' => '',
    ];

    $sisi = [
        'md'   => 'tw:px-card',
        'sm'   => 'tw:px-card-sm',
        'none' => 'tw:px-card',
    ];

    $kunci = isset($badan[$padding]) ? $padding : 'md';
@endphp

<div {{ $attributes->merge([
    'class' => 'tw:bg-surface tw:text-text tw:border tw:border-border tw:rounded-md tw:shadow-xs',
]) }}>
    @if (isset($header) && $header->hasActualContent())
        <div class="{{ $sisi[$kunci] }} tw:py-3 tw:border-b tw:border-border tw:text-section tw:font-section tw:text-text">{{ $header }}</div>
    @endif

    <div class="{{ $badan[$kunci] }}">{{ $slot }}</div>

    @if (isset($footer) && $footer->hasActualContent())
        <div class="{{ $sisi[$kunci] }} tw:py-3 tw:border-t tw:border-border tw:bg-surface-2 tw:text-caption tw:text-text-dim tw:rounded-b-md">{{ $footer }}</div>
    @endif
</div>
