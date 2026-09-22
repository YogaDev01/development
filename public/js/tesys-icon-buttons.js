/* =====================================================================
   Tesys — penanda tombol ikon di dalam sel tabel
   ---------------------------------------------------------------------
   Di seluruh aplikasi tersebar tombol ikon lepas di dalam sel tabel:
   tautan lampiran, "lihat invoice", "buka budget", hapus baris rincian.
   Tiap halaman menulis ukurannya sendiri — .btn-sm di satu tempat, .btn-xs
   di tempat lain — sehingga tombol yang artinya sama punya tinggi berbeda,
   dan dua tombol bersebelahan menempel tanpa jarak.

   Yang diseragamkan aturannya, bukan markup di empat puluhan tempat. Tapi
   aturan itu hanya boleh kena tombol yang ISINYA MEMANG CUMA IKON: tombol
   "[ikon] Detail" kalau ikut dijadikan kotak 30x30 justru terpotong
   tulisannya.

   CSS tidak bisa membedakan keduanya. Selector :has(> i:only-child)
   sempat dicoba dan salah: :only-child hanya menghitung elemen, teks
   telanjang di sebelah ikon tidak dianggap — jadi "[ikon] Detail" ikut
   tertangkap dan label Detail-nya hilang.

   Karena itu pemeriksaannya dilakukan di sini: sebuah tombol disebut
   ikon-saja kalau textContent-nya kosong setelah dipangkas. Yang lolos
   diberi kelas .ts-icon-only, dan CSS bekerja dari kelas itu.
   ===================================================================== */
(function () {
    'use strict';

    var PEMILIH = '.table td .btn, .table td .btn-xs';

    function periksa(tombol) {
        /* Sudah pernah diperiksa? Jangan kerjakan dua kali — daftar besar
           bisa berisi ratusan tombol dan tabel dinamis memicu ulang. */
        if (tombol.hasAttribute('data-ts-ib')) return;
        tombol.setAttribute('data-ts-ib', '');

        /* Hanya ikon: tidak ada teks sama sekali, dan memang ada ikonnya.
           Entitas &nbsp; ikut dipangkas — beberapa blade menulisnya
           sebagai pengganjal di dalam tombol. */
        var teks = (tombol.textContent || '').replace(/ /g, ' ').trim();
        var punyaIkon = !!tombol.querySelector('i, svg');

        if (teks === '' && punyaIkon) {
            tombol.classList.add('ts-icon-only');
        }
    }

    function sapu(akar) {
        var simpul = (akar || document).querySelectorAll(PEMILIH);
        for (var i = 0; i < simpul.length; i++) periksa(simpul[i]);
    }

    sapu(document);

    /* Banyak tabel digambar ulang setelah halaman siap — DataTables,
       penyaring kolom, dan beberapa daftar yang diisi lewat fetch. Tanpa
       pengamat ini, baris hasil gambar ulang kembali ke ukuran lamanya.

       Pengamatnya sengaja hanya melihat penambahan simpul dan menyapu
       cabang yang baru saja masuk, bukan seluruh dokumen. */
    if (window.MutationObserver) {
        var pengamat = new MutationObserver(function (daftar) {
            for (var i = 0; i < daftar.length; i++) {
                var tambah = daftar[i].addedNodes;
                for (var j = 0; j < tambah.length; j++) {
                    var n = tambah[j];
                    if (n.nodeType !== 1) continue;
                    if (n.matches && n.matches(PEMILIH)) periksa(n);
                    if (n.querySelectorAll) sapu(n);
                }
            }
        });
        pengamat.observe(document.body, { childList: true, subtree: true });
    }

    /* Dibuka supaya halaman yang menggambar tabelnya sendiri bisa memanggil
       ulang tanpa menunggu pengamat. */
    window.tesysTandaiTombolIkon = sapu;
})();
