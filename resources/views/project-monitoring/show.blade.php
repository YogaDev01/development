@extends('layouts.app')

@section('title', 'Detail Project Monitoring')

@php
    /*
     | $project->monitoringLogs sudah dibatasi ProjectMonitoringController::show()
     | ke $logLimit log TERBARU (diurutkan kronologis kembali). $lastLogId
     | karena itu tetap id tertinggi yang dirender, sehingga polling
     | fetchMessages() (where id > lastLogId) tetap benar.
     */
    $allLogs = $project->monitoringLogs;
    $lastLogId = $allLogs->max('id') ?? 0;
@endphp

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/project-monitoring') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                        {{ $project->name }}
                    </h1>
                    <small class="text-muted">{{ $project->code }} — {{ $project->customer->name ?? '-' }}</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Project</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/project-monitoring') }}">Project Monitoring</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="card shadow-sm mb-0" style="overflow:hidden;">
            <div class="row no-gutters monitoring-row">

                {{-- SIDEBAR: CLUSTER (dropdown toggle) > MILESTONE --}}
                <div class="col-md-4 col-lg-3 d-flex flex-column monitoring-sidebar">
                    <div class="sidebar-topbar d-flex align-items-center" style="gap:6px;">
                        {{-- Placeholder menyesuaikan kenyataan: pencarian ini
                             bekerja di peramban atas log yang DIMUAT, dan
                             pemuatan awal dibatasi (lihat banner di bawah).
                             Menjanjikan "seluruh log" saat sebagiannya tidak
                             ada di halaman hanya membuat orang menyimpulkan
                             datanya hilang. --}}
                        <input type="text" id="chatSearch" class="form-control form-control-sm"
                               placeholder="{{ ($logsTruncated ?? false) ? 'Cari di log terbaru...' : 'Cari di seluruh log...' }}">
                        @if($canManage)
                        <button aria-label="Atur Bobot Milestone" type="button" class="btn btn-outline-secondary btn-sm" id="btnEditWeights" title="Atur Bobot Milestone" style="flex-shrink:0;">
                            <i class="fas fa-balance-scale"></i>
                        </button>
                        @endif
                    </div>

                    <div class="sidebar-scroll">
                        {{-- COST REQUEST — per Budget Plan --}}
                        <div class="cluster-block">
                            <div class="cluster-toggle" data-target="crGroup">
                                <span><i class="fas fa-chevron-down mr-1 caret-icon"></i> Cost Request</span>
                            </div>
                            <div id="crGroup">
                                @forelse($budgetPlans as $bp)
                                <div class="sidebar-item" data-scope="budget"
                                     data-budget-id="{{ $bp->id }}"
                                     data-name="{{ $bp->description }}"
                                     data-bp-status="{{ $bp->status }}">
                                    <span class="sidebar-item-main">
                                        <i class="fas fa-file-invoice-dollar text-muted" style="font-size:11px;flex-shrink:0;"></i>
                                        <span class="sidebar-item-name">{{ $bp->description }}</span>
                                    </span>
                                </div>
                                @empty
                                <div class="text-muted" style="padding:8px 14px 8px 40px;font-size:11px;">Belum ada Budget Plan.</div>
                                @endforelse
                            </div>
                        </div>

                        @forelse($clusters as $cluster)
                        <div class="cluster-block">
                            <div class="cluster-toggle" data-target="clusterGroup{{ $cluster->id }}">
                                <span><i class="fas fa-chevron-down mr-1 caret-icon"></i> {{ $cluster->name }}</span>
                            </div>

                            <div id="clusterGroup{{ $cluster->id }}">
                                @forelse(($detailsByCluster[$cluster->id] ?? collect()) as $d)
                                <div class="sidebar-item" data-scope="detail"
                                     data-detail-id="{{ $d->id }}"
                                     data-name="{{ $d->stage->name ?? '-' }}"
                                     data-weight="{{ $d->weight }}"
                                     data-status="{{ $d->status }}"
                                     data-status-label="{{ $d->status_label }}"
                                     data-status-color="{{ $d->status_color }}"
                                     data-submitted="{{ $d->submitted_done_at ? 1 : 0 }}"
                                     data-submitted-by="{{ $d->submittedDoneBy->name ?? '' }}"
                                     data-plan-start="{{ optional($d->plan_start)->format('d/m/Y') }}"
                                     data-plan-end="{{ optional($d->plan_end)->format('d/m/Y') }}"
                                     data-actual-start="{{ optional($d->actual_start)->format('d/m/Y') }}"
                                     data-actual-end="{{ optional($d->actual_end)->format('d/m/Y') }}">
                                    <span class="sidebar-item-main">
                                        <span class="sidebar-item-name">{{ $d->stage->name ?? '-' }}</span>
                                    </span>
                                    <span class="sidebar-item-meta">
                                        <span class="badge badge-{{ $d->status_color }}">{{ $d->status_label }}</span>
                                        <small class="text-muted sidebar-item-weight">{{ $d->weight }}%</small>
                                    </span>
                                </div>
                                @empty
                                <div class="text-muted" style="padding:8px 14px 8px 40px;font-size:11px;">Belum ada milestone.</div>
                                @endforelse
                            </div>
                        </div>
                        @empty
                        <div class="text-muted text-center py-4" style="font-size:12px;">Belum ada Project di bawah PO ini.</div>
                        @endforelse
                    </div>
                </div>

                {{-- CHAT PANEL --}}
                <div class="col-md-8 col-lg-9 d-flex flex-column monitoring-chat-col">

                    <div class="chat-topbar" id="chatTopbar">
                        <button aria-label="Kembali ke daftar" type="button" id="btnBackSidebar" class="btn btn-sm btn-outline-secondary" style="display:none;flex-shrink:0;margin-right:8px;" title="Kembali ke daftar">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div id="chatHeaderEmpty" class="text-muted" style="font-size:12px;">
                            <i class="fas fa-hand-pointer mr-1"></i> Pilih milestone di sidebar untuk melihat detail & mulai aktivitas.
                        </div>
                        <div id="chatHeaderInfo" class="header-info-row" style="display:none;">
                            <strong id="miName" style="font-size:14px;"></strong>
                            <div class="chat-header-dates">
                                <span>Plan Start: <b id="miPlanStart">-</b></span>
                                <span>Plan Finish: <b id="miPlanEnd">-</b></span>
                                <span>Actual Start: <b id="miActualStart">-</b></span>
                                <span>Actual Finish: <b id="miActualEnd">-</b></span>
                            </div>
                            <button aria-label="Detail tanggal" type="button" id="btnMiInfo" class="btn btn-sm btn-outline-secondary d-md-none"
                                    style="flex-shrink:0;" title="Detail tanggal" data-toggle="modal" data-target="#mdlMilestoneInfo">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                        <div id="bpHeaderInfo" class="header-info-row" style="display:none;">
                            <strong id="bpName" style="font-size:14px;"></strong>
                        </div>
                    </div>
                    <div id="miNote" class="milestone-note" style="display:none;"></div>

                    <div id="chatBox"
                         data-last-id="{{ $lastLogId }}"
                         data-fetch-url="{{ route('project-monitoring.message.fetch', $project->id) }}"
                         data-can-manage="{{ $canManage ? '1' : '0' }}"
                         data-can-set-milestone="{{ $canSetMilestone ? '1' : '0' }}"
                         data-is-head="{{ $isHead ? '1' : '0' }}"
                         data-csrf="{{ csrf_token() }}"
                         data-detail-base-url="{{ url('/project-monitoring/detail') }}"
                         data-mention-users="{{ $mentionUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username ?: \Illuminate\Support\Str::of($u->name)->replace(' ', '')->limit(8, '')])->values()->toJson() }}"
                         class="chat-scroll">
                        @if($logsTruncated ?? false)
                        {{-- Jujur soal apa yang tidak ada di halaman ini.
                             Tanpa penanda, riwayat yang tidak dimuat tampak
                             seperti riwayat yang hilang - dan orang akan
                             melaporkannya sebagai data terhapus. --}}
                        <div class="alert alert-info py-2 px-3 mb-2" style="font-size:12px;">
                            <i class="fas fa-info-circle mr-1"></i>
                            Menampilkan <strong>{{ number_format($logLimit, 0, ',', '.') }}</strong> aktivitas terbaru
                            dari total <strong>{{ number_format($totalLogs, 0, ',', '.') }}</strong>.
                            Aktivitas lebih lama tidak dimuat agar halaman tetap responsif.
                        </div>
                        @endif
                        <div id="chatPlaceholder" class="chat-empty">
                            <i class="fas fa-comments"></i>
                            <div class="chat-empty-title">Belum ada yang dipilih</div>
                            <div class="chat-empty-sub">Pilih milestone atau Budget Plan di sebelah kiri untuk melihat aktivitasnya.</div>
                        </div>
                        <div id="chatEmptyScope" class="chat-empty" style="display:none;">
                            <i class="fas fa-inbox"></i>
                            <div class="chat-empty-title">Belum ada aktivitas di sini</div>
                            <div class="chat-empty-sub" id="chatEmptyScopeSub">Aktivitas yang ditulis untuk milestone ini akan muncul di sini.</div>
                        </div>
                        @foreach($allLogs as $log)
                            @include('project-monitoring._log_item', ['log' => $log])
                        @endforeach

                        @foreach($budgetPlans as $bp)
                        <div class="cr-feed" data-budget-id="{{ $bp->id }}" style="display:none;">
                            @forelse(($costRequestFeeds[$bp->id] ?? collect()) as $ev)
                                @if($ev['type'] === 'cr_event')
                                <div class="text-center my-2">
                                    <span class="badge badge-light text-muted" style="font-size:11px;font-weight:400;">
                                        <a href="#" class="btn-detail-cr font-weight-600" data-id="{{ $ev['cr_id'] }}" style="color:var(--info);">{{ $ev['cr_code'] }}</a>
                                        {{ $ev['message'] }} · {{ $ev['date'] }}
                                    </span>
                                </div>
                                @else
                                    @include('project-monitoring._log_item', ['log' => $ev['log']])
                                @endif
                            @empty
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-file-invoice-dollar d-block mb-2" style="font-size:32px;"></i>
                                Belum ada aktivitas Cost Request untuk Budget Plan ini.
                            </div>
                            @endforelse
                        </div>
                        @endforeach
                    </div>

                    @if($canManage)
                    <div id="chatComposer" style="border-top:1px solid var(--border);background:var(--surface);padding:12px;display:none;position:relative;">
                        <form action="{{ route('project-monitoring.message.store', $project->id) }}" method="POST" enctype="multipart/form-data" id="frmChatSend">
                            @csrf
                            <input type="hidden" name="cluster_id" id="inpActiveClusterId" value="">
                            <input type="hidden" name="project_detail_id" id="inpActiveDetailId" value="">
                            <input type="hidden" name="budget_plan_id" id="inpActiveBudgetId" value="">
                            <div id="attachPreview" class="mb-2" style="display:none;flex-wrap:wrap;gap:8px;"></div>
                            <div id="chatComposerInner" class="d-flex align-items-end" style="gap:8px;position:relative;">
                                <input type="file" name="attachments[]" id="inpAttachments" multiple style="display:none;">

                                <div class="dropdown dropup">
                                    <button aria-label="Menu" type="button" class="btn btn-outline-secondary btn-sm mb-0" id="btnMilestoneMenu" data-toggle="dropdown" data-boundary="viewport" title="Menu">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-left" id="milestoneActionsMenu"></div>
                                </div>

                                <div style="position:relative;flex:1;">
                                    <textarea name="message" id="inpMessage" class="form-control form-control-sm" rows="1" placeholder="Tulis pesan/aktivitas... (ketik @ untuk mention)" style="resize:none;overflow-y:hidden;"></textarea>
                                    <div id="mentionDropdown" class="mention-dropdown" style="display:none;"></div>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm" id="btnSend"><i class="fas fa-paper-plane"></i></button>
                            </div>
                            <div id="composerLockedNote" class="composer-locked-note" style="display:none;">
                                <i class="fas fa-lock mr-1"></i> Budget Plan ini tidak aktif — tidak bisa menulis aktivitas baru.
                            </div>
                            <div id="dropOverlay" class="text-center" style="display:none;position:absolute;inset:0;background:rgba(200,39,46,.12);border:2px dashed var(--accent);border-radius:var(--radius-md);align-items:center;justify-content:center;font-size:13px;font-weight:600;color:var(--warning);z-index:10;">
                                Lepaskan file di sini untuk melampirkan
                            </div>
                        </form>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

