/* =====================================================================
   TESYS — Tombol aksi daftar
   ---------------------------------------------------------------------
   Sebagian halaman menaruh tombol "Tambah …" / "Export" di kepala kartu
   (.card-header .ml-auto), jauh di atas bilah kendali daftar. Akibatnya
   satu baris penuh terpakai hanya untuk satu tombol, dan tombol aksi
   tersebar di dua tempat: sebagian di kepala kartu, sebagian di bilah.

   Tombolnya DIPINDAH (bukan dibuat ulang) ke sisi kanan bilah kendali di
   kartu yang sama, sejajar dengan Cari/Filter/Kolom — seperti Cost Request.
   Karena elemennya sama, seluruh event yang menempel padanya tetap bekerja.

   Kartu tanpa bilah kendali dibiarkan apa adanya.
   ===================================================================== */
(function ($) {
    'use strict';

    if (!$) return;

    function pindahkan(kepala) {
        var $kepala = $(kepala);
        var $aksi   = $kepala.children('.ml-auto').first();
        if (!$aksi.length || $aksi.data('ts-dipindah')) return;

        // Hanya kumpulan tombol. Kepala kartu yang berisi kendali lain
        // (pemilih tahun, checkbox, ringkasan) dibiarkan di tempatnya.
        var $isi = $aksi.children();
        if (!$isi.length) return;
        var semuaTombol = $isi.toArray().every(function (el) {
            return $(el).is('a.btn, button.btn, .btn-group, .dropdown');
        });
        if (!semuaTombol) return;

        var $bilah = $kepala.closest('.card').find('.custom-toolbar').first();
        if (!$bilah.length) return;

        var $kanan = $bilah.children('.custom-toolbar-right').first();
        if (!$kanan.length) {
            $kanan = $('<div class="custom-toolbar-right d-flex flex-wrap align-items-center" style="gap:8px;"></div>')
                .appendTo($bilah);
        }

        $aksi.data('ts-dipindah', true);
        $kanan.append($isi);
        $aksi.remove();
    }

    $(function () {
        $('.card-header').each(function () { pindahkan(this); });
    });
})(window.jQuery);
