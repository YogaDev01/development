/* =====================================================================
   Tesys — Salin Kode (label info)
   ---------------------------------------------------------------------
   Nilai di samping label "Kode" / "Code" (mis. "Kode", "Kode PO",
   "Batch Code") di drawer, modal detail, dan halaman show bisa diklik
   untuk disalin ke clipboard. Berlaku di semua halaman tanpa mengubah
   markup tiap modul: pasangan label → nilai dikenali dari strukturnya,
   termasuk yang baru dirender lewat AJAX (drawer/modal detail).

   Pasangan yang dikenali (label = elemen daun berteks "Kode…/…Code"):
     <dt>Kode</dt><dd>…</dd>                 (ts-dw-kv, definition list)
     <tr><th>Kode</th><td>…</td></tr>        (tabel info; BUKAN kepala kolom)
     <span class="…-label">Kode</span><span class="…-value">…</span>
   Tidak disentuh: kepala kolom (<thead>), label form (<label for>), dan
   nilai yang berisi kendali (input, tombol, tautan) — itu bukan teks kode.

   Isi nilai dibungkus <span class="ts-copy-kv"> supaya sorotan hanya
   selebar teksnya. Setelah diklik, teksnya sendiri berganti "Tersalin ✓"
   selama 2 detik lalu kembali — tanpa toast.

   Kelasnya sengaja BUKAN .ts-copy-code: kelas itu milik fitur salin kode
   di tabel (tesys-ui.js, dengan toast) dan gayanya sendiri.

   Hanya menyalin teks; tidak menyentuh data maupun aturan bisnis.
   ===================================================================== */
(function () {
    'use strict';

    var LABEL = /^(kode|code)(\s+[a-z.]+)?\s*:?$|^[a-z.]+\s+(kode|code)\s*:?$/i;
    var KANDIDAT = 'dt, th, [class*="label"]';
    var INTERAKTIF = 'input, select, textarea, button, a, [contenteditable]';
    var LAMA_MS = 2000;

    function teksBersih(el) {
        return (el.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function nilaiDari(label) {
        if (label.closest('thead')) return null;                       // kepala kolom tabel
        if (label.tagName === 'LABEL' && label.htmlFor) return null;    // label form
        if (label.querySelector('*:not(i):not(small):not(span)')) return null; // bukan elemen daun
        if (!LABEL.test(teksBersih(label))) return null;

        var v = label.nextElementSibling;
        if (!v || v.tagName === 'TH' || v.matches(KANDIDAT + ', label')) return null;
        if (label.tagName === 'DT' && v.tagName !== 'DD') return null;
        if (v.matches(INTERAKTIF) || v.querySelector(INTERAKTIF)) return null;
        return v;
    }

    /* Bungkus isi nilai dengan span inline (sekali; dibungkus ulang bila
       isinya diganti JS halaman, mis. drawer dibuka untuk record lain). */
    function tandai(v) {
        var t = teksBersih(v);
        if (!t || t === '-') return;
        var anak = v.firstElementChild;
        if (anak && anak.classList.contains('ts-copy-kv') && v.childNodes.length === 1) return;

        var span = document.createElement('span');
        span.className = 'ts-copy-kv';
        span.setAttribute('role', 'button');
        span.setAttribute('tabindex', '0');
        span.title = 'Klik untuk menyalin';
        while (v.firstChild) span.appendChild(v.firstChild);
        v.appendChild(span);
    }

    function pindai(akar) {
        var daftar = (akar || document).querySelectorAll(KANDIDAT);
        for (var i = 0; i < daftar.length; i++) {
            var v = nilaiDari(daftar[i]);
            if (v) tandai(v);
        }
    }

    /* Isi drawer/modal diisi JS setelah halaman dimuat. Pemindaian ulang
       ditunda sebentar dan digabung, jadi rentetan perubahan DOM (mis. tabel
       digambar ulang) hanya memicu satu pindaian. setTimeout, bukan
       requestAnimationFrame: rAF berhenti di tab latar, sehingga isi yang
       tiba saat tab tidak aktif tidak akan pernah dipindai. */
    var dijadwalkan = false;
    function jadwalkan() {
        if (dijadwalkan) return;
        dijadwalkan = true;
        setTimeout(function () {
            dijadwalkan = false;
            pindai(document);
        }, 60);
    }

    function salin(teks, selesai) {
        // Clipboard API bisa ditolak (izin/konteks tidak aman) — cara lama jadi cadangan.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(teks).then(selesai, function () { salinLama(teks, selesai); });
            return;
        }
        salinLama(teks, selesai);
    }
    function salinLama(teks, selesai) {
        var t = document.createElement('textarea');
        t.value = teks;
        t.setAttribute('readonly', '');
        t.style.position = 'fixed';
        t.style.opacity = '0';
        document.body.appendChild(t);
        t.select();
        try { if (document.execCommand('copy')) selesai(); } catch (e) {}
        document.body.removeChild(t);
    }

    /* Umpan balik di tempat: teks nilai berganti "Tersalin ✓" lalu kembali.
       Isi aslinya (bisa berisi badge) disimpan dan dipulihkan apa adanya. */
    function tandaiTersalin(el) {
        if (el.classList.contains('is-copied')) return;
        var asli = el.innerHTML;
        el.style.minWidth = el.offsetWidth + 'px';
        el.classList.add('is-copied');
        el.textContent = 'Tersalin ✓';
        setTimeout(function () {
            el.innerHTML = asli;
            el.classList.remove('is-copied');
            el.style.minWidth = '';
        }, LAMA_MS);
    }

    function aktifkan(el) {
        if (el.classList.contains('is-copied')) return;
        var teks = teksBersih(el);
        if (!teks || teks === '-') return;
        salin(teks, function () { tandaiTersalin(el); });
    }

    /* Fase capture: klik pada nilai kode tidak ikut membuka baris/kartu
       induknya (mis. baris tabel yang membuka detail saat diklik). */
    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('.ts-copy-kv');
        if (!el) return;
        var sel = window.getSelection && String(window.getSelection());
        if (sel && sel.length) return; // sedang memblok teks: biarkan
        e.preventDefault();
        e.stopPropagation();
        aktifkan(el);
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var el = e.target.closest && e.target.closest('.ts-copy-kv');
        if (!el || el !== e.target) return;
        e.preventDefault();
        e.stopPropagation();
        aktifkan(el);
    }, true);

    function mulai() {
        pindai(document);
        if (window.MutationObserver) {
            new MutationObserver(jadwalkan).observe(document.body, { childList: true, subtree: true });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
    else mulai();

    window.TesysCopyKv = { scan: pindai };
})();