@if($canManage)
{{-- MODAL: ATUR BOBOT MILESTONE — submit langsung, tanpa approval --}}
<div class="modal fade" id="mdlEditWeights" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <form method="POST" action="{{ route('project-monitoring.weights.update', $project->id) }}">
                @csrf
                <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                    <h5 class="modal-title text-white"><i class="fas fa-balance-scale mr-2" style="color:var(--accent);"></i> Atur Bobot Milestone</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding:22px 26px;max-height:60vh;overflow-y:auto;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Total bobot idealnya = 100.</small>
                        <small class="font-weight-600">Total saat ini: <span id="weightTotalLabel">0</span></small>
                    </div>
                    @forelse($clusters as $cluster)
                        @if(($detailsByCluster[$cluster->id] ?? collect())->isNotEmpty())
                        <div class="font-weight-600 text-muted mb-1 mt-2" style="font-size:11px;text-transform:uppercase;">{{ $cluster->name }}</div>
                        @foreach(($detailsByCluster[$cluster->id] ?? collect()) as $d)
                        <div class="d-flex align-items-center justify-content-between mb-2" style="gap:10px;">
                            <span style="font-size:12.5px;">{{ $d->stage->name ?? '-' }}</span>
                            <input type="number" name="weights[{{ $d->id }}]" class="form-control form-control-sm inp-weight"
                                   value="{{ $d->weight }}" min="0" max="100" style="width:80px;" required>
                        </div>
                        @endforeach
                        @endif
                    @empty
                    <div class="text-muted text-center py-3">Belum ada milestone.</div>
                    @endforelse
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSimpanWeights"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL: DETAIL TANGGAL MILESTONE (mobile only, dipicu tombol info) --}}
<div class="modal fade" id="mdlMilestoneInfo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);">
            <div class="modal-header" style="padding:14px 18px;">
                <h6 class="modal-title font-weight-bold mb-0">Detail Tanggal Milestone</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:16px 18px;font-size:13px;">
                <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Plan Start</span><strong id="miModalPlanStart">-</strong></div>
                <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Plan Finish</span><strong id="miModalPlanEnd">-</strong></div>
                <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Actual Start</span><strong id="miModalActualStart">-</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Actual Finish</span><strong id="miModalActualEnd">-</strong></div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: START MILESTONE (isi Actual Start) --}}
<div class="modal fade" id="mdlStartMilestone" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);">
            <form id="frmStartMilestone">
                <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                    <h5 class="modal-title text-white"><i class="fas fa-play mr-2" style="color:var(--accent);"></i> Start Milestone</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding:20px 22px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Actual Start <span class="text-danger">*</span></label>
                        <input type="date" id="inpStartMilestoneDate" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-play mr-1"></i> Mulai</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: SET FINISH (isi Actual Finish, sekali saja) --}}
<div class="modal fade" id="mdlSetFinish" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);">
            <form id="frmSetFinish">
                <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                    <h5 class="modal-title text-white"><i class="fas fa-flag-checkered mr-2" style="color:var(--accent);"></i> Set Finish</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding:20px 22px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Actual Finish <span class="text-danger">*</span></label>
                        <input type="date" id="inpSetFinishDate" class="form-control" required>
                        <small class="text-muted d-block mt-1">Hanya bisa diisi sekali, tidak bisa diubah lagi setelah disimpan.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-flag-checkered mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('budget-request._detail_modal')

