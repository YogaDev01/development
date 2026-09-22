@extends('layouts.app')

@section('title', 'Finance Forecast')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Finance Forecast</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Forecast</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total', $summary['total'], 'primary', 'fas fa-binoculars'],
            ['Draft', $summary['draft'], 'secondary', 'fas fa-pen'],
            ['Active', $summary['active'], 'success', 'fas fa-play-circle'],
            ['Closed', $summary['closed'], 'dark', 'fas fa-lock'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title"><i class="fas fa-binoculars mr-1"></i> Daftar Forecast</h3>
                <div class="ml-auto d-flex flex-wrap" style="gap:8px;">
                    <select name="status" class="form-control form-control-sm" onchange="location.href='?status='+this.value" style="width:auto;">
                        <option value="">Semua Status</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                        <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option value="CLOSED" {{ request('status') === 'CLOSED' ? 'selected' : '' }}>CLOSED</option>
                    </select>
                    <a href="{{ url('/forecast/create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Tambah Forecast
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0" style="font-size:12.5px;">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:1%;">No</th>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-nowrap">Period</th>
                                <th scope="col" class="text-nowrap">Source Fund</th>
                                <th scope="col" class="text-right text-nowrap">Forecast</th>
                                <th scope="col" class="text-right text-nowrap">Actual</th>
                                <th scope="col" class="text-right text-nowrap">Variance</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($forecasts as $f)
                            @php $varClass = $f->variance < 0 ? 'text-danger' : 'text-success'; @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{-- Judulnya catatan forecast; PO ditampilkan sebagai
                                         ringkasan karena satu forecast bisa memuat beberapa PO
                                         (lihat Forecast::getProjectSummaryAttribute()). --}}
                                    <strong>{{ $f->label }}</strong>
                                    <div class="text-muted" style="font-size:10px;">PO: {{ $f->project_summary }}</div>
                                </td>
                                <td class="text-nowrap">{{ $f->date_start->format('d/m/Y') }} - {{ $f->date_end->format('d/m/Y') }}</td>
                                <td>
                                    {{-- Rekening kini melekat di baris detail, jadi satu
                                         forecast bisa menyentuh beberapa rekening. --}}
                                    @php
                                        $rek = $f->details->pluck('sourceFund.name')->filter()->unique()->values();
                                    @endphp
                                    @forelse($rek->take(2) as $nama)
                                        <span class="badge badge-light border px-2 py-1" style="font-size:9.5px;">{{ $nama }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                    @if($rek->count() > 2)
                                        <span class="text-muted" style="font-size:10px;">+{{ $rek->count() - 2 }}</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $f->forecast_amount_formatted }}</td>
                                <td class="text-right">{{ $f->actual_amount_formatted }}</td>
                                <td class="text-right {{ $varClass }}">{{ $f->variance_formatted }} ({{ $f->achievement_pct }}%)</td>
                                <td><span class="badge badge-{{ $f->status_color }}">{{ $f->status }}</span></td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        {{-- Hanya View & Hapus. Edit dan seluruh alur
                                             (Activate, Close, isi Actual) pindah ke dalam
                                             modal detail — barisnya cukup jadi pintu masuk,
                                             bukan tempat semua tombol berkumpul.

                                             Hapus mengikuti Forecast::isLocked(), aturan yang
                                             sama dengan yang dipakai destroy(): CLOSED bersifat
                                             final, jadi tombolnya memang tidak ada. --}}
                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-fc-view"
                                                data-id="{{ $f->id }}"
                                                title="Detail" aria-label="Detail forecast {{ $f->label }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">View</span>
                                        </button>

                                        @if(! $f->isLocked())
                                        <form action="{{ url('/forecast/' . $f->id) }}" method="POST" class="d-inline delete-form">
                                            @csrf @method('DELETE')
                                            <button aria-label="Hapus forecast {{ $f->label }}" type="submit"
                                                    class="ts-action-primary is-danger" title="Hapus">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada forecast.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL DETAIL FORECAST
     Bentuknya mengikuti modal Detail Cost Request: panel ringkas gelap penuh
     di kiri (kode, judul, status, angka-angka pokok, dan tombol alurnya),
     ruang kerja terang di kanan dengan tab. Kelas dm-* sudah global; hanya
     kerangka dua kolomnya yang ditulis per halaman (lihat @push('styles')).

     Tanpa pita header: tombol tutup adalah tanda X di kepala panel gelap,
     sama seperti modal detail lain. --}}
