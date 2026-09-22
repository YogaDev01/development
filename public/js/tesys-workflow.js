/* =====================================================================
   TESYS — Perjalanan dokumen (workflow flow)
   ---------------------------------------------------------------------
   Menggambar posisi sebuah dokumen di dalam alurnya: tahap yang sudah
   dilewati, tahap sekarang, tahap yang belum, dan keadaan berhenti
   (ditolak / dibatalkan).

   SATU renderer untuk dua tempat. Halaman biasa memakai komponen Blade
   <x-workflow-flow> yang hanya mencetak wadah kosong

       <div class="ts-flow" data-ts-flow="budget-request" data-ts-status="PAID">

   lalu diisi di sini; drawer yang datanya datang belakangan memanggil
   TesysWorkflow.render() langsung. Dengan begitu markup-nya tidak ditulis
   dua kali dan tidak bisa berbeda antara halaman dan drawer.

   Definisi tahapnya datang dari App\Support\WorkflowStatus (PHP) dan
   didaftarkan oleh komponen Blade:

       TesysWorkflow.define('budget-request', [{key, label}, ...]);

   API:
     TesysWorkflow.define(konteks, tahap)
     TesysWorkflow.render(konteks, status)   -> string HTML
     TesysWorkflow.into(el, konteks, status)
     TesysWorkflow.hydrate(akar)             -> isi semua [data-ts-flow]
   ===================================================================== */
(function () {
    'use strict';

    var ALUR = {};                                   // konteks -> [{key,label}]
    var MATI = ['REJECTED', 'CANCELLED'];            // berhenti, bukan tahap

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function define(konteks, tahap) {
        if (konteks && Array.isArray(tahap) && tahap.length) ALUR[konteks] = tahap;
    }

    function indeksStatus(tahap, status) {
        for (var i = 0; i < tahap.length; i++) {
            if (tahap[i].key === status) return i;
        }
        return -1;
    }

    function render(konteks, status) {
        var tahap = ALUR[konteks];
        if (!tahap || !tahap.length) return '';

        var i = indeksStatus(tahap, status);
        var berhenti = MATI.indexOf(status) !== -1;

        // Status di luar peta (mis. OVERDUE pada invoice) sengaja tidak
        // digambar: menebak posisinya lebih menyesatkan daripada diam.
        if (i === -1 && !berhenti) return '';

        var ruas = '';
        tahap.forEach(function (t, n) {
            var keadaan = berhenti ? 'is-stopped'
                : (n < i ? 'is-done' : (n === i ? 'is-current' : ''));
            ruas += '<span class="ts-flow-seg ' + keadaan + '" title="' + esc(t.label) + '"></span>';
        });

        var keterangan = berhenti
            ? '<span class="ts-flow-stop">Perjalanan dokumen berhenti di sini</span>'
            : '<span>Tahap <strong>' + (i + 1) + '</strong> dari ' + tahap.length + '</span>'
              + (tahap[i + 1] ? '<span>Berikutnya: ' + esc(tahap[i + 1].label) + '</span>' : '<span>Tahap akhir</span>');

        return '<div class="ts-flow' + (berhenti ? ' is-off' : '') + '" role="img"'
             + ' aria-label="' + esc(berhenti ? 'Dokumen berhenti' : 'Tahap ' + (i + 1) + ' dari ' + tahap.length) + '">'
             +   '<div class="ts-flow-track">' + ruas + '</div>'
             +   '<div class="ts-flow-legend">' + keterangan + '</div>'
             + '</div>';
    }

    function into(el, konteks, status) {
        var node = typeof el === 'string' ? document.querySelector(el) : el;
        if (node) node.innerHTML = render(konteks, status);
        return node;
    }

    // Wadah yang dicetak komponen Blade. Dipanggil sekali saat halaman siap;
    // aman dipanggil ulang untuk potongan halaman yang baru dimuat.
    function hydrate(akar) {
        var lingkup = akar || document;
        lingkup.querySelectorAll('[data-ts-flow]').forEach(function (el) {
            el.innerHTML = render(el.getAttribute('data-ts-flow'), el.getAttribute('data-ts-status'));
        });
    }

    window.TesysWorkflow = { define: define, render: render, into: into, hydrate: hydrate };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { hydrate(); });
    } else {
        hydrate();
    }
})();
