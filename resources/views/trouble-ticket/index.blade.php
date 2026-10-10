@extends('layouts.app')

@section('title', 'Trouble Ticket')

@php
    use App\Models\TroubleTicket;
    use Illuminate\Support\Str;

    /*
     | Kolom tabel: key => [label, default tampil?]. Indeks DataTables = urutan di
     | sini; kolom Action (terakhir) selalu tampil dan dibekukan di kanan.
     */
    $kolom = [
        'no'         => ['No. TT', true],
        'ref'        => ['Ref. Eksternal', false],
        'customer'   => ['Customer', false],
        'site'       => ['Site', true],
        'problem'    => ['Problem', true],
        'severity'   => ['Severity', true],
        'issue'      => ['Issue', false],
        'device'     => ['Device / Alarm', false],
        'teknisi'    => ['Teknisi', false],
        'status'     => ['Status', true],
        'plan'       => ['Plan', false],
        'response'   => ['Response', false],
        'travel'     => ['Travel', false],
        'resolution' => ['Resolution', false],
        'reported'   => ['Dilaporkan', false],
        'created'    => ['Dibuat', false],
        'updated'    => ['Update', true],
    ];
    $colIndex   = array_flip(array_keys($kolom));
    $colDefault = array_map(fn ($k) => $k[1], $kolom);
    // Nilai SLA untuk sort: detik (berjalan maupun final); belum mulai = -1.
    $slaSort = fn ($m) => $m['seconds'] ?? -1;
