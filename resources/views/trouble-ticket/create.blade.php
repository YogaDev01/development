@extends('layouts.app')

@section('title', 'Buat Trouble Ticket')

@php
    use App\Models\TroubleTicket;
    use App\Models\TroubleTicketAttachment;
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
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-circle mr-1"></i> Gangguan</h3></div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold" for="fldRca">RCA</label>
                                    <select name="rca_id" id="fldRca" class="form-control @error('rca_id') is-invalid @enderror">
                                        <option value="">&mdash; Pilih RCA &mdash;</option>
                                        @foreach($rcas as $r)
                                            <option value="{{ $r->id }}" @selected((string) old('rca_id') === (string) $r->id)>{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('rca_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold" for="fldSubRca">Sub-RCA</label>
                                    <select name="sub_rca_id" id="fldSubRca" class="form-control @error('sub_rca_id') is-invalid @enderror">
                                        <option value="">&mdash; Pilih sub-RCA &mdash;</option>
                                    </select>
                                    <small class="form-text text-muted">Dugaan akar masalah; masih bisa diperbarui saat tiket ditutup.</small>
                                    @error('sub_rca_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold" for="fldDevice">Device</label>
                                    <select name="device_id" id="fldDevice" class="form-control @error('device_id') is-invalid @enderror">
                                        <option value="">&mdash; Pilih device &mdash;</option>
                                        @foreach($devices as $d)
                                            <option value="{{ $d->id }}" @selected((string) old('device_id') === (string) $d->id)>{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('device_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold" for="fldReportedAt">Date Open</label>
                                    <input type="datetime-local" name="reported_at" id="fldReportedAt" value="{{ old('reported_at', now()->format('Y-m-d\TH:i')) }}"
                                           max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('reported_at') is-invalid @enderror">
                                    <small class="form-text text-muted">Bawaan hari ini; jadi titik awal perhitungan aging.</small>
                                    @error('reported_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-12">
                                    <label class="font-weight-bold" for="fldDesc">Keterangan <span class="text-danger">*</span></label>
                                    <textarea name="description" id="fldDesc" rows="6" maxlength="10000"
                                              class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-12 mb-0">
                                    <label class="font-weight-bold" for="fldFiles">Lampiran</label>
                                    <input type="file" name="attachments[]" id="fldFiles" multiple class="form-control-file"
                                           accept="{{ collect(TroubleTicketAttachment::EXTENSIONS)->map(fn ($e) => '.' . $e)->implode(',') }}">
                                    <small class="form-text text-muted">Maks. {{ TroubleTicketAttachment::MAX_FILES }} berkas, 10 MB per berkas. {{ implode(', ', TroubleTicketAttachment::EXTENSIONS) }}.</small>
                                    <div class="tt-file-list" id="fldFilesInfo"></div>
                                    @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    @foreach($errors->get('attachments.*') as $pesan)
                                        <div class="invalid-feedback d-block">{{ $pesan[0] }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-broadcast-tower mr-1"></i> Site & Penugasan</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="font-weight-bold" for="fldSite">Site <span class="text-danger">*</span></label>
                                <select name="site_id" id="fldSite" class="form-control @error('site_id') is-invalid @enderror" required>
                                    <option value="">— Pilih site —</option>
                                    @foreach($sites as $s)
                                        <option value="{{ $s->id }}" data-customer="{{ $s->customer?->name }}" @selected((string) old('site_id') === (string) $s->id)>{{ $s->tid }} — {{ $s->sitename }}</option>
                                    @endforeach
                                </select>
                                @error('site_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="form-text text-muted">Customer: <strong id="lblCustomer">—</strong></small>
                            </div>
                            {{-- Teknisi tidak dipilih: ia mengikuti site. Kotaknya baca-saja,
                                 nilainya dikirim lewat input tersembunyi di bawahnya. --}}
                            <div class="form-group mb-0">
                                <label class="font-weight-bold" for="fldTechName">Teknisi <span class="text-danger">*</span></label>
                                <input type="text" id="fldTechName" class="form-control @error('assigned_to') is-invalid @enderror"
                                       value="" placeholder="— Pilih site dulu —" readonly>
                                <input type="hidden" name="assigned_to" id="fldTech" value="{{ old('assigned_to') }}">
                                @error('assigned_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="form-text text-muted" id="lblTechHint">Teknisi = PIC internal site tersebut; leader team tidak termasuk.</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-light border" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Timer Response berjalan sejak tiket disimpan sampai teknisi pertama kali membuka tiket.
                        Target SLA diambil dari kebijakan aktif yang berlaku saat tiket disimpan.
                        Severity tidak diisi di sini: ia mengikuti aging tiket &mdash; 0 hari Rendah, 1&ndash;2 Minor, 3&ndash;7 Major, 8 hari ke atas Kritis.
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

    TesysSearchableSelect.init('#fldSite', { placeholder: '— Pilih site —', searchPlaceholder: 'Cari Site ID / nama...' });
    TesysSearchableSelect.init('#fldDevice', { placeholder: '— Pilih device —', searchPlaceholder: 'Cari device...' });
    TesysSearchableSelect.init('#fldRca', { placeholder: '— Pilih RCA —', searchPlaceholder: 'Cari RCA...' });
    // Tanpa placeholder tetap: labelnya ikut opsi pertama ('Pilih RCA dulu' / 'Pilih sub-RCA').
    TesysSearchableSelect.init('#fldSubRca', { searchPlaceholder: 'Cari sub-RCA...' });

    // Sub-RCA mengikuti RCA; seluruh pasangannya sudah dikirim bersama halaman.
    var SUB_RCA = @json($subRcas->map(fn ($x) => ['id' => $x->id, 'rca_id' => $x->rca_id, 'name' => $x->name])->values());
    var oldSubRca = @json((string) old('sub_rca_id'));
    var $rca = $('#fldRca'), $sub = $('#fldSubRca');

    function loadSubRca(selected) {
        var rcaId = String($rca.val() || '');
        var daftar = rcaId ? SUB_RCA.filter(function (x) { return String(x.rca_id) === rcaId; }) : [];
        $sub.find('option').remove();
        $sub.append($('<option>').val('').text(rcaId ? '— Pilih sub-RCA —' : '— Pilih RCA dulu —'));
        daftar.forEach(function (x) { $sub.append($('<option>').val(x.id).text(x.name)); });
        // Nilai lama hanya dipakai bila masih ada di daftar RCA yang dipilih.
        var tetap = selected && daftar.some(function (x) { return String(x.id) === String(selected); });
        $sub.val(tetap ? selected : '');
        // Tombol searchable select ikut digambar ulang (lihat tesys-searchable-select.js).
        $sub.trigger('tss:sync');
    }

    $rca.on('change', function () { loadSubRca(null); });
    loadSubRca(oldSubRca);

    function setTech(id, nama) {
        $tech.val(id || '');
        $techName.val(nama || '');
    }

    function loadTechs(selected) {
        var id = $site.val();
        $('#lblCustomer').text($site.find(':selected').data('customer') || '—');
        if (!id) {
            setTech('', '');
            $techName.attr('placeholder', '— Pilih site dulu —');
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
            $('#lblTechHint').text('Teknisi = PIC internal site tersebut; leader team tidak termasuk.');
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
    $('#fldFiles').on('change', function () {
        var pesan = [];
        if (this.files.length > MAX_FILES) pesan.push('Paling banyak ' + MAX_FILES + ' berkas.');
        Array.prototype.forEach.call(this.files, function (f) {
            if (f.size > MAX_BYTES) pesan.push(f.name + ' melebihi 10 MB.');
        });
        $('#fldFilesInfo').html(pesan.length ? '<span class="text-danger">' + pesan.map(function (p) { return $('<i>').text(p).html(); }).join('<br>') + '</span>' : '');
        if (pesan.length) this.value = '';
    });

    $('#frmTtCreate').on('submit', function () {
        $('#btnTtSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
    });
});
</script>
@endpush
