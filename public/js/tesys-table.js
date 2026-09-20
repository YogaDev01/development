/* =====================================================================
   TESYS — Kendali daftar
   ---------------------------------------------------------------------
   Memindahkan pemilih jumlah baris (Page Size) milik DataTables ke dalam
   bilah kendali di atas tabel, bersama pencarian dan filter.

   Alasannya: "berapa baris yang ditampilkan" adalah kendali daftar, sama
   seperti pencarian dan filter — bukan tombol aksi. Sebelumnya ia berdiri
   sendiri di dekat pagination sehingga kendali daftar tersebar di dua
   tempat berjauhan.

   Elemennya DIPINDAH, bukan dibuat ulang, jadi seluruh event DataTables
   yang menempel padanya tetap bekerja — termasuk saat tabel digambar ulang.

   Halaman yang tidak punya bilah kendali (.custom-toolbar-left) dibiarkan
   apa adanya.
   ===================================================================== */
(function ($) {
    'use strict';

    if (!$) return;

    function pindahkan(tabel) {
        var $wrapper = $(tabel).closest('.dataTables_wrapper');
        if (!$wrapper.length) return;

        var $panjang = $wrapper.find('.dataTables_length').first();
        if (!$panjang.length || $panjang.data('ts-dipindah')) return;

        // Bilah kendali terdekat: di dalam kartu yang sama dengan tabelnya.
        var $bilah = $(tabel).closest('.card, .card-body, .content')
            .find('.custom-toolbar-left').first();

        if (!$bilah.length) return;

        $panjang.data('ts-dipindah', true).appendTo($bilah);
    }

    /*
      Pengikatan dilakukan saat berkas ini dimuat — bukan di dalam
      DOMContentLoaded. Callback $(function(){}) milik halaman berjalan lebih
      dulu daripada listener DOMContentLoaded biasa, sehingga tabelnya sudah
      terlanjur siap sebelum kita sempat mendengarkan.
    */
    $(document).on('init.dt', function (e, settings) {
        pindahkan(settings.nTable);
    });

    // Jaring pengaman untuk tabel yang sudah siap sebelum berkas ini dimuat.
    $(function () {
        $('.dataTables_wrapper table').each(function () { pindahkan(this); });
    });
})(window.jQuery);