@endphp

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">
        <x-stat-cards :cards="[
            ['Terbuka', $stats['terbuka'], 'info', 'fas fa-ticket-alt'],
            ['Lewat SLA', $stats['overdue'], 'danger', 'fas fa-exclamation-triangle', 'hanya tiket ber-target SLA'],
            ['Menunggu Approval Plan', $stats['menunggu_approval'], 'warning', 'fas fa-user-check'],
            ['Closed Bulan Ini', $stats['closed_bulan_ini'], 'success', 'fas fa-check-circle'],
        ]" />

        <div class="card shadow-sm">
            <div class="card-body">
                @if($terpotong)
                    <p class="text-muted mb-2" style="font-size:12px;"><i class="fas fa-info-circle mr-1"></i>Menampilkan 1.000 tiket terbaru dari {{ number_format($total, 0, ',', '.') }}. Filter &amp; urutan berlaku pada 1.000 tiket ini.</p>
                @endif

                {{-- Toolbar. Kiri: cari · clear · Filter (filter kolom) · Kolom · lewat SLA.
                     Kanan: Export · Pengaturan · Buat Tiket. --}}
                {{-- Rentang tanggal dilaporkan: di luar kotak toolbar, rata kanan (default bulan berjalan). --}}
                <div class="d-flex justify-content-end mb-2">
                    <div class="tt-date-range" title="Tanggal dilaporkan">
                        <input type="text" id="ttDateFrom" class="form-control form-control-sm" placeholder="Dari">
                        <span class="text-muted">s/d</span>
                        <input type="text" id="ttDateTo" class="form-control form-control-sm" placeholder="Sampai">
                    </div>
                </div>
                <div class="tt-toolbar mb-3">
                    <div class="tt-toolbar-left">
                        <div class="tt-search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="search" id="ttSearch" class="form-control form-control-sm" placeholder="Cari No. TT, site, issue, alarm..." aria-label="Cari">
                        </div>
                        <button type="button" id="btnTtClear" class="btn btn-outline-secondary btn-sm" title="Clear Filter" aria-label="Clear Filter"><i class="fas fa-eraser"></i></button>
                        {{-- TesysColumnFilter merender tombol "Filter" di sini; klik = daftar filter kolom. --}}
                        <div id="ttRowFilters" class="d-flex align-items-center"></div>
                        <div class="dropdown" id="ttColVis">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" data-display="static">
                                <i class="fas fa-columns mr-1"></i> Kolom
                            </button>
                            <div class="dropdown-menu p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                                @foreach($kolom as $key => [$label])
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input tt-col-toggle" id="ttCol_{{ $key }}" data-key="{{ $key }}">
                                        <label class="custom-control-label" for="ttCol_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                                    </div>
                                @endforeach
                                <div class="dropdown-divider"></div>
                                <button type="button" class="btn btn-link btn-sm p-0" id="btnTtColReset" style="font-size:12px;">Kembalikan kolom default</button>
                            </div>
                        </div>
                        <div class="custom-control custom-switch ml-1">
                            <input type="checkbox" class="custom-control-input" id="ttOnlyOverdue">
                            <label class="custom-control-label" for="ttOnlyOverdue" style="font-size:12.5px;">Lewat SLA</label>
                        </div>
                    </div>
                    <div class="tt-toolbar-right">
                        <a href="{{ route('trouble-ticket.export') }}" class="btn btn-outline-success btn-sm" id="btnTtExport"><i class="fas fa-file-excel mr-1"></i> Export</a>
                        @if($canSettings)
                            <a href="{{ route('trouble-ticket.settings') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sliders-h mr-1"></i> Pengaturan</a>
                        @endif
                        <a href="{{ route('trouble-ticket.create') }}" class="btn btn-success btn-sm"><i class="fas fa-plus mr-1"></i> Buat Tiket</a>
                    </div>
                </div>

                <div class="table-responsive tt-dt-wrap">
                    <table id="tblTt" class="table table-bordered table-hover table-striped mb-0 tt-dt">
                        <thead class="bg-light">
                            <tr>
                                @foreach($kolom as $key => [$label])
                                    <th scope="col" class="{{ $key === 'site' ? 'col-site' : 'col-fit' }}">{{ $label }}</th>
                                @endforeach
                                <th scope="col" class="text-center col-action tt-freeze">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $t)
                                @php
                                    $siteLabel = $t->site?->sitename ?? '';
                                    $mResp = $t->slaMetric('response');
                                    $mTrav = $t->slaMetric('travel');
                                    $mReso = $t->slaMetric('resolution');
                                    $tglLapor = $t->reported_at ?? $t->created_at;
                                @endphp
                                <tr data-id="{{ $t->id }}" data-status="{{ \App\Support\WorkflowStatus::label($t->status, 'trouble-ticket') }}"
                                    data-severity="{{ $t->severity_label }}"
                                    data-problem="{{ $t->problem?->name ?? '-' }}"
                                    data-customer="{{ $t->customer?->name ?? '-' }}"
                                    data-site="{{ $siteLabel ?: '-' }}"
                                    data-teknisi="{{ $t->technician?->name ?? '-' }}"
                                    data-reported="{{ $tglLapor->format('Y-m-d') }}"
                                    data-overdue="{{ isset($overdueIds[$t->id]) ? 1 : 0 }}">
                                    <td class="col-fit" data-order="{{ $t->id }}">
                                        <a href="{{ route('trouble-ticket.show', $t) }}" class="tt-no-link" title="Buka tiket">{{ $t->ticket_number }}</a>
                                    </td>
                                    <td class="col-fit">{{ $t->external_ref ?: '-' }}</td>
                                    <td class="col-fit">{{ $t->customer?->name ?? '-' }}</td>
                                    <td class="col-site">
                                        <span class="cell-trunc" style="max-width:60ch;" title="{{ $siteLabel }}">{{ Str::limit($siteLabel, 60, '...') ?: '-' }}</span>
                                    </td>
                                    <td class="col-fit">{{ $t->problem?->name ?? '-' }}</td>
                                    <td class="col-fit" data-order="{{ $t->severity_level }}"><span class="ts-status ts-status-sm ts-status-{{ [1 => 'danger', 2 => 'attention', 3 => 'progress'][$t->severity_level] ?? 'neutral' }}">{{ $t->severity_label }}</span></td>
                                    <td class="col-fit" title="{{ $t->issue }}">{{ Str::limit($t->issue, 50, '...') }}</td>
                                    <td class="col-fit">{{ $t->device ?: '-' }}{{ $t->flag_alarm ? ' · ' . $t->flag_alarm : '' }}</td>
                                    <td class="col-fit">{{ $t->technician?->name ?? '-' }}</td>
                                    <td class="col-fit" data-order="{{ array_search($t->status, TroubleTicket::STATUSES, true) }}">
                                        <x-status :status="$t->status" context="trouble-ticket" />
                                        @if(isset($overdueIds[$t->id]))<i class="fas fa-exclamation-circle text-danger ml-1" title="Lewat SLA"></i>@endif
                                    </td>
                                    <td class="col-fit">{{ $t->visit_decision_label ?? '-' }}{{ $t->visit_site ? ' · ' . $t->visit_site->format('d/m/y') : '' }}</td>
                                    <td class="col-fit" data-order="{{ $slaSort($mResp) }}">@include('trouble-ticket._sla', ['m' => $mResp, 'compact' => true])</td>
                                    <td class="col-fit" data-order="{{ $slaSort($mTrav) }}">@include('trouble-ticket._sla', ['m' => $mTrav, 'compact' => true])</td>
                                    <td class="col-fit" data-order="{{ $slaSort($mReso) }}">@include('trouble-ticket._sla', ['m' => $mReso, 'compact' => true])</td>
                                    <td class="col-fit" data-order="{{ $tglLapor->timestamp }}">{{ $tglLapor->format('d/m/y H:i') }}</td>
                                    <td class="col-fit" data-order="{{ $t->created_at->timestamp }}">{{ $t->created_at->format('d/m/y H:i') }}</td>
                                    <td class="col-fit" data-order="{{ $t->updated_at->timestamp }}">{{ $t->updated_at->format('d/m/y H:i') }}</td>
                                    <td class="text-center action-column tt-freeze">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('trouble-ticket.show', $t) }}" class="ts-action-primary is-neutral" title="Buka tiket" aria-label="Buka tiket">
                                                <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                            </a>
                                            <button type="button" class="ts-action-primary is-neutral btn-tt-info" data-url="{{ route('trouble-ticket.info', $t) }}" data-no="{{ $t->ticket_number }}" data-open="{{ route('trouble-ticket.show', $t) }}" title="Info" aria-label="Info">
                                                <i class="fas fa-info" aria-hidden="true"></i><span class="ts-al">Info</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal info tiket (tidak membuka tiket) --}}
