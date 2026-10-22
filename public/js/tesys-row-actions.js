/* =====================================================================
   Tesys — menu aksi baris (.ts-action-more)
   ---------------------------------------------------------------------
   Membuka menu "lainnya" pada kolom aksi tabel.

   Kenapa tidak memakai dropdown Bootstrap: kolom aksi berada di dalam
   pembungkus ber-overflow (mis. .cr-hscroll pada Cost Request), sehingga
   menu yang menempel di dalam baris akan terpotong. Menu di sini
   dipindahkan ke <body> saat dibuka dan diposisikan fixed — pola yang sama
   dengan tesys-column-filter.js dan tesys-searchable-select.js.

   Seluruh handler aksi di halaman memakai delegasi dari document, jadi
   memindahkan simpul menu tidak memutus satu pun handler.

   Markup yang diharapkan:
     <div class="ts-actions">
        <button class="ts-action-primary">…</button>
        <button class="ts-action-more" aria-haspopup="true" aria-expanded="false">⋮</button>
        <div class="ts-action-menu dropdown-menu" hidden> … </div>
     </div>
   ===================================================================== */
(function () {
    'use strict';

    var JARAK = 6;          // jarak menu dari tombol
    var menuAktif = null;   // menu yang sedang terbuka
    var tombolAktif = null;
    var sarangAsli = null;  // tempat menu sebelum dipindah, untuk dikembalikan

    function tutup() {
        if (!menuAktif) return;

        menuAktif.hidden = true;
        menuAktif.classList.remove('ts-action-menu-open');
        menuAktif.style.cssText = '';

        // Dikembalikan ke tempat asalnya supaya struktur baris tetap utuh.
        if (sarangAsli) sarangAsli.appendChild(menuAktif);

        if (tombolAktif) tombolAktif.setAttribute('aria-expanded', 'false');

        menuAktif = null;
        tombolAktif = null;
        sarangAsli = null;
    }

    function buka(tombol) {
        var menu = tombol.parentElement.querySelector('.ts-action-menu');
        if (!menu) return;

        tutup();

        sarangAsli = menu.parentElement;
        document.body.appendChild(menu);

        menu.hidden = false;
        menu.classList.add('ts-action-menu-open');
        menu.style.position = 'fixed';
        menu.style.zIndex = '1080';
        menu.style.display = 'block';
        menu.style.visibility = 'hidden';

        // Diukur setelah tampil supaya tingginya terbaca.
        var t = tombol.getBoundingClientRect();
        var m = menu.getBoundingClientRect();

        var kiri = t.right - m.width;                 // rata kanan dengan tombol
        var atas = t.bottom + JARAK;

        // Balik ke atas tombol bila ruang di bawah tidak cukup.
        if (atas + m.height > window.innerHeight - 8) {
            atas = Math.max(8, t.top - m.height - JARAK);
        }
        kiri = Math.min(Math.max(8, kiri), window.innerWidth - m.width - 8);

        // Koordinat rect = koordinat layar; style memakai px ber-`zoom`
        // (skala 80% di tesys-ui-shell.css), jadi dibagi skalanya.
        var z = parseFloat(getComputedStyle(document.documentElement).zoom) || 1;
        menu.style.left = Math.round(kiri / z) + 'px';
        menu.style.top = Math.round(atas / z) + 'px';
        menu.style.visibility = 'visible';

        tombol.setAttribute('aria-expanded', 'true');

        menuAktif = menu;
        tombolAktif = tombol;
    }

    document.addEventListener('click', function (e) {
        var tombol = e.target.closest('.ts-action-more');

        if (tombol) {
            e.preventDefault();
            e.stopPropagation();
            if (menuAktif && tombolAktif === tombol) { tutup(); } else { buka(tombol); }
            return;
        }

        // Klik di dalam menu dibiarkan (agar tautan/tombolnya bekerja), lalu
        // menu ditutup pada langkah berikutnya.
        if (menuAktif && menuAktif.contains(e.target)) {
            setTimeout(tutup, 0);
            return;
        }

        tutup();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && menuAktif) {
            var t = tombolAktif;
            tutup();
            if (t) t.focus();
        }
    });

    // Posisi fixed tidak ikut bergeser saat halaman atau tabel digulir.
    window.addEventListener('scroll', tutup, true);
    window.addEventListener('resize', tutup);
})();
