{{-- Modal Detail Cost Request — dipakai ulang di luar halaman Cost Request (mis. Project Monitoring). --}}
<div class="modal fade" id="mdlDetailCR" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            {{-- Tidak ada pita header di sini: judul, kode, dan tombol tutup
                 pindah ke kepala panel ringkas gelap. Lihat modal detail Cost
                 Request untuk pola yang sama. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface-2);">
                <div id="crDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent);"></i>
                </div>
                <div id="crDetailContent" style="display:none;">
                    <div class="row no-gutters">
                        <div class="col-md-3 dm-side" style="background:var(--charcoal);padding:28px 20px;min-height:420px;">

                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="crCode" class="dm-code"></div>
                                <h5 class="dm-title" id="crModalTitle">Detail CR</h5>
                                <small id="crModalSubtitle" class="dm-sub-hidden"></small>

                                <div class="dm-badges">
                                    <span id="crStatusBadge"></span>
                                    <span id="crNobBadge"></span>
                                </div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Tipe</span><div id="crType" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Pengajuan</span><div id="crAmount" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Realisasi</span><div id="crRealizationAmount" style="color:var(--success);font-size:14px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span><div id="crBeneficiary" class="text-white mt-1" style="font-size:12px;"></div><div id="crBank" style="color:var(--text-faint);font-size:11px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Requestor</span><div id="crApplicant" class="text-white mt-1" style="font-size:12px;"></div></div>
                        </div>
                        <div class="col-md-9" style="padding:20px;">
                            <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-list-ul mr-1"></i> Rincian <span id="crDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneRealization" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-receipt mr-1"></i> Realisasi <span id="crRealizationCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneAttachments" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-paperclip mr-1"></i> Lampiran <span id="crAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-history mr-1"></i> Log</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="crPaneInfo">
                                    <table class="table table-bordered mb-0" style="font-size:12px;">
                                        <tr><th width="30%" style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Kode</th><td id="infoCode"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Deskripsi</th><td id="infoDesc"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Tipe</th><td id="infoType"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Scope of Work</th><td id="infoProject"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Project</th><td id="infoCluster"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Budget Plan</th><td id="infoBudgetPlan"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Total</th><td id="infoAmount"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Status</th><td id="infoStatus"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Email Notif</th><td id="infoEmail" style="word-break:break-all;"></td></tr>
                                        <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Notes</th><td id="infoNotes" style="white-space:pre-line;"></td></tr>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="crPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:4%;">No</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Deskripsi</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Notes</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:13%;">Satuan</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:6%;">Qty</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:var(--surface-2);"><th colspan="5" class="text-right">Total</th><th class="text-right" id="crDetailTotal" style="color:var(--info);"></th></tr></tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneRealization">
                                    <div id="crNoRealization" class="text-center text-muted py-4" style="display:none;">
                                        <i class="fas fa-receipt d-block mb-2"></i> Belum ada data realisasi.
                                    </div>
                                    <div id="crRealizationWrapper">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                                <thead style="background:var(--surface-2);">
                                                    <tr>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;width:4%;">No</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;">CR Detail Item</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:16%;">Total CR</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:16%;">Actual Used</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;width:20%;">Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="crRealizationBody"></tbody>
                                                <tfoot><tr style="background:var(--surface-2);"><th colspan="3" class="text-right">Total Realisasi</th><th class="text-right" id="crRealizationTotal" style="color:var(--success);"></th><th></th></tr></tfoot>
                                            </table>
                                        </div>
                                        <div id="crRealizationAtts" class="mt-3"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneAttachments">
                                    <div id="crAttBody"></div>
                                </div>
                                <div class="tab-pane fade" id="crPaneLogs">
                                    <div id="crLogBody"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    function setTabCount(sel, n) {
        var el = $(sel);
        if (n && n > 0) { el.text(n).show(); }
        else { el.text('').hide(); }
    }

    /* Peta status berasal dari App\Support\WorkflowStatus — sumber yang sama
       dengan tabel, drawer, dan halaman show. Peta lama di sini hanya meliput
       11 status: SETTLE_CHECKED dan SETTLE_VERIFIED jatuh ke abu-abu tanpa
       label yang benar. */
    var crStatusMeta = @json(collect(\App\Models\BudgetRequest::STATUSES)->mapWithKeys(fn ($st) => [
        $st => \App\Support\WorkflowStatus::meta($st, 'budget-request'),
    ]));

    function crStatusBadge(kode, labelCadangan) {
        var m = crStatusMeta[kode] || { label: labelCadangan || kode, tone: 'neutral', description: null };
        var judul = m.description ? m.label + ' — ' + m.description : m.label;
        var el = $('<span>').addClass('ts-status ts-status-' + m.tone + ' ts-status-sm')
                   .attr('title', judul).text(m.label);
        return el.prop('outerHTML');
    }

    $(document).on('click', '.btn-detail-cr', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#crDetailLoading').show(); $('#crDetailContent').hide();
        $('#mdlDetailCR').modal('show');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#crModalTitle').text(d.description);
            $('#crModalSubtitle').text(d.code);
            $('#crCode').text(d.code);
            $('#crStatusBadge').html(crStatusBadge(d.status, d.status_label));
            $('#crNobBadge').html(d.is_nob ? '<span class="badge badge-danger px-2 py-1 mt-1">NOB</span>' : '');
            $('#crType').text(d.type);
            $('#crAmount').text(d.amount_formatted);
            $('#crRealizationAmount').text(d.total_realization_formatted || '0');
            $('#crBeneficiary').text(d.beneficiary);
            $('#crBank').text(d.bank + ' — ' + d.account_number);
            $('#crApplicant').text(d.applicant);

            $('#infoCode').html('<span class="badge badge-dark px-2 py-1">' + d.code + '</span>');
            $('#infoDesc').html(d.is_nob ? '<span class="badge badge-danger mr-1" style="font-size:9px;">NOB</span>' + d.description : d.description);
            if (d.ticket) {
                var tiketTeks = 'Tiket ' + d.ticket.number + (d.ticket.request ? ' · ' + d.ticket.request : '');
                $('#infoDesc').append(' ', d.ticket.url
                    ? $('<a class="badge badge-light border ml-1">').attr('href', d.ticket.url).text(tiketTeks)
                    : $('<span class="badge badge-light border ml-1">').text(tiketTeks));
            }
            $('#infoType').html('<span class="badge badge-info px-2 py-1">' + d.type + '</span>');
            $('#infoProject').text(d.project);
            $('#infoCluster').text(d.cluster);
            $('#infoBudgetPlan').text(d.budget_plan);
            $('#infoAmount').html('<strong style="color:var(--info);">' + d.amount_formatted + '</strong>');
            $('#infoStatus').html(crStatusBadge(d.status, d.status_label));
            $('#infoEmail').text(d.email || '-');
            $('#infoNotes').text(d.notes || '-');

            setTabCount('#crDetailCount', d.details.length);
            var dHtml = '', grand = 0;
            d.details.forEach(function(det, i) {
                var isTakeout = !!det.is_takeout;
                if (!isTakeout) grand += det.total;
                var isNob    = !!det.is_nob; // kolom, bukan tebakan dari teks catatan
                var rowStyle = isTakeout ? ' style="color:var(--text-faint);"' : (isNob ? ' style="color:var(--danger);"' : '');
                var strike   = isTakeout ? 'text-decoration:line-through;' : '';
                var badge    = isTakeout ? ' <span class="badge badge-warning" style="font-size:9px;">Takeout &middot; ' + (det.takeout_stage_label || '-') + '</span>' : '';
                dHtml += '<tr' + rowStyle + '><td class="text-center" style="' + strike + '">' + (i+1) + '</td><td style="' + strike + '">' + det.description + badge + '</td><td style="' + strike + '">' + (det.notes || '-') + '</td><td class="text-right" style="' + strike + '">' + det.amount_formatted + '</td><td class="text-center" style="' + strike + '">' + det.qty + '</td><td class="text-right" style="' + strike + '"><strong>' + det.total_formatted + '</strong></td></tr>';
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada rincian.</td></tr>');
            $('#crDetailTotal').text(grand.toLocaleString('id-ID'));

            setTabCount('#crRealizationCount', d.realizations.length);
            if (d.realizations.length) {
                $('#crNoRealization').hide();
                $('#crRealizationWrapper').show();
                var rHtml = '', rGrand = 0;
                d.realizations.forEach(function(r, i) {
                    rGrand += r.total;
                    rHtml += '<tr><td class="text-center">' + (i+1) + '</td><td>' + r.cr_item + '</td><td class="text-right">' + r.total_cr_formatted + '</td><td class="text-right"><strong>' + r.actual_formatted + '</strong></td><td>' + (r.notes || '-') + '</td></tr>';
                });
                $('#crRealizationBody').html(rHtml);
                $('#crRealizationTotal').text(rGrand.toLocaleString('id-ID'));

                var raHtml = '';
                if (d.realization_attachments && d.realization_attachments.length) {
                    raHtml = '<div class="mt-2"><strong style="font-size:12px;">Lampiran Realisasi:</strong>';
                    d.realization_attachments.forEach(function(a) {
                        raHtml += '<div class="d-flex align-items-center mt-1" style="gap:8px;"><span style="font-size:12px;">' + a.original_name + '</span><a href="/storage/' + a.path + '" data-evidence="crd-real" data-evidence-name="' + String(a.original_name).replace(/"/g, '&quot;') + '" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a></div>';
                    });
                    raHtml += '</div>';
                }
                $('#crRealizationAtts').html(raHtml);
            } else {
                $('#crNoRealization').show();
                $('#crRealizationWrapper').hide();
            }

            var atts = d.attachments || [];
            setTabCount('#crAttCount', atts.length);
            var attHtml = '';
            if (atts.length) {
                attHtml += '<div class="table-responsive"><table class="table table-bordered mb-3" style="font-size:12px;"><tbody>';
                atts.forEach(function(a, i) {
                    attHtml += '<tr><td width="5%" class="text-center">' + (i+1) + '</td><td>' + a.original_name + '</td><td width="10%" class="text-center"><a href="/storage/' + a.path + '" data-evidence="crd-req" data-evidence-name="' + String(a.original_name).replace(/"/g, '&quot;') + '" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a></td></tr>';
                });
                attHtml += '</tbody></table></div>';
            }
            $('#crAttBody').html(attHtml || '<p class="text-muted py-3 mb-0">Belum ada lampiran.</p>');

            var logHtml = '';
            d.logs.forEach(function(log) {
                logHtml += '<div class="d-flex align-items-start mb-3" style="gap:10px;">' +
                    '<span class="badge badge-' + log.action_color + ' mt-1 px-2 py-1" style="font-size:10px;white-space:nowrap;">' + log.action_label + '</span>' +
                    '<div><div style="font-size:12px;font-weight:600;">' + log.actor + '</div>' +
                    '<div style="font-size:11px;color:var(--text-faint);">' + log.date + '</div>' +
                    (log.notes ? '<div style="font-size:12px;margin-top:4px;padding:6px 10px;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border);">' + log.notes + '</div>' : '') +
                    '</div></div>';
            });
            $('#crLogBody').html(logHtml || '<p class="text-muted py-3 mb-0">Belum ada log.</p>');

            $('#crDetailTabs a:first').tab('show');
            $('#crDetailLoading').hide(); $('#crDetailContent').show();

        }).fail(function () {
            $('#crDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    $('#mdlDetailCR').on('hidden.bs.modal', function () {
        $('#crDetailContent').hide(); $('#crDetailLoading').show();
    });
});
</script>
@endpush