<div class="modal fade" id="mdlTtInfo" tabindex="-1" role="dialog" aria-labelledby="mdlTtInfoTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="mdlTtInfoTitle"><i class="fas fa-ticket-alt mr-1"></i> <span>Trouble Ticket</span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="mdlTtInfoBody" style="font-size:13px;"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                <a href="#" class="btn btn-primary btn-sm" id="mdlTtInfoOpen"><i class="fas fa-eye mr-1"></i> Buka Tiket</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.tt-page .ts-kpi-row { margin-top:16px; }
/* Semua kolom selebar isinya, kecuali Site (sisa ruang, dipotong 60 karakter). */
.tt-dt { width:100% !important; font-size:12.5px; }
.tt-dt th, .tt-dt td { vertical-align:middle; }
.tt-dt .col-fit { white-space:nowrap; width:1%; }
.tt-dt .col-site { white-space:nowrap; }
.tt-dt .cell-trunc { display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:bottom; }
/* No. TT: hitam tebal; hover = pil gelap, sama dengan kode CR (.cr-number-btn). */
.tt-no-link { display:inline-block; padding:2px 8px; margin-left:-8px; border-radius:var(--radius-sm); color:var(--text); font-weight:700;
    line-height:1.4; transition:background-color .12s ease, color .12s ease; }
.tt-dt a.tt-no-link, .tt-dt a.tt-no-link:visited { color:var(--text) !important; }
.tt-dt a.tt-no-link:hover, .tt-dt a.tt-no-link:focus { background:var(--charcoal); color:#fff !important; text-decoration:none; outline:none; }
/* Kolom Action dibekukan di kanan saat tabel digulir mendatar. */
.tt-dt .tt-freeze { position:sticky; right:0; z-index:2; background:var(--card-bg); box-shadow:-1px 0 0 var(--border); width:1%; white-space:nowrap; }
.tt-dt thead .tt-freeze { background:var(--surface-2); z-index:3; }
.tt-dt.table-striped tbody tr:nth-of-type(odd) .tt-freeze { background:var(--surface); }
.tt-dt.table-hover tbody tr:hover .tt-freeze { background:var(--surface-2); }

/* Toolbar: kiri pencarian & filter, kanan tanggal & tombol */
.tt-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;
    padding:10px 12px; background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-md); }
