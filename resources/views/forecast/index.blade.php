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
                {{-- Daftar dibatasi baris terbaru (Concerns\CapsLargeLists);
                     angka kartu ringkasan tetap dihitung dari SELURUH data
                     lewat query agregat, jadi tidak ikut terpotong. --}}
                @if(!empty($truncated))
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> forecast terbaru dari total
                    <strong>{{ number_format($total, 0, ',', '.') }}</strong>.
                    Gunakan pencarian/filter untuk mempersempit.
                </div>
                @endif
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
                            <tr data-forecast="{{ $f->id }}">
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
                                <td class="text-right fc-row-actual">{{ $f->actual_amount_formatted }}</td>
                                <td class="text-right fc-row-variance {{ $varClass }}">{{ $f->variance_formatted }} ({{ $f->achievement_pct }}%)</td>
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
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#fcPaneLog"
                                    style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-history mr-1"></i> Log
                                    <span id="fcLogCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
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
                                    {{-- SATU tabel untuk seluruh rekening, dengan baris induk
                                         per Source Fund yang bisa dibuka-tutup — bentuknya
                                         mengikuti tab Budget di Budgeting. Satu tabel berarti
                                         satu baris kepala kolom, dan seluruh angka (rincian,
                                         subtotal, grand total) berdiri di kolom yang sama
                                         tanpa perlu disejajarkan sendiri.

                                         Isinya dibangun di JS dari `groups`. --}}
                                    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:8px;">
                                        <small id="fcActualHint" style="color:var(--text-faint);display:none;">
                                            Ketik nominal realisasi di kolom Actual, lalu tekan
                                            <i class="fas fa-save"></i> — keterangannya wajib dan ditanyakan sebelum disimpan.
                                        </small>
                                        <div class="ml-auto d-flex" style="gap:6px;">
                                            <button type="button" id="fcExpandAll" class="btn btn-outline-secondary btn-sm" style="font-size:11px;">
                                                <i class="fas fa-angle-double-down mr-1"></i> Expand All
                                            </button>
                                            <button type="button" id="fcCollapseAll" class="btn btn-outline-secondary btn-sm" style="font-size:11px;">
                                                <i class="fas fa-angle-double-up mr-1"></i> Collapse All
                                            </button>
                                        </div>
                                    </div>

                                    <div id="fcGroups" class="table-responsive"></div>
                                </div>

                                <div class="tab-pane fade" id="fcPaneLog">
                                    {{-- Jejak aktivitas, bentuknya mengikuti timeline log modul
                                         lain (lihat delivery-note/show). --}}
                                    <div id="fcLogList" class="fc-timeline"></div>
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

{{-- Penambahan & penghapusan entri realisasi. Isinya ditulis JS setelah
     dialog keterangan dikonfirmasi — keterangannya wajib, dan syarat itu
     ditegakkan lagi di server (ForecastController::storeActual()). --}}
<form id="frmActualStore" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="amount" id="inpActualAmount">
    <input type="hidden" name="notes" id="inpActualNotes">
</form>
<form id="frmActualDestroy" method="POST" style="display:none;">@csrf @method('DELETE')</form>

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

/* --- Rincian per rekening ------------------------------------------------
   Satu tabel, dengan baris induk per Source Fund yang bisa dibuka-tutup. */
.fc-grp-head {
    background:var(--surface-2); cursor:pointer; user-select:none;
}
.fc-grp-head:hover     { background:var(--surface-3, var(--surface-2)); filter:brightness(.98); }
.fc-grp-head td        { font-size:11.5px; text-transform:uppercase; letter-spacing:.03em; }
.fc-grp-caret          { font-size:10px; color:var(--text-faint); transition:transform .12s ease; }

/* Rincian menjorok ke dalam, supaya hubungan induk-anak terbaca sekilas
   tanpa garis penghubung. */
.fc-indent             { padding-left:28px !important; }

.fc-grand th           { background:var(--surface-2); }

/* Jumlah keterangan yang menempel di tombolnya — penanda bahwa baris ini
   sudah punya catatan realisasi, tanpa harus dibuka dulu. */
