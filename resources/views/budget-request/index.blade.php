@extends('layouts.app')

@section('title', 'Cost Request')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="tw:flex tw:flex-col tw:gap-4 tw:py-4 tw:has-[#crTabs_.nav-link[data-crtab=db].active]:[&_.cr-add-request]:hidden">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
             menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Cost Request</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Cost Request</li>
            </ol>
        </div>

        @php
            // Sama dengan gerbang tombol "Tambah Request" di toolbar sebelumnya (peran tidak berubah).
            $canAdd = Auth::user()->hasRole(['superadmin','manager','supervisor']);
        @endphp

        <x-tw.page-header title="Pengajuan Dana & Pembayaran"
            description="Cash Advance dan Cost Payment beserta tahap persetujuan, pembayaran, dan settlement-nya.">
            @if($canAdd)
            <x-slot:actions>
                {{-- Hanya relevan di tab Cash Advance / Cost Payment: kelas cr-add-request disembunyikan oleh varian has pada
                     pembungkus halaman saat tab Database aktif (sama seperti sebelumnya, tombol ini tidak ada di tab itu). --}}
                <x-tw.button href="{{ url('/budget-request/create') }}" class="cr-add-request tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Request</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

        @if($canAdd)
        {{-- Mobile: tombol "Tambah Request" di header disembunyikan (CSS) dan diganti tombol melayang ini.
             Hanya tampil di layar sempit dan tidak di tab Database (aturan di tesys-ui-refine.css). --}}
        <a href="{{ url('/budget-request/create') }}" class="cr-fab" aria-label="Tambah Request" title="Tambah Request">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
        @endif

        @php
            $n = fn ($counts, $s) => (int) ($counts[$s] ?? 0);

            // Cash Advance: alur penuh sampai Settlement.
            $caSteps = [
                ['Rejected',        $n($caCounts, 'REJECTED'),        'danger',    'REJECTED'],
                ['Settle Rejected', $n($caCounts, 'SETTLE_REJECTED'), 'danger',    'SETTLE_REJECTED'],
                ['Draft',           $n($caCounts, 'DRAFT'),           'secondary', 'DRAFT'],
                ['Submitted',       $n($caCounts, 'SUBMITTED'),       'warning',   'SUBMITTED'],
                ['Checked',         $n($caCounts, 'CHECKED'),         'info',      'CHECKED'],
                ['Verified',        $n($caCounts, 'VERIFIED'),        'primary',   'VERIFIED'],
                ['Approved',        $n($caCounts, 'APPROVED'),        'success',   'APPROVED'],
                ['Process',         $n($caCounts, 'QUEUED'),          'warning',   'QUEUED'],
                ['Paid',            $n($caCounts, 'PAID'),            'success',   'PAID'],
                ['Settle',          $n($caCounts, 'REALIZED'),        'primary',   'REALIZED'],
                ['Settle Checked',  $n($caCounts, 'SETTLE_CHECKED'),  'info',      'SETTLE_CHECKED'],
                ['Settle Verified', $n($caCounts, 'SETTLE_VERIFIED'), 'primary',   'SETTLE_VERIFIED'],
                ['Settle Approved', $n($caCounts, 'SETTLE_APPROVED'), 'success',   'SETTLE_APPROVED'],
                ['Second Approved', $n($caCounts, 'SECOND_APPROVED'), 'success',   'SECOND_APPROVED'],
                ['Closed',          $n($caCounts, 'CLOSED'),          'dark',      'CLOSED'],
            ];

            // Cost Payment: setelah Process langsung Closed (tanpa Settlement).
            $cpSteps = [
                ['Rejected',  $n($cpCounts, 'REJECTED'),  'danger',    'REJECTED'],
                ['Draft',     $n($cpCounts, 'DRAFT'),     'secondary', 'DRAFT'],
                ['Submitted', $n($cpCounts, 'SUBMITTED'), 'warning',   'SUBMITTED'],
                ['Checked',   $n($cpCounts, 'CHECKED'),   'info',      'CHECKED'],
                ['Verified',  $n($cpCounts, 'VERIFIED'),  'primary',   'VERIFIED'],
                ['Approved',  $n($cpCounts, 'APPROVED'),  'success',   'APPROVED'],
                ['Process',   $n($cpCounts, 'QUEUED'),    'warning',   'QUEUED'],
            ];
            // Data lama (sebelum pemisahan alur): non-CA yang masih tertahan di Paid — tampilkan hanya bila ada.
            if ($n($cpCounts, 'PAID') > 0) {
                $cpSteps[] = ['Paid', $n($cpCounts, 'PAID'), 'success', 'PAID'];
            }
            $cpSteps[] = ['Closed', $n($cpCounts, 'CLOSED'), 'dark', 'CLOSED'];

            // Pengelompokan tampilan per fase (presentasi saja; urutan & arti status tidak berubah).
            // Second Approved = pembayaran kedua setelah Settle Approved, jadi masuk fase Settlement.
            $caGroups = [
                ['Fase 1: Pengajuan',   ['REJECTED', 'SETTLE_REJECTED', 'DRAFT', 'SUBMITTED']],
                ['Fase 2: Persetujuan', ['CHECKED', 'VERIFIED', 'APPROVED']],
                ['Fase 3: Pembayaran',  ['QUEUED', 'PAID']],
                ['Fase 4: Settlement',  ['REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'SETTLE_APPROVED', 'SECOND_APPROVED', 'CLOSED']],
            ];
            $cpGroups = [
                ['Fase 1: Pengajuan',   ['REJECTED', 'DRAFT', 'SUBMITTED']],
                ['Fase 2: Persetujuan', ['CHECKED', 'VERIFIED', 'APPROVED']],
                ['Fase 3: Pembayaran',  ['QUEUED', 'PAID', 'CLOSED']],
            ];
        @endphp

        {{-- Tab + rentang tanggal (filter tanggal berlaku untuk semua tab) --}}
        <div class="cr-head tw:flex tw:flex-wrap tw:items-end tw:justify-between tw:gap-2">
            <ul class="nav nav-tabs cr-main-tabs" id="crTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'ca' ? 'active' : '' }}" data-toggle="tab" data-crtab="ca" href="#crPaneCA" role="tab">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Cash Advance
                        <span class="badge badge-secondary ml-1">{{ number_format($caTotal, 0, ',', '.') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'cp' ? 'active' : '' }}" data-toggle="tab" data-crtab="cp" href="#crPaneCP" role="tab">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Cost Payment
                        <span class="badge badge-secondary ml-1">{{ number_format($cpTotal, 0, ',', '.') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'db' ? 'active' : '' }}" data-toggle="tab" data-crtab="db" href="#crPaneDB" role="tab">
                        <i class="fas fa-database mr-1"></i> Database
                        <span class="badge badge-secondary ml-1">{{ number_format($caTotal + $cpTotal, 0, ',', '.') }}</span>
                    </a>
                </li>
            </ul>
            <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:pb-1">
                {{-- Filter tanggal: dropdown "Last Update Time" (12 jam s/d 1 tahun).
                     Pilihan "Custom…" memunculkan dua date-picker di sebelah KIRI
                     dropdown; pilihan lain menyembunyikannya kembali. Tanpa filter,
                     rentangnya tetap bawaan: tanggal 1 s/d akhir bulan berjalan.
                     Semua memuat ulang halaman dengan ?date_from=&date_to= —
                     filternya tetap server-side. --}}
                <div id="crCustomRange" class="d-none align-items-center flex-wrap" style="gap:6px;">
                    <span class="text-muted" style="font-size:12px;">Periode</span>
                    {{-- Satu input rentang: sekali klik, kalender "dari" dan "sampai"
                         tampil berdampingan (dua bulan) dan user memilih dua tanggal
                         berurutan. Nilainya disalin ke dua hidden input di bawah supaya
                         tombol "Terapkan" ada di dalam popup kalender. --}}
                    <div class="d-flex align-items-center cr-date-range" style="gap:4px;">
                        <input type="text" id="crDateRange" class="form-control form-control-sm tdr-input" readonly>
                        <input type="hidden" id="crDateFrom" value="{{ substr($dateFrom, 0, 10) }}">
                        <input type="hidden" id="crDateTo" value="{{ substr($dateTo, 0, 10) }}">
                    </div>
                </div>
                <select id="crQuickRange" class="form-control form-control-sm" style="width:auto;">
                    <option value="">Last Update Time</option>
                    <option value="12h">12 jam terakhir</option>
                    <option value="1d">1 hari terakhir</option>
                    <option value="3d">3 hari terakhir</option>
                    <option value="7d">7 hari terakhir</option>
                    <option value="30d">30 hari terakhir</option>
                    <option value="1y">1 tahun terakhir</option>
                    <option value="custom">Custom…</option>
                </select>
                <x-tw.button id="btnRefreshCR" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
            </div>
        </div>

        {{-- Worklist approval per Budget Plan (dulu section "Review Cost Request") sudah
             dipindah ke halaman Todo List Request. --}}
        <div class="tab-content">
            @foreach([
                'ca' => ['pane' => 'crPaneCA', 'steps' => $caSteps, 'groups' => $caGroups, 'table' => 'tblCA'],
                'cp' => ['pane' => 'crPaneCP', 'steps' => $cpSteps, 'groups' => $cpGroups, 'table' => 'tblCP'],
                'db' => ['pane' => 'crPaneDB', 'steps' => null,     'groups' => null,      'table' => 'tblDB'],
            ] as $tabKey => $cfg)
            <div class="tab-pane fade {{ $activeTab === $tabKey ? 'show active' : '' }} cr-pane" id="{{ $cfg['pane'] }}" data-tab="{{ $tabKey }}" role="tabpanel">

                @if($cfg['steps'])
                {{-- Angka stepper dihitung di server (query agregat) — tampil segera walau tabel belum dimuat. --}}
                <x-workflow-stepper :steps="$cfg['steps']" :compact="true" :groups="$cfg['groups']" :table="$cfg['table']" field="status" />
                @endif

                {{-- Kartu tunggal toolbar + tabel. Div murni (tanpa kelas card/card-body): jarak, tepi, dan
                     latar dari token Tailwind; popover filter/kolom harus bisa keluar dari kartu (tanpa overflow). --}}
                <x-tw.card padding="sm" class="tw:mt-3">
                    @include('budget-request._toolbar', ['tab' => $tabKey])

                    {{-- Diisi AJAX saat tab pertama kali dibuka (data baru dimuat hanya bila tab dibuka).
                         Isi awal = kerangka pemuatan statis (tanpa animasi) yang menyerupai baris tabel. --}}
                    <div class="cr-tab-host" data-tab="{{ $tabKey }}"
                         data-url="{{ url('/budget-request/tab/' . $tabKey) }}">
                        <div role="status" aria-live="polite" aria-label="Memuat data" class="tw:flex tw:flex-col tw:gap-3">
                            <div aria-hidden="true" class="tw:h-8 tw:rounded-sm tw:bg-surface-2"></div>
                            @for($i = 0; $i < 8; $i++)
                            <div aria-hidden="true" class="tw:flex tw:items-center tw:gap-4 tw:border-b tw:border-border tw:pb-3">
                                <span class="tw:h-4 tw:w-8 tw:shrink-0 tw:rounded-sm tw:bg-surface-3"></span>
                                <span class="tw:h-4 tw:w-1/6 tw:shrink-0 tw:rounded-sm tw:bg-surface-3"></span>
                                <span class="tw:h-4 tw:flex-1 tw:rounded-sm tw:bg-surface-2"></span>
                                <span class="tw:h-4 tw:w-1/6 tw:shrink-0 tw:rounded-sm tw:bg-surface-2 tw:max-sm:hidden"></span>
                                <span class="tw:h-4 tw:w-1/12 tw:shrink-0 tw:rounded-sm tw:bg-surface-3"></span>
                            </div>
                            @endfor
                            <span class="tw:sr-only">Memuat data…</span>
                        </div>
                    </div>
                </x-tw.card>
            </div>
            @endforeach
        </div>
      </div>
    </div>
</div>

{{-- ================================================================ --}}
{{-- DRAWER TINJAU CR                                                   --}}
{{-- Permukaan tinjau cepat: status + konteks, ringkasan, timeline,     --}}
{{-- lampiran, dan aksi. Memakai endpoint detail-json yang sudah ada.   --}}
{{-- Rincian Request/Settlement ada di tab drawer ini (satu permukaan).  --}}
{{-- ================================================================ --}}
<x-drawer id="crDrawer" label="Detail Cost Request" width="max(50vw, 560px)">
    <div id="crDrawerContent" hidden>
        <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid var(--border);">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails"><i class="fas fa-list-ul mr-1"></i> Request <span id="crDetailCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneRealization"><i class="fas fa-receipt mr-1"></i> Settlement <span id="crRealizationCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs"><i class="fas fa-history mr-1"></i> Log</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="crPaneInfo"></div>
            <div class="tab-pane fade" id="crPaneDetails">
                                    {{-- Rincian dikelompokkan per Chart of Account, pola yang sama
                                         dengan tab Budget di modal Budgeting. --}}
                                    <div class="d-flex justify-content-end mb-2">
                                        <button type="button" class="btn btn-xs btn-outline-secondary cr-toggle-all" id="btnToggleCrDetail" data-body="crDetailBody" data-expanded="0"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col">Chart of Account / Description</th>
                                                    <th scope="col" style="text-align:right;width:13%;">Price</th>
                                                    <th scope="col" style="text-align:center;width:6%;">Qty</th>
                                                    <th scope="col" style="text-align:right;width:14%;">Total</th>
                                                    <th scope="col" style="text-align:center;width:1%;white-space:nowrap;" id="crDetailActionTh" class="d-none">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:var(--surface-2);"><th colspan="3" class="text-right">Total (item aktif)</th><th class="text-right" id="crDetailTotal" style="color:var(--info);"></th><th class="crDetailActionCell d-none"></th></tr></tfoot>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <h6 class="ts-dw-section-title">
                                            <i class="fas fa-paperclip mr-1"></i> Attachment <span id="crAttCount" class="badge badge-secondary ml-1"></span>
                                        </h6>
                                        <div id="crAttBody" class="cr-att-grid"></div>
                                    </div>
            </div>
                                <div class="tab-pane fade" id="crPaneRealization">
                                    <div id="crNoRealization" class="text-center text-muted py-4" style="display:none;">
                                        <i class="fas fa-receipt d-block mb-2"></i> No settlement data yet.
                                    </div>
                                    <div id="crRealizationWrapper">
                                        <h6 class="ts-dw-section-title">
                                            <i class="fas fa-list mr-1"></i> Rincian Settlement
                                        </h6>
                                        {{-- Dikelompokkan per Chart of Account, sama dengan tab Request & Budgeting. --}}
                                        <div class="d-flex justify-content-end mb-2">
                                            <button type="button" class="btn btn-xs btn-outline-secondary cr-toggle-all" id="btnToggleCrReal" data-body="crRealizationBody" data-expanded="0"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0">
                                                <thead style="background:var(--surface-2);">
                                                    <tr>
                                                        <th scope="col">Chart of Account / Description</th>
                                                        <th scope="col" style="text-align:right;width:13%;">Request</th>
                                                        <th scope="col" style="text-align:right;width:13%;">Settlement</th>
                                                        <th scope="col" style="text-align:right;width:12%;">Var</th>
                                                        <th scope="col" style="text-align:center;width:1%;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="crRealizationBody"></tbody>
                                                <tfoot><tr style="background:var(--surface-2);"><th class="text-right">Total</th><th class="text-right" id="crRealizationReqTotal"></th><th class="text-right" id="crRealizationTotal" style="color:var(--success);"></th><th class="text-right" id="crRealizationVarTotal"></th><th></th></tr></tfoot>
                                            </table>
                                        </div>
                                        <div class="mt-3">
                                            <h6 class="ts-dw-section-title">
                                                <i class="fas fa-paperclip mr-1"></i> Settlement Attachment (Global)
                                            </h6>
                                            <div id="crRealizationAtts" class="cr-att-grid"></div>
                                        </div>
                                        <div id="crSecondPaymentBox" class="mt-3" style="display:none;"></div>
                                    </div>
                                </div>
            <div class="tab-pane fade" id="crPaneLogs">
                <div id="crLogBody" class="cr-timeline cr-chat"></div>
                {{-- Kotak catatan gaya chat; disembunyikan JS saat CR berstatus Closed. --}}
                <form id="crLogForm" class="cr-log-form" hidden>
                    <textarea id="crLogInput" class="form-control" rows="2" maxlength="1000"
                              placeholder="Tulis catatan atau update…" aria-label="Catatan log"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary" id="crLogSend"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
                </form>
                <div id="crLogClosed" class="cr-log-closed" hidden><i class="fas fa-lock mr-1"></i> Cost Request sudah Closed — catatan baru tidak dapat ditambahkan.</div>
            </div>
        </div>
    </div>

    <div id="crDrawerError" hidden></div>

    <div id="crDrawerLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="card" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="4" />
    </div>

    <x-slot:footer>
        <div id="crDrawerActions" class="ts-actions" style="flex:1;justify-content:flex-start;"></div>
    </x-slot:footer>
</x-drawer>

{{-- MODAL HEAD APPROVE --}}
<div class="modal fade" id="mdlHeadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Head Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmHeadApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="headApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL NOTES (Head Revise/Reject, Finance Revise/Reject, Reject Realization) --}}
<div class="modal fade" id="mdlNotes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white" id="mdlNotesTitle"></h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmNotes" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <div id="mdlNotesAlert" class="alert alert-warning mb-3" style="font-size:12px;display:none;"></div>
                <div class="form-group mb-0">
                    <label class="font-weight-600" id="mdlNotesLabel">Catatan <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control" rows="3" required placeholder="Tuliskan catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="mdlNotesSubmitBtn">Konfirmasi</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL FINANCE VERIFY --}}
