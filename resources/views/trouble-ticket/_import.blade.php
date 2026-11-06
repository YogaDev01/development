{{-- Modal Import Excel di daftar tiket: unduh template, unggah & pratinjau,
     lalu konfirmasi. Pratinjau tidak menyimpan apa pun; server memeriksa ulang
     berkas saat konfirmasi (Concerns\ImportsTroubleTickets). --}}
<div class="modal fade" id="mdlTtImport" tabindex="-1" role="dialog" aria-labelledby="mdlTtImportTitle">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mdlTtImportTitle"><i class="fas fa-file-excel mr-1"></i> Import Trouble Ticket</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center mb-3" style="gap:8px;">
                    <a href="{{ route('trouble-ticket.import.template') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-download mr-1"></i> Unduh Template
                    </a>
                    <input type="file" id="ttImportFile" accept=".xlsx,.xls" class="form-control-file" style="max-width:320px;" aria-label="Berkas Excel">
                    <button type="button" class="btn btn-primary btn-sm" id="ttImportPreview" disabled><i class="fas fa-search mr-1"></i> Periksa</button>
                </div>

                <div class="alert alert-danger py-2 small" id="ttImportError" hidden></div>
                <div id="ttImportSummary" class="small mb-2" hidden></div>
                <div class="table-responsive" id="ttImportTableWrap" hidden>
                    <table class="table table-sm mb-0" data-ts-card="off">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:1%;">Baris</th>
                                <th scope="col" style="width:1%;">Status</th>
                                <th scope="col">Site</th>
                                <th scope="col">Teknisi</th>
                                <th scope="col">Keterangan</th>
                                <th scope="col">Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="ttImportRows"></tbody>
                    </table>
                </div>
                <div id="ttImportResult" hidden></div>
            </div>
            <div class="modal-footer">
                <label class="mb-0 mr-auto small" id="ttImportWarnWrap" hidden>
                    <input type="checkbox" id="ttImportWarn"> Sertakan baris yang mungkin duplikat
                </label>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success btn-sm" id="ttImportCommit" hidden></button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    var token = null, baris = [];
    var $file = $('#ttImportFile'), $preview = $('#ttImportPreview'), $commit = $('#ttImportCommit');
    var $err = $('#ttImportError'), $sum = $('#ttImportSummary'), $rows = $('#ttImportRows');
    var $warn = $('#ttImportWarn'), $warnWrap = $('#ttImportWarnWrap'), $hasil = $('#ttImportResult');
    var label = { ok: ['Valid', 'success'], peringatan: ['Periksa', 'warning'], gagal: ['Gagal', 'danger'] };

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    function kirim(url, tambahan) {
        var fd = new FormData();
        fd.append('file', $file[0].files[0]);
        fd.append('_token', @json(csrf_token()));
        Object.keys(tambahan || {}).forEach(function (k) { fd.append(k, tambahan[k]); });
        return $.ajax({ url: url, method: 'POST', data: fd, processData: false, contentType: false });
    }

    function pesanGagal(xhr) {
        var j = xhr.responseJSON || {};
        var v = j.errors ? Object.values(j.errors)[0][0] : null;
        $err.text(v || j.message || 'Permintaan gagal. Coba lagi.').prop('hidden', false);
    }

    function hitung() {
        var ok = baris.filter(function (r) { return r.status === 'ok'; }).length;
        var pw = baris.filter(function (r) { return r.status === 'peringatan'; }).length;
        var n = ok + ($warn.prop('checked') ? pw : 0);
        $commit.text('Buat ' + n + ' tiket').prop('hidden', false).prop('disabled', n === 0);
        return { ok: ok, pw: pw };
    }

    function reset() {
        token = null; baris = [];
        $err.prop('hidden', true); $sum.prop('hidden', true); $hasil.prop('hidden', true).empty();
        $('#ttImportTableWrap').prop('hidden', true); $commit.prop('hidden', true);
        $warnWrap.prop('hidden', true); $warn.prop('checked', false);
    }

    $file.on('change', function () { reset(); $preview.prop('disabled', !this.files.length); });
    $warn.on('change', hitung);

    $preview.on('click', function () {
        reset();
        $preview.prop('disabled', true);
        kirim(@json(route('trouble-ticket.import.preview'))).done(function (res) {
            token = res.token; baris = res.rows;
            $rows.html(baris.map(function (r) {
                var l = label[r.status];
                return '<tr><td>' + r.line + '</td><td><span class="badge badge-' + l[1] + '">' + l[0] + '</span></td>' +
                    '<td>' + esc(r.site) + '</td><td>' + esc(r.teknisi) + '</td><td>' + esc(r.keterangan) + '</td>' +
                    '<td>' + esc(r.messages.join(' ')) + '</td></tr>';
            }).join(''));
            $('#ttImportTableWrap').prop('hidden', false);
            var n = hitung();
            $warnWrap.prop('hidden', n.pw === 0);
            $sum.text(baris.length + ' baris: ' + n.ok + ' valid, ' + n.pw + ' perlu diperiksa, ' + (baris.length - n.ok - n.pw) + ' gagal.').prop('hidden', false);
        }).fail(pesanGagal).always(function () { $preview.prop('disabled', false); });
    });

    $commit.on('click', function () {
        $commit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Membuat tiket...');
        $err.prop('hidden', true);
        kirim(@json(route('trouble-ticket.import.commit')), { token: token, include_warnings: $warn.prop('checked') ? 1 : 0 }).done(function (res) {
            $('#ttImportTableWrap').prop('hidden', true); $sum.prop('hidden', true);
            $commit.prop('hidden', true); $warnWrap.prop('hidden', true); $preview.prop('disabled', true);
            var html = '<p class="mb-2"><strong>' + res.created.length + ' tiket dibuat</strong>, ' + res.failed.length + ' baris tidak dibuat.</p>';
            if (res.failed.length) {
                html += '<ul class="small mb-2">' + res.failed.map(function (f) { return '<li>Baris ' + f.line + ': ' + esc(f.message) + '</li>'; }).join('') + '</ul>';
                var csv = 'Baris;Catatan\n' + res.failed.map(function (f) { return f.line + ';"' + String(f.message).replace(/"/g, '""') + '"'; }).join('\n');
                html += '<a class="btn btn-outline-secondary btn-sm" download="import-tiket-gagal.csv" href="data:text/csv;charset=utf-8,' + encodeURIComponent('﻿' + csv) + '"><i class="fas fa-download mr-1"></i> Unduh baris gagal</a> ';
            }
            html += '<a class="btn btn-primary btn-sm" href="' + @json(route('trouble-ticket.index')) + '">Lihat daftar tiket</a>';
            $hasil.html(html).prop('hidden', false);
        }).fail(function (xhr) { pesanGagal(xhr); hitung(); });
    });

    // Tautan dari form Buat Tiket: ?import=1 langsung membuka modal.
    if (new URLSearchParams(location.search).get('import') === '1') $('#mdlTtImport').modal('show');
});
</script>
@endpush
