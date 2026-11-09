{{-- Modal & skrip tab Pengajuan (pengajuan biaya pra-Cost Request).

     Satu modal form dipakai tiga cara: pengajuan baru (teknisi), revisi/ubah
     milik sendiri (teknisi), dan perubahan oleh reviewer (supervisor team yang
     sama) — bedanya hanya alamat tujuan dan tombolnya. TANPA info sisa pagu /
     NOB / kuota CA: teknisi hanya mengajukan dana. Total tiap baris hanya
     bantuan baca; server menghitung ulang harga × qty. --}}
@php
    $dataForm = [
        'plans' => $budgetPlans->map(fn ($p) => ['id' => $p->id, 'label' => $p->code . ($p->description ? ' — ' . \Illuminate\Support\Str::limit($p->description, 40) : ''), 'project' => $p->project_id])->values(),
        // Sub-item dikelompokkan per akun COA di dropdown (pola form Cost Request).
        'details' => $budgetDetails->map(fn ($d) => ['id' => $d->id, 'plan' => $d->budget_plan_id,
            'account' => $d->account?->name ?? '-', 'deskripsi' => $d->deskripsi ?: '-', 'unit' => $d->unit ?: '-'])->values(),
        'clusters' => $clustersByProject->map(fn ($c) => $c->map(fn ($x) => ['id' => $x->id, 'label' => '[' . $x->code . '] ' . $x->name])->values()),
        // Cash Advance dikunci bila teknisi tiket tanpa Master PIC / Beneficiary Internal.
        'types' => $jenisCr->map(fn ($x) => ['id' => $x->id, 'ca' => (bool) $x->is_ca, 'off' => $x->is_ca && ! $caBeneficiary,
            'name' => $x->name . ($x->is_ca && ! $caBeneficiary ? ' (tidak tersedia)' : '')])->values(),
        'benef' => $beneficiaries->map(fn ($b) => ['id' => $b->id, 'label' => ($b->nickname ?: $b->complete_name) . ' (' . $b->category . ')'])->values(),
        'req' => $pengajuan->filter(fn ($r) => ($flagPengajuan[$r->id]['edit'] ?? false) || ($flagPengajuan[$r->id]['review'] ?? false))
            ->mapWithKeys(fn ($r) => [$r->id => [
                'plan' => $r->budget_plan_id, 'type' => $r->request_type_id, 'cluster' => $r->cluster_id,
                'benef' => $r->beneficiary_id, 'desc' => $r->description,
                'items' => $r->items->where('revision', $r->revision)->values()->map(fn ($i) => [
                    'id' => $i->id, 'detail' => $i->budget_detail_id, 'desc' => $i->description,
                    'amount' => (int) $i->amount, 'qty' => (int) $i->qty,
                    'files' => $i->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name])->values(),
                ]),
                'general' => $r->attachments->whereNull('item_id')->values()->map(fn ($a) => ['id' => $a->id, 'name' => $a->original_name]),
            ]]),
        'saveUrl' => route('trouble-ticket.requests.save', $t),
        'editUrl' => route('trouble-ticket.requests.edit', [$t, '__ID__']),
        'approveUrl' => route('trouble-ticket.requests.approve', [$t, '__ID__']),
        'rejectUrl' => route('trouble-ticket.requests.reject', [$t, '__ID__']),
        'cancelUrl' => route('trouble-ticket.requests.cancel', [$t, '__ID__']),
    ];
@endphp

