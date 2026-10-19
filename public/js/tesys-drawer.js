/* =====================================================================
   Tesys — Drawer
   ---------------------------------------------------------------------
   Perilaku panel geser kanan untuk komponen <x-drawer>.

   API:
     TesysDrawer.open(id)      buka drawer
     TesysDrawer.close(id)     tutup; tanpa id menutup yang sedang terbuka
     TesysDrawer.isOpen(id)

   Ditutup lewat: tombol silang, klik area gelap, atau tombol Escape.
   Fokus dipindahkan ke dalam panel saat dibuka dan dikembalikan ke elemen
   pemicu saat ditutup, serta ditahan di dalam panel selama terbuka.

   Tidak menyentuh data maupun aturan bisnis — isinya diisi oleh halaman.
   ===================================================================== */
(function () {
    'use strict';

    var terbuka = null;       // elemen .ts-drawer-root yang sedang terbuka
    var pemicuTerakhir = null; // elemen yang membuka drawer, untuk fokus balik

    var BISA_FOKUS = 'a[href], button:not([disabled]), input:not([disabled]),'
        + ' select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function panel(root) { return root.querySelector('.ts-drawer-panel'); }

    /*
      pemicu diterima eksplisit karena document.activeElement tidak selalu
      menunjuk tombol yang membuka drawer — misalnya saat dibuka lewat
      delegasi atau dari kode. Tanpa ini fokus tidak kembali ke tempat
      semula, dan pengguna keyboard kehilangan posisinya di tabel.
    */
    function open(id, pemicu) {
        var root = typeof id === 'string' ? document.getElementById(id) : id;
        if (!root || root === terbuka) return;

        close();

        pemicuTerakhir = pemicu || document.activeElement;

        root.hidden = false;
        // Dipaksa reflow supaya transisi masuk benar-benar berjalan.
        void root.offsetWidth;
        root.classList.add('is-open');
        document.body.classList.add('ts-drawer-lock');

        terbuka = root;

        var p = panel(root);
        if (p) {
            p.setAttribute('tabindex', '-1');
            p.focus({ preventScroll: true });
        }
    }

    function close(id) {
        var root = id ? (typeof id === 'string' ? document.getElementById(id) : id) : terbuka;
        if (!root) return;

        root.classList.remove('is-open');
        document.body.classList.remove('ts-drawer-lock');

        // Disembunyikan setelah animasi keluar selesai.
        window.setTimeout(function () {
            if (!root.classList.contains('is-open')) root.hidden = true;
        }, 260);

        if (terbuka === root) terbuka = null;

        if (pemicuTerakhir && typeof pemicuTerakhir.focus === 'function') {
            pemicuTerakhir.focus({ preventScroll: true });
        }
        pemicuTerakhir = null;
    }

    function isOpen(id) {
        var root = typeof id === 'string' ? document.getElementById(id) : id;
        return !!root && root.classList.contains('is-open');
    }

    /* Tombol silang dan area gelap. */
    document.addEventListener('click', function (e) {
        var tutup = e.target.closest('[data-drawer-close]');
        if (!tutup) return;
        var root = tutup.closest('.ts-drawer-root');
        if (root) close(root);
    });

    document.addEventListener('keydown', function (e) {
        if (!terbuka) return;

        if (e.key === 'Escape') {
            // Viewer lampiran (carousel) yang sedang terbuka menutup dirinya sendiri dulu.
            if (document.querySelector('#imgViewerOverlay.show')) return;
            e.preventDefault();
            close();
            return;
        }

        /* Fokus ditahan di dalam panel selama drawer terbuka. */
        if (e.key === 'Tab') {
            var p = panel(terbuka);
            if (!p) return;

            var isi = Array.prototype.filter.call(
                p.querySelectorAll(BISA_FOKUS),
                function (el) { return el.offsetParent !== null; }
            );
            if (!isi.length) return;

            var pertama = isi[0];
            var terakhir = isi[isi.length - 1];

            if (e.shiftKey && document.activeElement === pertama) {
                e.preventDefault();
                terakhir.focus();
            } else if (!e.shiftKey && document.activeElement === terakhir) {
                e.preventDefault();
                pertama.focus();
            }
        }
    });

    window.TesysDrawer = { open: open, close: close, isOpen: isOpen };
})();