.fc-entry-count {
    position:absolute; top:-5px; right:-5px; min-width:15px; height:15px; padding:0 3px;
    border-radius:8px; background:var(--accent, #c8a24a); color:#1b1b1b;
    font-size:9px; font-weight:700; line-height:15px; text-align:center;
}
.ts-actions .btn-fc-entries { position:relative; }

/* Satu entri realisasi di baris keterangan. */
.fc-entry {
    display:flex; align-items:flex-start; gap:8px;
    padding:6px 0; border-bottom:1px dashed var(--border); font-size:12px;
}
.fc-entry:last-child  { border-bottom:0; }
.fc-entry-main        { flex:1 1 auto; min-width:0; }
.fc-entry-meta        { color:var(--text-faint); font-size:10.5px; margin-top:2px; }

/* SweetAlert2 dipanggil dari DALAM modal detail, jadi lapisannya harus di
   atas backdrop Bootstrap (1040) dan modalnya sendiri (1050) — kalau tidak,
   dialognya tampil di bawah lapisan gelap dan tidak bisa disentuh. */
.swal2-container { z-index: 20000 !important; }

/* Kolom nominal realisasi: rapat, karena ia duduk di bawah angka akumulasi
   dalam satu sel. */
/* Tabel rincian & Grand Total memakai lebar kolom yang dipatok lewat
   colgroup. Tanpa table-layout:fixed peramban tetap melebarkan kolom menurut
   isinya, dan Grand Total — yang berada di tabel terpisah karena menutup
   semua rekening — melenceng dari kolom yang diringkasnya. */
.fc-table { table-layout: fixed; }
.fc-table td, .fc-table th { overflow-wrap: anywhere; }

.inp-actual-item { height:31px; font-size:12.5px; padding:4px 8px; }

/* Teks samar di kotak Actual bukan sekadar petunjuk — ia angka realisasi yang
   sudah tercatat, jadi dibuat cukup pekat untuk dibaca sebagai nilai. */
.inp-actual-item::placeholder { color:var(--text); opacity:.5; }

/* Dialog keterangan realisasi.
   Bawaan SweetAlert2 membuat kotak isiannya sempit dan menjulang: keterangan
   ditulis mendatar, jadi kotaknya dilebarkan dan tingginya dipangkas —
   persegi panjang, bukan kolom sempit. Lebar popupnya disamakan dengan dialog
   konfirmasi lain supaya sebentuk dengan yang sudah ada. */
.fc-swal-notes { width:560px; max-width:94vw; padding:24px 28px 20px; }
.fc-swal-notes .swal2-textarea {
    display:block; width:100%; max-width:100%; height:140px; min-height:100px;
    margin:0; padding:10px 12px; font-size:13px; resize:vertical; box-sizing:border-box;
}
.fc-swal-notes .swal2-html-container { margin:0 0 12px; text-align:left; }
.fc-swal-notes .swal2-actions        { margin-top:18px; }

/* --- Log -------------------------------------------------------------
   Timeline sederhana, sebentuk dengan log modul lain. */
.fc-tl-item {
    position:relative; display:flex; gap:10px;
    padding:0 0 14px 14px; border-left:2px solid var(--border);
}
.fc-tl-item:last-child { border-left-color:transparent; padding-bottom:0; }
.fc-tl-dot {
    position:absolute; left:-6px; top:4px; width:10px; height:10px; border-radius:50%;
    padding:0; border:2px solid var(--surface);
}
.fc-tl-body  { font-size:12px; }
.fc-tl-date  { color:var(--text-faint); font-size:10.5px; margin-top:3px; }
.fc-tl-notes { margin-top:4px; font-size:11.5px; color:var(--text); }
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

    /* Forecast yang sedang terbuka. Tombol realisasi dipasang lewat delegasi
       (barisnya dibangun ulang tiap modal dibuka), jadi alamat aksinya disusun
       dari sini, bukan dari atribut tiap tombol. */
    var idAktif = null;

    var TOKEN = $('#frmActualStore input[name="_token"]').val();

    /* Validasi Laravel (422) mengirim pesannya di `errors`; sisanya jatuh ke
       kalimat cadangan supaya pengguna tidak pernah melihat dialog kosong. */
    function pesanGagal(xhr, cadangan) {
        var j = xhr.responseJSON || {};
        if (j.errors) {
            var k = Object.keys(j.errors)[0];
            if (k) return j.errors[k][0];
        }
        return j.message || cadangan;
    }

    /*
    | Bootstrap menarik kembali fokus ke modalnya setiap kali fokus lari ke
    | elemen di luar — dan SweetAlert2 hidup DI LUAR modal itu. Akibatnya
    | kolom isian dialog tidak bisa diklik maupun diketik sama sekali: setiap
    | klik langsung direbut balik. Penjagaan itu dimatikan untuk modal ini
    | saja, bukan untuk seluruh aplikasi.
    |
    | Dialognya juga harus berada di atas backdrop modal (z-index Bootstrap
    | 1050/1040), jadi lapisannya dinaikkan lewat kelas di bawah.
    */
    $('#modalForecastDetail').on('shown.bs.modal', function () {
        $(document).off('focusin.bs.modal');
    });

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

        $('#fcActualHint').toggle(!!d.can_update_actual);

        renderRincian(d);
        renderLog(d);

        $('#fcDetailCount').text(d.detail_count);
        $('#fcFootMasuk').text(d.masuk_total);
        $('#fcFootKeluar').text(d.keluar_total);
        $('#fcFootActual').text(d.actual_amount);
        $('#fcFootVariance').text(d.variance);
    }

    /*
    | SATU tabel untuk seluruh rekening. Tiap Source Fund menjadi baris induk
    | yang bisa dibuka-tutup dan sudah membawa subtotalnya sendiri, persis
    | seperti Chart of Account di tab Budget.
    |
    | Bentuk lama — satu tabel terpisah per rekening — mengulang baris kepala
    | kolom sebanyak rekeningnya dan membuat Grand Total berdiri di tabelnya
    | sendiri, sehingga kolomnya harus disejajarkan dengan tangan. Di satu
    | tabel, persoalan itu hilang dengan sendirinya.
    |
    | Angka yang datang dari server sudah bertanda: baris KELUAR (rencana
    | maupun realisasinya) memang tampil negatif, karena uangnya pergi.
    */
    function renderRincian(d) {
        if (!d.groups.length) {
            $('#fcGroups').html('<p class="text-center text-muted py-3 mb-0">Belum ada detail.</p>');
            return;
        }

        var html = '<table class="table table-bordered mb-0 fc-table" style="font-size:12.5px;">'
            + '<colgroup><col style="width:22%;"><col style="width:10%;"><col style="width:14%;">'
            + '<col style="width:14%;"><col style="width:14%;"><col style="width:14%;"><col style="width:12%;"></colgroup>'
            + '<thead class="bg-light"><tr>'
            +   '<th scope="col">Description</th>'
            +   '<th scope="col" class="text-nowrap">PO</th>'
            +   '<th scope="col" class="text-right">Masuk</th>'
            +   '<th scope="col" class="text-right">Keluar</th>'
            +   '<th scope="col" class="text-right">Actual</th>'
            +   '<th scope="col" class="text-right">Variance</th>'
            +   '<th scope="col" class="text-center">Aksi</th>'
            + '</tr></thead><tbody>';

        d.groups.forEach(function (g, i) {
            html += '<tr class="fc-grp-head" data-grp="' + i + '">'
                + '<td colspan="2">'
                +   '<i class="fas fa-chevron-down fc-grp-caret mr-2"></i>'
                +   '<i class="fas fa-university mr-1 text-muted"></i>'
                +   '<strong>' + esc(g.source_fund) + '</strong>'
                +   '<span class="badge badge-light border ml-2" style="font-size:9.5px;">' + g.rows.length + '</span>'
                + '</td>'
                + '<td class="text-right">' + esc(g.masuk_formatted) + '</td>'
                + '<td class="text-right text-danger">' + esc(g.keluar_formatted) + '</td>'
                + '<td class="text-right">' + esc(g.actual_formatted) + '</td>'
                + '<td class="text-right ' + (g.variance_negative ? 'text-danger' : 'text-success') + '">'
                    + esc(g.variance_formatted) + '</td>'
                + '<td></td>'
                + '</tr>';

            g.rows.forEach(function (x) { html += barisRincian(x, d, i); });
        });

        html += '</tbody><tfoot><tr class="fc-grand">'
            + '<th colspan="2" class="text-right">Grand Total</th>'
            + '<th class="text-right" id="fcFootMasuk"></th>'
            + '<th class="text-right text-danger" id="fcFootKeluar"></th>'
            + '<th class="text-right" id="fcFootActual"></th>'
            + '<th class="text-right" id="fcFootVariance"></th>'
            + '<th></th>'
            + '</tr></tfoot></table>';

        $('#fcGroups').html(html);
    }

    function barisRincian(x, d, grp) {
        /* Dua tombol: menambah realisasi (selalu lewat dialog berketerangan)
           dan membuka daftar keterangan yang sudah tercatat. Tombol tambah
           hanya muncul kalau server memang menerimanya. */
        var aksi = '';
        if (d.can_update_actual) {
            aksi += '<button type="button" class="ts-action-primary is-success btn-fc-add-actual"'
                + ' data-detail="' + x.id + '" data-desc="' + esc(x.description) + '" data-type="' + esc(x.type) + '"'
                + ' title="Simpan realisasi (isi keterangan)"><i class="fas fa-save" aria-hidden="true"></i>'
                + '<span class="ts-al">Simpan realisasi</span></button>';
        }
        aksi += '<button type="button" class="ts-action-primary is-neutral btn-fc-entries"'
            + ' data-detail="' + x.id + '" title="Keterangan realisasi">'
            + '<i class="fas fa-comment-dots" aria-hidden="true"></i>'
            + (x.entry_count ? '<span class="fc-entry-count">' + x.entry_count + '</span>' : '')
            + '<span class="ts-al">Keterangan realisasi</span></button>';

        var baris = '<tr class="fc-grp-row" data-grp="' + grp + '">'
            + '<td class="fc-indent">' + esc(x.description) + '</td>'
            + '<td class="text-nowrap">' + (x.project
                    ? '<span title="' + esc(x.project_name || '') + '">' + esc(x.project) + '</span>'
                    : '<span class="text-muted">-</span>') + '</td>'
            /* Nilainya jatuh ke kolom yang sesuai arahnya; kolom seberangnya
               dibiarkan kosong agar arah baris terbaca dari letak angkanya. */
            + '<td class="text-right">' + (x.type === 'IN'
                    ? esc(x.amount_formatted) : '<span class="text-muted">-</span>') + '</td>'
            + '<td class="text-right text-danger">' + (x.type === 'OUT'
                    ? esc(x.amount_formatted) : '<span class="text-muted">-</span>') + '</td>'
            /* Satu kotak isian saja per sel, angkanya DI DALAM kotak dan rata
               kanan — sebentuk dengan kolom Amount di form Forecast. Isinya
               realisasi yang sudah tercatat; mengetik angka lain lalu menekan
               simpan berarti menambahkan realisasi sebesar angka itu. */
            + '<td class="text-right ' + (x.type === 'OUT' ? 'text-danger' : '') + '">'
                + (d.can_update_actual
                    /* Kotaknya KOSONG; realisasi yang sudah tercatat jadi teks
                       samar di dalamnya. Dengan begitu yang diketik selalu
                       jelas maksudnya — penambahan, bukan penggantian — dan
                       setelah disimpan kotaknya kosong lagi dengan sendirinya,
                       siap untuk penambahan berikutnya. */
                    ? '<input type="text" class="form-control form-control-sm text-right inp-actual-item"'
                        + ' data-detail="' + x.id + '" data-type="' + esc(x.type) + '"'
                        + ' inputmode="numeric" placeholder="' + esc(x.actual_amount_formatted) + '">'
                    : esc(x.actual_amount_formatted))
            + '</td>'
            + '<td class="text-right ' + (x.selisih_negative ? 'text-danger' : 'text-success') + '">' + esc(x.selisih_formatted) + '</td>'
            + '<td class="text-center action-column"><div class="action-buttons ts-actions">' + aksi + '</div></td>'
            + '</tr>';

        /* Baris keterangan: tersembunyi sampai tombolnya ditekan, supaya tabel
           tetap terbaca sebagai rencana — bukan sebagai catatan panjang. */
        var isi = '';
        if (x.entries.length) {
            x.entries.forEach(function (e) {
                isi += '<div class="fc-entry">'
                    + '<div class="fc-entry-main">'
                    +   '<strong>' + esc(e.amount) + '</strong> · ' + esc(e.notes)
                    +   '<div class="fc-entry-meta">' + esc(e.user) + ' — ' + esc(e.at) + '</div>'
                    + '</div>'
                    + (e.can_delete
                        ? '<button type="button" class="btn btn-link text-danger p-0 btn-fc-del-entry"'
                            + ' data-entry="' + e.id + '" title="Hapus realisasi">'
                            + '<i class="fas fa-times"></i></button>'
                        : '')
                    + '</div>';
            });
        } else {
            isi = '<div class="text-muted" style="font-size:11.5px;">Belum ada realisasi yang dicatat.</div>';
        }

        baris += '<tr class="fc-entry-row fc-grp-row" data-grp="' + grp + '" data-detail="' + x.id + '" style="display:none;">'
            + '<td colspan="8" style="background:var(--surface-2);">' + isi + '</td></tr>';

        return baris;
    }

    function renderLog(d) {
        var html = '';
        d.logs.forEach(function (l) {
            html += '<div class="fc-tl-item">'
                + '<span class="fc-tl-dot badge-' + esc(l.action_color) + '"></span>'
                + '<div class="fc-tl-body">'
                +   '<span class="badge badge-' + esc(l.action_color) + ' px-2 py-1 mr-2" style="font-size:9px;">'
                +       esc(l.action_label) + '</span>'
                +   '<strong>' + esc(l.actor) + '</strong>'
                +   '<div class="fc-tl-date">' + esc(l.at) + '</div>'
                +   (l.notes ? '<div class="fc-tl-notes">' + esc(l.notes) + '</div>' : '')
                + '</div></div>';
        });

        $('#fcLogList').html(html || '<div class="text-center text-muted py-3">Belum ada aktivitas.</div>');
        $('#fcLogCount').text(d.logs.length);
    }

    /*
    | `diam` = menyegarkan isi modal yang sudah terbuka tanpa menampilkan
    | kerangka pemuatan. Dipakai setelah realisasi disimpan: yang berubah
    | hanya angka-angkanya, dan mengedipkan seluruh modal untuk itu membuat
    | pekerjaan yang mulus terasa seperti pindah halaman.
    */
    function bukaDetail(id, diam) {
        idAktif = id;

        if (!diam) {
            $('#fcDetailLoading').show();
            $('#fcDetailContent').hide();
            $('#modalForecastDetail').modal('show');
        }

        return $.get(BASE + '/' + id + '/detail-json', function (d) {
            isiModal(d);
            perbaruiBarisDaftar(d);
            $('#fcDetailLoading').hide();
            $('#fcDetailContent').show();
        }).fail(function () {
            if (diam) {
                toast('error', 'Gagal memuat ulang detail.');
                return;
            }
            $('#fcDetailLoading').html(
                '<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>'
                + 'Gagal memuat detail. Silakan coba lagi.</p>'
            );
        });
    }

    /* Baris di daftar belakang ikut diperbarui — tanpa muat ulang, angkanya
       akan tertinggal dari yang baru saja terlihat di modal. */
    function perbaruiBarisDaftar(d) {
        var $tr = $('tr[data-forecast="' + d.id + '"]');
        if (!$tr.length) return;

        $tr.find('.fc-row-actual').text(d.actual_amount);
        $tr.find('.fc-row-variance')
            .text(d.variance + ' (' + d.achievement_pct + '%)')
            .toggleClass('text-danger', !!d.variance_negative)
            .toggleClass('text-success', !d.variance_negative);
    }

    /* Pemberitahuan ringkas di pojok — pengganti pesan flash, yang dulu hanya
       muncul karena halamannya dimuat ulang. */
    function toast(ikon, pesan) {
        Swal.fire({
            toast: true, position: 'top-end', icon: ikon, title: pesan,
            showConfirmButton: false, timer: 2200, timerProgressBar: true
        });
    }

    $(document).on('click', '.btn-fc-view', function () {
        bukaDetail($(this).data('id'));
    });

    /*
    | Membuka/menutup satu rekening. Baris keterangan ikut disembunyikan saat
    | grupnya ditutup, tapi tidak ikut terbuka begitu saja saat grupnya dibuka
    | — ia punya tombolnya sendiri, dan membuka semuanya sekaligus akan
    | mengubur tabelnya di bawah catatan.
    */
    function setGrup($head, buka) {
        $head.toggleClass('is-collapsed', !buka)
             .find('.fc-grp-caret')
             .toggleClass('fa-chevron-down', buka)
             .toggleClass('fa-chevron-right', !buka);

        $('.fc-grp-row[data-grp="' + $head.data('grp') + '"]').each(function () {
            var $r = $(this);
            $r.toggle(buka && !$r.hasClass('fc-entry-row'));
        });
    }

    $(document).on('click', '.fc-grp-head', function () {
        var $h = $(this);
        setGrup($h, $h.hasClass('is-collapsed'));
    });

    $('#fcExpandAll').on('click', function () {
        $('#fcGroups .fc-grp-head').each(function () { setGrup($(this), true); });
    });

    $('#fcCollapseAll').on('click', function () {
        $('#fcGroups .fc-grp-head').each(function () { setGrup($(this), false); });
    });

    /* Daftar keterangan per baris: dibuka/ditutup di tempat, tanpa memuat
       ulang modalnya. */
    $(document).on('click', '.btn-fc-entries', function () {
        $('.fc-entry-row[data-detail="' + $(this).data('detail') + '"]').toggle();
    });

    /* Pemisah ribuan saat mengetik di kolom Actual — sama seperti form
       Forecast lain, supaya angka yang sampai ke server tetap bilangan polos. */
    /*
    | Angka realisasi tampil BERTANDA mengikuti arah barisnya: Masuk menambah
    | kas (+), Keluar menguranginya (-). Tandanya dipasang otomatis — yang
    | diketik pengguna cukup angkanya, dan yang dikirim ke server pun tetap
    | bilangan polos tanpa tanda (arahnya sudah melekat di baris detail).
    */
    function formatActual(el) {
        var angka = (el.value || '').replace(/\D/g, '');
        var nilai = angka ? parseInt(angka, 10) : 0;

        // Kosong tetap kosong — supaya teks samar berisi realisasi yang sudah
        // tercatat tidak tertimbun angka 0 yang tidak berarti apa-apa.
        el.value = nilai > 0
            ? ($(el).data('type') === 'OUT' ? '-' : '') + nilai.toLocaleString('id-ID')
            : '';
    }

    $(document).on('input', '.inp-actual-item', function () {
        var posisi = this.value.length - this.selectionEnd;
        formatActual(this);
        // Kursor dikembalikan dihitung dari BELAKANG: pemisah ribuan dan tanda
        // minus yang baru dipasang menggeser posisinya kalau dihitung dari depan.
        var balik = Math.max(0, this.value.length - posisi);
        this.setSelectionRange(balik, balik);
    });

    /* Enter di kolom nominal = menekan tombol simpan barisnya. Tanpa ini
       Enter men-submit form terdekat dan nominalnya hilang tanpa penjelasan. */
    $(document).on('keydown', '.inp-actual-item', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        $('.btn-fc-add-actual[data-detail="' + $(this).data('detail') + '"]').click();
    });

    /*
    | Nominalnya diketik di tabel; yang ditanyakan dialog ini hanya
    | KETERANGAN — dan itu wajib: tombol konfirmasinya menolak jalan selama
    | kosong, dan server menolaknya lagi (ForecastController::storeActual)
    | supaya aturannya tidak bergantung pada peramban.
    |
    | Untuk baris KELUAR angka yang diketik tetap positif — arahnya sudah
    | melekat di barisnya, dan nilainya tercatat sebagai pengurang.
    */
    $(document).on('click', '.btn-fc-add-actual', function () {
        var detailId = $(this).data('detail');
        var keluar   = $(this).data('type') === 'OUT';
        var desc     = $(this).data('desc');
        var $inp     = $('.inp-actual-item[data-detail="' + detailId + '"]');
        var jumlah   = ($inp.val() || '').replace(/\D/g, '');

        if (!jumlah || parseInt(jumlah, 10) <= 0) {
            Swal.fire({
                icon: 'info',
                title: 'Nominal belum diisi',
                text: 'Isi dulu nominal realisasi di kolom Actual baris ini.',
                confirmButtonColor: '#55595e'
            }).then(function () { $inp.trigger('focus'); });
            return;
        }

        Swal.fire({
            title: 'Keterangan Realisasi',
            html:
                '<p style="font-size:12px;color:#6c757d;margin-bottom:10px;">'
                + esc(desc) + ' — realisasi yang ditambahkan: <strong>' + (keluar ? '-' : '') + esc(parseInt(jumlah, 10).toLocaleString('id-ID'))
                + '</strong>' + (keluar ? ' <span style="color:#dc3545;">(Keluar, dicatat sebagai pengurang)</span>' : '')
                + '</p>'
                + '<textarea id="swalNotes" class="swal2-textarea" placeholder="Keterangan (wajib)"></textarea>',
            customClass: { popup: 'fc-swal-notes' },
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#55595e',
            didOpen: function () { document.getElementById('swalNotes').focus(); },
            preConfirm: function () {
                var ket = (document.getElementById('swalNotes').value || '').trim();
                if (!ket) {
                    Swal.showValidationMessage('Keterangan wajib diisi sebelum realisasi ditambahkan.');
                    return false;
                }
                return ket;
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;

            $.post(BASE + '/' + idAktif + '/detail/' + detailId + '/actual', {
                _token: TOKEN,
                amount: jumlah,
                notes:  r.value
            }).done(function (res) {
                bukaDetail(idAktif, true);
                toast('success', res.message || 'Realisasi ditambahkan.');
            }).fail(function (xhr) {
                toast('error', pesanGagal(xhr, 'Realisasi gagal disimpan.'));
            });
        });
    });

    $(document).on('click', '.btn-fc-del-entry', function () {
        var entryId = $(this).data('entry');

        Swal.fire({
            title: 'Hapus realisasi ini?',
            text: 'Actual baris ini akan dihitung ulang.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;

            $.post(BASE + '/' + idAktif + '/actual/' + entryId, {
                _token: TOKEN,
                _method: 'DELETE'
            }).done(function (res) {
                bukaDetail(idAktif, true);
                toast('success', res.message || 'Realisasi dihapus.');
            }).fail(function (xhr) {
                toast('error', pesanGagal(xhr, 'Realisasi gagal dihapus.'));
            });
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
    if (mintaBuka) {
        bukaDetail(mintaBuka);

        /* Penandanya dibuang dari alamat setelah dipakai. Kalau dibiarkan, ia
           ikut tersimpan di riwayat peramban: menyegarkan halaman daftar
           (atau membukanya lagi dari bookmark) akan membuka modal detail
           dengan sendirinya, padahal yang diminta hanya daftarnya. */
        if (window.history.replaceState) {
            window.history.replaceState({}, '', window.location.pathname);
        }
    }
});
</script>
@endpush
