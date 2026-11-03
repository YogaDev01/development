/* =====================================================================
   Tesys — Treasury batch (halaman show + drawer daftar Treasury)
   ---------------------------------------------------------------------
   Perilaku bersama untuk partial daily-transaction/partials/items.blade.php
   dan partials/action-modals.blade.php:
     - expand/collapse grup "Daftar CR dalam Batch" + modal View Budget
     - Submit / Hapus draft / Keluarkan item draft (konfirmasi Swal)
     - Takeout / Reject satu baris (modal #mdlRejectItem)
     - Tandai PAID (modal #mdlMarkPaid, lampiran klik / drag-drop / tempel)

   Id batch dibaca dari [data-batch-id] terdekat. Bila window.TESYS_DT_FROM_INDEX
   bernilai true (halaman daftar), setiap form membawa from_index=1 sehingga
   controller kembali ke daftar (drawer dibuka ulang lewat ?open=), bukan ke
   halaman show. Tidak menyentuh aturan bisnis — semua dicek ulang di server.
   ===================================================================== */
$(function () {
    'use strict';

    var BASE = '/daily-transaction';

    function dariDaftar() { return window.TESYS_DT_FROM_INDEX === true; }

    function tandaiDaftar($form) {
        $form.find('input[name="from_index"]').remove();
        if (dariDaftar()) $form.append('<input type="hidden" name="from_index" value="1">');
    }

    function idBatch(el) { return $(el).closest('[data-batch-id]').data('batch-id'); }

    function warnaToken(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }

    function kirimForm(url, metode) {
        var $f = $('<form method="POST"></form>').attr('action', url);
        $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
        if (metode && metode !== 'POST') $f.append($('<input type="hidden" name="_method">').val(metode));
        tandaiDaftar($f);
        $('body').append($f);
        $f[0].submit();
    }

    /* ── DRAFT BATCH: Submit / Hapus / Keluarkan item ── */
    $(document).on('click', '.btn-submit-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        Swal.fire({
            title: 'Submit batch ' + code + '?', icon: 'question',
            text: 'Batch diajukan ke CEO untuk approval. Setelah di-submit, Cost Request di dalamnya menjadi QUEUED dan isi batch tidak bisa diubah langsung oleh Treasury.',
            showCancelButton: true, confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal',
            confirmButtonColor: warnaToken('--accent', '#92232b'), cancelButtonColor: warnaToken('--text-muted', '#6a6e72')
        }).then(function (r) { if (r.isConfirmed) kirimForm(BASE + '/' + id + '/submit', 'POST'); });
    });

    $(document).on('click', '.btn-delete-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        Swal.fire({
            title: 'Hapus draft ' + code + '?', icon: 'warning',
            text: 'Draft batch beserta daftar itemnya dihapus. Cost Request di dalamnya tetap APPROVED dan bisa dimasukkan ke batch lain.',
            showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal',
            confirmButtonColor: warnaToken('--danger', '#9a3030'), cancelButtonColor: warnaToken('--text-muted', '#6a6e72')
        }).then(function (r) {
            // Hapus draft selalu kembali ke daftar polos: batchnya sudah tidak ada.
            if (r.isConfirmed) {
                var $f = $('<form method="POST"></form>').attr('action', BASE + '/' + id);
                $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
                $f.append('<input type="hidden" name="_method" value="DELETE">');
                $('body').append($f);
                $f[0].submit();
            }
        });
    });

    $(document).on('click', '.btn-draft-remove-item', function () {
        var batchId = idBatch(this), itemId = $(this).data('item-id'), code = $(this).data('code');
        Swal.fire({
            title: 'Keluarkan ' + code + '?', icon: 'warning',
            text: 'Baris ini dikeluarkan dari draft batch. Statusnya tidak berubah dan bisa dimasukkan ke batch lain.',
            showCancelButton: true, confirmButtonText: 'Ya, Keluarkan', cancelButtonText: 'Batal',
            confirmButtonColor: warnaToken('--danger', '#9a3030'), cancelButtonColor: warnaToken('--text-muted', '#6a6e72')
        }).then(function (r) { if (r.isConfirmed) kirimForm(BASE + '/' + batchId + '/draft-remove-item/' + itemId, 'POST'); });
    });

    /* ── DAFTAR CR DALAM BATCH — grup per Budget Plan/PO ── */
    $(document).on('click', '.dt-items-wrap .dt-group-row', function () {
        var target = $(this).data('target');
        var $rows = $(this).closest('tbody').find('.' + target);
        var expanding = $rows.first().is(':hidden');
        $rows.toggle(expanding);
        $(this).find('.dt-caret').toggleClass('rotated', expanding);
    });

    $(document).on('click', '.dt-items-wrap .dt-toggle-all', function () {
        var $btn = $(this), $wrap = $btn.closest('.dt-items-wrap');
        var expanding = $btn.data('state') !== 'expanded';
        $wrap.find('.dt-group-row').each(function () {
            $wrap.find('.' + $(this).data('target')).toggle(expanding);
            $(this).find('.dt-caret').toggleClass('rotated', expanding);
        });
        $btn.data('state', expanding ? 'expanded' : 'collapsed');
        $btn.html(expanding ? '<i class="fas fa-compress-alt mr-1"></i>Collapse All' : '<i class="fas fa-expand-alt mr-1"></i>Expand All');
    });

    /* ── MODAL VIEW BUDGET — Chart of Account & list CR di dalamnya ── */
    $(document).on('click', '.dt-view-budget', function (e) {
        e.stopPropagation();
        $($(this).data('target')).modal('show');
    });

    $(document).on('click', '.dt-coa-row', function () {
        var target = $(this).data('target');
        var $rows = $('.' + target);
        var expanding = $rows.first().is(':hidden');
        if (expanding) {
            $rows.show();
        } else {
            // data-target ada di tombol .dt-si-toggle DI DALAM baris sub-item, jadi
            // dicari lewat .find() supaya daftar "N CR" yang terbuka ikut ditutup.
            $rows.find('.dt-si-toggle').each(function () {
                var t = $(this).data('target');
                if (t) $('.' + t).hide();
                $(this).find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });
            $rows.hide();
        }
        $(this).find('.dt-coa-caret').toggleClass('rotated', expanding);
    });

    $(document).on('click', '.dt-si-toggle', function (e) {
        e.stopPropagation();
        $('.' + $(this).data('target')).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    /* ── TAKEOUT / REJECT satu baris ── */
    $(document).on('click', '.btn-takeout-cr-item', function (e) {
        e.stopPropagation();
        var batchId = idBatch(this), itemId = $(this).data('item-id'), code = $(this).data('code');
        var isReject = $(this).data('mode') === 'reject';
        var $f = $('#frmRejectItem');
        $f.attr('action', BASE + '/' + batchId + '/' + (isReject ? 'reject-cr-item/' : 'reject-item/') + itemId);
        $f.find('textarea[name="notes"]').val('').attr('placeholder', isReject ? 'Alasan reject...' : 'Alasan takeout...');
        tandaiDaftar($f);
        $('#rejectItemTitle').text(isReject ? 'Reject CR' : 'Takeout CR');
        $('#rejectItemHint').text(isReject
            ? 'CR berstatus REJECTED (Second Payment: CR induk menjadi Settle Rejected).'
            : 'CR dikembalikan ke status APPROVED dan bisa dimasukkan ke batch lain.');
        $('#btnRejectItemSubmit').html('<i class="fas fa-times mr-1"></i> ' + (isReject ? 'Reject' : 'Takeout'));
        $('#rejectItemCode').text(code);
        $(this).closest('.modal').modal('hide');
        $('#mdlRejectItem').modal('show');
    });

    /* ── MARK PAID ── */
    var attInput = document.getElementById('inpAttachments');
    if (!attInput) return;
    var attFiles = new DataTransfer();

    $(document).on('click', '.btn-mark-paid', function () {
        var $f = $('#frmMarkPaid');
        $f.attr('action', $(this).data('action'));
        tandaiDaftar($f);
        $('#markPaidInfo').text($(this).data('info') || '');
        $('#mdlMarkPaid').modal('show');
    });

    $('#inpMarkPaidCharge').on('input', function () {
        var v = (this.value || '').replace(/[^\d]/g, '');
        this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });

    function fmtSize(b) {
        return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    }

    function renderAttFiles() {
        attInput.files = attFiles.files;
        var $ul = $('#attFileList').empty();
        $.each(attFiles.files, function (i, f) {
            var $li = $('<li class="d-flex align-items-center py-1 px-2 mb-1" style="gap:8px;border:1px solid var(--border);border-radius:var(--radius-md);font-size:12px;"></li>');
            $('<i class="fas ' + (/pdf/i.test(f.type) ? 'fa-file-pdf' : 'fa-file-image') + '" style="color:var(--text-muted);"></i>').appendTo($li);
            $('<span class="flex-grow-1 text-truncate"></span>').text(f.name).appendTo($li);
            $('<span class="text-muted text-nowrap"></span>').text(fmtSize(f.size)).appendTo($li);
            $('<button type="button" class="btn btn-xs btn-link text-danger p-0 btn-remove-att" title="Hapus"><i class="fas fa-times"></i></button>')
                .attr('data-index', i).appendTo($li);
            $ul.append($li);
        });
    }

    function addAttFiles(files) {
        var okExt = /\.(pdf|jpe?g|png)$/i, okType = /^(application\/pdf|image\/(jpeg|png))$/, rejected = [];
        $.each(files, function (_, f) {
            if (!okExt.test(f.name) && !okType.test(f.type)) { rejected.push(f.name + ' (format tidak didukung)'); return; }
            if (f.size > 10 * 1024 * 1024) { rejected.push(f.name + ' (lebih dari 10 MB)'); return; }
            attFiles.items.add(f);
        });
        renderAttFiles();
        if (rejected.length) alert('File ditolak:\n' + rejected.join('\n'));
    }

    $(document).on('click', '.btn-remove-att', function () {
        var idx = parseInt($(this).attr('data-index'), 10), next = new DataTransfer();
        $.each(attFiles.files, function (i, f) { if (i !== idx) next.items.add(f); });
        attFiles = next;
        renderAttFiles();
    });

    $('#attDropzone')
        .on('click', function () { attInput.click(); })
        .on('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); attInput.click(); } })
        .on('dragover dragenter', function (e) {
            e.preventDefault();
            $(this).css('border-color', 'var(--accent)');
        })
        .on('dragleave drop', function () { $(this).css('border-color', ''); })
        .on('drop', function (e) {
            e.preventDefault();
            var dt = e.originalEvent.dataTransfer;
            if (dt && dt.files.length) addAttFiles(dt.files);
        });

    // Pilihan dari dialog file ditambahkan ke daftar, bukan menggantikannya.
    $(attInput).on('change', function () {
        var picked = Array.prototype.slice.call(attInput.files);
        attInput.files = attFiles.files;
        addAttFiles(picked);
    });

    $('#mdlMarkPaid').on('paste', function (e) {
        var cd = e.originalEvent.clipboardData;
        if (!cd || !cd.files || !cd.files.length) return;
        e.preventDefault();
        addAttFiles(cd.files);
    }).on('hidden.bs.modal', function () {
        attFiles = new DataTransfer();
        renderAttFiles();
    });

    $('#frmMarkPaid').on('submit', function (e) {
        if (!attFiles.files.length) {
            e.preventDefault();
            alert('Upload minimal 1 file bukti pembayaran.');
        }
    });
});
