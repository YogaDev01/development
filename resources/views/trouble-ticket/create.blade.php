@extends('layouts.app')

@section('title', 'Buat Trouble Ticket')

@php
    use App\Models\TroubleTicket;
    use App\Models\TroubleTicketAttachment;

    $accept = collect(TroubleTicketAttachment::EXTENSIONS)->map(fn ($e) => '.' . $e)->implode(',');

    /* Satu form bisa menyimpan beberapa tiket sekaligus untuk site & teknisi
       yang sama. Blok gangguan dibangun ulang dari old() supaya isian tidak
       hilang saat validasi gagal; tanpa old(), satu blok kosong. */
    $blok = old('tickets', [[]]);
@endphp

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="tt-title">Buat Trouble Ticket</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Workflow</a></li>
                <li class="breadcrumb-item"><a href="{{ route('trouble-ticket.index') }}">Trouble Ticket</a></li>
                <li class="breadcrumb-item active">Buat</li>
            </ol>
        </div>

        <form action="{{ route('trouble-ticket.store') }}" method="POST" enctype="multipart/form-data" id="frmTtCreate" novalidate>
            @csrf

            @if($errors->any())
                <div class="alert alert-danger py-2" style="font-size:13px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Periksa kembali isian yang ditandai.
                </div>
            @endif

            <div class="row">
                <div class="col-lg-7">
                    <div id="ttBloks">
                        @foreach($blok as $i => $b)
                            <div class="card card-primary card-outline shadow-sm tt-blok" data-indeks="{{ $i }}">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-circle mr-1"></i> Gangguan</h3></div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label class="font-weight-bold">RCA</label>
                                            <select name="tickets[{{ $i }}][rca_id]" class="form-control tt-rca @error("tickets.$i.rca_id") is-invalid @enderror">
                                                <option value="">&mdash; Pilih RCA &mdash;</option>
                                                @foreach($rcas as $r)
                                                    <option value="{{ $r->id }}" @selected((string) ($b['rca_id'] ?? '') === (string) $r->id)>{{ $r->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted">Dugaan awal akar masalah.</small>
                                            @error("tickets.$i.rca_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label class="font-weight-bold">Sub-RCA</label>
                                            <select name="tickets[{{ $i }}][sub_rca_id]" class="form-control tt-sub-rca @error("tickets.$i.sub_rca_id") is-invalid @enderror"
                                                    data-terpilih="{{ $b['sub_rca_id'] ?? '' }}">
                                                <option value="">&mdash; Pilih sub-RCA &mdash;</option>
                                            </select>
                                            <small class="form-text text-muted">Rincian RCA.</small>
                                            @error("tickets.$i.sub_rca_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                        {{-- No. TT boleh ditentukan sendiri (mis. nomor tiket
                                             dari customer). Dibiarkan kosong = nomor otomatis. --}}
                                        <div class="form-group col-md-6">
                                            <label class="font-weight-bold">No. Tiket Custom</label>
                                            <input type="text" name="tickets[{{ $i }}][ticket_number]" maxlength="50"
                                                   value="{{ $b['ticket_number'] ?? '' }}" placeholder="Contoh: INC-2026-00123"
                                                   class="form-control @error("tickets.$i.ticket_number") is-invalid @enderror">
                                            <small class="form-text text-muted">Nomor dari customer/sistem lain. Kosong = otomatis (T.{{ now()->format('my') }}001).</small>
                                            @error("tickets.$i.ticket_number")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label class="font-weight-bold">Date Open</label>
                                            <input type="datetime-local" name="tickets[{{ $i }}][reported_at]"
                                                   value="{{ $b['reported_at'] ?? now()->format('Y-m-d\TH:i') }}"
                                                   max="{{ now()->format('Y-m-d\TH:i') }}"
                                                   class="form-control @error("tickets.$i.reported_at") is-invalid @enderror">
                                            <small class="form-text text-muted">Waktu awal gangguan.</small>
                                            @error("tickets.$i.reported_at")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-12">
                                            <label class="font-weight-bold">Keterangan <span class="text-danger">*</span></label>
                                            <textarea name="tickets[{{ $i }}][description]" rows="6" maxlength="10000"
                                                      class="form-control @error("tickets.$i.description") is-invalid @enderror" required>{{ $b['description'] ?? '' }}</textarea>
                                            @error("tickets.$i.description")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-12 mb-0">
                                            <label class="font-weight-bold">Lampiran</label>
                                            <input type="file" name="tickets[{{ $i }}][attachments][]" multiple class="form-control-file tt-lampiran" accept="{{ $accept }}">
                                            <small class="form-text text-muted">Maks. {{ TroubleTicketAttachment::MAX_FILES }} berkas, 10 MB per berkas. {{ implode(', ', TroubleTicketAttachment::EXTENSIONS) }}.</small>
                                            <div class="tt-file-list"></div>
                                            @foreach($errors->get("tickets.$i.attachments.*") as $pesan)
                                                <div class="invalid-feedback d-block">{{ $pesan[0] }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-broadcast-tower mr-1"></i> Site & Penugasan</h3></div>
                        <div class="card-body">
                            {{-- Customer dipilih lebih dulu: daftar site di bawahnya
                                 hanya memuat site milik customer tersebut. --}}
                            <div class="form-group">
                                <label class="font-weight-bold" for="fldCustomer">Customer <span class="text-danger">*</span></label>
                                @php $custLama = old('customer_id', $sites->firstWhere('id', old('site_id'))?->customer_id); @endphp
                                <select id="fldCustomer" class="form-control">
                                    <option value="">— Pilih customer —</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" @selected((string) $custLama === (string) $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="customer_id" id="fldCustomerId" value="{{ $custLama }}">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold" for="fldSite">Site <span class="text-danger">*</span></label>
                                <select name="site_id" id="fldSite" class="form-control @error('site_id') is-invalid @enderror" required>
                                    <option value="">— Pilih customer dulu —</option>
                                </select>
                                @error('site_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            {{-- Teknisi tidak dipilih: ia mengikuti site. Kotaknya baca-saja,
                                 nilainya dikirim lewat input tersembunyi di bawahnya. --}}
                            <div class="form-group mb-0">
                                <label class="font-weight-bold" for="fldTechName">Teknisi <span class="text-danger">*</span></label>
                                <input type="text" id="fldTechName" class="form-control @error('assigned_to') is-invalid @enderror"
                                       value="" placeholder="— Pilih customer dulu —" readonly>
                                <input type="hidden" name="assigned_to" id="fldTech" value="{{ old('assigned_to') }}">
                                @error('assigned_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="form-text text-muted" id="lblTechHint"></small>
                            </div>
                        </div>
                    </div>
                    {{-- Tombol duduk di kolom kanan, tepat di bawah ringkasan penugasan:
                         jalur baca form berakhir di sini, bukan melintas kembali ke kiri. --}}
                    <div class="d-flex justify-content-end mb-4" style="gap:8px;">
                        <a href="{{ route('trouble-ticket.index') }}" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Batal</a>
                        <button type="submit" class="btn btn-success" id="btnTtSave"><i class="fas fa-save mr-1"></i> Simpan Tiket</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var MAX_BYTES = {{ TroubleTicketAttachment::MAX_KB * 1024 }}, MAX_FILES = {{ TroubleTicketAttachment::MAX_FILES }};
    var techUrl = @json(url('trouble-ticket/site/__ID__/technicians'));
    var oldTech = @json((string) old('assigned_to'));
    var $site = $('#fldSite'), $tech = $('#fldTech'), $techName = $('#fldTechName');
    var $cust = $('#fldCustomer'), $custId = $('#fldCustomerId');
    // Seluruh site aktif dikirim bersama halaman; penyaringan per customer
    // dilakukan di browser, tanpa permintaan tambahan.
    var SITES = @json($sites->map(fn ($s) => ['id' => $s->id, 'customer_id' => $s->customer_id, 'label' => $s->tid . ' — ' . $s->sitename])->values());
    var oldSite = @json((string) old('site_id'));

    TesysSearchableSelect.init('#fldCustomer', { placeholder: '— Pilih customer —', searchPlaceholder: 'Cari customer...' });
    TesysSearchableSelect.init('#fldSite', { searchPlaceholder: 'Cari Site ID / nama...' });

    /* ---------- Customer → site ---------- */

    function isiSite(terpilih) {
        var custId = String($cust.val() || '');
        var daftar = custId ? SITES.filter(function (x) { return String(x.customer_id) === custId; }) : [];
        $custId.val(custId);
        $site.find('option').remove();
        $site.append($('<option>').val('').text(custId ? '— Pilih site —' : '— Pilih customer dulu —'));
        daftar.forEach(function (x) { $site.append($('<option>').val(x.id).text(x.label)); });
        // Site lama hanya dipertahankan bila masih milik customer yang dipilih.
        var tetap = terpilih && daftar.some(function (x) { return String(x.id) === String(terpilih); });
        $site.val(tetap ? terpilih : '');
        $site.trigger('tss:sync');
    }

    $cust.on('change', function () { isiSite(null); loadTechs(null); });
    isiSite(oldSite);

    /* ---------- Blok gangguan (satu blok = satu tiket) ---------- */

    // Seluruh pasangan RCA → Sub-RCA dikirim bersama halaman; tidak ada
    // permintaan tambahan saat RCA dipilih.
    var SUB_RCA = @json($subRcas->map(fn ($x) => ['id' => $x->id, 'rca_id' => $x->rca_id, 'name' => $x->name])->values());

    function isiSubRca($blok, terpilih) {
        var $rca = $blok.find('.tt-rca'), $sub = $blok.find('.tt-sub-rca');
        var rcaId = String($rca.val() || '');
        var daftar = rcaId ? SUB_RCA.filter(function (x) { return String(x.rca_id) === rcaId; }) : [];
        $sub.find('option').remove();
        $sub.append($('<option>').val('').text(rcaId ? '— Pilih sub-RCA —' : '— Pilih RCA dulu —'));
        daftar.forEach(function (x) { $sub.append($('<option>').val(x.id).text(x.name)); });
        // Nilai lama hanya dipakai bila masih ada di daftar RCA yang dipilih.
        var tetap = terpilih && daftar.some(function (x) { return String(x.id) === String(terpilih); });
        $sub.val(tetap ? terpilih : '');
        $sub.trigger('tss:sync');   // tombol searchable select digambar ulang
    }

    function siapkanBlok($blok) {
        TesysSearchableSelect.initOne($blok.find('.tt-rca'), { placeholder: '— Pilih RCA —', searchPlaceholder: 'Cari RCA...' });
        // Tanpa placeholder tetap: labelnya ikut opsi pertama.
        TesysSearchableSelect.initOne($blok.find('.tt-sub-rca'), { searchPlaceholder: 'Cari sub-RCA...' });
        isiSubRca($blok, $blok.find('.tt-sub-rca').attr('data-terpilih'));
    }

    $('#ttBloks .tt-blok').each(function () { siapkanBlok($(this)); });

    $(document).on('change', '#ttBloks .tt-rca', function () {
        isiSubRca($(this).closest('.tt-blok'), null);
    });

    /* ---------- Site → teknisi ---------- */

    function setTech(id, nama) {
        $tech.val(id || '');
        $techName.val(nama || '');
    }

    function loadTechs(selected) {
        var id = $site.val();
        if (!id) {
            setTech('', '');
            $techName.attr('placeholder', $cust.val() ? '— Pilih site dulu —' : '— Pilih customer dulu —');
            return;
        }
        setTech('', 'Memuat…');
        $.getJSON(techUrl.replace('__ID__', id)).done(function (res) {
            if (!res.technicians.length) {
                setTech('', '');
                $techName.attr('placeholder', 'Site ini belum punya teknisi');
                $('#lblTechHint').html('Tambahkan PIC internal (bukan leader team) di <a href="' + @json(url('/site')) + '/' + id + '">halaman site</a> lebih dulu.');
                return;
            }
            $('#lblTechHint').text('');
            // Teknisi site langsung dipakai; tidak ada yang perlu dipilih.
            var pakai = res.technicians.filter(function (t) { return String(t.id) === String(selected); })[0] || res.technicians[0];
            setTech(pakai.id, pakai.name);
        }).fail(function () {
            setTech('', '');
            $techName.attr('placeholder', 'Gagal memuat teknisi');
        });
    }

    $site.on('change', function () { loadTechs(null); });
    if ($site.val()) loadTechs(oldTech);

    // Batas ukuran dicek juga di browser; server tetap menolak bila dilewati.
    $(document).on('change', '.tt-lampiran', function () {
        var pesan = [];
        if (this.files.length > MAX_FILES) pesan.push('Paling banyak ' + MAX_FILES + ' berkas.');
        Array.prototype.forEach.call(this.files, function (f) {
            if (f.size > MAX_BYTES) pesan.push(f.name + ' melebihi 10 MB.');
        });
        $(this).closest('.form-group').find('.tt-file-list')
            .html(pesan.length ? '<span class="text-danger">' + pesan.map(function (p) { return $('<i>').text(p).html(); }).join('<br>') + '</span>' : '');
        if (pesan.length) this.value = '';
    });

    $('#frmTtCreate').on('submit', function () {
        $('#btnTtSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
    });
});
</script>
@endpush
