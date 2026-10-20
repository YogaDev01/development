/* =====================================================================
   Tesys — mode card untuk DataTable (layar <= 640px)
   ---------------------------------------------------------------------
   Pemasangan otomatis pada tabel DataTable yang LAYAK. Skrip ini tidak
   mengganti DOM tabel dan tidak menyentuh inisialisasi per halaman; ia
   hanya memberi kelas + data-label supaya CSS (tesys-ui-datatable.css)
   bisa menampilkan baris sebagai card. <table> tidak pernah disembunyikan
   (pencarian topbar memakai dataTable.tables({visible:true})) dan teks sel
   tidak diubah (tesys-money-sort.js).

   Layak bila: thead SATU baris, tanpa colspan/rowspan > 1, tanpa baris
   grup, dan tidak ber-data-ts-card="off" (juga tidak di dalam elemen
   ber-atribut itu). Tabel pivot/grup otomatis dikecualikan.

   Atribut opsional di Blade:
     <table data-ts-card="off">     paksa tidak jadi card
     <th data-short="Nama">         label ringkas di card (default: teks th)
     <th data-card="title">         paksa kolom ini jadi judul card
     <th data-card="hide">          sembunyikan kolom ini di card
     <th data-card="show">          tampilkan kolom "No" yang biasanya disembunyikan
     <div data-ts-card-scope>        tabel biasa (bukan DataTable) di dalamnya ikut
                                     mode card (kecuali di modal/drawer)
   Kelas yang dipasang: ts-card-table (tabel); ts-card-group (baris grup
   ber-colspan); ts-card-title, ts-card-status, ts-card-actions, ts-card-hide,
   ts-card-check, data-label (sel).

   Label mengikuti kolom TERLIHAT: th dan td dipasangkan menurut posisi DOM
   (kolom yang dibuang DataTables lewat visible:false / toggle kolom tidak ada
   di DOM; kolom yang disembunyikan Responsive tetap ada dan tetap sejajar).

   tfoot yang berisi teks dirender sebagai kartu ringkasan di atas daftar.
   Batasan: sel tbody ber-colspan di tengah baris tidak didukung (dilewati,
   tanpa label).
   ===================================================================== */
