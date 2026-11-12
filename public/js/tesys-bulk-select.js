/* =====================================================================
   Tesys — pilih baris + bar aksi massal untuk DataTable daftar.
   Dipakai halaman yang belum punya bulk sendiri (Expense, Collection).
   Pola & batasannya mengikuti bulk Purchase Order (_index-assets):
     - centang hanya pada baris yang memang boleh diproses (checkbox dirender
       server hanya untuk baris layak, atribut data-stage = tahap),
     - satu tahap per proses (tidak mencampur Submitted & Verified),
     - maks N baris, pilihan ikut filter (baris yang tersaring keluar dilepas),
     - kirim sebagai form POST biasa; server mengecek ulang status & role.

   TesysBulk.wire({
       table:  DataTable API,
       max:    50,
       noun:   'Expense',
       stages: { VERIFIED: { label: 'Submitted', approve: { url, text },
                             reject: { url, text, ke } } },
   });
   Markup yang diharapkan: input.ts-bulk-check[value=id][data-stage=..] di
   baris, input.ts-bulk-all (opsional) di header, dan bar:
     .ts-bulk-bar > .ts-bulk-info + button.ts-bulk-approve + button.ts-bulk-reject + button.ts-bulk-clear
   ===================================================================== */
(function ($) {
    'use strict';
    if (!$ || window.TesysBulk) return;

    function warna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    function toast(tipe, pesan) {
        if (window.tsToast) window.tsToast(tipe, pesan);
        else if (window.Swal) Swal.fire({ icon: tipe, text: pesan });
    }

    function wire(opt) {
        var table = opt.table, max = opt.max || 50, noun = opt.noun || 'item';
        var sel = new Map();   // id -> stage
        var kirim = false;

        function semuaNode() { return $(table.rows().nodes()); }
        function tahapAktif() {
            var st = null;
            sel.forEach(function (v) { st = st || v; });
            return st;
        }
        function refresh() {
            var tampil = new Set();
            table.rows({ search: 'applied' }).every(function () {
                var cb = $(this.node()).find('.ts-bulk-check')[0];
                if (cb) tampil.add(String(cb.value));
            });
            sel.forEach(function (v, id) { if (!tampil.has(id)) sel.delete(id); });
            semuaNode().find('.ts-bulk-check').each(function () { this.checked = sel.has(String(this.value)); });

            var aktif = tahapAktif(), kandidat = 0, dipilih = 0;
            table.rows({ search: 'applied' }).every(function () {
                var cb = $(this.node()).find('.ts-bulk-check')[0];
                if (!cb) return;
                var st = cb.getAttribute('data-stage');
                if (!aktif) aktif = st;
                if (st === aktif) { kandidat++; if (sel.has(String(cb.value))) dipilih++; }
            });
            var all = $(table.table().header()).find('.ts-bulk-all')[0];
            if (all) {
                all.checked = kandidat > 0 && dipilih === kandidat;
                all.indeterminate = dipilih > 0 && dipilih < kandidat;
                all.disabled = kandidat === 0;
            }
            var jml = sel.size, cfg = opt.stages[tahapAktif()] || {};
            var $bar = $('.ts-bulk-bar');
            $bar.toggleClass('d-none', jml === 0).toggleClass('d-flex', jml > 0);
            $bar.find('.ts-bulk-info').text(jml + ' dipilih' + (cfg.label ? ' · ' + cfg.label : ''));
            $bar.find('.ts-bulk-approve').toggle(!!cfg.approve).find('.ts-bulk-approve-label').text(cfg.approve ? cfg.approve.text : '');
            $bar.find('.ts-bulk-reject').toggle(!!cfg.reject).find('.ts-bulk-reject-label').text(cfg.reject ? cfg.reject.text : '');
        }

        $(document).on('change', '.ts-bulk-check', function () {
            var id = String(this.value), st = this.getAttribute('data-stage');
            if (this.checked) {
                var aktif = tahapAktif();
                if (aktif && aktif !== st) {
                    this.checked = false;
                    toast('warning', 'Pilih ' + noun + ' dengan tahap yang sama dalam satu proses.');
                    return;
                }
                if (sel.size >= max) {
                    this.checked = false;
                    toast('warning', 'Maksimal ' + max + ' ' + noun + ' per proses.');
                    return;
                }
                sel.set(id, st);
            } else {
                sel.delete(id);
            }
            refresh();
        });

        $(document).on('change', '.ts-bulk-all', function () {
            if (!this.checked) { sel.clear(); refresh(); return; }
            var aktif = tahapAktif(), calon = [];
            table.rows({ search: 'applied' }).every(function () {
                var cb = $(this.node()).find('.ts-bulk-check')[0];
                if (!cb) return;
                var st = cb.getAttribute('data-stage');
                if (!aktif) aktif = st;
                if (st === aktif && !sel.has(String(cb.value))) calon.push([String(cb.value), st]);
            });
            if (sel.size + calon.length > max) {
                sel.clear(); refresh();
                toast('warning', 'Maksimal ' + max + ' ' + noun + ' per proses. Persempit dengan filter lalu pilih lagi.');
                return;
            }
            calon.forEach(function (x) { sel.set(x[0], x[1]); });
            refresh();
        });

        $(document).on('click', '.ts-bulk-clear', function () { sel.clear(); refresh(); });
        table.on('draw', refresh);

        $(document).on('click', '.ts-bulk-approve, .ts-bulk-reject', function () {
            if (kirim) return;
            var tolak = $(this).hasClass('ts-bulk-reject'), $btn = $(this);
            var cfg = opt.stages[tahapAktif()], aksi = cfg && (tolak ? cfg.reject : cfg.approve);
            if (!aksi || !sel.size) return;
            var ids = Array.from(sel.keys()), jml = ids.length;

            var konfirmasi = {
                title: aksi.text + ' ' + jml + ' ' + noun + '?',
                html: '<div style="text-align:left;font-size:13px;">' + jml + ' ' + noun + ' tahap <strong>' + (cfg.label || '') + '</strong>'
                    + (aksi.ke ? ' &rarr; ' + aksi.ke : '') + '. Setiap ' + noun + ' diproses satu per satu; yang statusnya sudah berubah akan dilewati.</div>'
                    + (tolak ? '<div class="mt-2" style="text-align:left;font-size:12px;">Alasan wajib diisi (berlaku untuk semua):</div>' : ''),
                icon: tolak ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: tolak ? warna('--danger', '#9a3030') : warna('--accent', '#92232b'),
                cancelButtonColor: warna('--text-muted', '#6a6e72'),
                confirmButtonText: 'Ya, ' + aksi.text,
                cancelButtonText: 'Batal',
            };
            if (tolak) {
                konfirmasi.input = 'textarea';
                konfirmasi.inputAttributes = { maxlength: 500, 'aria-label': 'Alasan' };
                konfirmasi.inputValidator = function (v) { if (!v || !v.trim()) return 'Alasan wajib diisi.'; };
            }

            Swal.fire(konfirmasi).then(function (r) {
                if (!r.isConfirmed || kirim) return;
                kirim = true;
                var $f = $('<form method="POST"></form>').attr('action', aksi.url);
                $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
                ids.forEach(function (id) { $f.append($('<input type="hidden" name="ids[]">').val(id)); });
                if (tolak) $f.append($('<input type="hidden" name="' + (aksi.field || 'notes') + '">').val(r.value));
                if (aksi.extra) Object.keys(aksi.extra).forEach(function (k) { $f.append($('<input type="hidden">').attr('name', k).val(aksi.extra[k])); });
                $('.ts-bulk-bar button').prop('disabled', true);
                if (window.TesysUI && TesysUI.busy) TesysUI.busy($btn[0], true);
                $('body').append($f);
                $f[0].submit();
            });
        });

        // Kembali lewat tombol Back (bfcache): kunci kirim dilepas lagi.
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            kirim = false;
            $('.ts-bulk-bar button').prop('disabled', false);
        });

        refresh();
    }

    window.TesysBulk = { wire: wire };
})(window.jQuery);
