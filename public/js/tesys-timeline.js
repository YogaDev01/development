/* =====================================================================
   Tesys — Timeline riwayat workflow
   ---------------------------------------------------------------------
   Merender daftar status log menjadi timeline: pelaku, waktu,
   perpindahan status, dan catatan.

   Dipakai oleh drawer tinjau Cost Request, dan siap dipakai ulang oleh
   Purchase Order / Invoice / Budgeting tanpa menyalin markup.

   Bentuk data yang diharapkan (persis seperti yang sudah dikembalikan
   endpoint detail-json masing-masing modul):

     [{ action_label, date, actor, notes,
        status_from_label, status_to_label }]

   Urutan masukan boleh terbaru-dulu (bawaan endpoint) — kirim
   { terbaruDulu: true } dan fungsi ini yang membalikkannya, supaya tiap
   pemanggil tidak mengulang keputusan urutan.

   API:
     TesysTimeline.render(logs, opsi) -> string HTML
     TesysTimeline.into(el, logs, opsi)
   ===================================================================== */
(function () {
    'use strict';

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function kosong(pesan) {
        return '<div class="ts-empty ts-empty-compact">'
             + '<p class="ts-empty-msg">' + esc(pesan || 'Belum ada riwayat.') + '</p></div>';
    }

    /*
      Nada satu kejadian diambil dari action_color yang SUDAH dikirim endpoint
      (RequestStatusLog::getActionColorAttribute), bukan ditebak dari teksnya.
      Yang perlu menonjol hanya dua: penolakan dan permintaan revisi — dua
      kejadian yang menuntut orang menindaklanjuti sesuatu.
    */
    function nada(warna) {
        if (warna === 'danger') return ' is-danger';
        if (warna === 'warning') return ' is-warning';
        return '';
    }

    function render(logs, opsi) {
        opsi = opsi || {};
        if (!logs || !logs.length) return kosong(opsi.pesanKosong);

        // Dibalik agar terbaca sebagai perjalanan dari awal ke keadaan
        // sekarang; penanda "terbaru" karena itu menempel pada item terakhir.
        var urut = opsi.terbaruDulu === false ? logs.slice() : logs.slice().reverse();

        var html = '<ul class="ts-timeline">';
        urut.forEach(function (l, i) {
            var transisi = (l.status_from_label && l.status_to_label)
                ? esc(l.status_from_label) + ' → ' + esc(l.status_to_label)
                : '';

            html += '<li class="ts-tl-item' + (i === urut.length - 1 ? ' is-latest' : '') + nada(l.action_color) + '">'
                 +    '<div class="ts-tl-head">'
                 +      '<span class="ts-tl-action">' + esc(l.action_label) + '</span>'
                 +      '<span class="ts-tl-time">' + esc(l.date) + '</span>'
                 +    '</div>'
                 +    '<div class="ts-tl-actor">'
                 +      esc(l.actor || 'System')
                 +      (transisi ? ' · ' + transisi : '')
                 +    '</div>'
                 +    (l.notes ? '<div class="ts-tl-note">' + esc(l.notes) + '</div>' : '')
                 +  '</li>';
        });
        return html + '</ul>';
    }

    function into(el, logs, opsi) {
        var node = typeof el === 'string' ? document.querySelector(el) : el;
        if (node) node.innerHTML = render(logs, opsi);
        return node;
    }

    window.TesysTimeline = { render: render, into: into };
})();