.tt-toolbar-left, .tt-toolbar-right { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.tt-toolbar-right { margin-left:auto; }
.tt-search { position:relative; width:260px; max-width:100%; }
.tt-search > i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-faint); font-size:12px; pointer-events:none; }
.tt-search input.form-control { padding-left:32px !important; }
.tt-search > i { z-index:2; }
.tt-date-range { display:flex; align-items:center; gap:6px; }
.tt-date-range .form-control, .tt-date-range input.flatpickr-input + input { width:118px; }
@media (max-width: 767.98px) {
    .tt-toolbar-right { margin-left:0; }
    .tt-search { width:100%; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var colIndex   = @json($colIndex);
    var colDefault = @json($colDefault);
    var colKey     = 'tesys-colvis:trouble-ticket-index';

    var table = $('#tblTt').DataTable({
        responsive: false, autoWidth: false, pageLength: 25, ordering: true,
        lengthMenu: [10, 25, 50, 100],
        order: [[colIndex.updated, 'desc']],
        columnDefs: [{ orderable: false, targets: -1 }],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_', zeroRecords: 'Tidak ada tiket yang cocok dengan filter',
            emptyTable: 'Belum ada Trouble Ticket yang bisa Anda lihat',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ tiket', infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_)',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#ttSearch').on('keyup search', function () { table.search(this.value).draw(); });

    // Export mengikuti filter aktif (cari, filter kolom, tanggal, lewat SLA).
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnTtExport', table, @json(route('trouble-ticket.export')));
    }

    /* ---------- Pilih kolom (disimpan per browser) ---------- */
    function readPref() { try { return JSON.parse(localStorage.getItem(colKey) || '{}') || {}; } catch (e) { return {}; } }
    function writePref(p) { try { localStorage.setItem(colKey, JSON.stringify(p)); } catch (e) {} }
    function applyColVis() {
        var pref = readPref();
        Object.keys(colIndex).forEach(function (k) {
            var vis = pref.hasOwnProperty(k) ? !!pref[k] : !!colDefault[k];
            table.column(colIndex[k]).visible(vis, false);
            $('#ttCol_' + k).prop('checked', vis);
        });
        table.columns.adjust().draw(false);
    }
    applyColVis();
    $('.tt-col-toggle').on('change', function () {
        var pref = readPref(); pref[$(this).data('key')] = this.checked; writePref(pref); applyColVis();
    });
    $('#btnTtColReset').on('click', function () { writePref({}); applyColVis(); });
    $('#ttColVis .dropdown-menu').on('click', function (e) { e.stopPropagation(); });

    /* ---------- Filter kolom: tombol "Filter" milik TesysColumnFilter ---------- */
    TesysColumnFilter.initRowFilters('#tblTt', '#ttRowFilters', [
        { key: 'status', label: 'Status' }, { key: 'severity', label: 'Severity' },
        { key: 'problem', label: 'Problem' }, { key: 'customer', label: 'Customer' },
        { key: 'site', label: 'Site' }, { key: 'teknisi', label: 'Teknisi' }
    ]);

    /* ---------- Rentang tanggal dilaporkan (default bulan berjalan) & lewat SLA ---------- */
    var awalBulan = @json(now()->startOfMonth()->toDateString());
    var akhirBulan = @json(now()->endOfMonth()->toDateString());
    var dari = awalBulan, sampai = akhirBulan;
    $.fn.dataTable.ext.search.push(function (settings, data, idx) {
        if (settings.nTable.id !== 'tblTt') return true;
        var tr = settings.aoData[idx].nTr;
        if (!tr) return true;
        var tgl = tr.getAttribute('data-reported');
        if (dari && tgl < dari) return false;
        if (sampai && tgl > sampai) return false;
        if ($('#ttOnlyOverdue').is(':checked') && tr.getAttribute('data-overdue') !== '1') return false;
        return true;
    });
    var fpOpt = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', locale: (window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.id) || 'default' };
    var fpDari = flatpickr('#ttDateFrom', $.extend({}, fpOpt, { defaultDate: awalBulan, onChange: function (s, str) { dari = str; table.draw(); } }));
    var fpSampai = flatpickr('#ttDateTo', $.extend({}, fpOpt, { defaultDate: akhirBulan, onChange: function (s, str) { sampai = str; table.draw(); } }));
    $('#ttOnlyOverdue').on('change', function () { table.draw(); });
    table.draw();

    // Clear: kosongkan pencarian & filter kolom; tanggal kembali ke bulan berjalan.
    $('#btnTtClear').on('click', function () {
        TesysColumnFilter.clearAll('#tblTt');
        dari = awalBulan; sampai = akhirBulan;
        fpDari.setDate(awalBulan, false); fpSampai.setDate(akhirBulan, false);
        $('#ttOnlyOverdue').prop('checked', false);
        $('#ttSearch').val('');
        table.search('').draw();
    });

    /* ---------- Modal info ---------- */
    $(document).on('click', '.btn-tt-info', function () {
        var $b = $(this);
        $('#mdlTtInfoTitle span').text($b.data('no'));
        $('#mdlTtInfoOpen').attr('href', $b.data('open'));
        $('#mdlTtInfoBody').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</div>');
        $('#mdlTtInfo').modal('show');
        $.get($b.data('url'))
            .done(function (html) { $('#mdlTtInfoBody').html(html); })
            .fail(function () { $('#mdlTtInfoBody').html('<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Data tidak dapat dimuat.</p>'); });
    });

    TtTimers.start(@json(now()->toIso8601String()));
});
</script>
@endpush