<div class="modal fade" id="mdlFinanceVerify" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Finance Verify</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmFinanceVerify" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="financeVerifyText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Verify</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL CEO APPROVE --}}
<div class="modal fade" id="mdlCeoApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> CEO Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmCeoApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="ceoApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL SUBMIT REALISASI --}}
{{-- MODAL VERIFY REALIZATION --}}
<div class="modal fade" id="mdlVerifyRealization" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Settlement</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmVerifyRealization" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="verifyRealText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" id="btnVerifyRealSubmit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- accounts JSON untuk realisasi --}}

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>

/* Tabel yang baru tiba lewat AJAX ikut memudar, bukan muncul mendadak
   (animasi masuk halaman sendiri ada di tesys-ui-motion.css). */
.cr-tab-host.cr-tab-in { animation: tesys-enter .35s var(--ease-out) backwards; }

.text-var-pos { color:var(--success); }
.text-var-neg { color:var(--danger); }

/* ── Gaya index Purchase Order: link kode/status biru-tua. Latar/sorot baris/NOB/centang
   tabel Cost Request kini utilitas tw di _table.blade.php ($gayaTabel) + datatable.css. ── */
.cr-number-btn, .cr-cell-link {
    display:inline-block; background:transparent; border:none; padding:2px 8px; border-radius:var(--radius-sm);
    color:var(--text); font-weight:700; line-height:1.4;
    transition:background-color .12s ease, color .12s ease;
}
.cr-number-btn { font-size:12px; }
.cr-cell-link { font-size:13px; }
.cr-number-btn:hover, .cr-number-btn:focus,
.cr-cell-link:hover, .cr-cell-link:focus { background:var(--charcoal); color:#fff; outline:none; }
.cr-status-link { cursor:pointer; }
.cr-status-link:hover { filter:brightness(.92); }

/* ── TABEL: gaya sel/th/tfoot, kolom beku (centang/Code/Action), baris NOB/centang
   kini utilitas tw pada <table> (_table.blade.php, $gayaTabel; M5a). Di sini tinggal
   pembungkus guliran server (.cr-table-wrap) dan kursor kotak centang. ── */
/*
| .cr-table-wrap dipakai di _table.blade.php sebagai pembungkus tabel; didefinisikan
| agar markup server sudah punya area geser sendiri sebelum JavaScript membungkusnya
| ulang dengan .cr-hscroll (.table-responsive: overflow-x:auto).
*/
.cr-table-wrap { overflow-x:auto; }
/* Mode kartu (<= 640px) tabel Cost Request: utilitas tw pada <table> (_table.blade.php, $gayaKartu; M5b). */
.cr-col-check input[type="checkbox"] { cursor:pointer; margin:0; vertical-align:middle; }
.cr-col-check input[type="checkbox"]:disabled { cursor:not-allowed; }

/* Modal detail CR — ukuran & style disamakan dengan modal show Budget Plan. */
.modal-dialog-xxl {
    max-width: calc(100vw - 32px);
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    margin: 16px auto;
}
.modal-dialog-xxl .modal-content {
    height: calc(100vh - 32px);
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
}
.modal-dialog-xxl .modal-header,
.modal-dialog-xxl .modal-footer { flex: 0 0 auto; }
.modal-dialog-xxl .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
.modal-dialog-xxl #crDetailContent { height: 100%; }

.cr-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.cr-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.cr-sidebar-spacer { flex:1 1 auto; }
.cr-main-panel { flex:1 1 auto; padding:24px; min-width:0; }
@media (max-width:767.98px) {
    .cr-modal-flex { flex-direction:column; }
    .cr-sidebar { width:100%; flex:0 0 auto; }
}

/* Log tab — gaya chat sama persis dengan tab Log Purchase Order
   (project/_index-assets.blade.php: .prj-chat-*) — log dari user yang sedang
   login rata kanan ("mine"), lainnya rata kiri, dengan avatar inisial. */
.cr-timeline { padding:14px 4px; }
.cr-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.cr-chat-row { display:flex; gap:10px; max-width:78%; }
.cr-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.cr-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.cr-chat-row.mine .cr-chat-av { background:var(--charcoal); color:#fff; }
.cr-chat-av { overflow:hidden; }
.cr-chat-av img { width:100%; height:100%; object-fit:cover; }
.cr-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.cr-chat-row.mine .cr-chat-bubble { border-color:var(--border); }
.cr-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
/* Tab satu baris: bisa digeser mendatar tanpa scrollbar. */
#crDetailTabs { flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden; scrollbar-width:none; -ms-overflow-style:none; }
#crDetailTabs::-webkit-scrollbar { display:none; }
#crDetailTabs .nav-item { flex:0 0 auto; }
#crDetailTabs .nav-link { white-space:nowrap; }
/* Tab Log: chat menempel di bawah (terlama di atas), kotak kirim selalu terlihat. */
#crPaneLogs.active { display:flex; flex-direction:column; }
#crPaneLogs #crLogBody { margin-top:auto; }
#crPaneLogs .cr-log-form, #crPaneLogs .cr-log-closed { position:sticky; bottom:-24px; margin-bottom:-24px; padding-bottom:24px; background:var(--surface); }
/* Kode CR di kepala drawer: klik untuk menyalin. */
.cr-copy-code { background:none; border:0; padding:0; margin:0; font:inherit; letter-spacing:inherit; text-transform:inherit; color:inherit; cursor:pointer; }
.cr-copy-code:hover { color:var(--accent); text-decoration:underline; }
.cr-copy-code:focus-visible { outline:none; box-shadow:0 0 0 3px var(--accent-ring); border-radius:var(--radius-sm); }
/* Kotak catatan tab Log. */
.cr-log-form { display:flex; gap:8px; align-items:flex-end; margin-top:16px; padding-top:12px; border-top:1px solid var(--border); }
.cr-log-form textarea { font-size:12px; resize:none; flex:1 1 auto; }
.cr-log-closed { margin-top:16px; padding-top:12px; border-top:1px solid var(--border); font-size:11.5px; color:var(--text-faint); }
.cr-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }

/* Budget tab — reuse pola pivot grup/NOB dari Budget Plan */
.bp-caret.rotated { transform:rotate(90deg); }
.cr-req-badge { font-size:9px; }

/* Tombol salin (Reference & Kode di tab Info). */

/* Teks deskripsi panjang di tab Settlement: dipotong satu baris dengan "…",
   melebar penuh saat barisnya disorot kursor. */
#crRealizationBody .cr-clip { display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis; }
#crRealizationBody tr:hover .cr-clip { -webkit-line-clamp:unset; overflow:visible; white-space:normal; }

/* TAB INFO (Cost Request) — clean grid layout, sama seperti Budget Plan */
.cr-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.cr-info-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
.cr-info-grid-ref { display:grid; grid-template-columns:320px 1fr; gap:14px 18px; }
.cr-info-grid-scope { display:grid; grid-template-columns:3fr 1fr; gap:14px 18px; }
.cr-info-grid-scope.has-payment { grid-template-columns:12fr 5fr 3fr; }
.cr-info-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 16px; }
.cr-info-label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:5px; }
.cr-info-value { font-size:13px; color:var(--text); white-space:nowrap; }
.cr-info-grid-scope .cr-info-value { white-space:normal; }
.cr-info-highlight { background:var(--warning-bg); border-color:var(--border-strong); }
.cr-info-highlight .cr-info-value { font-weight:700; color:var(--warning); }
@media (max-width:576px) {
    .cr-info-grid, .cr-info-grid-3, .cr-info-grid-ref, .cr-info-grid-scope, .cr-info-grid-scope.has-payment { grid-template-columns:1fr; }
    .cr-info-value { white-space:normal; }
}

/* TAB REQUEST & REALIZATION — thumbnail grid lampiran */
.cr-att-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin-top:8px; }

/* Grup Chart of Account pada tab Request — mengikuti tampilan grup di modal Budgeting. */
#crDetailBody tr.cr-group-row, #crRealizationBody tr.cr-group-row,
#crDetailBody tr.cr-group-row td, #crRealizationBody tr.cr-group-row td { background:var(--info-bg) !important; }
#crDetailBody tr.cr-group-row:hover, #crRealizationBody tr.cr-group-row:hover,
#crDetailBody tr.cr-group-row:hover td, #crRealizationBody tr.cr-group-row:hover td { background:var(--surface-3) !important; }
#crDetailBody .cr-caret.rotated, #crRealizationBody .cr-caret.rotated { transform:rotate(90deg); }
#crDetailBody td.cr-indent, #crRealizationBody td.cr-indent { padding-left:32px !important; }
/* Kartu lampiran: seluruh kartu adalah tombol yang membuka carousel. */
.cr-att-card { display:block; width:100%; padding:0; text-align:left; border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; background:var(--surface); cursor:pointer; transition:border-color .12s ease, box-shadow .12s ease; }
.cr-att-card:hover, .cr-att-card:focus-visible { border-color:var(--accent-soft); box-shadow:var(--shadow-sm, 0 1px 4px rgba(0,0,0,.12)); outline:none; }
.cr-att-thumb { width:100%; height:120px; object-fit:cover; display:block; background:var(--surface-2); }
.cr-att-meta { display:block; padding:0 8px 6px; font-size:10.5px; color:var(--text-faint); }
.cr-att-icon { width:100%; height:120px; display:flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-faint); font-size:30px; }
.cr-att-name { font-size:11px; padding:6px 8px; color:var(--text); word-break:break-word; border-top:1px solid var(--border); }

.cr-search-box input   { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
.ts-control .item {
    max-width: 240px;
    display: flex;
    align-items: center;
    overflow: visible;
}
.ts-control .item > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}
.ts-control .item .remove { flex-shrink: 0; }
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:3px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
/* Tab utama: Cash Advance / Cost Payment / Database */
.cr-main-tabs { border-bottom:2px solid var(--border); flex-wrap:wrap; }
.cr-main-tabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:9px 16px; font-weight:600; font-size:13px; margin-bottom:-2px; }
.cr-main-tabs .nav-link:hover { color:var(--text); }
.cr-main-tabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; }
.cr-tab-host { min-height:120px; }
/* Tab drawer tetap di atas saat isinya digulir (.ts-drawer-body yang menggulir). */
/* Offset -18px = padding-top .ts-drawer-body, supaya tab menempel tepat di tepi atas dan isi tidak terlihat di atasnya. */
#crDetailTabs { position:sticky; top:-18px; margin-top:-18px; padding-top:18px; z-index:5; background:var(--surface); }
#crDetailTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 12px; }
#crDetailTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }
@media (max-width:576px) {
    .cr-btn-clear { width:auto; }
    .cr-row-filters { width:100%; }
    .cr-row-filters .tcf-row-btn { width:100%; }
    .cr-search-box input { width:100%; }
    .ts-wrapper { width:100% !important; min-width:0 !important; }
    .cr-row-filters .tcf-row-btn { flex:1 1 auto; }
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

/* Panel filter gabungan: dua kolom, rapat, dan tidak terpotong kontainer. */
.cr-filter-panel { max-height: 70vh; overflow-y: auto; }
.cr-filter-panel .cr-row-filters {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px;
}
.cr-filter-panel .tcf-row-btn { width: 100%; text-align: left; justify-content: space-between; overflow: hidden; }
.cr-filter-panel .tcf-row-btn > i { margin-left: 6px; flex: 0 0 auto; }
.cr-filter-title {
    font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--text-faint); margin-bottom: 10px;
}
.cr-filter-foot { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border); }
.cr-filter-toggle.cr-filter-on { background: var(--accent); border-color: var(--accent); color: #fff; }
@media (max-width: 575.98px) {
    .cr-filter-panel { min-width: 260px !important; }
    .cr-filter-panel .cr-row-filters { grid-template-columns: 1fr; }
}

/* Popover toolbar (Filter & Kolom) — ditempel pada tombolnya sendiri. */
.cr-pop { position: relative; }
.cr-pop-menu {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; z-index: 1080;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,.14);
}
.cr-pop.show .cr-pop-menu { display: block; }
</style>
@endpush

@push('scripts')
<script>
var currentUserId = parseInt('{{ (int) Auth::id() }}', 10) || 0;

