/**
 * Excel-style per-column header filter untuk jQuery DataTables.
 *
 * Pemakaian (setelah table.DataTable({...}) diinisialisasi):
 *   TesysColumnFilter.init('#tblBatch', [1, 2, 6]); // index kolom (0-based) yang mau difilter
 *
 * Menambahkan ikon funnel di header kolom terpilih. Klik ikon membuka dropdown
 * checklist berisi nilai unik pada kolom tsb (diambil dari teks sel, HTML di-strip).
 * Tombol "Filter" menerapkan filter, "Cancel" menutup tanpa mengubah apa pun.
 * Baris chip "Filters:" tampil di atas tabel (dibuat otomatis) menampilkan filter aktif.
 */
(function (window, $) {
    'use strict';

    var state = {}; // { tableId: { colIndex: Set(values) } }

    function stripHtml(html) {
        var div = document.createElement('div');
        div.innerHTML = String(html == null ? '' : html);
        return (div.textContent || div.innerText || '').trim();
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function ensureChipRow(tableEl) {
        var $table = $(tableEl);
        var $wrapper = $table.closest('.table-responsive');
        var $anchor = $wrapper.length ? $wrapper : $table;
        var $chipRow = $anchor.prev('.tcf-chip-row');
        if ($chipRow.length === 0) {
            $chipRow = $('<div class="tcf-chip-row mb-2"></div>');
            $anchor.before($chipRow);
        }
        return $chipRow;
    }

    var rowFieldLabels = {}; // { tableId: { field: label } }

    function renderChips(tableId, tableEl, columns) {
        columns = columns || {};
        var $chipRow = ensureChipRow(tableEl);
        var chips = [];

        var colState = state[tableId] || {};
        Object.keys(colState).forEach(function (colIdx) {
            var values = Array.from(colState[colIdx]);
            if (!values.length) return;
            var label = columns[colIdx] ? columns[colIdx].label : ('Kolom ' + colIdx);
            var text = values.length > 2
                ? (values.slice(0, 2).join(', ') + ' +' + (values.length - 2))
                : values.join(', ');
            chips.push(
                '<span class="badge badge-secondary tcf-chip mr-1 mb-1" data-type="col" data-col="' + colIdx + '" style="font-size:11px;padding:5px 8px;">' +
                escapeHtml(label) + ' : ' + escapeHtml(text) +
                ' <i class="fas fa-times ml-1 tcf-chip-clear" style="cursor:pointer;"></i>' +
                '</span>'
            );
        });

        var rowState = rowFilterState[tableId] || {};
        var labels = rowFieldLabels[tableId] || {};
        Object.keys(rowState).forEach(function (field) {
            var rawValues = Array.from(rowState[field]);
            if (!rawValues.length) return;
            var vl = (labels[field] && labels[field].valueLabels) || {};
            var displayValues = rawValues.map(function (v) { return vl[v] || v; });
            var label = (labels[field] && labels[field].label) || field;
            var text = displayValues.length > 2
                ? (displayValues.slice(0, 2).join(', ') + ' +' + (displayValues.length - 2))
                : displayValues.join(', ');
            chips.push(
                '<span class="badge badge-secondary tcf-chip mr-1 mb-1" data-type="row" data-field="' + field + '" style="font-size:11px;padding:5px 8px;">' +
                escapeHtml(label) + ' : ' + escapeHtml(text) +
                ' <i class="fas fa-times ml-1 tcf-chip-clear" style="cursor:pointer;"></i>' +
                '</span>'
            );
        });

        $chipRow.html(chips.join(''));
        $chipRow.toggle(chips.length > 0);
    }

    function closeAllDropdowns() {
        $('.tcf-dropdown').remove();
        // Penjaga klik-di-luar ikut dilepas. Kalau ditinggal, handler dari
        // dropdown yang sudah hilang masih menunggu dan bisa menutup
        // dropdown berikutnya begitu ia dibuka.
        $(document).off('click.tcf-outside');
    }

    /*
     | Satu tombol = buka DAN tutup.
     |
     | Sebelumnya tiap klik pada pemicu selalu menggambar dropdown baru, jadi
     | menekan tombol yang sama untuk kedua kalinya terlihat seperti tidak
     | terjadi apa-apa — padahal panelnya dihapus lalu dipasang lagi. Pemicu
     | yang sedang membuka panel dicatat di elemen panelnya, sehingga klik
     | berikutnya pada pemicu itu juga bisa dikenali sebagai perintah tutup.
     */
    function isDropdownOpenFor($trigger) {
        var el = $trigger.get(0);
        var open = false;
        $('.tcf-dropdown').each(function () {
            if ($(this).data('tcfOwner') === el) open = true;
        });
        return open;
    }

    function applyFilter(tableId, table, tableEl, columns) {
        table.draw();
        renderChips(tableId, tableEl, columns);
    }

    function openDropdown($icon, tableId, table, tableEl, colIdx, columns) {
        if (isDropdownOpenFor($icon)) { closeAllDropdowns(); return; }
        closeAllDropdowns();

        var current = (state[tableId] && state[tableId][colIdx]) ? state[tableId][colIdx] : new Set();

        // Kumpulkan nilai unik pada kolom ini, dari seluruh baris (abaikan filter aktif).
        var uniqueValues = [];
        var seen = {};
        table.column(colIdx, { search: 'none' }).data().each(function (d) {
            var text = stripHtml(d);
            if (text === '') text = '(Kosong)';
            if (!seen[text]) {
                seen[text] = true;
                uniqueValues.push(text);
            }
        });
        uniqueValues.sort(function (a, b) { return a.localeCompare(b); });

        var $dd = $('<div class="tcf-dropdown"></div>').css({
            position: 'absolute', zIndex: 3000, background: 'var(--surface, #ffffff)', border: '1px solid var(--border-strong, #d2d7e0)',
            borderRadius: '6px', boxShadow: '0 6px 18px rgba(0,0,0,.12)', padding: '8px',
            width: 'max-content', minWidth: '200px', maxWidth: '560px', maxHeight: '320px',
            display: 'flex', flexDirection: 'column'
        });

        var $search = $('<input type="text" class="form-control form-control-sm mb-2" placeholder="Cari...">');
        var $list = $('<div class="tcf-list" style="overflow-y:auto;overflow-x:hidden;max-height:200px;padding-right:14px;box-sizing:border-box;"></div>');

        uniqueValues.forEach(function (val) {
            var checked = current.has(val) ? 'checked' : '';
            $list.append(
                '<label class="tcf-item mb-1" style="display:flex;align-items:center;font-size:12px;font-weight:400;cursor:pointer;gap:6px;">' +
                '<input type="checkbox" class="tcf-check" value="' + escapeHtml(val) + '" ' + checked + '> ' +
                '<span>' + escapeHtml(val) + '</span>' +
                '</label>'
            );
        });

        $dd.append($search, $list);
        $dd.data('tcfOwner', $icon.get(0));
        $('body').append($dd);

        var offset = $icon.offset();
        var ddWidth = $dd.outerWidth();
        var viewportRight = $(window).scrollLeft() + $(window).width();
        var left = offset.left;
        if (left + ddWidth > viewportRight - 10) {
            // Tidak cukup ruang di kanan -> rata kanan terhadap ikon
            left = offset.left + $icon.outerWidth() - ddWidth;
        }
        $dd.css({ top: offset.top + $icon.outerHeight() + 4, left: Math.max(10, left) });

        $search.on('input', function () {
            var q = $(this).val().toLowerCase();
            $list.find('label').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
            });
        });

        // Apply langsung tiap checkbox berubah — tanpa tombol Apply/Cancel.
        $list.on('change', '.tcf-check', function () {
            var selected = new Set();
            $list.find('.tcf-check:checked').each(function () { selected.add($(this).val()); });

            state[tableId] = state[tableId] || {};
            if (selected.size === 0) {
                delete state[tableId][colIdx];
            } else {
                state[tableId][colIdx] = selected;
            }

            $icon.toggleClass('tcf-active', selected.size > 0);
            applyFilter(tableId, table, tableEl, columns);
        });

        $(document).one('click.tcf-outside', function handler(e) {
            if ($(e.target).closest('.tcf-dropdown, .tcf-filter-icon').length === 0) {
                closeAllDropdowns();
            } else {
                $(document).one('click.tcf-outside', handler);
            }
        });
    }

    var Api = {
        init: function (tableSelector, columnIndexes) {
            var $table = $(tableSelector);
            if ($table.length === 0) return;
            var tableEl = $table.get(0);
            var tableId = tableEl.id || (tableEl.id = 'tcf_' + Math.random().toString(36).slice(2));
            var table = $table.DataTable();
            var columns = {};

            columnIndexes.forEach(function (colIdx) {
                var $th = $table.find('thead th').eq(colIdx);
                if ($th.length === 0) return;
                columns[colIdx] = { label: $th.text().trim() };

                var $icon = $('<i class="fas fa-filter tcf-filter-icon ml-1" style="cursor:pointer;font-size:11px;opacity:.6;"></i>');
                $th.css('white-space', 'nowrap').append($icon);

                $icon.on('click', function (e) {
                    e.stopPropagation();
                    if ($(this).siblings('.tcf-dropdown').length || $('.tcf-dropdown').data('col') === colIdx) {
                        // toggle close if reopening same icon handled by outside-click already
                    }
                    openDropdown($icon, tableId, table, tableEl, colIdx, columns);
                });
            });

            $(document).on('click', '.tcf-chip-clear', function () {
                var $chip = $(this).closest('.tcf-chip');
                if ($chip.data('type') !== 'col') return;
                var colIdx = $chip.data('col');
                if (state[tableId]) delete state[tableId][colIdx];
                $table.find('thead th').eq(colIdx).find('.tcf-filter-icon').removeClass('tcf-active');
                applyFilter(tableId, table, tableEl, columns);
            });

            // Predikat pencarian custom: cocokkan teks sel terhadap nilai terpilih per kolom.
            $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
                if (settings.nTable.id !== tableId) return true;
                var tableState = state[tableId];
                if (!tableState) return true;

                for (var colIdx in tableState) {
                    if (!tableState.hasOwnProperty(colIdx)) continue;
                    var selected = tableState[colIdx];
                    var cellText = stripHtml(searchData[colIdx]);
                    if (cellText === '') cellText = '(Kosong)';
                    if (!selected.has(cellText)) return false;
                }
                return true;
            });
        }
    };

    // ------------------------------------------------------------------
    // Filter berbasis atribut data-* pada <tr> (untuk field yang tidak
    // punya kolom tabel tersendiri, misalnya field yang digabung dalam
    // satu sel). Dirender sebagai tombol kecil di toolbar, dropdown yang
    // sama persis gayanya dengan filter header kolom.
    // ------------------------------------------------------------------
    var rowFilterState = {}; // { tableId: { field: Set(rawValues) } }
    var rowFieldButtons = {}; // { tableId: { field: jQuery button } }

    /*
     | Filter bertingkat: daftar pilihan satu dropdown MENGIKUTI filter lain
     | yang sedang aktif.
     |
     | Sebelumnya tiap dropdown membaca seluruh baris tabel apa adanya, jadi
     | setelah Manager dipilih, dropdown PO/Tipe/Beneficiary/PIC masih
     | menawarkan semua nilai — termasuk yang tidak dimiliki manager itu dan
     | pasti menghasilkan tabel kosong kalau dicentang. Di sini baris yang
     | dipakai menyusun daftar disaring dulu oleh filter field LAIN; filter
     | field itu sendiri sengaja diabaikan, supaya pilihan yang sudah
     | tercentang tidak lenyap dari daftarnya sendiri dan masih bisa
     | dilepas kembali.
     */
    /*
     | Lingkup tambahan di luar filter — mis. tab halaman yang sudah menyaring
     | baris lewat $.fn.dataTable.ext.search. Daftar pilihan disusun dari
     | rows({search:'none'}) sehingga saringan semacam itu tidak terlihat:
     | tanpa ini, tab Internal menawarkan semua bank dari seluruh data padahal
     | mencentangnya pasti menghasilkan tabel kosong.
     */
    var rowScope = {}; // { tableId: function (tr) -> bool }
    var rowHooked = {}; // { tableId: true } — handler global sudah terpasang

    function rowInScope(tr, tableId) {
        var fn = rowScope[tableId];
        return typeof fn === 'function' ? !!fn(tr) : true;
    }

    function rowPassesOtherFilters(tr, tableId, exceptField) {
        if (!rowInScope(tr, tableId)) return false;

        var tableState = rowFilterState[tableId];
        if (!tableState) return true;

        for (var field in tableState) {
            if (!tableState.hasOwnProperty(field)) continue;
            if (field === exceptField) continue;
            var selected = tableState[field];
            if (!selected || selected.size === 0) continue;
            var raw = (tr.getAttribute('data-' + field) || '').toString();
            if (!selected.has(raw)) return false;
        }
        return true;
    }

    function openRowFilterDropdown($btn, tableId, table, tableEl, field, label, valueLabels, options, showSearch) {
        if (isDropdownOpenFor($btn)) { closeAllDropdowns(); return; }
        closeAllDropdowns();

        var current = (rowFilterState[tableId] && rowFilterState[tableId][field]) ? rowFilterState[tableId][field] : new Set();

        var uniqueRaw = [];

        if (options && options.length) {
            /*
              Daftar pilihan TETAP, ditentukan pemanggil.

              Pilihan yang disimpulkan dari baris yang ada punya lubang: nilai
              yang belum dipakai satu baris pun tidak pernah muncul — tepat
              ketika seseorang ingin memastikan memang belum ada. Untuk field
              yang himpunan nilainya sudah pasti (kategori, status), daftarnya
              lebih baik dinyatakan daripada ditebak.

              Urutannya dibiarkan apa adanya: pemanggil menulisnya dalam urutan
              yang bermakna (mis. ACTIVE sebelum INACTIVE), bukan alfabetis.
            */
            uniqueRaw = options.slice();

            /*
              ...KECUALI saat filter lain sedang aktif. Selama tidak ada filter
              lain, daftar lengkap itu memang berguna: nilai yang belum dipakai
              satu baris pun tetap terlihat. Begitu Manager (atau field lain)
              dipilih, daftar yang tetap lengkap justru menawarkan pilihan yang
              sudah pasti mengosongkan tabel — jadi di keadaan itu ia ikut
              menyempit ke nilai yang benar-benar dimiliki baris tersisa.
            */
            var lainAktif = false;
            var stateLain = rowFilterState[tableId];
            for (var f in (stateLain || {})) {
                if (stateLain.hasOwnProperty(f) && f !== field && stateLain[f] && stateLain[f].size) {
                    lainAktif = true;
                }
            }

            if (lainAktif) {
                var ada = {};
                table.rows({ search: 'none' }).nodes().each(function (tr) {
                    if (!rowPassesOtherFilters(tr, tableId, field)) return;
                    ada[(tr.getAttribute('data-' + field) || '').toString()] = true;
                });
                uniqueRaw = uniqueRaw.filter(function (v) {
                    // Yang sudah tercentang tetap ditampilkan supaya bisa dilepas.
                    return ada[v] || current.has(v);
                });
            }
        } else {
            var seen = {};
            table.rows({ search: 'none' }).nodes().each(function (tr) {
                if (!rowPassesOtherFilters(tr, tableId, field)) return;
                var raw = ($(tr).attr('data-' + field) || '').toString();
                if (!seen[raw]) { seen[raw] = true; uniqueRaw.push(raw); }
            });
            uniqueRaw = uniqueRaw.filter(function (v) { return v !== ''; });
            uniqueRaw.sort(function (a, b) {
                return (valueLabels && valueLabels[a] ? valueLabels[a] : a).localeCompare(valueLabels && valueLabels[b] ? valueLabels[b] : b);
            });
        }

        var $dd = $('<div class="tcf-dropdown"></div>').css({
            position: 'absolute', zIndex: 3000, background: 'var(--surface, #ffffff)', border: '1px solid var(--border-strong, #d2d7e0)',
            borderRadius: '6px', boxShadow: '0 6px 18px rgba(0,0,0,.12)', padding: '8px',
            width: 'max-content', minWidth: '200px', maxWidth: '560px', maxHeight: '320px',
            display: 'flex', flexDirection: 'column'
        });

        // showSearch default true — pemanggil lama tidak mengirim parameter
        // ini sama sekali, jadi perilakunya tidak berubah untuk mereka.
        // Dilewatkan false hanya untuk field yang pilihannya sedikit dan
        // sudah pasti (mis. Kategori, Status): kotak cari di situ tidak
        // menyaring apa pun yang berarti, hanya menambah satu klik sebelum
        // pilihannya terlihat.
        var $search = showSearch === false ? null
            : $('<input type="text" class="form-control form-control-sm mb-2" placeholder="Cari...">');
        var $list = $('<div class="tcf-list" style="overflow-y:auto;overflow-x:hidden;max-height:200px;padding-right:14px;box-sizing:border-box;"></div>');

        uniqueRaw.forEach(function (raw) {
            var displayText = (valueLabels && valueLabels[raw]) ? valueLabels[raw] : raw;
            var checked = current.has(raw) ? 'checked' : '';
            $list.append(
                '<label class="tcf-item mb-1" style="display:flex;align-items:center;font-size:12px;font-weight:400;cursor:pointer;gap:6px;">' +
                '<input type="checkbox" class="tcf-check" value="' + escapeHtml(raw) + '" ' + checked + '> ' +
                '<span>' + escapeHtml(displayText) + '</span>' +
                '</label>'
            );
        });

        if (uniqueRaw.length === 0) {
            $list.append('<div class="text-muted" style="font-size:12px;">Tidak ada data</div>');
        }

        if ($search) $dd.append($search);
        $dd.append($list);
        $dd.data('tcfOwner', $btn.get(0));
        $('body').append($dd);

        var offset = $btn.offset();
        var ddWidth = $dd.outerWidth();
        var viewportRight = $(window).scrollLeft() + $(window).width();
        var left = offset.left;
        if (left + ddWidth > viewportRight - 10) left = offset.left + $btn.outerWidth() - ddWidth;
        $dd.css({ top: offset.top + $btn.outerHeight() + 4, left: Math.max(10, left) });

        if ($search) {
            $search.on('input', function () {
                var q = $(this).val().toLowerCase();
                $list.find('label').each(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
                });
            });
        }

        // Apply langsung tiap checkbox berubah — tanpa tombol Apply/Cancel.
        $list.on('change', '.tcf-check', function () {
            var selected = new Set();
            $list.find('.tcf-check:checked').each(function () { selected.add($(this).val()); });

            rowFilterState[tableId] = rowFilterState[tableId] || {};
            if (selected.size === 0) {
                delete rowFilterState[tableId][field];
            } else {
                rowFilterState[tableId][field] = selected;
            }

            $btn.toggleClass('tcf-row-active', selected.size > 0);
            table.draw();
            renderChips(tableId, tableEl, null);
        });

        $(document).one('click.tcf-outside', function handler(e) {
            if ($(e.target).closest('.tcf-dropdown, .tcf-row-btn').length === 0) {
                closeAllDropdowns();
            } else {
                $(document).one('click.tcf-outside', handler);
            }
        });
    }

    /* ── SATU TOMBOL "FILTER" ──────────────────────────────────────────────
    | Halaman dengan banyak field filter menaruh tombolnya berjejer di bilah
    | kendali (Status, Customer, Financing, Manager, Tipe, …) sampai memakan
    | seluruh baris dan mendorong tombol aksi. Begitu ada lebih dari satu
    | field, deretan itu dipindahkan ke dalam satu popover "Filter" — pola
    | yang sudah dipakai Cost Request — lengkap dengan tombol Reset.
    |
    | Dikerjakan di sini, bukan di tiap halaman: kontainer dan daftar field-nya
    | memang sudah lewat fungsi ini, jadi tidak ada markup yang perlu diubah.
    |
    | Popover-nya tidak memakai dropdown Bootstrap: Popper menghitung posisi
    | terhadap kontainer tabel yang bisa digulir, sehingga panelnya melompat
    | saat dibuka. Panel cukup ditempel absolut di bawah tombolnya, lalu
    | digeser kalau ujungnya melewati tepi layar.
    */
    function bungkusFilterKePopover($container, tableSelector) {
        if (!$container.length) return;
        // Halaman yang sudah punya popover Filter sendiri dibiarkan.
        if ($container.closest('.ts-pop, .cr-pop, .dropdown-menu').length) return;
        if ($container.data('tcf-popover')) return;
        $container.data('tcf-popover', true);

        var $pop = $(
            '<div class="ts-pop tcf-filter-pop">' +
              '<button type="button" class="btn btn-outline-secondary btn-sm ts-pop-toggle">' +
                '<i class="fas fa-filter mr-1"></i> Filter' +
              '</button>' +
              '<div class="ts-pop-menu p-3" style="min-width:420px;">' +
                '<div class="tcf-pop-title">FILTER</div>' +
                '<div class="tcf-pop-body"></div>' +
              '</div>' +
            '</div>'
        );

        $container.before($pop);
        $pop.find('.tcf-pop-body').append($container.addClass('tcf-pop-grid'));

        /*
        | Tanpa "Terapkan": filter berlaku begitu nilainya dicentang.
        |
        | Tanpa "Reset" juga. Halaman yang memakai popover ini sudah punya
        | tombol hapus-filter (ikon penghapus) berdiri sendiri di bilah
        | kendali; tombol Reset di dalam popover mengerjakan hal yang sama
        | persis, hanya saja tersembunyi di balik satu klik pembuka. Kalau
        | tombol itu TIDAK ada, Reset tetap dipasang supaya filter yang sudah
        | menumpuk tidak kehilangan satu-satunya jalan bersih-bersihnya.
        */
        var adaTombolHapus = $pop.closest('.custom-toolbar').find('.fa-eraser').length > 0;
        if (!adaTombolHapus) {
            $pop.find('.ts-pop-menu').append(
                '<div class="tcf-pop-foot">' +
                  '<button type="button" class="btn btn-primary btn-sm tcf-filter-reset">' +
                    '<i class="fas fa-undo mr-1"></i>Reset' +
                  '</button>' +
                '</div>'
            );
            $pop.find('.tcf-filter-reset').on('click', function () {
                Api.clearAll(tableSelector);
            });
        }

        pindahkanTombolKolom($pop);
    }

    /* Tombol "Kolom" biasanya berdiri di sisi aksi (bersama Export/Tambah),
       padahal ia kendali tampilan — sama seperti Cari dan Filter. Dipindahkan
       ke sisi kiri, tepat setelah tombol Filter, supaya sisi kanan benar-benar
       hanya berisi tombol yang mengubah sesuatu. */
    function pindahkanTombolKolom($pop) {
        var $bilah = $pop.closest('.custom-toolbar');
        if (!$bilah.length) return;

        var $kolom = $bilah.find('.custom-toolbar-right .dropdown').filter(function () {
            return $(this).find('.fa-columns').length > 0;
        }).first();
        if (!$kolom.length || $kolom.data('tcf-dipindah')) return;

        // Menu-nya tadinya rata kanan karena menempel di ujung kanan bilah;
        // setelah pindah ke kiri, perataannya ikut dibalik.
        $kolom.find('.dropdown-menu').removeClass('dropdown-menu-right');
        $kolom.data('tcf-dipindah', true).insertAfter($pop);
    }

    $(document).on('click', '.ts-pop-toggle', function (e) {
        e.stopPropagation();
        var $pop = $(this).closest('.ts-pop');
        var buka = !$pop.hasClass('show');

        $('.ts-pop').removeClass('show');
        tutupDropdownBootstrap();
        $pop.toggleClass('show', buka);
        if (!buka) return;

        var $menu = $pop.find('.ts-pop-menu');
        $menu.css({ left: 0, right: 'auto' });
        var rect = $menu[0].getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) $menu.css({ left: 'auto', right: 0 });
    });

    $(document).on('click', function (e) {
        // Daftar centang tiap filter (.tcf-dropdown) dipasang di <body>, bukan di
        // dalam panel. Tanpa pengecualian ini, mencentang satu nilai dianggap
        // "klik di luar" sehingga panel Filter-nya ikut tertutup.
        if ($(e.target).closest('.ts-pop, .tcf-dropdown').length) return;
        $('.ts-pop').removeClass('show');
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('.ts-pop').removeClass('show');
    });

    /*
    | Popover Filter ini bukan dropdown Bootstrap, jadi keduanya tidak saling
    | tahu: Bootstrap menghentikan perambatan klik di tombolnya sendiri, maka
    | membuka "Kolom" tidak pernah menutup "Filter" (dan panelnya bertumpuk).
    | Keduanya ditutup satu sama lain di sini.
    */
    function tutupDropdownBootstrap() {
        $('.dropdown.show > [data-toggle="dropdown"], .dropdown.show > .dropdown-toggle').each(function () {
            $(this).dropdown('toggle');
        });
    }

    $(document).on('show.bs.dropdown', function () {
        $('.ts-pop').removeClass('show');
    });

    Api.initRowFilters = function (tableSelector, containerSelector, fields) {
        var $table = $(tableSelector);
        if ($table.length === 0) return;
        var tableEl = $table.get(0);
        var tableId = tableEl.id || (tableEl.id = 'tcf_' + Math.random().toString(36).slice(2));
        var table = $table.DataTable();
        var $container = $(containerSelector);

        rowFieldLabels[tableId] = rowFieldLabels[tableId] || {};
        rowFieldButtons[tableId] = rowFieldButtons[tableId] || {};

        /*
        | Inisialisasi ulang pada kontainer yang SAMA (mis. Cost Request yang
        | memuat ulang tabelnya setelah periode diganti) dulu menambah satu set
        | tombol baru di samping yang lama, sehingga panel Filter menampilkan
        | tiap field dua kali. Tombol lama milik kontainer ini dibuang dulu,
        | berikut filter tersimpan untuk field-nya — tabelnya sudah dibangun
        | ulang, jadi pilihan lama tidak lagi berlaku.
        */
        $container.find('.tcf-row-btn').remove();
        fields.forEach(function (f) {
            if (rowFilterState[tableId]) delete rowFilterState[tableId][f.key];
            delete rowFieldButtons[tableId][f.key];
        });
        renderChips(tableId, tableEl, null);

        fields.forEach(function (f) {
            rowFieldLabels[tableId][f.key] = { label: f.label, valueLabels: f.valueLabels };

            var $btn = $('<button type="button" class="btn btn-sm btn-outline-secondary tcf-row-btn"></button>')
                .html(escapeHtml(f.label) + ' <i class="fas fa-caret-down ml-1"></i>');
            $container.append($btn);
            rowFieldButtons[tableId][f.key] = $btn;

            // f.options (opsional) — daftar pilihan tetap. f.search: false
            // (opsional) — sembunyikan kotak cari untuk field berpilihan
            // sedikit. Tanpa keduanya, perilakunya tetap seperti sebelumnya.
            $btn.on('click', function (e) {
                e.stopPropagation();
                openRowFilterDropdown($btn, tableId, table, tableEl, f.key, f.label, f.valueLabels, f.options, f.search);
            });
        });

        // Lebih dari satu field: deretan tombolnya dilipat ke satu popover "Filter".
        if (fields.length > 1) bungkusFilterKePopover($container, tableSelector);

        // Handler global hanya dipasang sekali per tabel: tanpa penjaga ini,
        // tiap inisialisasi ulang menambah satu salinan lagi.
        if (!rowHooked[tableId]) {
            rowHooked[tableId] = true;
            $(document).on('click', '.tcf-chip-clear', function () {
                var $chip = $(this).closest('.tcf-chip');
                if ($chip.data('type') !== 'row') return;
                var field = $chip.data('field');
                if (rowFilterState[tableId]) delete rowFilterState[tableId][field];
                var $btn = rowFieldButtons[tableId] && rowFieldButtons[tableId][field];
                if ($btn) $btn.removeClass('tcf-row-active');
                table.draw();
                renderChips(tableId, tableEl, null);
            });

            $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
                if (settings.nTable.id !== tableId) return true;
                var tableState = rowFilterState[tableId];
                if (!tableState) return true;

                var row = settings.aoData[index].nTr;
                if (!row) return true;

                for (var field in tableState) {
                    if (!tableState.hasOwnProperty(field)) continue;
                    var selected = tableState[field];
                    var raw = (row.getAttribute('data-' + field) || '').toString();
                    if (!selected.has(raw)) return false;
                }
                return true;
            });
        }
    };

    // Reset semua filter (header kolom + row-attribute) untuk 1 tabel, tanpa reload halaman.
    // Tidak menyentuh kotak pencarian teks bebas maupun filter stepper workflow (lihat clearWorkflowStepper).
    Api.clearAll = function (tableSelector) {
        var $table = $(tableSelector);
        if ($table.length === 0) return;
        var tableEl = $table.get(0);
        var tableId = tableEl.id;
        if (!tableId) return;
        var table = $table.DataTable();

        delete state[tableId];
        delete rowFilterState[tableId];

        $table.find('.tcf-filter-icon').removeClass('tcf-active');
        $(tableEl).closest('.table-responsive').prev('.tcf-chip-row').empty().hide();
        $('.tcf-row-btn').removeClass('tcf-row-active');

        table.draw();
    };

    // Set 1 filter row-attribute secara programatis (mis. dari ?query= di URL),
    // efeknya sama persis seperti user mencentang salah satu opsi checklist —
    // chip & tombol ikut ter-highlight, tabel langsung ke-draw ulang. Dipanggil
    // SETELAH initRowFilters() untuk tabel yang sama.
    Api.setRowFilter = function (tableSelector, field, value) {
        var $table = $(tableSelector);
        if ($table.length === 0 || !value) return;
        var tableEl = $table.get(0);
        var tableId = tableEl.id;
        if (!tableId) return;
        var table = $table.DataTable();

        rowFilterState[tableId] = rowFilterState[tableId] || {};
        var selected = rowFilterState[tableId][field] || new Set();
        selected.add(value);
        rowFilterState[tableId][field] = selected;

        var $btn = rowFieldButtons[tableId] && rowFieldButtons[tableId][field];
        if ($btn) $btn.addClass('tcf-row-active');

        table.draw();
        renderChips(tableId, tableEl, null);
    };

    /* Dipanggil halaman yang punya saringan di luar filter ini (mis. tab).
       fn menerima <tr> dan mengembalikan true kalau baris itu masih relevan. */
    Api.setRowScope = function (tableId, fn) {
        rowScope[tableId.replace('#', '')] = fn;
    };

    window.TesysColumnFilter = Api;

    if (!document.getElementById('tcf-inline-style')) {
        var style = document.createElement('style');
        style.id = 'tcf-inline-style';
        style.textContent = '.tcf-filter-icon.tcf-active{opacity:1 !important;color:var(--accent,#d71920);} .tcf-row-btn.tcf-row-active{background:var(--accent,#d71920) !important;color:#fff !important;border-color:var(--accent,#d71920) !important;} .tcf-item{padding:4px 6px;border-radius:4px;margin:0 -6px;width:calc(100% + 12px);white-space:nowrap;} .tcf-item span{white-space:nowrap;} .tcf-item:hover{background:var(--surface-2,#f2f4f7);} .tcf-list{overflow-x:hidden !important;} .tcf-chip-row{display:flex;flex-wrap:wrap;max-width:100%;} .tcf-chip{max-width:100%;white-space:normal !important;word-break:break-word;}.ts-pop{position:relative;}.ts-pop-menu{display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:1080;background:var(--surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:var(--radius-md,10px);box-shadow:0 8px 24px rgba(0,0,0,.14);}.ts-pop.show .ts-pop-menu{display:block;}.tcf-pop-title{font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-faint,#8a8f98);margin-bottom:10px;}.tcf-pop-body{display:block !important;}.tcf-pop-grid{display:grid !important;grid-template-columns:repeat(2,minmax(0,1fr)) !important;gap:8px !important;align-items:start;width:100% !important;}.tcf-pop-grid .tcf-row-btn{width:100% !important;min-width:0;justify-content:space-between;text-align:left;overflow:hidden;}.tcf-pop-grid .tcf-row-btn > i{margin-left:6px;flex:0 0 auto;}.tcf-pop-foot{margin-top:12px;padding-top:10px;border-top:1px solid var(--border,#e5e7eb);}@media (max-width:640px){.ts-pop-menu{min-width:260px !important;max-width:calc(100vw - 32px);}.tcf-pop-grid{grid-template-columns:1fr !important;}}';
        document.head.appendChild(style);
    }
})(window, jQuery);
