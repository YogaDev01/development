/* =====================================================================
   Tesys — struktur baku tabel daftar (DataTable)
   ---------------------------------------------------------------------
   Satu pintu untuk halaman daftar yang barisnya sudah dirender server:
   sortir kolom, pencarian, filter daftar-centang per field, pagination
   + ukuran halaman, info jumlah data, keadaan kosong, dan tombol hapus
   filter. Mesinnya DataTables + TesysColumnFilter (sama dengan Cost
   Request & Cash Management); skrip ini hanya merangkainya supaya tiap
   halaman cukup memanggil satu fungsi.

   Pemakaian:
     TesysListTable.init({
         table:   '#tblX',                         // wajib
         order:   [[0, 'desc']],                   // opsional
         pageLength: 25,                           // opsional
         search:  '#xSearch',                      // kotak cari (opsional)
         clear:   '#xClear',                       // tombol hapus filter (opsional)
         filters: [                                // opsional
             { target: '#xFilterStatus', key: 'status', label: 'Semua Status',
               valueLabels: { DRAFT: 'Draft' }, search: false }
         ],
         columnDefs: [{ orderable: false, targets: -1 }]   // opsional
     });

   Syarat markup:
     - thead satu baris; tbody tanpa baris colspan selain keadaan kosong
       (x-empty / .ts-empty — dilewati otomatis).
     - Filter membaca atribut data-<key> pada tiap <tr>.
     - Kolom tanggal/nominal memakai data-order="<nilai urut>" pada <td>
       supaya sortir tidak mengikuti teks ("05 Nov 2026" / "Rp 1.000").
   Mengembalikan instance DataTable, atau null bila tabel kosong/gagal
   dipasang (halaman tetap hidup).
   ===================================================================== */
(function (window, $) {
    'use strict';
    if (!$ || !$.fn || !$.fn.dataTable) return;
    if (window.TesysListTable) return;

    var DEFAULTS = {
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthChange: true,
        // searching wajib true: kotak cari dan filter baris sama-sama lewat
        // mesin pencarian DataTables. Kotak bawaannya tidak tampil ('f' tidak
        // ada di dom) karena halaman menyediakan kotak cari sendiri.
        searching: true,
        ordering: true,
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_ data)',
            lengthMenu: 'Page Size _MENU_',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    };

    function init(opt) {
        opt = opt || {};
        var $t = $(opt.table);
        if (!$t.length) return null;

        // Tabel kosong = satu sel colspan; DataTables melempar "Incorrect column
        // count" dan mematikan seluruh skrip halaman bila dipasang di sini.
        if (!$t.find('tbody tr').length || $t.find('tbody .ts-empty').length) return null;

        var dt;
        try {
            dt = $t.DataTable($.extend(true, {}, DEFAULTS, {
                order: opt.order || [],
                pageLength: opt.pageLength || DEFAULTS.pageLength,
                columnDefs: opt.columnDefs || []
            }));
        } catch (e) {
            if (window.console) console.error(e);
            return null;
        }

        if (window.TesysColumnFilter && opt.filters) {
            $.each(opt.filters, function (_, f) {
                if (!$(f.target).length) return;
                var spec = { key: f.key, label: f.label || 'Semua' };
                if (f.valueLabels) spec.valueLabels = f.valueLabels;
                if (f.search === false) spec.search = false;
                window.TesysColumnFilter.initRowFilters(opt.table, $(f.target), [spec]);
            });
        }

        if (opt.search) {
            $(opt.search).on('input', function () {
                dt.search(this.value).draw();
            });
        }

        if (opt.clear) {
            $(opt.clear).on('click', function () {
                if (opt.search) $(opt.search).val('');
                dt.search('');
                if (window.TesysColumnFilter) window.TesysColumnFilter.clearAll(opt.table);
                dt.draw();
            });
        }

        return dt;
    }

    window.TesysListTable = { init: init };
})(window, window.jQuery);
