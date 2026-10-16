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
            /* Pembaca layar butuh nama; pakai title bila ada. Tidak menimpa
               aria-label yang sudah ditulis halaman. */
            var judul = tombol.getAttribute('title');
            if (judul && !tombol.hasAttribute('aria-label') && !tombol.hasAttribute('aria-labelledby')) {
                tombol.setAttribute('aria-label', judul);
            }
        }
    }

    /* Kontrol ikon-saja di luar sel tabel (topbar, toolbar, modal, drawer).
       Hanya menambah aria-label; ukurannya tidak disentuh. Bootstrap tooltip
       memindahkan title ke data-original-title, jadi keduanya dibaca. */
    var PEMILIH_LUAR = '.navbar a, .navbar button, .toolbar a, .toolbar button, ' +
        '.modal-header .close, .modal-footer .btn, .modal-body .btn, .card-header .btn, ' +
        '.card-tools .btn, .dataTables_wrapper .btn, [data-toggle="tooltip"]';

    function labelLuar(el) {
        if (el.hasAttribute('data-ts-al')) return;
        if (el.hasAttribute('aria-label') || el.hasAttribute('aria-labelledby')) return;
        if ((el.textContent || '').replace(/ /g, ' ').trim() !== '') return;
        if (!el.querySelector('i, svg') && !el.classList.contains('close')) return;
        var nama = el.getAttribute('title') || el.getAttribute('data-original-title');
        if (!nama && el.classList.contains('close')) nama = 'Tutup';
        /* Ditandai hanya setelah label terpasang; elemen yang title-nya
           baru muncul belakangan tetap bisa dilabeli pada sapuan berikutnya. */
        if (nama) {
            el.setAttribute('aria-label', nama);
            el.setAttribute('data-ts-al', '');
        }
    }

    function sapu(akar) {
        var induk = akar || document;
        var simpul = induk.querySelectorAll(PEMILIH);
        for (var i = 0; i < simpul.length; i++) periksa(simpul[i]);
        var luar = induk.querySelectorAll(PEMILIH_LUAR);
        for (var k = 0; k < luar.length; k++) labelLuar(luar[k]);
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
