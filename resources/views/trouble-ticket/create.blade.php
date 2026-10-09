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
                            @if($problems->isEmpty() || $severities->isEmpty())
                                <div class="alert alert-warning py-2" style="font-size:12.5px;">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Master {{ $problems->isEmpty() ? 'Problem' : '' }}{{ $problems->isEmpty() && $severities->isEmpty() ? ' dan ' : '' }}{{ $severities->isEmpty() ? 'Severity' : '' }}
                                    belum diisi. Isi tabel <code>trouble_ticket_problems</code> / <code>trouble_ticket_severities</code> di basis data lebih dulu.
                                </div>
                            @endif
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold" for="fldProblem">Problem <span class="text-danger">*</span></label>
                                    <select name="problem_id" id="fldProblem" class="form-control @error('problem_id') is-invalid @enderror" required>
                                        <option value="">— Pilih problem —</option>
                                        @foreach($problems as $p)
                                            <option value="{{ $p->id }}" @selected((string) old('problem_id') === (string) $p->id)>{{ $p->name }}{{ $p->requires_evidence ? ' (bukti wajib saat Closed)' : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('problem_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="font-weight-bold" for="fldSeverity">Severity <span class="text-danger">*</span></label>
                                    <select name="severity" id="fldSeverity" class="form-control @error('severity') is-invalid @enderror" required>
                                        <option value="">— Pilih —</option>
                                        @foreach($severities as $sv)
                                            <option value="{{ $sv->code }}" @selected(old('severity') === $sv->code)>{{ $sv->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('severity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="font-weight-bold" for="fldExtRef">Ref. Eksternal</label>
                                    <input type="text" name="external_ref" id="fldExtRef" maxlength="40" value="{{ old('external_ref') }}"
                                           class="form-control @error('external_ref') is-invalid @enderror" placeholder="No. tiket NOC/customer">
                                    @error('external_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-12">
                                    <label class="font-weight-bold" for="fldIssue">Issue <span class="text-danger">*</span></label>
                                    <input type="text" name="issue" id="fldIssue" maxlength="200" value="{{ old('issue') }}"
                                           class="form-control @error('issue') is-invalid @enderror" placeholder="mis. Link down, modem tidak merespons sejak 08.00" required>
                                    @error('issue')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold" for="fldDevice">Device</label>
                                    <input type="text" name="device" id="fldDevice" maxlength="40" value="{{ old('device') }}"
                                           class="form-control @error('device') is-invalid @enderror" placeholder="mis. Modem / Router / ODU">
                                    @error('device')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold" for="fldAlarm">Flag Alarm</label>
                                    <input type="text" name="flag_alarm" id="fldAlarm" maxlength="40" value="{{ old('flag_alarm') }}"
                                           class="form-control @error('flag_alarm') is-invalid @enderror" placeholder="Alarm dari NMS (opsional)">
                                    @error('flag_alarm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold" for="fldReportedAt">Dilaporkan</label>
                                    <input type="datetime-local" name="reported_at" id="fldReportedAt" value="{{ old('reported_at', now()->format('Y-m-d\TH:i')) }}"
                                           max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('reported_at') is-invalid @enderror">
                                    @error('reported_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold" for="fldReporter">Pelapor</label>
                                    <input type="text" name="reporter_name" id="fldReporter" maxlength="150" value="{{ old('reporter_name') }}"
                                           class="form-control @error('reporter_name') is-invalid @enderror" placeholder="Nama pelapor dari customer/NOC (opsional)">
                                    @error('reporter_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-12">
                                    <label class="font-weight-bold" for="fldDesc">Deskripsi <span class="text-danger">*</span></label>
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
                            <div class="form-group mb-0">
                                <label class="font-weight-bold" for="fldTech">Teknisi <span class="text-danger">*</span></label>
                                <select name="assigned_to" id="fldTech" class="form-control @error('assigned_to') is-invalid @enderror" required disabled>
                                    <option value="">— Pilih site dulu —</option>
                                </select>
                                @error('assigned_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <small class="form-text text-muted" id="lblTechHint">Teknisi = PIC internal yang terdaftar di site tersebut.</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-light border" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Timer Response berjalan sejak tiket disimpan sampai teknisi pertama kali membuka tiket.
                        Target SLA diambil dari kebijakan aktif untuk severity/problem ini saat tiket disimpan. Waktu "Dilaporkan" hanya informasi.
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-4" style="gap:8px;">
                <a href="{{ route('trouble-ticket.index') }}" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Batal</a>
                <button type="submit" class="btn btn-success" id="btnTtSave"><i class="fas fa-save mr-1"></i> Simpan Tiket</button>
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
    var $site = $('#fldSite'), $tech = $('#fldTech');

    TesysSearchableSelect.init('#fldSite', { placeholder: '— Pilih site —', searchPlaceholder: 'Cari Site ID / nama...' });
    TesysSearchableSelect.init('#fldProblem', { placeholder: '— Pilih problem —', searchPlaceholder: 'Cari problem...' });

    function loadTechs(selected) {
        var id = $site.val();
        $('#lblCustomer').text($site.find(':selected').data('customer') || '—');
        $tech.prop('disabled', true).find('option').remove();
        if (!id) {
            $tech.append('<option value="">— Pilih site dulu —</option>');
            return;
        }
        $tech.append('<option value="">Memuat...</option>');
        $.getJSON(techUrl.replace('__ID__', id)).done(function (res) {
            $tech.find('option').remove();
            if (!res.technicians.length) {
                $tech.append('<option value="">Site ini belum punya PIC internal</option>');
                $('#lblTechHint').html('Tambahkan PIC internal di <a href="' + @json(url('/site')) + '/' + id + '">halaman site</a> lebih dulu.');
                return;
            }
            $('#lblTechHint').text('Teknisi = PIC internal yang terdaftar di site tersebut.');
            $tech.append('<option value="">— Pilih teknisi —</option>');
            res.technicians.forEach(function (t) {
                $tech.append($('<option>').val(t.id).text(t.name));
            });
            if (res.technicians.length === 1 && !selected) selected = String(res.technicians[0].id);
            $tech.val(selected || '').prop('disabled', false);
        }).fail(function () {
            $tech.find('option').remove();
            $tech.append('<option value="">Gagal memuat teknisi</option>');
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