<div class="modal fade" id="modalForecastDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-body dm-body" style="padding:0;background:var(--surface);">

                <div id="fcDetailLoading" style="padding:28px;min-height:320px;">
                    <x-skeleton type="lines" :rows="8" />
                </div>

                <div id="fcDetailContent" style="display:none;">
                    <div class="fc-modal-flex">

                        <div class="fc-sidebar dm-side">
                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="fcCode" class="dm-code"></div>
                                <h5 class="dm-title" id="fcTitle"></h5>

                                <div class="dm-badges"><span id="fcStatusBadge"></span></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08);">

                            <div class="mb-2">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Period</span>
                                <div id="fcPeriod" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-2">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Forecast Amount</span>
                                <div id="fcForecast" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-2">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Actual Amount</span>
                                <div id="fcActual" style="color:var(--success);font-size:14px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-2">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Variance</span>
                                <div id="fcVariance" style="font-size:14px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-2">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Achievement</span>
                                <div id="fcAchievement" class="text-white mt-1" style="font-size:13px;font-weight:700;"></div>
                            </div>
                            <div class="mb-2">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Dibuat Oleh</span>
                                <div id="fcCreator" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>

                            <div class="fc-sidebar-spacer"></div>

                            {{-- Alur dokumen: Edit selama belum CLOSED, lalu
                                 Activate (DRAFT) atau Close (ACTIVE). Tombolnya
                                 ditampilkan mengikuti izin yang dikirim server,
                                 bukan disimpulkan ulang dari string status. --}}
                            <div class="mt-3">
                                <a href="#" id="fcBtnEdit" class="btn btn-outline-warning btn-sm btn-block mb-2" style="display:none;">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <button type="button" id="fcBtnActivate" class="btn btn-success btn-sm btn-block mb-2" style="display:none;">
                                    <i class="fas fa-play mr-1"></i> Activate
                                </button>
                                <button type="button" id="fcBtnClose" class="btn btn-dark btn-sm btn-block mb-2" style="display:none;">
                                    <i class="fas fa-lock mr-1"></i> Close
                                </button>
                            </div>
                        </div>

                        <div class="fc-main-panel">
                            <ul class="nav nav-tabs mb-3" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#fcPaneInfo"
                                    style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#fcPaneRincian"
                                    style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-list-ul mr-1"></i> Rincian
                                    <span id="fcDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="fcPaneInfo">
                                    <div class="ts-deflist mb-3">
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">Kode</span><span id="infoCode" class="ts-deflist-value"></span></div>
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">Catatan</span><span id="infoNotes" class="ts-deflist-value"></span></div>
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">Period</span><span id="infoPeriod" class="ts-deflist-value"></span></div>
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">PO / Project</span><span id="infoProjects" class="ts-deflist-value"></span></div>
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">Status</span><span id="infoStatus" class="ts-deflist-value"></span></div>
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">Diaktifkan</span><span id="infoActivated" class="ts-deflist-value"></span></div>
                                        <div class="ts-deflist-row"><span class="ts-deflist-label">Ditutup</span><span id="infoClosed" class="ts-deflist-value"></span></div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="fcPaneRincian">
                                    <form id="frmUpdateActual" method="POST">
                                        @csrf
                                        <div class="d-flex align-items-center flex-wrap mb-2" style="gap:8px;">
                                            <small id="fcActualHint" style="color:var(--text-faint);display:none;">
                                                Actual diisi per item, lalu disimpan sekaligus.
                                            </small>
                                            <button type="submit" id="fcBtnSimpanActual"
                                                    class="btn btn-success btn-sm ml-auto" style="display:none;">
                                                <i class="fas fa-save mr-1"></i> Simpan Actual
                                            </button>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0" style="font-size:12.5px;">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th scope="col">Description</th>
                                                        <th scope="col" class="text-nowrap">PO</th>
                                                        <th scope="col" class="text-nowrap">Tipe</th>
                                                        <th scope="col" class="text-nowrap">Source Fund</th>
                                                        <th scope="col" class="text-right">Amount (Plan)</th>
                                                        <th scope="col" class="text-right" style="width:150px;">Actual</th>
                                                        <th scope="col" class="text-right">Variance</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="fcDetailBody"></tbody>
                                                <tfoot>
                                                    <tr style="background:var(--surface-2);">
                                                        <th colspan="4" class="text-right">Total</th>
                                                        <th class="text-right" id="fcFootForecast"></th>
                                                        <th class="text-right" id="fcFootActual"></th>
                                                        <th class="text-right" id="fcFootVariance"></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Form alur, disubmit dari tombol di panel gelap setelah konfirmasi. --}}
