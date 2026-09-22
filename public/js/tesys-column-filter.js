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
    }

    function applyFilter(tableId, table, tableEl, columns) {
        table.draw();
        renderChips(tableId, tableEl, columns);
    }

    function openDropdown($icon, tableId, table, tableEl, colIdx, columns) {
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

    function openRowFilterDropdown($btn, tableId, table, tableEl, field, label, valueLabels, options, showSearch) {
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
        } else {
            var seen = {};
            table.rows({ search: 'none' }).nodes().each(function (tr) {
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

    Api.initRowFilters = function (tableSelector, containerSelector, fields) {
        var $table = $(tableSelector);
        if ($table.length === 0) return;
        var tableEl = $table.get(0);
        var tableId = tableEl.id || (tableEl.id = 'tcf_' + Math.random().toString(36).slice(2));
        var table = $table.DataTable();
        var $container = $(containerSelector);

        rowFieldLabels[tableId] = rowFieldLabels[tableId] || {};
        rowFieldButtons[tableId] = rowFieldButtons[tableId] || {};

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

    window.TesysColumnFilter = Api;

    if (!document.getElementById('tcf-inline-style')) {
        var style = document.createElement('style');
        style.id = 'tcf-inline-style';
        style.textContent = '.tcf-filter-icon.tcf-active{opacity:1 !important;color:var(--accent,#d71920);} .tcf-row-btn.tcf-row-active{background:var(--accent,#d71920) !important;color:#fff !important;border-color:var(--accent,#d71920) !important;} .tcf-item{padding:4px 6px;border-radius:4px;margin:0 -6px;width:calc(100% + 12px);white-space:nowrap;} .tcf-item span{white-space:nowrap;} .tcf-item:hover{background:var(--surface-2,#f2f4f7);} .tcf-list{overflow-x:hidden !important;} .tcf-chip-row{display:flex;flex-wrap:wrap;max-width:100%;} .tcf-chip{max-width:100%;white-space:normal !important;word-break:break-word;}';
        document.head.appendChild(style);
    }
})(window, jQuery);