$(function () {

    // Keterangan status berasal dari App\Support\WorkflowStatus (satu sumber).
    var statusMeta = @json(collect(\App\Models\BudgetRequest::STATUSES)->mapWithKeys(fn ($s) => [
        $s => \App\Support\WorkflowStatus::meta($s, 'budget-request'),
    ]));


    /* ── FILTER TANGGAL ────────────────────────────────────────────────────
    | Komponen bersama TesysDateRange (public/js/tesys-date-range.js): satu
    | kotak teks yang membuka dua kalender "From / To". Nilainya ditulis ke
    | #crDateFrom / #crDateTo, dan baru dipakai saat "Terapkan" ditekan.
    | Kalau flatpickr gagal termuat, komponennya mengembalikan null dan filter
    | turun ke dua input teks manual (YYYY-MM-DD). */
    var crPeriode = (typeof TesysDateRange === 'object')
        ? TesysDateRange.init({
            input: '#crDateRange', from: '#crDateFrom', to: '#crDateTo',
            applyButton: { label: 'Terapkan', onClick: function (dari, sampai) { applyDateRange(dari, sampai); } }
        })
        : null;

    if (!crPeriode) {
        console.warn('TesysDateRange tidak tersedia - filter tanggal beralih ke mode input manual.');
        $('#crDateFrom, #crDateTo').attr('type', 'text')
            .addClass('form-control form-control-sm')
            .attr('placeholder', 'YYYY-MM-DD');
        $('#crDateRange').remove();
        // Tanpa popup kalender, tombol Terapkan perlu tampil di samping input manual.
        $('<button type="button" class="btn btn-primary btn-sm"><i class="fas fa-check mr-1"></i> Terapkan</button>')
            .on('click', function () { applyDateRange($('#crDateFrom').val(), $('#crDateTo').val()); })
            .appendTo('.cr-date-range');
    }

    function crBukaPeriode() { if (crPeriode) { crPeriode.open(); } }
    function crTutupPeriode() { if (crPeriode) { crPeriode.close(); } }

    /* ── RENTANG CEPAT ──────────────────────────────────────────────────────
    | "12 jam terakhir" memakai presisi jam (YYYY-MM-DD HH:mm:ss); pilihan hari
    | dan tahun memakai tanggal saja. Pilihan "Custom…" hanya membuka
    | date-picker "dari" supaya user menentukan sendiri rentangnya. */
    function fmtTanggal(d) {
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    }
    function fmtWaktu(d) {
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        return fmtTanggal(d) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    // Pilihan aktif: dari ?range=, atau "Custom…" kalau halaman dibuka dengan
    // rentang tanggal yang ditentukan sendiri.
    var RANGE_AKTIF = '{{ request('range', '') }}';
    if (!RANGE_AKTIF && @json(request()->filled('date_from') || request()->filled('date_to'))) {
        RANGE_AKTIF = 'custom';
    }
    $('#crQuickRange').val(RANGE_AKTIF);
    $('#crCustomRange').toggleClass('d-none', RANGE_AKTIF !== 'custom')
                       .toggleClass('d-flex', RANGE_AKTIF === 'custom');

    $('#crQuickRange').on('change', function () {
        var v = $(this).val();
        if (!v) return;

        if (v === 'custom') {
            $('#crCustomRange').removeClass('d-none').addClass('d-flex');
            crBukaPeriode();
            return;
        }
        $('#crCustomRange').addClass('d-none').removeClass('d-flex');

        var now  = new Date();
        var mulai = new Date(now.getTime());
        var pakaiJam = false;

        if (v === '12h')      { mulai.setHours(mulai.getHours() - 12); pakaiJam = true; }
        else if (v === '1d')  { mulai.setDate(mulai.getDate() - 1); }
        else if (v === '3d')  { mulai.setDate(mulai.getDate() - 3); }
        else if (v === '7d')  { mulai.setDate(mulai.getDate() - 7); }
        else if (v === '30d') { mulai.setDate(mulai.getDate() - 30); }
        else if (v === '1y')  { mulai.setFullYear(mulai.getFullYear() - 1); }

        /* Sama seperti "Terapkan": data diambil ulang lewat AJAX, halaman tidak
           dimuat ulang. Pilihan rentangnya tetap disimpan di URL (?range=). */
        applyDateRange(
            pakaiJam ? fmtWaktu(mulai) : fmtTanggal(mulai),
            pakaiJam ? fmtWaktu(now)   : fmtTanggal(now),
            v
        );
    });

    // (accounts holder lama sudah tidak diperlukan — realisasi sekarang pakai CR Detail Item)

    /* ── TAB CASH ADVANCE / COST PAYMENT / DATABASE — data dimuat saat tab dibuka ──
    | Halaman awal hanya membawa kerangka + angka stepper. Baris tabel diambil
    | AJAX (BudgetRequestController::tab) HANYA ketika tab pertama kali dibuka,
    | lalu DataTables dipasang pada tabel tab itu saja.
    | Catatan: jangan menulis tag komponen atau direktif Blade di komentar. */
    var CR_DATE_FROM = '{{ $dateFrom }}';
    var CR_DATE_TO   = '{{ $dateTo }}';
    var CR_ROW_FILTERS = [
        { key: 'po',          label: 'PO' },
        { key: 'type',        label: 'Tipe' },
        { key: 'beneficiary', label: 'Beneficiary' },
        { key: 'pic',         label: 'PIC' },
        { key: 'requestor',   label: 'Requestor' },
        { key: 'pminternal',  label: 'Manager' },
        { key: 'status',      label: 'Status' },
    ];
    var CR_TABS = {
        // Tab Cash Advance isinya sudah pasti satu tipe, jadi filter Tipe
        // tidak menyaring apa pun di sini.
        ca: { id: 'tblCA', mapVis: { tanggal: 3, paid_at: 4, pengaju: 5, pic: 6, realisasi: 8, refund: 9, claim: 10 },
              filters: CR_ROW_FILTERS.filter(function (f) { return f.key !== 'type'; }) },
        cp: { id: 'tblCP', mapVis: { tanggal: 3, paid_at: 4, pengaju: 5, pic: 6 },
              filters: CR_ROW_FILTERS },
        db: { id: 'tblDB', mapVis: { tanggal: 4, paid_at: 5, pengaju: 6, pic: 7 },
              filters: CR_ROW_FILTERS },
    };
    var crVisDefault = { tanggal: true, paid_at: true, pengaju: true, pic: true, realisasi: false, refund: true, claim: true };

    // Preferensi kolom per tab (kunci tab Cash Advance tetap sama seperti sebelumnya).
    function crVisKey(tab, key) { return 'crColVis_' + (tab === 'ca' ? '' : tab + '_') + key; }
    function getCrColVis(tab, key) {
        var stored = localStorage.getItem(crVisKey(tab, key));
        return stored === null ? !!crVisDefault[key] : stored === '1';
    }
    function setCrColVis(tab, key, visible) { localStorage.setItem(crVisKey(tab, key), visible ? '1' : '0'); }

    function crMoney(v) {
        var sign = v < 0 ? '-' : '';
        return sign + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }
    function crPane(tab) { return $('.cr-pane[data-tab="' + tab + '"]'); }

    /* ── PANEL FILTER GABUNGAN ─────────────────────────────────────────────
    | Dulu tiap field punya tombol dropdown sendiri sehingga toolbar penuh
    | tombol dan posisinya bergeser-geser saat dibuka. Sekarang semuanya
    | dikumpulkan di satu panel: satu <select> per field, pilihannya diambil
    | dari nilai yang benar-benar ada pada baris tabel, lalu diterapkan
    | sekaligus lewat API filter yang sama (jadi chip & reset tetap jalan). */
    function buildFilterPanel($pane, cfg) {
        /*
        | Panel ini TIDAK membuat kontrol sendiri: tombol per-field bawaan
        | TesysColumnFilter dipindahkan ke dalamnya. Dengan begitu tiap filter
        | tetap punya kotak pencarian dan daftar centang (bisa pilih lebih dari
        | satu nilai), lengkap dengan chip dan penyorotan tombol — perilaku yang
        | sudah dipakai modul lain, tanpa menduplikasi logikanya.
        */
        var $filters = $pane.find('.cr-row-filters');
        if (!$filters.length) return;

        function tandaiAktif() {
            var ada = $filters.find('.tcf-row-active').length > 0;
            $pane.find('.cr-filter-toggle').toggleClass('cr-filter-on', ada);
        }

        $filters.off('click.crfilter').on('click.crfilter', '.tcf-row-btn', function () {
            setTimeout(tandaiAktif, 50);
        });

        $pane.find('.cr-filter-reset').off('click').on('click', function () {
            TesysColumnFilter.clearAll('#' + cfg.id);
            tandaiAktif();
        });

        tandaiAktif();
    }


    /* ── POPOVER FILTER & KOLOM ────────────────────────────────────────────
    | Tidak memakai dropdown Bootstrap: Popper menghitung posisinya terhadap
    | kontainer tabel yang bisa digulir, sehingga panelnya melompat-lompat
    | saat dibuka. Di sini panel cukup ditempel absolut di bawah tombolnya,
    | lalu digeser ke kiri kalau ujungnya melewati tepi layar. */
    $(document).on('click', '.cr-pop-toggle', function (e) {
        e.stopPropagation();
        var $pop = $(this).closest('.cr-pop');
        var buka = !$pop.hasClass('show');

        $('.cr-pop').removeClass('show');
        $pop.toggleClass('show', buka);
        if (!buka) return;

        // Geser kalau panel keluar dari tepi kanan layar.
        var $menu = $pop.find('.cr-pop-menu');
        $menu.css({ left: 0, right: 'auto' });
        var rect = $menu[0].getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) $menu.css({ left: 'auto', right: 0 });
    });

    $(document).on('click', function (e) {
        /*
        | Daftar centang tiap filter (.tcf-dropdown) dipasang di <body>, bukan
        | di dalam panel. Tanpa pengecualian ini, mencentang satu nilai dianggap
        | "klik di luar" sehingga panel Filter-nya ikut tertutup dan daftarnya
        | tertinggal mengambang.
        */
        if ($(e.target).closest('.cr-pop, .tcf-dropdown').length) return;
        $('.cr-pop').removeClass('show');
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('.cr-pop').removeClass('show');
    });


    // Klik kode CR = salin ke clipboard (detail dibuka lewat klik baris).
    function crToast(pesan, ikon) {
        // Toast standar aplikasi (sama dengan pesan flash sukses/galat).
        if (window.tsToast) window.tsToast(ikon === 'error' ? 'error' : 'success', pesan);
    }
    $(document).on('click', '.cr-copy-code', function (e) {
        e.stopPropagation();
        var kode = String($(this).data('code') || $.trim($(this).text()));
        function berhasil() { crToast('Kode ' + kode + ' disalin'); }
        function gagal() { crToast('Gagal menyalin kode', 'error'); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(kode).then(berhasil, gagal);
            return;
        }
        // Cadangan untuk koneksi non-HTTPS.
        var ta = document.createElement('textarea');
        ta.value = kode; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy') ? berhasil() : gagal(); } catch (err) { gagal(); }
        document.body.removeChild(ta);
    });

    /* Lebar kolom bisa diseret (handle di tepi kanan header) dan diingat per tab di
       localStorage. Tanpa pengaturan tersimpan, lebar bawaan dari CSS tetap dipakai.
       Lebar ditulis lewat elemen <style> khusus di dalam media (min-width: 641px),
       jadi mode kartu mobile tidak tersentuh. Klik dua kali handle = kembali ke bawaan. */
    function crInitResize(tab, tbl) {
        var id = tbl.id, kunci = 'crColW_' + tab, MIN = 48;
        var styleEl = document.createElement('style');
        document.head.appendChild(styleEl);

        function zoom() { return parseFloat(getComputedStyle(document.documentElement).zoom) || 1; }
        function ths() { return Array.prototype.slice.call(tbl.querySelectorAll('thead th')); }
        function lebar(th) { return th.getBoundingClientRect().width / zoom(); }

        // Lebar efektif sekarang: kolom ber-data-col ke peta, sisanya (centang/aksi) ke "tetap".
        function ukur() {
            var peta = {}, tetap = 0;
            ths().forEach(function (th) {
                var k = th.getAttribute('data-col');
                if (k) peta[k] = lebar(th); else tetap += lebar(th);
            });
            return { peta: peta, tetap: tetap };
        }
        function lukis(peta, tetap) {
            var total = tetap, aturan = '';
            Object.keys(peta).forEach(function (k) {
                total += peta[k];
                aturan += 'body.tesys-ui .cr-pane #' + id + ' th[data-col="' + k + '"]{width:' + Math.round(peta[k]) + 'px !important;}';
            });
            aturan += 'body.tesys-ui .cr-pane table#' + id + '{width:' + Math.round(total) + 'px;min-width:0;}';
            styleEl.textContent = '@media (min-width: 641px){' + aturan + '}';
        }
        function simpan(peta) {
            try { localStorage.setItem(kunci, JSON.stringify(peta)); } catch (e) {}
        }
        function reset() {
            styleEl.textContent = '';
            try { localStorage.removeItem(kunci); } catch (e) {}
        }

        // Terapkan lebar tersimpan (bila ada) di atas lebar bawaan yang sedang terukur.
        var tersimpan = null;
        try { tersimpan = JSON.parse(localStorage.getItem(kunci) || 'null'); } catch (e) {}
        if (tersimpan && typeof tersimpan === 'object') {
            var u = ukur();
            Object.keys(u.peta).forEach(function (k) {
                var w = parseFloat(tersimpan[k]);
                if (w >= MIN) u.peta[k] = w;
            });
            lukis(u.peta, u.tetap);
        }

        ths().forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (!k) return;
            var h = document.createElement('span');
            h.className = 'cr-resizer';
            h.title = 'Seret untuk mengubah lebar • klik dua kali untuk mengembalikan';
            th.appendChild(h);

            h.addEventListener('click', function (e) { e.stopPropagation(); });   // jangan memicu sortir
            h.addEventListener('dblclick', function (e) { e.stopPropagation(); reset(); });
            h.addEventListener('pointerdown', function (e) {
                e.preventDefault(); e.stopPropagation();
                var z = zoom(), u = ukur(), x0 = e.clientX, w0 = u.peta[k];
                h.setPointerCapture(e.pointerId);
                h.classList.add('is-active');
                function gerak(ev) {
                    u.peta[k] = Math.max(MIN, w0 + (ev.clientX - x0) / z);
                    lukis(u.peta, u.tetap);
                }
                function lepas() {
                    h.removeEventListener('pointermove', gerak);
                    h.removeEventListener('pointerup', lepas);
                    h.removeEventListener('pointercancel', lepas);
                    h.classList.remove('is-active');
                    simpan(u.peta);
                }
                h.addEventListener('pointermove', gerak);
                h.addEventListener('pointerup', lepas);
                h.addEventListener('pointercancel', lepas);
            });
        });
    }

    function crCurrentTab() { return $('#crTabs .nav-link.active').data('crtab') || 'ca'; }

    function initCrTable(tab) {
        var cfg = CR_TABS[tab], $pane = crPane(tab), $host = $pane.find('.cr-tab-host');
        var $tbl = $('#' + cfg.id);
        if (!$tbl.length) return;

        var emptyTpl = $host.find('.cr-empty-template')[0];
        // Kolom checkbox bulk (hanya bila server merendernya) menggeser SEMUA indeks kolom satu langkah.
        var off = $tbl.find('thead th.cr-col-check').length ? 1 : 0;
        var defs = [{ targets: [off], visible: false, orderable: false }];
        if (off) defs.push({ targets: [0], orderable: false, searchable: false });
        // Kolom Action (terakhir; tab Database tidak punya) tanpa sortir.
        if ($tbl.find('thead th.action-column').length) defs.push({ targets: [-1], orderable: false, searchable: false });
        Object.keys(cfg.mapVis).forEach(function (k) {
            defs.push({ targets: [cfg.mapVis[k] + off], visible: getCrColVis(tab, k) });
        });

        var table;
        try {
            // TIDAK pakai extension "responsive" DataTables (rebentrok dengan toggle
            // visibility manual). Tabel selalu scroll horizontal lewat .cr-hscroll.
            table = $tbl.DataTable({
                autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
                order: [], // pertahankan urutan dari server (input terbaru di atas)
                columnDefs: defs,
                dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
                language: { lengthMenu: 'Page Size _MENU_',
                    emptyTable: (emptyTpl && emptyTpl.innerHTML) || 'Belum ada data',
                    zeroRecords: '<div class="ts-empty ts-empty-compact">'
                        + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                        + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                        + '<p class="ts-empty-msg">Tidak ada Cost Request yang cocok dengan pencarian atau filter yang sedang aktif.</p>'
                        + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral cr-empty-reset">'
                        + '<i class="fas fa-undo"></i><span class="ts-al">Reset filter</span></button></div></div>',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data',
                    paginate: { previous: 'Previous', next: 'Next' } }
            });
        } catch (err) {
            console.error('Gagal inisialisasi DataTable tab ' + tab + ':', err);
            return;
        }
        cfg.table = table;

        $tbl.wrap('<div class="cr-hscroll table-responsive"></div>');
        crInitResize(tab, $tbl[0]);

        // Toggle kolom
        $pane.find('.cr-col-vis-toggle').each(function () {
            var key = $(this).data('column');
            if (cfg.mapVis[key] == null) return;
            $(this).prop('checked', table.column(cfg.mapVis[key] + off).visible());
        }).off('change').on('change', function () {
            var key = $(this).data('column');
            if (cfg.mapVis[key] == null) return;
            var visible = $(this).is(':checked');
            table.column(cfg.mapVis[key] + off).visible(visible);
            setCrColVis(tab, key, visible);
            try { table.columns.adjust(); } catch (e) {}
        });
        $pane.find('.cr-col-dropdown .dropdown-menu').off('click').on('click', function (e) { e.stopPropagation(); });
        table.on('column-visibility.dt', function () { try { table.columns.adjust(); } catch (e) {} });

        // Pencarian (debounce — hindari full-scan tabel pada tiap ketukan)
        $('#' + cfg.id + '_filter').hide();
        $pane.find('.cr-search-box').html('<input type="search" class="form-control form-control-sm cr-search-input" aria-label="Cari data" placeholder="Cari data...">');
        var searchTimer = null;
        $pane.find('.cr-search-input').on('keyup search', function () {
            var val = this.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.search(val).draw(); }, 300);
        });

        // Footer: total dari SEMUA baris yang lolos filter/search (bukan cuma halaman aktif).
        var recalcFooter = function () {
            var amount = 0, real = 0, vr = 0, cost = 0, claim = 0;
            table.rows({ search: 'applied' }).every(function () {
                var node = this.node();
                amount += parseFloat(node.querySelector('.col-raw-amount')?.dataset.raw)    || 0;
                real   += parseFloat(node.querySelector('.col-raw-realisasi')?.dataset.raw) || 0;
                vr     += parseFloat(node.querySelector('.col-raw-var')?.dataset.raw)       || 0;
                cost   += parseFloat(node.querySelector('.col-raw-cost')?.dataset.raw)      || 0;
                claim  += parseFloat(node.querySelector('.col-raw-claim')?.dataset.raw)     || 0;
            });
            $tbl.find('.cr-foot-amount').text(crMoney(amount));
            $tbl.find('.cr-foot-realisasi').text(crMoney(real));
            $tbl.find('.cr-foot-cost').text(crMoney(cost));
            $tbl.find('.cr-foot-var').html('<span class="' + (vr >= 0 ? 'text-var-pos' : 'text-var-neg') + '">' + crMoney(vr) + '</span>');
            $tbl.find('.cr-foot-claim').text(crMoney(claim));
        };
        table.on('draw', recalcFooter);
        recalcFooter();

        TesysColumnFilter.initRowFilters('#' + cfg.id, $pane.find('.cr-row-filters'), cfg.filters);
        buildFilterPanel($pane, cfg);
        crBulkInit(tab);

        $pane.find('.cr-btn-clear').off('click').on('click', function () {
            TesysColumnFilter.clearAll('#' + cfg.id);
            TesysWorkflowStepper.clear(cfg.id);
            var url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({}, '', url.toString());
        });

        // Filter status dari URL (?status=) hanya untuk tab yang sedang aktif dibuka dari URL.
        var urlStatus = new URLSearchParams(window.location.search).get('status');
        if (urlStatus && crCurrentTab() === tab) {
            var $step = $('[data-wf-table="' + cfg.id + '"] .wf-tl-step[data-status="' + urlStatus + '"]');
            if ($step.length) $step.trigger('click');
        }

        /* Filter dari tautan luar (kartu Active Cash Advance di Summary).
           Dipasang lewat setRowFilter supaya hasilnya persis seperti user
           mencentang sendiri: chip filternya muncul dan bisa dilepas.
           Sama seperti ?status=, hanya berlaku untuk tab yang dibuka dari URL. */
        if (crCurrentTab() === tab) {
            (function applyRowFilterFromUrl() {
                var q   = new URLSearchParams(window.location.search);
                var sel = '#' + cfg.id;
                var manager = q.get('manager');
                var pic     = q.get('pic');
                if (manager) TesysColumnFilter.setRowFilter(sel, 'pminternal', manager.toUpperCase());
                if (pic)     TesysColumnFilter.setRowFilter(sel, 'pic', pic);

                /* Tipe & daftar status ikut dibawa supaya daftar di sini memuat
                   himpunan yang sama dengan kartu asalnya. setRowFilter menambah
                   (bukan mengganti) nilai pada field yang sama, jadi beberapa
                   status bisa dipasang berurutan. */
                var type = q.get('type');
                if (type) TesysColumnFilter.setRowFilter(sel, 'type', type);

                /* Daftar PIC di bawah manager yang dipilih. Dipasang sebagai filter
                   agar namanya terbaca di chip - bukan untuk mempersempit, karena
                   filter Manager sudah melakukannya. */
                (q.get('pics') || '').split(',').forEach(function (nm) {
                    nm = nm.trim();
                    if (nm) TesysColumnFilter.setRowFilter(sel, 'pic', nm);
                });

                (q.get('statuses') || '').split(',').forEach(function (st) {
                    st = st.trim();
                    if (st) TesysColumnFilter.setRowFilter(sel, 'status', st);
                });
            })();
        }
    }

    function crLoadTab(tab) {
        var cfg = CR_TABS[tab];
        if (!cfg) return;
        if (cfg.loaded) { try { cfg.table && cfg.table.columns.adjust(); } catch (e) {} return; }
        if (cfg.loading) return;

        var $host = crPane(tab).find('.cr-tab-host');
        cfg.loading = true;
        $.get($host.data('url'), { date_from: CR_DATE_FROM, date_to: CR_DATE_TO })
            .done(function (html) {
                $host.html(html).addClass('cr-tab-in');
                cfg.loaded = true;
                initCrTable(tab);
                crSyncCounts(tab);
            })
            .fail(function () {
                $host.html('<div class="text-center text-muted py-4">'
                    + '<i class="fas fa-exclamation-triangle mr-1"></i> Gagal memuat data. '
                    + '<button type="button" class="btn btn-sm btn-outline-secondary ml-2 cr-tab-retry" data-tab="' + tab + '">Coba lagi</button></div>');
            })
            .always(function () { cfg.loading = false; });
    }

    $(document).on('click', '.cr-tab-retry', function () { crLoadTab($(this).data('tab')); });

    // Buka tab -> muat datanya (sekali), sinkronkan ?tab= di URL, buang ?status= milik tab sebelumnya.
    $('#crTabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
        var tab = $(this).data('crtab');
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('status');
        window.history.replaceState({}, '', url.toString());
        Object.keys(CR_TABS).forEach(crBulkReset); // seleksi bulk tidak dibawa antar tab
        crLoadTab(tab);
    });

    crLoadTab('{{ $activeTab }}');

    // Sinkron filter stepper status <-> URL (?status=) supaya tidak hilang setelah aksi (POST lalu redirect back()).
    $(document).on('click', '.wf-tl-step', function () {
        var $step = $(this);
        if (!$step.closest('[data-wf-table]').length) return;
        var url = new URL(window.location.href);
        if ($step.hasClass('wf-active')) {
            url.searchParams.set('status', $step.data('status'));
        } else {
            url.searchParams.delete('status');
        }
        window.history.replaceState({}, '', url.toString());
    });

    /* Refresh mengembalikan rentang tanggal ke bawaan (tanggal 1 s/d akhir bulan
       berjalan), jadi kalender ikut kembali ke bulan default. */
    $('#btnRefreshCR').on('click', function () {
        var url = new URL(window.location.href);
        ['date_from', 'date_to', 'from', 'range'].forEach(function (k) { url.searchParams.delete(k); });
        window.location.href = url.toString();
    });

    /* Tombol "Reset filter" pada keadaan kosong memakai ulang tombol Clear Filter tab-nya. */
    $(document).on('click', '.cr-empty-reset', function () {
        var $pane = $(this).closest('.cr-pane');
        $pane.find('.cr-search-input').val('').trigger('keyup');
        $pane.find('.cr-btn-clear').trigger('click');
    });

    /* ── FILTER TANGGAL — server-side (reload halaman dengan ?date_from=&date_to=) ──
    | Berlaku untuk semua tab; ?tab= ikut terbawa di URL. Pakai "date_from"/"date_to"
    | (bukan "from"/"to") karena "from" sudah dipakai link sidebar (?from=finance). */
    /* Ganti rentang tanggal tanpa memuat ulang halaman: URL disinkronkan,
       semua tab ditandai belum dimuat, lalu tab yang sedang terbuka diambil
       ulang lewat AJAX. Stepper & badge tab ikut diperbarui dari data meta
       yang dikirim server bersama tabelnya. */
    function applyDateRange(from, to, range) {
        if (!from || !to) { return; }
        CR_DATE_FROM = from;
        CR_DATE_TO   = to;

        var url = new URL(window.location.href);
        url.searchParams.delete('from');
        if (range) { url.searchParams.set('range', range); }
        else       { url.searchParams.delete('range'); }
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        window.history.replaceState({}, '', url.toString());

        Object.keys(CR_TABS).forEach(function (tab) {
            var cfg = CR_TABS[tab];
            if (cfg.table) {
                try { cfg.table.destroy(); } catch (e) {}
                cfg.table = null;
            }
            if (cfg.sel) cfg.sel.clear();
            crPane(tab).find('.cr-bulk-bar').addClass('d-none').removeClass('d-flex');
            cfg.loaded  = false;
            cfg.loading = false;
            crPane(tab).find('.cr-tab-host').empty();
        });

        crLoadTab(crCurrentTab());
    }

    /* Perbarui angka stepper & badge tab dari meta yang ikut terkirim di
       fragment tabel (jumlah per status untuk rentang tanggal baru). */
    function crSyncCounts(tab) {
        var $meta = crPane(tab).find('.cr-tab-meta').first();
        if (!$meta.length) return;

        var total  = parseInt($meta.attr('data-total'), 10) || 0;
        var counts = {};
        try { counts = JSON.parse($meta.attr('data-counts') || '{}') || {}; } catch (e) { counts = {}; }

        crPane(tab).find('.wf-tl-step').each(function () {
            var n = parseInt(counts[$(this).data('status')], 10) || 0;
            var $node = $(this).find('.wf-tl-node');
            var warna = 'badge-' + ($(this).data('wf-color') || 'secondary');
            $node.text(n)
                 .toggleClass('is-empty', n === 0)
                 .toggleClass(warna, n > 0);
        });

        $('#crTabs .nav-link[data-crtab="' + tab + '"] .badge').text(total.toLocaleString('id-ID'));
    }

    // Export mengikuti filter yang SEDANG AKTIF di tabel tab tsb (search, dropdown, stepper).
    // Tanpa filter tambahan -> export penuh rentang tanggal (bukan hanya baris ter-render/ter-cap).
    $(document).on('click', '.cr-btn-export', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        var cfg = CR_TABS[tab];
        var fallback = '{{ url("/budget-request/export") }}?date_from=' + encodeURIComponent(CR_DATE_FROM)
            + '&date_to=' + encodeURIComponent(CR_DATE_TO) + '&tab=' + encodeURIComponent(tab);

        try {
            var table = cfg && cfg.table;
            if (!table) { window.location.href = fallback; return; }

            // Kolom export = kolom yang AKTIF (terlihat) di tabel tab ini, urut sesuai tabel.
            var cols = [];
            table.columns().every(function () {
                var k = $(this.header()).data('col');
                if (k && this.visible()) cols.push(k);
            });
            fallback += cols.map(function (k) { return '&cols[]=' + encodeURIComponent(k); }).join('');

            var total    = table.rows().count();
            var filtered = table.rows({ search: 'applied' }).count();
            if (filtered === total) { window.location.href = fallback; return; }

            var ids = [];
            table.rows({ search: 'applied' }).every(function () {
                var id = $(this.node()).data('id');
                if (id) ids.push(id);
            });

            var $f = $('<form method="POST" action="' + '{{ url("/budget-request/export") }}' + '"></form>');
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            $f.append($('<input type="hidden" name="tab">').val(tab));
            cols.forEach(function (k) { $f.append($('<input type="hidden" name="cols[]">').val(k)); });
            ids.forEach(function (id) { $f.append($('<input type="hidden" name="ids[]">').val(id)); });
            $('body').append($f);
            $f[0].submit();
            $f.remove();
        } catch (err) {
            window.location.href = fallback;
        }
    });

    /* ── WARNA TOMBOL KONFIRMASI ──
       Diambil dari token desain, bukan biru/merah bawaan Bootstrap, supaya
       dialog konfirmasi memakai warna yang sama dengan tombol di halaman. */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    /* ── KEADAAN SIBUK ──
       Setiap aksi CR adalah POST biasa yang memuat ulang halaman. Tanpa
       penanda ini tidak ada umpan balik sama sekali antara klik dan halaman
       berganti, sehingga tombol mudah tertekan dua kali. */
    function sibukkan(form) {
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit',
        '#frmHeadApprove, #frmNotes, #frmFinanceVerify, #frmCeoApprove, #frmVerifyRealization',
        function () { sibukkan(this); });

    /* ── BULK APPROVE / REJECT TAHAP PENGAJUAN + BULK APPROVE TAHAP SETTLEMENT (Fase 4) ──
    | Tahap settlement (Settle, Settle Checked, Settle Verified, Settle Approved) hanya
    | punya Approve (budget-request.settlement-bulk-approve); tombol Reject disembunyikan.
    | Memakai endpoint yang sama dengan section Review di Todo List
    | (budget-request.review-bulk-approve / -reject). Seleksi hanya satu status
    | per aksi, maks CR_BULK_MAX; id & status yang terlihat dikirim sebagai
    | expected[id] supaya CR yang statusnya sudah berubah dilewati server.
    | Hak centang per baris ditentukan server (data-elig), JS hanya menguncinya. */
    var CR_BULK_MAX = 50;
    var CR_BULK_URL = {
        approve: '{{ route('budget-request.review-bulk-approve') }}',
        reject:  '{{ route('budget-request.review-bulk-reject') }}',
        settle:  '{{ route('budget-request.settlement-bulk-approve') }}'
    };
    var CR_BULK_STAGE = {
        SUBMITTED: { ke: 'CHECKED',  approve: 'Approve (Check)' },
        CHECKED:   { ke: 'VERIFIED', approve: 'Verify' },
        VERIFIED:  { ke: 'APPROVED', approve: 'Approve' },
        // Fase 4 — Settlement (hanya Approve).
        REALIZED:        { ke: 'SETTLE_CHECKED',  approve: 'Check Settle',   settle: true },
        SETTLE_CHECKED:  { ke: 'SETTLE_VERIFIED', approve: 'Verify Settle',  settle: true },
        SETTLE_VERIFIED: { ke: 'SETTLE_APPROVED / CLOSED', approve: 'Approve Settle', settle: true },
        SETTLE_APPROVED: { ke: 'SECOND_APPROVED', approve: 'Second Approve', settle: true }
    };

    function crCap(s) { return s ? String(s).split('_').map(function (w) { return w.charAt(0) + w.slice(1).toLowerCase(); }).join(' ') : ''; }
    function crBulkWarn() {
        if (window.tsToast) window.tsToast('warning', 'Maksimal ' + CR_BULK_MAX + ' CR per proses. Pilihan dibatalkan; persempit dengan filter lalu pilih lagi.');
    }
    function crBulkNodes(tab) {
        var cfg = CR_TABS[tab];
        return (cfg && cfg.table) ? $(cfg.table.rows().nodes()) : $();
    }
    function crBulkInfo(tr) {
        var $tr = $(tr);
        return {
            id:      String($tr.attr('data-id')),
            status:  $tr.attr('data-status'),
            amount:  parseInt($tr.attr('data-amount'), 10) || 0,
            nob:     $tr.attr('data-nob') === '1',
            takeout: $tr.attr('data-takeout') === '1'
        };
    }

    // Buang seleksi yang tidak lagi lolos search/filter (agar yang dikirim = yang terlihat).
    function crBulkPrune(cfg) {
        var ada = new Set();
        cfg.table.rows({ search: 'applied' }).every(function () { ada.add(String($(this.node()).attr('data-id'))); });
        cfg.sel.forEach(function (id) { if (!ada.has(id)) cfg.sel.delete(id); });
    }

    function crBulkRefresh(tab) {
        var cfg = CR_TABS[tab];
        if (!cfg || !cfg.sel || !cfg.table) return;
        var $pane = crPane(tab), jml = 0, total = 0, status = null;

        crBulkNodes(tab).each(function () {
            var inf = crBulkInfo(this);
            if (cfg.sel.has(inf.id)) { jml++; total += inf.amount; if (!status) status = inf.status; }
        });
        cfg.status = status;

        crBulkNodes(tab).each(function () {
            var $cb = $(this).find('.cr-check-row');
            if (!$cb.length) return;
            var inf = crBulkInfo(this), elig = $cb.attr('data-elig') === '1';
            var kunci = !!status && inf.status !== status;
            $cb.prop('checked', cfg.sel.has(inf.id));
            if (!elig) return; // alasan tooltip dari server
            $cb.prop('disabled', kunci);
            if (kunci) $cb.attr('title', 'Satu proses hanya untuk satu tahap (' + crCap(status) + '). Baris ini berstatus ' + crCap(inf.status) + '.');
            else $cb.removeAttr('title');
        });

        // Header "pilih semua": hanya baris eligible berstatus sama yang lolos filter.
        var cand = 0, dipilih = 0;
        if (status) {
            cfg.table.rows({ search: 'applied' }).every(function () {
                var inf = crBulkInfo(this.node());
                if ($(this.node()).find('.cr-check-row').attr('data-elig') !== '1' || inf.status !== status) return;
                cand++;
                if (cfg.sel.has(inf.id)) dipilih++;
            });
        }
        var allEl = $pane.find('.cr-check-all')[0];
        if (allEl) {
            allEl.checked = cand > 0 && dipilih === cand;
            allEl.indeterminate = dipilih > 0 && dipilih < cand;
        }

        var $bar = $pane.find('.cr-bulk-bar');
        $bar.toggleClass('d-none', jml === 0).toggleClass('d-flex', jml > 0);
        $bar.find('.cr-bulk-info').text(jml + ' dipilih · total Rp ' + crMoney(total));
        $bar.find('.cr-bulk-approve-label').text(status && CR_BULK_STAGE[status] ? CR_BULK_STAGE[status].approve : 'Approve');
        // Tahap settlement tidak punya bulk Reject.
        $bar.find('.cr-bulk-reject').toggleClass('d-none', !!(status && CR_BULK_STAGE[status] && CR_BULK_STAGE[status].settle));
    }

    function crBulkInit(tab) {
        var cfg = CR_TABS[tab];
        if (!cfg.table || !$('#' + cfg.id).find('.cr-check-all').length) return;
        cfg.sel = new Set();
        cfg.status = null;
        cfg.table.on('draw', function () { crBulkPrune(cfg); crBulkRefresh(tab); });
    }

    function crBulkReset(tab) {
        var cfg = CR_TABS[tab];
        if (!cfg || !cfg.sel) return;
        cfg.sel.clear();
        crBulkRefresh(tab);
    }

    $(document).on('change', '.cr-check-row', function () {
        var tab = $(this).closest('.cr-tbl').data('tab'), cfg = CR_TABS[tab];
        if (!cfg || !cfg.sel) return;
        var id = String(this.value);
        if (this.checked) {
            if (cfg.sel.size >= CR_BULK_MAX) { this.checked = false; crBulkWarn(); return; }
            cfg.sel.add(id);
        } else {
            cfg.sel.delete(id);
        }
        crBulkRefresh(tab);
    });

    $(document).on('change', '.cr-check-all', function () {
        var tab = $(this).closest('.cr-tbl').data('tab'), cfg = CR_TABS[tab];
        if (!cfg || !cfg.sel) return;
        if (!this.checked) { cfg.sel.clear(); crBulkRefresh(tab); return; }

        var target = cfg.status, cand = [];
        cfg.table.rows({ search: 'applied' }).every(function () {
            if ($(this.node()).find('.cr-check-row').attr('data-elig') !== '1') return;
            var inf = crBulkInfo(this.node());
            if (!target) target = inf.status;
            if (inf.status === target) cand.push(inf.id);
        });
        // Lebih dari batas: seluruh pilihan dibatalkan (bukan dipotong), supaya
        // tidak ada CR terpilih yang tak disadari user.
        var gabungan = new Set(cfg.sel);
        cand.forEach(function (id) { gabungan.add(id); });
        if (gabungan.size > CR_BULK_MAX) {
            cfg.sel.clear();
            crBulkWarn();
            crBulkRefresh(tab);
            return;
        }
        gabungan.forEach(function (id) { cfg.sel.add(id); });
        crBulkRefresh(tab);
    });

    $(document).on('click', '.cr-bulk-clear', function () {
        crBulkReset($(this).closest('.cr-bulk-bar').data('tab'));
    });

    $(document).on('click', '.cr-bulk-approve, .cr-bulk-reject', function () {
        var $btn = $(this), reject = $btn.hasClass('cr-bulk-reject');
        var tab = $btn.closest('.cr-bulk-bar').data('tab'), cfg = CR_TABS[tab];
        if (!cfg || !cfg.sel || cfg.submitting) return;

        var rows = [];
        crBulkNodes(tab).each(function () {
            var inf = crBulkInfo(this);
            if (cfg.sel.has(inf.id)) rows.push(inf);
        });
        if (!rows.length) return;

        var dari = rows[0].status, tahap = CR_BULK_STAGE[dari];
        if (!tahap || (reject && tahap.settle)) return;
        var total = 0, nob = 0, tk = 0;
        rows.forEach(function (r) { total += r.amount; if (r.nob) nob++; if (r.takeout) tk++; });

        var html = '<div style="text-align:left;font-size:13px;line-height:1.7;">'
            + '<div><strong>' + rows.length + ' CR</strong>: ' + crCap(dari) + ' &rarr; ' + (reject ? 'Rejected' : crCap(tahap.ke)) + '</div>'
            + '<div>Total: <strong>Rp ' + crMoney(total) + '</strong></div>'
            + (nob ? '<div>Berstatus NOB (termasuk NOB sub-item): <strong>' + nob + ' CR</strong></div>' : '')
            + (tk ? '<div>Punya item takeout: <strong>' + tk + ' CR</strong></div>' : '')
            + '</div>';

        var opsi = reject
            ? { title: 'Reject ' + rows.length + ' CR?', icon: 'warning', html: html + '<div class="mt-2" style="text-align:left;font-size:12px;">Alasan penolakan wajib diisi:</div>',
                input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan penolakan' },
                inputValidator: function (v) { if (!v || !v.trim()) return 'Alasan penolakan wajib diisi.'; },
                confirmButtonColor: WARNA_BAHAYA, confirmButtonText: 'Ya, Reject' }
            : { title: tahap.approve + ' ' + rows.length + ' CR?', icon: 'question', html: html,
                confirmButtonColor: WARNA_UTAMA, confirmButtonText: 'Ya, ' + tahap.approve };
        opsi.showCancelButton = true;
        opsi.cancelButtonColor = WARNA_BATAL;
        opsi.cancelButtonText = 'Batal';

        Swal.fire(opsi).then(function (r) {
            if (!r.isConfirmed || cfg.submitting) return;
            cfg.submitting = true;

            var $f = $('<form method="POST"></form>').attr('action', reject ? CR_BULK_URL.reject : (tahap.settle ? CR_BULK_URL.settle : CR_BULK_URL.approve));
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            rows.forEach(function (x) {
                $f.append($('<input type="hidden" name="ids[]">').val(x.id));
                $f.append($('<input type="hidden">').attr('name', 'expected[' + x.id + ']').val(x.status));
            });
            if (reject) $f.append($('<input type="hidden" name="notes">').val(r.value));

            crPane(tab).find('.cr-bulk-bar button').prop('disabled', true);
            if (window.TesysUI && TesysUI.busy) TesysUI.busy($btn[0], true);
            // Redirect back() membawa kembali ke URL halaman ini (termasuk ?tab=).
            $('body').append($f);
            $f[0].submit();
        });
    });

    // Kembali lewat tombol Back (bfcache): halaman dipulihkan beku, jadi kunci kirim
    // dan tombol bar yang sempat dinonaktifkan harus dilepas lagi.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        Object.keys(CR_TABS).forEach(function (tab) {
            var cfg = CR_TABS[tab];
            if (!cfg || !cfg.sel) return;
            cfg.submitting = false;
            crPane(tab).find('.cr-bulk-bar button').prop('disabled', false).each(function () {
                if (window.TesysUI && TesysUI.busy) TesysUI.busy(this, false);
            });
        });
    });

    /* ── SUBMIT CONFIRM ── */
    $(document).on('submit', '.frmSubmitCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit CR?', text:'CR akan dikirim ke Head untuk direview.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_UTAMA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    $(document).on('submit', '.frmDeleteCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus CR?', icon:'warning', showCancelButton:true, confirmButtonColor:WARNA_BAHAYA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* ── HEAD APPROVE ── */
    $(document).on('click', '.btn-head-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#headApproveText').text('Approve CR ' + code + '? Status akan menjadi CHECKED dan diteruskan ke Finance.');
        $('#frmHeadApprove').attr('action', '/budget-request/' + id + '/head-approve');
        $('#frmHeadApprove textarea').val('');
        $('#mdlHeadApprove').modal('show');
    });

    /* ── CEO APPROVE ── */
    $(document).on('click', '.btn-ceo-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#ceoApproveText').text('Approve CR ' + code + '? Status akan menjadi APPROVED dan siap masuk batch pembayaran.');
        $('#frmCeoApprove').attr('action', '/budget-request/' + id + '/ceo-approve');
        $('#frmCeoApprove textarea').val('');
        $('#mdlCeoApprove').modal('show');
    });

    /* ── GENERIC NOTES MODAL ── */
    function openNotesModal(id, action, title, label, btnClass, btnText, alertMsg) {
        $('#mdlNotesTitle').html('<i class="fas fa-exclamation-triangle mr-2" style="color:var(--accent);"></i>' + title);
        $('#mdlNotesLabel').html(label + ' <span class="text-danger">*</span>');
        if (alertMsg) { $('#mdlNotesAlert').html(alertMsg).show(); } else { $('#mdlNotesAlert').hide(); }
        $('#frmNotes').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmNotes textarea').val('');
        $('#mdlNotesSubmitBtn').text(btnText).removeClass('btn-danger btn-warning').addClass(btnClass);
        $('#mdlNotes').modal('show');
    }

    $(document).on('click', '.btn-head-reject', function () {
        openNotesModal($(this).data('id'), 'head-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-finance-reject', function () {
        openNotesModal($(this).data('id'), 'finance-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-ceo-reject', function () {
        openNotesModal($(this).data('id'), 'ceo-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-manager-revise', function () {
        openNotesModal($(this).data('id'), 'manager-revise', 'Revise CR → Draft', 'Catatan Revisi', 'btn-warning', 'Revise',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>DRAFT</strong> untuk diperbaiki.');
    });
    $(document).on('click', '.btn-manager-cancel', function () {
        openNotesModal($(this).data('id'), 'manager-cancel', 'Cancel CR', 'Alasan Pembatalan', 'btn-dark', 'Cancel',
            '<i class="fas fa-exclamation-triangle mr-1"></i> CR akan menjadi <strong>CANCELLED</strong> secara permanen dan tidak bisa diproses lagi.');
    });
    /* ── TAKEOUT / RESTORE SUB-ITEM ── */
    function submitPost(url) {
        var $f = $('<form method="POST" action="' + url + '"></form>').append(
            '<input type="hidden" name="_token" value="' + $('meta[name="csrf-token"]').attr('content') + '">'
        );
        $('body').append($f); $f.submit();
    }

    $(document).on('click', '.btn-takeout-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail'), desc = $(this).data('desc') || 'item ini';
        openNotesModal(crId, 'detail/' + detailId + '/takeout', 'Takeout Item', 'Alasan Takeout', 'btn-danger', 'Takeout',
            '<i class="fas fa-info-circle mr-1"></i> Item "<strong>' + esc(desc) + '</strong>" tidak akan dibawa proses. Nilai CR otomatis berkurang. Jika semua item di-takeout, CR menjadi <strong>REJECTED</strong>.');
    });

    $(document).on('click', '.btn-restore-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail');
        Swal.fire({
            title: 'Kembalikan item?', text: 'Item akan ikut diproses lagi dan nilai CR bertambah.',
            icon: 'question', showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Kembalikan', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) submitPost('/budget-request/' + crId + '/detail/' + detailId + '/restore');
        });
    });

    /* ── FINANCE VERIFY ── */
    $(document).on('click', '.btn-finance-verify', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#financeVerifyText').text('Verifikasi CR ' + code + '? Status akan menjadi VERIFIED dan menunggu approval CEO.');
        $('#frmFinanceVerify').attr('action', '/budget-request/' + id + '/finance-verify');
        $('#frmFinanceVerify textarea').val('');
        $('#mdlFinanceVerify').modal('show');
    });

    /* ── SETTLEMENT — HEAD CHECK / FINANCE VERIFY / CEO CLOSE (modal approve
       yang sama, action & teks disesuaikan per tahap) ── */
    function openSettlementApproveModal(id, code, action, text, btnText) {
        $('#verifyRealText').text(text);
        $('#frmVerifyRealization').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmVerifyRealization textarea').val('');
        $('#btnVerifyRealSubmit').html('<i class="fas fa-check-double mr-1"></i> ' + btnText);
        $('#mdlVerifyRealization').modal('show');
    }

    $(document).on('click', '.btn-head-check-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'head-check-settlement',
            'Check Settlement CR ' + code + '? Status akan berubah menjadi SETTLE CHECKED, menunggu verifikasi Finance.',
            'Check → Settle Checked');
    });
    $(document).on('click', '.btn-finance-verify-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'finance-verify-settlement',
            'Verifikasi Settlement CR ' + code + '? Status akan berubah menjadi SETTLE VERIFIED. CR dengan Expense Claim lanjut ke Settle Approve, selebihnya langsung menunggu Close.',
            'Verify → Settle Verified');
    });
    $(document).on('click', '.btn-settle-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'settle-approve',
            'Setujui Expense Claim CR ' + code + '? Pengajuan lanjut ke antrean Second Approve Approver.',
            'Approve → Second Approve');
    });
    $(document).on('click', '.btn-second-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'second-approve',
            'Setujui Expense Claim CR ' + code + '? Pengajuan masuk antrean Buat Batch Baru Treasury, jadi Process setelah dibatch, dan CLOSED otomatis setelah dibayar.',
            'Approve → Treasury');
    });
    $(document).on('click', '.btn-settle-reject', function () {
        openNotesModal($(this).data('id'), 'settle-reject', 'Reject Expense Claim', 'Alasan', 'btn-danger', 'Reject → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR kembali ke <strong>PAID</strong> untuk disettle ulang. Expense Claim yang sudah terbentuk dibatalkan.');
    });
    $(document).on('click', '.btn-second-reject', function () {
        openNotesModal($(this).data('id'), 'second-reject', 'Reject Expense Claim', 'Alasan', 'btn-danger', 'Reject → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR kembali ke <strong>PAID</strong> untuk disettle ulang. Expense Claim yang sudah terbentuk dibatalkan.');
    });
    $(document).on('click', '.btn-ceo-close-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'ceo-close-settlement',
            'Close Settlement CR ' + code + '? Status akan berubah menjadi CLOSED.',
            'Close');
    });

    $(document).on('click', '.btn-head-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'head-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });
    $(document).on('click', '.btn-manager-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'manager-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });
    $(document).on('click', '.btn-finance-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'finance-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });

    /* ── DRAWER DETAIL ── */
    // Set badge angka pada tab: tampil bila > 0, sembunyi bila 0
    function setTabCount(sel, n) {
        var el = $(sel);
        if (n && n > 0) { el.text(n).show(); }
        else { el.text('').hide(); }
    }

    /* ── PENAMPIL LAMPIRAN — pemicu klik kartu/tombol lampiran (komponen bersama) ── */
    (function () {
        // Penampil lampiran = komponen bersama (public/js/tesys-evidence-stage.js).
        function openViewer(daftar, mulai) {
            TesysEvidenceStage.open((daftar || []).map(function (a) {
                return { url: a.full, name: a.name, is_image: a.is_image, is_pdf: a.is_pdf };
            }), mulai);
        }

        // Trigger: klik kartu lampiran (Info, Request, Settlement). Seluruh
        // lampiran satu grup masuk carousel; gambar jadi slide, file lain kartu nama.
        $(document).on('click', '.cr-att-card', function () {
            var daftar = (window.__crAttGroups && window.__crAttGroups[$(this).data('group')]) || [];
            openViewer(daftar.map(function (a) {
                return { full: '/storage/' + a.path, name: a.original_name, is_image: !!a.is_image, is_pdf: !!a.is_pdf };
            }), parseInt($(this).data('idx'), 10) || 0);
        });

        /* Membuka lampiran satu baris. Gambar masuk carousel; PDF dan dokumen lain
           TIDAK bisa ditampilkan di carousel, jadi begitu ada file non-gambar
           seluruh lampiran dirinci dalam satu daftar: gambar membuka carousel,
           PDF/dokumen dibuka di tab baru. Sebelumnya file non-gambar ikut hilang
           kalau baris itu juga punya gambar. */
        function bukaLampiran(atts) {
            atts = atts || [];
            if (!atts.length) return;

            // Semua lampiran masuk satu carousel dengan urutan seperti saat
            // diunggah; yang bukan gambar dirender sebagai kartu logo + nama file.
            openViewer(atts.map(function (a) {
                return {
                    full:     '/storage/' + a.path,
                    name:     a.original_name,
                    is_image: !!a.is_image,
                    is_pdf:   !!a.is_pdf
                };
            }), 0);
        }

        // Tombol View lampiran per item pada tab Request.
        $(document).on('click', '.btn-view-detail-att', function () {
            var id = $(this).data('detail');
            bukaLampiran((window.__crDetailAtt && window.__crDetailAtt[id]) || []);
        });

        /* Buka/tutup grup Chart of Account pada tab Request. */
        $(document).on('click', '#crDetailBody .cr-group-row', function () {
            var $sub = $('#crDetailBody .' + $(this).data('target'));
            var buka = $sub.first().is(':hidden');
            $sub.toggle(buka);
            $(this).find('.cr-caret').toggleClass('rotated', buka);
        });
        /* Grup yang sama untuk tab Settlement. */
        $(document).on('click', '#crRealizationBody .cr-group-row', function () {
            var $sub = $('#crRealizationBody .' + $(this).data('target'));
            var buka = $sub.first().is(':hidden');
            $sub.toggle(buka);
            $(this).find('.cr-caret').toggleClass('rotated', buka);
        });

        /* Satu tombol Expand/Collapse All: label & ikonnya berganti mengikuti
           keadaan. data-body menunjuk tbody yang digrup (Request/Settlement). */
        $(document).on('click', '.cr-toggle-all', function () {
            var $btn = $(this);
            var sel = '#' + $btn.data('body');
            var expand = $btn.data('expanded') ? false : true;
            $(sel + ' .cr-group-item').toggle(expand);
            $(sel + ' .cr-caret').toggleClass('rotated', expand);
            $btn.data('expanded', expand ? 1 : 0)
                .html(expand
                    ? '<i class="fas fa-angle-double-up mr-1"></i>Collapse All'
                    : '<i class="fas fa-angle-double-down mr-1"></i>Expand All');
        });

        // Tombol View lampiran baris Settlement — pola sama dengan tab Request.
        $(document).on('click', '.btn-view-real-att', function () {
            bukaLampiran((window.__crRealAtt && window.__crRealAtt[$(this).data('row')]) || []);
        });

    })();

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }

    /* Keadaan kosong ringkas untuk ruang sempit di dalam modal — bentuknya
       sama dengan komponen keadaan kosong yang dipakai tabel dan drawer. */
    function kosongRingkas(pesan) {
        return '<div class="ts-empty ts-empty-compact"><p class="ts-empty-msg">' + esc(pesan) + '</p></div>';
    }

    /* Memisah label gabungan "Sub COA — Deskripsi" menjadi dua bagian supaya
       tab Request & Settlement bisa menampilkannya dua baris. Pemisahnya bisa
       hyphen atau em/en dash yang diapit spasi. */
    function splitCoaLabel(teks) {
        var s = (teks || '').trim();
        var m = s.split(/\s+[–—-]\s+/);
        if (m.length >= 2) return { sub: m[0], rest: m.slice(1).join(' — ') };
        return { sub: s, rest: '' };
    }

    /* Kartu lampiran: gambar tampil sebagai thumbnail, file lain sebagai ikon.
       Klik kartu membuka carousel untuk seluruh lampiran satu grup. */
    window.__crAttGroups = {};
    function renderAttGrid(atts, grup) {
        if (!atts || !atts.length) return kosongRingkas('Belum ada lampiran.');
        window.__crAttGroups[grup] = atts;
        return atts.map(function (a, i) {
            var url = '/storage/' + a.path;
            var thumb = a.is_image
                ? '<img loading="lazy" src="' + escAttr(url) + '" alt="" class="cr-att-thumb">'
                : '<span class="cr-att-icon"><i class="fas ' + (a.is_pdf ? 'fa-file-pdf' : 'fa-file-alt') + '"></i></span>';
            return '<button type="button" class="cr-att-card" data-group="' + grup + '" data-idx="' + i + '" title="Buka lampiran">'
                + thumb
                + '<span class="cr-att-name" style="display:block;">' + esc(a.original_name) + '</span>'
                + (a.size_formatted ? '<span class="cr-att-meta">' + esc(a.size_formatted) + '</span>' : '')
                + '</button>';
        }).join('');
    }

    /* Mengisi tab Request, Settlement & Log drawer dari payload detail-json. */
    function isiPanelCR(d, tab) {
            // Detail tab
            setTabCount('#crDetailCount', d.details.length);
            var canModerate = !!d.can_moderate_items;
            window.__crDetailAtt = {};
            var anyAtt = d.details.some(function (x) { return x.attachments && x.attachments.length; });
            var showActionCol = canModerate || anyAtt;
            $('#crDetailActionTh, .crDetailActionCell').toggleClass('d-none', !showActionCol);
            /* Rincian dikelompokkan per Chart of Account (pola tab Budget di
               Budgeting): satu baris grup berisi nama akun + totalnya, baris
               itemnya disembunyikan sampai grupnya dibuka. Urutan grup mengikuti
               kemunculan pertama akun pada data, jadi tidak mengacak urutan asli. */
            var dHtml = '', grand = 0, nomor = 0, grupIdx = 0;
            var urutanAkun = [], perAkun = {};
            d.details.forEach(function (det) {
                var acc = det.account || '-';
                if (!perAkun[acc]) { perAkun[acc] = []; urutanAkun.push(acc); }
                perAkun[acc].push(det);
            });

            urutanAkun.forEach(function (acc) {
              var items = perAkun[acc];
              var grupClass = 'cr-dgrp-' + (grupIdx++);
              var grupTotal = items.reduce(function (t, x) { return t + (x.is_takeout ? 0 : x.total); }, 0);

              dHtml += '<tr class="cr-group-row" data-target="' + grupClass + '" style="cursor:pointer;">' +
                  '<td style="font-weight:700;">' +
                      '<i class="fas fa-chevron-right mr-2 cr-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>' +
                      esc(acc) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>' +
                  '</td>' +
                  '<td></td><td></td>' +
                  '<td class="text-right" style="font-weight:700;">' + grupTotal.toLocaleString('id-ID') + '</td>' +
                  (showActionCol ? '<td></td>' : '') +
              '</tr>';

              items.forEach(function(det) {
                var i = nomor++;
                var isNob    = !!det.is_nob; // kolom, bukan tebakan dari teks catatan
                var isTakeout = !!det.is_takeout;
                if (!isTakeout) grand += det.total;

                // Hanya isi CSS-nya (tanpa atribut), karena baris item juga perlu
                // display:none saat grupnya tertutup.
                var rowStyle = isTakeout
                    ? 'color:var(--text-faint);background:var(--surface-2);'
                    : (isNob ? 'color:var(--danger);' : '');
                var strike = isTakeout ? 'text-decoration:line-through;' : '';

                var takeoutBadge = isTakeout
                    ? ' <span class="badge badge-warning" style="font-size:9px;" title="' + escAttr(det.takeout_notes || '') + '">Takeout &middot; ' + esc(det.takeout_stage_label || '-') + '</span>'
                    : '';

                var atts = det.attachments || [];
                window.__crDetailAtt[det.id] = atts;
                var attBadge = atts.length
                    ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + atts.length + '</span>'
                    : '';

                var btnStyle = 'white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;';
                var actionInner = '';
                if (atts.length) {
                    actionInner += '<button type="button" class="btn btn-info btn-view-detail-att mr-1" style="' + btnStyle + '" data-detail="' + det.id + '" title="Lihat lampiran"><i class="fas fa-eye"></i></button>';
                }
                if (canModerate) {
                    actionInner += isTakeout
                        ? '<button type="button" class="btn btn-outline-secondary btn-restore-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + det.id + '" title="Kembalikan item"><i class="fas fa-undo"></i></button>'
                        : '<button type="button" class="btn btn-outline-danger btn-takeout-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + det.id + '" data-desc="' + escAttr(det.description || '') + '" title="Takeout item"><i class="fas fa-cut"></i></button>';
                }
                var actionCell = showActionCol ? '<td class="text-center text-nowrap">' + actionInner + '</td>' : '';

                // Dua baris: Sub COA (+ jumlah lampiran) di atas, deskripsi di bawah.
                // Sub COA dari budget detail dipakai langsung (tebal); catatan baris
                // jadi deskripsi kecil di bawahnya. Tanpa sub_coa, fallback ke
                // pemisahan label seperti sebelumnya.
                var belah = det.sub_coa
                    ? { sub: det.sub_coa, rest: det.notes || '' }
                    : splitCoaLabel(det.item_label || det.description || '-');
                dHtml += '<tr class="' + grupClass + ' cr-group-item" style="display:none;' + rowStyle + '">' +
                    '<td class="cr-indent" style="' + strike + '">' +
                        '<div style="font-weight:600;">' + esc(belah.sub) + takeoutBadge + attBadge + '</div>' +
                        (belah.rest ? '<div style="color:var(--text-faint);font-size:11px;margin-top:2px;">' + esc(belah.rest) + '</div>' : '') +
                        (isTakeout && det.takeout_notes ? '<small class="text-muted" style="text-decoration:none;display:block;margin-top:2px;">Alasan: ' + esc(det.takeout_notes) + '</small>' : '') +
                    '</td>' +
                    '<td class="text-right" style="' + strike + '">' + det.amount_formatted + '</td>' +
                    '<td class="text-center" style="' + strike + '">' + det.qty + '</td>' +
                    '<td class="text-right" style="' + strike + '"><strong>' + det.total_formatted + '</strong></td>' +
                    actionCell +
                '</tr>';
              });
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="6" class="text-center text-muted py-3">No item detail yet.</td></tr>');
            $('#crDetailTotal').text(grand.toLocaleString('id-ID'));
            $('#btnToggleCrDetail').data('expanded', 0).html('<i class="fas fa-angle-double-down mr-1"></i>Expand All');

            /* Tab Settlement hanya berlaku untuk Cash Advance — jenis pengajuan
               lain (Cost Payment) tidak punya tahap settlement sama sekali, jadi
               tabnya disembunyikan, bukan ditampilkan kosong. */
            var $tabSettle = $('#crDetailTabs a[href="#crPaneRealization"]').closest('li');
            if (d.is_cash_advance) {
                $tabSettle.show();
            } else {
                $tabSettle.hide();
                // Kalau tab ini sedang aktif dari CR sebelumnya, balik ke Info.
                if ($('#crPaneRealization').hasClass('active')) {
                    $('#crDetailTabs a[href="#crPaneInfo"]').tab('show');
                }
            }

            // Realization tab
            setTabCount('#crRealizationCount', d.realizations.length);
            if (d.is_cash_advance && d.realizations.length) {
                $('#crNoRealization').hide();
                $('#crRealizationWrapper').show();
                var rHtml = '', rGrand = 0, rNomor = 0, rGrupIdx = 0;
                // Lampiran per baris tidak lagi digelar sebagai grid thumbnail —
                // cukup satu ikon "view" di kolom Action yang membuka viewer.
                window.__crRealAtt = {};

                // Var = Request - Settlement (angka yang ditampilkan di kolom
                // Request & Settlement baris itu). Hijau = sisa, merah = lebih.
                function fmtVar(n) {
                    n = Math.round(n || 0);
                    if (n === 0) return '<span class="text-muted">0</span>';
                    var warna = n > 0 ? 'var(--success)' : 'var(--danger)';
                    return '<span style="color:' + warna + ';font-weight:600;">'
                        + (n > 0 ? '' : '-') + Math.abs(n).toLocaleString('id-ID') + '</span>';
                }

                // Total Request / Settlement / Var di footer Rincian Settlement — angka dari
                // server (settlement_comparison): Request = item pengajuan, tidak dijumlah dari
                // total_cr per baris (bisa berlipat bila satu item dipecah jadi beberapa baris).
                var cmp = d.settlement_comparison || { rows: [] };
                $('#crRealizationReqTotal').text(cmp.request_formatted || '0');
                $('#crRealizationVarTotal').html(fmtVar(cmp.variance));

                // Rincian Settlement: Request item asal hanya ditulis di baris
                // PERTAMA item itu (baris pecahan berikutnya "-"). Var-nya =
                // Request item - jumlah SELURUH baris settlement item tersebut,
                // jadi ditulis di baris TERAKHIR item (setelah semua pecahan
                // terlihat), bukan di baris pertama yang angkanya belum lengkap.
                var settlePerItem = {}, sudahTampil = {}, barisTerakhir = {};
                d.realizations.forEach(function (r) {
                    if (r.request_detail_id) {
                        settlePerItem[r.request_detail_id] = (settlePerItem[r.request_detail_id] || 0) + (r.actual || 0);
                        barisTerakhir[r.request_detail_id] = r;
                    }
                });
                d.realizations.forEach(function (r) {
                    r._reqShown = null;
                    r._varShown = null;
                    if (r.request_detail_id && !sudahTampil[r.request_detail_id]) {
                        sudahTampil[r.request_detail_id] = true;
                        r._reqShown = r.total_cr || 0;
                    }
                    if (r.request_detail_id && barisTerakhir[r.request_detail_id] === r) {
                        r._varShown = (r.total_cr || 0) - (settlePerItem[r.request_detail_id] || 0);
                    }
                });

                // Dikelompokkan per Chart of Account, pola yang sama dengan tab Request.
                var rUrutan = [], rPerAkun = {};
                d.realizations.forEach(function (r) {
                    var acc = r.account || '-';
                    if (!rPerAkun[acc]) { rPerAkun[acc] = []; rUrutan.push(acc); }
                    rPerAkun[acc].push(r);
                });

                rUrutan.forEach(function (acc) {
                  var items = rPerAkun[acc];
                  var grupClass = 'cr-rgrp-' + (rGrupIdx++);
                  var grupReq    = items.reduce(function (t, x) { return t + (x._reqShown || 0); }, 0);
                  var grupSettle = items.reduce(function (t, x) { return t + (x.actual || 0); }, 0);

                  rHtml += '<tr class="cr-group-row" data-target="' + grupClass + '" style="cursor:pointer;">'
                      + '<td style="font-weight:700;">'
                          + '<i class="fas fa-chevron-right mr-2 cr-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>'
                          + esc(acc) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>'
                      + '</td>'
                      + '<td class="text-right" style="font-weight:700;">' + grupReq.toLocaleString('id-ID') + '</td>'
                      + '<td class="text-right" style="font-weight:700;">' + grupSettle.toLocaleString('id-ID') + '</td>'
                      + '<td class="text-right" style="font-weight:700;">' + fmtVar(grupReq - grupSettle) + '</td>'
                      + '<td></td>'
                      + '</tr>';

                  items.forEach(function (r) {
                    var i = rNomor++;
                    rGrand += r.total;
                    var itemAtts = r.attachments || [];
                    window.__crRealAtt[i] = itemAtts;
                    var aksi = itemAtts.length
                        ? '<button type="button" class="btn btn-info btn-view-real-att" '
                          + 'style="white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;" '
                          + 'data-row="' + i + '" title="Lihat lampiran item (' + itemAtts.length + ')">'
                          + '<i class="fas fa-eye"></i></button>'
                        : '<span class="text-muted" style="font-size:11px;">-</span>';

                    // Kolom pertama digabung: Sub COA (+lampiran), lalu deskripsi
                    // request dan deskripsi settlement masing-masing satu baris.
                    var subCoa   = r.sub_coa || r.des_request || r.cr_item || '-';
                    var reqDesc  = r.des_request || r.cr_item || '-';
                    var setDesc  = r.des_settle || '-';
                    var noteDesc = r.change_note || '-';
                    var attR     = itemAtts.length
                        ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + itemAtts.length + '</span>'
                        : '';
                    // Baris tambahan / pindah Sub COA (Settlement Custom COA).
                    var asal = r.is_added
                        ? ' <span class="badge badge-info" style="font-size:9px;">Baris tambahan</span>'
                        : (r.is_moved ? ' <span class="badge badge-warning" style="font-size:9px;">Pindah dari: ' + esc(r.origin_label || '-') + '</span>' : '');

                    rHtml += '<tr class="' + grupClass + ' cr-group-item" style="display:none;">'
                        + '<td class="cr-indent">'
                            + '<div style="font-weight:600;">' + esc(subCoa) + attR + asal + '</div>'
                            + '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Request:</span> ' + esc(reqDesc) + '</div>'
                            + '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Settle:</span> ' + esc(setDesc) + '</div>'
                            + '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Note:</span> ' + esc(noteDesc) + '</div>'
                        + '</td>'
                        + '<td class="text-right">' + (r._reqShown !== null ? (r._reqShown || 0).toLocaleString('id-ID') : '<span class="text-muted">-</span>') + '</td>'
                        + '<td class="text-right"><strong>' + r.actual_formatted + '</strong></td>'
                        + '<td class="text-right">' + (r._varShown !== null ? fmtVar(r._varShown) : (r.is_added ? fmtVar(-(r.actual || 0)) : '<span class="text-muted">-</span>')) + '</td>'
                        + '<td class="text-center">' + aksi + '</td>'
                        + '</tr>';
                  });
                });
                $('#crRealizationBody').html(rHtml);
                $('#crRealizationTotal').text(rGrand.toLocaleString('id-ID'));
                $('#btnToggleCrReal').data('expanded', 0).html('<i class="fas fa-angle-double-down mr-1"></i>Expand All');
                $('#crRealizationAtts').html(renderAttGrid(d.realization_attachments, 'real'));

                // Second Payment — cuma tampil kalau Settlement pernah melebihi
                // nilai pengajuan awal (ada baris di second_payments).
                var sp = d.second_payments || [];
                if (sp.length) {
                    var spHtml = '<h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">' +
                        '<i class="fas fa-hand-holding-usd mr-1"></i> Second Payment</h6>' +
                        '<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                        '<tbody>' +
                        '<tr><td>Original Request</td><td class="text-right"><strong>' + esc(d.amount_formatted) + '</strong></td></tr>' +
                        '<tr><td>Settlement</td><td class="text-right"><strong>' + esc(d.total_realization_formatted) + '</strong></td></tr>' +
                        sp.map(function (p) {
                            // Pembayarannya lewat batch Treasury — tanpa kode
                            // batch, "Pending" saja tidak memberi tahu apa pun
                            // tentang di mana uangnya sekarang.
                            var badge;
                            if (p.status === 'PAID') {
                                badge = '<span class="badge badge-success">Paid' + (p.paid_at ? ' — ' + esc(p.paid_at) : '') + '</span>'
                                      + (p.batch_code ? ' <span class="badge badge-light border">' + esc(p.batch_code) + '</span>' : '');
                            } else if (p.batch_code) {
                                badge = '<span class="badge badge-info">Menunggu pembayaran batch ' + esc(p.batch_code) + '</span>';
                            } else {
                                badge = '<span class="badge badge-warning">Belum masuk batch Treasury</span>';
                            }
                            return '<tr><td>Additional Payment</td><td class="text-right"><strong>' + esc(p.amount_formatted) + '</strong> ' + badge + '</td></tr>';
                        }).join('') +
                        '</tbody></table>';
                    $('#crSecondPaymentBox').html(spHtml).show();
                } else {
                    $('#crSecondPaymentBox').hide();
                }
            } else {
                $('#crNoRealization').show();
                $('#crRealizationWrapper').hide();
                $('#crSecondPaymentBox').hide();
            }

            // Attachment (di bawah tabel Request)
            var atts = d.attachments || [];
            setTabCount('#crAttCount', atts.length);
            $('#crAttBody').html(renderAttGrid(atts, 'req'));

            // Logs tab — gaya chat, sama persis dengan tab Log Purchase Order:
            // log dari user yang sedang login rata kanan ("mine"), lainnya rata
            // kiri, avatar inisial dari nama actor. Tanpa nama role.
            var logHtml = '';
            (d.logs || []).slice().reverse().forEach(function (log) {
                logHtml += renderLogRow(log);
            });
            $('#crLogBody').html(logHtml || kosongRingkas('Belum ada riwayat persetujuan.'));
            // Kotak catatan hanya selama CR belum Closed (server menolak juga).
            var bisaCatat = d.can_post_log_note !== false;
            $('#crLogForm').prop('hidden', !bisaCatat).data('id', d.id);
            $('#crLogClosed').prop('hidden', bisaCatat);
            $('#crLogInput').val('');
            if ($('#crPaneLogs').hasClass('active')) aturTinggiLog(true);

            // Buka tab tertentu bila diminta pemanggil (mis. klik nilai Request ->
            // tab Request, klik badge Status -> tab Log) — default tab pertama (Info).
            var $targetTab = tab ? $('#crDetailTabs a[href="' + tab + '"]') : $();
            ($targetTab.length ? $targetTab : $('#crDetailTabs a:first')).tab('show');
    }

    $(document).on('click', '.btn-detail-cr', function () {
        openCRDrawer($(this).data('id'), this, $(this).data('tab'));
    });

    /* ================================================================
       DRAWER TINJAU CR

       Memakai endpoint /budget-request/{id}/detail-json yang sudah ada —
       tidak ada query, route, maupun aturan bisnis baru. Drawer hanya
       menyusun ulang data yang sama menjadi permukaan tinjau: status
       beserta konteksnya, ringkasan, timeline, dan lampiran.

       Tombol aksinya DISALIN dari baris tabel yang bersangkutan, sehingga
       gerbang peran dan status persis sama dengan yang sudah dirender
       server — tidak ada logika izin kedua di sisi klien.
       ================================================================ */

    var crDrawerId = null;

    /* esc() sudah didefinisikan di atas; tidak dibuat ulang di sini supaya
       tidak ada dua fungsi dengan nama yang sama. */

    function drawerKV(pasangan) {
        var html = '<dl class="ts-dw-kv">';
        pasangan.forEach(function (p) {
            if (p[1] === null || p[1] === undefined || p[1] === '') return;
            html += '<dt>' + esc(p[0]) + '</dt><dd' + (p[2] ? ' class="is-amount"' : '') + '>' + esc(p[1]) + '</dd>';
        });
        return html + '</dl>';
    }

    /* Tab Log setinggi area gulir drawer supaya chat menempel di bawah
       walau isinya sedikit; lalu gulir ke pesan terbaru. */
    function aturTinggiLog(gulirKeBawah) {
        var body = document.getElementById('crDrawer-body');
        var $tabs = $('#crDetailTabs');
        if (!body || !$tabs.length) return;
        var cs = getComputedStyle(body);
        var tinggi = body.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)
                   - $tabs.outerHeight() - parseFloat($tabs.css('margin-bottom')) - parseFloat($tabs.css('margin-top'));
        $('#crPaneLogs').css('min-height', Math.max(tinggi, 0));
        if (gulirKeBawah) body.scrollTop = body.scrollHeight;
    }
    $(document).on('shown.bs.tab', '#crDetailTabs a[data-toggle="tab"]', function () {
        this.scrollIntoView({ block: 'nearest', inline: 'nearest' }); // tab aktif selalu terlihat
        if ($(this).attr('href') === '#crPaneLogs') aturTinggiLog(true);
    });
    $(window).on('resize', function () { if ($('#crPaneLogs').hasClass('active')) aturTinggiLog(false); });

    /* Mobile: geser kiri/kanan pada isi drawer untuk pindah tab. Di tab
       pertama (Info), geser kanan menutup drawer. Gesekan yang dimulai di
       tabel/tab/kotak isian dibiarkan (itu gulir mendatar atau mengetik). */
    (function () {
        var x0 = null, y0 = null, abaikan = false;
        $(document).on('touchstart', '#crDrawer-body', function (e) {
            var t = e.originalEvent.touches[0];
            x0 = t.clientX; y0 = t.clientY;
            abaikan = window.innerWidth > 768 || e.originalEvent.touches.length > 1 ||
                $(e.target).closest('.table-responsive, #crDetailTabs, textarea, input, select').length > 0;
        });
        $(document).on('touchend', '#crDrawer-body', function (e) {
            if (x0 === null || abaikan) { x0 = null; return; }
            var t = e.originalEvent.changedTouches[0];
            var dx = t.clientX - x0, dy = t.clientY - y0;
            x0 = null;
            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            var $tabs = $('#crDetailTabs li:visible a');
            var i = $tabs.index($tabs.filter('.active'));
            if (dx < 0) {                    // geser kiri → tab berikutnya
                if ($tabs[i + 1]) $($tabs[i + 1]).tab('show');
            } else if (i > 0) {              // geser kanan → tab sebelumnya
                $($tabs[i - 1]).tab('show');
            } else {                         // geser kanan di tab Info → tutup
                TesysDrawer.close('crDrawer');
            }
        });
    })();

    /* Satu baris chat di tab Log. Catatan bebas di-escape; riwayat status
       dirender seperti semula. */
    function renderLogRow(log) {
        var mine = log.user_id && currentUserId && log.user_id === currentUserId;
        var isNote = log.kind === 'note';
        var actor = log.actor || 'System';
        var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
        return '<div class="cr-chat-row ' + (mine ? 'mine' : '') + '">' +
            '<div class="cr-chat-av">' + (log.avatar ? '<img src="' + esc(log.avatar) + '" alt="">' : esc(initials)) + '</div>' +
            '<div>' +
                '<div class="cr-chat-meta"><strong>' + (isNote ? esc(actor) : actor) + '</strong> · ' + esc(log.date) + '</div>' +
                '<div class="cr-chat-bubble">' +
                    (isNote
                        ? '<div class="cr-chat-note" style="margin-top:0;">' + esc(log.notes) + '</div>'
                        : '<span class="badge badge-' + log.action_color + ' px-2 py-1" style="font-size:9px;">' + log.action_label + '</span>' +
                          (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + (log.status_from_label || log.status_from) + ' → ' + (log.status_to_label || log.status_to) + '</span>' : '') +
                          (log.notes ? '<div class="cr-chat-note">' + log.notes + '</div>' : '')) +
                '</div>' +
            '</div>' +
        '</div>';
    }

    $(document).on('submit', '#crLogForm', function (e) {
        e.preventDefault();
        var $f = $(this), $in = $('#crLogInput'), $btn = $('#crLogSend');
        var teks = $.trim($in.val());
        if (!teks) { $in.focus(); return; }
        $btn.prop('disabled', true);
        $.post('/budget-request/' + $f.data('id') + '/log-note', { _token: $('meta[name="csrf-token"]').attr('content'), notes: teks })
            .done(function (res) {
                if (!res || !res.log) return;
                $('#crLogBody .ts-empty').remove();
                $('#crLogBody').append(renderLogRow(res.log));
                $in.val('');
                var body = document.getElementById('crDrawer-body');
                if (body) body.scrollTop = body.scrollHeight;
            })
            .fail(function (xhr) {
                var pesan = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.notes && xhr.responseJSON.errors.notes[0])))
                    || 'Catatan belum dapat dikirim. Coba lagi.';
                Swal.fire({ icon: 'error', title: 'Gagal mengirim', text: pesan });
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    // Salin kode CR ke clipboard saat kode di kepala drawer diklik.
    $(document).on('click', '.cr-copy-code', function () {
        var btn = this, kode = $(btn).data('code'), asli = $(btn).text();
        function selesai() {
            $(btn).text('Tersalin ✓');
            setTimeout(function () { $(btn).text(asli); }, 1200);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(kode).then(selesai);
        } else {
            var t = document.createElement('textarea');
            t.value = kode; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); selesai(); } catch (err) {}
            document.body.removeChild(t);
        }
    });

    /* Lini masa tahap CR dari log status. Memakai kejadian TERBARU tiap tahap
       dan hanya yang terjadi setelah pengajuan terakhir, supaya CR yang pernah
       ditolak lalu diajukan ulang tidak menampilkan tanggal dari putaran lama. */
    function bangunLiniMasa(d) {
        var logs = (d.logs || []).filter(function (l) { return l.kind !== 'note'; }); // terbaru dulu
        function cari(fn) { for (var i = 0; i < logs.length; i++) { if (fn(logs[i])) return logs[i]; } return null; }

        var diajukan = cari(function (l) { return l.status_to === 'SUBMITTED'; });
        var mulai = diajukan ? Date.parse(diajukan.ts) : null;

        function tahap(label, fn, cadangan) {
            var l = cari(function (x) { return fn(x) && (mulai === null || Date.parse(x.ts) >= mulai); });
            if (l) return { label: label, date: l.date, ts: Date.parse(l.ts) };
            return cadangan ? { label: label, date: cadangan, ts: null } : null;
        }
        function status(kode) { return function (l) { return l.status_to === kode; }; }

        var daftar = [
            tahap('Diajukan', status('SUBMITTED'), d.submitted_at),
            tahap('Dicheck', status('CHECKED')),
            tahap('Diverifikasi', status('VERIFIED')),
            tahap('Di-approve', status('APPROVED')),
            tahap('Diproses', status('QUEUED')),
            tahap('Dibayar', function (l) { return l.action_type === 'paid'; }, d.paid_at)
        ];
        if (d.is_cash_advance) {
            daftar.push(
                tahap('Settle', status('REALIZED')),
                tahap('Settle: Dicheck', status('SETTLE_CHECKED')),
                tahap('Settle: Diverifikasi', status('SETTLE_VERIFIED')),
                tahap('Settle: Di-approve', status('SETTLE_APPROVED')),
                tahap('2nd Payment', status('SECOND_APPROVED'))
            );
        }
        var closed = d.status === 'CLOSED' ? tahap('Closed', status('CLOSED')) : null;
        daftar.push(closed);

        var baris = daftar.filter(Boolean).map(function (t) { return [t.label, t.date]; });

        // Total waktu: diajukan → closed; selama belum closed dihitung sampai sekarang.
        if (mulai !== null) {
            var selesai = closed && closed.ts ? closed.ts : Date.now();
            var menit = Math.max(0, Math.floor((selesai - mulai) / 60000));
            var hari = Math.floor(menit / 1440), jam = Math.floor((menit % 1440) / 60), mnt = menit % 60;
            var teks = (hari ? hari + ' hari ' : '') + (jam || hari ? jam + ' jam ' : '') + mnt + ' menit';
            baris.push([closed && closed.ts ? 'Total waktu (Diajukan → Closed)' : 'Berjalan sejak Diajukan', teks]);
        }
        return { baris: baris };
    }

    /* Carousel lampiran di kiri drawer (layar >= 1200px) — komponen bersama tesys-evidence-stage.js. */
    var stageEvidence = TesysEvidenceStage.attach('crDrawer');

    function openCRDrawer(id, pemicu, tab) {
        crDrawerId = id;

        $('#crDrawerContent').attr('hidden', true);
        $('#crDrawerError').attr('hidden', true).empty();
        $('#crDrawerLoading').show();
        $('#crDrawer-eyebrow').text('');
        $('#crDrawer-title').text('Memuat…');
        $('#crDrawerActions').empty();

        TesysDrawer.open('crDrawer', pemicu);
        stageEvidence.clear();

        // Aksi baris disalin apa adanya, termasuk form dan token CSRF-nya.
        var baris = pemicu ? $(pemicu).closest('tr') : $('.cr-tbl:not([data-tab="db"]) tbody tr[data-id="' + id + '"]').first();
        var aksi = baris.find('.ts-actions').children('.ts-action-primary, form').clone(true, true);
        // Baris dengan banyak aksi hanya punya menu titik tiga: butirnya disalin sebagai tombol drawer.
        var $menuAksi = baris.find('.ts-action-menu').first();
        if ($menuAksi.length) {
            aksi = $menuAksi.children('.dropdown-item, form').clone(true, true);
            aksi.find('.dropdown-item').addBack('.dropdown-item').removeClass('dropdown-item').addClass('ts-action-primary');
        }
        if (aksi.length) $('#crDrawerActions').append(aksi);

        function tampilkanGagal(pesan) {
            $('#crDrawerLoading').hide();
            $('#crDrawerError').html(
                '<div class="ts-empty">'
                + '<span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p>'
                + '<p class="ts-empty-msg">' + esc(pesan) + '</p>'
                + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral" id="crDrawerRetry">'
                + '<i class="fas fa-redo"></i><span class="ts-al">Coba lagi</span></button></div></div>'
            ).removeAttr('hidden');
        }

        $.get('/budget-request/' + id + '/detail-json')
            .done(function (d) {
                /*
                  Saat sesi habis, Laravel mengalihkan permintaan AJAX ini ke
                  halaman login — jQuery mengikuti pengalihan itu dan menerima
                  HTML berstatus 200, bukan galat. Tanpa penjagaan ini drawer
                  akan merender isi kosong seolah datanya memang tidak ada.
                */
                if (!d || typeof d !== 'object' || !d.code) {
                    tampilkanGagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.');
                    return;
                }

                var meta = statusMeta[d.status] || { label: d.status_label || d.status, description: null, tone: 'neutral' };

                $('#crDrawer-eyebrow').html(
                    '<button type="button" class="cr-copy-code" data-code="' + escAttr(d.code) + '" title="Klik untuk menyalin kode">'
                    + esc(d.code) + '</button>' + esc(d.type ? ' · ' + d.type : ''));
                $('#crDrawer-title').text(d.description);

                var html = '';

                /* ---- Badge tipe CR: Cash Advance burgundy lembut, selain itu
                   (Cost Payment dll.) biru lembut. Memakai token warna yang ada
                   (--accent-tint / --info-bg), bukan hex baru. */
                var tipeBadge = '';
                if (d.type) {
                    var gayaTipe = d.is_cash_advance
                        ? 'background:var(--accent-tint);color:var(--accent-text);border:1px solid var(--accent-tint-2);'
                        : 'background:var(--info-bg);color:var(--info);border:1px solid var(--info-bg);';
                    tipeBadge = '<span style="' + gayaTipe + 'font-size:10px;font-weight:700;'
                              + 'padding:2px 8px;border-radius:var(--radius-sm);white-space:nowrap;">'
                              + esc(d.type) + '</span>';
                }
                if (d.is_nob) tipeBadge += ' <span class="badge badge-danger" style="font-size:10px;">NOB</span>';

                /* ---- Nilai: nominal pengajuan + status di pojok kanan kartu.
                   Settlement/Refund hanya ditampilkan kalau nilainya > 0. */
                var sisi = '';
                var nilaiSettle = parseInt(d.total_realization, 10) || 0;
                if (nilaiSettle > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Settlement</span>'
                          + '<strong>' + esc(d.total_realization_formatted) + '</strong></span>';
                }
                /* Kelebihan settlement dinamai Second Payment, bukan "Refund 0". */
                var lebihBayar = parseInt(d.settlement_overage, 10) || 0;
                var nilaiRefund = parseInt(d.refund, 10) || 0;
                if (lebihBayar > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Second Payment</span>'
                          + '<strong>' + esc(d.settlement_overage_formatted) + '</strong></span>';
                } else if (nilaiRefund > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Refund</span>'
                          + '<strong>' + esc(d.refund_formatted) + '</strong></span>';
                }

                html += '<div class="ts-dw-section"><div class="ts-dw-amount">'
                     +    '<span class="ts-dw-amount-main">'
                     +      '<span class="ts-dw-amount-label">Nilai pengajuan</span>'
                     +      '<span class="ts-dw-amount-value">' + esc(d.amount_formatted || '-') + '</span>'
                     +      '<span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px;">'
                     +        tipeBadge
                     +        '<span class="ts-status ts-status-' + meta.tone + ' ts-status-sm">' + esc(meta.label) + '</span>'
                     +      '</span>'
                     +    '</span>'
                     +    sisi
                     +  '</div>'
                     +  (window.TesysWorkflow ? TesysWorkflow.render('budget-request', d.status) : '')
                     +  '</div>';

                /* ---- Badge Chart of Account: daftar COA unik yang dipakai CR,
                   supaya langsung terlihat biaya ini menyentuh akun apa saja. */
                var coaUnik = [];
                (d.details || []).forEach(function (det) {
                    var acc = det.account || '';
                    if (acc && acc !== '-' && coaUnik.indexOf(acc) === -1) coaUnik.push(acc);
                });
                if (coaUnik.length) {
                    html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Chart of Account</p>'
                         +  '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
                    coaUnik.forEach(function (acc) {
                        html += '<span style="background:var(--surface-2);color:var(--text-dim);'
                             +  'border:1px solid var(--border);font-size:11px;font-weight:600;'
                             +  'padding:3px 10px;border-radius:var(--radius-sm);">' + esc(acc) + '</span>';
                    });
                    html += '</div></div>';
                }

                /* ---- Informasi pengajuan: siapa & atas dasar apa.
                   Penerima menampilkan PIC-nya; Rekening memuat beneficiary +
                   bank & nomor. Purchase Order / Budget Plan dilebur jadi satu
                   baris Reference (Kode - Scope of Work); baris Scope of Work
                   dan Project terpisah tidak ditampilkan lagi. */
                var referensi = (d.code_reference && d.code_reference !== '-')
                    ? d.code_reference + (d.reference_scope && d.reference_scope !== '-' ? ' - ' + d.reference_scope : '')
                    : (d.reference_scope || null);
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Informasi pengajuan</p>'
                     + drawerKV([
                         ['Kode', d.code],
                         ['Description', d.description],
                         ['Payment Notes', d.payment_notes],
                         ['Notes', d.notes],
                         ['Requestor', d.applicant],
                         ['Beneficiary', d.pic],
                         ['Bank Account', d.beneficiary && d.bank
                             ? d.beneficiary + ' (' + d.bank + ' - ' + d.account_number + ')'
                             : (d.beneficiary || null)],
                         ['Reference', referensi],
                         ['Email', d.email],
                       ])
                     + '</div>';

                /* ---- Tanggal: lini masa tahap persetujuan, disusun dari log
                   status (tahap terbaru per status). Lampiran tidak diulang di
                   sini — sudah ada di tab Request & Settlement. */
                var tl = bangunLiniMasa(d);
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Tanggal</p>'
                     + (tl.baris.length ? drawerKV(tl.baris) : kosongRingkas('Belum diajukan.'))
                     + '</div>';

                $('#crPaneInfo').html(html);
                $('#crDrawerContent').removeAttr('hidden');
                $('#crDrawerLoading').hide();
                isiPanelCR(d, tab);
                if (String(id) === String(crDrawerId)) stageEvidence.render(TesysEvidenceStage.fromCostRequest(d));
            })
            .fail(function () {
                tampilkanGagal('Sambungan ke server terputus atau data sedang tidak tersedia.');
            });
    }

    $(document).on('click', '.btn-review-cr', function () {
        openCRDrawer($(this).data('id'), this);
    });

    // Klik di mana saja pada baris membuka detail (drawer yang sama dengan klik kode CR).
    // Kontrol di dalam baris (tombol, tautan, centang, menu aksi) tetap bekerja sendiri.
    $(document).on('click', '.cr-tbl tbody tr[data-id]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, .cr-col-check').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        openCRDrawer($(this).data('id'), this);
    });

    $(document).on('click', '#crDrawerRetry', function () {
        if (crDrawerId) openCRDrawer(crDrawerId);
    });

    // Auto-buka drawer detail bila diakses dengan ?open=<id> (mis. dari To Do).
    // Parameternya langsung dibuang dari URL setelah dipakai: kalau dibiarkan,
    // setiap reload (atau redirect back() dari aksi lain) akan membuka drawer
    // detail itu lagi seolah-olah bug.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var openId = params.get('open');
        if (!openId) return;

        params.delete('open');
        var sisa = params.toString();
        window.history.replaceState({}, '',
            window.location.pathname + (sisa ? '?' + sisa : '') + window.location.hash);

        openCRDrawer(openId);
    })();

});
</script>
@endpush
