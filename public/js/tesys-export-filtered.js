/*
|--------------------------------------------------------------------------
| TesysExportFiltered — "Export Excel" mengikuti filter yang sedang aktif
|--------------------------------------------------------------------------
|
| Masalahnya: tabel index disaring di sisi klien (kotak cari, dropdown filter
| kolom, stepper status). Tautan export adalah GET biasa ke server, yang tidak
| tahu apa-apa soal filter itu — jadi user menyaring sampai tersisa 5 baris,
| menekan Export, lalu mendapat seluruh 2.000 baris.
|
| Pola yang dipakai di sini sama persis dengan yang sudah ada di halaman Cost
| Request, sekarang dijadikan satu berkas supaya tiap halaman tidak menyalin
| ulang logikanya (dan cepat atau lambat menyalin salah):
|
|   - Tidak ada filter aktif (baris lolos == seluruh baris) -> biarkan GET
|     berjalan apa adanya. Ini penting: daftar yang dipotong oleh batas baris
|     (lihat Concerns\CapsLargeLists) tetap harus bisa diexport UTUH, bukan
|     cuma sebanyak yang kebetulan ter-render.
|   - Ada filter aktif -> POST daftar id baris yang lolos. Dikirim sebagai POST
|     karena jumlahnya bisa ribuan dan tidak muat di query string.
|   - DataTable gagal init / error apa pun -> jatuh kembali ke GET biasa.
|     Export yang mengambil kelebihan data masih berguna; export yang gagal
|     sama sekali tidak.
|
| Sisi server cukup membaca input 'ids' (array). Kalau kosong, perlakukan
| seperti export biasa.
*/
(function (window, $) {
    'use strict';

    var Api = {};

    /**
     * @param {string}  buttonSelector  Tombol/tautan export.
     * @param {Object}  table           Instance DataTable.
     * @param {string}  postUrl         URL tujuan POST (boleh sama dengan href tombol).
     * @param {Object} [options]
     * @param {string} [options.idAttr='id']  Nama data-attribute pada <tr> yang memuat id baris.
     */
    Api.wire = function (buttonSelector, table, postUrl, options) {
        var opts   = options || {};
        var idAttr = opts.idAttr || 'id';
        var $btn   = $(buttonSelector);

        if ($btn.length === 0 || !table) return;

        $btn.on('click', function (e) {
            e.preventDefault();

            var href = $btn.attr('href') || postUrl;

            try {
                var total    = table.rows().count();
                var filtered = table.rows({ search: 'applied' }).count();

                // Tanpa filter tambahan: export penuh lewat GET (lihat catatan di atas).
                if (filtered === total) {
                    window.location.href = href;
                    return;
                }

                var ids = [];
                table.rows({ search: 'applied' }).every(function () {
                    var id = $(this.node()).data(idAttr);
                    if (id !== undefined && id !== null && id !== '') ids.push(id);
                });

                // Baris lolos filter tapi tidak satupun punya id — tidak ada yang
                // bisa dikirim; lebih baik export penuh daripada berkas kosong.
                if (ids.length === 0) {
                    window.location.href = href;
                    return;
                }

                var $f = $('<form method="POST"></form>').attr('action', postUrl);
                $f.append($('<input type="hidden" name="_token">')
                    .val($('meta[name="csrf-token"]').attr('content')));
                ids.forEach(function (id) {
                    $f.append($('<input type="hidden" name="ids[]">').val(id));
                });
                $('body').append($f);
                $f[0].submit();
                $f.remove();
            } catch (err) {
                window.location.href = href;
            }
        });
    };

    window.TesysExportFiltered = Api;
})(window, jQuery);
