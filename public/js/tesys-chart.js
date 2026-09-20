/* =====================================================================
   TESYS — Bakuan grafik
   ---------------------------------------------------------------------
   Dua hal, berlaku untuk SELURUH grafik di aplikasi tanpa menyentuh kode
   masing-masing halaman:

     1. Bakuan tampilan — huruf, warna teks, garis bantu, dan tooltip
        mengikuti token design system. Sebelumnya tiap grafik memakai
        bawaan Chart.js (Helvetica abu-abu) yang tidak senada dengan
        halamannya.

     2. Keadaan kosong — saat seluruh nilainya nol atau tidak ada data,
        grafik TIDAK digambar sebagai sumbu kosong. Kanvasnya dibersihkan
        lalu diganti satu kalimat yang menjelaskan keadaannya. Halaman yang
        datanya belum terisi jadi terbaca "memang belum ada", bukan "grafik
        rusak".

   Chart.js dimuat oleh halaman (bukan layout), jadi berkas ini menunggu
   sampai objeknya ada sebelum memasang apa pun.

   Pesan kosong bisa disesuaikan per kanvas lewat atribut:

       <canvas id="chartAging" data-empty-text="Tidak ada invoice overdue."></canvas>
   ===================================================================== */
(function () {
    'use strict';

    /* Warna grafik dibaca dari token CSS, bukan ditulis ulang di sini.
       Dengan begitu grafik ikut berubah bersama tema tanpa perlu ada
       dua daftar warna yang harus dijaga tetap sama. Nilai cadangan di
       parameter kedua dipakai kalau berkas ini terpanggil sebelum
       stylesheet selesai dimuat. */
    function token(nama, cadangan) {
        try {
            var v = getComputedStyle(document.documentElement)
                .getPropertyValue(nama).trim();
            return v || cadangan;
        } catch (e) {
            return cadangan;
        }
    }

    /* Dipublikasikan karena beberapa halaman menyusun palet grafiknya
       sendiri di dalam <script> dan perlu menyelesaikan token juga.

       Ini bukan kenyamanan, melainkan pencegah bug: Chart.js menggambar ke
       <canvas>, dan canvas TIDAK mengenal var(--…). String seperti
       'var(--danger)' yang dikirim apa adanya bukan menghasilkan warna
       salah, melainkan gagal diurai dan jatuh ke hitam. Kegagalannya
       senyap — tidak ada peringatan di konsol.

       tesysWarna menerima token maupun warna biasa, jadi satu daftar
       palet boleh memuat keduanya tanpa perlu dipisah. */
    function tesysWarna(nilai, cadangan) {
        if (typeof nilai !== 'string') return nilai;
        var m = nilai.match(/^var\(\s*(--[\w-]+)\s*(?:,([^)]*))?\)$/);
        if (!m) return nilai;

        /* cadangan DIABAIKAN kalau bukan string, dan itu bukan sekadar
           kehati-hatian. Cara paling wajar memakai fungsi ini adalah
           daftar.map(tesysWarna) — dan Array.map memanggil callback-nya
           dengan (nilai, INDEKS, larik). Jadi parameter kedua menerima
           angka, bukan string.

           Versi pertama memanggil .trim() pada nilai itu. Indeks 0 lolos
           karena falsy, indeks 1 dan seterusnya melempar TypeError, dan
           satu lemparan mematikan SELURUH blok <script> halaman — semua
           grafik hilang, bukan cuma warnanya yang salah. */
        var fb     = typeof cadangan === 'string' ? cadangan.trim() : '';
        var inline = m[2] ? m[2].trim() : '';
        return token(m[1], inline || fb || 'transparent');
    }
    window.tesysWarna = tesysWarna;

    /* Dibaca ULANG lewat fungsi, bukan dibekukan ke dalam variabel saat
       berkas ini dimuat.

       Versi sebelumnya membaca keempat token sekali di awal. Selama tema
       hanya bisa berubah lewat muat ulang halaman, itu cukup. Begitu tema
       bisa ditukar di tempat, grafik yang sudah tergambar tetap memakai
       warna tema lama — sumbu dan garis bantunya nyaris hilang di tema
       gelap, atau menyala di tema terang. */
    function TEKS()       { return token('--text-faint', '#6b7280'); }
    function TEKS_PEKAT() { return token('--text', '#171923'); }
    function GARIS()      { return token('--border', '#e5e7eb'); }
    function PERMUKAAN()  { return token('--surface', '#ffffff'); }
    var HURUF = 'Inter, -apple-system, "Segoe UI", sans-serif';

    /* Bagian yang BERGANTUNG PADA TEMA dipisah supaya bisa dijalankan
       ulang; sisanya (plugin keadaan kosong) cukup sekali. */
    function pasangWarna(Chart) {
        /* ---------------- 1. Bakuan tampilan (Chart.js v2) ---------------- */
        var d = Chart.defaults && Chart.defaults.global;
        if (d) {
            d.defaultFontFamily = HURUF;
            d.defaultFontColor  = TEKS();
            d.defaultFontSize   = 11;

            if (d.legend && d.legend.labels) {
                d.legend.labels.boxWidth = 10;
                d.legend.labels.padding = 12;
                d.legend.labels.usePointStyle = true;
            }

            if (d.tooltips) {
                d.tooltips.backgroundColor = TEKS_PEKAT();
                d.tooltips.titleFontFamily = HURUF;
                d.tooltips.bodyFontFamily  = HURUF;
                d.tooltips.titleFontSize   = 11.5;
                d.tooltips.bodyFontSize    = 11.5;
                d.tooltips.cornerRadius    = 8;
                d.tooltips.xPadding        = 10;
                d.tooltips.yPadding        = 8;
                d.tooltips.displayColors   = true;
                d.tooltips.borderWidth     = 0;
            }

            if (d.elements) {
                if (d.elements.line)  { d.elements.line.borderWidth = 2; d.elements.line.tension = .3; }
                if (d.elements.point) { d.elements.point.radius = 2.5; d.elements.point.hoverRadius = 4; }
                if (d.elements.arc)   { d.elements.arc.borderColor = PERMUKAAN(); d.elements.arc.borderWidth = 2; }
            }
        }

        // Garis bantu tipis dan tanpa garis sumbu tebal.
        var skala = Chart.defaults && Chart.defaults.scale;
        if (skala && skala.gridLines) {
            skala.gridLines.color = GARIS();
            skala.gridLines.drawBorder = false;
            skala.gridLines.lineWidth = 1;
        }

    }

    function pasang(Chart) {
        if (!Chart || Chart.__tesys) return;
        Chart.__tesys = true;

        pasangWarna(Chart);

        /* ---------------- 2. Keadaan kosong ---------------- */
        Chart.plugins.register({
            id: 'tesysEmptyState',

            // Digambar SESUDAH grafiknya, lalu kanvas dibersihkan seluruhnya —
            // termasuk sumbu dan garis bantu yang sudah terlanjur tergambar.
            afterDraw: function (chart) {
                if (!kosong(chart)) return;

                var ctx = chart.ctx;
                var lebar = chart.width;
                var tinggi = chart.height;
                var pesan = (chart.canvas && chart.canvas.getAttribute('data-empty-text'))
                    || 'Tidak ada data pada periode yang dipilih.';

                ctx.save();
                ctx.clearRect(0, 0, lebar, tinggi);

                // Lingkaran ikon
                ctx.beginPath();
                ctx.arc(lebar / 2, tinggi / 2 - 16, 15, 0, Math.PI * 2);
                ctx.fillStyle = token('--surface-3', '#e9ecf2');
                ctx.fill();

                ctx.fillStyle = TEKS;
                ctx.font = '600 13px ' + HURUF;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('—', lebar / 2, tinggi / 2 - 16);

                ctx.fillStyle = TEKS;
                ctx.font = '500 12px ' + HURUF;
                bungkus(ctx, pesan, lebar / 2, tinggi / 2 + 14, lebar - 40, 16);

                ctx.restore();
            }
        });
    }

    /* Kosong = tidak ada dataset, tidak ada angka, atau semuanya nol. */
    function kosong(chart) {
        var data = chart && chart.data;
        if (!data || !data.datasets || !data.datasets.length) return true;

        var adaIsi = false;
        data.datasets.forEach(function (ds) {
            var nilai = ds && ds.data;
            if (!nilai || !nilai.length) return;
            nilai.forEach(function (v) {
                var n = (v && typeof v === 'object') ? (v.y !== undefined ? v.y : v.value) : v;
                if (n !== null && n !== undefined && Number(n) !== 0 && !isNaN(Number(n))) adaIsi = true;
            });
        });
        return !adaIsi;
    }

    /* Teks panjang dipotong per kata supaya tidak terpotong tepi kanvas. */
    function bungkus(ctx, teks, x, y, lebarMaks, tinggiBaris) {
        var kata = String(teks).split(' ');
        var baris = '';
        var barisan = [];

        kata.forEach(function (k) {
            var uji = baris ? baris + ' ' + k : k;
            if (ctx.measureText(uji).width > lebarMaks && baris) {
                barisan.push(baris);
                baris = k;
            } else {
                baris = uji;
            }
        });
        if (baris) barisan.push(baris);

        barisan.slice(0, 3).forEach(function (b, i) {
            ctx.fillText(b, x, y + i * tinggiBaris);
        });
    }

    /*
      Chart.js dimuat oleh halaman (@push('scripts')), yaitu SETELAH berkas ini,
      dan sebagian halaman langsung membuat grafik begitu berkas itu selesai.
      Menunggu DOMContentLoaded karena itu terlambat: bakuan harus sudah
      terpasang sebelum new Chart() pertama.

      Karena bundel Chart.js menulis window.Chart, cukup dititipkan penangkap
      di properti itu — begitu nilainya diisi, bakuan langsung dipasang.
      configurable:true supaya tidak ada yang terkunci kalau pola muatnya
      berubah kelak.
    */
    if (window.Chart) {
        pasang(window.Chart);
    } else {
        var simpan;
        try {
            Object.defineProperty(window, 'Chart', {
                configurable: true,
                enumerable: true,
                get: function () { return simpan; },
                set: function (nilai) { simpan = nilai; pasang(nilai); }
            });
        } catch (e) {
            // Lingkungan yang menolak defineProperty: pakai jaring pengaman.
            document.addEventListener('DOMContentLoaded', function () { pasang(window.Chart); });
        }
    }

    /* ---------------------------------------------------------------
       Tema berganti di tempat -> warna dipasang ulang, lalu setiap
       grafik yang masih hidup digambar ulang.

       Pengalih tema menukar atribut data-theme di <html>; MutationObserver
       memantaunya. Cara ini tidak menuntut pengalihnya tahu bahwa ada
       grafik di halaman — keduanya tetap tidak saling kenal.
       --------------------------------------------------------------- */
    function segarkanGrafik() {
        var C = window.Chart;
        if (!C) return;

        pasangWarna(C);

        /* Chart.js v2 menyimpan instansnya di Chart.instances. */
        var daftar = C.instances || {};
        Object.keys(daftar).forEach(function (k) {
            var g = daftar[k];
            if (!g || !g.chart) return;
            try {
                /* Warna sumbu tersimpan di konfigurasi tiap grafik saat
                   dibuat, jadi tidak cukup mengubah default — nilainya
                   disuntik ulang ke skala yang sudah ada. */
                var op = g.options || {};
                ['xAxes', 'yAxes'].forEach(function (sumbu) {
                    var arr = op.scales && op.scales[sumbu];
                    if (!arr) return;
                    arr.forEach(function (sk) {
                        if (sk.gridLines) sk.gridLines.color = GARIS();
                        if (sk.ticks) sk.ticks.fontColor = TEKS();
                    });
                });
                if (op.legend && op.legend.labels) op.legend.labels.fontColor = TEKS();
                if (op.tooltips) op.tooltips.backgroundColor = TEKS_PEKAT();
                g.update(0);
            } catch (e) { /* satu grafik bermasalah tidak boleh menjatuhkan sisanya */ }
        });
    }

    if (window.MutationObserver) {
        new MutationObserver(function (daftar) {
            for (var i = 0; i < daftar.length; i++) {
                if (daftar[i].attributeName === 'data-theme') { segarkanGrafik(); break; }
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }

})();