<form id="frmActivate" method="POST" style="display:none;">@csrf</form>
<form id="frmClose" method="POST" style="display:none;">@csrf</form>

@endsection

@push('styles')
<style>
/* Kerangka dua kolom modal detail. Kelas dm-* (panel gelap, kepala, badge)
   sudah global di tesys-ui.css & tesys-ui-luxe.css; yang tidak global hanya
   tata letaknya, dan memang ditulis ulang per halaman — pola yang sama dipakai
   budget-request, daily-transaction, dan beneficiaries. */
/* Tinggi PASTI — alasannya sama dengan modal detail lain: panel gelap di kiri
   mewarisi tinggi lewat rantai height:100%, dan rantai itu putus kalau
   induknya bertinggi otomatis atau hanya punya batas atas. Tanpa ini panelnya
   berhenti setinggi isinya dan menyisakan kertas putih di bawahnya saat
   rincian di kanan memanjang. */
#modalForecastDetail .modal-dialog  { height: min(860px, calc(100vh - 64px)); margin: 1.75rem auto; }
#modalForecastDetail .modal-content { height: 100%; max-height: 100%; }
#modalForecastDetail #fcDetailContent { height: 100%; }

/* Alasan sama dengan modal detail Beneficiary: max-height:88vh bawaan
   .dm-side memotong panel gelap lebih pendek dari modalnya yang kini
   bertinggi pasti. */
#modalForecastDetail .dm-side { max-height: 100%; }

/* .dm-body bawaan (tesys-ui.css) sudah overflow-y:auto — untuk modal
   SATU-KOLOM. Di modal dua-kolom ini yang menggulir hanya .fc-main-panel;
   dm-body ikut auto berarti dua scrollbar bertumpuk. */
#modalForecastDetail .modal-body.dm-body { overflow: hidden; }

.fc-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.fc-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.fc-sidebar-spacer { flex:1 1 auto; }
.fc-main-panel     { flex:1 1 auto; padding:24px; min-width:0; }
@media (max-width:767.98px) {
    .fc-modal-flex { flex-direction:column; }
    .fc-sidebar    { width:100%; flex:0 0 auto; }
}

/* Aturan global menyebut .cr-/.bp-/.prj- satu per satu, jadi kerangka ini
   tidak ikut tersapu: barisnya harus setinggi penuh (kalau tidak, panel
   gelapnya berhenti di tengah), dan sisi kanannya menggulung sendiri supaya
   kepala dokumen di kiri tetap terlihat saat rinciannya panjang. */
