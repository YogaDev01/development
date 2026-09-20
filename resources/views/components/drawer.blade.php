{{--
    <x-drawer> — panel geser dari kanan untuk meninjau sebuah record.

    Dipakai untuk meninjau, bukan untuk mengubah data: daftar di belakangnya
    tetap terlihat, sehingga memeriksa sepuluh pengajuan berturut-turut tidak
    perlu sepuluh kali buka-tutup halaman. Form panjang dan tabel lebar tetap
    memakai halaman penuh; konfirmasi tetap memakai modal.

    Isi drawer diisi oleh JavaScript halaman (lihat public/js/tesys-drawer.js),
    jadi komponen ini hanya menyediakan kerangkanya.

    Props:
      id       : id unik drawer, dipakai TesysDrawer.open('<id>')
      title    : judul awal (biasanya ditimpa saat data dimuat)
      width    : lebar maksimum panel, default 560px
      label    : teks pembaca layar untuk panel

    Slot:
      default  : isi badan drawer
      footer   : baris aksi di kaki drawer (opsional)

    Contoh:
      <x-drawer id="crDetail" title="Detail Cost Request">
          <div id="crDrawerBody"></div>
          <x-slot:footer>
              <button class="ts-action-primary"><span class="ts-al">Approve</span></button>
          </x-slot:footer>
      </x-drawer>
--}}
@props([
    'id'    => 'drawer',
    'title' => '',
    'width' => '560px',
    'label' => 'Panel detail',
])

<div class="ts-drawer-root" id="{{ $id }}" data-drawer hidden>

    <div class="ts-drawer-scrim" data-drawer-close></div>

    <aside class="ts-drawer-panel" style="--drawer-w: {{ $width }}"
           role="dialog" aria-modal="true" aria-label="{{ $label }}"
           aria-labelledby="{{ $id }}-title">

        <header class="ts-drawer-head">
            <div class="ts-drawer-heading">
                <p class="ts-drawer-eyebrow" id="{{ $id }}-eyebrow"></p>
                <h2 class="ts-drawer-title" id="{{ $id }}-title">{{ $title }}</h2>
            </div>
            <button type="button" class="ts-drawer-close" data-drawer-close
                    aria-label="Tutup panel">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="ts-drawer-body" id="{{ $id }}-body">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="ts-drawer-foot" id="{{ $id }}-foot">{{ $footer }}</footer>
        @endisset

    </aside>
</div>
