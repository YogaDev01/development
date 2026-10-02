/*
|------------------------------------------------------------------------------
| tesys-money-sort.js — pengurutan kolom nominal (format rupiah Indonesia)
|------------------------------------------------------------------------------
| Masalah: DataTables mengurutkan "1.000.000" sebagai teks, sehingga 900.000
| dianggap lebih besar dari 1.000.000. Plugin ini mendaftarkan tipe kolom baru
| "num-id": sel yang isinya angka berformat Indonesia (titik ribuan, koma
| desimal, boleh ada "Rp", tanda minus, atau kurung untuk nilai negatif)
| dikenali otomatis lalu diurutkan sebagai ANGKA.
|
| Dipasang global di layouts/app.blade.php supaya berlaku di seluruh modul
| tanpa perlu menyentuh tiap tabel. Kolom non-nominal tidak terpengaruh:
| tanggal (ada "/"), kode, dan teks status tidak lolos pola di bawah.
*/
(function ($) {
    'use strict';
    if (!$ || !$.fn || !$.fn.dataTable) return;

    var dt = $.fn.dataTable;

    // Pola nominal: opsional "Rp", opsional minus / kurung, digit dengan titik
    // ribuan, opsional koma desimal. Wajib ada minimal satu pemisah ribuan atau
    // simbol Rp — angka polos tetap ditangani tipe bawaan "num" DataTables.
    var POLA = /^\s*(\()?\s*-?\s*(?:Rp\.?\s*)?\d{1,3}(?:\.\d{3})+(?:,\d+)?\s*(\))?\s*$/;
    var POLA_RP = /^\s*(\()?\s*-?\s*Rp\.?\s*\d+(?:\.\d{3})*(?:,\d+)?\s*(\))?\s*$/;

    function keAngka(d) {
        if (d === null || d === undefined) return -Infinity;
        var s = (typeof d === 'string' ? d : String(d));
        // Buang tag HTML (sel nominal kadang dibungkus <strong>/<button>).
        s = s.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        if (s === '' || s === '-') return -Infinity;

        var negatif = /^\(.*\)$/.test(s) || s.indexOf('-') === 0;
        s = s.replace(/[()\s]/g, '').replace(/^-/, '').replace(/Rp\.?/i, '');
        s = s.replace(/\./g, '').replace(/,/g, '.');

        var n = parseFloat(s);
        if (isNaN(n)) return -Infinity;
        return negatif ? -n : n;
    }

    function bersih(d) {
        var s = (typeof d === 'string' ? d : String(d === null || d === undefined ? '' : d));
        return s.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
    }

    dt.ext.type.detect.unshift(function (d) {
        var s = bersih(d);
        if (s === '' || s === '-') return null;   // sel kosong tidak menentukan tipe
        return (POLA.test(s) || POLA_RP.test(s)) ? 'num-id' : null;
    });

    dt.ext.type.order['num-id-pre'] = keAngka;

    // Dipakai kolom yang ingin memaksa pengurutan nominal:
    // columnDefs: [{ targets: [3], type: 'num-id' }]
    dt.ext.type.search['num-id'] = function (d) { return bersih(d); };
})(window.jQuery);
