/* =====================================================================
   Tesys — Kolom tabel bisa diseret (resize), versi bersama
   ---------------------------------------------------------------------
   Pola yang sama dengan daftar Cost Request: handle di tepi kanan setiap
   <th data-col="…">, lebar disimpan di localStorage per kunci, klik dua
   kali handle = kembali ke lebar bawaan. Hanya berlaku >= 641px (mode
   kartu mobile tidak disentuh). Tabelnya memakai table-layout: fixed
   supaya lebar yang diseret benar-benar dipakai.

   API:
     TesysColResize.init(tableEl, kunciPenyimpanan)

   <html> memakai zoom: ukuran getBoundingClientRect adalah piksel layar,
   sedangkan nilai CSS adalah piksel ter-zoom — keduanya dibagi zoom.
   ===================================================================== */
(function () {
    'use strict';

    var MIN = 48;

    function init(tbl, kunci) {
        if (!tbl || !tbl.id || tbl.getAttribute('data-col-resize') === '1') return;
        tbl.setAttribute('data-col-resize', '1');

        var id = tbl.id;
        var styleEl = document.createElement('style');
        document.head.appendChild(styleEl);

        function zoom() { return parseFloat(getComputedStyle(document.documentElement).zoom) || 1; }
        function ths() { return Array.prototype.slice.call(tbl.querySelectorAll('thead th')); }
        function lebar(th) { return th.getBoundingClientRect().width / zoom(); }

        // Kolom ber-data-col masuk peta; sisanya (centang/aksi/kolom tersembunyi) ke "tetap".
        function ukur() {
            var peta = {}, tetap = 0;
            ths().forEach(function (th) {
                var k = th.getAttribute('data-col');
                if (k) peta[k] = lebar(th); else tetap += lebar(th);
            });
            return { peta: peta, tetap: tetap };
        }
        function lukis(peta, tetap) {
            var total = tetap, aturan = '';
            Object.keys(peta).forEach(function (k) {
                total += peta[k];
                aturan += 'body.tesys-ui table#' + id + ' th[data-col="' + k + '"]{width:' + Math.round(peta[k]) + 'px !important;}';
            });
            aturan += 'body.tesys-ui table#' + id + '{width:' + Math.round(total) + 'px !important;min-width:0 !important;}';
            styleEl.textContent = '@media (min-width: 641px){' + aturan + '}';
        }
        function simpan(peta) {
            try { localStorage.setItem(kunci, JSON.stringify(peta)); } catch (e) {}
        }
        function reset() {
            styleEl.textContent = '';
            try { localStorage.removeItem(kunci); } catch (e) {}
        }

        var tersimpan = null;
        try { tersimpan = JSON.parse(localStorage.getItem(kunci) || 'null'); } catch (e) {}
        if (tersimpan && typeof tersimpan === 'object') {
            var u0 = ukur();
            Object.keys(u0.peta).forEach(function (k) {
                var w = parseFloat(tersimpan[k]);
                if (w >= MIN) u0.peta[k] = w;
            });
            lukis(u0.peta, u0.tetap);
        }

        ths().forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (!k) return;
            var h = document.createElement('span');
            h.className = 'ts-resizer';
            h.title = 'Seret untuk mengubah lebar • klik dua kali untuk mengembalikan';
            th.appendChild(h);

            h.addEventListener('click', function (e) { e.stopPropagation(); });   // jangan memicu sortir
            h.addEventListener('dblclick', function (e) { e.stopPropagation(); reset(); });
            h.addEventListener('pointerdown', function (e) {
                e.preventDefault(); e.stopPropagation();
                var z = zoom(), u = ukur(), x0 = e.clientX, w0 = u.peta[k];
                h.setPointerCapture(e.pointerId);
                h.classList.add('is-active');
                function gerak(ev) {
                    u.peta[k] = Math.max(MIN, w0 + (ev.clientX - x0) / z);
                    lukis(u.peta, u.tetap);
                }
                function lepas() {
                    h.removeEventListener('pointermove', gerak);
                    h.removeEventListener('pointerup', lepas);
                    h.removeEventListener('pointercancel', lepas);
                    h.classList.remove('is-active');
                    simpan(u.peta);
                }
                h.addEventListener('pointermove', gerak);
                h.addEventListener('pointerup', lepas);
                h.addEventListener('pointercancel', lepas);
            });
        });
    }

    window.TesysColResize = { init: init };
})();
