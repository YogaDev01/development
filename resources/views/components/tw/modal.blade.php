{{--
    <x-tw.modal> — modal Bootstrap 4 dengan isi Tailwind (prefix tw).

    Kerangka Bootstrap DIPERTAHANKAN persis supaya JS yang ada tetap bekerja
    (data-toggle="modal", $('#id').modal('show'), shown.bs.modal di
    tesys-ui.js, hide.bs.modal di tesys-searchable-select.js):
      div.modal.fade[tabindex=-1][role=dialog][aria-hidden=true][aria-labelledby]
        > div.modal-dialog[role=document] (+ modal-sm|modal-lg|modal-xl)
          > div.modal-content
    Komponen TIDAK membuka/menutup modal sendiri.

    Isi .modal-content memakai tiga <section> (kepala, badan, kaki) ber-kelas tw. SENGAJA tanpa
    .modal-header/.modal-body/.modal-footer/.close (aturan legacy .modal*
    bentrok) dan tanpa div anak langsung .modal-content (compat.css:297 mengubah
    div anak menjadi kolom flex di layar pendek). Bukan <header>/<footer>: di luar
    <section>/<article>/<main> keduanya terpetakan sebagai landmark banner/contentinfo
    (terukur di Chromium, butir G4-landmark); <section> tanpa nama = generic.

    Props:
      id    : WAJIB — id elemen .modal (juga dasar id judul: {id}-title)
      size  : sm | lg | xl | (kosong = ukuran normal)

    Slot:
      title   : judul (disusun dalam span role="heading" aria-level="2")
      default : isi
      footer  : <x-slot:footer> opsional (tombol aksi, rata kanan)

    Atribut lain (data-backdrop, data-keyboard, class tambahan, ...) diteruskan
    ke div.modal. Tombol tutup: button[data-dismiss=modal][aria-label=Tutup].

    TIDAK menambah z-index/zoom (tesys-ui-shell.css yang mengatur). Radius dan
    bayangan .modal-content tetap legacy (shell.css:634 !important 12px).

    Sufiks ! yang ada:
      flex! pada section kepala/kaki : reboot BS header,footer,section{display:block}
                                 tanpa layer menang atas utilitas berlayer.
      rounded-sm! pada tombol tutup : reboot BS button{border-radius:0}
      text-sub!, leading-tight! pada tombol tutup : reboot BS
                                 button{font-size:inherit;line-height:inherit}
      outline-accent-text! (tombol tutup, fokus papan ketik) : warna outline legacy
                                 :focus-visible{outline:2px solid var(--accent)}
                                 (tesys-ui.css:57) dikunci ke accent-text (kontras >= 3:1)

    Hasil ukur (E2b, docs/ui/tailwind-evidence): dialog selebar dialog legacy; backdrop
    penuh viewport di bawah zoom .8; fokus masuk, jebakan fokus dan Escape bawaan
    Bootstrap bekerja; fokus kembali ke pemicu hanya bila dibuka lewat data-toggle
    (perilaku Bootstrap 4, bukan komponen); tinggi <= 700px atau lebar <= 575px: isi panjang
    menggulir di dalam section (aturan legacy compat.css:283-310 memberi
    .modal-content max-height + kolom flex); di viewport lain overlay .modal yang
    menggulir, sama dengan modal legacy. Tombol tutup 40px (di bawah 44px; lolos 24px).
--}}
@props([
    'id',
    'size' => null,
])

@php
    $ukuran = [
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
    ];
    $kelasDialog = trim('modal-dialog ' . ($ukuran[$size] ?? ''));
@endphp

<div {{ $attributes->merge([
    'class'           => 'modal fade',
    'id'              => $id,
    'tabindex'        => '-1',
    'role'            => 'dialog',
    'aria-hidden'     => 'true',
    'aria-labelledby' => $id . '-title',
]) }}>
    <div class="{{ $kelasDialog }}" role="document">
        <div class="modal-content">
            <section class="tw:flex! tw:items-center tw:justify-between tw:gap-3 tw:px-card tw:py-3 tw:border-b tw:border-border">
                <span id="{{ $id }}-title" role="heading" aria-level="2" class="tw:text-section tw:font-section tw:text-text">{{ $title ?? '' }}</span>
                <button type="button" data-dismiss="modal" aria-label="Tutup" class="tw:inline-flex tw:items-center tw:justify-center tw:shrink-0 tw:w-10 tw:h-10 tw:border tw:border-transparent tw:rounded-sm! tw:bg-transparent tw:text-text-dim tw:text-sub! tw:leading-tight! tw:enabled:hover:bg-surface-2 tw:enabled:hover:text-text tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-accent-text!"><span aria-hidden="true">&times;</span></button>
            </section>

            <section class="tw:flex-1 tw:min-h-0 tw:overflow-y-auto tw:px-card tw:py-4 tw:text-data tw:text-text">{{ $slot }}</section>

            @if (isset($footer) && $footer->hasActualContent())
                <section class="tw:flex! tw:flex-wrap tw:items-center tw:justify-end tw:gap-2 tw:px-card tw:py-3 tw:border-t tw:border-border">{{ $footer }}</section>
            @endif
        </div>
    </div>
</div>
