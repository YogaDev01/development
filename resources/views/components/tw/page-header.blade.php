{{--
    <x-tw.page-header> — kepala halaman Tailwind (prefix tw): eyebrow kecil, judul,
    deskripsi satu kalimat, dan aksi di kanan. Hanya <div>/<span> (tanpa h1-h6/p/a)
    supaya aturan tag legacy tidak mengalahkan kelas tw.

    Props:
      eyebrow     : teks kecil kapital di atas judul (mis. "General · Feedback"); kosong = tanpa
      title       : judul (WAJIB; di-escape)
      description : satu kalimat penjelas abu-abu; kosong = tanpa

    Slot:
      actions : <x-slot:actions>…</x-slot:actions> — tombol (x-tw.button); primer di akhir
                supaya berada paling kanan. Kosong = tanpa wadah aksi.

    Atribut lain (id, class, data-*) diteruskan ke <div> terluar.

    JUDUL = <div role="heading" aria-level="2">, BUKAN <h1>: shell (tesys-shell.js
    hoistPageTitle) memindahkan <h1> pertama di konten ke topbar lalu MENGHAPUSNYA dari
    konten. Judul topbar tetap datang dari <h1> yang ditulis halaman (disembunyikan
    secara visual dengan tw:sr-only di dalam wadah yang ikut terhapus); kepala ini
    menampilkan judul bagian yang tidak boleh sama dengan judul topbar.

    Ukuran judul memakai tw:text-title (turunan --fs-page, lihat masukan build); tanpa hex,
    tanpa gradien/animasi. Mobile: aksi turun di bawah judul (flex-wrap).
--}}
@props([
    'eyebrow'     => null,
    'title'       => '',
    'description' => null,
])

<div {{ $attributes->merge([
    'class' => 'tw:flex tw:flex-wrap tw:items-end tw:justify-between tw:gap-4 tw:pb-4 tw:border-b tw:border-border',
]) }}>
    <div class="tw:min-w-0 tw:flex-1 tw:max-sm:basis-full">
        @if (filled($eyebrow))
            <div class="tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">{{ $eyebrow }}</div>
        @endif
        <div role="heading" aria-level="2" class="tw:mt-1 tw:text-title tw:font-heading tw:leading-tight tw:text-text">{{ $title }}</div>
        @if (filled($description))
            <div class="tw:mt-1 tw:text-body tw:leading-body tw:text-text-dim">{{ $description }}</div>
        @endif
    </div>

    @if (isset($actions) && $actions->hasActualContent())
        <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2">{{ $actions }}</div>
    @endif
</div>
