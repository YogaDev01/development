{{--
    <x-empty> — keadaan kosong yang menjelaskan sebab dan memberi jalan keluar.

    Menggantikan pola "Tidak ada data" polos. Tombol lanjutan hanya muncul
    bila pemanggil mengisi slot `action` — pengecekan permission tetap
    dilakukan di halaman pemanggil, bukan di sini.

    Props:
      title   : judul singkat (wajib diisi agar tidak generik)
      message : satu kalimat penjelas (opsional)
      icon    : kelas Font Awesome (default folder terbuka)
      compact : true untuk ruang sempit, mis. di dalam drawer

    Contoh:
      <x-empty title="Belum ada Cost Request"
               message="Belum ada pengajuan yang cocok dengan filter saat ini.">
          <x-slot:action>
              <button class="btn btn-secondary btn-sm" id="crResetFilter">Reset filter</button>
          </x-slot:action>
      </x-empty>
--}}
@props([
    'title'   => 'Belum ada data',
    'message' => null,
    'icon'    => 'fas fa-folder-open',
    'compact' => false,
])

<div {{ $attributes->merge(['class' => 'ts-empty' . ($compact ? ' ts-empty-compact' : '')]) }}>
    <span class="ts-empty-ic" aria-hidden="true"><i class="{{ $icon }}"></i></span>
    <p class="ts-empty-title">{{ $title }}</p>
    @if($message)
        <p class="ts-empty-msg">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="ts-empty-action">{{ $action }}</div>
    @endisset
</div>