@if($canRequest || $flagPengajuan->contains(fn ($f) => $f['review'] || $f['edit'] || $f['submit'] || $f['cancel'] || $f['settle_review']))
    <div class="modal fade tt-modal-full" id="mdlTtRequest" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            {{-- Tanpa tt-submit-once: validasi lampiran per baris di bawah bisa membatalkan kirim,
                 dan tombol tidak boleh terkunci bila itu terjadi. --}}
            <form class="modal-content" id="frmTtRequest" method="POST" enctype="multipart/form-data" action="{{ route('trouble-ticket.requests.save', $t) }}">
                @csrf
                <input type="hidden" name="request_id" id="fldRqId" value="">
                <input type="hidden" name="submit" id="fldRqSubmit" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar mr-1"></i> <span id="ttRqTitle">Ajukan Dana</span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="form-row">
                        <div class="form-group col-md-4 mb-2">
                            <label class="font-weight-bold" for="fldRqType">Jenis <span class="text-danger">*</span></label>
                            <select name="request_type_id" id="fldRqType" class="form-control" required></select>
                        </div>
                        <div class="form-group col-md-4 mb-2">
                            <label class="font-weight-bold" for="fldRqPlan">Budget Plan <span class="text-danger">*</span></label>
                            <select name="budget_plan_id" id="fldRqPlan" class="form-control" data-searchable required></select>
                            @if($budgetPlans->isEmpty())
                                <small class="text-danger">Belum ada Budget Plan ACTIVE untuk customer Anda.</small>
                            @endif
                        </div>
                        <div class="form-group col-md-4 mb-2" id="rqClusterWrap" style="display:none;">
                            <label class="font-weight-bold" for="fldRqCluster">Project <span class="text-danger">*</span></label>
                            <select name="cluster_id" id="fldRqCluster" class="form-control"></select>
                            <small class="text-danger" id="rqClusterNone" style="display:none;">Tidak ada project terhubung pada Budget Plan ini; pilih Budget Plan lain.</small>
                        </div>
                        <div class="form-group col-md-4 mb-2" id="rqBenefWrap" style="display:none;">
                            <label class="font-weight-bold" for="fldRqBenef">Beneficiary <span class="text-danger">*</span></label>
                            <select name="beneficiary_id" id="fldRqBenef" class="form-control" data-searchable></select>
                        </div>
                        <div class="form-group col-md-4 mb-2" id="rqBenefCa" style="display:none;">
                            <label class="font-weight-bold">Penerima dana</label>
                            <input type="text" class="form-control" value="{{ $caBeneficiary ? ($caBeneficiary->nickname ?: $caBeneficiary->complete_name) . ' (Beneficiary Internal PIC teknisi)' : 'Beneficiary Internal PIC teknisi (otomatis)' }}" readonly>
                        </div>
                        @unless($caBeneficiary)
                            <div class="col-12 mb-2">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Cash Advance tidak tersedia: teknisi tiket ini belum terhubung ke Master PIC atau PIC-nya belum punya Beneficiary Internal.</small>
                            </div>
                        @endunless
                        <div class="form-group col-12 mb-2">
                            <label class="font-weight-bold" for="fldRqDesc">Deskripsi <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="fldRqDesc" class="form-control" maxlength="500" required placeholder="Untuk keperluan apa dana ini">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm tt-cost-table tt-rq-table mb-1">
                            <thead>
                                <tr>
                                    <th style="width:24%">COA / Sub-item <span class="text-danger">*</span></th>
                                    <th>Deskripsi <span class="text-danger">*</span></th>
                                    <th style="width:120px">Harga <span class="text-danger">*</span></th>
                                    <th style="width:64px">Qty <span class="text-danger">*</span></th>
                                    <th style="width:110px" class="text-right">Total</th>
                                    <th style="width:170px">Lampiran <span class="text-danger tt-rq-req-star">*</span></th>
                                    <th style="width:36px"></th>
                                </tr>
                            </thead>
                            <tbody id="ttRqRows"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Total Pengajuan</th>
                                    <th class="text-right" id="ttRqGrand">0</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ttRqAdd"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>

                    <div class="form-group mt-3 mb-1">
                        <label class="font-weight-bold">Lampiran tambahan <span class="text-muted font-weight-normal">(opsional)</span></label>
                        <div id="ttRqGeneralKeep"></div>
                        <input type="file" name="general_files[]" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="tt-stage-note">
                        Pengajuan ini <strong>belum</strong> membentuk Cost Request. Setelah di-submit, supervisor team
                        Anda mereview dan, bila disetujui, sistem membuat <strong>Cost Request berstatus Draft</strong>.
                        Cash Advance tidak wajib berlampiran; jenis lain <strong>wajib</strong> melampirkan berkas di setiap baris.
                    </div>
                    <div class="text-danger mt-2 d-none" id="ttRqErr"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm mr-auto" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-outline-primary btn-sm tt-rq-send" data-submit="0" id="btnTtRqDraft"><i class="fas fa-save mr-1"></i> Simpan Draft</button>
                    <button type="submit" class="btn btn-primary btn-sm tt-rq-send" data-submit="1" id="btnTtRqSubmit"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Keputusan yang butuh keterangan: Reject (alasan wajib) dan Batal (alasan wajib). --}}
    <div class="modal fade" id="mdlTtReqDecision" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" id="frmTtReqDecision" method="POST" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="ttRqDecTitle">Keputusan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label class="font-weight-bold" for="fldRqDecText" id="ttRqDecLabel">Alasan <span class="text-danger">*</span></label>
                    <textarea name="notes" id="fldRqDecText" class="form-control" rows="3" maxlength="500" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btnTtRqDec">Kirim</button>
                </div>
            </form>
        </div>
    </div>

    @push('styles')
    <style>
    .tt-rq-files { font-size:11px; color:var(--text-muted); overflow-wrap:anywhere; }
    .tt-rq-keep { display:block; font-size:11.5px; margin:0; font-weight:400; overflow-wrap:anywhere; }
    .tt-rq-table .form-control-sm { min-width:0; }
    /* Picker searchable Sub Item di sel tabel — ramping seperti form Cost Request. */
    .tt-rq-table td .tss-btn { padding:.25rem .5rem; font-size:13px; }
    /* Kotak deskripsi + ikon "perluas" di ujung kanan (pola form Cost Request). */
    .tt-rq-desc-wrap { position:relative; }
    .tt-rq-desc-wrap .tt-rq-desc { padding-right:30px; }
    .tt-rq-desc-wrap .tt-rq-desc-expand {
        position:absolute; top:2px; right:2px; bottom:2px; width:26px;
        display:flex; align-items:center; justify-content:center;
        background:var(--surface); border:none; border-radius:0 var(--radius-sm) var(--radius-sm) 0;
        line-height:1; color:var(--text-faint); cursor:pointer; font-size:11px; z-index:2;
    }
    .tt-rq-desc-wrap .tt-rq-desc-expand:hover { color:var(--accent); }
    .swal2-close.tt-rq-close { color:var(--danger); background:transparent; box-shadow:none; }
    @media (max-width: 767.98px) {
        .tt-modal-full .modal-dialog { margin:0; max-width:none; min-height:100%; }
        .tt-modal-full .modal-content { min-height:100vh; border-radius:0; border:0; }
        .tt-rq-table thead { display:none; }
        .tt-rq-table tr.tt-rq-row { display:block; border:1px solid var(--border); border-radius:var(--radius-sm); padding:8px; margin-bottom:8px; }
        .tt-rq-table tr.tt-rq-row td { display:block; border:0; padding:3px 0; width:auto !important; text-align:left !important; }
        .tt-rq-table tr.tt-rq-row td::before { content:attr(data-label); display:block; font-size:11px; color:var(--text-faint); }
        .tt-rq-table tfoot, .tt-rq-table tfoot tr, .tt-rq-table tfoot th { display:block; text-align:right !important; }
    }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var D = @json($dataForm);
        var $m = $('#mdlTtRequest'), $form = $('#frmTtRequest'), $rows = $('#ttRqRows');
        var rupiah = new Intl.NumberFormat('id-ID');

        function angka(t) { return parseInt(String(t || '').replace(/[^\d]/g, ''), 10) || 0; }
        function uang(t) { return String(t || '').replace(/[^\d]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
        function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
        function opts(list, sel, ph) {
            return '<option value="">' + esc(ph) + '</option>' + list.map(function (o) {
                return '<option value="' + o.id + '"' + (String(o.id) === String(sel) ? ' selected' : '') + (o.off ? ' disabled' : '') + '>' + esc(o.label || o.name) + '</option>';
            }).join('');
        }
        /* Opsi sub-item dikelompokkan per akun (sama dengan form Cost Request):
           judul akun tebal tidak bisa diklik, item "Deskripsi (satuan)"; tombol
           terpilih menampilkan "Akun - Deskripsi (satuan)", nama akun ikut dicari. */
        function escAttr(t) { return esc(t).replace(/"/g, '&quot;'); }
        function optsDetail(list, sel) {
            var grup = {}, urutan = [];
            list.forEach(function (d) {
                if (!grup[d.account]) { grup[d.account] = []; urutan.push(d.account); }
                grup[d.account].push(d);
            });
            var html = '<option value="">— Pilih Sub Item —</option>';
            urutan.forEach(function (akun) {
                html += '<optgroup label="' + escAttr(akun) + '">';
                grup[akun].forEach(function (d) {
                    var teks = d.deskripsi + ' (' + d.unit + ')';
                    html += '<option value="' + d.id + '"' + (String(d.id) === String(sel) ? ' selected' : '')
                        + ' data-label="' + escAttr(akun + ' - ' + teks) + '" data-item-label="' + escAttr(teks) + '"'
                        + ' data-search="' + escAttr(akun) + '">' + esc(teks) + '</option>';
                });
                html += '</optgroup>';
            });
            return html;
        }
        function pasangPickerItem($scope) {
            if (typeof TesysSearchableSelect === 'undefined') return;
            $scope.find('select.tt-rq-item').each(function () {
                TesysSearchableSelect.initOne($(this), { placeholder: '— Pilih Sub Item —', searchPlaceholder: 'Cari Sub Item...' });
                $(this).trigger('tss:sync');
            });
        }
        // Dropdown panjang diberi pencarian (sama seperti dropdown panjang lain di app).
        if (typeof TesysSearchableSelect !== 'undefined') {
            TesysSearchableSelect.init($('#fldRqPlan, #fldRqBenef'), { searchPlaceholder: 'Ketik untuk mencari...' });
        }

        function jenis() { var v = $('#fldRqType').val(); return D.types.filter(function (t) { return String(t.id) === String(v); })[0] || null; }
        function plan() { var v = $('#fldRqPlan').val(); return D.plans.filter(function (p) { return String(p.id) === String(v); })[0] || null; }
        function detailPlan() { var p = $('#fldRqPlan').val(); return D.details.filter(function (d) { return String(d.plan) === String(p); }); }

        function rowHtml(r) {
            r = r || {};
            var files = (r.files || []).map(function (f) {
                return '<label class="tt-rq-keep"><input type="checkbox" class="tt-rq-keepbox" name="items[0][keep_attachment_ids][]" value="' + f.id + '" checked> ' + esc(f.name) + '</label>';
            }).join('');
            return '<tr class="tt-rq-row">'
                + '<td data-label="COA / Sub-item"><input type="hidden" name="items[0][source_item_id]" value="' + (r.id || '') + '">'
                // Tanpa atribut required: select disembunyikan picker searchable (validasi
                // native tak bisa fokus ke elemen tersembunyi) — dicek di handler submit.
                + '<select name="items[0][budget_detail_id]" class="form-control form-control-sm tt-rq-item">' + optsDetail(detailPlan(), r.detail) + '</select></td>'
                + '<td data-label="Deskripsi"><div class="tt-rq-desc-wrap">'
                + '<input type="text" name="items[0][description]" class="form-control form-control-sm tt-rq-desc" maxlength="255" required value="' + escAttr(r.desc) + '">'
                + '<button type="button" class="tt-rq-desc-expand" title="Perluas deskripsi" aria-label="Perluas deskripsi" tabindex="-1"><i class="fas fa-expand-alt"></i></button>'
                + '</div></td>'
                + '<td data-label="Harga"><input type="text" inputmode="numeric" name="items[0][amount]" class="form-control form-control-sm text-right tt-rq-amount" required value="' + (r.amount ? uang(r.amount) : '') + '"></td>'
                + '<td data-label="Qty"><input type="number" name="items[0][qty]" class="form-control form-control-sm tt-rq-qty" min="1" step="1" required value="' + (r.qty || 1) + '"></td>'
                + '<td data-label="Total" class="text-right align-middle tt-rq-total">0</td>'
                + '<td data-label="Lampiran">' + files
                + '<label class="btn btn-outline-secondary btn-xs mb-0 mt-1"><i class="fas fa-paperclip mr-1"></i> Pilih<input type="file" name="items[0][files][]" class="tt-rq-file" multiple hidden accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></label>'
                + '<div class="tt-rq-files"></div></td>'
                + '<td class="align-middle text-center"><button type="button" class="btn btn-link btn-sm text-danger p-0 tt-rq-del" aria-label="Hapus baris"><i class="fas fa-times"></i></button></td>'
                + '</tr>';
        }

        function nomori() {
            $rows.children('tr').each(function (i) {
                $(this).find('[name]').each(function () { this.name = this.name.replace(/items\[\d+\]/, 'items[' + i + ']'); });
            });
        }
        function hitung() {
            var total = 0;
            $rows.children('tr').each(function () {
                var baris = angka($(this).find('.tt-rq-amount').val()) * (parseInt($(this).find('.tt-rq-qty').val(), 10) || 0);
                total += baris;
                $(this).find('.tt-rq-total').text(rupiah.format(baris));
            });
            $('#ttRqGrand').text(rupiah.format(total));
        }
        function tambah(r) { var $tr = $(rowHtml(r)); $rows.append($tr); pasangPickerItem($tr); nomori(); hitung(); }

        function sinkronJenis() {
            var t = jenis(), ca = !!(t && t.ca);
            $('#rqBenefWrap').toggle(!!t && !ca);
            $('#fldRqBenef').prop('required', !!t && !ca).prop('disabled', ca);
            $('#rqBenefCa').toggle(ca);
            $('.tt-rq-req-star').toggle(!ca);
        }
        function sinkronPlan() {
            // Sub-item hanya dari Budget Plan terpilih; pilihan yang tak cocok dikosongkan.
            var daftar = detailPlan();
            $rows.find('.tt-rq-item').each(function () {
                var cur = $(this).val();
                var ada = daftar.some(function (d) { return String(d.id) === String(cur); });
                $(this).html(optsDetail(daftar, ada ? cur : '')).trigger('tss:sync');
            });
            // Project (= cluster_id pada Cost Request) hanya untuk budget PROYEK (aturan form Cost Request).
            var p = plan(), list = p && p.project ? (D.clusters[p.project] || []) : null;
            $('#rqClusterWrap').toggle(list !== null);
            if (list !== null) {
                var cur = $('#fldRqCluster').val();
                $('#fldRqCluster').html(list.length === 1 ? opts(list, list[0].id, '— Project —') : opts(list, cur, '— Project —'))
                    .prop('required', list.length > 0);
                $('#rqClusterNone').toggle(list.length === 0);
            } else {
                $('#fldRqCluster').prop('required', false).val('');
            }
        }

        function buka(mode, id) {
            var r = id ? D.req[id] : null;
            $form[0].reset();
            $rows.empty();
            $('#ttRqErr').addClass('d-none');
            $('#ttRqGeneralKeep').empty();
            $form.find('button').prop('disabled', false);

            $('#fldRqType').html(opts(D.types, r && r.type, '— Pilih jenis —'));
            $('#fldRqPlan').html(opts(D.plans, r && r.plan, '— Pilih Budget Plan —'));
            $('#fldRqBenef').html(opts(D.benef, r && r.benef, '— Pilih beneficiary —'));
            $('#fldRqDesc').val(r ? r.desc : '');
            $('#fldRqId').val(mode === 'review' ? '' : (id || ''));

            // Tujuan form: simpan oleh pemohon, atau ubah oleh reviewer.
            $form.attr('action', mode === 'review' ? D.editUrl.replace('__ID__', id) : D.saveUrl);
            $('#ttRqTitle').text(mode === 'review' ? 'Ubah Pengajuan (Supervisor)' : (r ? 'Ubah Pengajuan' : 'Ajukan Dana'));
            $('#btnTtRqDraft').toggle(mode !== 'review');
            $('#btnTtRqSubmit').html(mode === 'review' ? '<i class="fas fa-save mr-1"></i> Simpan Perubahan' : '<i class="fas fa-paper-plane mr-1"></i> Submit')
                .data('submit', mode === 'review' ? 0 : 1);

            sinkronJenis();
            sinkronPlan();
            if (r) { $('#fldRqCluster').val(r.cluster || ''); }
            (r ? r.items : [null]).forEach(function (it) {
                tambah(it ? { id: it.id, detail: it.detail, desc: it.desc, amount: it.amount, qty: it.qty, files: it.files } : null);
            });
            sinkronPlan();
            if (r) {
                // Pilih ulang sub-item (sinkronPlan mengosongkan yang belum cocok saat baris baru ditambahkan).
                $rows.children('tr').each(function (i) { $(this).find('.tt-rq-item').val(r.items[i].detail).trigger('tss:sync'); });
                (r.general || []).forEach(function (f) {
                    $('#ttRqGeneralKeep').append('<label class="tt-rq-keep"><input type="checkbox" name="keep_general_ids[]" value="' + f.id + '" checked> ' + esc(f.name) + '</label>');
                });
            }
            hitung();
            $('#fldRqPlan, #fldRqBenef').trigger('tss:sync');
            $m.modal('show');
        }

        $('#btnTtReqNew').on('click', function () { buka('new'); });
        $(document).on('click', '.btn-tt-req-edit', function () { buka(this.dataset.mode, this.dataset.id); });

        $('#fldRqType').on('change', sinkronJenis);
        $('#fldRqPlan').on('change', sinkronPlan);
        $('#ttRqAdd').on('click', function () { tambah(null); sinkronPlan(); });
        $rows.on('click', '.tt-rq-del', function () {
            if ($rows.children('tr').length <= 1) { $(this).closest('tr').find('input[type=text],input[type=number]').val(''); }
            else { $(this).closest('tr').remove(); nomori(); }
            hitung();
        });
        $rows.on('input', '.tt-rq-amount', function () {
            var dariKanan = this.value.length - (this.selectionStart || 0);
            this.value = uang(this.value);
            var k = Math.max(0, this.value.length - dariKanan);
            try { this.setSelectionRange(k, k); } catch (e) { /* input lama */ }
        });
        $rows.on('input change', '.tt-rq-amount, .tt-rq-qty', hitung);

        /* Perluas deskripsi: pop-up textarea, hasilnya ditulis balik ke kotak baris
           saat ditutup (X, Esc, klik luar). Pop-up ditaruh DI DALAM modal (target)
           supaya focus-trap modal Bootstrap tidak merebut fokus textarea, dan Esc
           tidak ikut menutup modal pengajuan. */
        $rows.on('click', '.tt-rq-desc-expand', function () {
            if (typeof Swal === 'undefined') return;
            var $inp = $(this).closest('.tt-rq-desc-wrap').find('.tt-rq-desc');
            Swal.fire({
                target: $m[0],
                title: 'Deskripsi',
                html: '<textarea id="ttRqDescPopup" class="form-control" rows="6" maxlength="255" style="font-size:13px;width:100%;resize:vertical;">'
                    + esc($inp.val()) + '</textarea>'
                    + '<small id="ttRqDescCount" class="d-block mt-1 text-muted" style="font-size:11px;text-align:left;"></small>',
                width: '560px',
                showConfirmButton: false,
                showCloseButton: true,
                keydownListenerCapture: true,
                customClass: { closeButton: 'tt-rq-close' },
                didOpen: function (popup) {
                    var ta = popup.querySelector('#ttRqDescPopup'), inf = popup.querySelector('#ttRqDescCount');
                    function sisa() {
                        var n = 255 - ta.value.length;
                        inf.textContent = n <= 0 ? 'Batas 255 karakter sudah habis.' : 'Sisa ' + n + ' karakter.';
                        inf.className = 'd-block mt-1 ' + (n <= 0 ? 'text-danger' : 'text-muted');
                    }
                    ta.addEventListener('input', sisa);
                    sisa();
                    popup.addEventListener('keydown', function (e) { if (e.key === 'Escape') e.stopPropagation(); });
                    ta.focus();
                    ta.setSelectionRange(ta.value.length, ta.value.length);
                },
                willClose: function (popup) {
                    var ta = popup.querySelector('#ttRqDescPopup');
                    if (ta) $inp.val(ta.value).trigger('input');
                }
            });
        });
        $rows.on('change', '.tt-rq-file', function () {
            $(this).closest('td').find('.tt-rq-files').text(Array.prototype.map.call(this.files, function (f) { return f.name; }).join(', '));
        });

        // Lampiran per baris wajib untuk jenis selain Cash Advance (diperiksa ulang di server).
        $form.on('click', '.tt-rq-send', function () { $('#fldRqSubmit').val($(this).data('submit') ? 1 : 0); });
        $form.on('submit', function (e) {
            var t = jenis(), pesan = '';
            $rows.children('tr').each(function (i) {
                if (!$(this).find('.tt-rq-item').val() && !pesan) pesan = 'Baris ' + (i + 1) + ': Sub Item wajib dipilih.';
            });
            if (t && !t.ca && !pesan) {
                $rows.children('tr').each(function (i) {
                    var kept = $(this).find('.tt-rq-keepbox:checked').length;
                    var baru = $(this).find('.tt-rq-file')[0].files.length;
                    if (!kept && !baru && !pesan) pesan = 'Baris ' + (i + 1) + ': lampiran wajib untuk jenis selain Cash Advance.';
                });
            }
            if (pesan) { e.preventDefault(); $('#ttRqErr').text(pesan).removeClass('d-none'); return; }
            $form.find('button').prop('disabled', true);
        });

        // Reject / Batal: alasan wajib.
        $(document).on('click', '.btn-tt-req-decide', function () {
            $('#fldRqDecDecision').remove();

            // Mengembalikan settlement memakai modal yang sama, alasan juga wajib.
            if (this.dataset.kind === 'settle-return') {
                $('#frmTtReqDecision').attr('action', this.dataset.url)
                    .append('<input type="hidden" id="fldRqDecDecision" name="decision" value="return">');
                $('#fldRqDecText').attr('name', 'notes').val('');
                $('#ttRqDecTitle').text('Kembalikan settlement');
                $('#ttRqDecLabel').html('Alasan pengembalian <span class="text-danger">*</span>');
                $('#btnTtRqDec').text('Kembalikan');
                $('#mdlTtReqDecision').modal('show');
                return;
            }

            var reject = this.dataset.kind === 'reject';
            $('#frmTtReqDecision').attr('action', (reject ? D.rejectUrl : D.cancelUrl).replace('__ID__', this.dataset.id));
            $('#fldRqDecText').attr('name', reject ? 'notes' : 'reason').val('');
            $('#ttRqDecTitle').text(reject ? 'Tolak pengajuan' : 'Batalkan pengajuan');
            $('#ttRqDecLabel').html((reject ? 'Alasan penolakan' : 'Alasan pembatalan') + ' <span class="text-danger">*</span>');
            $('#btnTtRqDec').text(reject ? 'Tolak' : 'Batalkan');
            $('#mdlTtReqDecision').modal('show');
        });
    })();
    </script>
    @endpush
@endif
