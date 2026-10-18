/*
|--------------------------------------------------------------------------
| TesysDateRange
|--------------------------------------------------------------------------
| Pemilih rentang tanggal "From / To" — satu kotak teks yang membuka popup
| berisi DUA kalender inline berdampingan:
|
|   - kalender kiri memilih tanggal awal, kalender kanan tanggal akhir;
|   - masing-masing bernavigasi sendiri, jadi bulannya boleh tidak berurutan,
|     namun bulan kiri tidak boleh melewati bulan kanan;
|   - label bulan & tahun adalah tombol yang membuka daftar bercentang
|     dengan kotak pencarian (lompat jauh tanpa menekan panah berkali-kali);
|   - hanya tanggal milik bulan itu yang tampil, dan tanggal terpilih
|     ditandai lingkaran.
|
| Nilainya disimpan di dua input tersembunyi (format Y-m-d) supaya alur yang
| sudah ada — tombol "Terapkan", submit form, atau reload — tidak berubah.
|
| Pemakaian:
|   TesysDateRange.init({
|       input: '#crDateRange',   // kotak teks yang ditampilkan (readonly)
|       from:  '#crDateFrom',    // input hidden tanggal awal (Y-m-d)
|       to:    '#crDateTo',      // input hidden tanggal akhir (Y-m-d)
|       onApply: function (from, to) { ... }   // opsional
|   });
|
| applyButton (opsional): { label, onClick(from, to) } — menambah tombol di dalam
| popup (di bawah kedua kalender). Klik menutup popup lalu memanggil onClick.
| Dipakai halaman yang menerapkan filter lewat tombol, bukan tiap tanggal berubah.
|
| onApply dipanggil tiap kali kedua tanggal berubah. Modul yang punya tombol
| "Terapkan" sendiri cukup mengabaikannya dan membaca input hidden-nya.
|
| Mengembalikan objek { open, close, setRange } atau null bila flatpickr
| tidak tersedia (pemanggil menyiapkan fallback input manual sendiri).
*/
(function () {
    'use strict';

    function p2(n) { return (n < 10 ? '0' : '') + n; }
    function fmtIso(d) { return d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()); }
    function fmtTampil(d) { return p2(d.getDate()) + '/' + p2(d.getMonth() + 1) + '/' + d.getFullYear(); }

    function tutupSemuaPanel(kecuali) {
        $('.tdr-panel').each(function () {
            if (kecuali && this === kecuali) return;
            $(this).remove();
        });
    }

    /* Satu handler dokumen untuk semua instance: panel bulan/tahun tertutup
       saat klik di mana pun selain panel itu sendiri atau tombol pembukanya. */
    $(document).off('mousedown.tdrPanel').on('mousedown.tdrPanel', function (e) {
        var $panel = $(e.target).closest('.tdr-panel');
        var $pick  = $(e.target).closest('.tdr-pick');
        $('.tdr-panel').each(function () {
            if ($panel.length && this === $panel[0]) return;
            if ($pick.length && $(this).data('owner') === $pick[0]) return;
            $(this).remove();
        });
    });

    function init(opts) {
        if (typeof flatpickr !== 'function') return null;

        var $input = $(opts.input);
        var $from  = $(opts.from);
        var $to    = $(opts.to);
        if (!$input.length || !$from.length || !$to.length) return null;

        var fpA, fpB;
        var rangeAktif = '';

        /* ── Pemilih bulan/tahun ─────────────────────────────────────────── */
        function tutupPanel($cal) { $cal.find('.tdr-panel').remove(); }

        function syncPanah() {
            if (!fpA || !fpB) return;
            var sama = idxBulan(fpA) === idxBulan(fpB);
            $(fpA.calendarContainer).find('.flatpickr-next-month').toggleClass('tdr-panah-mati', sama);
            $(fpB.calendarContainer).find('.flatpickr-prev-month').toggleClass('tdr-panah-mati', sama);
        }

        function syncLabel(fp) {
            var $cal = $(fp.calendarContainer);
            tutupPanel($cal);
            syncPanah();
            $cal.find('.flatpickr-current-month').html('')
                .append($('<button type="button" class="tdr-pick" data-jenis="bulan"></button>')
                    .text(fp.l10n.months.longhand[fp.currentMonth]))
                .append($('<button type="button" class="tdr-pick" data-jenis="tahun"></button>')
                    .text(fp.currentYear));
        }

        function idxBulan(fp) { return fp.currentYear * 12 + fp.currentMonth; }

        /* Bulan kalender kiri tidak boleh melewati kalender kanan: sisi yang
           baru digeser ditarik kembali ke posisi sisi lainnya. */
        var kunci = false;
        function jagaUrutan(sisi) {
            if (kunci || !fpA || !fpB) return;
            if (idxBulan(fpA) <= idxBulan(fpB)) return;

            kunci = true;
            var target = sisi === 'a' ? fpB : fpA;
            var geser  = sisi === 'a' ? fpA : fpB;
            geser.changeYear(target.currentYear);
            geser.changeMonth(target.currentMonth, false);
            syncLabel(geser);
            kunci = false;
        }

        function pasangPemilih(fp) {
            var $cal = $(fp.calendarContainer);
            if ($cal.data('tdrPicker')) { syncLabel(fp); return; }
            $cal.data('tdrPicker', true).addClass('tdr-cal-body');

            $cal.on('click', '.tdr-pick', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn  = $(this);
                var jenis = $btn.data('jenis');           // 'bulan' | 'tahun'

                if ($cal.find('.tdr-panel').data('owner') === this) { tutupPanel($cal); return; }
                tutupSemuaPanel();

                var items = [];
                if (jenis === 'bulan') {
                    fp.l10n.months.longhand.forEach(function (nama, i) {
                        items.push({ label: nama, value: i, aktif: i === fp.currentMonth });
                    });
                } else {
                    for (var y = fp.currentYear - 12; y <= fp.currentYear + 12; y++) {
                        items.push({ label: String(y), value: y, aktif: y === fp.currentYear });
                    }
                }

                var $panel = $('<div class="tdr-panel"></div>').data('owner', this);
                /* size="1": tanpa ini lebar bawaan <input> (±20 karakter) ikut
                   menentukan lebar panel yang memakai width:max-content, dan
                   panelnya jadi jauh lebih lebar dari daftar bulan/tahun. */
                var $cari  = $('<input type="text" size="1" class="form-control form-control-sm tdr-cari">')
                    .attr('placeholder', jenis === 'bulan' ? 'Cari...' : 'Cari...');
                var $list  = $('<div class="tdr-list"></div>');

                items.forEach(function (it) {
                    $('<div class="tdr-item"></div>')
                        .toggleClass('is-active', it.aktif)
                        .text(it.label)
                        .data('value', it.value)
                        .appendTo($list);
                });

                $cari.on('input', function () {
                    var q = $(this).val().toLowerCase();
                    $list.children('.tdr-item').each(function () {
                        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
                    });
                });
                $cari.on('keydown', function (e2) { e2.stopPropagation(); });

                $list.on('click', '.tdr-item', function () {
                    var val = $(this).data('value');
                    tutupPanel($cal);
                    if (jenis === 'bulan') { fp.changeMonth(val, false); }
                    else                   { fp.changeYear(val); }
                    syncLabel(fp);
                });

                $panel.append($cari, $list);
                $cal.append($panel);

                /* Posisi dihitung dari selisih offset terhadap container
                   kalender (offsetParent bawaan adalah .flatpickr-month,
                   sehingga panel sisi kanan bisa muncul di kalender kiri),
                   lalu dijepit agar tidak keluar kotak. */
                var oBtn = $btn.offset(), oCal = $cal.offset();
                var kiri = oBtn.left - oCal.left;
                var maks = $cal.outerWidth() - $panel.outerWidth() - 6;
                $panel.css({
                    top:  (oBtn.top - oCal.top + $btn.outerHeight() + 2) + 'px',
                    left: Math.max(6, Math.min(kiri, maks)) + 'px'
                });
                $cari.trigger('focus');
            });

            syncLabel(fp);
        }

        /* ── Popup dua kalender ──────────────────────────────────────────── */
        var $wrap = $input.parent().addClass('tdr-wrap');
        var $popup = $('<div class="tdr-pop d-none"></div>');
        var $sisiA = $('<div class="tdr-cal"><div class="tdr-judul">From</div></div>');
        var $sisiB = $('<div class="tdr-cal"><div class="tdr-judul">To</div></div>');
        $popup.append($sisiA, $sisiB).appendTo($wrap);

        if (opts.applyButton && typeof opts.applyButton.onClick === 'function') {
            var $tombol = $('<button type="button" class="btn btn-primary btn-sm"></button>')
                .append('<i class="fas fa-check mr-1"></i>')
                .append(document.createTextNode(opts.applyButton.label || 'Terapkan'));
            $popup.addClass('tdr-pop-has-foot')
                  .append($('<div class="tdr-foot"></div>').append($tombol));
            $tombol.on('click', function () {
                tutup();
                opts.applyButton.onClick($from.val(), $to.val());
            });
        }

        var tglAwal  = $from.val();
        var tglAkhir = $to.val();
        if (!tglAwal || !tglAkhir) {
            var kini = new Date();
            tglAwal  = tglAwal  || fmtIso(new Date(kini.getFullYear(), kini.getMonth(), 1));
            tglAkhir = tglAkhir || fmtIso(kini);
        }

        function tulisRentang() {
            var a = fpA && fpA.selectedDates[0];
            var b = fpB && fpB.selectedDates[0];
            if (!a || !b) return;
            $from.val(fmtIso(a));
            $to.val(fmtIso(b));
            $input.val(fmtTampil(a) + ' s/d ' + fmtTampil(b));
            if (typeof opts.onApply === 'function') { opts.onApply(fmtIso(a), fmtIso(b), rangeAktif); }
        }

        function opsi(bulanTampil, $sisi, tglTerpilih, sisi) {
            return {
                inline: true, showMonths: 1, dateFormat: 'Y-m-d',
                appendTo: $sisi[0],
                defaultDate: tglTerpilih,
                /* Dipasang di sini, bukan lewat set() setelah init: set()
                   memanggil jumpToDate sehingga grid melompat ke bulan tanggal
                   terpilih sementara label tetap di bulan yang kita atur. */
                maxDate: sisi === 'a' ? tglAkhir : null,
                minDate: sisi === 'b' ? tglAwal  : null,
                onReady: function (sel, str, fp) {
                    fp.changeMonth(bulanTampil.getMonth(), false);
                    fp.changeYear(bulanTampil.getFullYear());
                    pasangPemilih(fp);
                },
                onMonthChange: function (sel, str, fp) { syncLabel(fp); jagaUrutan(sisi); },
                onYearChange:  function (sel, str, fp) { syncLabel(fp); jagaUrutan(sisi); },
                onChange: function (dates) {
                    /* Tanggal awal tidak boleh melewati tanggal akhir. */
                    if (dates[0]) {
                        if (sisi === 'a' && fpB) { fpB.set('minDate', dates[0]); }
                        if (sisi === 'b' && fpA) { fpA.set('maxDate', dates[0]); }
                    }
                    tulisRentang();
                }
            };
        }

        var dAwal  = new Date(tglAwal);
        var dAkhir = new Date(tglAkhir);
        /* Kalender kanan dibuka pada bulan tanggal akhir (tanpa filter = bulan
           berjalan); kalender kiri satu bulan sebelumnya. */
        var bulanKiri = new Date(dAkhir.getFullYear(), dAkhir.getMonth() - 1, 1);

        fpA = flatpickr($('<input type="text" class="tdr-src">').appendTo($sisiA)[0],
                        opsi(bulanKiri, $sisiA, tglAwal, 'a'));
        fpB = flatpickr($('<input type="text" class="tdr-src">').appendTo($sisiB)[0],
                        opsi(dAkhir, $sisiB, tglAkhir, 'b'));
        syncPanah();

        $from.val(tglAwal);
        $to.val(tglAkhir);
        $input.val(fmtTampil(dAwal) + ' s/d ' + fmtTampil(dAkhir));

        function buka()  { $popup.removeClass('d-none'); }
        function tutup() {
            $popup.addClass('d-none');
            tutupPanel($(fpA.calendarContainer));
            tutupPanel($(fpB.calendarContainer));
        }

        /* Pilihan rentang cepat — markup & isinya sama dengan Cost Request.
           Dipasang hanya bila pemanggil meminta (opts.quick).

           Kotak rentang tanggal hanya tampil saat pilihannya "Custom...",
           persis seperti Cost Request. Pilihan yang sedang aktif dibaca dari
           ?range= di URL (atau opts.range) supaya tetap terpilih setelah
           halaman dimuat ulang, dan ikut dikirim lewat input hidden "range"
           untuk modul yang memakai form. */
        if (opts.quick) {
            var pilihan = [
                ['',     'Last Update'],
                ['12h',  '12 jam terakhir'],
                ['1d',   '1 hari terakhir'],
                ['3d',   '3 hari terakhir'],
                ['7d',   '7 hari terakhir'],
                ['30d',  '30 hari terakhir'],
                ['1y',   '1 tahun terakhir'],
                ['custom', 'Custom...']
            ];
            var $quick = $('<select class="form-control form-control-sm tdr-quick"></select>');
            pilihan.forEach(function (o) {
                $quick.append($('<option></option>').attr('value', o[0]).text(o[1]));
            });
            $quick.insertAfter($input);

            var $rangeField = $('<input type="hidden" name="range">').insertAfter($quick);

            rangeAktif = opts.range
                || (new URLSearchParams(window.location.search).get('range') || '');
            $quick.val(rangeAktif);
            $rangeField.val(rangeAktif);
            $input.toggleClass('d-none', rangeAktif !== 'custom');

            $quick.on('change', function () {
                var v = $(this).val();
                rangeAktif = v;
                $rangeField.val(v);
                $input.toggleClass('d-none', v !== 'custom');

                if (v === 'custom') { buka(); return; }
                tutup();
                if (!v) return;

                var kini  = new Date();
                var mulai = new Date(kini.getTime());
                if (v === '12h')      { mulai.setHours(mulai.getHours() - 12); }
                else if (v === '1d')  { mulai.setDate(mulai.getDate() - 1); }
                else if (v === '3d')  { mulai.setDate(mulai.getDate() - 3); }
                else if (v === '7d')  { mulai.setDate(mulai.getDate() - 7); }
                else if (v === '30d') { mulai.setDate(mulai.getDate() - 30); }
                else if (v === '1y')  { mulai.setFullYear(mulai.getFullYear() - 1); }

                hasil.setRange(fmtIso(mulai), fmtIso(kini));
            });

            /* Memilih tanggal sendiri di kalender berarti rentangnya custom. */
            $popup.on('mousedown', '.flatpickr-day', function () {
                rangeAktif = 'custom';
                $quick.val('custom');
                $rangeField.val('custom');
            });
        }

        $input.on('click', function () {
            if ($popup.hasClass('d-none')) { buka(); } else { tutup(); }
        });

        $(document).on('mousedown', function (e) {
            if ($popup.hasClass('d-none')) return;
            if ($(e.target).closest($popup).length || $(e.target).is($input)) return;
            tutup();
        });

        var hasil = {
            open: buka,
            close: tutup,
            setRange: function (from, to) {
                /* Batas dilepas dulu: tanggal baru bisa berada di luar rentang
                   lama sehingga setDate akan menolaknya. */
                fpA.set('maxDate', null);
                fpB.set('minDate', null);
                fpA.setDate(from, false);
                fpB.setDate(to, false);
                fpA.set('maxDate', to);
                fpB.set('minDate', from);
                syncLabel(fpA);
                syncLabel(fpB);
                tulisRentang();
            }
        };
        return hasil;
    }

    window.TesysDateRange = { init: init };
})();