<!-- IMAGE VIEWER (carousel + zoom) -->
<div id="imgViewerOverlay">
    <button aria-label="Tutup" type="button" id="imgViewerClose" title="Tutup"><i class="fas fa-times"></i></button>
    <div id="imgViewerName"></div>
    <div id="imgViewerStage">
        <button aria-label="Sebelumnya" type="button" class="imgViewerNav" id="imgViewerPrev" title="Sebelumnya"><i class="fas fa-chevron-left"></i></button>
        <img id="imgViewerImg" src="" alt="">
        <button aria-label="Berikutnya" type="button" class="imgViewerNav" id="imgViewerNext" title="Berikutnya"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div id="imgViewerCounter"></div>
</div>

@endsection

@push('styles')
<style>
/* Tingginya dulu calc(100vh - 170px): angka 170 itu tebakan tinggi
   navbar+breadcrumb, jadi di resolusi/zoom mana pun yang tidak kebetulan pas,
   kartunya melewati layar dan memunculkan scrollbar halaman. Sekarang --pm-h
   diisi hasil pengukuran posisi kartu yang sebenarnya (lihat setTinggiMonitoring
   di bawah); nilai calc() di sini tinggal cadangan kalau JS belum sempat jalan. */
.monitoring-row      { height:var(--pm-h, calc(100vh - 170px)); min-height:320px; }
/* Sidebar = daftar (putih, seperti tabel), kanvas log = bidang yang mundur
   selangkah supaya bubble putih menonjol di atasnya. Sebelumnya kanvas log
   memakai --info-bg: warna kebiruan yang di tempat lain berarti "sedang
   dipilih/disorot", dipakai untuk area paling luas di halaman - itu yang
   membuatnya terbaca asing di sebelah sidebar yang hangat. */
.monitoring-sidebar   { border-right:1px solid var(--border); background:var(--surface); height:100%; padding:0; overflow-x:hidden; }
.monitoring-chat-col  { background:var(--surface-2); height:100%; padding:0; overflow-x:hidden; min-width:0; }

@media (max-width: 767.98px) {
    .monitoring-row      { height:auto !important; }
    .monitoring-sidebar  { height:75vh !important; border-right:none; }
    .monitoring-chat-col { display:none !important; height:75vh !important; }
    .monitoring-row.mobile-chat-active .monitoring-sidebar  { display:none !important; }
    .monitoring-row.mobile-chat-active .monitoring-chat-col { display:flex !important; }
    #btnBackSidebar { display:inline-flex !important; }
    .chat-header-dates { display:none !important; }
    /* Tanpa min-width:0 nama milestone yang panjang mendorong tombol info
       keluar dari topbar alih-alih dipendekkan dengan elipsis. */
    .header-info-row   { flex-wrap:nowrap; gap:8px; }
    .header-info-row > strong { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .chat-scroll       { padding:12px; }
}

@media (min-width: 768px) {
    /* Hanya .sidebar-scroll & .chat-scroll yang boleh menggulung. */
    body.pm-fit-page { overflow-y:hidden; }
    /* Padding bawah pembungkus halaman (60px) itu nafas untuk halaman yang
       memang menggulung. Di halaman yang justru harus pas selayar, ruang itu
       malah jadi pita kosong di bawah kartu - jadi dikembalikan ke kartunya. */
    /* tesys-ui-shell.css memasangnya dengan !important pada body.tesys-ui,
       jadi harus ditimpa dengan selector yang lebih spesifik - bukan sekadar
       .content biasa yang akan kalah diam-diam. */
    body.tesys-ui.pm-fit-page .main > .content { padding-bottom:16px !important; }
}

.sidebar-topbar       { padding:10px; border-bottom:1px solid var(--border); background:var(--surface); flex-shrink:0; }
.sidebar-scroll       { flex:1; overflow-y:auto; }

.chat-topbar          { min-height:52px; display:flex; align-items:center; padding:8px 16px; border-bottom:1px solid var(--border); background:var(--surface); flex-shrink:0; }
.chat-header-dates    { display:flex; gap:0; font-size:11.5px; color:var(--text-dim); flex-wrap:wrap; align-items:center; }
/* Empat tanggal yang berdempetan tanpa pemisah sulit dipindai; garis tipis
   memberi batas antar pasangan tanpa menambah berat visual. */
.chat-header-dates > span { padding:0 12px; border-left:1px solid var(--border); line-height:1.2; }
.chat-header-dates > span:first-child { border-left:0; padding-left:0; }
.chat-header-dates b  { color:var(--text); font-variant-numeric:tabular-nums; }
.chat-scroll          { flex:1; overflow-y:auto; overflow-x:hidden; padding:18px; min-width:0; }
/* Sebelumnya, memilih milestone yang belum punya log menyembunyikan
   placeholder dan tidak menggantinya dengan apa pun - yang tersisa satu
   bidang kosong sebesar layar tanpa keterangan apa-apa. */
.chat-empty           { text-align:center; color:var(--text-faint); padding:clamp(48px,14vh,140px) 16px; }
.chat-empty i         { font-size:30px; display:block; margin-bottom:10px; opacity:.5; }
.chat-empty-title     { font-size:13px; font-weight:600; color:var(--text-dim); }
.chat-empty-sub       { font-size:11.5px; margin-top:4px; }
.composer-locked-note { font-size:11px; color:var(--text-dim); background:var(--warning-bg); border:1px solid var(--border); border-radius:var(--radius-sm); padding:6px 10px; margin-top:8px; }
.milestone-note       { font-size:11px; color:var(--text-faint); background:var(--warning-bg); border-bottom:1px solid var(--border-strong); padding:6px 16px; flex-shrink:0; }

.progress-track       { height:8px; background:var(--surface-3); border-radius:var(--radius-pill); overflow:hidden; }
.progress-fill         { height:100%; border-radius:var(--radius-pill); background:var(--accent); transition:width .3s; }

/* Tanpa Bootstrap .d-flex (punya !important) supaya toggle display:none via JS benar-benar menang. */
.header-info-row      { display:flex; width:100%; align-items:center; justify-content:space-between; flex-wrap:wrap; }

/* "Cost Request" ditulis di Blade, nama cluster datang dari data - tanpa
   text-transform keduanya tampil beda besar-kecil hurufnya di daftar sama. */
.cluster-toggle        { padding:10px 14px; background:var(--surface-2); font-weight:700; font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--text-faint); cursor:pointer; user-select:none; border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
.cluster-block:first-child .cluster-toggle { border-top:0; }
.cluster-toggle:hover   { background:var(--surface-3); color:var(--text-dim); }
.caret-icon             { transition:transform .15s; display:inline-block; }
.cluster-toggle.collapsed .caret-icon { transform:rotate(-90deg); }

/* Nama melebar, status & bobot dikunci di gugus kanan dengan lebar tetap -
   sebelumnya badge ikut mengekor panjang nama, jadi tidak ada satu pun kolom
   yang lurus saat mata menyusuri daftar. */
.sidebar-item          { padding:9px 14px 9px 28px; cursor:pointer; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:12.5px; border-left:3px solid transparent; }
.sidebar-item:last-child { border-bottom:0; }
.sidebar-item-name     { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sidebar-item-main     { display:flex; align-items:center; gap:6px; flex:1 1 auto; min-width:0; }
.sidebar-item-meta     { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-shrink:0; }
.sidebar-item-meta .badge { font-size:8.5px; }
.sidebar-item-weight   { flex-shrink:0; min-width:34px; text-align:right; font-variant-numeric:tabular-nums; }
.sidebar-item:hover     { background:var(--surface-2); }
/* Aksen kiri dulu memakai --accent (merah) - di seluruh TESYS merah berarti
   bahaya/gagal, bukan "baris ini sedang dibuka". */
.sidebar-item.active-item { background:var(--info-bg) !important; border-left-color:var(--info); }
.sidebar-item.active-item .sidebar-item-name { font-weight:600; color:var(--text); }

/* .badge bawaan Bootstrap ber-white-space:nowrap. Pesan sistem ("X mengubah
   status milestone ... menjadi ON GOING") dipakai sebagai badge, jadi di layar
   sempit kalimatnya tidak dipotong melainkan MEMAKSA lebar - itu yang membuat
   teksnya terpancung dan seluruh halaman bisa digeser ke samping. */
.chat-scroll .badge   { white-space:normal; display:inline-block; max-width:100%; text-align:center; line-height:1.45; overflow-wrap:anywhere; }
.chat-scroll          { max-width:100%; }

.chat-bubble          { max-width:65%; border-radius:var(--radius-lg); padding:8px 12px; font-size:13px; box-shadow:0 1px 1px rgba(0,0,0,.06); }
.chat-bubble-has-media { max-width:340px; }
@media (max-width: 767.98px) {
    .chat-bubble           { max-width:82%; }
    .chat-bubble-has-media { max-width:260px; }
}
.chat-bubble-me        { background:var(--success-bg); }
.chat-bubble-other     { background:var(--surface); }
.chat-bubble-author    { font-size:11px; font-weight:700; color:var(--info); margin-bottom:2px; }
.chat-bubble-text      { white-space:pre-line; word-break:break-word; }
.chat-bubble-text .mention { color:var(--info); }
.chat-bubble-time      { font-size:10px; color:var(--text-faint); text-align:right; margin-top:4px; }
.chat-bubble-attachments { display:grid; grid-template-columns:repeat(auto-fill, minmax(90px, 1fr)); gap:5px; margin:6px 0; }
.chat-att-img           { width:100%; height:90px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid var(--border); display:block; cursor:pointer; }
.chat-att-video         { width:100%; max-height:220px; border-radius:var(--radius-sm); grid-column:1 / -1; }
.chat-att-file          { display:flex; align-items:center; gap:4px; font-size:11.5px; grid-column:1 / -1; padding:6px 8px; background:var(--surface-2); border-radius:var(--radius-sm); border:1px solid var(--border); word-break:break-all; }

/* ── IMAGE VIEWER (carousel + zoom, ala WhatsApp) ── */
#imgViewerOverlay {
    position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:2000;
    display:none; align-items:center; justify-content:center; flex-direction:column;
}
#imgViewerOverlay.show { display:flex; }
#imgViewerStage { position:relative; width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
#imgViewerImg {
    max-width:90vw; max-height:82vh; object-fit:contain; cursor:zoom-in;
    transition:transform .15s ease; transform-origin:center center; user-select:none; -webkit-user-drag:none;
}
#imgViewerImg.zoomed { cursor:grab; }
#imgViewerImg.dragging { cursor:grabbing; transition:none; }
#imgViewerClose {
    position:absolute; top:16px; right:20px; z-index:10; background:rgba(255,255,255,.12); border:none; color:#fff;
    width:38px; height:38px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