body.tesys-ui .modal-body.dm-body .fc-modal-flex { height:100%; min-height:100%; }
body.tesys-ui .modal-body.dm-body .fc-main-panel { overflow-y:auto; max-height:100%; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    $('.delete-form').submit(function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus Forecast?', text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL DETAIL FORECAST
    |--------------------------------------------------------------------------
    | Detailnya dulu halaman tersendiri. Dipindahkan ke modal supaya membuka
    | satu forecast tidak berarti meninggalkan daftarnya — dan supaya bentuknya
    | sama dengan modal detail modul lain.
    */

    var BASE = '{{ url("/forecast") }}';

    function esc(nilai) {
        return $('<div>').text(nilai == null ? '' : String(nilai)).html();
    }

    function rupiah(n) {
        return Number(n || 0).toLocaleString('id-ID');
    }

    function isiModal(d) {
        $('#fcCode').text(d.code);
        $('#fcTitle').text(d.label);
        $('#fcStatusBadge').html('<span class="badge badge-' + esc(d.status_color) + '">' + esc(d.status) + '</span>');

        $('#fcPeriod').text(d.period);
        $('#fcForecast').text(d.forecast_amount);
        $('#fcActual').text(d.actual_amount);
        $('#fcVariance').text(d.variance).css('color', d.variance_negative ? '#F87171' : '#34D399');
        $('#fcAchievement').text(d.achievement_pct + '%');
        $('#fcCreator').text(d.creator);

        $('#infoCode').text(d.code);
        $('#infoNotes').text(d.notes || '-');
        $('#infoPeriod').text(d.period);
        $('#infoProjects').text(d.projects.length ? d.projects.join(', ') : 'Tanpa PO');
        $('#infoStatus').html('<span class="badge badge-' + esc(d.status_color) + '">' + esc(d.status) + '</span>');
        $('#infoActivated').text(d.activated_at || '-');
        $('#infoClosed').text(d.closed_at || '-');

        /* Tombol alur mengikuti izin dari server, bukan disimpulkan ulang di
           sini dari string status — menyimpulkannya dua kali adalah cara
           tercepat membuat tombol yang tampil berbeda dari yang diterima. */
        $('#fcBtnEdit').attr('href', BASE + '/' + d.id + '/edit').toggle(!!d.can_edit);
        $('#fcBtnActivate').toggle(!!d.can_activate);
        $('#fcBtnClose').toggle(!!d.can_close);

        $('#frmActivate').attr('action', BASE + '/' + d.id + '/activate');
        $('#frmClose').attr('action', BASE + '/' + d.id + '/close');
        $('#frmUpdateActual').attr('action', BASE + '/' + d.id + '/update-actual');

        $('#fcBtnSimpanActual').toggle(!!d.can_update_actual);
        $('#fcActualHint').toggle(!!d.can_update_actual);

        var rows = '';
        d.details.forEach(function (x) {
            rows += '<tr>'
                + '<td>' + esc(x.description) + '</td>'
                + '<td class="text-nowrap">' + (x.project
                        ? '<span title="' + esc(x.project_name || '') + '">' + esc(x.project) + '</span>'
                        : '<span class="text-muted">-</span>') + '</td>'
                + '<td class="text-nowrap"><span class="badge badge-' + esc(x.type_color) + ' px-2 py-1" style="font-size:9.5px;">'
                    + esc(x.type_label) + '</span></td>'
                + '<td class="text-nowrap">' + esc(x.source_fund) + '</td>'
                + '<td class="text-right">' + esc(x.amount_formatted) + '</td>'
                + '<td class="text-right">'
                    + (d.can_update_actual
                        ? '<input type="text" name="details[' + x.id + ']" class="form-control form-control-sm text-right inp-actual-item" value="' + esc(x.actual_amount) + '">'
                        : esc(x.actual_amount_formatted))
                + '</td>'
                + '<td class="text-right ' + (x.selisih_negative ? 'text-danger' : 'text-success') + '">'
                    + esc(x.selisih_formatted) + '</td>'
                + '</tr>';
        });

        if (!d.details.length) {
            rows = '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada detail.</td></tr>';
        }

        $('#fcDetailBody').html(rows);
        $('#fcDetailCount').text(d.details.length);
        $('#fcFootForecast').text(d.forecast_amount);
        $('#fcFootActual').text(d.actual_amount);
        $('#fcFootVariance').text(d.variance);
    }

    function bukaDetail(id) {
        $('#fcDetailLoading').show();
        $('#fcDetailContent').hide();
        $('#modalForecastDetail').modal('show');

        $.get(BASE + '/' + id + '/detail-json', function (d) {
            isiModal(d);
            $('#fcDetailLoading').hide();
            $('#fcDetailContent').show();
        }).fail(function () {
            $('#fcDetailLoading').html(
                '<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>'
                + 'Gagal memuat detail. Silakan coba lagi.</p>'
            );
        });
    }

    $(document).on('click', '.btn-fc-view', function () {
        bukaDetail($(this).data('id'));
    });

    /* Pemisah ribuan saat mengetik, dan dibuang lagi sebelum dikirim — sama
       seperti form Forecast lain, supaya angka yang sampai ke server tetap
       bilangan polos. */
    $(document).on('input', '.inp-actual-item', function () {
        var v = this.value.replace(/\D/g, '');
        this.value = v ? parseInt(v, 10).toLocaleString('id-ID') : '';
    });

    $('#frmUpdateActual').on('submit', function () {
        $(this).find('.inp-actual-item').each(function () {
            $(this).val($(this).val().replace(/\./g, ''));
        });
    });

    $('#fcBtnActivate').on('click', function () {
        Swal.fire({
            title: 'Activate Forecast?', text: 'Setelah aktif, Actual Amount bisa diisi per item.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Activate', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) $('#frmActivate').submit(); });
    });

    $('#fcBtnClose').on('click', function () {
        Swal.fire({
            title: 'Close Forecast?', text: 'Forecast yang sudah CLOSED tidak dapat dibuka lagi.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#343a40', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Close', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) $('#frmClose').submit(); });
    });

    /* Tautan lama /forecast/{id} diarahkan ke sini dengan ?open={id} — bookmark
       dan redirect edit() saat forecast terkunci tetap mendarat di detail yang
       benar, bukan di daftar tanpa penjelasan. */
    var mintaBuka = new URLSearchParams(window.location.search).get('open');
    if (mintaBuka) bukaDetail(mintaBuka);
});
</script>
@endpush
