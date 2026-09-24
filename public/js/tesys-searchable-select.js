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
        $('.tss-dropdown').remove();
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
        $('body').append($dd);
        var offset = $anchor.offset();
        var ddWidth = $dd.outerWidth();
        var viewportRight = $(window).scrollLeft() + $(window).width();
        var left = offset.left;
        if (left + ddWidth > viewportRight - 10) left = Math.max(10, viewportRight - 10 - ddWidth);
        $dd.css({ top: offset.top + $anchor.outerHeight() + 4, left: left });
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
                $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
            });
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

            $select.find('option').each(function () {
                var $opt = $(this);
                var val = $opt.val();
                if (!val) return;
                var $item = $('<div class="tss-item"></div>').text(optionLabel($opt)).attr('data-value', val);
                if ($opt.is(':selected')) $item.addClass('active');
                $list.append($item);
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
                $dd.remove();
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