#imgViewerClose:hover { background:rgba(255,255,255,.25); }
.imgViewerNav {
    position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.12); border:none; color:#fff;
    width:44px; height:44px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.imgViewerNav:hover { background:rgba(255,255,255,.25); }
#imgViewerPrev { left:16px; }
#imgViewerNext { right:16px; }
#imgViewerCounter { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:#fff; font-size:12px; background:rgba(255,255,255,.12); padding:4px 12px; border-radius:var(--radius-lg); }
#imgViewerName { position:absolute; top:20px; left:20px; z-index:10; color:#fff; font-size:12px; opacity:.85; max-width:60vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
@media (max-width:576px) {
    .imgViewerNav { width:36px; height:36px; font-size:16px; }
    #imgViewerPrev { left:6px; } #imgViewerNext { right:6px; }
}

.mention-dropdown { position:absolute; bottom:100%; left:0; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); box-shadow:0 2px 8px rgba(0,0,0,.15); max-height:200px; overflow-y:auto; width:220px; margin-bottom:6px; z-index:20; }
.mention-item { padding:8px 12px; font-size:12.5px; cursor:pointer; }
.mention-item:hover { background:var(--surface-2); }

#inpMessage { min-height:31px; }

#milestoneActionsMenu       { min-width:190px; max-width:220px; padding:4px; border-radius:var(--radius-md); box-shadow:0 6px 20px rgba(0,0,0,.14); border:1px solid var(--border); }
#milestoneActionsMenu form  { margin:0; }
#milestoneActionsMenu .dropdown-divider { margin:4px 0; }
#milestoneActionsMenu button.dropdown-item {
    border:none; background:none; width:100%; text-align:left;
    font-size:12.5px; padding:7px 10px; border-radius:var(--radius-sm); color:var(--text);
    display:flex; align-items:center; gap:6px; transition:background .1s;
}
#milestoneActionsMenu button.dropdown-item:hover,
#milestoneActionsMenu button.dropdown-item:focus {
    background:var(--info-bg); color:var(--text);
}