(function ($) {
    'use strict';
    if (!$ || !$.fn || !$.fn.dataTable) return;
    if (window.TesysTable) return;   // cegah listener ganda bila skrip termuat dua kali

    var RE_NO = /^(no\.?|#)$/i;
    var RE_AKSI = /^(aksi|action|actions)$/i;
    var RE_STATUS = /^status$/i;

    function teks(el) { return $.trim($(el).text()).replace(/\s+/g, ' '); }

    function layak(tbl) {
        var $t = $(tbl);
        if ($t.closest('[data-ts-card="off"]').length) return false;
        if ($t.is('.col-pic-table')) return false;
        var $head = $t.children('thead').children('tr');
        if ($head.length !== 1) return false;
        // DataTables menambahkan colspan="1" rowspan="1" ke setiap th; yang
        // dihitung hanya yang benar-benar menggabung sel (> 1).
        var bertingkat = false;
        $head.children().each(function () {
            if ((parseInt(this.getAttribute('colspan'), 10) || 1) > 1 ||
                (parseInt(this.getAttribute('rowspan'), 10) || 1) > 1) bertingkat = true;
        });
        if (bertingkat) return false;
        if ($t.children('tbody').children('tr.dtrg-group, tr.group').length) return false;
        return true;
    }

    function ringkasan($t, labels) {
        var $foot = $t.children('tfoot').children('tr');
        var baris = [], judul = '';
        $foot.each(function () {
            var idx = 0;
            $(this).children().each(function () {
                var t = teks(this), span = parseInt(this.getAttribute('colspan'), 10) || 1;
                if (t) {
                    var lb = labels[idx] || '';
                    if (idx === 0 && !/\d/.test(t)) judul = judul || t;
                    else baris.push([lb, t]);
                }
                idx += span;
            });
        });
        return { judul: judul, baris: baris };
    }

    function renderRingkasan($t, labels) {
        var $s = $t.prev('.ts-card-summary');
        var r = ringkasan($t, labels);
        if (!r.baris.length) { $s.remove(); return; }
        if (!$s.length) $s = $('<div class="ts-card-summary"></div>').insertBefore($t);
        var $n = $('<div></div>');
        $('<p class="ts-card-summary-title"></p>').text(r.judul || 'Ringkasan').appendTo($n);
        r.baris.forEach(function (b) {
            var $row = $('<div class="ts-card-summary-row"></div>');
            $('<span></span>').text(b[0]).appendTo($row);
            $('<span></span>').text(b[1]).appendTo($row);
            $n.append($row);
        });
        $s.empty().append($n.children());
    }

    // Hapus semua jejak mode card (tabel tak lagi layak / dikecualikan).
    function bersihkan($t) {
        $t.removeClass('ts-card-table');
        $t.prev('.ts-card-summary').remove();
        $t.children('tbody').children('tr').removeClass('ts-card-group')
            .children('td')
            .removeClass('ts-card-hide ts-card-title ts-card-status ts-card-actions ts-card-check')
            .removeAttr('data-label');
    }

    function pasang(tbl) {
        var $t = $(tbl);
        if (!layak(tbl)) { bersihkan($t); return; }

        var $ths = $t.children('thead').children('tr').children('th');
        var labels = [], hide = {}, judulIdx = -1, aksiIdx = {}, statusIdx = -1;
        $ths.each(function (i) {
            var $h = $(this), mode = $h.attr('data-card');
            labels[i] = $h.attr('data-short') || teks(this);
            // Nomor urut tidak berguna di card; data-card="show" untuk menampilkannya.
            if (mode === 'hide' || (mode !== 'show' && RE_NO.test(labels[i]))) hide[i] = true;
            if (mode === 'title' && judulIdx < 0) judulIdx = i;
            if (RE_AKSI.test(labels[i]) || $h.hasClass('action-column')) aksiIdx[i] = true;
            if (statusIdx < 0 && RE_STATUS.test(labels[i])) statusIdx = i;
        });
        if (judulIdx < 0) {
            for (var i = 0; i < labels.length; i++) {
                if (labels[i] && !hide[i] && !aksiIdx[i] && i !== statusIdx) { judulIdx = i; break; }
            }
        }

        $t.addClass('ts-card-table');
        $t.children('tbody').children('tr').each(function () {
            var $tr = $(this);
            // Baris grup (sel ber-colspan, mis. baris PO di Todo List): posisi sel
            // tidak lagi sejajar dengan header, jadi tanpa label — tampil sebagai
            // kepala kelompok. Baris "tidak ada data" DataTables punya CSS sendiri.
            var grup = $tr.children('td[colspan]').filter(function () {
                return (parseInt(this.getAttribute('colspan'), 10) || 1) > 1 && !$(this).hasClass('dataTables_empty');
            }).length > 0;
            $tr.toggleClass('ts-card-group', grup);
            if (grup) {
                $tr.children('td').removeAttr('data-label')
                    .removeClass('ts-card-title ts-card-status ts-card-actions ts-card-hide ts-card-check');
                return;
            }
            $tr.children('td').each(function (i) {
                if (this.hasAttribute('colspan')) return;
                var $c = $(this);
                // Sel yang isinya hanya checkbox pilih-baris: ditaruh di pojok kiri atas card.
                $c.toggleClass('ts-card-check', $c.children('input[type="checkbox"]').length === 1 && teks(this) === '');
                // Kolom aksi juga dikenali dari isinya (tombol/menu aksi), bukan hanya header.
                var aksi = !!aksiIdx[i] || $c.find('.ts-actions, .action-buttons').length > 0;
                $c.attr('data-label', labels[i] || '');
                $c.toggleClass('ts-card-title', i === judulIdx);
                $c.toggleClass('ts-card-status', i === statusIdx && i !== judulIdx && !aksi);
                $c.toggleClass('ts-card-actions', aksi);
                $c.toggleClass('ts-card-hide', !!hide[i]);
            });
        });
        renderRingkasan($t, labels);

        // tfoot sering dihitung ulang skrip halaman sesudah 'draw'.
        var foot = $t.children('tfoot')[0];
        if (foot && !foot._tsObs && window.MutationObserver) {
            var tm;
            foot._tsObs = new MutationObserver(function () {
                clearTimeout(tm);
                tm = setTimeout(function () { renderRingkasan($t, labels); }, 50);
            });
            foot._tsObs.observe(foot, { childList: true, characterData: true, subtree: true });
        }
    }

    // Satu jadwal per tabel per frame, supaya beberapa event beruntun tidak mengulang kerja.
    function jadwalkan(tbl) {
        if (!tbl || tbl._tsAntri) return;
        tbl._tsAntri = true;
        var jalan = function () { tbl._tsAntri = false; pasang(tbl); };
        if (window.requestAnimationFrame) window.requestAnimationFrame(jalan); else setTimeout(jalan, 0);
    }

    $(document).on('init.dt draw.dt column-visibility.dt responsive-display.dt', function (e, settings) {
        if (settings && settings.nTable) jadwalkan(settings.nTable);
    });
    // Tabel di tab yang semula tersembunyi.
    $(document).on('shown.bs.tab', function () {
        $('table.dataTable').each(function () { jadwalkan(this); });
    });
    $(function () { $('table.dataTable').each(function () { jadwalkan(this); }); });

    /*
     | Tabel biasa (bukan DataTable) di area konten halaman juga ikut mode card:
     |  - semua tabel di dalam elemen ber-atribut data-ts-card-scope, dan
     |  - tabel daftar di .main .content (otomatis).
     | Tidak ikut: tabel di modal & drawer (rincian, bukan daftar), tabel di
     | dalam <form> atau berisi isian (baris yang diedit tetap tabel), tabel
     | bersarang di dalam tabel lain (ikut induknya lewat pemanggilan manual),
     | dan tabel yang tidak layak (header bertingkat, pivot, data-ts-card="off").
     */
    var SELEKTOR_LUAR = '.modal, .todo-drawer, .ts-drawer-root, form';
    function tabelScope() {
        return $('[data-ts-card-scope] table, .main .content table').not('table.dataTable')
            .filter(function () {
                var $t = $(this);
                if ($t.closest(SELEKTOR_LUAR).length) return false;
                if ($t.parents('table').length) return false;
                // Isian tersembunyi (token form hapus di kolom aksi) bukan isian baris.
                if ($t.find('input:not([type="checkbox"]):not([type="hidden"]), select, textarea').length) return false;
                return true;
            });
    }

    // Isi tabel yang diganti skrip halaman (AJAX, filter ulang) dipasang ulang.
    function amati(tbl) {
        var body = tbl.tBodies && tbl.tBodies[0];
        if (!body || body._tsObs || !window.MutationObserver) return;
        body._tsObs = new MutationObserver(function () { jadwalkan(tbl); });
        body._tsObs.observe(body, { childList: true });
    }
    function pindai() {
        tabelScope().each(function () { amati(this); jadwalkan(this); });
    }
    $(pindai);
    $(document).on('shown.bs.tab', pindai);

    window.TesysTable = { pasang: pasang, layak: layak };
})(window.jQuery);
