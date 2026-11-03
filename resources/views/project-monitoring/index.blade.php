@extends('layouts.app')

@section('title', 'Project Monitoring')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Project Monitoring</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Project</li>
                <li class="breadcrumb-item active">Project Monitoring</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar Project Monitoring"
            description="Progres PO (PO Received, BAST, Invoice, Payment) beserta pembaruan terakhirnya.">
            <x-slot:actions>
                <x-tw.button id="btnRefreshMon" variant="secondary"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Stepper status PO (pola daftar Purchase Order); klik tahap = filter tabel
             lewat data-postatus. Tahap Closing sengaja tidak ditampilkan, sama seperti
             halaman Purchase Order. --}}
        @php
            $poSteps = [
                ['Draft',              $summary['draft'],              'secondary', 'DRAFT'],
                ['Submitted',          $summary['submitted'],          'warning',   'SUBMITTED'],
                ['Verified',           $summary['verified'],           'info',      'VERIFIED'],
                ['Early Start',        $summary['early_start'],        'primary',   'EARLY_START'],
                ['Waiting PO',         $summary['waiting_po'],         'info',      'WAITING_PO'],
                ['Active',             $summary['active'],             'success',   'ACTIVE'],
                ['Waiting Invoice',    $summary['progress_done'],      'warning',   'PROGRESS_DONE'],
                ['Partially Invoiced', $summary['partially_invoiced'], 'warning',   'PARTIALLY_INVOICED'],
                ['Partially Paid',     $summary['partially_paid'],     'info',      'PARTIALLY_PAID'],
                ['Paid',               $summary['paid'],               'success',   'PAID'],
                ['Closed',             $summary['closed'],             'dark',      'CLOSED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$poSteps" :compact="true" table="tblMonitoring" field="postatus" :groups="[['Fase 1: Pengajuan', ['DRAFT','SUBMITTED','VERIFIED']], ['Fase 2: Persiapan', ['EARLY_START','WAITING_PO']], ['Fase 3: Pelaksanaan', ['ACTIVE','PROGRESS_DONE']], ['Fase 4: Penagihan', ['PARTIALLY_INVOICED','PARTIALLY_PAID','PAID','CLOSED']]]" />

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">

                {{-- FILTER --}}
                <div class="custom-toolbar mb-3 d-flex align-items-center flex-wrap" style="gap:8px;">
                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input" id="monSearch"></div>
                    </div>
                    <button aria-label="Clear Filter" type="button" id="btnClearFilterMon" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <div id="monRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    <div class="form-check ml-2" style="white-space:nowrap;">
                        <input type="checkbox" class="form-check-input" id="chkShowClosed">
                        <label class="form-check-label" for="chkShowClosed" style="font-size:13px;cursor:pointer;">Show Closed</label>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tblMonitoring" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="fit-col text-center">No</th>
                                <th scope="col" class="all">Scope of Work</th>
                                <th scope="col" class="fit-col text-center">Status</th>
                                <th scope="col" class="fit-col text-center">Implement</th>
                                <th scope="col" class="fit-col text-center">Progress</th>
                                <th scope="col" class="fit-col text-center">Update</th>
                                <th scope="col" class="fit-col text-center action-column"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                            @php
                                $progress = $project->implementationProgress();
                                $pmInternal = $project->pm_internal_name ?: '-';
                                $sc = $project->monitoring_status_color;
                                $labels = ['NOT_YET' => 'Not Yet', 'ON_GOING' => 'On Going', 'DONE' => 'Done'];
                            @endphp
                            @php
                                $lastUpdate = $project->last_update_at ? \Carbon\Carbon::parse($project->last_update_at) : $project->created_at;
                            @endphp
                            <tr data-href="{{ route('project-monitoring.show', $project->id) }}" data-pm="{{ $pmInternal }}" data-customer="{{ $project->customer->name ?? '' }}" data-status="{{ $project->status_label }}" data-postatus="{{ $project->status }}"
                                data-update="{{ $lastUpdate->timestamp }}">
                                <td class="fit-col text-center">{{ $loop->iteration }}</td>

                                <td>
                                    <div style="font-weight:600;margin-top:3px;">
                                        {{ $project->name }}
                                        @if(in_array($project->status, array_merge(['WAITING_PO', 'ACTIVE'], \App\Models\Project::PARTIAL_STATUSES)))
                                            @if($project->status === 'WAITING_PO' && $project->monitoring_po_received_status === 'DONE')
                                                <span class="badge badge-warning ml-1" style="font-size:10px;">Waiting Upload PO</span>
                                            @elseif($project->monitoring_bast_status === 'DONE')
                                                <span class="badge badge-warning ml-1" style="font-size:10px;">Waiting Upload BAST</span>
                                            @elseif($project->monitoring_payment_status === 'DONE')
                                                <span class="badge badge-warning ml-1" style="font-size:10px;">Waiting Closed</span>
                                            @endif
                                        @endif
                                    </div>
                                    <div style="font-size:11px;color:var(--text-faint);">{{ $project->code }} - {{ $project->customer->name ?? '-' }} - {{ $pmInternal }}</div>
                                </td>

                                <td class="fit-col text-center">
                                    <span class="badge badge-{{ $project->status_color }} px-2 py-1">{{ $project->status_label }}</span>
                                </td>

                                <td class="fit-col text-center" data-order="{{ $progress }}">
                                    {{ rtrim(rtrim(number_format($progress, 1, ',', '.'), '0'), ',') }}%
                                </td>

                                <td class="fit-col text-center text-nowrap">
                                    <span class="badge badge-{{ $sc['po_received'] }} po-mini-badge" title="PO Received: {{ $labels[$project->monitoring_po_received_status] ?? $project->monitoring_po_received_status }}">R</span>
                                    <span class="badge badge-{{ $sc['bast'] }} po-mini-badge" title="BAST: {{ $labels[$project->monitoring_bast_status] ?? $project->monitoring_bast_status }}">B</span>
                                    <span class="badge badge-{{ $sc['invoice'] }} po-mini-badge" title="Invoice: {{ $labels[$project->monitoring_invoice_status] ?? $project->monitoring_invoice_status }}">I</span>
                                    <span class="badge badge-{{ $sc['payment'] }} po-mini-badge" title="Payment: {{ $labels[$project->monitoring_payment_status] ?? $project->monitoring_payment_status }}">P</span>
                                </td>

                                <td class="fit-col text-center" data-order="{{ $lastUpdate->timestamp }}">
                                    <strong style="white-space:nowrap;">{{ $lastUpdate->format('d/m/y') }}</strong>
                                    <small class="d-block text-muted" style="white-space:nowrap;">{{ $lastUpdate->format('H:i') }}</small>
                                </td>

                                <td class="fit-col text-center action-column">
                                    {{-- Detail Log dibuka lewat klik baris; satu-satunya aksi: Info Purchase Order (panel samping). --}}
                                    <div class="action-buttons ts-actions">
                                        <button type="button" class="ts-action-primary is-neutral btn-info-po"
                                                data-id="{{ $project->id }}"
                                                title="Info Purchase Order" aria-label="Info Purchase Order {{ $project->code }}">
                                            <i class="fas fa-info-circle" aria-hidden="true"></i><span class="ts-al">Info PO</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty title="Belum ada Purchase Order yang perlu dimonitor." icon="fas fa-folder-open" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </x-tw.card>
      </div>
    </div>
</div>

{{-- PANEL SAMPING INFO PURCHASE ORDER (read-only: Informasi, Milestone, PIC) --}}
<x-drawer id="poInfoDrawer" label="Info Purchase Order" width="max(50vw, 560px)">
    <div id="poInfoContent" hidden>
        <ul class="nav nav-tabs mb-3" id="poInfoTabs" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#poPaneInfo" style="font-size:12px;font-weight:600;padding:8px 10px;">
                    <i class="fas fa-info-circle mr-1"></i> Informasi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#poPaneDetails" style="font-size:12px;font-weight:600;padding:8px 10px;">
                    <i class="fas fa-project-diagram mr-1"></i> Milestone
                    <span id="poDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#poPanePics" style="font-size:12px;font-weight:600;padding:8px 10px;">
                    <i class="fas fa-users mr-1"></i> PIC
                    <span id="poPicCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <!-- TAB INFO -->
            <div class="tab-pane fade show active" id="poPaneInfo">
                <table class="table table-bordered mb-0" style="font-size:13px;">
                    <tr><th width="30%" style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Kode</th><td id="poInfoCode"></td></tr>
                    <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Scope of Work</th><td id="poInfoName"></td></tr>
                    <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Customer</th><td id="poInfoCustomer"></td></tr>
                    <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Term of Payment</th><td id="poInfoToP"></td></tr>
                    <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Status</th><td id="poInfoStatus"></td></tr>
                    <tr><th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Notes</th><td id="poInfoNotes" style="white-space:pre-line;"></td></tr>
                </table>
            </div>
    
            <!-- TAB PROJECT DETAIL -->
            <div class="tab-pane fade" id="poPaneDetails">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" style="font-size:12px;">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;width:5%;">No</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Milestone</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Status</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Plan Start</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Plan End</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Actual Start</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Actual End</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Notes</th>
                            </tr>
                        </thead>
                        <tbody id="poDetailBody"></tbody>
                    </table>
                </div>
            </div>
    
            <!-- TAB PIC -->
            <div class="tab-pane fade" id="poPanePics">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" style="font-size:12px;">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;width:5%;">No</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Nama PIC</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Jabatan</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Whatsapp</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Email</th>
                            </tr>
                        </thead>
                        <tbody id="poPicBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="poInfoError" hidden></div>

    <div id="poInfoLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="5" />
    </div>

    <x-slot:footer>
        <small class="text-muted mr-auto"><i class="fas fa-info-circle mr-1"></i> Read-only.</small>
        <a href="#" id="poInfoOpenLog" class="btn btn-primary btn-sm"><i class="fas fa-stream mr-1"></i> Buka Detail Log</a>
    </x-slot:footer>
</x-drawer>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
#tblMonitoring tbody tr[data-href] { cursor:pointer; }
.fit-col             { white-space: nowrap; width: 1%; }
.action-column       { min-width:80px !important; }
.po-mini-badge       { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; line-height:1; padding:0; margin:0 1px; font-size:9px; font-weight:700; border-radius:50%; text-align:center; cursor:default; opacity:.92; }
.po-mini-badge:hover { opacity:1; transform:scale(1.08); }
.custom-toolbar      { display:flex; align-items:flex-start; justify-content:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#monSearch input     { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
#tblMonitoring       { width:100% !important; }
@media (max-width:576px) {
    .custom-toolbar      { flex-direction:column !important; align-items:stretch !important; }
    #btnClearFilterMon   { width:100%; }
    #monRowFilters       { width:100%; }
    #monRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #monSearch input     { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .btn { width:100%; }
    .card-header > div { width:100%; }
    #monRowFilters .tcf-row-btn { flex:1 1 auto; }
}

/* --- Filter dropdown: bedakan header grup vs item --- */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text) !important;
    background: var(--surface-2) !important;
    padding: 8px 12px !important;
    border-top: 1px solid var(--border);
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header {
    border-top: none;
}
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option {
    padding-left: 26px !important;
}

#poInfoTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#poInfoTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }

#poDetailBody tr.mon-group-row { cursor:pointer; background:var(--info-bg) !important; }
#poDetailBody tr.mon-group-row:hover { background:var(--surface-3) !important; }
#poDetailBody tr.mon-group-row td { background:transparent !important; }
#poDetailBody .mon-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
#poDetailBody .mon-caret.rotated { transform:rotate(90deg); }
#poDetailBody td.mon-indent { padding-left:28px !important; }

.mon-pivot-group-row { cursor:pointer; background:var(--info-bg) !important; }
.mon-pivot-group-row:hover { background:var(--surface-3) !important; }
.mon-pivot-group-row td { background:transparent !important; }
.mon-pivot-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
.mon-pivot-caret.rotated { transform:rotate(90deg); }
.mon-pivot-indent { padding-left:28px !important; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    $('#btnRefreshMon').on('click', function () { location.reload(); });

    /* Klik di mana saja pada baris membuka Detail Log (pola Cost Request). Kontrol di dalam baris
       (tombol Info PO, kontrol responsif DataTables) bekerja sendiri. */
    $(document).on('click', '#tblMonitoring tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu').length) return;
        /* Responsive (mode inline) memberi kelas dtr-control pada kolom pertama yang
           terlihat — di sini Scope of Work. Sel itu hanya berfungsi sebagai tombol
           buka-tutup rincian saat ada kolom yang disembunyikan (tabel .collapsed);
           selain itu klik di sana tetap membuka Detail Log. */
        if ($(e.target).closest('td.dtr-control').length && $('#tblMonitoring').hasClass('collapsed')) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        window.location.href = $(this).attr('data-href');
    });
    var table = $('#tblMonitoring').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        columnDefs: [
            { orderable: false, visible: false, targets: [0] },
            { responsivePriority: 1, targets: [0, 1, -1] },
            { responsivePriority: 2, targets: [2, 3, 4, 5] },
        ],
        order: [[5, 'desc']],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: { lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });

    $('#tblMonitoring_filter').hide();
    $('#monSearch').html('<input type="search" id="monSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#monSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    TesysColumnFilter.initRowFilters('#tblMonitoring', '#monRowFilters', [
        { key: 'customer', label: 'Customer' },
        { key: 'pm',       label: 'Manager' },
        { key: 'status',   label: 'Status' },
    ]);

    $('#btnClearFilterMon').on('click', function () {
        TesysColumnFilter.clearAll('#tblMonitoring');
        TesysWorkflowStepper.clear('tblMonitoring');
    });

    /* Klik tahap Closed di stepper: PO Closed disembunyikan secara default,
       jadi "Show Closed" ikut dicentang supaya hasil saringannya tidak kosong. */
    $(document).on('click', '[data-wf-table="tblMonitoring"] .wf-tl-step[data-status="CLOSED"]', function () {
        if ($(this).hasClass('wf-active') && !$('#chkShowClosed').is(':checked')) {
            $('#chkShowClosed').prop('checked', true);
            table.draw();
        }
    });

    /* ── SHOW CLOSED — default sembunyikan PO berstatus Closed ── */
    $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
        if (settings.nTable.id !== 'tblMonitoring') return true;
        if ($('#chkShowClosed').is(':checked')) return true;

        var status = $(table.row(index).node()).data('status') || '';
        return String(status).toLowerCase() !== 'closed';
    });

    $('#chkShowClosed').on('change', function () { table.draw(); });
    table.draw();

    /* ── PANEL SAMPING INFO PURCHASE ORDER (ringkas: Informasi, Milestone, PIC) ── */
    var poStatusColors = {
        'DRAFT':'secondary','SUBMITTED':'warning',
        'WAITING_PO':'info','ACTIVE':'success','CLOSED':'dark'
    };

    var poInfoAktif = null;

    $(document).on('click', '.btn-info-po', function () {
        var id = $(this).data('id');
        var href = $(this).closest('tr').attr('data-href') || '#';
        $('#poInfoContent').attr('hidden', true);
        $('#poInfoError').attr('hidden', true).empty();
        $('#poInfoLoading').show();
        $('#poInfoDrawer-eyebrow').text('');
        $('#poInfoDrawer-title').text('Memuat…');
        $('#poInfoOpenLog').attr('href', href);
        $('#poInfoTabs a:first').tab('show');
        TesysDrawer.open('poInfoDrawer', this);
        poInfoAktif = id;

        $.get('/project/' + id + '/detail-json', function (d) {
            // Respons PO yang dibuka sebelumnya diabaikan bila user sudah pindah PO.
            if (poInfoAktif !== id) return;
            $('#poInfoDrawer-eyebrow').text((d.code || '-') + ' · ' + (d.customer || '-'));
            $('#poInfoDrawer-title').text(d.name);

            // Tab Info (umum saja — tanpa nilai kontrak/budget)
            $('#poInfoCode').html('<span class="badge badge-dark px-2 py-1">' + (d.code || '-') + '</span>');
            $('#poInfoName').text(d.name);
            $('#poInfoCustomer').text(d.customer);
            $('#poInfoToP').text(d.term_of_payment || '-');
            $('#poInfoStatus').html('<span class="badge badge-' + (poStatusColors[d.status]||'secondary') + ' px-2 py-1">' + d.status_label + '</span>');
            $('#poInfoNotes').text(d.notes || '-');

            // Tab Milestone — grouped by Project (cluster), collapsible, default expand
            var monStatusColors = { 'NOT_STARTED':'secondary', 'ON_GOING':'warning', 'DONE':'success' };
            $('#poDetailCount').text(d.details.length);
            var monGroups = {};
            var monGroupOrder = [];
            (d.details || []).forEach(function (det) {
                var key = det.cluster || '-';
                if (!monGroups[key]) { monGroups[key] = []; monGroupOrder.push(key); }
                monGroups[key].push(det);
            });

            var detHtml = '';
            monGroupOrder.forEach(function (key, g) {
                var items = monGroups[key];
                detHtml += '<tr class="mon-group-row" data-target="mon-grp-' + g + '">' +
                    '<td colspan="8">' +
                        '<i class="fas fa-chevron-right mon-caret rotated mr-2"></i>' +
                        '<strong>' + key + '</strong> ' +
                        '<span class="text-muted" style="font-size:11px;">(' + items.length + ' milestone)</span>' +
                    '</td>' +
                '</tr>';

                items.forEach(function (det, i) {
                    var statusBadge = det.status
                        ? '<span class="badge badge-' + (monStatusColors[det.status] || 'secondary') + ' px-2 py-1" style="font-size:10px;">' + (det.status_label || det.status) + '</span>'
                        : '-';
                    detHtml += '<tr class="mon-grp-' + g + '">' +
                        '<td class="text-center mon-indent">' + (i + 1) + '</td>' +
                        '<td>' + det.stage + '</td>' +
                        '<td>' + statusBadge + '</td>' +
                        '<td>' + (det.plan_start || '-') + '</td>' +
                        '<td>' + (det.plan_end || '-') + '</td>' +
                        '<td>' + (det.actual_start || '-') + '</td>' +
                        '<td>' + (det.actual_end || '-') + '</td>' +
                        '<td>' + (det.notes || '-') + '</td>' +
                    '</tr>';
                });
            });
            $('#poDetailBody').html(detHtml || '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada data detail.</td></tr>');

            // Tab PIC — grouped by tipe (Internal/Customer), collapsible, default collapsed
            var picGroups = {
                'Internal': (d.pics_internal || []),
                'Customer': (d.pics_customer || [])
            };
            var picBadge = { 'Internal': 'secondary', 'Customer': 'primary' };
            var allPicsCount = (d.pics_internal || []).length + (d.pics_customer || []).length;
            $('#poPicCount').text(allPicsCount);
            var picHtml = '';
            Object.keys(picGroups).forEach(function (type, gi) {
                var items = picGroups[type];
                if (!items.length) return;
                var groupClass = 'mon-pic-grp-' + gi;
                picHtml += '<tr class="mon-pivot-group-row" data-target="' + groupClass + '">' +
                    '<td colspan="5" style="font-weight:700;">' +
                        '<i class="fas fa-chevron-right mr-2 mon-pivot-caret"></i>' +
                        '<span class="badge badge-' + picBadge[type] + ' px-2 py-1 mr-1" style="font-size:10px;">' + type + '</span>' +
                        '<span class="text-muted" style="font-size:11px;">(' + items.length + ' PIC)</span>' +
                    '</td>' +
                '</tr>';
                items.forEach(function (p, i) {
                    picHtml += '<tr class="' + groupClass + '" style="display:none;">' +
                        '<td class="text-center mon-pivot-indent">' + (i + 1) + '</td>' +
                        '<td>' + (p.pic || '-') + '</td>' +
                        '<td>' + (p.position || '-') + '</td>' +
                        '<td>' + (p.phone || '-') + '</td>' +
                        '<td>' + (p.email || '-') + '</td>' +
                    '</tr>';
                });
            });
            $('#poPicBody').html(picHtml || '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada PIC.</td></tr>');

            $('#poInfoLoading').hide();
            $('#poInfoContent').removeAttr('hidden');
        }).fail(function () {
            if (poInfoAktif !== id) return;
            $('#poInfoDrawer-title').text('Info Purchase Order');
            $('#poInfoLoading').hide();
            $('#poInfoError').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>').removeAttr('hidden');
        });
    });

    // Grouping/collapse tabel Milestone (default expand)
    $(document).on('click', '#poDetailBody .mon-group-row', function () {
        var target = $(this).data('target');
        $('#poDetailBody .' + target).toggle();
        $(this).find('.mon-caret').toggleClass('rotated');
    });

    // Grouping/collapse tabel PIC (default collapsed)
    $(document).on('click', '.mon-pivot-group-row', function () {
        var target = $(this).data('target');
        $(this).closest('table').find('.' + target).toggle();
        $(this).find('.mon-pivot-caret').toggleClass('rotated');
    });
});
</script>
@endpush