.attach-chip     { position:relative; width:56px; height:56px; border-radius:var(--radius-md); overflow:visible; flex-shrink:0; }
.attach-thumb    { width:100%; height:100%; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--border); display:block; }
.attach-icon     { width:100%; height:100%; border-radius:var(--radius-md); border:1px solid var(--border); background:var(--info-bg); display:flex; align-items:center; justify-content:center; color:var(--text-faint); font-size:18px; }
.attach-remove   {
    position:absolute; top:-6px; right:-6px; width:18px; height:18px; line-height:16px; padding:0;
    border-radius:50%; border:1px solid var(--border); background:var(--surface); color:var(--danger); font-size:13px;
    cursor:pointer; text-align:center;
}
.attach-remove:hover { background:var(--danger); color:#fff; border-color:var(--danger); }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var chatBox = document.getElementById('chatBox');
    var fetchUrl = chatBox.dataset.fetchUrl;
    var canManage = chatBox.dataset.canManage === '1';
    var canSetMilestone = chatBox.dataset.canSetMilestone === '1';
    var isHead = chatBox.dataset.isHead === '1';
    var mentionUsers = JSON.parse(chatBox.dataset.mentionUsers || '[]');
    var csrfToken = chatBox.dataset.csrf;
    var detailBaseUrl = chatBox.dataset.detailBaseUrl;
    var activeDetailId = null;
    var activeBudgetId = null;
    var lastActiveStorageKey = 'pm-last-active-' + window.location.pathname;
    var lastActiveBudgetKey = 'pm-last-active-budget-' + window.location.pathname;

    function scrollToBottom() { chatBox.scrollTop = chatBox.scrollHeight; }

    /* Belum ada milestone terpilih di awal -> sembunyikan semua bubble */
    $('.chat-bubble-wrap').hide();

    /* ── COLLAPSE / EXPAND PROJECT (CLUSTER) — murni tombol dropdown ── */
    $('.cluster-toggle').on('click', function () {
        $(this).toggleClass('collapsed');
        $('#' + $(this).data('target')).slideToggle(120);
    });

    /* ── Toggle 3 mode header secara eksklusif: empty / milestone / budget ──
    | Pakai .css('display', ...) eksplisit (bukan .show()/.hide()/.toggle())
    | supaya tidak bergantung pada nilai display bawaan yang dipulihkan jQuery. */
    function setHeaderMode(mode) {
        $('#chatHeaderEmpty').css('display', mode === 'empty' ? 'block' : 'none');
        $('#chatHeaderInfo').css('display', mode === 'milestone' ? 'flex' : 'none');
        $('#bpHeaderInfo').css('display', mode === 'budget' ? 'flex' : 'none');
        if (mode !== 'milestone') $('#miNote').css('display', 'none');
    }

    /* ── MOBILE: toggle sidebar <-> chat (mirip WhatsApp) ── */
    $('#btnBackSidebar').on('click', function () {
        $('.monitoring-row').removeClass('mobile-chat-active');
    });

    /* ── PILIH MILESTONE / COST REQUEST ── */
    /* Milestone yang belum punya log dulunya menyisakan kanvas kosong tanpa
       keterangan: #chatPlaceholder disembunyikan saat memilih, dan tidak ada
       yang menggantikannya. Feed Cost Request sudah punya pesan kosongnya
       sendiri di Blade, jadi yang ditangani di sini hanya scope milestone. */
    function perbaruiEmptyScope() {
        var tampil = activeDetailId
                  && $('#chatBox').children('.chat-bubble-wrap:visible').length === 0;
        $('#chatEmptyScope').toggle(!!tampil);
    }

    /*
     | Fit-page (desktop): tinggi kartu = sisa ruang dari posisi atas kartu
     | sampai bawah layar. Diukur ulang saat ukuran jendela/sidebar berubah,
     | karena tinggi navbar & breadcrumb ikut berubah saat teks judul membungkus.
     |
     | Di mobile media query sudah memaksa height:auto !important, jadi variabel
     | ini sengaja dikosongkan supaya tidak ada yang bertabrakan.
     */
    /* 0, bukan 16: nafas di bawah kartu sudah disediakan padding-bottom
       pembungkus halaman (.main > .content, disetel 16px di blok CSS di atas).
       Menambah 16 lagi di sini berarti menghitung jarak yang sama dua kali -
       persis sebesar itu pula halaman jadi lebih tinggi dari layar. */
    var PM_JARAK_BAWAH = 16;   // nafas antara bawah kartu dan tepi layar

    function setTinggiMonitoring() {
        var baris = document.querySelector('.monitoring-row');
        var kartu = baris && baris.closest('.card');
        if (!baris || !kartu) return;

        var mobile = window.matchMedia('(max-width: 767.98px)').matches;
        document.body.classList.toggle('pm-fit-page', !mobile);
        document.documentElement.style.overflowY = mobile ? '' : 'hidden';

        if (mobile) {
            baris.style.removeProperty('--pm-h');
            return;
        }

        /*
           Patokannya bukan "tinggi layar dikurangi tebakan tinggi header"
           (itu yang dulu dipakai lewat calc(100vh - 170px) dan meleset di
           sebagian resolusi), melainkan selisih antara bawah KARTU dengan tepi
           layar: berapa pun tinggi header, padding pembungkus, atau chrome
           kartunya, selisih itu sudah menghitung semuanya sekaligus.

           Diulang beberapa kali karena satu penyesuaian bisa mengubah
           pembungkusan teks di header dan menggeser lagi patokannya. Berhenti
           begitu selisihnya di bawah 1px.
        */
        /* Sengaja TIDAK memakai requestAnimationFrame: rAF dijeda saat tab
           tidak terlihat, jadi halaman yang dibuka di tab latar akan tetap
           memakai tinggi cadangan. getBoundingClientRect() sendiri sudah
           memaksa layout dihitung ulang, jadi penyesuaian bertahap ini aman
           dilakukan berurutan. */
        for (var putaran = 0; putaran < 4; putaran++) {
            var tinggiSekarang = baris.getBoundingClientRect().height;
            var selisih = window.innerHeight - kartu.getBoundingClientRect().bottom - PM_JARAK_BAWAH;

            if (Math.abs(selisih) < 1) break;

            baris.style.setProperty('--pm-h', Math.max(320, tinggiSekarang + selisih) + 'px');
        }

        // Sesuatu (auto-scroll ke pesan terbaru) bisa menggeser halaman
        // sebelum tingginya pas; kembalikan ke atas.
        if (window.pageYOffset !== 0) window.scrollTo(0, 0);
    }

    setTinggiMonitoring();
    $(window).on('resize', setTinggiMonitoring);
    // Sidebar aplikasi bisa dilipat/dibuka - lebar berubah, judul membungkus,
    // tinggi header ikut berubah. Transisinya ~300ms.
    $(document).on('click', '[data-widget="pushmenu"], .ts-sidebar-toggle', function () {
        window.setTimeout(setTinggiMonitoring, 350);
    });

    $('.sidebar-item').on('click', function () {
        $('.monitoring-row').addClass('mobile-chat-active');
        $('.sidebar-item').removeClass('active-item');
        $(this).addClass('active-item');

        var d = $(this).data();

        if (d.scope === 'budget') {
            selectBudgetPlan(d);
            return;
        }

        activeDetailId = null;
        activeBudgetId = null;
        localStorage.setItem(lastActiveStorageKey, d.detailId);
        localStorage.removeItem(lastActiveBudgetKey);
        activeDetailId = d.detailId;

        $('.cr-feed').hide();
        $('#chatPlaceholder').hide();
        $('.chat-bubble-wrap').each(function () {
            $(this).toggle(String($(this).data('scope-stage')) === String(d.detailId));
        });

        setHeaderMode('milestone');
        renderHeader(d);
        renderActionsMenu('milestone', d);
        renderComposerState(d);

        $('#inpActiveDetailId').val(d.detailId);
        $('#inpActiveBudgetId').val('');
        perbaruiEmptyScope();
        scrollToBottom();
    });

    function selectBudgetPlan(d) {
        activeDetailId = null;
        activeBudgetId = d.budgetId;
        localStorage.setItem(lastActiveBudgetKey, d.budgetId);
        localStorage.removeItem(lastActiveStorageKey);

        $('.chat-bubble-wrap').hide();
        $('#chatPlaceholder').hide();
        $('.cr-feed').hide();
        var feed = $('.cr-feed[data-budget-id="' + d.budgetId + '"]').show();
        feed.find('.chat-bubble-wrap').each(function () {
            $(this).toggle(String($(this).data('scope-budget')) === String(d.budgetId));
        });

        setHeaderMode('budget');
        $('#bpName').text(d.name);

        renderActionsMenu('budget', d);
        renderBudgetComposerState(d);

        $('#inpActiveBudgetId').val(d.budgetId);
        $('#inpActiveDetailId').val('');
        perbaruiEmptyScope();
        scrollToBottom();
    }

    function renderBudgetComposerState(d) {
        if (!canManage) return;

        var locked = d.bpStatus !== 'ACTIVE';

        $('#chatComposer').show();
        $('#composerLockedNote').text('Budget Plan ini tidak aktif — tidak bisa menulis aktivitas baru.').toggle(locked);

        $('#inpMessage, #btnSend, #inpAttachments').prop('disabled', locked);
        $('#inpMessage').attr('placeholder', locked ? 'Budget Plan tidak aktif...' : 'Tulis pesan/aktivitas... (ketik @ untuk mention)');
        setTimeout(autoResizeMessage, 0);
    }

    function renderHeader(d) {
        $('#miName').text(d.name);
        $('#miPlanStart, #miModalPlanStart').text(d.planStart || '-');
        $('#miPlanEnd, #miModalPlanEnd').text(d.planEnd || '-');
        $('#miActualStart, #miModalActualStart').text(d.actualStart || '-');
        $('#miActualEnd, #miModalActualEnd').text(d.actualEnd || '-');
        $('#miNote').hide();
    }

    /* ── Menu (⋮): Lampirkan File + (khusus milestone) aksi status ──
    | Aturan:
    |   NOT_STARTED -> hanya "Start Milestone" (isi Actual Start).
    |   ON_GOING    -> hanya "Set Finish" (isi Actual Finish, otomatis jadi DONE).
    |   DONE        -> tidak ada aksi status lagi, cuma lampiran chat. */
    function renderActionsMenu(scope, d) {
        var menu = $('#milestoneActionsMenu').empty();

        menu.append(
            $('<button type="button" class="dropdown-item"><i class="fas fa-paperclip mr-2"></i>Lampirkan File</button>')
                .on('click', function () { $('#inpAttachments').trigger('click'); })
        );

        if (scope === 'milestone' && canManage && canSetMilestone) {
            if (d.status === 'NOT_STARTED') {
                menu.append('<div class="dropdown-divider"></div>');
                menu.append(
                    $('<button type="button" class="dropdown-item"><i class="fas fa-play mr-2"></i>Start Milestone</button>')
                        .on('click', function () { openStartMilestoneModal(d); })
                );
            } else if (d.status === 'ON_GOING') {
                menu.append('<div class="dropdown-divider"></div>');
                menu.append(
                    $('<button type="button" class="dropdown-item"><i class="fas fa-flag-checkered mr-2"></i>Set Finish</button>')
                        .on('click', function () { openSetFinishModal(d); })
                );
            }
            // DONE: tidak ada aksi status tambahan.
        }

        $('#btnMilestoneMenu').show();
    }

    function submitMilestoneStatus(detailId, status, actualStart) {
        var form = $('<form method="POST"></form>').attr('action', detailBaseUrl + '/' + detailId + '/set-status');
        form.append($('<input type="hidden" name="_token">').val(csrfToken));
        form.append($('<input type="hidden" name="status">').val(status));
        if (actualStart) form.append($('<input type="hidden" name="actual_start">').val(actualStart));
        $('body').append(form);
        form.submit();
    }

    function openStartMilestoneModal(d) {
        var today = new Date().toISOString().slice(0, 10);
        $('#inpStartMilestoneDate').val(today);
        $('#frmStartMilestone').data('detail-id', d.detailId);
        $('#mdlStartMilestone').modal('show');
    }

    $('#frmStartMilestone').on('submit', function (e) {
        e.preventDefault();
        var detailId = $(this).data('detail-id');
        submitMilestoneStatus(detailId, 'ON_GOING', $('#inpStartMilestoneDate').val());
    });

    function openSetFinishModal(d) {
        var today = new Date().toISOString().slice(0, 10);
        $('#inpSetFinishDate').val(today);
        $('#frmSetFinish').data('detail-id', d.detailId);
        $('#mdlSetFinish').modal('show');
    }

    $('#frmSetFinish').on('submit', function (e) {
        e.preventDefault();
        var detailId = $(this).data('detail-id');
        var form = $('<form method="POST"></form>').attr('action', detailBaseUrl + '/' + detailId + '/set-finish');
        form.append($('<input type="hidden" name="_token">').val(csrfToken));
        form.append($('<input type="hidden" name="actual_end">').val($('#inpSetFinishDate').val()));
        $('body').append(form);
        form.submit();
    });

    function renderComposerState(d) {
        if (!canManage) return;

        var locked = d.status === 'NOT_STARTED';

        $('#chatComposer').show();
        $('#composerLockedNote').text('Milestone belum dimulai — gunakan menu (⋮) untuk Start Milestone.').toggle(locked);
        $('#inpMessage, #btnSend, #inpAttachments').prop('disabled', locked);
        $('#inpMessage').attr('placeholder', locked ? 'Mulai milestone dulu lewat menu (⋮)...' : 'Tulis pesan/aktivitas... (ketik @ untuk mention)');
        setTimeout(autoResizeMessage, 0);
    }

    /* ── Pencarian keyword — di SELURUH log (semua milestone & Cost Request),
    | bukan cuma scope yang sedang aktif. Saat ada keyword, semua feed
    | (chatBox + cr-feed) ditampilkan berdampingan dan cuma bubble yang cocok
    | yang terlihat. Saat keyword dikosongkan, kembali ke tampilan scope aktif. */
    function restoreScopeView() {
        $('#chatSearchInfo').remove();
        if (activeBudgetId) {
            $('.cr-feed').hide();
            var feed = $('.cr-feed[data-budget-id="' + activeBudgetId + '"]').show();
            $('.chat-bubble-wrap').each(function () {
                var inActiveFeed = feed.find(this).length > 0;
                $(this).toggle(inActiveFeed && String($(this).data('scope-budget')) === String(activeBudgetId));
            });
        } else if (activeDetailId) {
            $('.cr-feed').hide();
            $('.chat-bubble-wrap').each(function () {
                $(this).toggle(String($(this).data('scope-stage')) === String(activeDetailId));
            });
        } else {
            $('.cr-feed').hide();
            $('.chat-bubble-wrap').hide();
        }
        perbaruiEmptyScope();
    }

    $('#chatSearch').on('keyup', function () {
        var kw = $(this).val().toLowerCase().trim();

        if (!kw) {
            restoreScopeView();
            return;
        }

        // Global: tampilkan semua feed (supaya bubble Cost Request ikut terlihat) lalu filter per teks.
        $('#chatPlaceholder').hide();
        $('#chatEmptyScope').hide();
        $('.cr-feed').show();
        var matchCount = 0;
        $('.chat-bubble-wrap').each(function () {
            var text = $(this).data('search-text') || '';
            var match = String(text).indexOf(kw) !== -1;
            $(this).toggle(match);
            if (match) matchCount++;
        });

        $('#chatSearchInfo').remove();
        $(chatBox).prepend(
            '<div id="chatSearchInfo" class="text-center text-muted py-2" style="font-size:11px;">' +
            '<i class="fas fa-search mr-1"></i>' + matchCount + ' hasil ditemukan di seluruh log untuk "' + $('<div>').text(kw).html() + '"' +
            '</div>'
        );
    });

    /* ── Preview lampiran: thumbnail/icon + tombol × per file ── */
    function renderAttachPreview() {
        var input = document.getElementById('inpAttachments');
        var $prev = $('#attachPreview').empty();

        if (!input.files.length) { $prev.css('display', 'none'); return; }

        Array.prototype.forEach.call(input.files, function (file, idx) {
            var $chip = $('<div class="attach-chip"></div>').attr('title', file.name);

            if (file.type.indexOf('image/') === 0) {
                $chip.append($('<img class="attach-thumb">').attr('src', URL.createObjectURL(file)));
            } else {
                var icon = file.type.indexOf('video/') === 0 ? 'fa-file-video'
                    : file.type === 'application/pdf' ? 'fa-file-pdf'
                    : /word|msword/.test(file.type) ? 'fa-file-word'
                    : /excel|spreadsheet/.test(file.type) ? 'fa-file-excel'
                    : /zip|compressed/.test(file.type) ? 'fa-file-archive'
                    : 'fa-file';
                $chip.append($('<div class="attach-icon"></div>').append($('<i class="fas ' + icon + '"></i>')));
            }

            $chip.append(
                $('<button type="button" class="attach-remove" title="Hapus lampiran">&times;</button>')
                    .on('click', function () { removeAttachAt(idx); })
            );

            $prev.append($chip);
        });

        $prev.css('display', 'flex');
    }

    function removeAttachAt(idx) {
        var input = document.getElementById('inpAttachments');
        var dt = new DataTransfer();
        Array.prototype.forEach.call(input.files, function (f, i) { if (i !== idx) dt.items.add(f); });
        input.files = dt.files;
        renderAttachPreview();
    }

    $('#inpAttachments').on('change', renderAttachPreview);

    /* ── KIRIM PESAN via AJAX ──────────────────────────────────────────────
     | Sebelumnya form ini full-page POST: kalau upload lampiran lambat/gagal
     | di server, halaman "loading" selamanya tanpa feedback. Sekarang AJAX +
     | spinner + error jelas, dan pesan baru langsung ditarik lewat polling. */
    var chatSending = false;
    $('#frmChatSend').on('submit', function (e) {
        e.preventDefault();
        if (chatSending) return;

        var form  = this;
        var $btn  = $('#btnSend');
        var hasMsg = $('#inpMessage').val().trim().length > 0;
        var hasFile = document.getElementById('inpAttachments').files.length > 0;
        if (!hasMsg && !hasFile) return;

        chatSending = true;
        var btnHtml = $btn.html();
        var btnWasDisabled = $btn.prop('disabled');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: form.action,
            method: 'POST',
            data: new FormData(form),
            processData: false,
            contentType: false,
            headers: { 'Accept': 'application/json' },
            timeout: 120000
        }).done(function () {
            $('#inpMessage').val('').trigger('input');
            var inp = document.getElementById('inpAttachments');
            try { inp.value = ''; } catch (e) {}
            renderAttachPreview();
            $('#mentionDropdown').hide();
            if (typeof pollNewMessages === 'function') pollNewMessages();
            setTimeout(scrollToBottom, 400);
        }).fail(function (xhr) {
            var msg = 'Gagal mengirim. Coba lagi.';
            if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
            else if (xhr.status === 413) msg = 'Ukuran lampiran terlalu besar untuk server.';
            else if (xhr.status === 0 || xhr.statusText === 'timeout') msg = 'Koneksi timeout saat upload lampiran.';
            Swal.fire({ icon: 'error', title: 'Gagal', text: msg, confirmButtonColor: '#92232b' });
        }).always(function () {
            chatSending = false;
            $btn.prop('disabled', btnWasDisabled).html(btnHtml);
        });
    });

    /* ── ATUR BOBOT MILESTONE ── */
    function recalcWeightTotal() {
        var total = 0;
        $('.inp-weight').each(function () { total += Number($(this).val()) || 0; });
        $('#weightTotalLabel').text(total).css('color', total === 100 ? 'var(--success)' : 'var(--danger)');
        $('#btnSimpanWeights').prop('disabled', total !== 100);
    }
    $('#btnEditWeights').on('click', function () {
        $('#mdlEditWeights').modal('show');
        recalcWeightTotal();
    });
    $(document).on('input', '.inp-weight', recalcWeightTotal);

    $('#mdlEditWeights form').on('submit', function (e) {
        var total = 0;
        $('.inp-weight').each(function () { total += Number($(this).val()) || 0; });
        if (total !== 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Total bobot harus 100',
                text: 'Total saat ini ' + total + '. Sesuaikan dulu sebelum menyimpan.',
                confirmButtonColor: '#92232b'
            });
        }
    });

    /* ── AUTO-EXTEND TEXTAREA — maks ~5 baris, lalu scroll ──
    | #chatComposer masih display:none saat halaman pertama dimuat (belum ada
    | milestone terpilih), jadi scrollHeight terukur 0 kalau resize dijalankan
    | saat itu. Makanya autoResizeMessage() harus dipanggil ULANG setiap kali
    | composer ditampilkan (lihat renderComposerState/renderBudgetComposerState). */
    var autoResizeMessage = (function () {
        var ta = document.getElementById('inpMessage');
        if (!ta) return function () {};
        var lineHeight = 18; // px, kira-kira sesuai font-size:13px + line-height form-control-sm
        var maxLines = 5;
        var maxHeight = lineHeight * maxLines + 12; // + padding kira-kira

        function resize() {
            if (ta.offsetParent === null) return; // masih tersembunyi, jangan diukur dulu
            ta.style.height = 'auto';
            var newHeight = Math.min(ta.scrollHeight, maxHeight);
            ta.style.height = newHeight + 'px';
            ta.style.overflowY = ta.scrollHeight > maxHeight ? 'auto' : 'hidden';
        }
        ta.addEventListener('input', resize);
        return resize;
    })();

    /* ── Enter untuk kirim ── */
    $('#frmChatSend').on('keydown', 'textarea[name="message"]', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $('#frmChatSend').submit();
        }
    });

    /* ── MENTION "@" — diketik langsung di textarea, ala WhatsApp ──
    | Ketik "@" lalu sebagian nama -> muncul daftar filter. Pilih salah satu
    | -> disisipkan sebagai "@NamaTanpaSpasi " lalu lanjut ketik pesan. */
    $('#inpMessage').on('input click keyup', function () {
        var ta = this;
        var pos = ta.selectionStart || 0;
        var uptoCursor = ta.value.slice(0, pos);
        var m = uptoCursor.match(/@([A-Za-z0-9_]*)$/);
        var dd = $('#mentionDropdown');

        if (!m) { dd.hide(); return; }

        var query = m[1].toLowerCase();
        var matches = mentionUsers.filter(function (u) {
            return u.username.toLowerCase().indexOf(query) !== -1 || u.name.toLowerCase().indexOf(query) !== -1;
        });

        dd.empty();
        if (matches.length === 0) {
            dd.hide();
            return;
        }
        matches.forEach(function (u) {
            var $item = $('<div class="mention-item"></div>');
            $item.append($('<span class="font-weight-600"></span>').text('@' + u.username));
            $item.append($('<small class="text-muted ml-1"></small>').text(u.name));
            $item.on('mousedown', function (e) {
                e.preventDefault();
                var val = ta.value;
                var curPos = ta.selectionStart || 0;
                var before = val.slice(0, curPos);
                var atIdx = before.lastIndexOf('@');
                var insert = '@' + u.username + ' ';
                ta.value = val.slice(0, atIdx) + insert + val.slice(curPos);
                var newPos = atIdx + insert.length;
                ta.focus();
                ta.setSelectionRange(newPos, newPos);
                dd.hide();
            });
            dd.append($item);
        });
        dd.show();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#inpMessage, #mentionDropdown').length) $('#mentionDropdown').hide();
    });

    /* ── LAMPIRAN: tambah file ke input tanpa mengganti yang sudah dipilih ── */
    function addFilesToAttachInput(fileList) {
        var input = document.getElementById('inpAttachments');
        var dt = new DataTransfer();
        Array.prototype.forEach.call(input.files, function (f) { dt.items.add(f); });
        Array.prototype.forEach.call(fileList, function (f) { dt.items.add(f); });
        input.files = dt.files;
        $(input).trigger('change');
    }

    /* ── DRAG & DROP FILE (ala WhatsApp) ── */
    var composerEl = document.getElementById('chatComposer');
    ['dragenter', 'dragover'].forEach(function (evt) {
        composerEl.addEventListener(evt, function (e) {
            e.preventDefault(); e.stopPropagation();
            $('#dropOverlay').css('display', 'flex');
        });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        composerEl.addEventListener(evt, function (e) {
            e.preventDefault(); e.stopPropagation();
            $('#dropOverlay').css('display', 'none');
        });
    });
    composerEl.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files.length) {
            addFilesToAttachInput(e.dataTransfer.files);
        }
    });

    /* ── IMAGE VIEWER — carousel + zoom (ala WhatsApp) ── */
    (function () {
        var overlay  = document.getElementById('imgViewerOverlay');
        var stageImg = document.getElementById('imgViewerImg');
        var counter  = document.getElementById('imgViewerCounter');
        var images   = [];
        var idx      = 0;
        var zoomed   = false;
        var isDragging = false, dragStartX = 0, dragStartY = 0, imgX = 0, imgY = 0;
        var justSwiped = false;   // menandai klik yang sebenarnya gerakan geser

        function renderViewer() {
            var item = images[idx];
            if (!item) return;
            resetZoom();
            stageImg.src = item.full;
            $('#imgViewerName').text(item.name || '');
            counter.textContent = (idx + 1) + ' / ' + images.length;
            /* Panah hanya ada kalau memang ada tujuannya: tidak tampil untuk
               lampiran tunggal, tidak ada panah kiri di lampiran pertama dan
               tidak ada panah kanan di lampiran terakhir. */
            var banyak = images.length > 1;
            $('#imgViewerPrev').css('display', (banyak && idx > 0) ? 'flex' : 'none');
            $('#imgViewerNext').css('display', (banyak && idx < images.length - 1) ? 'flex' : 'none');
        }

        function resetZoom() {
            zoomed = false;
            imgX = 0; imgY = 0;
            stageImg.classList.remove('zoomed', 'dragging');
            stageImg.style.transform = 'translate(0px, 0px) scale(1)';
            stageImg.style.cursor = 'zoom-in';
        }

        function applyTransform() {
            stageImg.style.transform = 'translate(' + imgX + 'px, ' + imgY + 'px) scale(' + (zoomed ? 2.4 : 1) + ')';
        }

        function openViewer(groupImages, startIndex) {
            images = groupImages;
            idx = startIndex;
            renderViewer();
            overlay.classList.add('show');
            $('body').css('overflow', 'hidden');
        }

        function closeViewer() {
            overlay.classList.remove('show');
            $('body').css('overflow', '');
        }

        function showNext() { if (idx < images.length - 1) { idx++; renderViewer(); } }
        function showPrev() { if (idx > 0) { idx--; renderViewer(); } }

        // Trigger: klik thumbnail gambar pada chat bubble
        $(document).on('click', '.chat-att-img', function () {
            var $group = $(this).closest('.chat-bubble-attachments');
            var groupImages = $group.find('.chat-att-img').map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $group.find('.chat-att-img').index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        $('#imgViewerClose').on('click', closeViewer);
        $('#imgViewerNext').on('click', showNext);
        $('#imgViewerPrev').on('click', showPrev);
        $(overlay).on('click', function (e) {
            // Klik di area kosong menutup viewer — kecuali kalau klik itu
            // ujung dari gerakan geser antar lampiran.
            if (justSwiped) { justSwiped = false; return; }
            if (e.target === overlay) closeViewer();
        });

        $(document).on('keydown', function (e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') closeViewer();
            else if (e.key === 'ArrowRight') showNext();
            else if (e.key === 'ArrowLeft') showPrev();
        });

        // Zoom: klik ganda toggle, scroll wheel toggle
        stageImg.addEventListener('dblclick', function () {
            zoomed = !zoomed;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        });
        stageImg.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomed = e.deltaY < 0 ? true : false;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        }, { passive: false });

        // Pan saat zoomed (drag)
        stageImg.addEventListener('mousedown', function (e) {
            if (!zoomed) return;
            isDragging = true;
            dragStartX = e.clientX - imgX;
            dragStartY = e.clientY - imgY;
            stageImg.classList.add('dragging');
        });
        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            imgX = e.clientX - dragStartX;
            imgY = e.clientY - dragStartY;
            applyTransform();
        });
        document.addEventListener('mouseup', function () {
            isDragging = false;
            stageImg.classList.remove('dragging');
        });

        /* ── GESER KIRI/KANAN untuk pindah lampiran ─────────────────────────
           Saat gambar TIDAK di-zoom, tarikan mendatar (mouse maupun sentuh)
           berpindah ke lampiran sebelum/sesudahnya — pola yang sama dengan
           galeri ponsel. Saat di-zoom, tarikan tetap menggeser gambar
           (pan), jadi keduanya tidak saling rebutan. */
        var SWIPE_MIN = 50;          // px, biar klik/getar kecil tidak terbaca geser
        var swipeX = null, swipeY = null;

        function swipeStart(x, y) { if (!zoomed) { swipeX = x; swipeY = y; } }

        function swipeEnd(x, y) {
            if (swipeX === null || images.length < 2) { swipeX = swipeY = null; return; }
            var dx = x - swipeX, dy = y - swipeY;
            swipeX = swipeY = null;
            if (Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy)) return;
            if (dx < 0 && idx >= images.length - 1) return;   // sudah di lampiran terakhir
            if (dx > 0 && idx <= 0) return;                   // sudah di lampiran pertama
            justSwiped = true;
            if (dx < 0) showNext(); else showPrev();
        }

        stageImg.addEventListener('mousedown', function (e) { swipeStart(e.clientX, e.clientY); });
        document.addEventListener('mouseup', function (e) {
            if (!overlay.classList.contains('show')) { swipeX = swipeY = null; return; }
            swipeEnd(e.clientX, e.clientY);
        });

        stageImg.addEventListener('touchstart', function (e) {
            var t = e.changedTouches[0];
            swipeStart(t.clientX, t.clientY);
        }, { passive: true });
        stageImg.addEventListener('touchend', function (e) {
            var t = e.changedTouches[0];
            swipeEnd(t.clientX, t.clientY);
        }, { passive: true });

        /* Geser juga boleh lewat area kosong di kiri/kanan gambar. */
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === stageImg) return;
            swipeStart(e.clientX, e.clientY);
        });
    })();

    /* ── PASTE FILE DARI CLIPBOARD (ala WhatsApp) ── */
    document.getElementById('inpMessage').addEventListener('paste', function (e) {
        var items = (e.clipboardData || window.clipboardData).items || [];
        var files = [];
        for (var i = 0; i < items.length; i++) {
            if (items[i].kind === 'file') {
                var f = items[i].getAsFile();
                if (f) files.push(f);
            }
        }
        if (files.length) {
            e.preventDefault();
            addFilesToAttachInput(files);
        }
    });

    /* ── REALTIME POLLING — cek log baru tiap 10 detik ── */
    function pollNewMessages() {
        var lastId = $(chatBox).data('last-id') || 0;
        $.get(fetchUrl, { after: lastId }, function (res) {
            if (res.html) {
                var wasAtBottom = (chatBox.scrollTop + chatBox.clientHeight) >= (chatBox.scrollHeight - 30);
                var wrap = $('<div>' + res.html + '</div>');

                wrap.find('.chat-bubble-wrap').each(function () {
                    var bubble = $(this);
                    var budgetId = bubble.data('scope-budget');
                    var stageId = bubble.data('scope-stage');

                    if (budgetId) {
                        var feed = $('.cr-feed[data-budget-id="' + budgetId + '"]');
                        bubble.toggle(String(budgetId) === String(activeBudgetId));
                        feed.append(bubble);
                    } else {
                        bubble.toggle(!!stageId && String(stageId) === String(activeDetailId));
                        $(chatBox).append(bubble);
                    }
                });

                $(chatBox).data('last-id', res.last_id);
                if (wasAtBottom && (activeDetailId || activeBudgetId)) scrollToBottom();
            }
        });
    }
    // Tab tersembunyi tidak polling (lihat catatan tesysPoll di layout);
    // begitu tab dilihat lagi, pesan baru langsung diambil sekali.
    setInterval(function () { if (!document.hidden) pollNewMessages(); }, 10000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollNewMessages();
    });

    /* ── Buka milestone tertentu bila diakses dengan ?detail=<id> (dari modal PO) ── */
    var deepDetailId = new URLSearchParams(window.location.search).get('detail');
    var deepItem = deepDetailId ? $('.sidebar-item[data-scope="detail"][data-detail-id="' + deepDetailId + '"]') : $();

    /* ── Kembali ke scope terakhir (mis. setelah kirim pesan & reload) ── */
    var lastActiveId = localStorage.getItem(lastActiveStorageKey);
    var lastActiveBudgetId = localStorage.getItem(lastActiveBudgetKey);
    if (deepItem.length) {
        // pastikan grup cluster-nya terbuka dulu, lalu pilih milestone-nya
        var $grp = deepItem.closest('[id^="clusterGroup"]');
        if ($grp.length) {
            $grp.stop(true, true).show();
            $('.cluster-toggle[data-target="' + $grp.attr('id') + '"]').removeClass('collapsed');
        }
        setTimeout(function () {
            deepItem.trigger('click');
            try { deepItem[0].scrollIntoView({ block: 'center' }); } catch (e) {}
        }, 60);
    } else if (lastActiveId) {
        var lastItem = $('.sidebar-item[data-detail-id="' + lastActiveId + '"]');
        if (lastItem.length) lastItem.trigger('click');
    } else if (lastActiveBudgetId) {
        var lastBudgetItem = $('.sidebar-item[data-budget-id="' + lastActiveBudgetId + '"]');
        if (lastBudgetItem.length) lastBudgetItem.trigger('click');
    }
});
</script>
@endpush
