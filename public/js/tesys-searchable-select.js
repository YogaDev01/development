/*
|--------------------------------------------------------------------------
| TesysSearchableSelect
|--------------------------------------------------------------------------
| Dropdown kustom (tombol + panel pencarian) sebagai pengganti Tom Select —
| menghindari bug "kotak dobel" Tom Select dan konsisten dengan pola filter
| dropdown lain di app (search box di dalam panel, bukan search-as-you-type
| di kontrol tertutup).
|
| <select> asli TETAP jadi sumber nilai (disembunyikan, bukan dihapus), jadi
| semua logic existing yang dengar event 'change' pada select itu tetap
| jalan tanpa perlu diubah. Mendukung <select> biasa (single) maupun
| <select multiple> (tampil sebagai chip + checklist, pengganti Tom Select
| dengan plugin remove_button).
|
| Pemakaian:
|   TesysSearchableSelect.init('#selectBudgetPlan', { placeholder: '— Pilih Budget Plan —' });
|   TesysSearchableSelect.initOne($(selectEl), { placeholder: '...' }); // untuk elemen dinamis (mis. baris tabel)
|
| Opsi:
|   placeholder       — teks tombol/placeholder saat belum ada yang dipilih
|   searchPlaceholder — placeholder input pencarian di dalam panel
|   onChange          — dipanggil langsung (selectEl) setiap kali pilihan berubah,
|                        tidak bergantung pada bubbling event 'change'
|
| Label tiap opsi diambil dari atribut data-label (kalau ada), fallback ke
| teks <option> itu sendiri.
|
| Tambahan opsional (single select; pemanggil lama tidak terpengaruh):
|   <optgroup label="..."> — judul grup tampil tebal dan tidak bisa diklik; opsi
|                            di bawahnya menjorok. Judul disembunyikan saat pencarian
|                            tidak menyisakan opsi di grupnya.
|   data-item-label       — teks item di daftar (tombol terpilih tetap memakai data-label).
|   data-search           — kata tambahan yang ikut dicari (tidak ditampilkan).
|   data-badge            — teks lencana kecil di akhir item (data-badge-tone: nada, mis.
|                            "internal"/"external" -> kelas tss-badge-<nada>).
*/
(function () {
    'use strict';

    function optionLabel($opt) {
        return $opt.data('label') || $opt.text().trim();
    }

    /* Satu tombol = buka DAN tutup.

       Pemicu panel dicatat di elemen panelnya, jadi klik kedua pada tombol
       yang sama bisa dikenali sebagai perintah tutup. Tanpa ini tiap klik
       selalu menghapus lalu menggambar ulang panel — dari layar terlihat
       seperti dropdown yang tidak bisa ditutup. */
    function closeAllTss() {
        $('.tss-dropdown').each(function () {
            var cleanup = $(this).data('tssCleanup');
            if (cleanup) cleanup();
        }).remove();
        $(document).off('click.tss-outside');
    }

    function isTssOpenFor($btn) {
        var el = $btn.get(0), open = false;
        $('.tss-dropdown').each(function () {
            if ($(this).data('tssOwner') === el) open = true;
        });
        return open;
    }

    function positionDropdown($dd, $anchor) {
        $dd.data('tssOwner', $anchor.get(0));

        /* Di dalam modal Bootstrap, panel harus ikut di dalam modal: panel di
           <body> berada di luar focus-trap modal (_enforceFocus), sehingga
           kotak cari tak bisa diketik dan klik pada panel ikut terganggu. */
        var $modal = $anchor.closest('.modal');
        if ($modal.length) {
            $modal.append($dd);
            $modal.one('hide.bs.modal', closeAllTss);
        } else {
            $('body').append($dd);
        }

        /* Halaman penuh menggulir lewat kotak konten (.main > .content, lihat
           tesys-ui-shell.css) dan <body> sendiri tidak menggulir, jadi dipakai
           koordinat layar (fixed). Aplikasi berjalan dengan `zoom` pada <html>
           (skala 80%): getBoundingClientRect() memberi piksel LAYAR, sedangkan
           outerHeight()/outerWidth() dan nilai css top/left/max-height memakai
           satuan CSS. Ukuran panel karena itu diukur dengan
           getBoundingClientRect() juga — dulu memakai outerHeight() sehingga
           saat panel membalik ke atas field ia melayang jauh di atasnya. */
        function place() {
            var anchorEl = $anchor.get(0);
            if (!document.body.contains(anchorEl)) { closeAllTss(); return; }

            var zm = parseFloat(getComputedStyle(document.documentElement).zoom) || 1;
            var r = anchorEl.getBoundingClientRect();

            // Field tergulir keluar layar: tutup, jangan biarkan panel melayang.
            if (r.bottom < 0 || r.top > window.innerHeight) { closeAllTss(); return; }

            // Ukur dari kondisi bersih (fixed, tanpa batas tinggi dari pemanggilan sebelumnya).
            $dd.css({ position: 'fixed', top: 0, left: 0, maxHeight: '' });
            var box = $dd.get(0).getBoundingClientRect();
            var ddWidth = box.width;
            var ddHeight = box.height;

            var left = r.left;
            if (left + ddWidth > window.innerWidth - 10) left = Math.max(10, window.innerWidth - 10 - ddWidth);

            var spaceBelow = window.innerHeight - r.bottom - 14;
            var spaceAbove = r.top - 14;
            var top;
            if (ddHeight <= spaceBelow || spaceBelow >= spaceAbove) {
                top = r.bottom + 4;
                // Sisa ruang lebih sempit dari panel: isinya yang dipendekkan,
                // bukan panelnya yang terpotong di tepi layar.
                if (ddHeight > spaceBelow) {
                    $dd.css('max-height', Math.max(140, spaceBelow) / zm);
                }
            } else {
                if (ddHeight > spaceAbove) {
                    $dd.css('max-height', Math.max(140, spaceAbove) / zm);
                    ddHeight = $dd.get(0).getBoundingClientRect().height;
                }
                top = Math.max(10, r.top - ddHeight - 4);
            }
            $dd.css({ top: top / zm, left: left / zm });
        }

        place();
        $dd.data('tssPlace', place);

        /* Posisi fixed tidak ikut gulir: ikuti field saat wadah mana pun
           (konten, modal, jendela) digulir atau ukuran layar berubah — kecuali
           gulir di dalam daftar panelnya sendiri. */
        function onScroll(ev) {
            if ($dd.get(0).contains(ev.target)) return;
            place();
        }
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', place);
        $dd.data('tssCleanup', function () {
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', place);
        });
    }

    function bindOutsideClose($dd) {
        $(document).one('click.tss-outside', function handler(ev) {
            if ($(ev.target).closest('.tss-dropdown, .tss-btn').length === 0) {
                closeAllTss();
            } else if ($(document.body).find($dd).length) {
                $(document).one('click.tss-outside', handler);
            }
        });
    }

    function bindSearch($search, $list) {
        $search.on('input', function () {
            var q = $(this).val().toLowerCase();
            $list.children('.tss-item').each(function () {
                var teks = ($(this).text() + ' ' + ($(this).attr('data-search') || '')).toLowerCase();
                $(this).toggle(teks.indexOf(q) !== -1);
            });
            // Judul grup hanya tampil bila ada opsi di bawahnya yang tampil.
            $list.children('.tss-group').each(function () {
                var ada = $(this).nextUntil('.tss-group', '.tss-item').filter(function () {
                    return $(this).css('display') !== 'none';
                }).length > 0;
                $(this).toggle(ada);
            });
            // Tinggi panel berubah: panel yang membalik ke atas harus menempel lagi ke field.
            var place = $list.closest('.tss-dropdown').data('tssPlace');
            if (place) place();
        }).focus();
    }

    function initSingle($select, opts) {
        var $btn = $(
            '<button type="button" class="btn btn-outline-secondary btn-block tss-btn ' +
            'd-flex justify-content-between align-items-center"></button>'
        );
        var $label = $('<span class="text-truncate tss-label"></span>');
        $btn.append($label).append('<i class="fas fa-caret-down ml-2 text-muted"></i>');
        $select.before($btn);

        function syncLabel() {
            var $opt = $select.find('option:selected');
            var placeholder = opts.placeholder || $select.find('option').first().text().trim() || '— Pilih —';
            $label.text($opt.val() ? optionLabel($opt) : placeholder);
        }
        syncLabel();

        // Panggil ini kalau <option> dibangun ulang lewat JS (mis. dropdown dependen).
        $select.on('tss:sync', syncLabel);

        $btn.on('click', function (e) {
            e.stopPropagation();
            if (isTssOpenFor($btn)) { closeAllTss(); return; }
            closeAllTss();

            var $dd = $('<div class="tss-dropdown"></div>');
            var $search = $('<input type="text" class="form-control form-control-sm mb-2" placeholder="' +
                (opts.searchPlaceholder || 'Cari...') + '">');
            var $list = $('<div class="tss-list"></div>');

            function tambahItem($opt, dalamGrup) {
                var val = $opt.val();
                // Opsi kosong = placeholder; opsi disabled = disaring dropdown
                // dependen (mis. Sub-RCA yang bukan milik RCA terpilih).
                if (!val || $opt.prop('disabled')) return;
                var $item = $('<div class="tss-item"></div>').text($opt.attr('data-item-label') || optionLabel($opt)).attr('data-value', val);
                if (dalamGrup) $item.addClass('tss-item-grouped');
                if ($opt.attr('data-search')) $item.attr('data-search', $opt.attr('data-search'));
                var badge = $opt.attr('data-badge');
                if (badge) {
                    var nada = $opt.attr('data-badge-tone');
                    $item.append($('<span class="tss-badge"></span>')
                        .addClass(nada ? 'tss-badge-' + nada : '').text(badge));
                }
                if ($opt.is(':selected')) $item.addClass('active');
                $list.append($item);
            }
            $select.children().each(function () {
                var $el = $(this);
                if ($el.is('optgroup')) {
                    var $opts = $el.children('option').filter(function () {
                        return $(this).val() && !$(this).prop('disabled');
                    });
                    if (!$opts.length) return;
                    $list.append($('<div class="tss-group"></div>').text($el.attr('label') || ''));
                    $opts.each(function () { tambahItem($(this), true); });
                } else if ($el.is('option')) {
                    tambahItem($el, false);
                }
            });
            if ($list.children().length === 0) {
                $list.append('<div class="text-muted p-2" style="font-size:12px;">Tidak ada data.</div>');
            }

            $dd.append($search, $list);
            positionDropdown($dd, $btn);
            bindSearch($search, $list);

            $list.on('click', '.tss-item', function () {
                var selectEl = $select.get(0);
                selectEl.value = $(this).attr('data-value');
                // Dispatch event NATIVE (bukan $.trigger()) — supaya listener yang
                // didaftarkan via addEventListener() di halaman (bukan lewat jQuery)
                // ikut kepanggil. $.trigger() tidak selalu memicu handler native.
                selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                // Panggil langsung juga (tidak bergantung ke bubbling event sama
                // sekali) — supaya update tetap jalan walau listener 'change' di
                // halaman terpasang lewat cara yang tidak menangkap event bubble ini.
                if (typeof opts.onChange === 'function') opts.onChange(selectEl);
                syncLabel();
                closeAllTss();
            });

            bindOutsideClose($dd);
        });
    }

    function initMulti($select, opts) {
        var $btn = $('<div class="tss-btn tss-multi-btn" tabindex="0"></div>');
        var $chips = $('<div class="tss-chips"></div>');
        $btn.append($chips).append('<i class="fas fa-caret-down ml-2 text-muted tss-caret"></i>');
        $select.before($btn);

        function fireChange() {
            var selectEl = $select.get(0);
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof opts.onChange === 'function') opts.onChange(selectEl);
        }

        function renderChips() {
            $chips.empty();
            var $sel = $select.find('option:selected');
            if ($sel.length === 0) {
                $chips.append($('<span class="tss-placeholder"></span>').text(opts.placeholder || '— Pilih —'));
                return;
            }
            $sel.each(function () {
                var $opt = $(this);
                var $chip = $('<span class="tss-chip"></span>').text(optionLabel($opt));
                var $x = $('<span class="tss-chip-remove">&times;</span>');
                $chip.append($x);
                $x.on('click', function (e) {
                    e.stopPropagation();
                    $opt.prop('selected', false);
                    renderChips();
                    fireChange();
                });
                $chips.append($chip);
            });
        }
        renderChips();
        $select.on('tss:sync', renderChips);

        $btn.on('click', function (e) {
            e.stopPropagation();
            if (isTssOpenFor($btn)) { closeAllTss(); return; }
            closeAllTss();

            var $dd = $('<div class="tss-dropdown"></div>');
            var $search = $('<input type="text" class="form-control form-control-sm mb-2" placeholder="' +
                (opts.searchPlaceholder || 'Cari...') + '">');
            var $list = $('<div class="tss-list"></div>');

            $select.find('option').each(function () {
                var $opt = $(this);
                var val = $opt.val();
                if (!val) return;
                var $item = $('<div class="tss-item tss-item-check"></div>').attr('data-value', val);
                $item.append('<i class="fas fa-check tss-check-icon"></i>');
                $item.append(document.createTextNode(optionLabel($opt)));
                if ($opt.is(':selected')) $item.addClass('active');
                $list.append($item);
            });
            if ($list.children().length === 0) {
                $list.append('<div class="text-muted p-2" style="font-size:12px;">Tidak ada data.</div>');
            }

            $dd.append($search, $list);
            positionDropdown($dd, $btn);
            bindSearch($search, $list);

            // Klik item TIDAK menutup panel — supaya bisa pilih beberapa sekaligus.
            $list.on('click', '.tss-item', function () {
                var $item = $(this);
                var $opt = $select.find('option[value="' + $item.attr('data-value') + '"]');
                var nowSelected = !$opt.is(':selected');
                $opt.prop('selected', nowSelected);
                $item.toggleClass('active', nowSelected);
                renderChips();
                fireChange();
            });

            bindOutsideClose($dd);
        });
    }

    function initOne($select, opts) {
        opts = opts || {};
        if ($select.data('tss-init')) return;
        $select.data('tss-init', true);
        $select.hide();

        if ($select.prop('multiple')) {
            initMulti($select, opts);
        } else {
            initSingle($select, opts);
        }
    }

    window.TesysSearchableSelect = {
        init: function (selector, opts) {
            $(selector).each(function () { initOne($(this), opts); });
        },
        initOne: function ($select, opts) { initOne($select, opts); }
    };
})();
