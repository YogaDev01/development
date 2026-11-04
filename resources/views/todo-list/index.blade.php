@extends('layouts.app')

@section('title', 'Todo List Request')

@section('content')
@php
    /* Atribut grouping PO untuk baris tabel flat (dibaca JS "GROUPING PO"):
       label & keterangan sama formatnya dengan lapis PO di worklist CR —
       "[kode] nama" + "(customer - manager)". Tanpa project = "— Tanpa PO —"
       dengan keterangan cost center / sumber dana. */
    /* Kolom PO tabel Budgeting: "[0000] SCOPE OF WORK (Customer - Manager)".
       Budget non-proyek (cost center) tidak punya PO — tampil nama sumbernya. */
    $bpPoLabel = function ($bp) {
        $project = $bp->project;
        if (! $project) {
            return $bp->source_name ?: '-';
        }
        $pm = strtoupper($project->pm_internal_name ?? '-');
        return '[' . $project->code . '] ' . $project->name
            . ' (' . ($project->customer?->name ?? '-') . ($pm !== '-' ? ' - ' . $pm : '') . ')';
    };

    $poAttrs = function ($project, ?string $fallbackSub = null, $amount = 0) {
        if ($project) {
            $pm  = strtoupper($project->pm_internal_name ?? '-');
            $sub = ($project->customer?->name ?? '-') . ($pm && $pm !== '-' ? ' - ' . $pm : '');
            $label = '[' . $project->code . '] ' . $project->name;
        } else {
            $label = '— Tanpa PO —';
            $sub   = $fallbackSub ?? '';
        }
        return new \Illuminate\Support\HtmlString(
            'data-po-key="' . e($label) . '" data-po-label="' . e($label) . '" data-po-sub="' . e($sub) . '" data-po-amount="' . e((float) $amount) . '"'
        );
    };
@endphp

<div class="content" id="todoApp" data-ts-card-scope>
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Todo List Request</h1>
                <div style="color:var(--text-faint);font-size:13px;margin-top:2px;">Task Cost Request, Budgeting, Treasury, Purchase Order, Invoice, dan Collection yang menunggu tindakan Anda</div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Todo List Request</li>
            </ol>
        </div>

        @if($loadError ?? false)
        <div class="alert alert-danger" style="border-radius:var(--radius-lg);">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Sebagian data Todo List gagal dimuat (kemungkinan ada data yang tidak konsisten di salah satu modul).
            Tim IT sudah otomatis diberi tahu lewat log — silakan coba lagi beberapa saat lagi, atau hubungi admin kalau terus berulang.
        </div>
        @endif

        {{-- Tab "Summary Task" dihapus: isinya sudah digantikan modal Summary Task
             (tombol di toolbar bawah), yang kini menampilkan matrix Tahap x Modul
             lengkap. Dengan tinggal satu panel, bilah tab-nya ikut dihapus — tab
             tunggal cuma menambah satu lapis tanpa memberi pilihan apa pun. --}}

        <!-- SUMMARY CARDS -->
        <div class="row todo-kpi">
            <div class="col-6 col-md-4 mb-3">
                <div class="card shadow-sm mb-0 h-100 todo-kpi-card">
                    <div class="card-body py-3 px-3">
                        <div class="todo-kpi-label">My Pending Tasks</div>
                        <div class="todo-kpi-value">{{ $totalPending }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 mb-3">
                <div class="card shadow-sm mb-0 h-100 todo-kpi-card is-alert">
                    <div class="card-body py-3 px-3">
                        <div class="todo-kpi-label">Max Aging</div>
                        <div class="todo-kpi-value">{{ $maxAging }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <div class="card shadow-sm mb-0 h-100 todo-kpi-card">
                    <div class="card-body py-3 px-3">
                        <div class="todo-kpi-label">Tindakan Selesai Hari Ini</div>
                        <div class="todo-kpi-value">{{ $completedToday }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Strip task per modul — markup & kelas yang sama dengan x-workflow-stepper
             mode berfase di halaman Cost Request (gayanya di tesys-ui-refine.css,
             .wf-host .wf-phased). Diisi JS dari kartu section yang ada; klik tile
             menyaring halaman ke modul itu, klik lagi untuk melepas. --}}
        <div id="todoJump" class="wf-host todo-wf-host" hidden>
            <div class="wf-compact wf-phased">
                <div class="wf-phase-row"></div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="card shadow-sm mb-0" style="border-radius:var(--radius-lg);">
            <div class="card-body py-3 px-3">
                <div class="d-flex flex-wrap align-items-center todo-toolbar" style="gap:10px;">
                    <div class="d-flex flex-wrap align-items-center todo-toolbar-filters" style="gap:10px;">
                        <div class="todo-search" style="position:relative;width:190px;">
                            <i class="fas fa-search" style="position:absolute;left:10px;top:9px;color:var(--text-faint);font-size:12px;"></i>
                            <input type="text" id="todoSearch" class="form-control form-control-sm" placeholder="Cari CR..." style="padding-left:28px;">
                        </div>
                        <div id="todoRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                        <button aria-label="Clear Filter" type="button" id="btnTodoClearFilter" class="btn btn-outline-secondary btn-sm" title="Clear Filter" style="display:none;">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                    <div class="d-flex flex-wrap align-items-center ml-auto todo-toolbar-actions" style="gap:8px;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#mdlSummaryTask">
                            <i class="fas fa-chart-pie mr-1"></i> Summary Task
                        </button>
                        <a href="{{ url('/budget-request') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Daftar Cost Request
                        </a>
                        <button aria-label="Refresh" type="button" class="btn btn-outline-secondary btn-sm" id="btnTodoRefresh" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="{{ route('todo-list.export') }}" id="btnExportTodo" class="btn btn-outline-secondary btn-sm" title="Export semua daftar (mengikuti pencarian & filter)">
                            <i class="fas fa-file-excel mr-1"></i> Export Semua
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ASSIGNMENT — penugasan bebas antar-user. Selalu dirender (di luar
             cabang $hasAnySection) karena tidak terkait tahapan approval role. --}}
        @include('todo-list._assignments')

        @if(!$hasAnySection)
        <div class="text-center card shadow-sm mt-3" style="border-radius:var(--radius-lg);padding:60px 20px;">
            <div style="font-size:48px;color:var(--text-faint);"><i class="fas fa-tasks"></i></div>
            <div style="font-size:16px;font-weight:700;color:var(--text);margin-top:12px;">Belum ada task untuk role Anda</div>
            <div style="font-size:13px;color:var(--text-faint);margin-top:4px;">Role Anda saat ini tidak memiliki tahapan approval di modul manapun.</div>
        </div>
        @else

            @if($totalPending === 0 && empty($comingSoonRoles))
            <div class="text-center card shadow-sm mt-3" id="todoEmptyState" style="border-radius:var(--radius-lg);padding:60px 20px;">
                <div style="font-size:48px;color:var(--success);"><i class="fas fa-check-circle"></i></div>
                <div style="font-size:18px;font-weight:700;color:var(--text);margin-top:12px;">You're all caught up!</div>
                <div style="font-size:13px;color:var(--text-faint);margin-top:4px;">Tidak ada task yang menunggu tindakan Anda.</div>
            </div>
            @endif

            {{-- Tidak ada task yang cocok dengan filter/pencarian saat ini --}}
            <div class="text-center card shadow-sm mt-3" id="todoNoMatch" style="display:none;border-radius:var(--radius-lg);padding:60px 20px;">
                <div style="font-size:48px;color:var(--text-faint);"><i class="fas fa-filter"></i></div>
                <div style="font-size:16px;font-weight:700;color:var(--text);margin-top:12px;">Tidak ada CR yang cocok</div>
                <div style="font-size:13px;color:var(--text-faint);margin-top:4px;">Coba ubah kata kunci pencarian atau filter.</div>
            </div>

            {{-- HEAD / COST CONTROL / CEO — worklist grouped per Budget Plan --}}
            @foreach($sections as $key => $section)
                @include('todo-list._worklist_section', [
                    'key'         => $key,
                    'title'       => $section['title'],
                    'statusLabel' => $section['status_label'],
                    'groups'      => $section['groups'],
                    'approveUrl'  => $section['approve_url'],
                    'rejectUrl'   => $section['reject_url'],
                ])
            @endforeach

            {{-- MANAGER — REJECT HANDLING --}}
            @if($managerRejected->isNotEmpty())
            <div id="tl-reject" class="card card-danger card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-undo mr-1"></i> Reject Handling
                        <span class="badge badge-secondary ml-1">{{ $managerRejected->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:190px;">CR Number</th>
                                    <th scope="col" style="width:32%;">Description</th>
                                    <th scope="col" style="width:30%;">Alasan Ditolak</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:130px;">Batch Treasury</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($managerRejected as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? ''));
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                @endphp
                                <tr class="todo-row" data-export-key="cr:{{ $cr->id }}" {{ $poAttrs($cr->budgetPlan?->project, $cr->budgetPlan?->costCenter?->name, $cr->amount) }} data-pm="{{ strtoupper($cr->pm_internal_name) }}" data-po="{{ $cr->po_label }}" data-search="{{ $crSearch }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 120) }}</td>
                                    <td style="font-size:11.5px;">
                                        @if($cr->rejection_log)
                                        <div class="text-danger">{{ $cr->rejection_log->notes }}</div>
                                        <div class="text-muted">— {{ $cr->rejection_log->actor_name }} ({{ $cr->rejection_log->status_from }}), {{ $cr->rejection_log->created_at?->format('d M Y H:i') }}</div>
                                        @else
                                        <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        @php
                                            $bolehRevise = Auth::user()->hasRole(['superadmin','manager','supervisor']);
                                            $bolehCancel = Auth::user()->hasRole(['superadmin','manager']);
                                        @endphp
                                        <div class="action-buttons ts-actions">

                                            {{-- Revise mengembalikan CR ke Draft — aksi mundur, bukan
                                                 merusak, jadi netral. Cancel menutup CR untuk selamanya
                                                 dan disimpan di menu supaya tidak terklik sambil lalu. --}}
                                            @if($bolehRevise)
                                            <button type="button" class="ts-action-primary is-neutral btn-manager-revise"
                                                    data-id="{{ $cr->id }}" title="Revise → Draft">
                                                <i class="fas fa-undo" aria-hidden="true"></i><span class="ts-al">Revise</span>
                                            </button>
                                            @elseif($bolehCancel)
                                            <button type="button" class="ts-action-primary is-danger btn-manager-cancel"
                                                    data-id="{{ $cr->id }}" title="Cancel">
                                                <i class="fas fa-ban" aria-hidden="true"></i><span class="ts-al">Cancel</span>
                                            </button>
                                            @endif

                                            @if($bolehRevise && $bolehCancel)
                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $cr->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <button type="button" class="dropdown-item is-danger btn-manager-cancel"
                                                        data-id="{{ $cr->id }}">
                                                    <i class="fas fa-ban"></i>Cancel
                                                </button>
                                            </div>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- MANAGER — SUBMIT REALIZATION --}}
            @if($managerPaid->isNotEmpty())
            <div id="tl-settlement" class="card card-info card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-receipt mr-1"></i> Submit Settlement
                        <span class="badge badge-secondary ml-1">{{ $managerPaid->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:190px;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($managerPaid as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? ''));
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                @endphp
                                <tr class="todo-row" data-export-key="cr:{{ $cr->id }}" {{ $poAttrs($cr->budgetPlan?->project, $cr->budgetPlan?->costCenter?->name, $cr->amount) }} data-pm="{{ strtoupper($cr->pm_internal_name) }}" data-po="{{ $cr->po_label }}" data-search="{{ $crSearch }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>
                                        @if($cr->status === 'SETTLE_REJECTED')
                                            <span class="badge badge-danger mr-1" style="font-size:9px;"
                                                  title="{{ $cr->settle_reject_log?->notes }}">Settlement Ditolak</span>
                                        @endif
                                        {{ Str::limit($cr->description, 150) }}
                                    </td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('budget-request.realize-form', $cr->id) }}"
                                               class="ts-action-primary" title="Submit Settlement">
                                                <i class="fas fa-receipt" aria-hidden="true"></i><span class="ts-al">Settle</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- SETTLEMENT — 3 tahap approval berjenjang:
                 Head Check -> Finance Verify -> Finance Manager Close.
                 Tahap penutupan dipegang FINANCE MANAGER, bukan CEO — itu
                 sudah tercermin di controller (role finance_manager), hanya
                 labelnya yang tertinggal menyebut CEO. --}}
            @foreach([
                ['data' => $headSettlementCheck, 'key' => 'settle-check', 'title' => 'Settlement — Head Check', 'approveBtn' => 'btn-head-check-settlement', 'rejectBtn' => 'btn-head-reject-settlement', 'approveTitle' => 'Check → Settle Checked'],
                ['data' => $financeSettlementVerify, 'key' => 'settle-verify', 'title' => 'Settlement — Finance Verify', 'approveBtn' => 'btn-finance-verify-settlement', 'rejectBtn' => 'btn-finance-reject-settlement', 'approveTitle' => 'Verify → Settle Verified'],
            ] as $stage)
            @if($stage['data']->isNotEmpty())
            <div id="tl-{{ $stage['key'] }}" class="card card-success card-outline shadow-sm mt-3 todo-worklist" data-section="{{ $stage['key'] }}">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-lock mr-1"></i> {{ $stage['title'] }}
                        {{-- MN-03: data kini bertingkat PO -> Budget Plan -> CR,
                             jadi hitungannya ikut menembus dua lapis. --}}
                        <span class="badge badge-secondary ml-1">{{ $stage['data']->sum('qty') }}</span>
                        <span class="text-muted ml-1" style="font-size:11px;">dari {{ $stage['data']->count() }} PO</span>
                    </h3>
                </div>
                @include('todo-list._bulk_bar', ['key' => $stage['key'], 'approveLabel' => $stage['key'] === 'settle-check' ? 'Check' : 'Verify', 'unit' => 'CR'])
                <div class="card-body p-0">
                    <div style="padding:6px 16px;border-bottom:1px solid var(--border);">
                        <button type="button" class="btn btn-link todo-toggle-all p-0" style="font-size:11.5px;font-weight:600;text-decoration:none;" data-state="collapsed">
                            <i class="fas fa-expand-alt mr-1"></i>Expand All
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:190px;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Refund</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stage['data'] as $poi => $poGroup)
                    @php
                        /* Satu PO dengan SATU Budget Plan tidak perlu dua baris
                           grup — keduanya mewakili himpunan CR yang sama. Lapis
                           Budget Plan dilewati, baris CR digantung pada baris PO.
                           Lihat penjelasan lengkap di _worklist_section.blade.php. */
                        $planTunggal = $poGroup['plans']->count() === 1;
                    @endphp
                                {{-- LAPIS 1 — PURCHASE ORDER (MN-03). --}}
                                <tr class="todo-po-row" data-target="todo-{{ $stage['key'] }}-po-{{ $poi }}">
                                    <td><input type="checkbox" class="todo-check-group" data-group="todo-{{ $stage['key'] }}-po-{{ $poi }}" aria-label="Pilih semua CR PO ini"></td>
                                    <td colspan="3">
                                        <i class="fas fa-chevron-right todo-caret mr-2"></i>
                                        <strong>{{ $poGroup['po'] }}</strong>
                                        {{-- Manager hanya ikut ditulis kalau ada. Budget cost center (Overhead/
                                                 Payroll) tidak punya PM, dan "(NAMA - -)" cuma menambah
                                                 tanda hubung yang tidak mewakili apa pun. --}}
                                    @php $poSub = $poGroup['customer'] . (($poGroup['pm'] && $poGroup['pm'] !== '-') ? ' - ' . $poGroup['pm'] : ''); @endphp
                                    <span class="text-muted" style="font-size:11px;">({{ $poSub }})</span>
                                        <span class="badge badge-secondary ml-1">{{ $poGroup['qty'] }} CR</span>
                                        @if(!$planTunggal)
                                            <span class="badge badge-light border ml-1" style="font-size:9px;">{{ $poGroup['plans']->count() }} budget plan</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap" style="font-weight:700;">
                                        {{ number_format($poGroup['amount'], 0, ',', '.') }}
                                    </td>
                                    <td colspan="2"></td>
                                    <td class="text-center text-nowrap">
                                        @php
                                            $poBudgetTargets = collect($poGroup['plans'])->keys()
                                                ->map(fn ($pi) => '#mdlTodoCoa-' . $stage['key'] . '-' . $poi . '-' . $pi)->implode(',');
                                        @endphp
                                        {{-- Satu tombol untuk seluruh Budget Plan di PO ini — modal
                                             gabungan menampilkan tabel per kode anggaran. --}}
                                        <div class="action-buttons ts-actions">
                                            <button aria-label="View Budget" type="button" class="ts-action-primary is-neutral todo-view-budget-group"
                                                    data-targets="{{ $poBudgetTargets }}" data-po-label="{{ $poGroup['po'] }}" title="View Budget">
                                                <i class="fas fa-chart-pie" aria-hidden="true"></i><span class="ts-al">View Budget</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                @foreach($poGroup['plans'] as $pi => $group)
                                @php
                                    $gi = $poi . '-' . $pi;
                                    $indukBarisCr = $planTunggal
                                        ? 'todo-' . $stage['key'] . '-po-' . $poi
                                        : 'todo-' . $stage['key'] . '-grp-' . $gi;
                                @endphp
                                @if(!$planTunggal)
                                {{-- LAPIS 2 — BUDGET PLAN. --}}
                                <tr class="todo-group-row todo-{{ $stage['key'] }}-po-{{ $poi }}" style="display:none;"
                                    data-target="todo-{{ $stage['key'] }}-grp-{{ $gi }}">
                                    <td><input type="checkbox" class="todo-check-group" data-group="todo-{{ $stage['key'] }}-grp-{{ $gi }}" aria-label="Pilih semua CR budget plan ini"></td>
                                    <td colspan="3" style="padding-left:32px;">
                                        <i class="fas fa-chevron-right todo-caret mr-2"></i>
                                        <strong>{{ $group['label'] }}</strong>
                                        <span class="text-muted" style="font-size:11px;">({{ $group['pm'] }})</span>
                                        <span class="badge badge-secondary ml-1">{{ $group['items']->count() }}</span>
                                    </td>
                                    <td class="text-right text-nowrap" style="font-weight:700;">
                                        {{ number_format($group['items']->sum('amount'), 0, ',', '.') }}
                                    </td>
                                    <td class="text-right text-nowrap" style="font-weight:700;">
                                        {{ number_format($group['items']->sum('proposed_refund'), 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                    <td class="text-center text-nowrap">
                                        
                                    </td>
                                </tr>
                                @endif
                                @foreach($group['items'] as $cr)
                                @php
                                    // Pencarian ikut menjangkau No. PO, sesuai grouping baru.
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' '
                                        . ($cr->beneficiary?->nickname ?? '') . ' ' . $poGroup['po']);
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                    $refundClass = $cr->proposed_refund < 0 ? 'text-danger' : ($cr->proposed_refund > 0 ? 'text-success' : 'text-muted');
                                @endphp
                                <tr class="{{ $indukBarisCr }} todo-row" data-export-key="cr:{{ $cr->id }}" style="display:none;"
                                    data-pm="{{ $group['pm'] }}" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
                                    <td><input type="checkbox" class="todo-check" value="{{ $cr->id }}" data-amount="{{ $cr->amount }}" aria-label="Pilih {{ $cr->code }}"></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-right text-nowrap {{ $refundClass }}"><strong>{{ $cr->proposed_refund_formatted }}</strong>
                                        @if($cr->proposed_refund < 0)<span class="badge badge-warning ml-1" style="font-size:8px;">Second Approval</span>@endif
                                    </td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">

                                            <button type="button" class="ts-action-primary {{ $stage['approveBtn'] }}"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}"
                                                    title="{{ $stage['approveTitle'] }}">
                                                <i class="fas fa-check-double" aria-hidden="true"></i><span class="ts-al">{{ $stage['approveTitle'] }}</span>
                                            </button>

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $cr->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <button type="button" class="dropdown-item is-danger {{ $stage['rejectBtn'] }}"
                                                        data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                    <i class="fas fa-times"></i>Tolak
                                                </button>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="4" class="text-right">Subtotal</th>
                                    <th class="text-right text-nowrap">
                                        {{ number_format($stage['data']->sum('amount'), 0, ',', '.') }}
                                    </th>
                                    <th class="text-right text-nowrap">
                                        {{ number_format($stage['data']->sum(fn ($po) => $po['plans']->sum(fn ($g) => $g['items']->sum('proposed_refund'))), 0, ',', '.') }}
                                    </th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            @include('todo-list._budget_modals', ['key' => $stage['key'], 'groups' => $stage['data']])
            @endif
            @endforeach

            {{-- EXPENSE CLAIM — 2ND APPROVE (SETTLE_APPROVED -> SECOND_APPROVED, role approver).
                 Hanya CR yang settlement-nya melebihi pengajuan yang sampai ke sini. --}}
            @if($approverSecondApprove->isNotEmpty())
            <div id="tl-settle-2nd" class="card card-warning card-outline shadow-sm mt-3 todo-worklist" data-section="settle-2nd">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Expense Claim — Second Approve
                        <span class="badge badge-secondary ml-1">{{ $approverSecondApprove->sum('qty') }}</span>
                        <span class="text-muted ml-1" style="font-size:11px;">dari {{ $approverSecondApprove->count() }} PO</span>
                    </h3>
                </div>
                @include('todo-list._bulk_bar', ['key' => 'settle-2nd', 'approveLabel' => 'Second Approve', 'unit' => 'CR'])
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-sm table-hover mb-0" style="font-size:12px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:180px;">Code</th>
                                    <th scope="col">Deskripsi</th>
                                    <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Request</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Settlement</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:135px;">Expense Claim</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($approverSecondApprove as $poi => $poGroup)
                                @foreach($poGroup['plans'] as $pi => $group)
                                @foreach($group['items'] as $cr)
                                @php
                                    $gi = $poi . '-' . $pi;
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                @endphp
                                <tr class="todo-row" data-export-key="cr:{{ $cr->id }}" data-po-key="{{ $poGroup['po'] }}" data-po-label="{{ $poGroup['po'] }}" data-po-sub="{{ $poGroup['customer'] . (($poGroup['pm'] ?? '-') !== '-' ? ' - ' . $poGroup['pm'] : '') }}" data-po-amount="{{ $cr->expense_claim }}"
                                    data-pm="{{ $group['pm'] }}" data-po="{{ $poGroup['po'] }}"
                                    data-search="{{ strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? '')) }}">
                                    <td><input type="checkbox" class="todo-check" value="{{ $cr->id }}" data-amount="{{ $cr->expense_claim ?? $cr->amount }}" aria-label="Pilih {{ $cr->code }}"></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap">{{ $cr->amount_formatted }}</td>
                                    <td class="text-right text-nowrap">{{ number_format($cr->total_realization, 0, ',', '.') }}</td>
                                    <td class="text-right text-nowrap text-danger"><strong>{{ $cr->expense_claim_fmt }}</strong></td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <button type="button" class="ts-action-primary btn-second-approve"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve → Treasury">
                                                <i class="fas fa-hand-holding-usd" aria-hidden="true"></i><span class="ts-al">Second Approve</span>
                                            </button>
                                            <button type="button" class="ts-action-primary is-danger btn-second-reject"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Reject → Paid">
                                                <i class="fas fa-times" aria-hidden="true"></i><span class="ts-al">Reject</span>
                                            </button>
                                            {{-- Sumber tombol View Budget di header grup PO (disembunyikan
                                                 di baris — rinciannya milik grup, bukan item). --}}
                                            <button aria-label="View Budget" type="button" class="ts-action-primary is-neutral todo-view-budget todo-po-mirror d-none"
                                                    data-target="#mdlTodoCoa-settle-2nd-{{ $gi }}" title="View Budget">
                                                <i class="fas fa-chart-pie" aria-hidden="true"></i><span class="ts-al">View Budget</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @include('todo-list._budget_modals', ['key' => 'settle-2nd', 'groups' => $approverSecondApprove])

            {{-- SETTLEMENT — CEO CLOSE (SETTLE_VERIFIED), diblokir kalau masih ada Second Approval PENDING --}}
            @if($ceoSettlementClose->isNotEmpty())
            <div id="tl-settle-close" class="card card-success card-outline shadow-sm mt-3 todo-worklist" data-section="settle-close">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-lock mr-1"></i> Settlement — Finance Manager Close
                        <span class="badge badge-secondary ml-1">{{ $ceoSettlementClose->sum('qty') }}</span>
                        <span class="text-muted ml-1" style="font-size:11px;">dari {{ $ceoSettlementClose->count() }} PO</span>
                    </h3>
                </div>

                {{-- Multi-select: Close / Reject beberapa CR sekaligus. Memakai
                     mesin bulk yang sama dengan worklist review (lihat
                     _worklist_section.blade.php + handler .todo-bulk-* di bawah). --}}
                <div class="todo-bulk-bar">
                    <div class="d-flex align-items-center justify-content-between">
                        <div style="font-size:12.5px;color:var(--text);"><strong class="todo-bulk-count">0</strong> CR dipilih<span class="todo-bulk-amount"></span></div>
                        <div class="d-flex" style="gap:8px;">
                            <button type="button" class="btn btn-outline-success btn-sm todo-bulk-approve" disabled>
                                <i class="fas fa-check-double mr-1"></i>Approve
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm todo-bulk-reject" disabled>
                                <i class="fas fa-times mr-1"></i>Reject
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div style="padding:6px 16px;border-bottom:1px solid var(--border);">
                        <button type="button" class="btn btn-link todo-toggle-all p-0" style="font-size:11.5px;font-weight:600;text-decoration:none;" data-state="collapsed">
                            <i class="fas fa-expand-alt mr-1"></i>Expand All
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:190px;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Refund</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Expense Claim</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ceoSettlementClose as $poi => $poGroup)
                    @php
                        /* Satu PO dengan SATU Budget Plan tidak perlu dua baris
                           grup — keduanya mewakili himpunan CR yang sama. Lapis
                           Budget Plan dilewati, baris CR digantung pada baris PO.
                           Lihat penjelasan lengkap di _worklist_section.blade.php. */
                        $planTunggal = $poGroup['plans']->count() === 1;
                    @endphp
                                {{-- LAPIS 1 — PURCHASE ORDER (MN-03). --}}
                                <tr class="todo-po-row" data-target="todo-settle-close-po-{{ $poi }}">
                                    <td><input type="checkbox" class="todo-check-group" data-group="todo-settle-close-po-{{ $poi }}" aria-label="Pilih semua CR PO ini"></td>
                                    <td colspan="3">
                                        <i class="fas fa-chevron-right todo-caret mr-2"></i>
                                        <strong>{{ $poGroup['po'] }}</strong>
                                        {{-- Manager hanya ikut ditulis kalau ada. Budget cost center (Overhead/
                                                 Payroll) tidak punya PM, dan "(NAMA - -)" cuma menambah
                                                 tanda hubung yang tidak mewakili apa pun. --}}
                                    @php $poSub = $poGroup['customer'] . (($poGroup['pm'] && $poGroup['pm'] !== '-') ? ' - ' . $poGroup['pm'] : ''); @endphp
                                    <span class="text-muted" style="font-size:11px;">({{ $poSub }})</span>
                                        <span class="badge badge-secondary ml-1">{{ $poGroup['qty'] }} CR</span>
                                        @if(!$planTunggal)
                                            <span class="badge badge-light border ml-1" style="font-size:9px;">{{ $poGroup['plans']->count() }} budget plan</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap" style="font-weight:700;">
                                        {{ number_format($poGroup['amount'], 0, ',', '.') }}
                                    </td>
                                    <td colspan="3"></td>
                                    <td class="text-center text-nowrap">
                                        @php
                                            $poBudgetTargets = collect($poGroup['plans'])->keys()
                                                ->map(fn ($pi) => '#mdlTodoCoa-settle-close-' . $poi . '-' . $pi)->implode(',');
                                        @endphp
                                        {{-- Satu tombol untuk seluruh Budget Plan di PO ini — modal
                                             gabungan menampilkan tabel per kode anggaran. --}}
                                        <div class="action-buttons ts-actions">
                                            <button aria-label="View Budget" type="button" class="ts-action-primary is-neutral todo-view-budget-group"
                                                    data-targets="{{ $poBudgetTargets }}" data-po-label="{{ $poGroup['po'] }}" title="View Budget">
                                                <i class="fas fa-chart-pie" aria-hidden="true"></i><span class="ts-al">View Budget</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                @foreach($poGroup['plans'] as $pi => $group)
                                @php
                                    $gi = $poi . '-' . $pi;
                                    $indukBarisCr = $planTunggal
                                        ? 'todo-settle-close-po-' . $poi
                                        : 'todo-settle-close-grp-' . $gi;
                                @endphp
                                @if(!$planTunggal)
                                {{-- LAPIS 2 — BUDGET PLAN. --}}
                                <tr class="todo-group-row todo-settle-close-po-{{ $poi }}" style="display:none;"
                                    data-target="todo-settle-close-grp-{{ $gi }}">
                                    <td><input type="checkbox" class="todo-check-group" data-group="todo-settle-close-grp-{{ $gi }}" aria-label="Pilih semua CR budget plan ini"></td>
                                    <td colspan="5" style="padding-left:32px;">
                                        <i class="fas fa-chevron-right todo-caret mr-2"></i>
                                        <strong>{{ $group['label'] }}</strong>
                                        <span class="text-muted" style="font-size:11px;">({{ $group['pm'] }})</span>
                                        <span class="badge badge-secondary ml-1">{{ $group['items']->count() }}</span>
                                    </td>
                                    <td colspan="2"></td>
                                    <td class="text-center text-nowrap">
                                        
                                    </td>
                                </tr>
                                @endif
                                @foreach($group['items'] as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' '
                                        . ($cr->beneficiary?->nickname ?? '') . ' ' . $poGroup['po']);
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                    // Refund & Expense Claim saling eksklusif: settlement di bawah
                                    // pengajuan = Refund, di atasnya = Expense Claim. Nilai negatif
                                    // Refund sudah diwakili kolom Expense Claim, jadi tampil 0.
                                    $refundShown = max(0, (int) $cr->proposed_refund);
                                    $refundClass = $refundShown > 0 ? 'text-success' : 'text-muted';
                                @endphp
                                <tr class="{{ $indukBarisCr }} todo-row" data-export-key="cr:{{ $cr->id }}" style="display:none;"
                                    data-pm="{{ $group['pm'] }}" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
                                    {{-- CR yang Second Payment-nya masih PENDING memang tidak bisa
                                         di-Close, jadi tidak diberi checkbox sama sekali daripada
                                         ikut terpilih lalu gagal diam-diam saat bulk. --}}
                                    {{-- CR dengan Expense Claim tetap diberi checkbox: bulk Approve
                                         menjalankan Settle Approve untuknya, Close untuk yang lain
                                         (lihat HandlesCrSettlement::settlementBulkClose). --}}
                                    <td>
                                        @unless($cr->pending_second_payment && ! $cr->needs_settle_approve)
                                        <input type="checkbox" class="todo-check" value="{{ $cr->id }}" data-amount="{{ $cr->amount }}" aria-label="Pilih {{ $cr->code }}">
                                        @endunless
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-right text-nowrap {{ $refundClass }}"><strong>{{ number_format($refundShown, 0, ',', '.') }}</strong></td>
                                    <td class="text-right text-nowrap {{ $cr->needs_settle_approve ? 'text-danger' : 'text-muted' }}"><strong>{{ $cr->needs_settle_approve ? $cr->expense_claim_fmt : '0' }}</strong></td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        {{-- Expense Claim > 0 SELALU punya Second Payment PENDING (dibuat
                                             saat Finance Verify), jadi cabang Settle Approve harus dicek
                                             lebih dulu — kalau tidak, badge "Pending" menutupi aksinya. --}}
                                        @if($cr->pending_second_payment && ! $cr->needs_settle_approve)
                                        <span class="badge badge-warning" style="font-size:9px;" title="Second Approval {{ $cr->pending_second_payment->amount_formatted }} masih PENDING">
                                            <i class="fas fa-hourglass-half mr-1"></i>Second Approval Pending
                                        </span>
                                        @else
                                        <div class="action-buttons ts-actions">
                                            @if($cr->needs_settle_approve)
                                            <button type="button" class="ts-action-primary btn-settle-approve"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}"
                                                    title="Expense Claim {{ $cr->expense_claim_fmt }} — Approve → Second Approve">
                                                <i class="fas fa-check-double" aria-hidden="true"></i><span class="ts-al">Settle Approve</span>
                                            </button>
                                            <button type="button" class="ts-action-primary is-danger btn-settle-reject"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Reject → Paid">
                                                <i class="fas fa-times" aria-hidden="true"></i><span class="ts-al">Reject</span>
                                            </button>
                                            @else
                                            <button type="button" class="ts-action-primary btn-ceo-close-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Close">
                                                <i class="fas fa-lock" aria-hidden="true"></i><span class="ts-al">Close</span>
                                            </button>
                                            @endif

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $cr->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <button type="button" class="dropdown-item is-danger btn-manager-reject-settlement"
                                                        data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                    <i class="fas fa-times"></i>Tolak settlement
                                                </button>
                                            </div>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- FORM tersembunyi untuk bulk Close/Reject — ids diisi JS dari checkbox --}}
                <form id="frmTodoBulkApprove-settle-close" action="{{ route('budget-request.settlement-bulk-close') }}" method="POST" style="display:none;">
                    @csrf
                </form>
                <form id="frmTodoBulkReject-settle-close" action="{{ route('budget-request.settlement-bulk-reject') }}" method="POST" style="display:none;">
                    @csrf
                    <textarea name="notes"></textarea>
                </form>
            </div>
            @endif

            @include('todo-list._budget_modals', ['key' => 'settle-close', 'groups' => $ceoSettlementClose])

            {{-- SECOND PAYMENT — selisih Settlement yang belum dibayar.
                 Informatif saja: pelunasannya lewat batch Treasury
                 (Daily Transaction), bukan ditandai dari halaman ini. --}}
            @if($secondPaymentPending->isNotEmpty())
            <div id="tl-second-payment" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Second Approval — Menunggu Pembayaran
                        <span class="badge badge-secondary ml-1">{{ $secondPaymentPending->count() }}</span>
                    </h3>
                    <div class="card-tools">
                        <a href="{{ url('/daily-transaction/create') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Buat Batch Treasury
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:190px;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:140px;">Second Approval</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($secondPaymentGroups as $poi => $poGroup)
                                @foreach($poGroup['plans'] as $pi => $group)
                                @foreach($group['items'] as $cr)
                                @php $gi = $poi . '-' . $pi; @endphp
                                <tr class="todo-row" data-export-key="cr:{{ $cr->id }}" {{ $poAttrs($cr->budgetPlan?->project, $cr->budgetPlan?->costCenter?->name, $cr->pending_payment?->amount ?? 0) }} data-search="{{ strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? '')) }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 150) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->pending_payment->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap">
                                        @if($cr->second_payment_batch_code)
                                            <a href="{{ url('/daily-transaction/' . $cr->second_payment_batch->id) }}" class="badge badge-info" style="font-size:10px;">
                                                {{ $cr->second_payment_batch_code }}
                                            </a>
                                        @else
                                            <span class="badge badge-warning" style="font-size:10px;" title="Belum dimasukkan ke batch Treasury mana pun">Belum dibatch</span>
                                        @endif
                                        {{-- Detail Budget Plan milik CR ini (ringkasan plan). --}}
                                        @if($cr->budget_plan_id)
                                        <button aria-label="Detail Budget" type="button" class="btn btn-outline-secondary btn-sm todo-bp-detail ml-1"
                                                data-id="{{ $cr->budget_plan_id }}" style="padding:1px 8px;font-size:10.5px;" title="Detail Budget">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                        @endif
                                        {{-- Sumber tombol View Budget di header grup PO (disembunyikan
                                             di baris — rinciannya milik grup, bukan item). Pola sama
                                             dengan section Second Approve & Settlement. --}}
                                        <button aria-label="View Budget" type="button" class="ts-action-primary is-neutral todo-view-budget todo-po-mirror d-none"
                                                data-target="#mdlTodoCoa-second-pay-{{ $gi }}" title="View Budget">
                                            <i class="fas fa-chart-pie" aria-hidden="true"></i><span class="ts-al">View Budget</span>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @include('todo-list._budget_modals', ['key' => 'second-pay', 'groups' => $secondPaymentGroups])

            {{-- BUDGETING — 4 tahap sama seperti Cost Request: Manager submit -> Head
                 check -> Finance verify -> CEO approve (Active). --}}
            @php
                $budgetingStages = [
                    ['data' => $budgetingHeadCheck,  'key' => 'bp-head',   'title' => 'Budgeting — Menunggu Approval Head',      'approveRoute' => 'budget-plan.head-approve',   'rejectRoute' => 'budget-plan.head-reject',   'approveTitle' => 'Approve'],
                    ['data' => $budgetingVerify,     'key' => 'bp-verify', 'title' => 'Budgeting — Menunggu Verifikasi Finance', 'approveRoute' => 'budget-plan.finance-verify', 'rejectRoute' => 'budget-plan.finance-reject','approveTitle' => 'Verify'],
                    ['data' => $budgetingCeoApprove, 'key' => 'bp-ceo',    'title' => 'Budgeting — Menunggu Approval CEO',       'approveRoute' => 'budget-plan.ceo-approve',    'rejectRoute' => 'budget-plan.ceo-reject',    'approveTitle' => 'Approve'],
                ];
            @endphp
            @foreach($budgetingStages as $stage)
            @if($stage['data']->isNotEmpty())
            <div id="tl-{{ $stage['key'] }}" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section todo-worklist" data-section="{{ $stage['key'] }}">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-coins mr-1"></i> {{ $stage['title'] }}
                        <span class="badge badge-secondary ml-1">{{ $stage['data']->count() }}</span>
                    </h3>
                </div>
                @include('todo-list._bulk_bar', ['key' => $stage['key'], 'approveLabel' => $stage['approveTitle'], 'unit' => 'budget'])
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:180px;">Code</th>
                                    <th scope="col" style="width:40%;">Description</th>
                                    <th scope="col" style="width:26%;">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Budget</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stage['data'] as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="bp:{{ $bp->id }}" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
                                    <td><input type="checkbox" class="todo-check" value="{{ $bp->id }}" data-amount="{{ $bp->amount }}" aria-label="Pilih {{ $bp->code }}"></td>
                                    {{-- Todo #4 — kode membuka rincian Budget Plan di
                                         tempat, sehingga approver tidak perlu pindah ke
                                         halaman Budgeting hanya untuk melihat isinya
                                         sebelum memutuskan. --}}
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td>{{ $bpPoLabel($bp) }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $bp->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $bpAgingClass }}">{{ $bp->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">

                                            <form action="{{ route($stage['approveRoute'], $bp->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button aria-label="{{ $stage['approveTitle'] }}" type="submit"
                                                        class="ts-action-primary" title="{{ $stage['approveTitle'] }}">
                                                    <i class="fas fa-check" aria-hidden="true"></i><span class="ts-al">{{ $stage['approveTitle'] }}</span>
                                                </button>
                                            </form>

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $bp->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <button type="button" class="dropdown-item is-danger todo-bp-reject"
                                                        data-id="{{ $bp->id }}" data-action="{{ route($stage['rejectRoute'], $bp->id) }}">
                                                    <i class="fas fa-times"></i>Reject
                                                </button>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            @endforeach

            {{-- BUDGETING — CLOSING BELUM DIAJUKAN (SISA DATA LAMA), MANAGER AJUKAN CLOSING --}}
            @if($budgetingClosingDue->isNotEmpty())
            <div id="tl-bp-closing-due" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-flag-checkered mr-1"></i> Budgeting — Ajukan Closing (CLOSING belum diajukan)
                        <span class="badge badge-secondary ml-1">{{ $budgetingClosingDue->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:180px;">Code</th>
                                    <th scope="col" style="width:40%;">Description</th>
                                    <th scope="col" style="width:26%;">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Budget</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetingClosingDue as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="bp:{{ $bp->id }}" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td>{{ $bpPoLabel($bp) }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $bp->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $bpAgingClass }}">{{ $bp->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        {{-- Pengajuan closing wajib berlampiran, jadi barisnya
                                             mengantar ke halaman Budgeting (modal Submit
                                             Closing) alih-alih beraksi langsung dari sini. --}}
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ url('/budget-plan') }}" class="ts-action-primary"
                                               title="Ajukan Closing di halaman Budgeting">
                                                <i class="fas fa-flag-checkered" aria-hidden="true"></i><span class="ts-al">Ajukan Closing</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- BUDGETING — CLOSING SUDAH DIAJUKAN MANAGER, FINANCE VERIFIKASI CLOSING --}}
            @if($budgetingClosingVerify->isNotEmpty())
            <div id="tl-bp-closing-verify" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-flag-checkered mr-1"></i> Budgeting — Menunggu Verifikasi Closing
                        <span class="badge badge-secondary ml-1">{{ $budgetingClosingVerify->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:180px;">Code</th>
                                    <th scope="col" style="width:40%;">Description</th>
                                    <th scope="col" style="width:26%;">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Budget</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetingClosingVerify as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="bp:{{ $bp->id }}" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td>{{ $bpPoLabel($bp) }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $bp->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $bpAgingClass }}">{{ $bp->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        {{-- Verifikasi closing perlu melihat berkas closing, jadi
                                             barisnya membuka Budget Plan itu di halaman Budgeting. --}}
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ url('/budget-plan') }}?open={{ $bp->id }}" class="ts-action-primary"
                                               title="Review closing di halaman Budgeting">
                                                <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Review</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- BUDGETING — REJECTED, MANAGER REVISE KE DRAFT --}}
            @if($budgetingRejected->isNotEmpty())
            <div id="tl-bp-rejected" class="card card-danger card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-coins mr-1"></i> Budgeting — Ditolak (Revise ke Draft)
                        <span class="badge badge-secondary ml-1">{{ $budgetingRejected->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:180px;">Code</th>
                                    <th scope="col" style="width:28%;">Description</th>
                                    <th scope="col" style="width:18%;">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Budget</th>
                                    <th scope="col" style="width:24%;">Alasan Ditolak</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetingRejected as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="bp:{{ $bp->id }}" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
                                    {{-- Todo #4 — kode membuka rincian Budget Plan di
                                         tempat, sehingga approver tidak perlu pindah ke
                                         halaman Budgeting hanya untuk melihat isinya
                                         sebelum memutuskan. --}}
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td>{{ $bpPoLabel($bp) }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $bp->amount_formatted }}</strong></td>
                                    <td class="text-muted">{{ Str::limit($bp->approver_notes ?? '-', 100) }}</td>
                                    <td class="text-center text-nowrap {{ $bpAgingClass }}">{{ $bp->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <button type="button" class="ts-action-primary is-neutral todo-bp-revise"
                                                    data-id="{{ $bp->id }}" data-action="{{ route('budget-plan.manager-revise', $bp->id) }}"
                                                    title="Revise → Draft">
                                                <i class="fas fa-undo" aria-hidden="true"></i><span class="ts-al">Revise</span>
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
            @endif

            {{-- TREASURY — MENUNGGU PROSES (batch PENDING_APPROVER, ditandai "Submitted" -> jadi Approved/Done setelah diproses CEO) --}}
            {{-- TREASURY — BUAT BATCH PEMBAYARAN (CR APPROVED, belum masuk batch manapun) --}}
            @if($treasuryBatchCreate->isNotEmpty() || $treasuryBatchSecond->isNotEmpty())
            <div id="tl-batch-create" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-layer-group mr-1"></i> Treasury — Buat Batch Pembayaran
                        <span class="badge badge-secondary ml-1">{{ $treasuryBatchCreate->count() + $treasuryBatchSecond->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    {{-- Todo #5 — batch dibuat DI SINI, bukan dengan pindah ke
                         halaman Treasury lalu memilih ulang CR yang sama dari
                         daftar panjang. CR-nya sudah ada di depan mata; yang
                         kurang cuma cara memilih beberapa sekaligus. --}}
                    <div class="todo-bulk-bar" id="tlBatchBar">
                        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                            <div style="font-size:12.5px;color:var(--text);">
                                <strong id="tlBatchCount">0</strong> CR dipilih<span id="tlBatchAmount"></span>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="tlBatchOpen" disabled>
                                <i class="fas fa-layer-group mr-1"></i>Buat Batch Pembayaran
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;">
                                        <input type="checkbox" id="tlBatchCheckAll" title="Pilih semua">
                                    </th>
                                    <th scope="col" class="text-nowrap" style="width:190px;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Dikelompokkan per PURCHASE ORDER, sama seperti worklist
                                     approval (lihat _worklist_section.blade.php), supaya satu
                                     batch bisa disusun per PO tanpa memungut CR satu-satu dari
                                     daftar panjang.

                                     Grupnya dirender TERTUTUP seperti worklist lain. Supaya
                                     hitungan batch tetap jujur: mencentang grup yang tertutup
                                     membukanya dulu, dan menutup grup melepas centang isinya
                                     (lihat handler .tl-batch-check-group / .todo-po-row). --}}
                                @foreach($treasuryBatchGroups as $poi => $poGroup)
                                <tr class="todo-po-row" data-target="tlbatch-po-{{ $poi }}">
                                    <td>
                                        <input type="checkbox" class="tl-batch-check-group"
                                               data-group="tlbatch-po-{{ $poi }}" title="Pilih semua CR pada PO ini">
                                    </td>
                                    <td colspan="3">
                                        <i class="fas fa-chevron-right todo-caret mr-2"></i>
                                        <strong>{{ $poGroup['po'] }}</strong>
                                        {{-- Manager hanya ikut ditulis kalau ada. Budget cost center (Overhead/
                                                 Payroll) tidak punya PM, dan "(NAMA - -)" cuma menambah
                                                 tanda hubung yang tidak mewakili apa pun. --}}
                                    @php $poSub = $poGroup['customer'] . (($poGroup['pm'] && $poGroup['pm'] !== '-') ? ' - ' . $poGroup['pm'] : ''); @endphp
                                    <span class="text-muted" style="font-size:11px;">({{ $poSub }})</span>
                                        <span class="badge badge-secondary ml-1">{{ $poGroup['qty'] }} CR</span>
                                    </td>
                                    <td class="text-right text-nowrap" style="font-weight:700;">
                                        {{ number_format($poGroup['amount'], 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>

                                @foreach($poGroup['plans'] as $group)
                                @foreach($group['items'] as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' '
                                        . ($cr->beneficiary?->nickname ?? '') . ' ' . $poGroup['po']);
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                @endphp
                                <tr class="todo-row tlbatch-po-{{ $poi }}" data-export-key="cr:{{ $cr->id }}" style="display:none;" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
                                    <td>
                                        <input type="checkbox" class="tl-batch-check"
                                               value="{{ $cr->id }}" data-amount="{{ $cr->amount }}">
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 150) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                </tr>
                                @endforeach
                                @endforeach
                                @endforeach

                                {{-- SECOND PAYMENT yang sudah Second Approve — kewajiban
                                     bayar yang menunggu Treasury sama seperti CR APPROVED,
                                     jadi ia ikut bisa dicentang dan dibatch dari sini.
                                     Dipisahkan pada grupnya sendiri karena yang dikirim ke
                                     store() bukan id CR melainkan id Second Payment
                                     (second_payment_ids[]) — lihat data-kind di bawah. --}}
                                @if($treasuryBatchSecond->isNotEmpty())
                                <tr class="todo-po-row" data-target="tlbatch-second">
                                    <td>
                                        <input type="checkbox" class="tl-batch-check-group"
                                               data-group="tlbatch-second" title="Pilih semua Second Approval">
                                    </td>
                                    <td colspan="3">
                                        <i class="fas fa-chevron-right todo-caret mr-2"></i>
                                        <strong>Second Approval</strong>
                                        <span class="text-muted" style="font-size:11px;">(sudah Second Approve)</span>
                                        <span class="badge badge-secondary ml-1">{{ $treasuryBatchSecond->count() }}</span>
                                    </td>
                                    <td class="text-right text-nowrap" style="font-weight:700;">
                                        {{ number_format($treasuryBatchSecond->sum('amount'), 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>
                                @foreach($treasuryBatchSecond as $sp)
                                @php
                                    $spSearch = strtolower($sp->cr_code . ' ' . $sp->cr_desc . ' ' . $sp->beneficiary_label);
                                @endphp
                                <tr class="todo-row tlbatch-second" data-export-key="sp:{{ $sp->id }}" style="display:none;" data-po="{{ $sp->po_label }}" data-search="{{ $spSearch }}">
                                    <td>
                                        <input type="checkbox" class="tl-batch-check" data-kind="second"
                                               value="{{ $sp->id }}" data-amount="{{ $sp->amount }}">
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $sp->budget_request_id }}">{{ $sp->cr_code }}</button>
                                    </td>
                                    <td>{{ Str::limit($sp->cr_desc, 150) }}</td>
                                    <td class="text-nowrap">{{ $sp->beneficiary_label }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $sp->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap text-muted">-</td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="4" class="text-right">Subtotal</th>
                                    <th class="text-right">{{ number_format($treasuryBatchCreate->sum('amount') + $treasuryBatchSecond->sum('amount'), 0, ',', '.') }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($treasuryProcess->isNotEmpty())
            <div id="tl-batch-process" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section todo-worklist" data-section="batch-process">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-money-check-alt mr-1"></i> Treasury — Menunggu Proses
                        <span class="badge badge-secondary ml-1">{{ $treasuryProcess->count() }}</span>
                    </h3>
                </div>
                @include('todo-list._bulk_bar', ['key' => 'batch-process', 'approveLabel' => 'Proses', 'unit' => 'batch'])
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 todo-fit-table" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="todo-col-fit">Batch Code</th>
                                    <th scope="col" class="todo-col-desc">Deskripsi</th>
                                    <th scope="col" class="todo-col-fit">Tanggal</th>
                                    <th scope="col" class="todo-col-fit">Type</th>
                                    <th scope="col" class="todo-col-fit">Kategori</th>
                                    <th scope="col" class="todo-col-fit text-right">Nilai Batch</th>
                                    <th scope="col" class="todo-col-fit text-center">Aging</th>
                                    <th scope="col" class="todo-col-fit text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($treasuryProcess as $batch)
                                @php $dtAgingClass = $batch->aging_days >= 3 ? 'text-danger' : ($batch->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="dt:{{ $batch->id }}" data-search="{{ strtolower($batch->code . ' ' . $batch->description) }}">
                                    <td><input type="checkbox" class="todo-check" value="{{ $batch->id }}" data-amount="{{ $batch->captured_amount }}" aria-label="Pilih {{ $batch->code }}"></td>
                                    <td class="todo-col-fit"><a href="{{ route('daily-transaction.show', $batch->id) }}" class="cr-number-btn">{{ $batch->code }}</a></td>
                                    <td class="todo-col-desc">{{ $batch->description ?? '-' }}</td>
                                    <td class="todo-col-fit">{{ $batch->date?->format('d M Y') ?? '-' }}</td>
                                    <td class="todo-col-fit"><span class="badge badge-{{ $batch->type_color }} px-2 py-1" style="font-size:10px;">{{ $batch->type_label }}</span></td>
                                    <td class="todo-col-fit">
                                        @if($batch->sourceFund)
                                            <span class="badge badge-secondary px-2 py-1" style="font-size:10px;">{{ $batch->sourceFund->name }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="todo-col-fit text-right col-raw-total" data-raw="{{ $batch->captured_amount }}"><strong>{{ $batch->captured_amount_formatted }}</strong></td>
                                    <td class="todo-col-fit text-center {{ $dtAgingClass }}">{{ $batch->aging_label }}</td>
                                    <td class="todo-col-fit text-center">
                                        <div class="action-buttons ts-actions">

                                            <form action="{{ route('daily-transaction.approve', $batch->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button aria-label="Proses/Done" type="submit"
                                                        class="ts-action-primary" title="Proses/Done">
                                                    <i class="fas fa-check" aria-hidden="true"></i><span class="ts-al">Proses</span>
                                                </button>
                                            </form>

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk batch">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <button type="button" class="dropdown-item is-danger todo-dt-reject"
                                                        data-id="{{ $batch->id }}" data-action="{{ route('daily-transaction.reject-batch', $batch->id) }}">
                                                    <i class="fas fa-times"></i>Reject
                                                </button>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="6" class="text-right">Subtotal</th>
                                    <th class="text-right">{{ number_format($treasuryProcess->sum('captured_amount'), 0, ',', '.') }}</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- TREASURY — DRAFT BELUM DIAJUKAN KE CEO (isinya sudah dipesan, tidak muncul lagi di Buat Batch) --}}
            @if($treasuryDraftSubmit->isNotEmpty())
            <div id="tl-batch-draft" class="card card-info card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-paper-plane mr-1"></i> Treasury — Draft Belum Diajukan
                        <span class="badge badge-secondary ml-1">{{ $treasuryDraftSubmit->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 todo-fit-table" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="todo-col-fit">Batch Code</th>
                                    <th scope="col" class="todo-col-desc">Deskripsi</th>
                                    <th scope="col" class="todo-col-fit">Tanggal</th>
                                    <th scope="col" class="todo-col-fit">Kategori</th>
                                    <th scope="col" class="todo-col-fit text-center">Item</th>
                                    <th scope="col" class="todo-col-fit text-right">Nilai Batch</th>
                                    <th scope="col" class="todo-col-fit text-center">Aging</th>
                                    <th scope="col" class="todo-col-fit text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($treasuryDraftSubmit as $batch)
                                @php $dtAgingClass = $batch->aging_days >= 3 ? 'text-danger' : ($batch->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="dt:{{ $batch->id }}" data-search="{{ strtolower($batch->code . ' ' . $batch->description) }}">
                                    <td class="todo-col-fit"><a href="{{ route('daily-transaction.show', $batch->id) }}" class="cr-number-btn">{{ $batch->code }}</a></td>
                                    <td class="todo-col-desc">{{ $batch->description ?? '-' }}</td>
                                    <td class="todo-col-fit">{{ $batch->date?->format('d M Y') ?? '-' }}</td>
                                    <td class="todo-col-fit">
                                        @if($batch->sourceFund)
                                            <span class="badge badge-secondary px-2 py-1" style="font-size:10px;">{{ $batch->sourceFund->name }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="todo-col-fit text-center">{{ $batch->included_items_count }}</td>
                                    <td class="todo-col-fit text-right col-raw-total" data-raw="{{ $batch->captured_amount }}"><strong>{{ $batch->captured_amount_formatted }}</strong></td>
                                    <td class="todo-col-fit text-center {{ $dtAgingClass }}">{{ $batch->aging_label }}</td>
                                    <td class="todo-col-fit text-center">
                                        <a href="{{ route('daily-transaction.show', $batch->id) }}" class="btn btn-outline-primary btn-sm" title="Buka draft untuk diajukan ke CEO">
                                            <i class="fas fa-paper-plane mr-1"></i>Ajukan
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="5" class="text-right">Subtotal</th>
                                    <th class="text-right">{{ number_format($treasuryDraftSubmit->sum('captured_amount'), 0, ',', '.') }}</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- TREASURY — SIAP DIBAYAR (status DONE, tinggal ditandai Paid — perlu upload bukti transfer, jadi link-out) --}}
            @if($treasuryReadyToPay->isNotEmpty())
            <div id="tl-batch-pay" class="card card-info card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Treasury — Siap Dibayar
                        <span class="badge badge-secondary ml-1">{{ $treasuryReadyToPay->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 todo-fit-table" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="todo-col-fit">Batch Code</th>
                                    <th scope="col" class="todo-col-desc">Deskripsi</th>
                                    <th scope="col" class="todo-col-fit">Tanggal</th>
                                    <th scope="col" class="todo-col-fit">Type</th>
                                    <th scope="col" class="todo-col-fit">Kategori</th>
                                    <th scope="col" class="todo-col-fit text-right">Nilai Batch</th>
                                    <th scope="col" class="todo-col-fit text-center">Aging</th>
                                    <th scope="col" class="todo-col-fit text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($treasuryReadyToPay as $batch)
                                @php $dtAgingClass = $batch->aging_days >= 3 ? 'text-danger' : ($batch->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="dt:{{ $batch->id }}" data-search="{{ strtolower($batch->code . ' ' . $batch->description) }}">
                                    <td class="todo-col-fit"><a href="{{ route('daily-transaction.show', $batch->id) }}" class="cr-number-btn">{{ $batch->code }}</a></td>
                                    <td class="todo-col-desc">{{ $batch->description ?? '-' }}</td>
                                    <td class="todo-col-fit">{{ $batch->date?->format('d M Y') ?? '-' }}</td>
                                    <td class="todo-col-fit"><span class="badge badge-{{ $batch->type_color }} px-2 py-1" style="font-size:10px;">{{ $batch->type_label }}</span></td>
                                    <td class="todo-col-fit">
                                        @if($batch->sourceFund)
                                            <span class="badge badge-secondary px-2 py-1" style="font-size:10px;">{{ $batch->sourceFund->name }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="todo-col-fit text-right col-raw-total" data-raw="{{ $batch->captured_amount }}"><strong>{{ $batch->captured_amount_formatted }}</strong></td>
                                    <td class="todo-col-fit text-center {{ $dtAgingClass }}">{{ $batch->aging_label }}</td>
                                    <td class="todo-col-fit text-center">
                                        <a href="{{ route('daily-transaction.show', $batch->id) }}" class="btn btn-outline-primary btn-sm" title="Mark Paid">
                                            <i class="fas fa-hand-holding-usd mr-1"></i>Mark Paid
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="5" class="text-right">Subtotal</th>
                                    <th class="text-right">{{ number_format($treasuryReadyToPay->sum('captured_amount'), 0, ',', '.') }}</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- COST REGISTER — MENUNGGU APPROVAL APPROVER (VERIFIED) --}}
            @if($costRegisterApprove->isNotEmpty())
            <div id="tl-reg-approve" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Cost Register — Menunggu Approval
                        <span class="badge badge-secondary ml-1">{{ $costRegisterApprove->count() }}</span>
                    </h3>
                    <div class="text-muted small mt-1">Diajukan Finance; Approver menyetujui atau mengembalikan ke Draft di halaman Cost Register.</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">PO</th>
                                    <th scope="col">Tipe / Catatan</th>
                                    <th scope="col" class="text-nowrap">Beneficiary</th>
                                    <th scope="col" class="text-nowrap">Input By</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Nominal</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:110px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($costRegisterApprove as $reg)
                                @php $regAgingClass = $reg->aging_days >= 3 ? 'text-danger' : ($reg->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="reg:{{ $reg->id }}" data-search="{{ strtolower(($reg->project?->code ?? '') . ' ' . ($reg->project?->customer?->name ?? '') . ' ' . $reg->type_label . ' ' . ($reg->notes ?? '') . ' ' . ($reg->beneficiary?->nickname ?? '')) }}">
                                    <td class="text-nowrap">{{ $reg->project?->code ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $reg->type_color }} px-2 py-1" style="font-size:10px;">{{ $reg->type_label }}</span>
                                        @if($reg->notes) <span class="text-muted">{{ Str::limit($reg->notes, 80) }}</span> @endif
                                    </td>
                                    <td class="text-nowrap">{{ $reg->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $reg->inputBy?->name ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $reg->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $regAgingClass }}" title="Sejak diajukan">{{ $reg->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        {{-- Approve / Revisi langsung dari sini memakai endpoint yang sama dengan
                                             halaman Cost Register (CostRegisterController::approve()/revise()),
                                             termasuk cek role & status di sana. Cost Register tidak punya status
                                             REJECTED: "tolak" = kembalikan ke Draft dengan catatan wajib. --}}
                                        <div class="action-buttons ts-actions">
                                            <button type="button" class="ts-action-primary td-reg-approve"
                                                    data-id="{{ $reg->id }}" data-label="{{ ($reg->project?->code ?? '-') . ' · ' . $reg->amount_formatted }}"
                                                    title="Approve">
                                                <i class="fas fa-check" aria-hidden="true"></i><span class="ts-al">Approve</span>
                                            </button>

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk Cost Register {{ $reg->project?->code ?? $reg->id }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <button type="button" class="dropdown-item td-reg-revise"
                                                        data-id="{{ $reg->id }}" data-label="{{ ($reg->project?->code ?? '-') . ' · ' . $reg->amount_formatted }}">
                                                    <i class="fas fa-undo"></i>Kembalikan ke Draft
                                                </button>
                                                <a href="{{ route('cost-register.index') }}?date_from={{ $reg->created_at?->format('Y-m-d') }}" class="dropdown-item">
                                                    <i class="fas fa-eye"></i>Detail
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- COST REGISTER — APPROVED, BELUM MASUK BATCH AKTIF --}}
            @if($costRegisterBatch->isNotEmpty())
            <div id="tl-reg-batch" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-layer-group mr-1"></i> Cost Register — Menunggu Dibatch Treasury
                        <span class="badge badge-secondary ml-1">{{ $costRegisterBatch->count() }}</span>
                    </h3>
                    <div class="text-muted small mt-1">Sudah disetujui Approver, belum masuk batch pembayaran aktif.</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">PO</th>
                                    <th scope="col">Tipe / Catatan</th>
                                    <th scope="col" class="text-nowrap">Beneficiary</th>
                                    <th scope="col" class="text-nowrap">Input By</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Nominal</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:110px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($costRegisterBatch as $reg)
                                @php $regAgingClass = $reg->aging_days >= 3 ? 'text-danger' : ($reg->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="reg:{{ $reg->id }}" data-search="{{ strtolower(($reg->project?->code ?? '') . ' ' . ($reg->project?->customer?->name ?? '') . ' ' . $reg->type_label . ' ' . ($reg->notes ?? '') . ' ' . ($reg->beneficiary?->nickname ?? '')) }}">
                                    <td class="text-nowrap">{{ $reg->project?->code ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $reg->type_color }} px-2 py-1" style="font-size:10px;">{{ $reg->type_label }}</span>
                                        @if($reg->notes) <span class="text-muted">{{ Str::limit($reg->notes, 80) }}</span> @endif
                                    </td>
                                    <td class="text-nowrap">{{ $reg->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $reg->inputBy?->name ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $reg->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $regAgingClass }}" title="Sejak disetujui">{{ $reg->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('daily-transaction.create') }}" class="ts-action-primary" title="Buat Batch">
                                                <i class="fas fa-layer-group" aria-hidden="true"></i><span class="ts-al">Buat Batch</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- COLLECTION — WAITING INVOICE (No. PO sudah BAST, belum ditagih sama sekali/penuh; reminder, bukan approval) --}}
            @if($collectionWaitingInvoice->isNotEmpty())
            <div id="tl-collection" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Collection — Waiting Invoice
                        <span class="badge badge-secondary ml-1">{{ $collectionWaitingInvoice->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">No. PO</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:135px;">Sisa Ditagih</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($collectionWaitingInvoice as $poNumber)
                                @php $poAgingClass = $poNumber->aging_days >= 3 ? 'text-danger' : ($poNumber->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="ponum:{{ $poNumber->id }}" {{ $poAttrs($poNumber->project, null, $poNumber->remainingValue()) }} data-search="{{ strtolower($poNumber->po_number . ' ' . ($poNumber->project?->name ?? '') . ' ' . ($poNumber->project?->customer?->name ?? '')) }}">
                                    {{-- Badge status di baris sendiri: kolom No. PO sempit (width tetap) sehingga badge sebaris menimpa teks. --}}
                                    <td>{{ $poNumber->po_number }}<div><span class="badge badge-{{ $poNumber->status_color }}" style="font-size:9px;">{{ $poNumber->status_label }}</span></div></td>
                                    <td>{{ $poNumber->project?->name ?? '-' }} <span class="text-muted">({{ $poNumber->project?->customer?->name ?? '-' }})</span></td>
                                    <td class="text-right text-nowrap"><strong>{{ $poNumber->remaining_value_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $poAgingClass }}">{{ $poNumber->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">

                                            <a href="{{ route('invoice-cash-receipt.create') }}"
                                               class="ts-action-primary" title="Buat Invoice">
                                                <i class="fas fa-plus" aria-hidden="true"></i><span class="ts-al">Buat Invoice</span>
                                            </a>

                                            @if($poNumber->project_id)
                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $poNumber->po_number }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <a href="{{ route('project.index') }}?open={{ $poNumber->project_id }}"
                                                   target="_blank" rel="noopener" class="dropdown-item">
                                                    <i class="fas fa-eye"></i>Detail PO
                                                </a>
                                            </div>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- TROUBLE TICKET — REVIEW PENGAJUAN BIAYA (supervisor team teknisi; flat list, tautan ke tab Pengajuan) --}}
            @if($ticketRequestReview->isNotEmpty())
            <div id="tl-tt-request" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Trouble Ticket — Review Pengajuan
                        <span class="badge badge-secondary ml-1">{{ $ticketRequestReview->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">Tiket</th>
                                    <th scope="col">Site / Pengajuan</th>
                                    <th scope="col" class="text-nowrap">Pemohon</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Total</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ticketRequestReview as $req)
                                @php $reqAgingClass = $req->aging_days >= 3 ? 'text-danger' : ($req->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="ttreq:{{ $req->id }}" data-search="{{ strtolower(($req->ticket?->ticket_number ?? '') . ' ' . ($req->ticket?->site?->sitename ?? '') . ' ' . $req->description . ' ' . ($req->requester?->name ?? '')) }}">
                                    <td>{{ $req->ticket?->ticket_number ?? '-' }}</td>
                                    <td>{{ $req->ticket?->site?->sitename ?? '-' }}<div class="text-muted">{{ $req->requestType?->name }} — {{ \Illuminate\Support\Str::limit($req->description, 80) }}</div></td>
                                    <td class="text-nowrap">{{ $req->requester?->name ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ number_format((int) $req->total, 0, ',', '.') }}</strong></td>
                                    <td class="text-center text-nowrap {{ $reqAgingClass }}">{{ $req->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            @if($req->ticket)
                                            <a href="{{ route('trouble-ticket.show', $req->ticket) }}?tab=pengajuan" class="ts-action-primary" title="Review pengajuan">
                                                <i class="fas fa-clipboard-check" aria-hidden="true"></i><span class="ts-al">Review</span>
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- INVOICE — MENUNGGU VERIFIKASI --}}
            {{-- PURCHASE ORDER — MENUNGGU APPROVAL FINANCE --}}
            @if($poSubmitted->isNotEmpty())
            <div id="tl-po-submitted" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-signature mr-1"></i> Purchase Order — Menunggu Approval Finance
                        <span class="badge badge-secondary ml-1">{{ $poSubmitted->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">PO</th>
                                    <th scope="col">Scope of Work / Customer</th>
                                    <th scope="col" class="text-nowrap">Manager</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Nilai PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:90px;">No. PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($poSubmitted as $po)
                                @php $poAgingClass = $po->aging_days >= 3 ? 'text-danger' : ($po->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="po:{{ $po->id }}" data-search="{{ strtolower($po->code . ' ' . $po->name . ' ' . ($po->customer?->name ?? '') . ' ' . ($po->manager?->name ?? '')) }}">
                                    <td class="text-nowrap">
                                        {{-- q= mengisi pencarian di halaman Purchase Order, open=
                                             membuka modal detailnya — PO yang dituju langsung
                                             terlihat, bukan tenggelam di daftar. --}}
                                        <a href="{{ route('project.index') }}?q={{ urlencode($po->code) }}&open={{ $po->id }}" class="cr-number-btn">{{ $po->code }}</a>
                                    </td>
                                    <td>{{ $po->name }} <span class="text-muted">({{ $po->customer?->name ?? '-' }})</span></td>
                                    {{-- Username, bukan nama lengkap: kolom ini cuma penanda
                                         "punya siapa", dan nama lengkap membuat barisnya melebar
                                         tanpa menambah informasi. --}}
                                    <td class="text-nowrap">{{ $po->manager?->username ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $po->value_formatted }}</strong></td>
                                    {{-- Jumlah No. PO ditampilkan sebagai konteks: saat di-approve nanti,
                                         status Active otomatis dipilih hanya bila total No. PO sudah pas
                                         dengan kontrak, selain itu Waiting PO. --}}
                                    <td class="text-center text-nowrap">
                                        @if($po->po_numbers_count > 0)
                                            <span class="badge badge-info">{{ $po->po_numbers_count }}</span>
                                        @else
                                            <span class="text-muted" title="Belum ada No. PO — hanya bisa disetujui ke Waiting PO">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap {{ $poAgingClass }}">{{ $po->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('project.index') }}?q={{ urlencode($po->code) }}&open={{ $po->id }}"
                                               class="ts-action-primary" title="Review PO">
                                                <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Review</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- PURCHASE ORDER — MENUNGGU APPROVAL APPROVER (VERIFIED) --}}
            @if($poVerified->isNotEmpty())
            <div id="tl-po-verified" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-signature mr-1"></i> Purchase Order — Menunggu Approval Approver
                        <span class="badge badge-secondary ml-1">{{ $poVerified->count() }}</span>
                    </h3>
                    <div class="text-muted small mt-1">Budget Plan yang dibuat dari form PO ikut disetujui bersama PO.</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">PO</th>
                                    <th scope="col">Scope of Work / Customer</th>
                                    <th scope="col" class="text-nowrap">Manager</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Nilai PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:90px;">No. PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($poVerified as $po)
                                @php $poAgingClass = $po->aging_days >= 3 ? 'text-danger' : ($po->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="po:{{ $po->id }}" data-search="{{ strtolower($po->code . ' ' . $po->name . ' ' . ($po->customer?->name ?? '') . ' ' . ($po->manager?->name ?? '')) }}">
                                    <td class="text-nowrap">
                                        <a href="{{ route('project.index') }}?q={{ urlencode($po->code) }}&open={{ $po->id }}" class="cr-number-btn">{{ $po->code }}</a>
                                    </td>
                                    <td>{{ $po->name }} <span class="text-muted">({{ $po->customer?->name ?? '-' }})</span></td>
                                    <td class="text-nowrap">{{ $po->manager?->username ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $po->value_formatted }}</strong></td>
                                    <td class="text-center text-nowrap">
                                        @if($po->po_numbers_count > 0)
                                            <span class="badge badge-info">{{ $po->po_numbers_count }}</span>
                                        @else
                                            <span class="text-muted" title="Belum ada No. PO — hanya bisa disetujui ke Waiting PO">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap {{ $poAgingClass }}">{{ $po->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('project.index') }}?q={{ urlencode($po->code) }}&open={{ $po->id }}"
                                               class="ts-action-primary" title="Review PO">
                                                <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Review</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- PURCHASE ORDER — CLOSING MENUNGGU VERIFIKASI FINANCE (CLOSING) --}}
            @if($poClosingVerify->isNotEmpty())
            <div id="tl-po-closing" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-flag-checkered mr-1"></i> Purchase Order — Menunggu Verifikasi Closing
                        <span class="badge badge-secondary ml-1">{{ $poClosingVerify->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:150px;">PO</th>
                                    <th scope="col">Scope of Work / Customer</th>
                                    <th scope="col" class="text-nowrap">Manager</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:135px;">Nilai PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($poClosingVerify as $po)
                                @php $poAgingClass = $po->aging_days >= 3 ? 'text-danger' : ($po->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="po:{{ $po->id }}" data-search="{{ strtolower($po->code . ' ' . $po->name . ' ' . ($po->customer?->name ?? '') . ' ' . ($po->manager?->name ?? '')) }}">
                                    <td class="text-nowrap">
                                        <a href="{{ route('project.index') }}?q={{ urlencode($po->code) }}&open={{ $po->id }}" class="cr-number-btn">{{ $po->code }}</a>
                                    </td>
                                    <td>{{ $po->name }} <span class="text-muted">({{ $po->customer?->name ?? '-' }})</span></td>
                                    <td class="text-nowrap">{{ $po->manager?->username ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $po->value_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $poAgingClass }}">{{ $po->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('project.index') }}?q={{ urlencode($po->code) }}&open={{ $po->id }}"
                                               class="ts-action-primary" title="Review Closing PO">
                                                <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Review</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($invoiceVerify->isNotEmpty())
            <div id="tl-invoice-verify" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section todo-worklist" data-section="invoice-verify">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice mr-1"></i> Invoice — Menunggu Verifikasi
                        <span class="badge badge-secondary ml-1">{{ $invoiceVerify->count() }}</span>
                    </h3>
                </div>
                @include('todo-list._bulk_bar', ['key' => 'invoice-verify', 'approveLabel' => 'Verify', 'canReject' => false, 'unit' => 'invoice'])
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:155px;">Invoice</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceVerify as $inv)
                                @php $invAgingClass = $inv->aging_days >= 3 ? 'text-danger' : ($inv->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="inv:{{ $inv->id }}" {{ $poAttrs($inv->po, null, $inv->billed_amount) }} data-search="{{ strtolower($inv->code . ' ' . ($inv->po?->name ?? '') . ' ' . ($inv->po?->customer?->name ?? '')) }}">
                                    <td><input type="checkbox" class="todo-check" value="{{ $inv->id }}" data-amount="{{ $inv->billed_amount }}" aria-label="Pilih {{ $inv->code }}"></td>
                                    <td class="text-nowrap"><a href="{{ route('invoice-cash-receipt.show', $inv->id) }}" class="cr-number-btn">{{ $inv->code }}</a></td>
                                    <td>{{ $inv->po?->name ?? '-' }} <span class="text-muted">({{ $inv->po?->customer?->name ?? '-' }})</span></td>
                                    <td class="text-right text-nowrap"><strong>{{ $inv->billed_amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $invAgingClass }}">{{ $inv->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">

                                            <form action="{{ route('invoice-cash-receipt.verify', $inv->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button aria-label="Verify" type="submit"
                                                        class="ts-action-primary" title="Verify">
                                                    <i class="fas fa-check" aria-hidden="true"></i><span class="ts-al">Verify</span>
                                                </button>
                                            </form>

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $inv->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <a href="{{ route('invoice-cash-receipt.show', $inv->id) }}" class="dropdown-item">
                                                    <i class="fas fa-eye"></i>Detail
                                                </a>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- INVOICE — MENUNGGU APPROVAL --}}
            @if($invoiceApprove->isNotEmpty())
            <div id="tl-invoice-approve" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section todo-worklist" data-section="invoice-approve">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Invoice — Menunggu Approval
                        <span class="badge badge-secondary ml-1">{{ $invoiceApprove->count() }}</span>
                    </h3>
                </div>
                @include('todo-list._bulk_bar', ['key' => 'invoice-approve', 'approveLabel' => 'Approve', 'canReject' => false, 'unit' => 'invoice'])
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:155px;">Invoice</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:130px;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceApprove as $inv)
                                @php $invAgingClass = $inv->aging_days >= 3 ? 'text-danger' : ($inv->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-export-key="inv:{{ $inv->id }}" {{ $poAttrs($inv->po, null, $inv->billed_amount) }} data-search="{{ strtolower($inv->code . ' ' . ($inv->po?->name ?? '') . ' ' . ($inv->po?->customer?->name ?? '')) }}">
                                    <td><input type="checkbox" class="todo-check" value="{{ $inv->id }}" data-amount="{{ $inv->billed_amount }}" aria-label="Pilih {{ $inv->code }}"></td>
                                    <td class="text-nowrap"><a href="{{ route('invoice-cash-receipt.show', $inv->id) }}" class="cr-number-btn">{{ $inv->code }}</a></td>
                                    <td>{{ $inv->po?->name ?? '-' }} <span class="text-muted">({{ $inv->po?->customer?->name ?? '-' }})</span></td>
                                    <td class="text-right text-nowrap"><strong>{{ $inv->billed_amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $invAgingClass }}">{{ $inv->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">

                                            <form action="{{ route('invoice-cash-receipt.approve', $inv->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button aria-label="Approve" type="submit"
                                                        class="ts-action-primary" title="Approve">
                                                    <i class="fas fa-check" aria-hidden="true"></i><span class="ts-al">Approve</span>
                                                </button>
                                            </form>

                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $inv->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>
                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                <a href="{{ route('invoice-cash-receipt.show', $inv->id) }}" class="dropdown-item">
                                                    <i class="fas fa-eye"></i>Detail
                                                </a>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- COMING SOON — Accounting (belum ada modul nyata yang di-wire) --}}
            @if(!empty($comingSoonRoles))
            <div class="card shadow-sm mt-3" style="border-radius:var(--radius-lg);">
                <div class="card-body text-center py-4">
                    <div style="font-size:32px;color:var(--text-faint);"><i class="fas fa-tools"></i></div>
                    <div style="font-size:14px;font-weight:700;color:var(--text);margin-top:8px;">
                        {{ implode(', ', $comingSoonRoles) }} — Coming Soon
                    </div>
                    <div style="font-size:12.5px;color:var(--text-faint);margin-top:4px;">
                        Modul ini akan terhubung ke data nyata pada fase berikutnya.
                    </div>
                </div>
            </div>
            @endif

        @endif

    </div>
</div>

@include('todo-list._action_modals')

{{-- Todo #5 — BUAT BATCH PEMBAYARAN.
     Form ini POST ke route Treasury yang SUDAH ADA (daily-transaction.store),
     jadi seluruh aturannya — CR harus APPROVED, tidak boleh sudah masuk batch
     aktif, rekening tidak boleh Main Account — tetap ditegakkan di satu tempat
     yang sama. Tidak ada jalur simpan kedua yang bisa menyimpang. --}}
<div class="modal fade" id="mdlTlBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-layer-group mr-2" style="color:var(--accent);"></i>Buat Batch Pembayaran
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <form action="{{ route('daily-transaction.store') }}" method="POST" id="tlBatchForm">
                @csrf
                <input type="hidden" name="type" value="CASH_OUT">
                <div id="tlBatchIds"></div>

                <div class="modal-body" style="padding:22px 26px;">
                    <div class="alert alert-info py-2" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong id="tlBatchSummary">0 CR</strong> akan dimasukkan ke batch baru.
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Deskripsi Batch <span class="text-danger">*</span></label>
                        <input type="text" name="description" id="tlBatchDesc" class="form-control form-control-sm"
                               maxlength="255" placeholder="Mis. PEMBAYARAN VENDOR MINGGU INI" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Rekening Sumber <span class="text-danger">*</span></label>
                        <select name="source_fund_id" class="form-control form-control-sm" required>
                            <option value="">— Pilih Rekening —</option>
                            @foreach($batchSourceFunds as $sf)
                                <option value="{{ $sf->id }}">
                                    {{ $sf->name }} — {{ $sf->bank->name ?? '-' }} ({{ $sf->no_account ?? '-' }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Main Account tidak muncul di sini — rekening itu khusus untuk Top Up.</small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm" maxlength="500"></textarea>
                    </div>
                </div>

                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-check mr-1"></i> Buat Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Todo #4 — RINCIAN BUDGET PLAN.
     Isinya diambil dari endpoint yang SUDAH ADA (budget-plan/{id}/detail-json),
     yang juga dipakai halaman Budgeting. Membuat endpoint baru berarti dua
     sumber angka untuk satu Budget Plan, dan cepat atau lambat keduanya
     berbeda. --}}
<div class="modal fade" id="mdlTodoBp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <div>
                    <h5 class="modal-title text-white mb-0" id="todoBpTitle">Rincian Budget Plan</h5>
                    <div id="todoBpSubtitle" style="color:var(--text-faint);font-size:11px;margin-top:2px;"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body" style="max-height:78vh;overflow-y:auto;">
                <div id="todoBpLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="text-muted mt-2 mb-0">Memuat rincian...</p>
                </div>

                <div id="todoBpBody" style="display:none;">
                    <ul class="nav nav-tabs mb-3" id="todoBpTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#todoBpTabBudget" role="tab"><i class="fas fa-coins mr-1"></i>Budget</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#todoBpTabPo" role="tab"><i class="fas fa-file-signature mr-1"></i>PO</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#todoBpTabAtt" role="tab"><i class="fas fa-paperclip mr-1"></i>Attachment <span class="badge badge-secondary ml-1" id="todoBpAttCount">0</span></a></li>
                    </ul>

                    <div class="tab-content">
                        {{-- TAB BUDGET — rincian anggaran per Chart of Account. --}}
                        <div class="tab-pane fade show active" id="todoBpTabBudget" role="tabpanel">
                            <div class="row mb-3" id="todoBpKpi"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="font-size:12px;min-width:620px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th scope="col">Keterangan</th>
                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Satuan</th>
                                            <th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>
                                            <th scope="col" class="text-right text-nowrap" style="width:130px;">Budget</th>
                                        </tr>
                                    </thead>
                                    <tbody id="todoBpDetails"></tbody>
                                    <tfoot>
                                        <tr style="background:var(--surface-2);">
                                            <th colspan="3" class="text-right">Total</th>
                                            <th class="text-right text-nowrap" id="todoBpTotal">0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div id="todoBpNotesWrap" class="mt-3" style="display:none;">
                                <h6 class="font-weight-bold" style="font-size:12px;">Catatan</h6>
                                <p id="todoBpNotes" class="text-muted mb-0" style="font-size:12px;"></p>
                            </div>
                        </div>

                        {{-- TAB PO — ringkasan dari halaman Purchase Order (project/{id}/detail-json). --}}
                        <div class="tab-pane fade" id="todoBpTabPo" role="tabpanel">
                            <div id="todoBpPoBody"></div>
                        </div>

                        {{-- TAB ATTACHMENT — lampiran PO & No. PO dari halaman Purchase Order. --}}
                        <div class="tab-pane fade" id="todoBpTabAtt" role="tabpanel">
                            <div id="todoBpAttBody"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                <a href="#" id="todoBpOpenPage" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-external-link-alt mr-1"></i> Buka di Budgeting
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================== MODAL SUMMARY TASK ==============================
     Satu-satunya tampilan Summary Task sejak tab dengan nama sama dihapus:
     matrix Tahap (Role + nama tahap) x Modul. --}}
<div class="modal fade" id="mdlSummaryTask" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white mb-0"><i class="fas fa-chart-pie mr-2" style="color:var(--accent);"></i>Summary Task</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="max-height:78vh;overflow-y:auto;">
                <ul class="nav nav-tabs mb-3" id="summaryTabs" role="tablist" style="border-bottom:2px solid var(--border);">
                    <li class="nav-item">
                        <a class="nav-link active" id="tabSummaryTask" data-toggle="tab" href="#paneSummaryTask" role="tab"
                           style="font-size:12.5px;font-weight:600;"><i class="fas fa-tasks mr-1"></i>Summary Task</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tabSummaryRespon" data-toggle="tab" href="#paneSummaryRespon" role="tab"
                           style="font-size:12.5px;font-weight:600;"><i class="fas fa-stopwatch mr-1"></i>Summary Respontime</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="paneSummaryTask" role="tabpanel">
                        @include('todo-list._summary_task_list')
                    </div>
                    <div class="tab-pane fade" id="paneSummaryRespon" role="tabpanel">
                        @include('todo-list._summary_respontime')
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL VIEW BUDGET GABUNGAN — dipakai baris PO yang memayungi lebih dari
     satu Budget Plan. Isinya disalin dari modal per-plan oleh handler
     .todo-view-budget-group, jadi tidak ada duplikasi markup tabel di sini. --}}
<div class="modal fade" id="mdlTodoCoaMulti" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white mb-0" id="mdlTodoCoaMultiTitle">View Budget</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0" id="mdlTodoCoaMultiBody" style="max-height:75vh;overflow-y:auto;overflow-x:auto;"></div>
            <div class="modal-footer" style="border-top:1px solid var(--border);">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================== DETAIL DRAWER ==============================
     Komponen dan susunan yang sama dengan drawer detail di halaman Cost Request
     (budget-request/index): kepala putih (eyebrow kode · tipe + judul), tab
     Info / Request / Settlement / Log, log gaya chat dengan kotak catatan, dan
     lampiran yang dibuka di viewer selebar sisa layar di kiri drawer.
     Datanya dari endpoint yang sama (budget-request/{id}/detail-json). --}}
<x-drawer id="todoDrawer" label="Detail Cost Request" width="max(50vw, 560px)">
    <div id="todoDrawerContent" hidden>
        <ul class="nav nav-tabs mb-3" id="todoDetailTabs">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#todoPaneInfo"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#todoPaneRequest"><i class="fas fa-list-ul mr-1"></i> Request <span id="todoReqCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item" id="todoTabRealisasiLi"><a class="nav-link" data-toggle="tab" href="#todoPaneRealisasi"><i class="fas fa-receipt mr-1"></i> Settlement <span id="todoRealCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#todoPaneLog"><i class="fas fa-history mr-1"></i> Log</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="todoPaneInfo"></div>
            <div class="tab-pane fade" id="todoPaneRequest"></div>
            <div class="tab-pane fade" id="todoPaneRealisasi"></div>
            <div class="tab-pane fade" id="todoPaneLog">
                <div id="todoLogBody" class="cr-chat"></div>
                {{-- Kotak catatan gaya chat; disembunyikan JS saat CR berstatus Closed. --}}
                <form id="todoLogForm" class="cr-log-form" hidden>
                    <textarea id="todoLogInput" class="form-control" rows="2" maxlength="1000"
                              placeholder="Tulis catatan atau update…" aria-label="Catatan log"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary" id="todoLogSend"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
                </form>
                <div id="todoLogClosed" class="cr-log-closed" hidden><i class="fas fa-lock mr-1"></i> Cost Request sudah Closed — catatan baru tidak dapat ditambahkan.</div>
            </div>
        </div>
    </div>

    <div id="todoDrawerError" hidden></div>

    <div id="todoDrawerLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="card" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="4" />
    </div>

    {{-- Bar aksi workflow — isinya dibangun dari workflow_actions milik
         detail-json, jadi tombol yang tampil persis sama dengan yang
         diizinkan server untuk user & status ini. Kaki drawer disembunyikan
         saat CR tidak punya aksi apa pun bagi user ini. --}}
    <x-slot:footer>
        <div id="todoDrwActions" class="todo-drawer-actions"></div>
    </x-slot:footer>
</x-drawer>

@endsection

@push('styles')
<style>
/* Tujuan tautan "Perlu Tindakan" di Dashboard (#tl-<key>). Offset dipakai
   supaya judul kartu tidak tertutup header saat browser melompat ke sana. */
[id^="tl-"] { scroll-margin-top: 90px; }

@keyframes tl-sorot {
    from { box-shadow: 0 0 0 3px rgba(200,39,46,.45); }
    to   { box-shadow: 0 0 0 3px rgba(200,39,46,0); }
}
.tl-disorot { animation: tl-sorot 1.8s ease-out; }

@media (prefers-reduced-motion: reduce) {
    .tl-disorot { animation: none; box-shadow: 0 0 0 2px rgba(200,39,46,.45); }
}
</style>
<style>
.cr-number-btn { background:transparent;border:none;padding:2px 8px;border-radius:var(--radius-sm);color:var(--info);font-weight:700;font-size:12px;transition:background .15s ease;text-decoration:none;display:inline-block;cursor:pointer; }
.cr-number-btn:hover { background:rgba(37,99,235,0.1);color:#1d4ed8;text-decoration:none; }
.rv-nob-item { color:var(--warning) !important; }

.summary-num-link { font-weight:700;color:var(--accent-dark);text-decoration:none;padding:3px 10px;border-radius:var(--radius-sm);display:inline-block;transition:background .15s ease,color .15s ease;cursor:pointer; }
.summary-num-link:hover { color:var(--warning);background:rgba(201,126,10,0.12);text-decoration:none; }
.summary-total-cell { font-weight:700; }
.summary-total-cell.summary-total-pos { color:var(--text); }
.summary-total-cell.summary-total-zero { color:var(--text-faint); }

.todo-group-row { cursor:pointer;background:var(--surface-2) !important; }
.todo-group-row:hover { background:var(--surface-3) !important; }
/* Lapis PO (MN-03) — dibedakan dari lapis Budget Plan lewat bobot huruf dan
   latar yang sedikit lebih pekat, supaya hierarkinya terbaca tanpa indentasi
   yang berlebihan. */
/* Halaman ini bisa tergulir mendatar di lebar tablet: salah satu tabelnya
   lebih lebar dari layar, dan luberannya menembus sampai ke kontainer halaman
   walau tabelnya sendiri sudah punya penggulir (.table-responsive).
   Gulir mendatar di level HALAMAN selalu keliru di sini — yang perlu digulir
   adalah tabelnya, bukan seluruh layar, dan menggeser halaman justru membawa
   sidebar & header ikut bergeser. Ditutup di kontainer halaman saja, bukan di
   body, supaya tidak memengaruhi halaman lain. */
/* clip, bukan hidden: hidden membuat #todoApp (anak flex .main > .content)
   menjadi kotak gulir sendiri — batang gulirnya jadi menjorok dari tepi
   kanan/bawah layar. clip memotong luapan mendatar tanpa kotak gulir. */
#todoApp { overflow-x: clip; }

.todo-po-row { cursor:pointer;background:var(--surface-3) !important;font-weight:700; }
.todo-po-row:hover { filter:brightness(.97); }
.todo-caret { transition:transform .15s ease;font-size:10px;color:var(--text-faint); }
.todo-caret.rotated { transform:rotate(90deg); }

/* Tabel flat (Treasury/Invoice/dst) — semua kolom fit-content (nowrap)
   kecuali kolom Deskripsi, yang tetap boleh wrap. */
.todo-fit-table th.todo-col-fit,
.todo-fit-table td.todo-col-fit { white-space:nowrap;width:1%; }
.todo-fit-table th.todo-col-desc,
.todo-fit-table td.todo-col-desc { white-space:normal;min-width:220px; }

/* Judul pemisah antar Budget Plan pada modal View Budget gabungan */
.todo-multi-head {
    padding:10px 16px;background:var(--surface-2);border-top:1px solid var(--border);
    border-bottom:1px solid var(--border);font-size:12.5px;font-weight:700;color:var(--text);
    cursor:pointer;
}
.todo-multi-head:hover { background:var(--surface-3); }
#mdlTodoCoaMultiBody > .todo-multi-head:first-child { border-top:none; }

.todo-coa-row:hover { background:var(--surface-3) !important; }
.todo-coa-caret { transition:transform .15s ease; }
.todo-coa-caret.rotated { transform:rotate(90deg); }

/* Bulk bar — netral & ringan, bukan blok warna gelap */
.todo-bulk-bar { padding:8px 16px;background:var(--surface-2);border-top:1px solid var(--border);border-bottom:1px solid var(--border); }

/* Mobile: bulk bar menempel di bawah section, tombol aksi selebar sentuh */
@media (max-width:640px) {
    .todo-bulk-bar { position:sticky;bottom:0;z-index:5;padding:8px 12px;box-shadow:var(--shadow-sm); }
    .todo-worklist { padding-bottom:4px; }
    .todo-worklist .table-responsive { -webkit-overflow-scrolling:touch; }
    .todo-worklist .btn,
    .todo-worklist .btn-sm,
    .todo-bulk-bar .btn { min-height:40px;min-width:40px; }
    .todo-worklist input.todo-check,
    .todo-worklist input.todo-check-all,
    .todo-worklist input.todo-check-group { width:20px;height:20px; }

    /* Mode card (tesys-table.js lewat data-ts-card-scope di #todoApp): daftar
       card duduk di latar abu tipis supaya terpisah dari kepala section. */
    #todoApp .table-responsive:has(> table.ts-card-table) { padding:10px 10px 0;background:var(--surface-2); }
    /* .p-0 dikalahkan `.card-body { padding … !important }` di tesys-ui-luxe.css;
       di HP padding itu dobel dengan padding daftar card di atas. */
    body.tesys-ui #todoApp .card-body.p-0 { padding:0 !important; }
    #todoApp .table-responsive > .ts-card-summary { margin-top:0; }

    /* Header tabel disembunyikan di mode card, tapi checkbox "pilih semua"
       (bulk approve, buat batch) harus tetap terjangkau: hanya sel itu yang tampil. */
    body.tesys-ui #todoApp table.ts-card-table:not(#ts-card) > thead:has(th > input[type="checkbox"]) {
        position:static;width:auto;height:auto;overflow:visible;clip:auto;display:block;margin-bottom:8px;
    }
    body.tesys-ui #todoApp table.ts-card-table:not(#ts-card) > thead:has(th > input[type="checkbox"]) > tr { display:block; }
    body.tesys-ui #todoApp table.ts-card-table:not(#ts-card) > thead:has(th > input[type="checkbox"]) th { display:none; }
    body.tesys-ui #todoApp table.ts-card-table:not(#ts-card) > thead:has(th > input[type="checkbox"]) th:has(> input[type="checkbox"]) {
        display:flex;align-items:center;gap:8px;width:auto !important;padding:0;border:0;background:transparent;
        font-size:12px;font-weight:600;color:var(--text-dim);text-transform:none;letter-spacing:0;
    }
    body.tesys-ui #todoApp table.ts-card-table:not(#ts-card) > thead:has(th > input[type="checkbox"]) th:has(> input[type="checkbox"])::after { content:'Pilih semua'; }

    /* Tabel yang TIDAK jadi card (header bertingkat dsb.): lebar kolom yang
       dikunci (table-layout:fixed) membuat kolom tanpa lebar menyusut di bawah
       nol dan teksnya saling menimpa. Di HP kolom mengikuti isi, digulir. */
    #todoApp .card-body table:not(.ts-card-table) { table-layout:auto;min-width:560px; }

    /* Kepala section: judul, jumlah, dan keterangan turun baris dengan rapi. */
    #todoApp .card-header { padding:12px 14px !important; }
    #todoApp .card-header .card-title {
        display:flex;flex-wrap:wrap;align-items:center;gap:4px 6px;
        font-size:14px;line-height:1.3;
    }
    #todoApp .card-header .card-title > i { margin-right:2px !important; }
    #todoApp .card-header .card-title .badge { margin-left:0 !important; }
    #todoApp .card-header .card-title .text-muted { margin-left:0 !important; }

    /* Tab Assignment: dua tab dibagi rata, label boleh dua baris (badge
       jumlah tetap terlihat), tanpa batang gulir. */
    #assignmentTabs { flex-wrap:nowrap;overflow:hidden; }
    #assignmentTabs .nav-item { flex:1 1 0;min-width:0; }
    #assignmentTabs .nav-link { text-align:center;padding:8px 6px;white-space:normal;font-size:12.5px;line-height:1.3; }

    /* Padding kartu: dua lapis 18px (.content + .card-body) memakan lebar
       layar HP. Satu ukuran ringkas untuk semua kartu di halaman ini. */
    body.tesys-ui #todoApp .card-body:not(.p-0) { padding:12px !important; }
    /* tesys-ui.css memberi .card padding 16px 18px DI LUAR padding .card-body. */
    #todoApp .card { padding:0; }

    /* Ringkasan angka: lebih rapat, angka tidak memaksa kartu setinggi layar. */
    .todo-kpi > [class*="col-"] { margin-bottom:10px !important; }
    .todo-kpi .card-body > div:last-child { font-size:20px !important;line-height:1.2; }
    .todo-kpi { margin-left:-5px;margin-right:-5px; }
    .todo-kpi > [class*="col-"] { padding-left:5px;padding-right:5px; }

    /* Toolbar: pencarian selebar layar, tombol aksi berbagi satu baris. */
    .todo-toolbar, .todo-toolbar-filters { width:100%; }
    .todo-toolbar .todo-search { width:100% !important; }
    .todo-toolbar-actions { margin-left:0 !important;width:100%; }
    .todo-toolbar-actions > .btn { flex:1 1 auto; }

    /* ── Drawer detail CR: lebar panel sudah selebar layar (x-drawer);
       tombol aksi berbagi satu baris selebar sentuh. ── */
    #todoDrawer .todo-drawer-actions .btn { flex:1 1 auto;min-height:40px; }
    #todoDrawer .ts-drawer-foot { padding-bottom:calc(13px + env(safe-area-inset-bottom)); }

    /* ── Modal View Budget (mode card via TesysTable, lihat show.bs.modal):
       judul tidak menabrak tombol tutup, angka dalam grid dua kolom. ── */
    .modal[id^="mdlTodoCoa"] .modal-header { padding:12px 14px; }
    .modal[id^="mdlTodoCoa"] .modal-header > div,
    .modal[id^="mdlTodoCoa"] .modal-header > .modal-title { flex:1 1 auto;min-width:0;padding-right:8px; }
    .modal[id^="mdlTodoCoa"] .modal-title { font-size:14.5px;line-height:1.35; }
    .modal[id^="mdlTodoCoa"] .modal-body { padding:10px !important;background:var(--surface-2);max-height:calc(100vh - 150px) !important; }
    .modal[id^="mdlTodoCoa"] .todo-multi-head { margin:0 -10px; }

    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group) {
        display:grid !important;grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:12px;padding:8px 12px;
    }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group) > td {
        flex-direction:column;align-items:flex-start;justify-content:flex-start;gap:1px;
        text-align:left !important;border-bottom:0;padding:4px 0 !important;
    }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group) > td::before {
        flex:none;max-width:none;font-size:10.5px;
    }
    /* Judul (Chart of Account / sub-item / deskripsi CR) selebar card. */
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group) > td.ts-card-title {
        grid-column:1 / -1;order:-1;flex-direction:row;flex-wrap:wrap;align-items:center;gap:4px;
        padding:2px 0 8px !important;margin-bottom:2px;border-bottom:1px solid var(--border);
    }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group):has(> td.ts-card-status) > td.ts-card-title { padding-right:92px !important; }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group) > td.ts-card-title .badge { margin-left:0 !important; }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr:not(.ts-card-group) > td.ts-card-actions {
        grid-column:1 / -1;flex-direction:row;justify-content:flex-end;margin-top:4px;padding-top:8px !important;
    }
    /* Baris pemegang daftar CR (sel ber-colspan berisi tabel): wadah, bukan kepala grup. */
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr.ts-card-group:has(table) {
        display:block !important;padding:8px;background:transparent;border:1px dashed var(--border-strong);
    }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table:not(#ts-card) > tbody > tr.ts-card-group:has(table) > td { display:block !important;width:100% !important; }
    body.tesys-ui .modal[id^="mdlTodoCoa"] table.ts-card-table table.ts-card-table > tbody > tr { margin-bottom:8px; }
}

/* ── DETAIL DRAWER (x-drawer) — gaya isi disamakan dengan drawer Cost Request ── */
/* Bar aksi drawer di kaki panel — tidak ikut tergulir bersama isi tab, supaya
   tombol Approve/Reject tetap terjangkau pada CR berisi banyak rincian. */
.todo-drawer-actions { flex:1;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap; }
.todo-drawer-actions .btn { font-size:12px;font-weight:600;padding:6px 16px; }
/* Drawer (x-drawer) duduk di z-index 1070 — di ATAS modal Bootstrap bawaan
   (1050). Modal yang dibuka selagi drawer terbuka (View Budget, Detail
   Budget, konfirmasi aksi) dinaikkan, berikut backdrop-nya, kalau tidak ia
   muncul di belakang panel. */
body:has(#todoDrawer.is-open) .modal { z-index:1080; }
body:has(#todoDrawer.is-open) .modal-backdrop { z-index:1075; }
/* Kebalikannya juga terjadi: drawer CR bisa dibuka DARI dalam modal View
   Budget (tombol view per CR). Yang terakhir dibuka harus menang, jadi drawer
   naik di atas modal selama kelas ini terpasang. */
body.todo-drawer-top #todoDrawer { z-index:1091; }

/* Tab drawer: satu baris, bisa digeser mendatar tanpa scrollbar, dan tetap di
   atas saat isi digulir. Offset -18px = padding-top .ts-drawer-body. */
#todoDetailTabs { border-bottom:2px solid var(--border);flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-ms-overflow-style:none;
    position:sticky;top:-18px;margin-top:-18px;padding-top:18px;z-index:5;background:var(--surface); }
#todoDetailTabs::-webkit-scrollbar { display:none; }
#todoDetailTabs .nav-item { flex:0 0 auto; }
#todoDetailTabs .nav-link { white-space:nowrap;color:var(--text-faint);border:none;border-bottom:2px solid transparent;border-radius:0;padding:8px 12px;font-size:12.5px;font-weight:600; }
#todoDetailTabs .nav-link.active { color:var(--accent) !important;border-bottom-color:var(--accent) !important;background:transparent !important;font-weight:700; }

/* Kode CR di kepala drawer: klik untuk menyalin. */
.cr-copy-code { background:none;border:0;padding:0;margin:0;font:inherit;letter-spacing:inherit;text-transform:inherit;color:inherit;cursor:pointer; }
.cr-copy-code:hover { color:var(--accent);text-decoration:underline; }
.cr-copy-code:focus-visible { outline:none;box-shadow:0 0 0 3px var(--accent-ring);border-radius:var(--radius-sm); }

/* Tab Log — gaya chat sama dengan Cost Request / Purchase Order: log user yang
   sedang login rata kanan ("mine"), lainnya rata kiri, avatar inisial. Chat
   menempel di bawah (terlama di atas), kotak kirim selalu terlihat. */
.cr-chat { display:flex;flex-direction:column;gap:14px;padding:4px 2px; }
.cr-chat-row { display:flex;gap:10px;max-width:78%; }
.cr-chat-row.mine { align-self:flex-end;flex-direction:row-reverse;text-align:right; }
.cr-chat-av { width:30px;height:30px;border-radius:50%;background:var(--info-bg);color:var(--text-dim);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;overflow:hidden; }
.cr-chat-row.mine .cr-chat-av { background:var(--charcoal);color:#fff; }
.cr-chat-av img { width:100%;height:100%;object-fit:cover; }
.cr-chat-bubble { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:8px 12px;font-size:12px; }
.cr-chat-meta { font-size:10px;color:var(--text-faint);margin-bottom:4px; }
.cr-chat-note { margin-top:6px;font-size:11.5px;line-height:1.45;white-space:pre-line;color:var(--text); }
#todoPaneLog.active { display:flex;flex-direction:column; }
#todoPaneLog #todoLogBody { margin-top:auto; }
#todoPaneLog .cr-log-form, #todoPaneLog .cr-log-closed { position:sticky;bottom:-24px;margin-bottom:-24px;padding-bottom:24px;background:var(--surface); }
.cr-log-form { display:flex;gap:8px;align-items:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid var(--border); }
.cr-log-form textarea { font-size:12px;resize:none;flex:1 1 auto; }
.cr-log-closed { margin-top:16px;padding-top:12px;border-top:1px solid var(--border);font-size:11.5px;color:var(--text-faint); }

/* Teks deskripsi panjang di tab Settlement: satu baris dengan "…", melebar
   penuh saat barisnya disorot. */
#todoRealBody .cr-clip { display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis; }
#todoRealBody tr:hover .cr-clip { -webkit-line-clamp:unset;overflow:visible;white-space:normal; }
/* Lebar kolom dikunci: dengan layout otomatis, baris grup ber-colspan dan
   baris anak yang muncul/hilang membuat kolom bergeser tiap kali grup dibuka. */
.todo-flat-section .card-body table,
.todo-worklist .card-body table { table-layout:fixed; }
.todo-flat-section .card-body table td,
.todo-worklist .card-body table td { overflow-wrap:anywhere; }
/* Tabel flat berkolom fit-content (Treasury Menunggu Proses / Siap Dibayar):
   table-layout:fixed di atas membuat width:1% + nowrap pada .todo-col-fit
   saling tumpuk. Layout otomatis hanya untuk tabel ini; Deskripsi tetap wrap
   (.todo-col-desc) dan .table-responsive tetap menggulir bila sempit. */
.todo-flat-section .card-body table.todo-fit-table,
.todo-worklist .card-body table.todo-fit-table { table-layout:auto; }

/* Header kolom yang bisa diurutkan (tabel di modal View Budget). */
.todo-sort-th { cursor:pointer; user-select:none; }
.todo-sort-th:hover { color:var(--accent); }
.todo-sort-ic { font-size:9px; opacity:.35; margin-left:2px; }
.todo-sort-th[data-dir] .todo-sort-ic { opacity:1; color:var(--accent); }
/* Riwayat pengajuan PIC — pohon COA/Sub COA/item di tab Info. */
.todo-pichist-wrap { max-height:320px; overflow:auto; border:1px solid var(--border); border-radius:var(--radius-md); margin-top:6px; }
.todo-pichist-table { width:100%; margin-bottom:0; }
.todo-pichist-table th, .todo-pichist-table td { padding:6px 10px; vertical-align:middle; }
.todo-pichist-table thead th, .todo-pichist-table tfoot th { position:sticky; z-index:2; background:var(--surface-2); }
.todo-pichist-table thead th { top:0; }
.todo-pichist-table tfoot th { bottom:0; }

/* Grup per Chart of Account di tab Request & Settlement drawer (pola sama
   dengan tab Budget di Budget Plan). */
tr.td-grp-row { cursor:pointer; }
tr.td-grp-row, tr.td-grp-row td { background:var(--info-bg) !important; }
tr.td-grp-row:hover, tr.td-grp-row:hover td { background:var(--surface-3) !important; }
.td-grp-caret { font-size:10px; color:var(--text-faint); transition:transform .15s ease; display:inline-block; }
.td-grp-caret.rotated { transform:rotate(90deg); }
td.td-grp-indent { padding-left:32px !important; }

/* Grid lampiran — sama seperti tab Request/Settlement di Cost Request: seluruh
   kartu adalah tombol yang membuka carousel. */
.cr-att-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin-top:8px; }
.cr-att-card { display:block; width:100%; padding:0; text-align:left; border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; background:var(--surface); cursor:pointer; transition:border-color .12s ease, box-shadow .12s ease; }
.cr-att-card:hover, .cr-att-card:focus-visible { border-color:var(--accent-soft); box-shadow:var(--shadow-sm); outline:none; }
.cr-att-thumb { width:100%; height:120px; object-fit:cover; display:block; background:var(--surface-2); }
.cr-att-icon { width:100%; height:120px; display:flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-faint); font-size:30px; }
.cr-att-name { display:block; font-size:11px; padding:6px 8px; color:var(--text); word-break:break-word; border-top:1px solid var(--border); }
.cr-att-meta { display:block; padding:0 8px 6px; font-size:10.5px; color:var(--text-faint); }

/* ── BARIS DATA: seluruh baris membuka detail (lihat handler tr.todo-row) ── */
#todoApp tr.todo-row { cursor:pointer; }
#todoApp tr.todo-row:hover > td { background:var(--surface-2); }
#todoApp tr.todo-row:focus { outline:none; }
#todoApp tr.todo-row:focus-visible > td { box-shadow:inset 0 2px 0 var(--accent-ring), inset 0 -2px 0 var(--accent-ring); }
/* Baris yang dicentang untuk bulk approve/reject tetap terlihat terpilih. */
#todoApp tr.todo-row:has(.todo-check:checked) > td,
#todoApp tr.todo-row:has(.tl-batch-check:checked) > td { background:var(--accent-tint); }

/* ── STRIP TASK PER MODUL — gaya dari tesys-ui-refine.css (.wf-host .wf-phased).
   Di sini hanya jarak ke kartu berikutnya, kursor tile, dan kelas penyaring modul. ── */
.todo-wf-host { margin-bottom:14px; }
.todo-wf-host[hidden] { display:none; }
body.tesys-ui .todo-wf-host .wf-phased { margin-top:0; }
.todo-wf-host .wf-tl-step { cursor:pointer;user-select:none; }
/* Kartu section di luar modul yang sedang dipilih di strip. Kelas, bukan
   .hide(), supaya tidak bentrok dengan cari/filter yang memakai .toggle(). */
#todoApp .todo-mod-hidden { display:none !important; }

/* ── KARTU SECTION ala kartu tabel Cost Request: judul, baris toolbar
   (cari & filter kiri, export kanan), lalu tabel — semuanya di dalam satu
   kartu bertepi tipis tanpa garis pemisah kepala. ── */
body.tesys-ui #todoApp .card.todo-sec-card { padding:0 !important;overflow:clip; }
body.tesys-ui #todoApp .todo-sec-card > .card-header { border-bottom:0 !important;padding:14px 16px 0 !important; }
body.tesys-ui #todoApp .todo-sec-card > .card-header .card-title {
    float:none;display:flex;flex-wrap:wrap;align-items:center;gap:4px 6px;font-size:14px;font-weight:700;color:var(--text);
}
#todoApp .todo-sec-card > .card-header .card-title > i { color:var(--text-faint);margin-right:2px !important; }
#todoApp .todo-sec-card.card-danger > .card-header .card-title > i { color:var(--danger); }
#todoApp .todo-sec-card > .card-header .card-title .badge,
#todoApp .todo-sec-card > .card-header .card-title .text-muted { margin-left:0 !important; }
body.tesys-ui #todoApp .todo-sec-card > .card-body { padding:0 16px 16px !important; }
/* Bulk bar ikut masuk ke dalam bidang kartu. */
#todoApp .todo-sec-card > .todo-bulk-bar { margin:0 16px 10px;border:1px solid var(--border);border-radius:var(--radius-sm); }

/* Toolbar per tabel: semua kendali rapat di kiri (cari, filter, export,
   expand), sama dengan bilah kendali tabel Cost Request. */
.todo-sec-bar { display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 16px 12px; }
.todo-sec-left { display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0; }
/* Kotak cari dibuat sendiri (bukan .form-control). Gaya input bawaan tema
   (tepi, bayangan fokus, padding ber-!important) dinolkan pada input di
   dalamnya — kalau tidak, muncul kotak kedua di dalam kotak cari. */
.todo-sec-search {
    display:flex;align-items:center;gap:7px;width:240px;height:30px;margin:0;padding:0 9px;
    border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);
    font-weight:400;cursor:text;transition:border-color .12s ease, box-shadow .12s ease;
}
.todo-sec-search:focus-within { border-color:var(--accent-soft);box-shadow:0 0 0 3px var(--accent-ring); }
/* Kotak cari berupa <label>: tema memberi setiap label margin-bottom 5px
   (tesys-ui-compat.css), yang membuatnya terangkat dari tombol di sebelahnya. */
body.tesys-ui #todoApp label.todo-sec-search { margin:0;box-sizing:border-box;line-height:1;font-size:12px; }
.todo-sec-search-ic { flex:none;display:inline-flex;color:var(--text-faint);font-size:11px; }
body.tesys-ui #todoApp .todo-sec-search input.todo-sec-q,
body.tesys-ui #todoApp .todo-sec-search input.todo-sec-q:focus {
    flex:1 1 auto;min-width:0;width:auto;height:100% !important;min-height:0 !important;margin:0 !important;padding:0 !important;
    border:0 !important;border-radius:0 !important;outline:0 !important;box-shadow:none !important;background:transparent !important;
    font-size:12px !important;color:var(--text);-webkit-appearance:none;appearance:none;
}
.todo-sec-q::placeholder { color:var(--text-faint); }
.todo-sec-q::-webkit-search-decoration { -webkit-appearance:none; }
.todo-sec-bar .btn-sm { height:30px;font-size:12px;display:inline-flex;align-items:center;gap:5px; }
.todo-sec-filter-n { background:var(--accent);color:#fff;font-size:9.5px; }
.todo-sec-filter-btn.todo-filter-active { border-color:var(--accent);color:var(--accent-text);background:var(--accent-tint); }
.todo-sec-count { font-size:11px;color:var(--text-faint);font-variant-numeric:tabular-nums;white-space:nowrap; }
.todo-sec-count[hidden] { display:none; }
.todo-sec-nomatch { margin-top:10px;padding:14px 16px;font-size:12px;color:var(--text-faint);border:1px dashed var(--border);border-radius:var(--radius-sm);background:var(--surface-2); }
.todo-sec-nomatch[hidden] { display:none; }

/* Panel Filter — sama dengan panel Filter Cost Request: judul, grid dua
   kolom tombol field (gaya .tcf-row-btn global), Reset di kaki. */
.todo-fp {
    position:absolute;z-index:3000;min-width:420px;max-width:calc(100vw - 24px);padding:16px;
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);box-shadow:0 8px 24px rgba(0,0,0,.14);
}
.todo-fp .tcf-pop-title { font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-faint);margin-bottom:10px; }
.todo-fp-grid { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:8px; }
.todo-fp .tcf-row-btn { width:100%;justify-content:center;overflow:hidden;white-space:nowrap; }
.todo-fp .tcf-row-btn.is-open:not(.tcf-row-active) { border-color:var(--accent-soft);background:var(--surface-2); }
.todo-fp .tcf-pop-foot { margin-top:12px;padding-top:10px;border-top:1px solid var(--border); }
/* Daftar pilihan satu field (dibuka dari tombol field). */
.todo-sec-list-pop {
    position:absolute;z-index:3010;display:flex;flex-direction:column;width:max-content;min-width:220px;max-width:340px;max-height:320px;padding:8px;
    background:var(--surface);border:1px solid var(--border-strong);border-radius:var(--radius-sm);box-shadow:0 6px 18px rgba(0,0,0,.12);
}
.todo-sec-list-pop .tcf-list { overflow-y:auto;overflow-x:hidden;max-height:220px;padding-right:6px; }
.todo-sec-list-pop .tcf-item { display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12px;font-weight:400;cursor:pointer; }
.todo-sec-list-pop .todo-sec-list-empty { font-size:12px;padding:4px 2px; }
.todo-sec-list-pop .todo-sec-list-empty[hidden] { display:none; }
.todo-sec-pop-v { flex:1 1 auto;min-width:0; }
.todo-sec-pop-n { flex:none;padding-left:10px;font-size:10.5px;color:var(--text-faint);font-variant-numeric:tabular-nums; }
/* Kode yang baru disalin. */
.cr-number-btn.is-copied { color:var(--success) !important;background:var(--success-bg, var(--surface-2)) !important; }
@media (max-width:640px) {
    body.tesys-ui #todoApp .todo-sec-card > .card-header { padding:12px 12px 0 !important; }
    body.tesys-ui #todoApp .todo-sec-card > .card-body { padding:0 10px 10px !important; }
    #todoApp .todo-sec-card > .todo-bulk-bar { margin:0 10px 8px; }
    .todo-sec-bar { padding:8px 12px 10px; }
    .todo-sec-left { flex:1 1 100%; }
    .todo-sec-search { flex:1 1 100%;width:auto;height:38px; }
    .todo-sec-bar .todo-sec-lbl { display:none; } /* ikon saja; title tetap ada */
    .todo-sec-bar .btn-sm { height:38px;min-width:40px;justify-content:center; }
    .todo-fp { min-width:0;width:calc(100vw - 24px); }
    .todo-fp-grid { grid-template-columns:1fr; }
}

/* Filter checklist — gaya sama dengan halaman Cost Request (TesysColumnFilter) */
.todo-filter-btn.todo-filter-active { border-color:var(--accent);color:var(--accent-dark);background:var(--warning-bg); }
.todo-filter-dropdown {
    position:absolute;z-index:3000;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);
    box-shadow:0 6px 18px rgba(0,0,0,.12);padding:8px;width:max-content;min-width:200px;max-width:560px;
    max-height:320px;display:flex;flex-direction:column;
}
.todo-filter-list { overflow-y:auto;overflow-x:hidden;max-height:200px;padding-right:14px;box-sizing:border-box; }
.todo-filter-item { display:flex;align-items:center;font-size:12px;font-weight:400;cursor:pointer;gap:6px;margin-bottom:4px; }
</style>
@endpush

@push('scripts')
<script>
/*
  Datang dari Dashboard lewat #tl-<key>: browser sudah melompat sendiri, tapi
  di halaman sepanjang ini kartu yang dituju sulit dikenali. Sorot sebentar
  supaya jelas bagian mana yang dimaksud. Tidak mengubah data apa pun.
*/
(function () {
    function sorot() {
        var hash = window.location.hash;
        if (!hash || hash.indexOf('#tl-') !== 0) return;
        var el = document.getElementById(hash.slice(1));
        if (!el) return;
        el.scrollIntoView({ block: 'start', behavior: 'smooth' });
        el.classList.remove('tl-disorot');
        void el.offsetWidth;   // paksa reflow supaya animasi berjalan lagi
        el.classList.add('tl-disorot');
    }
    window.addEventListener('hashchange', sorot);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sorot);
    } else {
        sorot();
    }
})();
</script>
<script>
$(function () {

    /* ── WORKLIST — collapse/expand grup Budget Plan ── */
    $(document).on('click', '.todo-group-row', function () {
        var target = $(this).data('target');
        var $rows = $('.' + target);
        var expanding = $rows.first().is(':hidden');
        $rows.toggle(expanding);
        $(this).find('.todo-caret').toggleClass('rotated', expanding);
    });

    /* ── BUAT BATCH PEMBAYARAN DARI TODO LIST (Todo #5) ── */
    function tlBatchRefresh() {
        var $dipilih = $('.tl-batch-check:checked');
        var total = 0;
        $dipilih.each(function () { total += parseInt($(this).data('amount'), 10) || 0; });

        $('#tlBatchCount').text($dipilih.length);
        $('#tlBatchAmount').text($dipilih.length ? ' — Rp ' + total.toLocaleString('id-ID') : '');
        $('#tlBatchOpen').prop('disabled', $dipilih.length === 0);

        // Centang "pilih semua" hanya kalau SEMUA baris yang sedang TAMPIL
        // tercentang — baris yang tersaring oleh pencarian tidak dihitung,
        // supaya centangnya tidak berbohong soal apa yang ikut terpilih.
        var $tampil = $('.tl-batch-check').filter(function () { return $(this).closest('tr').is(':visible'); });
        $('#tlBatchCheckAll').prop('checked', $tampil.length > 0 && $tampil.filter(':checked').length === $tampil.length);

        // Centang header PO menyusul isinya: tercentang hanya kalau SEMUA CR
        // yang tampil di grup itu tercentang, supaya tidak pernah menyiratkan
        // "satu PO penuh" padahal sebagian tersaring pencarian.
        $('.tl-batch-check-group').each(function () {
            var $anggota = $('.' + $(this).data('group')).find('.tl-batch-check')
                .filter(function () { return $(this).closest('tr').is(':visible'); });
            $(this).prop('checked', $anggota.length > 0 && $anggota.filter(':checked').length === $anggota.length);
        });
    }

    $(document).on('change', '.tl-batch-check', tlBatchRefresh);

    // Klik pada checkbox grup tidak boleh ikut membuka/menutup baris PO-nya.
    $(document).on('click', '.tl-batch-check-group', function (e) { e.stopPropagation(); });
    $(document).on('change', '.tl-batch-check-group', function (e) {
        e.stopPropagation();
        var pilih  = $(this).is(':checked');
        var $baris = $('.' + $(this).data('group'));
        // Grup yang masih tertutup dibuka dulu: batch tidak boleh berisi CR
        // yang tidak pernah terlihat oleh yang mencentangnya.
        if (pilih && !$baris.filter(':visible').length) {
            $baris.show();
            $(this).closest('tr').find('.todo-caret').addClass('rotated');
        }
        $baris.find('.tl-batch-check')
            .filter(function () { return $(this).closest('tr').is(':visible'); })
            .prop('checked', pilih);
        tlBatchRefresh();
    });

    // Menutup grup PO di section Treasury melepas centang isinya — kalau tidak,
    // CR yang tersembunyi tetap ikut terbatch tanpa terlihat.
    $(document).on('click', '.todo-po-row', function () {
        var $baris = $('.' + $(this).data('target'));
        if (!$baris.filter(':visible').length) {
            $baris.find('.tl-batch-check:checked').prop('checked', false);
            tlBatchRefresh();
        }
    });

    $(document).on('change', '#tlBatchCheckAll', function () {
        var pilih = $(this).is(':checked');
        // Hanya baris yang sedang tampil — kalau tidak, pencarian jadi tidak
        // ada artinya dan user bisa tanpa sadar membatch CR yang tak dilihatnya.
        $('.tl-batch-check').filter(function () { return $(this).closest('tr').is(':visible'); })
            .prop('checked', pilih);
        tlBatchRefresh();
    });

    $(document).on('click', '#tlBatchOpen', function () {
        var $dipilih = $('.tl-batch-check:checked');
        if (!$dipilih.length) return;

        var total = 0;
        $dipilih.each(function () { total += parseInt($(this).data('amount'), 10) || 0; });

        // Daftar id ditulis ulang tiap kali modal dibuka, bukan ditumpuk.
        // Second Payment dikirim pada parameter tersendiri: store() memang
        // memvalidasi dan membuat barisnya lewat jalur yang berbeda dari CR.
        $('#tlBatchIds').html($dipilih.map(function () {
            var field = $(this).data('kind') === 'second' ? 'second_payment_ids[]' : 'cr_ids[]';
            return '<input type="hidden" name="' + field + '" value="' + $(this).val() + '">';
        }).get().join(''));

        $('#tlBatchSummary').text($dipilih.length + ' CR — Rp ' + total.toLocaleString('id-ID'));
        $('#mdlTlBatch').modal('show');
        $('#mdlTlBatch').one('shown.bs.modal', function () { $('#tlBatchDesc').focus(); });
    });

    // Pencarian bisa menyembunyikan baris yang sudah tercentang; hitungannya
    // dibiarkan apa adanya (pilihan tetap sah), tapi centang "pilih semua"
    // perlu dinilai ulang terhadap baris yang tampil.
    $('#todoSearch').on('keyup', tlBatchRefresh);

    // Format durasi detik -> ringkas (dtk/mnt/jam/hari) — sama persis dengan
    // TodoListController::formatDuration(). Dipakai filter Summary Task (avg
    // time ikut dihitung ulang saat modul dipersempit) dan sortir Summary
    // Respontime (lihat IIFE masing-masing di bawah).
    function fmtDurasi(detik) {
        if (detik === null || detik === undefined) return null;
        if (detik < 60) return detik + ' dtk';
        var m = Math.floor(detik / 60);
        if (m < 60) return m + ' mnt';
        var h = Math.floor(m / 60), mm = m % 60;
        if (h < 24) return h + ' j' + (mm ? ' ' + mm + ' m' : '');
        var d = Math.floor(h / 24), hh = h % 24;
        return d + ' hr' + (hh ? ' ' + hh + ' j' : '');
    }

    /* ── SUMMARY TASK — filter Modul & Role ──
       Tabel statis di dalam modal (~20 baris), jadi disaring langsung di DOM:
       tanpa DataTable, tanpa reload. Markupnya di _summary_task_list.blade.php;
       kodenya ada DI SINI karena partial itu dirender sebelum jQuery dimuat. */
    (function () {
    var $wrap = $('#summaryTaskWrap');
    if (!$wrap.length) return;

    var modul = '';  // '' = semua
    var role  = '';

    function terapkan() {
        var $tbl = $('#summaryTaskTable');

        // Kolom modul: sembunyikan yang tidak dipilih (header, sel, footer sekaligus).
        $tbl.find('[data-module]').each(function () {
            $(this).toggle(!modul || $(this).data('module') === modul);
        });

        var totalPerModul = {};
        var grand = 0;
        var tampil = 0;
        var grandAvgSum = 0, grandAvgCount = 0;

        $tbl.find('tbody tr[data-role]').each(function () {
            var $tr = $(this);

            // Baris ikut disembunyikan kalau rolenya tidak cocok, ATAU kalau modul
            // yang dipilih memang tidak punya tahap ini — kalau tidak, hasil filter
            // penuh baris "·" yang tidak memberi tahu apa pun.
            var $selModul = $tr.find('[data-module]').filter(function () {
                return !modul || $(this).data('module') === modul;
            });
            var adaAngka = $selModul.filter('[data-count]').length > 0;
            var cocok = (!role || $tr.data('role') === role) && adaAngka;

            $tr.toggle(cocok);
            if (!cocok) return;
            tampil++;

            // Total baris = jumlah sel modul yang sedang TERLIHAT saja. Avg Time
            // baris = rata-rata TERTIMBANG dari avg_sum/avg_count sel yang sama
            // (bobot = jumlah transisi) — bukan rata-rata dari rata-rata, supaya
            // menyaring ke satu Modul tetap menampilkan avg modul itu sendiri.
            var subtotal = 0, avgSum = 0, avgCount = 0;
            $selModul.filter('[data-count]').each(function () {
                var n = parseInt($(this).attr('data-count'), 10) || 0;
                subtotal += n;
                avgSum   += parseInt($(this).attr('data-avg-sum'), 10) || 0;
                avgCount += parseInt($(this).attr('data-avg-count'), 10) || 0;
                var m = $(this).data('module');
                totalPerModul[m] = (totalPerModul[m] || 0) + n;
            });

            grand += subtotal;
            grandAvgSum += avgSum; grandAvgCount += avgCount;
            $tr.find('[data-total-cell]')
                .text(subtotal)
                .toggleClass('summary-total-pos', subtotal > 0)
                .toggleClass('summary-total-zero', subtotal <= 0);
            $tr.find('[data-avg-cell]').html(avgCount > 0
                ? fmtDurasi(Math.round(avgSum / avgCount))
                : '<span class="text-muted">-</span>');
        });

        $tbl.find('[data-foot-module]').each(function () {
            $(this).text(totalPerModul[$(this).data('module')] || 0);
        });
        $tbl.find('[data-foot-grand]').text(grand);
        $tbl.find('[data-foot-avg]').text(grandAvgCount > 0 ? fmtDurasi(Math.round(grandAvgSum / grandAvgCount)) : '-');

        $('#stEmptyRow').toggle(tampil === 0);
        $tbl.find('tfoot').toggle(tampil > 0);

        // Tombol memakai warna merek begitu filternya tidak lagi "semua",
        // pola yang sama dengan tombol filter di halaman lain (.col-mf-active).
        $('#stModuleBtn').toggleClass('col-mf-active', modul !== '');
        $('#stRoleBtn').toggleClass('col-mf-active', role !== '');
        $('#stModuleLabel').text(modul || 'All Module');
        $('#stRoleLabel').text(role || 'All Role');
    }

    $wrap.on('click', '.st-module-opt', function () {
        modul = $(this).data('value') || '';
        $wrap.find('.st-module-opt').removeClass('active');
        $(this).addClass('active');
        terapkan();
    });

    $wrap.on('click', '.st-role-opt', function () {
        role = $(this).data('value') || '';
        $wrap.find('.st-role-opt').removeClass('active');
        $(this).addClass('active');
        terapkan();
    });

    $('#stClearFilter').on('click', function () {
        modul = '';
        role  = '';
        $wrap.find('.st-module-opt, .st-role-opt').removeClass('active');
        $wrap.find('.st-module-opt[data-value=""], .st-role-opt[data-value=""]').addClass('active');
        terapkan();
    });
    })();

    /* ── SUMMARY RESPONTIME — tabel sortable (klik header) ──
       Datanya window.__respontimeData (di-set inline oleh
       _summary_respontime.blade.php). Baris dirender ulang oleh JS tiap kali
       sortir berubah — tabelnya kecil (satu baris per user), jadi tanpa
       DataTable. Default: Rata-rata ascending (dari server). */
    (function () {
    var data = window.__respontimeData;
    if (!data || !data.rows || !data.rows.length) return;

    var sortKey = 'avg_seconds'; // kolom "Rata-rata" — cocok dengan urutan awal dari server.
    var sortDir = 'asc';

    function cellOf(row, key) {
        return (key === 'user' || key === 'avg_seconds') ? null : (row.cells[key] || null);
    }

    function sortValue(row, key) {
        if (key === 'user') return row.user;
        if (key === 'avg_seconds') return row.avg_seconds;
        var c = cellOf(row, key);
        return c ? c.avg_seconds : null;
    }

    function render() {
        var rows = data.rows.slice().sort(function (a, b) {
            var x = sortValue(a, sortKey), y = sortValue(b, sortKey);
            // Baris tanpa data ("-") selalu ke bawah, berapa pun arah sortirnya —
            // supaya "-" tidak terbaca seolah "paling cepat" saat ascending.
            if (x === null && y === null) return 0;
            if (x === null) return 1;
            if (y === null) return -1;
            var r = (sortKey === 'user')
                ? String(x).localeCompare(String(y), 'id', { numeric: true })
                : (x - y);
            return sortDir === 'asc' ? r : -r;
        });

        var body = rows.map(function (row) {
            var cells = data.modules.map(function (mod) {
                var c = cellOf(row, mod);
                return '<td class="text-center text-nowrap">' + (c
                    ? '<div style="font-weight:600;">' + esc(fmtDurasi(c.avg_seconds)) + '</div>'
                      + '<div class="text-muted" style="font-size:10px;">' + c.count + '&times;</div>'
                    : '<span class="text-muted">-</span>') + '</td>';
            }).join('');

            var avgCell = row.avg_seconds !== null
                ? '<div style="font-weight:700;">' + esc(fmtDurasi(row.avg_seconds)) + '</div>'
                  + '<div class="text-muted" style="font-size:10px;">' + row.count + '&times;</div>'
                : '<span class="text-muted">-</span>';

            return '<tr>' +
                '<td class="text-nowrap"><strong>' + esc(row.user) + '</strong></td>' +
                cells +
                '<td class="text-center text-nowrap">' + avgCell + '</td>' +
            '</tr>';
        }).join('');

        $('#summaryResponBody').html(body);

        $('#summaryResponTable thead .sr-sort').each(function () {
            var aktif = $(this).data('key') === sortKey;
            var $icon = $(this).find('i');
            $icon.attr('class', 'fas ' + (aktif ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'))
                 .css('opacity', aktif ? 1 : .35);
        });
    }

    $(document).on('click keydown', '#summaryResponTable .sr-sort', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        var key = $(this).data('key');
        sortDir = (sortKey === key && sortDir === 'asc') ? 'desc' : 'asc';
        sortKey = key;
        render();
    });

    render();
    })();

    /* ── RINCIAN BUDGET PLAN (Todo #4) ──
       Approver bisa melihat isi Budget Plan tanpa meninggalkan Todo List.
       Datanya dari endpoint yang sama dengan halaman Budgeting. */
    function todoBpRp(n) { return (parseInt(n, 10) || 0).toLocaleString('id-ID'); }

    function todoBpKartu(label, nilai, nada) {
        return '' +
            '<div class="col-md-3 col-6 mb-2">' +
                '<div style="border:1px solid var(--border);border-radius:var(--radius-md);padding:8px 10px;">' +
                    '<div style="font-size:9.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--text-faint);">' + label + '</div>' +
                    '<div style="font-size:15px;font-weight:700;color:var(--' + nada + ');font-variant-numeric:tabular-nums;">' + nilai + '</div>' +
                '</div>' +
            '</div>';
    }

    /* Tab PO & Attachment: dari endpoint halaman Purchase Order yang sudah
       ada (project/{id}/detail-json), bukan salinan data tersendiri. */
    function todoBpAttLink(a) {
        var ikon = a.is_image ? 'fa-file-image' : (a.is_pdf ? 'fa-file-pdf' : 'fa-file');
        return '<a href="{{ url('/storage') }}/' + encodeURI(a.path) + '" target="_blank" rel="noopener" class="d-block mb-1" style="font-size:12.5px;">' +
            '<i class="fas ' + ikon + ' mr-1"></i>' + esc(a.title || a.file_name || 'File') + '</a>';
    }

    function todoBpInfo(label, nilai) {
        return '<div class="col-md-4 col-6 mb-3"><div style="font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--text-faint);">' + label + '</div>' +
            '<div style="font-size:13px;font-weight:600;">' + nilai + '</div></div>';
    }

    function todoBpLoadPo(projectId) {
        var kosong = function (pesan) {
            var html = '<div class="text-center text-muted py-4" style="font-size:12.5px;"><i class="fas fa-info-circle d-block mb-2" style="font-size:20px;"></i>' + pesan + '</div>';
            $('#todoBpPoBody, #todoBpAttBody').html(html);
            $('#todoBpAttCount').text('0');
        };
        if (!projectId) { kosong('Budget ini tidak terkait Purchase Order (budget cost center).'); return; }

        $('#todoBpPoBody, #todoBpAttBody').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i></div>');

        $.get('{{ url("/project") }}/' + projectId + '/detail-json', function (p) {
            var poRows = (p.po_numbers || []).map(function (n) {
                return '<tr><td class="text-nowrap">' + esc(n.po_number) + '</td>' +
                    '<td class="text-nowrap">' + esc(n.release_date || '-') + '</td>' +
                    '<td class="text-right text-nowrap">' + esc(n.nominal_formatted || '-') + '</td>' +
                    '<td class="text-center"><span class="badge badge-' + esc(n.status_color || 'secondary') + '">' + esc(n.status_label || n.status || '-') + '</span></td></tr>';
            }).join('');

            $('#todoBpPoBody').html(
                '<div class="row">' +
                    todoBpInfo('PO', '[' + esc(p.code) + '] ' + esc(p.name)) +
                    todoBpInfo('Customer', esc(p.customer)) +
                    todoBpInfo('Manager', esc((p.manager || '-').toUpperCase())) +
                    todoBpInfo('Nilai Kontrak', esc(p.contract_formatted || '-')) +
                    todoBpInfo('Nilai PO', esc(p.value_formatted || '-')) +
                    todoBpInfo('Status', '<span class="badge badge-' + esc(p.status_color || 'secondary') + '">' + esc(p.status_label || '-') + '</span>') +
                '</div>' +
                '<h6 class="font-weight-bold" style="font-size:12px;">No. PO</h6>' +
                '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:12px;">' +
                    '<thead class="bg-light"><tr><th>No. PO</th><th>Tanggal Rilis</th><th class="text-right">Nominal</th><th class="text-center">Status</th></tr></thead>' +
                    '<tbody>' + (poRows || '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada No. PO.</td></tr>') + '</tbody>' +
                '</table></div>'
            );

            // Lampiran PO (per jenis) + lampiran tiap No. PO beserta BAST-nya.
            var bagian = [], jumlah = 0;
            $.each(p.attachments || {}, function (jenis, daftar) {
                if (!daftar || !daftar.length) return;
                jumlah += daftar.length;
                bagian.push('<h6 class="font-weight-bold mt-2" style="font-size:12px;text-transform:capitalize;">' + esc(String(jenis).replace(/_/g, ' ')) + '</h6>' +
                    daftar.map(todoBpAttLink).join(''));
            });
            (p.po_numbers || []).forEach(function (n) {
                var poAtt = n.attachments || [], bastAtt = n.bast_attachments || [];
                if (!poAtt.length && !bastAtt.length) return;
                jumlah += poAtt.length + bastAtt.length;
                bagian.push('<h6 class="font-weight-bold mt-2" style="font-size:12px;">No. PO ' + esc(n.po_number) + '</h6>' +
                    poAtt.map(todoBpAttLink).join('') +
                    (bastAtt.length ? '<div class="text-muted mt-1" style="font-size:11px;">BAST</div>' + bastAtt.map(todoBpAttLink).join('') : ''));
            });
            $('#todoBpAttCount').text(jumlah);
            $('#todoBpAttBody').html(bagian.join('') || '<div class="text-center text-muted py-4" style="font-size:12.5px;">Belum ada attachment di Purchase Order ini.</div>');
        }).fail(function (xhr) {
            kosong(xhr.status === 403
                ? 'Anda tidak memiliki akses ke Purchase Order ini.'
                : 'Gagal memuat data Purchase Order.');
        });
    }

    $(document).on('click', '.todo-bpd-coa', function () {
        var $rows = $('#todoBpDetails .' + $(this).data('target'));
        var expanding = $rows.first().is(':hidden');
        $rows.toggle(expanding);
        $(this).find('.todo-coa-caret').toggleClass('rotated', expanding);
    });

    $(document).on('click', '.todo-bp-detail', function (e) {
        e.stopPropagation();

        var id = $(this).data('id');

        $('#todoBpLoading').show();
        $('#todoBpBody').hide();
        $('#todoBpTitle').text('Rincian Budget Plan');
        $('#todoBpSubtitle').text('');
        $('#todoBpOpenPage').attr('href', '{{ url("/budget-plan") }}?open=' + id);
        $('#todoBpTabs a[href="#todoBpTabBudget"]').tab('show');
        $('#todoBpPoBody, #todoBpAttBody').empty();
        $('#todoBpAttCount').text('0');
        $('#mdlTodoBp').modal('show');

        $.get('{{ url("/budget-plan") }}/' + id + '/detail-json', function (d) {
            $('#todoBpTitle').text(d.code + ' — ' + (d.description || '-'));
            $('#todoBpSubtitle').text(
                [d.source_name, d.code_reference, d.customer, d.manager]
                    .filter(function (v) { return v && v !== '-'; }).join(' · ')
            );

            /* Angka ringkas: anggaran, terpakai, sisa, persentase. Sisa yang
               minus diberi nada danger — itu kondisi yang harus terbaca
               sebelum tombol Approve ditekan, bukan sesudahnya. */
            var sisa = parseInt(d.remaining_amount, 10) || 0;
            $('#todoBpKpi').html(
                todoBpKartu('Anggaran', 'Rp ' + todoBpRp(d.amount), 'text') +
                todoBpKartu('Terpakai', 'Rp ' + todoBpRp(d.used_amount), 'info') +
                todoBpKartu('Sisa', 'Rp ' + todoBpRp(sisa), sisa < 0 ? 'danger' : 'success') +
                todoBpKartu('Terpakai (%)', (d.usage_percentage || 0) + '%', (d.usage_percentage || 0) > 100 ? 'danger' : 'text')
            );

            /* Rincian dikelompokkan per Chart of Account. Kunci payload showJson():
               `amount` = harga satuan, `qty` + `unit`, `total` = nilai baris.
               Tanpa kolom Terpakai/Sisa: budget yang sedang diajukan memang
               belum pernah terpakai. */
            var perCoa = {}, urutan = [];
            (d.details || []).forEach(function (x) {
                var k = x.account || '-';
                if (!perCoa[k]) { perCoa[k] = { rows: [], total: 0 }; urutan.push(k); }
                perCoa[k].rows.push(x);
                perCoa[k].total += parseInt(x.total, 10) || 0;
            });
            urutan.sort();

            var grandTotal = 0;
            var baris = urutan.map(function (k, ki) {
                var g = perCoa[k];
                var cls = 'todo-bpd-coa-' + ki;
                grandTotal += g.total;
                // Baris CoA bisa dibuka/tutup (awal: tertutup), sama seperti
                // modal View Budget di worklist Cost Request.
                var html = '<tr class="todo-bpd-coa" data-target="' + cls + '" style="background:var(--surface-2);font-weight:700;cursor:pointer;">' +
                    '<td colspan="3">' +
                        '<button type="button" class="btn btn-outline-secondary" style="padding:1px 6px;margin-right:6px;" tabindex="-1" aria-label="Buka/tutup ' + escAttr(k) + '">' +
                            '<i class="fas fa-chevron-right todo-coa-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>' +
                        '</button>' +
                        esc(k) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + g.rows.length + '</span></td>' +
                    '<td class="text-right text-nowrap">' + todoBpRp(g.total) + '</td></tr>';
                g.rows.forEach(function (x) {
                    var qty = x.qty ? (parseFloat(x.qty).toLocaleString('id-ID') + (x.unit ? ' ' + esc(x.unit) : '')) : '-';
                    html += '<tr class="' + cls + '" style="display:none;">' +
                        '<td style="padding-left:24px;">' + esc(x.deskripsi || '-') + '</td>' +
                        '<td class="text-right text-nowrap">' + todoBpRp(x.amount) + '</td>' +
                        '<td class="text-center text-nowrap">' + qty + '</td>' +
                        '<td class="text-right text-nowrap">' + todoBpRp(x.total) + '</td>' +
                    '</tr>';
                });
                return html;
            }).join('');

            $('#todoBpDetails').html(baris || '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada rincian anggaran.</td></tr>');
            $('#todoBpTotal').text(todoBpRp(grandTotal));

            todoBpLoadPo(d.project_id);

            var catatan = [d.notes, d.approver_notes].filter(Boolean).join(' — ');
            $('#todoBpNotes').text(catatan);
            $('#todoBpNotesWrap').toggle(!!catatan);

            $('#todoBpLoading').hide();
            $('#todoBpBody').show();
        }).fail(function (xhr) {
            /* 403 di sini BUKAN kegagalan teknis: Budget Plan berbasis Cost
               Center (Overhead/Tax/Payroll) punya pembatasan per-record sendiri
               di BudgetPlanController::canViewBudgetPlan(). Daftar approval di
               Todo List tidak memakai pembatasan itu, jadi sebuah baris bisa
               muncul untuk disetujui walau rinciannya tidak boleh dibuka oleh
               orang yang sama. Dikatakan apa adanya — kalau ditulis "gagal
               memuat", orang akan mengira ada yang rusak dan mencoba berulang. */
            if (xhr && xhr.status === 403) {
                $('#todoBpLoading').html(
                    '<div class="alert alert-warning mb-0" style="font-size:12px;">' +
                    '<i class="fas fa-lock mr-1"></i>' +
                    'Rincian Budget Plan ini dibatasi untuk role Anda (budget Cost Center ' +
                    'seperti Overhead, Tax, atau Payroll punya pembatasan tersendiri). ' +
                    'Hubungi Finance/Admin kalau Anda memang perlu melihat isinya.' +
                    '</div>'
                );
                return;
            }

            $('#todoBpLoading').html(
                '<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>' +
                'Gagal memuat rincian Budget Plan.</p>'
            );
        });
    });

    /* ── GROUPING PO untuk tabel flat (table.todo-po-group) ──
       Baris dikelompokkan per PO dari atribut data-po-* (diisi $poAttrs di
       Blade), menjadi lapis .todo-po-row yang sama dengan worklist CR —
       sehingga buka/tutup, filter, Expand All, dan checkbox grup ikut
       bekerja tanpa kode tambahan. Urutan grup mengikuti baris pertamanya,
       jadi urutan aging tertua dari server tetap terjaga. */
    (function groupFlatTablesByPo() {
        function escHtml(v) { return $('<span>').text(v == null ? '' : v).html(); }
        $('table.todo-po-group').each(function (ti) {
            var $table = $(this);
            var $tbody = $table.children('tbody').first();
            var $rows = $tbody.children('tr.todo-row');
            if (!$rows.length) return;

            var $ths = $table.find('thead tr').first().children('th');
            var nCol = $ths.length;
            var hasCheck = $table.find('thead .todo-check-all').length > 0;
            var amtIdx = $ths.index($ths.filter('[data-po-amount]'));

            var groups = [], byKey = {};
            $rows.each(function () {
                var key = $(this).attr('data-po-key') || '— Tanpa PO —';
                if (!byKey[key]) {
                    byKey[key] = { label: $(this).attr('data-po-label') || key, sub: $(this).attr('data-po-sub') || '', rows: [], amount: 0 };
                    groups.push(byKey[key]);
                }
                byKey[key].rows.push(this);
                byKey[key].amount += parseFloat($(this).attr('data-po-amount')) || 0;
            });

            $rows.detach();
            groups.forEach(function (g, gi) {
                var cls = 'tlpg' + ti + '-po-' + gi;
                var used = hasCheck ? 1 : 0;
                var html = '<tr class="todo-po-row" data-target="' + cls + '">';
                if (hasCheck) {
                    html += '<td><input type="checkbox" class="todo-check-group" data-group="' + cls + '" aria-label="Pilih semua item PO ini"></td>';
                }
                html += '<td colspan="' + ((amtIdx > 0 ? amtIdx : nCol) - used) + '">' +
                    '<i class="fas fa-chevron-right todo-caret mr-2"></i><strong>' + escHtml(g.label) + '</strong>' +
                    (g.sub ? ' <span class="text-muted" style="font-size:11px;">(' + escHtml(g.sub) + ')</span>' : '') +
                    ' <span class="badge badge-secondary ml-1">' + g.rows.length + '</span></td>';
                /*
                 | Tombol View Budget ikut naik ke baris PO supaya rincian
                 | anggaran bisa dibuka tanpa membuka grupnya dulu. Hanya
                 | dilakukan kalau SELURUH baris di grup ini menunjuk rincian
                 | yang sama — kalau tidak, satu tombol di baris PO akan
                 | mengaku mewakili beberapa anggaran sekaligus.
                 */
                /* Satu PO bisa berisi beberapa Budget Plan. Tombolnya tetap
                   SATU — targetnya dikumpulkan jadi daftar, lalu dibuka sebagai
                   satu modal berisi tabel per kode (lihat handler
                   .todo-view-budget-group). Dua tombol sejajar tanpa label cuma
                   menyisakan tebak-tebakan tombol mana milik anggaran mana. */
                var mirrorSeen = {}, mirrorTargets = [], mirrorOnlyHeader = false;
                g.rows.forEach(function (r) {
                    var $b = $(r).find('.todo-po-mirror').first();
                    if (!$b.length) return;
                    if ($b.hasClass('d-none')) mirrorOnlyHeader = true;
                    var key = $b.attr('data-target') || '';
                    if (!key || mirrorSeen[key]) return;
                    mirrorSeen[key] = true;
                    mirrorTargets.push(key);
                });
                var groupBtn = mirrorTargets.length
                    ? '<div class="action-buttons ts-actions todo-po-mirror-box"' + (mirrorOnlyHeader ? ' data-keep="1"' : '') + '>' +
                        '<button aria-label="View Budget" type="button" class="ts-action-primary is-neutral todo-view-budget-group"' +
                        ' data-targets="' + escHtml(mirrorTargets.join(',')) + '"' +
                        ' data-po-label="' + escHtml(g.label) + '" title="View Budget">' +
                        '<i class="fas fa-chart-pie" aria-hidden="true"></i><span class="ts-al">View Budget</span></button>' +
                      '</div>'
                    : '';

                if (amtIdx > 0) {
                    html += '<td class="text-right text-nowrap" style="font-weight:700;">' + Math.round(g.amount).toLocaleString('id-ID') + '</td>';
                    var sisa = nCol - amtIdx - 1;
                    if (groupBtn && sisa > 0) {
                        if (sisa - 1 > 0) html += '<td colspan="' + (sisa - 1) + '"></td>';
                        html += '<td class="text-center text-nowrap">' + groupBtn + '</td>';
                    } else if (sisa > 0) {
                        html += '<td colspan="' + sisa + '"></td>';
                    }
                }
                $tbody.append(html + '</tr>');
                g.rows.forEach(function (r) { $(r).addClass(cls).hide(); $tbody.append(r); });
            });
        });
    })();

    /* ── SORTIR KOLOM TABEL TODO LIST ──────────────────────────────────────
       Dipasang SETELAH grouping PO dibuat, supaya baris grup ikut terurut.
       Sortirnya berjenjang dan hanya menata ulang baris yang sudah ada di
       halaman (tidak memanggil server): baris induk diurutkan terhadap sesama
       induk, baris anak diurutkan di dalam induknya masing-masing — kalau
       tidak, CR akan berpindah PO. Status buka/tutup tiap grup tidak diubah. */
    (function sortirTabelTodo() {
        // Nilai sel: nominal rupiah, aging ("3 hari" / "-120 menit"), tanggal
        // d-m-Y, selebihnya teks. Aging disetarakan ke menit supaya "2 hari"
        // tidak kalah dari "90 menit".
        function nilaiSel(teks) {
            var t = $.trim(teks).replace(/\s+/g, ' ');
            if (t === '' || t === '-') return { n: null, s: '' };

            var aging = t.match(/^(-?[\d.]+)\s*(menit|jam|hari)$/i);
            if (aging) {
                var a = parseFloat(aging[1].replace(/\./g, '')) || 0;
                var k = aging[2].toLowerCase();
                return { n: k === 'hari' ? a * 1440 : (k === 'jam' ? a * 60 : a), s: t };
            }

            var tgl = t.match(/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/);
            if (tgl) return { n: parseInt(tgl[3] + tgl[2] + tgl[1], 10), s: t };

            if (/^\(?\s*-?\s*(Rp\.?\s*)?\d{1,3}(\.\d{3})*(,\d+)?\s*\)?$/.test(t)) {
                var neg = /^\(/.test(t) || t.indexOf('-') === 0;
                var n = parseFloat(t.replace(/[()\s]/g, '').replace(/^-/, '')
                        .replace(/Rp\.?/i, '').replace(/\./g, '').replace(/,/g, '.'));
                if (!isNaN(n)) return { n: neg ? -n : n, s: t };
            }
            if (/^-?\d+$/.test(t)) return { n: parseInt(t, 10), s: t };

            return { n: null, s: t.toLowerCase() };
        }

        // Kolom ke-i bisa tertutup colspan di baris grup — sel yang dipakai
        // adalah sel yang rentang kolomnya mencakup i.
        function selKolom(tr, idx) {
            var pos = 0, hasil = null;
            $(tr).children('td,th').each(function () {
                var span = parseInt($(this).attr('colspan'), 10) || 1;
                if (idx >= pos && idx < pos + span) { hasil = this; return false; }
                pos += span;
            });
            return hasil;
        }

        function bandingkan(a, b, naik) {
            var arah = naik ? 1 : -1;
            if (a.n !== null && b.n !== null) return (a.n - b.n) * arah;
            if (a.n !== null) return -1;          // baris berisi angka di atas
            if (b.n !== null) return 1;
            if (a.s === b.s) return 0;
            if (a.s === '') return 1;             // sel kosong selalu di bawah
            if (b.s === '') return -1;
            return a.s.localeCompare(b.s, 'id') * arah;
        }

        // Hanya tabel di dalam modal View Budget — daftar worklist di luar
        // sengaja mempertahankan urutan aging dari server.
        window.todoPasangSortir = function ($scope) {
            $scope.find('table').filter(function () {
                var $t = $(this);
                if ($t.data('todoSortReady')) return false;
                return $t.find('thead th').length > 0 && $t.children('tbody').children('tr').length > 1;
            }).each(pasang);
        };

        function pasang() {
            var $table = $(this).data('todoSortReady', true);
            var $tbody = $table.children('tbody').first();
            var $ths   = $table.find('thead tr').first().children('th');

            $ths.each(function (i) {
                var $th = $(this);
                // Kolom centang dan kolom tombol tidak punya isi yang bisa diurutkan.
                if ($th.find('input[type=checkbox]').length) return;
                if (/^(action|aksi)$/i.test($.trim($th.text()))) return;

                $th.addClass('todo-sort-th').attr('data-col', i)
                   .append(' <i class="fas fa-sort todo-sort-ic"></i>');
            });

            $table.on('click', '.todo-sort-th', function (e) {
                var $th = $(this);
                // Modal View Budget punya tabel daftar CR DI DALAM selnya —
                // klik di tabel anak tidak boleh ikut mengurutkan induknya.
                if ($th.closest('table')[0] !== $table[0]) return;
                e.stopPropagation();

                var idx  = parseInt($th.attr('data-col'), 10);
                var naik = $th.attr('data-dir') !== 'asc';

                $ths.filter('.todo-sort-th').removeAttr('data-dir')
                    .find('.todo-sort-ic').attr('class', 'fas fa-sort todo-sort-ic');
                $th.attr('data-dir', naik ? 'asc' : 'desc')
                   .find('.todo-sort-ic').attr('class', 'fas fa-sort-' + (naik ? 'up' : 'down') + ' todo-sort-ic');

                var semua = $tbody.children('tr').toArray();

                // Baris anak dikenali dari class yang sama dengan data-target
                // induknya — pola yang sudah dipakai buka/tutup grup.
                var kelasAnak = {};
                semua.forEach(function (tr) {
                    var t = $(tr).attr('data-target');
                    if (t) kelasAnak[t] = true;
                });

                function anakDari(tr) {
                    var t = $(tr).attr('data-target');
                    if (!t) return [];
                    return semua.filter(function (x) { return x !== tr && $(x).hasClass(t); });
                }
                function induk(tr) {
                    var ada = false;
                    $.each(($(tr).attr('class') || '').split(/\s+/), function (_, c) {
                        if (c && kelasAnak[c]) { ada = true; return false; }
                    });
                    return ada;
                }

                function urut(daftar) {
                    daftar.sort(function (x, y) {
                        return bandingkan(nilaiSel($(selKolom(x, idx)).text()),
                                          nilaiSel($(selKolom(y, idx)).text()), naik);
                    });
                    var keluar = [];
                    daftar.forEach(function (tr) {
                        keluar.push(tr);
                        var anak = anakDari(tr);
                        if (anak.length) keluar = keluar.concat(urut(anak));
                    });
                    return keluar;
                }

                var puncak = semua.filter(function (tr) { return !induk(tr); });
                urut(puncak).forEach(function (tr) { $tbody.append(tr); });
            });
        }

        // Modal gabungan mengkloning isinya saat dibuka, jadi pemasangan
        // dilakukan setiap modal tampil, bukan sekali saat halaman dimuat.
        $(document).on('shown.bs.modal', function (e) {
            var $m = $(e.target);
            if (!/^mdlTodoCoa/.test($m.attr('id') || '')) return;
            window.todoPasangSortir($m);
        });

        // Di HP tabel View Budget (tujuh kolom angka) tampil sebagai card —
        // dipasang sebelum modal tampil, termasuk salinan di modal gabungan.
        // Tampilan desktop tidak berubah: kelasnya hanya dipakai CSS <= 640px.
        $(document).on('show.bs.modal', function (e) {
            var $m = $(e.target);
            if (!/^mdlTodoCoa/.test($m.attr('id') || '') || !window.TesysTable) return;
            $m.find('table').each(function () { window.TesysTable.pasang(this); });
        });
    })();

    /* ── WORKLIST — buka/tutup lapis PURCHASE ORDER (MN-03) ──
       Menutup PO ikut menutup seluruh Budget Plan di dalamnya beserta CR-nya;
       kalau tidak, baris CR akan menggantung tanpa induk yang terlihat. */
    $(document).on('click', '.todo-po-row', function () {
        var target = $(this).data('target');
        var $plans = $('.' + target);
        var expanding = $plans.filter(':visible').length === 0;

        if (expanding) {
            $plans.show();
        } else {
            $plans.each(function () {
                var planTarget = $(this).data('target');
                if (planTarget) $('.' + planTarget).hide();
                $(this).find('.todo-caret').removeClass('rotated');
            });
            $plans.hide();
        }

        $(this).find('.todo-caret').toggleClass('rotated', expanding);

        /* Tombol View Budget di baris PO hanya pintasan selagi grupnya
           tertutup — begitu dibuka, tiap barisnya sudah punya tombol sendiri,
           jadi yang di atas ditarik kembali supaya tidak dobel. */
        // Kecuali tombolnya memang HANYA ada di header (data-keep) — misalnya
        // Expense Claim, yang barisnya tidak punya tombol View Budget sendiri.
        $(this).find('.todo-po-mirror-box').not('[data-keep]').toggle(!expanding);
    });

    /* ── WORKLIST — tombol View Budget ── */
    $(document).on('click', '.todo-view-budget', function (e) {
        e.stopPropagation();
        $($(this).data('target')).modal('show');
    });

    /* ── WORKLIST — tombol View Budget di baris PO ──
       Satu PO bisa memayungi beberapa Budget Plan, dan masing-masing punya
       modalnya sendiri. Daripada menaruh sederet tombol di satu baris, isinya
       disalin ke SATU modal gabungan: satu tabel per kode, berurutan. Salinan
       dibuat ulang tiap kali dibuka supaya selalu mengikuti modal sumbernya. */
    $(document).on('click', '.todo-view-budget-group', function (e) {
        e.stopPropagation();

        var targets = ($(this).attr('data-targets') || '').split(',').filter(Boolean);
        if (!targets.length) return;
        if (targets.length === 1) { $(targets[0]).modal('show'); return; }

        var $body = $('#mdlTodoCoaMultiBody').empty();
        $('#mdlTodoCoaMultiTitle').text($(this).attr('data-po-label') || 'View Budget');

        targets.forEach(function (sel, i) {
            var $src = $(sel);
            if (!$src.length) return;

            /* Tabelnya disembunyikan dulu: modal gabungan bisa memuat beberapa
               anggaran sekaligus, dan menumpuk semuanya terbuka membuat yang
               dicari justru harus digulir dulu. Judul kode = tombol bukanya. */
            $body.append(
                '<div class="todo-multi-head" data-target="tlmulti-' + i + '">' +
                    '<i class="fas fa-chevron-right todo-caret mr-2"></i>' +
                    ($src.find('.modal-title').first().html() || '') +
                '</div>'
            );
            $('<div class="todo-multi-sec tlmulti-' + i + '" style="display:none;"></div>')
                .append($src.find('.modal-body').first().children().clone())
                .appendTo($body);
        });

        $('#mdlTodoCoaMulti').modal('show');
    });

    /* Buka/tutup satu kode di modal View Budget gabungan. */
    $(document).on('click', '.todo-multi-head', function () {
        var $sec = $('#mdlTodoCoaMultiBody').find('.' + $(this).data('target'));
        var buka = !$sec.is(':visible');
        $sec.toggle(buka);
        $(this).find('.todo-caret').toggleClass('rotated', buka);
    });

    /* ── MODAL VIEW BUDGET — collapse/expand Chart of Account & list CR ── */
    $(document).on('click', '.todo-coa-row', function () {
        var target = $(this).data('target');
        var $rows = $('.' + target);
        var expanding = $rows.first().is(':hidden');
        if (expanding) {
            $rows.show();
        } else {
            // data-target ada di tombol .todo-si-toggle DI DALAM baris sub-item,
            // bukan di baris <tr>-nya sendiri — harus dicari lewat .find(), bukan
            // .data() langsung pada $(this), supaya daftar "N CR" yang lagi
            // expanded ikut disembunyikan saat Chart of Account-nya di-collapse.
            $rows.find('.todo-si-toggle').each(function () {
                var t = $(this).data('target');
                if (t) $('.' + t).hide();
                $(this).find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });
            $rows.hide();
        }
        $(this).find('.todo-coa-caret').toggleClass('rotated', expanding);
    });
    $(document).on('click', '.todo-si-toggle', function (e) {
        e.stopPropagation();
        var target = $(this).data('target');
        $('.' + target).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    /* ── WORKLIST — Expand/Collapse All (per section) ── */
    $(document).on('click', '.todo-toggle-all', function () {
        var $btn = $(this);
        var $section = $btn.closest('.todo-worklist, .todo-flat-section');
        var expanding = $btn.data('state') !== 'expanded';

        // Dua lapis (PO -> Budget Plan), jadi keduanya ikut dibuka/ditutup —
        // membuka lapis Budget Plan saja tidak akan terlihat selama induk PO-nya
        // masih tertutup.
        $section.find('.todo-po-row, .todo-group-row').each(function () {
            var target = $(this).data('target');
            // Baris yang sedang tersaring cari/filter tetap tersembunyi.
            $section.find('.' + target).not('.todo-filtered-out').toggle(expanding);
            $(this).find('.todo-caret').toggleClass('rotated', expanding);
        });
        if (expanding) {
            // Lapis grup yang seluruh isinya tersaring ikut disembunyikan lagi.
            $section.find('.todo-group-row, .todo-po-row').each(function () {
                var $anak = $section.find('.' + $(this).data('target'));
                if ($anak.length && !$anak.not('.todo-filtered-out').length) $(this).hide();
            });
        }
        $btn.data('state', expanding ? 'expanded' : 'collapsed');
        $btn.html(expanding ? '<i class="fas fa-compress-alt mr-1"></i>Collapse All' : '<i class="fas fa-expand-alt mr-1"></i>Expand All');
    });

    /* ── WORKLIST — checkbox & bulk approve/reject (per section) ── */
    // CR di bawah PO bisa bertingkat (PO -> Budget Plan -> CR), jadi checkbox
    // grup menjangkau juga CR milik grup anak di bawahnya.
    function groupLeaves(group) {
        var $rows = $('.' + group);
        var $leaves = $rows.find('.todo-check');
        $rows.find('.todo-check-group').each(function () {
            $leaves = $leaves.add(groupLeaves($(this).data('group')));
        });
        return $leaves;
    }
    var BULK_MAX = 50; // sama dengan TodoListController::BULK_MAX_IDS
    function bulkTerlaluBanyak(ids) {
        if (ids.length <= BULK_MAX) return false;
        Swal.fire({ icon: 'warning', title: 'Terlalu banyak item',
            text: 'Maksimum ' + BULK_MAX + ' item per proses. Anda memilih ' + ids.length + '.' });
        return true;
    }
    function updateBulkBar($section) {
        var $checked = $section.find('.todo-check:checked');
        var n = $checked.length;
        var total = 0;
        $checked.each(function () { total += parseFloat($(this).data('amount')) || 0; });
        $section.find('.todo-bulk-count').text(n);
        $section.find('.todo-bulk-amount').text(n > 0 ? (' - ' + Math.round(total).toLocaleString('id-ID')) : '');
        $section.find('.todo-bulk-approve, .todo-bulk-reject').prop('disabled', n === 0);

        // Sinkronkan checkbox header grup: tercentang kalau semua CR dalam grup itu tercentang
        // (termasuk saat "select all" section ditekan, atau CR dicentang satu-satu).
        $section.find('.todo-check-group').each(function () {
            var $leaves = groupLeaves($(this).data('group'));
            $(this).prop('checked', $leaves.length > 0 && $leaves.filter(':checked').length === $leaves.length);
        });
    }
    $(document).on('change', '.todo-check', function () {
        updateBulkBar($(this).closest('.todo-worklist'));
    });
    $(document).on('change', '.todo-check-all', function () {
        var $section = $(this).closest('.todo-worklist');
        // Hanya baris yang lolos cari/filter — CR yang sedang tersembunyi oleh
        // filter tidak ikut terpilih untuk bulk approve/reject tanpa terlihat.
        $section.find('.todo-row').not('.todo-filtered-out').find('.todo-check').prop('checked', $(this).is(':checked'));
        updateBulkBar($section);
    });

    /* ── WORKLIST — checkbox di header grup: pilih semua CR dalam grup itu ── */
    $(document).on('click', '.todo-check-group', function (e) {
        e.stopPropagation();
    });
    $(document).on('change', '.todo-check-group', function () {
        var $section = $(this).closest('.todo-worklist');
        groupLeaves($(this).data('group')).filter(function () { return !$(this).closest('.todo-row').hasClass('todo-filtered-out'); })
            .prop('checked', $(this).is(':checked'));
        updateBulkBar($section);
    });

    $(document).on('click', '.todo-bulk-approve', function () {
        var $section = $(this).closest('.todo-worklist');
        var key = $section.data('section');
        var ids = $section.find('.todo-check:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length || bulkTerlaluBanyak(ids)) return;
        // Label ikut tombolnya: section settlement menutup CR ("Close"),
        // section review meng-approve-nya.
        var verb = $(this).text().trim() || 'Approve';
        Swal.fire({
            title: verb + ' ' + ids.length + ' CR?', icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, ' + verb, cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            var $form = $('#frmTodoBulkApprove-' + key);
            $form.find('input[name="ids[]"]').remove();
            ids.forEach(function (id) { $form.append('<input type="hidden" name="ids[]" value="' + id + '">'); });
            $form.submit();
        });
    });

    $(document).on('click', '.todo-bulk-reject', function () {
        var $section = $(this).closest('.todo-worklist');
        var key = $section.data('section');
        var ids = $section.find('.todo-check:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length || bulkTerlaluBanyak(ids)) return;
        $('#bulkRejectCount').text(ids.length);
        $('#bulkRejectNotes').val('').removeClass('is-invalid');
        $('#mdlBulkRejectCR').data('ids', ids).data('key', key).modal('show');
    });
    $('#btnBulkRejectConfirm').on('click', function () {
        var notes = $('#bulkRejectNotes').val().trim();
        if (!notes) { $('#bulkRejectNotes').addClass('is-invalid'); return; }
        var ids = $('#mdlBulkRejectCR').data('ids') || [];
        var key = $('#mdlBulkRejectCR').data('key');
        var $form = $('#frmTodoBulkReject-' + key);
        $form.find('input[name="ids[]"]').remove();
        ids.forEach(function (id) { $form.append('<input type="hidden" name="ids[]" value="' + id + '">'); });
        $form.find('textarea[name="notes"]').val(notes);
        $form.submit();
    });

    /* ── HEAD APPROVE ── */
    $(document).on('click', '.btn-head-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#headApproveText').text('Approve CR ' + code + '? Status akan menjadi CHECKED dan diteruskan ke Cost Control.');
        $('#frmHeadApprove').attr('action', '/budget-request/' + id + '/head-approve');
        $('#frmHeadApprove textarea').val('');
        $('#mdlHeadApprove').modal('show');
    });

    /* ── FINANCE / COST CONTROL VERIFY ── */
    $(document).on('click', '.btn-finance-verify', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#financeVerifyText').text('Verifikasi CR ' + code + '? Status akan menjadi VERIFIED dan menunggu approval CEO.');
        $('#frmFinanceVerify').attr('action', '/budget-request/' + id + '/finance-verify');
        $('#frmFinanceVerify textarea').val('');
        $('#mdlFinanceVerify').modal('show');
    });

    /* ── CEO APPROVE ── */
    $(document).on('click', '.btn-ceo-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#ceoApproveText').text('Approve CR ' + code + '? Status akan menjadi APPROVED dan siap masuk batch pembayaran.');
        $('#frmCeoApprove').attr('action', '/budget-request/' + id + '/ceo-approve');
        $('#frmCeoApprove textarea').val('');
        $('#mdlCeoApprove').modal('show');
    });

    /* ── GENERIC NOTES MODAL (Reject / Revise / Cancel / Reject Realisasi) ── */
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

    /* ── REJECT Budgeting / Treasury — modul lain, URL beda prefix dari
       budget-request/, jadi pakai action absolut dari data-action langsung
       (bukan concat seperti openNotesModal di atas) tapi modal #mdlNotes yang
       sama dipakai ulang. ── */
    function openNotesModalAbsolute(actionUrl, title, label, btnClass, btnText) {
        $('#mdlNotesTitle').html('<i class="fas fa-exclamation-triangle mr-2" style="color:var(--accent);"></i>' + title);
        $('#mdlNotesLabel').html(label + ' <span class="text-danger">*</span>');
        $('#mdlNotesAlert').hide();
        $('#frmNotes').attr('action', actionUrl);
        $('#frmNotes textarea').val('');
        $('#mdlNotesSubmitBtn').text(btnText).removeClass('btn-danger btn-warning').addClass(btnClass);
        $('#mdlNotes').modal('show');
    }
    $(document).on('click', '.todo-bp-reject', function () {
        openNotesModalAbsolute($(this).data('action'), 'Tolak Budget Plan', 'Alasan Penolakan', 'btn-danger', 'Tolak');
    });
    $(document).on('click', '.todo-bp-revise', function () {
        openNotesModalAbsolute($(this).data('action'), 'Revise Budget Plan → Draft', 'Catatan Revisi', 'btn-warning', 'Revise');
    });
    $(document).on('click', '.todo-dt-reject', function () {
        openNotesModalAbsolute($(this).data('action'), 'Tolak Batch', 'Alasan Penolakan', 'btn-danger', 'Tolak');
    });
    /* ── SETTLEMENT — HEAD CHECK / FINANCE VERIFY / CEO CLOSE ── */
    function openSettlementApproveModal(id, action, text, btnText) {
        $('#verifyRealText').text(text);
        $('#frmVerifyRealization').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmVerifyRealization textarea').val('');
        $('#btnVerifyRealSubmit').html('<i class="fas fa-check-double mr-1"></i> ' + btnText);
        $('#mdlVerifyRealization').modal('show');
    }

    $(document).on('click', '.btn-head-check-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, 'head-check-settlement',
            'Check Settlement CR ' + code + '? Status akan berubah menjadi SETTLE CHECKED, menunggu verifikasi Finance.',
            'Check → Settle Checked');
    });
    $(document).on('click', '.btn-finance-verify-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, 'finance-verify-settlement',
            'Verifikasi Settlement CR ' + code + '? Status akan berubah menjadi SETTLE VERIFIED, menunggu Close CEO.',
            'Verify → Settle Verified');
    });
    $(document).on('click', '.btn-settle-approve', function () {
        openSettlementApproveModal($(this).data('id'), 'settle-approve',
            'Setujui Expense Claim CR ' + $(this).data('code') + '? Pengajuan lanjut ke antrean Second Approve Approver.',
            'Approve → Second Approve');
    });
    $(document).on('click', '.btn-settle-reject', function () {
        openNotesModal($(this).data('id'), 'settle-reject', 'Reject Expense Claim', 'Alasan', 'btn-danger', 'Reject → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR kembali ke <strong>PAID</strong> untuk disettle ulang. Expense Claim yang sudah terbentuk dibatalkan.');
    });
    $(document).on('click', '.btn-second-reject', function () {
        openNotesModal($(this).data('id'), 'second-reject', 'Reject Expense Claim', 'Alasan', 'btn-danger', 'Reject → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR kembali ke <strong>PAID</strong> untuk disettle ulang. Expense Claim yang sudah terbentuk dibatalkan.');
    });
    $(document).on('click', '.btn-second-approve', function () {
        openSettlementApproveModal($(this).data('id'), 'second-approve',
            'Setujui Expense Claim CR ' + $(this).data('code') + '? Pengajuan masuk antrean Buat Batch Baru Treasury, jadi Process setelah dibatch, dan CLOSED otomatis setelah dibayar.',
            'Approve → Treasury');
    });
    $(document).on('click', '.btn-ceo-close-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, 'ceo-close-settlement',
            'Close Settlement CR ' + code + '? Status akan berubah menjadi CLOSED.',
            'Close');
    });

    $(document).on('click', '.btn-head-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'head-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });
    $(document).on('click', '.btn-manager-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'manager-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya. Second Approval yang belum dibayar ikut dibatalkan.');
    });
    $(document).on('click', '.btn-finance-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'finance-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });

    /* ============================== FILTER & SEARCH ============================== */
    // Filter checklist bergaya sama dengan halaman Cost Request (TesysColumnFilter),
    // tapi dibangun sendiri karena tabel Todo List bukan DataTables (baris grouped +
    // collapsible custom) — TesysColumnFilter.initRowFilters butuh instance DataTables.
    var todoFilterState = {}; // { field: Set(values) }

    function todoEscHtml(s) {
        return $(document.createElement('span')).text(s == null ? '' : s).html();
    }

    function closeTodoFilterDropdowns() {
        $('.todo-filter-dropdown').remove();
        // Penjaga klik-di-luar ikut dilepas — kalau ditinggal, handler milik
        // panel yang sudah hilang masih menunggu dan bisa menutup panel
        // berikutnya begitu ia dibuka.
        $(document).off('click.todoFilterOutside');
    }

    /* Satu tombol = buka DAN tutup. Pemicu yang sedang membuka panel dicatat
       di elemen panelnya, jadi klik kedua pada tombol yang sama dikenali
       sebagai perintah tutup, bukan gambar ulang. Sama seperti perilaku
       filter di halaman lain (lihat tesys-column-filter.js). */
    function isTodoDropdownOpenFor($btn) {
        var el = $btn.get(0), open = false;
        $('.todo-filter-dropdown').each(function () {
            if ($(this).data('todoOwner') === el) open = true;
        });
        return open;
    }

    function openTodoFilterDropdown($btn, field, label) {
        if (isTodoDropdownOpenFor($btn)) { closeTodoFilterDropdowns(); return; }
        closeTodoFilterDropdowns();
        var current = todoFilterState[field] || new Set();

        /* Filter bertingkat: daftar pilihan mengikuti filter LAIN yang sedang
           aktif. Setelah Manager dipilih, dropdown PO hanya menawarkan PO milik
           manager itu — bukan seluruh PO yang pasti mengosongkan tabel kalau
           dicentang. Filter field ini sendiri diabaikan supaya pilihan yang
           sudah tercentang tidak hilang dari daftarnya sendiri. */
        function todoRowPassesOtherFilters($tr) {
            for (var f in todoFilterState) {
                if (!todoFilterState.hasOwnProperty(f) || f === field) continue;
                var sel = todoFilterState[f];
                if (!sel || sel.size === 0) continue;
                if (!sel.has(($tr.data(f) || '').toString())) return false;
            }
            return true;
        }

        var uniqueRaw = [], seen = {};
        $('.todo-row').each(function () {
            var $tr = $(this);
            if (!todoRowPassesOtherFilters($tr)) return;
            var raw = ($tr.data(field) || '').toString();
            if (raw && !seen[raw]) { seen[raw] = true; uniqueRaw.push(raw); }
        });
        uniqueRaw.sort(function (a, b) { return a.localeCompare(b); });

        var $dd = $('<div class="todo-filter-dropdown"></div>');
        var $search = $('<input type="text" class="form-control form-control-sm mb-2" placeholder="Cari...">');
        var $list = $('<div class="todo-filter-list"></div>');

        uniqueRaw.forEach(function (raw) {
            var checked = current.has(raw) ? 'checked' : '';
            $list.append(
                '<label class="todo-filter-item"><input type="checkbox" class="todo-filter-check" value="' + todoEscHtml(raw) + '" ' + checked + '> <span>' + todoEscHtml(raw) + '</span></label>'
            );
        });
        if (!uniqueRaw.length) $list.append('<div class="text-muted" style="font-size:12px;">Tidak ada data</div>');

        $dd.append($search, $list);
        $dd.data('todoOwner', $btn.get(0));
        $('body').append($dd);

        TesysPlaceDropdown($dd, $btn);

        $search.on('input', function () {
            var q = $(this).val().toLowerCase();
            $list.find('label').each(function () { $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1); });
        });

        $list.on('change', '.todo-filter-check', function () {
            var selected = new Set();
            $list.find('.todo-filter-check:checked').each(function () { selected.add($(this).val()); });
            if (selected.size === 0) delete todoFilterState[field];
            else todoFilterState[field] = selected;

            $btn.toggleClass('todo-filter-active', selected.size > 0);
            applyTodoFilters();
        });

        $(document).one('click.todoFilterOutside', function handler(e) {
            if ($(e.target).closest('.todo-filter-dropdown, .todo-filter-btn').length === 0) {
                closeTodoFilterDropdowns();
            } else {
                $(document).one('click.todoFilterOutside', handler);
            }
        });
    }

    function initTodoRowFilters(containerSelector, fields) {
        var $container = $(containerSelector);
        fields.forEach(function (f) {
            var $btn = $('<button type="button" class="btn btn-outline-secondary btn-sm todo-filter-btn"></button>')
                .html(todoEscHtml(f.label) + ' <i class="fas fa-caret-down ml-1"></i>');
            $container.append($btn);
            $btn.on('click', function (e) {
                e.stopPropagation();
                openTodoFilterDropdown($btn, f.key, f.label);
            });
        });
    }

    initTodoRowFilters('#todoRowFilters', [
        { key: 'po', label: 'PO' },
        { key: 'pm', label: 'Manager' },
    ]);

    $('#btnTodoClearFilter').on('click', function () {
        $('#todoSearch').val('');
        todoFilterState = {};
        $('.todo-filter-btn').removeClass('todo-filter-active');
        applyTodoFilters();
    });

    /* Kartu section yang berisi tabel worklist. Sebagian kartu punya dua kelas
       sekaligus (flat + worklist) — himpunan jQuery tidak menggandakannya. */
    function todoSections() { return $('#todoApp').find('.todo-worklist, .todo-flat-section'); }

    /* Keadaan cari/filter per tabel (toolbar di kepala tiap kartu):
       { q: 'teks', cols: { <indeks kolom>: Set(nilai) } } */
    function todoSecState($sec) {
        var st = $sec.data('todoSec');
        if (!st) { st = { q: '', cols: {} }; $sec.data('todoSec', st); }
        return st;
    }
    function todoSecActive(st) {
        return !!(st.q || Object.keys(st.cols).some(function (k) { return st.cols[k].size > 0; }));
    }

    // Teks sel kolom ke-i pada baris data (baris data tidak memakai colspan).
    function todoCellText(tr, idx) {
        var td = tr.children[idx];
        return td ? $.trim($(td).text()).replace(/\s+/g, ' ') : '';
    }
    // Nilai field filter tabel: "c:<indeks kolom>" atau "d:<atribut data-*>".
    function todoFieldValue(tr, key) {
        if (key.indexOf('d:') === 0) return $.trim(tr.getAttribute('data-' + key.slice(2)) || '');
        return todoCellText(tr, parseInt(key.slice(2), 10));
    }

    function todoRowPassesSection(tr, st) {
        if (st.q) {
            var hay = (($(tr).data('search') || '') + ' ' + $(tr).text()).toLowerCase();
            if (hay.indexOf(st.q) === -1) return false;
        }
        for (var k in st.cols) {
            if (!st.cols.hasOwnProperty(k) || !st.cols[k].size) continue;
            if (!st.cols[k].has(todoFieldValue(tr, k))) return false;
        }
        return true;
    }

    /* Kembalikan satu section ke keadaan awal: lapis PO tampil, lapis Budget
       Plan & baris CR di section GROUPED tertutup.

       Kenapa ditutup, bukan dibiarkan apa adanya: selama pencarian tadi,
       induk dari setiap baris yang cocok sengaja dipaksa terbuka. Kalau
       keadaan itu ditinggalkan begitu saja, melepas filter menyisakan pohon
       setengah terbuka yang tidak pernah diminta siapa pun. */
    function todoResetSection($sec) {
        $sec.show();
        $sec.find('.todo-row').removeClass('todo-filtered-out');
        $sec.find('.todo-po-row').show();
        if (!$sec.find('.todo-po-row').length) {
            // Section tanpa lapis PO (pola lama): header grup tampil, baris flat tampil.
            $sec.find('.todo-group-row').show();
            if ($sec.hasClass('todo-flat-section')) $sec.find('.todo-row').show();
        } else {
            $sec.find('.todo-group-row, .todo-row').hide();
            $sec.find('.todo-caret').removeClass('rotated');
        }
        $sec.find('.todo-sec-nomatch').prop('hidden', true);
        $sec.find('.todo-sec-count').prop('hidden', true);
    }

    function applyTodoFilters() {
        var q = ($('#todoSearch').val() || '').toLowerCase().trim();
        var activeFields = Object.keys(todoFilterState).filter(function (k) { return todoFilterState[k].size > 0; });
        var globalOn = !!(q || activeFields.length);

        $('#btnTodoClearFilter').toggle(globalOn);

        var anyGlobal = false;
        todoSections().each(function () {
            var $sec = $(this);
            var st = todoSecState($sec);
            var secOn = todoSecActive(st);
            $sec.find('.todo-sec-bar').toggleClass('is-filtering', secOn);

            /* Section yang tidak sedang disaring dibiarkan apa adanya (grup yang
               dibuka user tetap terbuka) — hanya dikembalikan ke keadaan awal
               tepat saat filternya baru saja dilepas. */
            if (!globalOn && !secOn) {
                if (st.wasOn) todoResetSection($sec);
                st.wasOn = false;
                return;
            }
            st.wasOn = true;

            var total = 0, lolosGlobal = 0, lolos = 0;
            $sec.find('.todo-row').each(function () {
                var $row = $(this);
                var g = true;
                if (q && ($row.data('search') || '').toString().indexOf(q) === -1) g = false;
                activeFields.forEach(function (field) {
                    if (!todoFilterState[field].has(($row.data(field) || '').toString())) g = false;
                });
                var match = g && (!secOn || todoRowPassesSection(this, st));
                $row.toggle(match).toggleClass('todo-filtered-out', !match);
                total++;
                if (g) lolosGlobal++;
                if (match) lolos++;
            });

            // Header Budget Plan disembunyikan kalau semua CR anaknya tersaring habis.
            $sec.find('.todo-group-row').each(function () {
                var $children = $sec.find('.' + $(this).data('target'));
                $(this).toggle($children.filter(':visible').length > 0 || $children.length === 0);
            });
            // Lalu header PO (MN-03) — dinilai SETELAH header Budget Plan, karena
            // yang menentukan adalah ada-tidaknya budget plan yang masih tampil.
            $sec.find('.todo-po-row').each(function () {
                var $plans = $sec.find('.' + $(this).data('target'));
                $(this).toggle($plans.filter(':visible').length > 0 || $plans.length === 0);
            });

            /* Pencarian global yang tidak menyisakan baris menyembunyikan kartu
               seluruhnya (seperti sebelumnya). Filter milik tabel itu sendiri
               TIDAK menyembunyikan kartunya — toolbar-nya harus tetap terjangkau
               untuk melepas filter — melainkan menampilkan pesan di dalam kartu. */
            $sec.toggle(!globalOn || lolosGlobal > 0);
            $sec.find('.todo-sec-nomatch').prop('hidden', !(secOn && lolos === 0 && (!globalOn || lolosGlobal > 0)));
            $sec.find('.todo-sec-count').prop('hidden', !secOn)
                .text(lolos + ' dari ' + total);
            if (lolosGlobal > 0) anyGlobal = true;
        });

        $('#todoNoMatch').toggle(globalOn && !anyGlobal);
        if (globalOn) $('#todoEmptyState').hide();
    }
    $('#todoSearch').on('keyup', applyTodoFilters);

    /* Export mengikuti pencarian/penyaringan yang sedang aktif. Halaman ini
       BUKAN DataTable (baris disembunyikan langsung lewat applyTodoFilters),
       jadi TesysExportFiltered tidak bisa dipakai apa adanya — id dikumpulkan
       dari baris yang masih terlihat. Tanpa filter aktif, tautannya dibiarkan
       GET biasa supaya export penuh tetap utuh. */
    $('#btnExportTodo').on('click', function (e) {
        var $btn = $(this);

        try {
            // Dinilai dari hasil filter, bukan :visible — baris di dalam grup
            // yang sedang tertutup tetap bagian dari data yang diekspor.
            var $semua = $('.todo-row');
            var $tampil = $semua.not('.todo-filtered-out');
            if ($tampil.length === $semua.length) return; // GET biasa

            // Kunci "modul:id" (bukan id polos) karena id antar-modul bisa sama.
            var ids = [];
            $tampil.each(function () {
                var k = $(this).attr('data-export-key');
                if (k && ids.indexOf(k) === -1) ids.push(k);
            });
            if (!ids.length) {
                // Filter aktif tetapi tidak ada baris yang lolos: jangan jatuh ke
                // GET penuh (mengekspor SEMUA baris, berlawanan dengan filter).
                e.preventDefault();
                Swal.fire({ icon: 'info', title: 'Tidak ada baris untuk diekspor',
                    text: 'Tidak ada baris yang sesuai dengan filter yang sedang aktif.' });
                return;
            }

            e.preventDefault();
            var $f = $('<form method="POST"></form>').attr('action', $btn.attr('href'));
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            ids.forEach(function (id) {
                $f.append($('<input type="hidden" name="ids[]">').val(id));
            });
            $('body').append($f);
            $f[0].submit();
            $f.remove();
        } catch (err) {
            /* biarkan GET biasa berjalan */
        }
    });

    /* ── TOOLBAR PER TABEL: Cari · Filter · Export ─────────────────────────
       Satu baris toolbar di dalam setiap kartu worklist, di atas tabelnya —
       susunan yang sama dengan toolbar tabel Cost Request (cari & filter di
       kiri, export di kanan). Tabel Todo List bukan DataTables (baris
       bertingkat PO → Budget Plan → item dengan buka/tutup sendiri), jadi
       pencarian & filternya memakai mesin yang sama dengan filter global di
       atas (applyTodoFilters) — hanya cakupannya satu tabel.
       - Cari  : teks bebas di baris tabel itu (kode, deskripsi, nama, PO, …).
       - Filter: checklist nilai per field. Field diambil dari kolom kategori
                 yang ada di tabel itu (Beneficiary/PIC, PO, Kategori, …) dan
                 dari atribut baris (Manager = data-pm, PO = data-po) untuk
                 tabel yang tidak menampilkan kolomnya.
       - Export: Excel berisi baris tabel itu yang lolos cari/filter, lewat
                 endpoint export yang sudah ada (ids "modul:id"). */

    // Kolom kategori yang layak difilter, dicocokkan dengan judul kolomnya.
    var TODO_SEC_COLS = [
        { re: /^(beneficiary|pic|beneficiary \/ pic)$/i, label: 'Beneficiary / PIC', urut: 1 },
        { re: /^manager$/i,                              label: 'Manager',           urut: 2 },
        { re: /^(po|no\. po)$/i,                         label: 'PO',                urut: 3 },
        { re: /^(kategori|type|tipe)$/i,                 label: null,                urut: 4 },
        { re: /^(input by|requestor|pemohon)$/i,         label: null,                urut: 5 },
        { re: /^(status|bank|customer)$/i,               label: null,                urut: 6 }
    ];
    // Atribut baris sebagai cadangan bila kolomnya tidak ditampilkan tabel itu.
    var TODO_SEC_ATTRS = [
        { attr: 'pm', label: 'Manager', urut: 2 },
        { attr: 'po', label: 'PO',      urut: 3 }
    ];

    function todoSecFields($sec) {
        var $ths = $sec.find('.card-body table').first().find('thead tr').first().children('th');
        var rows = $sec.find('.todo-row').toArray();
        var fields = [], dipakai = {};

        $ths.each(function (i) {
            if ((parseInt($(this).attr('colspan'), 10) || 1) > 1) return;
            var judul = $.trim($(this).clone().find('i, .todo-sort-ic').remove().end().text());
            for (var j = 0; j < TODO_SEC_COLS.length; j++) {
                var c = TODO_SEC_COLS[j];
                if (!c.re.test(judul)) continue;
                var label = c.label || judul;
                if (dipakai[label]) return;
                dipakai[label] = true;
                fields.push({ key: 'c:' + i, label: label, urut: c.urut });
                return;
            }
        });
        TODO_SEC_ATTRS.forEach(function (a) {
            if (dipakai[a.label]) return;
            var ada = rows.some(function (tr) { return $.trim(tr.getAttribute('data-' + a.attr) || '') !== ''; });
            if (!ada) return;
            dipakai[a.label] = true;
            fields.push({ key: 'd:' + a.attr, label: a.label, urut: a.urut });
        });
        fields.sort(function (x, y) { return x.urut - y.urut; });

        // Nilai unik + jumlah baris per nilai.
        fields.forEach(function (f) {
            var hitung = {};
            rows.forEach(function (tr) {
                var v = todoFieldValue(tr, f.key);
                if (!v) return;
                hitung[v] = (hitung[v] || 0) + 1;
            });
            f.counts = hitung;
            f.values = Object.keys(hitung).sort(function (a, b) { return a.localeCompare(b, 'id'); });
        });
        return fields.filter(function (f) { return f.values.length > 0; });
    }

    function todoSecFilterCount(st) {
        return Object.keys(st.cols).reduce(function (t, k) { return t + (st.cols[k].size ? 1 : 0); }, 0);
    }

    function todoSecSyncButton($sec) {
        var st = todoSecState($sec);
        var n = todoSecFilterCount(st);
        $sec.find('.todo-sec-filter-btn').toggleClass('todo-filter-active', n > 0)
            .find('.todo-sec-filter-n').text(n || '').prop('hidden', !n);
        $sec.find('.todo-sec-reset').prop('hidden', !todoSecActive(st));
    }

    /* Panel Filter tabel — bentuk sama dengan panel Filter di Cost Request
       (TesysColumnFilter): judul FILTER, grid tombol per field, tombol Reset.
       Tiap tombol field membuka daftar bercentang ber-kotak-cari di bawahnya;
       pilihan langsung berlaku tanpa tombol Terapkan. */
    function todoSecCloseList() {
        $('.todo-sec-list-pop').remove();
        $('.todo-fp .tcf-row-btn').removeClass('is-open');
    }
    function todoSecCloseFilter() {
        todoSecCloseList();
        $('.todo-fp').remove();
        $('.todo-sec-filter-btn').attr('aria-expanded', 'false');
        $(document).off('.todoSecOutside');
    }

    function todoSecFieldButton($btn, f, sel) {
        $btn.toggleClass('tcf-row-active', sel.size > 0)
            .html(todoEscHtml(f.label) + (sel.size ? ' <span class="col-mf-badge">' + sel.size + '</span>' : '') +
                  ' <i class="fas fa-caret-down ml-1" aria-hidden="true"></i>');
    }

    function todoSecOpenList($sec, $panel, $fbtn, f) {
        var milikIni = $('.todo-sec-list-pop').data('todoOwner') === $fbtn.get(0);
        todoSecCloseList();
        if (milikIni) return; // klik kedua pada tombol yang sama = tutup

        var st = todoSecState($sec);
        var sel = st.cols[f.key] || new Set();
        var $dd = $('<div class="tcf-dropdown todo-sec-list-pop" role="dialog"></div>').attr('aria-label', 'Filter ' + f.label);
        var $find = $('<input type="text" class="form-control form-control-sm mb-2" placeholder="Cari...">').attr('aria-label', 'Cari ' + f.label);
        var $list = $('<div class="tcf-list"></div>');
        f.values.forEach(function (v) {
            var $lbl = $('<label class="tcf-item"></label>');
            $('<input type="checkbox" class="tcf-check">').val(v).prop('checked', sel.has(v)).appendTo($lbl);
            $('<span class="todo-sec-pop-v"></span>').text(v).appendTo($lbl);
            $('<span class="todo-sec-pop-n"></span>').text(f.counts[v]).appendTo($lbl);
            $list.append($lbl);
        });
        var $kosong = $('<div class="text-muted todo-sec-list-empty" hidden>Tidak ada yang cocok</div>');
        $dd.append($find, $list, $kosong).data('todoOwner', $fbtn.get(0));
        $('body').append($dd);
        $fbtn.addClass('is-open');
        TesysPlaceDropdown($dd, $fbtn);
        $find.trigger('focus');

        $find.on('input', function () {
            var q = $.trim($(this).val()).toLowerCase();
            var ada = 0;
            $list.find('label').each(function () {
                var cocok = $(this).find('.todo-sec-pop-v').text().toLowerCase().indexOf(q) !== -1;
                $(this).toggle(cocok);
                if (cocok) ada++;
            });
            $kosong.prop('hidden', ada > 0);
        });
        $list.on('change', '.tcf-check', function () {
            var set = new Set();
            $list.find('.tcf-check:checked').each(function () { set.add($(this).val()); });
            if (set.size) st.cols[f.key] = set; else delete st.cols[f.key];
            todoSecFieldButton($fbtn, f, set);
            todoSecSyncButton($sec);
            applyTodoFilters();
        });
    }

    function todoSecOpenFilter($sec, $btn) {
        var milikIni = $('.todo-fp').data('todoOwner') === $btn.get(0);
        todoSecCloseFilter();
        closeTodoFilterDropdowns();
        if (milikIni) return;

        var st = todoSecState($sec);
        var fields = todoSecFields($sec);
        var $panel = $('<div class="todo-fp" role="dialog" aria-label="Filter tabel"></div>');
        $panel.append('<div class="tcf-pop-title">FILTER</div>');
        if (!fields.length) {
            $panel.append('<div class="text-muted" style="font-size:12px;">Tabel ini tidak punya kolom kategori untuk difilter. Gunakan kotak cari.</div>');
        } else {
            var $grid = $('<div class="todo-fp-grid"></div>').appendTo($panel);
            fields.forEach(function (f) {
                var $fbtn = $('<button type="button" class="btn btn-sm btn-outline-secondary tcf-row-btn"></button>');
                todoSecFieldButton($fbtn, f, st.cols[f.key] || new Set());
                $fbtn.on('click', function (e) {
                    e.stopPropagation();
                    todoSecOpenList($sec, $panel, $fbtn, f);
                });
                $grid.append($fbtn);
            });
            $('<div class="tcf-pop-foot"><button type="button" class="btn btn-primary btn-sm todo-fp-reset"><i class="fas fa-undo mr-1"></i>Reset</button></div>')
                .appendTo($panel)
                .on('click', '.todo-fp-reset', function () {
                    st.cols = {};
                    todoSecCloseList();
                    $grid.find('.tcf-row-btn').each(function (i) { todoSecFieldButton($(this), fields[i], new Set()); });
                    todoSecSyncButton($sec);
                    applyTodoFilters();
                });
        }

        $panel.data('todoOwner', $btn.get(0));
        $('body').append($panel);
        $btn.attr('aria-expanded', 'true');
        TesysPlaceDropdown($panel, $btn);

        // Klik di luar panel & daftar menutup; Escape menutup daftar dulu, lalu panel.
        setTimeout(function () {
            $(document).on('click.todoSecOutside', function (e) {
                var $t = $(e.target);
                if ($t.closest('.todo-sec-list-pop').length) return;
                if ($t.closest('.todo-fp').length) { if (!$t.closest('.tcf-row-btn').length) todoSecCloseList(); return; }
                if ($t.closest('.todo-sec-filter-btn').length) return;
                todoSecCloseFilter();
            });
            $(document).on('keydown.todoSecOutside', function (e) {
                if (e.key !== 'Escape') return;
                if ($('.todo-sec-list-pop').length) todoSecCloseList(); else todoSecCloseFilter();
            });
            $(window).on('resize.todoSecOutside', todoSecCloseFilter);
        }, 0);
    }

    function todoSecExport($sec) {
        var ids = [];
        $sec.find('.todo-row').not('.todo-filtered-out').each(function () {
            var k = $(this).attr('data-export-key');
            if (k && ids.indexOf(k) === -1) ids.push(k);
        });
        if (!ids.length) {
            Swal.fire({ icon: 'info', title: 'Tidak ada baris untuk diekspor',
                text: 'Tidak ada baris di tabel ini yang sesuai dengan pencarian/filter yang sedang aktif.' });
            return;
        }
        var $f = $('<form method="POST"></form>').attr('action', $('#btnExportTodo').attr('href'));
        $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
        ids.forEach(function (id) { $f.append($('<input type="hidden" name="ids[]">').val(id)); });
        $('body').append($f);
        $f[0].submit();
        $f.remove();
    }

    (function pasangToolbarTabel() {
        todoSections().each(function () {
            var $sec = $(this);
            var $head = $sec.children('.card-header').first();
            if (!$head.length || !$sec.find('.todo-row').length || $sec.find('.todo-sec-bar').length) return;
            var judul = $.trim($head.find('.card-title').first().clone().children('.badge, .text-muted').remove().end().text());

            $sec.addClass('todo-sec-card');
            var $bar = $(
                '<div class="todo-sec-bar">' +
                    '<div class="todo-sec-left">' +
                        '<label class="todo-sec-search">' +
                            '<span class="todo-sec-search-ic" aria-hidden="true"><i class="fas fa-search"></i></span>' +
                            '<input type="search" class="todo-sec-q" placeholder="Cari di tabel ini…" aria-label="Cari di tabel ' + todoEscHtml(judul) + '">' +
                        '</label>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm todo-sec-filter-btn" title="Filter" aria-haspopup="dialog" aria-expanded="false">' +
                            '<i class="fas fa-filter" aria-hidden="true"></i><span class="todo-sec-lbl">Filter</span>' +
                            '<span class="badge badge-pill todo-sec-filter-n" hidden></span>' +
                        '</button>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm todo-sec-reset" title="Reset cari & filter tabel ini" hidden>' +
                            '<i class="fas fa-eraser" aria-hidden="true"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm todo-sec-export" title="Export tabel ini ke Excel">' +
                            '<i class="fas fa-file-excel" aria-hidden="true"></i><span class="todo-sec-lbl">Export</span>' +
                        '</button>' +
                        '<span class="todo-sec-count" hidden></span>' +
                    '</div>' +
                '</div>');
            $head.after($bar);

            /* Expand/Collapse All untuk tabel bergrup (lapis PO / Budget Plan,
               termasuk yang dikelompokkan JS) — dipindah ke toolbar ini; tabel
               bergrup yang belum punya ikut mendapatkannya. */
            if ($sec.find('.todo-po-row, .todo-group-row').length) {
                var $lama = $sec.find('.todo-toggle-all').first();
                var $tgl = $lama.length ? $lama : $('<button type="button" data-state="collapsed"></button>')
                    .html('<i class="fas fa-expand-alt mr-1"></i>Expand All');
                var $wadahLama = $lama.parent();
                $tgl.attr('class', 'btn btn-outline-secondary btn-sm todo-toggle-all').removeAttr('style')
                    .attr('title', 'Buka/tutup semua grup');
                $bar.find('.todo-sec-export').after($tgl); // urutan: Cari · Filter · Export · Expand All
                if ($wadahLama.length && !$wadahLama.children().length) $wadahLama.remove();
            }

            // Pesan "tidak ada yang cocok" milik tabel ini, di bawah tabelnya.
            $sec.find('.card-body').first().append(
                '<div class="todo-sec-nomatch" hidden><i class="fas fa-filter mr-1" aria-hidden="true"></i>' +
                'Tidak ada baris yang cocok dengan pencarian/filter tabel ini. ' +
                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline todo-sec-reset-link">Reset</button></div>');

            var tunda = null;
            $bar.on('input', '.todo-sec-q', function () {
                var v = $(this).val();
                clearTimeout(tunda);
                tunda = setTimeout(function () {
                    todoSecState($sec).q = $.trim(v).toLowerCase();
                    todoSecSyncButton($sec);
                    applyTodoFilters();
                }, 160);
            });
            $bar.on('click', '.todo-sec-filter-btn', function (e) {
                e.stopPropagation();
                todoSecOpenFilter($sec, $(this));
            });
            $bar.on('click', '.todo-sec-export', function () { todoSecExport($sec); });
            $sec.on('click', '.todo-sec-reset, .todo-sec-reset-link', function () {
                var st = todoSecState($sec);
                st.q = ''; st.cols = {};
                $bar.find('.todo-sec-q').val('');
                todoSecCloseFilter();
                todoSecSyncButton($sec);
                applyTodoFilters();
            });
        });
    })();

    /* ── STRIP TASK PER MODUL ──
       Tile per modul (jumlah task = badge judul kartu section), dikelompokkan
       per fase seperti strip status di halaman Cost Request. Klik tile
       menyaring halaman: hanya daftar modul itu yang tampil (tile terisi
       merah); klik lagi untuk menampilkan semua. */
    (function pasangStripModul() {
        var $host = $('#todoJump');
        var $secs = todoSections().filter('[id^="tl-"]');
        if (!$secs.length) return;

        // Modul ditentukan dari id kartu (#tl-<key>); fase = kelompok tile.
        var MODUL = [
            { re: /^tl-(head|cost_control|ceo|reject)/, nama: 'Cost Request',   fase: 'Cost Request' },
            { re: /^tl-(settle|second-payment)/,        nama: 'Settlement',     fase: 'Cost Request' },
            { re: /^tl-bp-/,                            nama: 'Budgeting',      fase: 'Budgeting & PO' },
            { re: /^tl-po-/,                            nama: 'Purchase Order', fase: 'Budgeting & PO' },
            { re: /^tl-batch-/,                         nama: 'Treasury',       fase: 'Pembayaran' },
            { re: /^tl-reg-/,                           nama: 'Cost Register',  fase: 'Pembayaran' },
            { re: /^tl-invoice-/,                       nama: 'Invoice',        fase: 'Penagihan' },
            { re: /^tl-collection/,                     nama: 'Collection',     fase: 'Penagihan' },
            { re: /^tl-tt-/,                            nama: 'Trouble Ticket', fase: 'Lainnya' }
        ];
        var URUT_FASE = ['Cost Request', 'Budgeting & PO', 'Pembayaran', 'Penagihan', 'Lainnya'];

        var perNama = {}, perFase = {};
        $secs.each(function () {
            var id = this.id;
            var m = MODUL.filter(function (x) { return x.re.test(id); })[0] || { nama: 'Lainnya', fase: 'Lainnya' };
            if (!perNama[m.nama]) {
                perNama[m.nama] = { nama: m.nama, ids: [], total: 0, urut: MODUL.indexOf(m) };
                (perFase[m.fase] = perFase[m.fase] || []).push(perNama[m.nama]);
            }
            perNama[m.nama].ids.push(id);
            perNama[m.nama].total += parseInt($.trim($(this).children('.card-header').find('.card-title .badge').first().text()), 10) || 0;
        });

        var $row = $host.find('.wf-phase-row');
        URUT_FASE.forEach(function (fase) {
            var tiles = perFase[fase];
            if (!tiles) return;
            tiles.sort(function (x, y) { return x.urut - y.urut; });
            var $phase = $('<section class="wf-phase"></section>').css('flex-grow', tiles.length)
                .append($('<div class="wf-phase-title"></div>').text(fase));
            var $steps = $('<div class="wf-phase-steps"></div>');
            tiles.forEach(function (t) {
                // Satu kata per baris, seperti label strip Cost Request.
                var label = t.nama.split(/\s+/).map(function (w) { return todoEscHtml(w); }).join('<br>');
                $('<div class="wf-tl-step" role="button" tabindex="0" aria-pressed="false"></div>')
                    .attr({ 'data-ids': t.ids.join(' '), 'data-wf-color': 'secondary', title: t.nama + ': ' + t.total + ' task' })
                    .html('<span class="badge wf-tl-node ' + (t.total ? 'badge-secondary' : 'is-empty') + '">' + t.total + '</span>' +
                          '<div class="wf-tl-label ' + (t.total ? 'is-on' : 'is-off') + '">' + label + '</div>')
                    .appendTo($steps);
            });
            $row.append($phase.append($steps));
        });
        $host.prop('hidden', false);

        function pilih($step) {
            var aktif = !$step.hasClass('wf-active');
            $host.find('.wf-tl-step').removeClass('wf-active').attr('aria-pressed', 'false');
            $secs.removeClass('todo-mod-hidden');
            if (aktif) {
                var ids = ($step.attr('data-ids') || '').split(' ');
                $step.addClass('wf-active').attr('aria-pressed', 'true');
                $secs.each(function () { $(this).toggleClass('todo-mod-hidden', ids.indexOf(this.id) === -1); });
                var el = document.getElementById(ids[0]);
                if (el && el.getBoundingClientRect().top < 0) el.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        }
        $host.on('click', '.wf-tl-step', function () { pilih($(this)); });
        $host.on('keydown', '.wf-tl-step', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pilih($(this)); }
        });
    })();

    /* ── KLIK KODE = SALIN KODE ──
       Kode (CR, batch, PO, invoice, …) di tabel kini menyalin dirinya ke
       clipboard; membuka detail cukup dengan klik barisnya (handler
       tr.todo-row). Dipasang di fase capture pada #todoApp supaya handler
       pembuka lama pada tombol/tautan kode tidak ikut berjalan. Saat baris
       yang membuka detail, tanda todoBukaBaris mengizinkan klik itu lewat. */
    var todoBukaBaris = false;
    function todoSalin(teks, selesai) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(teks).then(selesai, function () {});
            return;
        }
        var t = document.createElement('textarea');
        t.value = teks; t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); selesai(); } catch (err) {}
        document.body.removeChild(t);
    }
    var todoAppEl = document.getElementById('todoApp');
    if (todoAppEl) {
        todoAppEl.addEventListener('click', function (e) {
            if (todoBukaBaris) return;
            var btn = e.target.closest && e.target.closest('tr.todo-row .cr-number-btn');
            if (!btn || !todoAppEl.contains(btn)) return;
            e.preventDefault();
            e.stopPropagation();
            var $b = $(btn);
            if ($b.data('todoCopying')) return;
            var kode = $.trim($b.attr('data-code') || $b.text());
            if (!$b.attr('data-code')) $b.attr('data-code', kode);
            todoSalin(kode, function () {
                $b.data('todoCopying', true).addClass('is-copied')
                  .css('min-width', $b.outerWidth() + 'px').text('Tersalin ✓');
                setTimeout(function () {
                    $b.text(kode).removeClass('is-copied').css('min-width', '').data('todoCopying', false);
                }, 1100);
            });
        }, true);
        $('#todoApp tr.todo-row .cr-number-btn').attr('title', 'Klik untuk menyalin kode');
    }

    $('#btnTodoRefresh').on('click', function () { location.reload(); });

    /* ============================== DETAIL DRAWER ==============================
       Susunan & gaya disamakan dengan drawer detail di halaman Cost Request
       (budget-request/index.blade.php): kepala putih x-drawer, kartu nilai +
       alur dokumen (TesysWorkflow), tab Request/Settlement dikelompokkan per
       Chart of Account, lampiran sebagai kartu yang membuka carousel di sisa
       layar kiri, dan tab Log gaya chat dengan kotak catatan. Data tetap dari
       endpoint yang sudah ada — tidak ada query, route, atau aturan baru. */

    // Keterangan status berasal dari App\Support\WorkflowStatus (satu sumber
    // dengan tabel, drawer, dan halaman show Cost Request).
    var todoStatusMeta = @json(collect(\App\Models\BudgetRequest::STATUSES)->mapWithKeys(fn ($s) => [
        $s => \App\Support\WorkflowStatus::meta($s, 'budget-request'),
    ]));
    var todoCurrentUserId = parseInt('{{ (int) Auth::id() }}', 10) || 0;
    var todoDrawerId = null;

    function esc(s) { return $(document.createElement('span')).text(s == null ? '' : s).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }

    function setTabCount(sel, n) {
        var el = $(sel);
        if (n && n > 0) { el.text(n).show(); }
        else { el.text('').hide(); }
    }

    /* Keadaan kosong ringkas untuk ruang sempit di dalam drawer. */
    function kosongRingkas(pesan) {
        return '<div class="ts-empty ts-empty-compact"><p class="ts-empty-msg">' + esc(pesan) + '</p></div>';
    }

    function drawerKV(pasangan) {
        var html = '<dl class="ts-dw-kv">';
        pasangan.forEach(function (p) {
            if (p[1] === null || p[1] === undefined || p[1] === '') return;
            html += '<dt>' + esc(p[0]) + '</dt><dd' + (p[2] ? ' class="is-amount"' : '') + '>' + esc(p[1]) + '</dd>';
        });
        return html + '</dl>';
    }

    /* Kartu lampiran: gambar tampil sebagai thumbnail, file lain sebagai ikon.
       Klik kartu membuka carousel untuk seluruh lampiran satu grup. */
    window.__crAttGroups = {};
    function renderAttGrid(atts, grup) {
        if (!atts || !atts.length) return kosongRingkas('Belum ada lampiran.');
        window.__crAttGroups[grup] = atts;
        return '<div class="cr-att-grid">' + atts.map(function (a, i) {
            var url = '/storage/' + a.path;
            var thumb = a.is_image
                ? '<img loading="lazy" src="' + escAttr(url) + '" alt="" class="cr-att-thumb">'
                : '<span class="cr-att-icon"><i class="fas ' + (a.is_pdf ? 'fa-file-pdf' : 'fa-file-alt') + '"></i></span>';
            return '<button type="button" class="cr-att-card" data-group="' + grup + '" data-idx="' + i + '" title="Buka lampiran">'
                + thumb
                + '<span class="cr-att-name">' + esc(a.original_name) + '</span>'
                + (a.size_formatted ? '<span class="cr-att-meta">' + esc(a.size_formatted) + '</span>' : '')
                + '</button>';
        }).join('') + '</div>';
    }

    /* Kelompokkan baris (detail CR / realisasi) per Chart of Account, urutan
       kemunculan pertama dipertahankan supaya sama dengan urutan input. */
    function groupByAccount(rows) {
        var order = [], map = {};
        (rows || []).forEach(function (r) {
            var acc = r.account || '-';
            if (!map[acc]) { map[acc] = []; order.push(acc); }
            map[acc].push(r);
        });
        return order.map(function (acc) { return { account: acc, items: map[acc] }; });
    }

    // Var = Request - Settlement. Hijau = sisa, merah = lebih.
    function fmtVar(n) {
        n = Math.round(n || 0);
        if (n === 0) return '<span class="text-muted">0</span>';
        return '<span style="color:' + (n > 0 ? 'var(--success)' : 'var(--danger)') + ';font-weight:600;">'
            + esc((n > 0 ? '' : '-') + Math.abs(n).toLocaleString('id-ID')) + '</span>';
    }

    function expandAllBtn(body) {
        return '<div class="d-flex justify-content-end mb-2">' +
            '<button type="button" class="btn btn-xs btn-outline-secondary td-grp-toggle-all" data-body="' + body + '" data-expanded="0">' +
            '<i class="fas fa-angle-double-down mr-1"></i>Expand All</button></div>';
    }

    /* Lini masa tahap CR dari log status — sama dengan drawer Cost Request.
       Memakai kejadian TERBARU tiap tahap dan hanya yang terjadi setelah
       pengajuan terakhir, supaya CR yang pernah ditolak lalu diajukan ulang
       tidak menampilkan tanggal dari putaran lama. */
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
            tahap('Dibuat', function () { return false; }, d.created_at),
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
        return baris;
    }

    /* Satu baris chat di tab Log. Catatan bebas di-escape; riwayat status
       dirender seperti di drawer Cost Request. */
    function renderLogRow(log) {
        var mine = log.user_id && todoCurrentUserId && log.user_id === todoCurrentUserId;
        var isNote = log.kind === 'note';
        var actor = log.actor || 'System';
        var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
        return '<div class="cr-chat-row ' + (mine ? 'mine' : '') + '">' +
            '<div class="cr-chat-av">' + (log.avatar ? '<img src="' + escAttr(log.avatar) + '" alt="">' : esc(initials)) + '</div>' +
            '<div>' +
                '<div class="cr-chat-meta"><strong>' + esc(actor) + '</strong> · ' + esc(log.date) + '</div>' +
                '<div class="cr-chat-bubble">' +
                    (isNote
                        ? '<div class="cr-chat-note" style="margin-top:0;">' + esc(log.notes) + '</div>'
                        : '<span class="badge badge-' + esc(log.action_color || 'secondary') + ' px-2 py-1" style="font-size:9px;">' + esc(log.action_label) + '</span>' +
                          (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + esc(log.status_from_label || log.status_from) + ' → ' + esc(log.status_to_label || log.status_to) + '</span>' : '') +
                          (log.notes ? '<div class="cr-chat-note">' + esc(log.notes) + '</div>' : '')) +
                '</div>' +
            '</div>' +
        '</div>';
    }

    /* Tab Log setinggi area gulir drawer supaya chat menempel di bawah walau
       isinya sedikit; lalu gulir ke pesan terbaru. */
    function aturTinggiLog(gulirKeBawah) {
        var body = document.getElementById('todoDrawer-body');
        var $tabs = $('#todoDetailTabs');
        if (!body || !$tabs.length) return;
        var cs = getComputedStyle(body);
        var tinggi = body.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)
                   - $tabs.outerHeight() - parseFloat($tabs.css('margin-bottom')) - parseFloat($tabs.css('margin-top'));
        $('#todoPaneLog').css('min-height', Math.max(tinggi, 0));
        if (gulirKeBawah) body.scrollTop = body.scrollHeight;
    }
    $(document).on('shown.bs.tab', '#todoDetailTabs a[data-toggle="tab"]', function () {
        this.scrollIntoView({ block: 'nearest', inline: 'nearest' }); // tab aktif selalu terlihat
        if ($(this).attr('href') === '#todoPaneLog') aturTinggiLog(true);
    });
    $(window).on('resize', function () { if ($('#todoPaneLog').hasClass('active')) aturTinggiLog(false); });

    /* Mobile: geser kiri/kanan pada isi drawer untuk pindah tab. Di tab Info,
       geser kanan menutup drawer. Gesekan yang dimulai di tabel/tab/kotak
       isian dibiarkan (itu gulir mendatar atau mengetik). */
    (function () {
        var x0 = null, y0 = null, abaikan = false;
        $(document).on('touchstart', '#todoDrawer-body', function (e) {
            var t = e.originalEvent.touches[0];
            x0 = t.clientX; y0 = t.clientY;
            abaikan = window.innerWidth > 768 || e.originalEvent.touches.length > 1 ||
                $(e.target).closest('.table-responsive, .todo-pichist-wrap, #todoDetailTabs, textarea, input, select').length > 0;
        });
        $(document).on('touchend', '#todoDrawer-body', function (e) {
            if (x0 === null || abaikan) { x0 = null; return; }
            var t = e.originalEvent.changedTouches[0];
            var dx = t.clientX - x0, dy = t.clientY - y0;
            x0 = null;
            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            var $tabs = $('#todoDetailTabs li:visible a');
            var i = $tabs.index($tabs.filter('.active'));
            if (dx < 0) {
                if ($tabs[i + 1]) $($tabs[i + 1]).tab('show');
            } else if (i > 0) {
                $($tabs[i - 1]).tab('show');
            } else {
                TesysDrawer.close('todoDrawer');
            }
        });
    })();

    $(document).on('submit', '#todoLogForm', function (e) {
        e.preventDefault();
        var $f = $(this), $in = $('#todoLogInput'), $btn = $('#todoLogSend');
        var teks = $.trim($in.val());
        if (!teks) { $in.focus(); return; }
        $btn.prop('disabled', true);
        $.post('{{ url('/budget-request') }}/' + $f.data('id') + '/log-note', { _token: $('meta[name="csrf-token"]').attr('content'), notes: teks })
            .done(function (res) {
                if (!res || !res.log) return;
                $('#todoLogBody .ts-empty').remove();
                $('#todoLogBody').append(renderLogRow(res.log));
                $in.val('');
                var body = document.getElementById('todoDrawer-body');
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
    $(document).on('click', '#todoDrawer .cr-copy-code', function () {
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

    /* ── TAB INFO ── */
    function isiInfo(d) {
        var meta = todoStatusMeta[d.status] || { label: d.status_label || d.status, description: null, tone: 'neutral' };
        var html = '';

        // Badge tipe: Cash Advance burgundy lembut, selain itu biru lembut.
        var tipeBadge = '';
        if (d.type) {
            var gayaTipe = d.is_cash_advance
                ? 'background:var(--accent-tint);color:var(--accent-text);border:1px solid var(--accent-tint-2);'
                : 'background:var(--info-bg);color:var(--info);border:1px solid var(--info-bg);';
            tipeBadge = '<span style="' + gayaTipe + 'font-size:10px;font-weight:700;padding:2px 8px;border-radius:var(--radius-sm);white-space:nowrap;">'
                      + esc(d.type) + '</span>';
        }
        if (d.is_nob) tipeBadge += ' <span class="badge badge-danger" style="font-size:10px;">NOB</span>';

        // Settlement/Second Payment/Refund hanya ditampilkan kalau nilainya > 0.
        var sisi = '';
        if ((parseInt(d.total_realization, 10) || 0) > 0) {
            sisi += '<span class="ts-dw-amount-side"><span>Settlement</span><strong>' + esc(d.total_realization_formatted) + '</strong></span>';
        }
        if ((parseInt(d.settlement_overage, 10) || 0) > 0) {
            sisi += '<span class="ts-dw-amount-side"><span>Second Payment</span><strong>' + esc(d.settlement_overage_formatted) + '</strong></span>';
        } else if ((parseInt(d.refund, 10) || 0) > 0) {
            sisi += '<span class="ts-dw-amount-side"><span>Refund</span><strong>' + esc(d.refund_formatted) + '</strong></span>';
        }

        html += '<div class="ts-dw-section"><div class="ts-dw-amount">'
             +    '<span class="ts-dw-amount-main">'
             +      '<span class="ts-dw-amount-label">Nilai pengajuan</span>'
             +      '<span class="ts-dw-amount-value">' + esc(d.amount_formatted || '-') + '</span>'
             +      '<span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px;">'
             +        tipeBadge
             +        '<span class="ts-status ts-status-' + esc(meta.tone) + ' ts-status-sm"' + (meta.description ? ' title="' + escAttr(meta.description) + '"' : '') + '>' + esc(meta.label) + '</span>'
             +      '</span>'
             +    '</span>'
             +    sisi
             +  '</div>'
             +  (window.TesysWorkflow ? TesysWorkflow.render('budget-request', d.status) : '')
             +  '</div>';

        // Chart of Account unik yang dipakai CR.
        var coaUnik = [];
        (d.details || []).forEach(function (det) {
            var acc = det.account || '';
            if (acc && acc !== '-' && coaUnik.indexOf(acc) === -1) coaUnik.push(acc);
        });
        if (coaUnik.length) {
            html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Chart of Account</p>'
                 +  '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
            coaUnik.forEach(function (acc) {
                html += '<span style="background:var(--surface-2);color:var(--text-dim);border:1px solid var(--border);font-size:11px;font-weight:600;'
                     +  'padding:3px 10px;border-radius:var(--radius-sm);">' + esc(acc) + '</span>';
            });
            html += '</div></div>';
        }

        // Informasi pengajuan — Purchase Order / Budget Plan dilebur jadi satu
        // baris Reference (Kode - Scope of Work).
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
                 ['Bank Account', d.beneficiary && d.bank && d.bank !== '-'
                     ? d.beneficiary + ' (' + d.bank + (d.account_number && d.account_number !== '-' ? ' - ' + d.account_number : '') + ')'
                     : (d.beneficiary || null)],
                 ['Reference', referensi],
                 ['Email', d.email],
               ])
             + '</div>';

        var tl = bangunLiniMasa(d);
        html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Tanggal</p>'
             + (tl.length ? drawerKV(tl) : kosongRingkas('Belum diajukan.'))
             + '</div>';

        /* Riwayat pengajuan PIC ini di PO ini — satu PIC bisa punya banyak
           beneficiary. Disusun pohon COA → Sub COA → item (lihat picHistory()
           di server); nilai & sumber baris sudah dihitung di sana (Cash
           Advance dari settlement, non-CA dari pengajuan). Beneficiary CR ini
           belum tentu punya PIC master (has_pic) — kalau tidak, judul panel
           ikut berganti jadi "Beneficiary". */
        var ph = d.pic_history || {};
        var phItems = ph.items || [];
        var phJudul = ph.has_pic ? 'Riwayat pengajuan PIC ini di PO ini' : 'Riwayat pengajuan beneficiary ini di PO ini';
        var phKosong = ph.has_pic ? 'PIC ini' : 'beneficiary ini';
        html += '<div class="ts-dw-section"><p class="ts-dw-section-title">' + esc(phJudul) +
                (ph.pic_name ? ' <span style="font-weight:400;text-transform:none;letter-spacing:0;">— ' + esc(ph.pic_name) + '</span>' : '') +
                '</p>';
        if (phItems.length) {
            window.__picHist = ph;
            html += '<div class="todo-pichist-wrap" id="todoPicHist"></div>';
        } else {
            window.__picHist = null;
            html += kosongRingkas('Belum ada pengajuan lain yang sudah dibayar untuk ' + phKosong + ' di PO ini.');
        }
        html += '</div>';

        $('#todoPaneInfo').html(html);
        renderPicHist();
    }

    /* ── TAB REQUEST (rincian item pengajuan + attachment) ── */
    function isiRequest(d) {
        var canModerate = !!d.can_moderate_items;
        window.__crDetailAtt = {};
        var details = d.details || [];
        setTabCount('#todoReqCount', details.length);
        var anyAtt = details.some(function (x) { return x.attachments && x.attachments.length; });
        var showAksi = canModerate || anyAtt;
        var html = '';

        if (details.length) {
            html += expandAllBtn('todoReqBody');
            html += '<div class="table-responsive"><table class="table table-bordered mb-0">' +
                '<thead style="background:var(--surface-2);"><tr>' +
                    '<th scope="col">Chart of Account / Description</th>' +
                    '<th scope="col" class="text-right text-nowrap" style="width:13%;">Price</th>' +
                    '<th scope="col" class="text-center text-nowrap" style="width:6%;">Qty</th>' +
                    '<th scope="col" class="text-right text-nowrap" style="width:14%;">Total</th>' +
                    (showAksi ? '<th scope="col" class="text-center text-nowrap" style="width:1%;">Aksi</th>' : '') +
                '</tr></thead><tbody id="todoReqBody">';
            var grand = 0;
            groupByAccount(details).forEach(function (grp, gi) {
                var gClass = 'td-req-grp-' + gi;
                // Item takeout tidak ikut dijumlah supaya subtotal grup dan total
                // bawah konsisten dengan nilai CR yang diproses.
                var gTotal = grp.items.reduce(function (t, x) { return t + (x.is_takeout ? 0 : (parseFloat(x.total) || 0)); }, 0);

                html += '<tr class="td-grp-row" data-target="' + gClass + '">' +
                    '<td style="font-weight:700;"><i class="fas fa-chevron-right mr-2 td-grp-caret"></i>' +
                        esc(grp.account) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + grp.items.length + '</span></td>' +
                    '<td></td><td></td>' +
                    '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(Math.round(gTotal).toLocaleString('id-ID')) + '</td>' +
                    (showAksi ? '<td></td>' : '') +
                '</tr>';

                grp.items.forEach(function (item) {
                    var isTakeout = !!item.is_takeout;
                    if (!isTakeout) grand += parseFloat(item.total) || 0;

                    var rowStyle = isTakeout
                        ? 'color:var(--text-faint);background:var(--surface-2);'
                        : (item.is_nob ? 'color:var(--danger);' : '');
                    var strike = isTakeout ? 'text-decoration:line-through;' : '';
                    var badge = isTakeout
                        ? ' <span class="badge badge-warning" style="font-size:9px;" title="' + escAttr(item.takeout_notes || '') + '">Takeout &middot; ' + esc(item.takeout_stage_label || '-') + '</span>'
                        : '';

                    var atts = item.attachments || [];
                    window.__crDetailAtt[item.id] = atts;
                    var evi = atts.length
                        ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + atts.length + '</span>'
                        : '';
                    // Dua baris: Sub COA di atas, catatan baris sebagai deskripsi kecil.
                    var subCoa = item.sub_coa || item.item_label || item.description || '-';
                    var noteLine = (item.sub_coa && item.notes)
                        ? '<div style="color:var(--text-faint);font-size:11px;margin-top:2px;">' + esc(item.notes) + '</div>'
                        : '';

                    var btnStyle = 'white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;';
                    var actInner = '';
                    if (atts.length) {
                        actInner += '<button type="button" class="btn btn-info btn-view-detail-att mr-1" style="' + btnStyle + '" data-detail="' + item.id + '" title="Lihat lampiran"><i class="fas fa-eye"></i></button>';
                    }
                    if (canModerate) {
                        actInner += isTakeout
                            ? '<button type="button" class="btn btn-outline-secondary td-restore-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + item.id + '" title="Kembalikan item"><i class="fas fa-undo"></i></button>'
                            : '<button type="button" class="btn btn-outline-danger td-takeout-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + item.id + '" data-desc="' + escAttr(item.description || '') + '" title="Takeout item"><i class="fas fa-cut"></i></button>';
                    }

                    html += '<tr class="' + gClass + '" style="display:none;' + rowStyle + '">' +
                        '<td class="td-grp-indent" style="' + strike + '">' +
                            '<div style="font-weight:600;">' + esc(subCoa) + badge + evi + '</div>' +
                            noteLine +
                            (isTakeout && item.takeout_notes ? '<small class="text-muted" style="text-decoration:none;display:block;margin-top:2px;">Alasan: ' + esc(item.takeout_notes) + '</small>' : '') +
                        '</td>' +
                        '<td class="text-right text-nowrap" style="' + strike + '">' + esc(item.amount_formatted) + '</td>' +
                        '<td class="text-center" style="' + strike + '">' + esc(item.qty) + '</td>' +
                        '<td class="text-right text-nowrap" style="' + strike + '"><strong>' + esc(item.total_formatted) + '</strong></td>' +
                        (showAksi ? '<td class="text-center text-nowrap">' + actInner + '</td>' : '') +
                    '</tr>';
                });
            });
            html += '</tbody><tfoot><tr style="background:var(--surface-2);">' +
                '<th colspan="3" class="text-right">Total (item aktif)</th>' +
                '<th class="text-right text-nowrap" style="color:var(--info);">' + esc(Math.round(grand).toLocaleString('id-ID')) + '</th>' +
                (showAksi ? '<th></th>' : '') +
                '</tr></tfoot></table></div>';
        } else {
            html += kosongRingkas('Belum ada rincian item.');
        }

        var atts = d.attachments || [];
        html += '<div class="mt-3"><h6 class="ts-dw-section-title"><i class="fas fa-paperclip mr-1"></i> Attachment' +
            (atts.length ? ' <span class="badge badge-secondary ml-1">' + atts.length + '</span>' : '') + '</h6>' +
            renderAttGrid(atts, 'req') + '</div>';
        $('#todoPaneRequest').html(html);
    }

    /* ── TAB SETTLEMENT — hanya untuk Cash Advance (jenis lain tidak punya
       tahap settlement, jadi tabnya disembunyikan, bukan ditampilkan kosong). */
    function isiSettlement(d) {
        var $tab = $('#todoTabRealisasiLi');
        var real = d.realizations || [];
        setTabCount('#todoRealCount', real.length);
        if (!d.is_cash_advance) {
            $tab.hide();
            if ($('#todoPaneRealisasi').hasClass('active')) $('#todoDetailTabs a[href="#todoPaneInfo"]').tab('show');
            $('#todoPaneRealisasi').empty();
            return;
        }
        $tab.show();
        window.__crRealAtt = {};

        if (!real.length) {
            $('#todoPaneRealisasi').html(
                '<div class="text-center text-muted py-4"><i class="fas fa-receipt d-block mb-2"></i> Belum ada data settlement.</div>');
            return;
        }

        var html = '';
        /* Total Request / Settlement / Var dari server (settlement_comparison):
           satu item bisa dipecah jadi beberapa baris settlement (Settlement
           Custom COA), jadi total_cr per baris tidak boleh dijumlah begitu saja. */
        var cmp = d.settlement_comparison || { rows: [] };

        // Request item asal hanya ditulis di baris PERTAMA item itu. Var-nya =
        // Request item - jumlah seluruh baris settlement item tersebut, jadi
        // ditulis di baris TERAKHIR item (setelah semua pecahan terlihat).
        var settlePerItem = {}, sudahTampil = {}, barisTerakhir = {}, jumlahBaris = {};
        real.forEach(function (r) {
            if (r.request_detail_id) {
                settlePerItem[r.request_detail_id] = (settlePerItem[r.request_detail_id] || 0) + (r.actual || 0);
                jumlahBaris[r.request_detail_id] = (jumlahBaris[r.request_detail_id] || 0) + 1;
                barisTerakhir[r.request_detail_id] = r;
            }
        });
        real.forEach(function (r) {
            r._reqShown = null;
            r._varShown = null;
            r._pecah = '';
            if (r.request_detail_id && !sudahTampil[r.request_detail_id]) {
                sudahTampil[r.request_detail_id] = true;
                r._reqShown = r.total_cr || 0;
                if (jumlahBaris[r.request_detail_id] > 1) r._pecah = 'utama';
            } else if (r.request_detail_id && jumlahBaris[r.request_detail_id] > 1) {
                r._pecah = 'pecahan';
            }
            if (r.request_detail_id && barisTerakhir[r.request_detail_id] === r) {
                r._varShown = (r.total_cr || 0) - (settlePerItem[r.request_detail_id] || 0);
            }
        });

        html += '<h6 class="ts-dw-section-title"><i class="fas fa-list mr-1"></i> Rincian Settlement</h6>';
        html += expandAllBtn('todoRealBody');
        html += '<div class="table-responsive"><table class="table table-bordered mb-0">' +
            '<thead style="background:var(--surface-2);"><tr>' +
                '<th scope="col">Chart of Account / Description</th>' +
                '<th scope="col" class="text-right text-nowrap" style="width:13%;">Request</th>' +
                '<th scope="col" class="text-right text-nowrap" style="width:13%;">Settlement</th>' +
                '<th scope="col" class="text-right text-nowrap" style="width:12%;">Var</th>' +
                '<th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>' +
            '</tr></thead><tbody id="todoRealBody">';

        var nomor = 0, rGrand = 0;
        groupByAccount(real).forEach(function (grp, gi) {
            var gClass = 'td-real-grp-' + gi;
            var gReq    = grp.items.reduce(function (t, x) { return t + (x._reqShown || 0); }, 0);
            var gSettle = grp.items.reduce(function (t, x) { return t + (x.actual || 0); }, 0);

            html += '<tr class="td-grp-row" data-target="' + gClass + '">' +
                '<td style="font-weight:700;"><i class="fas fa-chevron-right mr-2 td-grp-caret"></i>' +
                    esc(grp.account) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + grp.items.length + '</span></td>' +
                '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(gReq.toLocaleString('id-ID')) + '</td>' +
                '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(gSettle.toLocaleString('id-ID')) + '</td>' +
                '<td class="text-right text-nowrap" style="font-weight:700;">' + fmtVar(gReq - gSettle) + '</td>' +
                '<td></td>' +
            '</tr>';

            grp.items.forEach(function (r) {
                var ri = nomor++;
                rGrand += r.total || 0;
                var itemAtts = r.attachments || [];
                window.__crRealAtt[ri] = itemAtts;
                var aksi = itemAtts.length
                    ? '<button type="button" class="btn btn-info btn-view-real-att" style="white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;" data-row="' + ri + '" title="Lihat lampiran item (' + itemAtts.length + ')"><i class="fas fa-eye"></i></button>'
                    : '<span class="text-muted" style="font-size:11px;">-</span>';
                var attR = itemAtts.length
                    ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + itemAtts.length + '</span>'
                    : '';
                // Baris tambahan / pindah Sub COA (Settlement Custom COA).
                var asal = r.is_added
                    ? ' <span class="badge badge-info" style="font-size:9px;">Baris tambahan</span>'
                    : (r._pecah === 'utama'
                        ? ' <span class="badge badge-success" style="font-size:9px;">Sub Utama</span>'
                        : (r._pecah === 'pecahan'
                            ? ' <span class="badge badge-warning" style="font-size:9px;">Sub Pecahan</span>'
                            : '')) + (r.is_moved ? ' <span class="badge badge-warning" style="font-size:9px;">Pindah dari: ' + esc(r.origin_label || '-') + '</span>' : '');

                html += '<tr class="' + gClass + '" style="display:none;">' +
                    '<td class="td-grp-indent">' +
                        '<div style="font-weight:600;">' + esc(r.sub_coa || r.des_request || r.cr_item || '-') + attR + asal + '</div>' +
                        '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Request:</span> ' + esc(r.des_request || r.cr_item || '-') + '</div>' +
                        '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Settle:</span> ' + esc(r.des_settle || '-') + '</div>' +
                        '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Note:</span> ' + esc(r.change_note || '-') + '</div>' +
                    '</td>' +
                    '<td class="text-right text-nowrap">' + (r._reqShown !== null ? esc((r._reqShown || 0).toLocaleString('id-ID')) : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right text-nowrap"><strong>' + esc(r.actual_formatted) + '</strong></td>' +
                    '<td class="text-right text-nowrap">' + (r._varShown !== null ? fmtVar(r._varShown) : (r.is_added ? fmtVar(-(r.actual || 0)) : '<span class="text-muted">-</span>')) + '</td>' +
                    '<td class="text-center">' + aksi + '</td>' +
                '</tr>';
            });
        });
        html += '</tbody><tfoot><tr style="background:var(--surface-2);">' +
            '<th class="text-right">Total</th>' +
            '<th class="text-right text-nowrap">' + esc(cmp.request_formatted || '0') + '</th>' +
            '<th class="text-right text-nowrap" style="color:var(--success);">' + esc(rGrand.toLocaleString('id-ID')) + '</th>' +
            '<th class="text-right text-nowrap">' + fmtVar(cmp.variance) + '</th>' +
            '<th></th>' +
            '</tr></tfoot></table></div>';

        /* Perbandingan per COA / Sub COA — dipertahankan dari Todo List versi
           sebelumnya karena dipakai approver menilai selisih per Sub COA. */
        if ((cmp.rows || []).length) {
            html += '<div class="mt-3"><h6 class="ts-dw-section-title"><i class="fas fa-balance-scale mr-1"></i> Perbandingan per COA / Sub COA</h6>' +
                '<div class="table-responsive"><table class="table table-bordered mb-0">' +
                '<thead style="background:var(--surface-2);"><tr>' +
                    '<th scope="col">COA / Sub COA</th>' +
                    '<th scope="col" class="text-right text-nowrap" style="width:16%;">Request</th>' +
                    '<th scope="col" class="text-right text-nowrap" style="width:16%;">Settlement</th>' +
                    '<th scope="col" class="text-right text-nowrap" style="width:14%;">Var</th>' +
                '</tr></thead><tbody>';
            cmp.rows.forEach(function (c) {
                html += '<tr>' +
                    '<td><div style="font-weight:600;">' + esc(c.sub_coa || '-') + '</div>' +
                        '<div class="text-muted" style="font-size:11px;">' + esc(c.account || '-') + '</div></td>' +
                    '<td class="text-right text-nowrap">' + esc(c.request_formatted) + '</td>' +
                    '<td class="text-right text-nowrap"><strong>' + esc(c.settlement_formatted) + '</strong></td>' +
                    '<td class="text-right text-nowrap">' + fmtVar(c.variance) + '</td>' +
                '</tr>';
            });
            html += '</tbody><tfoot><tr style="background:var(--surface-2);">' +
                '<th class="text-right">Total</th>' +
                '<th class="text-right text-nowrap">' + esc(cmp.request_formatted || '0') + '</th>' +
                '<th class="text-right text-nowrap">' + esc(cmp.settlement_formatted || '0') + '</th>' +
                '<th class="text-right text-nowrap">' + fmtVar(cmp.variance) + '</th>' +
                '</tr></tfoot></table></div></div>';
        }

        html += '<div class="mt-3"><h6 class="ts-dw-section-title"><i class="fas fa-paperclip mr-1"></i> Settlement Attachment (Global)</h6>' +
            renderAttGrid(d.realization_attachments, 'real') + '</div>';

        // Second Payment — hanya tampil kalau Settlement melebihi pengajuan awal.
        var sp = d.second_payments || [];
        if (sp.length) {
            html += '<div class="mt-3"><h6 class="ts-dw-section-title"><i class="fas fa-hand-holding-usd mr-1"></i> Second Payment</h6>' +
                '<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;"><tbody>' +
                '<tr><td>Original Request</td><td class="text-right"><strong>' + esc(d.amount_formatted) + '</strong></td></tr>' +
                '<tr><td>Settlement</td><td class="text-right"><strong>' + esc(d.total_realization_formatted) + '</strong></td></tr>' +
                sp.map(function (p) {
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
                '</tbody></table></div>';
        }
        $('#todoPaneRealisasi').html(html);
    }

    /* ── TAB LOG — gaya chat, terlama di atas; kotak catatan selama CR belum
       Closed (server menolak juga). ── */
    function isiLog(d) {
        var logHtml = '';
        (d.logs || []).slice().reverse().forEach(function (log) { logHtml += renderLogRow(log); });
        $('#todoLogBody').html(logHtml || kosongRingkas('Belum ada riwayat persetujuan.'));
        var bisaCatat = d.can_post_log_note !== false;
        $('#todoLogForm').prop('hidden', !bisaCatat).data('id', d.id);
        $('#todoLogClosed').prop('hidden', bisaCatat);
        $('#todoLogInput').val('');
        if ($('#todoPaneLog').hasClass('active')) aturTinggiLog(true);
    }

    /* Carousel lampiran di kiri drawer (layar >= 1200px) — komponen bersama tesys-evidence-stage.js. */
    var stageEvidence = TesysEvidenceStage.attach('todoDrawer');

    function openCrDrawer(id, pemicu) {
        todoDrawerId = id;

        $('#todoDrawerContent').attr('hidden', true);
        $('#todoDrawerError').attr('hidden', true).empty();
        $('#todoDrawerLoading').show();
        $('#todoDrawer-eyebrow').text('');
        $('#todoDrawer-title').text('Memuat…');
        $('#todoDrwActions').empty();
        $('#todoDrawer-foot').hide();

        /* Dibuka dari dalam modal (mis. tombol view di View Budget) -> drawer
           harus menimpa modalnya. Penjaga fokus modal Bootstrap dilepas supaya
           kotak catatan di drawer tetap bisa diketik. */
        var dariModal = $('.modal.show').length > 0;
        $('body').toggleClass('todo-drawer-top', dariModal);
        if (dariModal) $(document).off('focusin.bs.modal');

        TesysDrawer.open('todoDrawer', pemicu);
        stageEvidence.clear();

        function tampilkanGagal(pesan) {
            $('#todoDrawerLoading').hide();
            $('#todoDrawer-title').text('Detail Cost Request');
            $('#todoDrawerError').html(
                '<div class="ts-empty">'
                + '<span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p>'
                + '<p class="ts-empty-msg">' + esc(pesan) + '</p>'
                + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral" id="todoDrawerRetry">'
                + '<i class="fas fa-redo"></i><span class="ts-al">Coba lagi</span></button></div></div>'
            ).removeAttr('hidden');
        }

        $.get('{{ url('/budget-request') }}/' + id + '/detail-json')
            .done(function (d) {
                /* Saat sesi habis, Laravel mengalihkan permintaan AJAX ke halaman
                   login (HTML berstatus 200) — tanpa penjagaan ini drawer merender
                   isi kosong seolah datanya memang tidak ada. */
                if (!d || typeof d !== 'object' || !d.code) {
                    tampilkanGagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.');
                    return;
                }
                if (String(id) !== String(todoDrawerId)) return; // drawer sudah dibuka untuk CR lain

                $('#todoDrawer-eyebrow').html(
                    '<button type="button" class="cr-copy-code" data-code="' + escAttr(d.code) + '" title="Klik untuk menyalin kode">'
                    + esc(d.code) + '</button>' + esc(d.type ? ' · ' + d.type : ''));
                $('#todoDrawer-title').text(d.description || d.code);

                isiInfo(d);
                isiRequest(d);
                isiSettlement(d);
                isiLog(d);
                renderDrawerActions(d);
                stageEvidence.render(TesysEvidenceStage.fromCostRequest(d));

                $('#todoDrawerContent').removeAttr('hidden');
                $('#todoDrawerLoading').hide();
                $('#todoDetailTabs a:first').tab('show');
            })
            .fail(function () {
                tampilkanGagal('Sambungan ke server terputus atau data sedang tidak tersedia.');
            });
    }

    $(document).on('click', '#todoDrawerRetry', function () {
        if (todoDrawerId) openCrDrawer(todoDrawerId);
    });

    /* ── AKSI WORKFLOW DARI DRAWER ──────────────────────────────────────────
       Daftar aksinya datang dari server (workflow_actions pada detail-json),
       bukan disusun ulang di sini: gerbang role & status hanya ditulis sekali,
       di tempat yang sama dengan method aksinya. Halaman ini cuma menggambar
       apa yang dikirim. */
    var todoDrwActions = {}; // key aksi -> definisi, diisi ulang tiap drawer dibuka
    // Checkbox baris tempat drawer dibuka (kosong kalau barisnya tidak punya
    // checkbox) — dipakai tombol "Pilih" untuk ikut multi-select.
    var $todoDrwCheck = $();

    function drawerSelectButton() {
        if (!$todoDrwCheck.length) return '';
        var on = $todoDrwCheck.is(':checked');
        return '<button type="button" class="btn btn-outline-secondary mr-auto todo-drw-select">' +
            '<i class="fas ' + (on ? 'fa-minus-square' : 'fa-check-square') + ' mr-1"></i>' +
            (on ? 'Batal Pilih' : 'Pilih') + '</button>';
    }

    /* ── RIWAYAT PENGAJUAN PIC (drawer tab Info) ──
       Disusun seperti View Budget: pohon COA → Sub COA → item. Tiap baris item
       memuat deskripsi, nilai, status CR, tanggal, dan tombol yang membuka CR
       asalnya. Data (termasuk sumber baris CA/non-CA & nilainya) sudah dihitung
       server di picHistory(); di sini hanya penyusunan pohon + collapse.
       Baris grup memakai kelas .td-grp-row yang sama dengan tab Request/
       Settlement, jadi toggle-nya ikut handler yang sudah ada. */
    function renderPicHist() {
        var ph = window.__picHist;
        var $wrap = $('#todoPicHist');
        if (!ph || !$wrap.length) return;
        var items = ph.items || [];

        var coaOrder = [], coaMap = {};
        items.forEach(function (it) {
            var coa = it.account || '-';
            if (!coaMap[coa]) { coaMap[coa] = { subOrder: [], sub: {}, total: 0 }; coaOrder.push(coa); }
            var g = coaMap[coa];
            g.total += parseFloat(it.value) || 0;
            var sc = it.sub_coa || '-';
            if (!g.sub[sc]) { g.sub[sc] = { rows: [], total: 0 }; g.subOrder.push(sc); }
            g.sub[sc].rows.push(it);
            g.sub[sc].total += parseFloat(it.value) || 0;
        });
        var grand = items.reduce(function (t, x) { return t + (parseFloat(x.value) || 0); }, 0);

        var html = '<table class="table table-sm table-bordered mb-0 todo-pichist-table" style="font-size:11.5px;">' +
            '<thead class="bg-light"><tr>' +
                '<th scope="col">COA / Sub COA / Item</th>' +
                '<th scope="col" class="text-right text-nowrap" style="width:110px;">Nilai</th>' +
                '<th scope="col" class="text-center text-nowrap" style="width:84px;">Status</th>' +
                '<th scope="col" class="text-nowrap" style="width:82px;">Tanggal</th>' +
                '<th scope="col" class="text-center" style="width:44px;">Aksi</th>' +
            '</tr></thead><tbody>';

        coaOrder.forEach(function (coa, ci) {
            var coaCls = 'pic-coa-' + ci;
            html += '<tr class="td-grp-row" data-target="' + coaCls + '">' +
                '<td style="font-weight:700;"><i class="fas fa-chevron-right mr-2 td-grp-caret"></i>' + esc(coa) + '</td>' +
                '<td class="text-right text-nowrap" style="font-weight:700;">' + Math.round(coaMap[coa].total).toLocaleString('id-ID') + '</td>' +
                '<td colspan="3"></td>' +
            '</tr>';
            coaMap[coa].subOrder.forEach(function (sc, si) {
                var subCls = coaCls + '-sub-' + si;
                var s = coaMap[coa].sub[sc];
                html += '<tr class="td-grp-row ' + coaCls + '" style="display:none;" data-target="' + subCls + '">' +
                    '<td style="padding-left:24px;font-weight:600;"><i class="fas fa-chevron-right mr-2 td-grp-caret"></i>' + esc(sc) +
                        ' <span class="badge badge-secondary" style="font-size:9px;">' + s.rows.length + '</span></td>' +
                    '<td class="text-right text-nowrap" style="font-weight:600;">' + Math.round(s.total).toLocaleString('id-ID') + '</td>' +
                    '<td colspan="3"></td>' +
                '</tr>';
                s.rows.forEach(function (r) {
                    html += '<tr class="' + subCls + '" style="display:none;">' +
                        '<td style="padding-left:44px;">' + esc(r.description || '-') + '</td>' +
                        '<td class="text-right text-nowrap">' + esc(r.value_formatted) + '</td>' +
                        '<td class="text-center text-nowrap"><span class="badge badge-' + esc(r.cr_status_color || 'secondary') + '" style="font-size:9px;">' + esc(r.cr_status_label || r.cr_status || '-') + '</span></td>' +
                        '<td class="text-nowrap">' + esc(r.cr_date) + '</td>' +
                        '<td class="text-center"><a href="{{ url('/budget-request') }}?open=' + encodeURIComponent(r.cr_id) + '"' +
                            ' target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" style="padding:1px 7px;font-size:10px;"' +
                            ' title="Lihat ' + escAttr(r.cr_code) + '" aria-label="Lihat ' + escAttr(r.cr_code) + '"><i class="fas fa-eye"></i></a></td>' +
                    '</tr>';
                });
            });
        });

        html += '</tbody><tfoot><tr style="background:var(--surface-2);">' +
            '<th class="text-right">Subtotal (' + items.length + ' item)</th>' +
            '<th class="text-right text-nowrap">' + Math.round(grand).toLocaleString('id-ID') + '</th>' +
            '<th colspan="3"></th>' +
        '</tr></tfoot></table>';

        $wrap.html(html);
    }

    function renderDrawerActions(d) {
        var list = d.workflow_actions || [];
        todoDrwActions = {};
        var html = drawerSelectButton();
        if (!list.length && !html) { $('#todoDrwActions').empty(); $('#todoDrawer-foot').hide(); return; }

        list.forEach(function (a) {
            todoDrwActions[a.key] = a;
            html += '<button type="button" class="btn btn-' + esc(a.variant) + ' todo-drw-action" data-key="' + escAttr(a.key) + '">' +
                '<i class="fas ' + esc(a.icon) + ' mr-1"></i>' + esc(a.label) + '</button>';
        });
        $('#todoDrwActions').html(html);
        $('#todoDrawer-foot').show();
    }

    $(document).on('click', '.todo-drw-action', function () {
        var a = todoDrwActions[$(this).data('key')];
        if (!a) return;

        $('#drwActionTitle').html('<i class="fas ' + esc(a.icon) + ' mr-2" style="color:var(--accent);"></i>' + esc(a.title));
        $('#drwActionText').text(a.confirm);
        $('#frmDrwAction').attr('action', a.url);

        var $notes = $('#drwActionNotes').val('').removeClass('is-invalid');
        // Reject wajib beralasan (server pun memvalidasinya); approve/check/
        // close hanya menawarkan catatan.
        $notes.prop('required', !!a.notes_required);
        $('#drwActionNotesLabel').html(a.notes_required
            ? 'Alasan <span class="text-danger">*</span>'
            : 'Catatan (opsional)');
        $notes.attr('placeholder', a.notes_required ? 'Tuliskan alasan...' : 'Catatan...');

        $('#drwActionSubmit')
            .attr('class', 'btn btn-' + a.variant)
            .html('<i class="fas ' + esc(a.icon) + ' mr-1"></i> ' + esc(a.label));

        $('#mdlDrwAction').modal('show');
    });

    $('#frmDrwAction').on('submit', function (e) {
        var $notes = $('#drwActionNotes');
        if ($notes.prop('required') && !$.trim($notes.val())) {
            e.preventDefault();
            $notes.addClass('is-invalid').focus();
        }
    });
    $('#drwActionNotes').on('input', function () { $(this).removeClass('is-invalid'); });

    // Modal yang dibuka belakangan kembali menang atas drawer.
    $(document).on('show.bs.modal', function () { $('body').removeClass('todo-drawer-top'); });

    $(document).on('click', '.todo-open-drawer', function () {
        $todoDrwCheck = $(this).closest('tr').find('.todo-check').first();
        openCrDrawer($(this).data('id'), this);
    });

    /* Klik di mana saja pada baris data membuka detailnya — pola yang sama
       dengan tabel Cost Request. Yang dipicu adalah pembuka utama baris itu
       (drawer CR, modal Budget Plan, atau tautan halaman detail modul lain),
       jadi tidak ada tujuan baru. Kontrol di dalam baris (tombol, tautan,
       centang, menu aksi) tetap bekerja sendiri, dan seleksi teks tidak
       dianggap klik. */
    $(document).on('click', '#todoApp tr.todo-row', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, .dropdown-menu').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        todoBukaDetailBaris(this, e.ctrlKey || e.metaKey);
    });
    // Baris bisa difokus dengan Tab dan dibuka dengan Enter — pengganti klik
    // kode, yang kini menyalin kodenya.
    function todoBukaDetailBaris(tr, tabBaru) {
        var $buka = $(tr).find('.todo-open-drawer, .todo-bp-detail, a.cr-number-btn').first();
        if (!$buka.length) return;
        if ($buka.is('a') && tabBaru) { window.open($buka.attr('href'), '_blank'); return; }
        todoBukaBaris = true;
        try { $buka[0].click(); } finally { todoBukaBaris = false; }
    }
    $('#todoApp tr.todo-row').attr('tabindex', 0);
    $(document).on('keydown', '#todoApp tr.todo-row', function (e) {
        if (e.key !== 'Enter' || e.target !== this) return;
        e.preventDefault();
        todoBukaDetailBaris(this, e.ctrlKey || e.metaKey);
    });

    // Pilih/batal pilih baris asal lalu tutup drawer, supaya user bisa lanjut
    // memilih baris lain dan memproses sekaligus lewat bar multi-select.
    $(document).on('click', '.todo-drw-select', function () {
        if (!$todoDrwCheck.length) return;
        $todoDrwCheck.prop('checked', !$todoDrwCheck.is(':checked')).trigger('change');
        TesysDrawer.close('todoDrawer');
    });

    /* ── TAKEOUT / RESTORE SUB-ITEM (dari drawer approval) ── */
    function todoSubmitPost(url, extra) {
        var $f = $('<form method="POST" action="' + url + '"></form>').append(
            '<input type="hidden" name="_token" value="' + $('meta[name="csrf-token"]').attr('content') + '">'
        );
        (extra || []).forEach(function (kv) {
            $f.append($('<input type="hidden">').attr('name', kv[0]).val(kv[1]));
        });
        $('body').append($f); $f.submit();
    }

    $(document).on('click', '.td-takeout-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail'), desc = $(this).data('desc') || 'item ini';
        Swal.fire({
            title: 'Takeout item?',
            html: 'Item "<strong>' + esc(desc) + '</strong>" tidak akan dibawa proses. Jika semua item di-takeout, CR menjadi <strong>REJECTED</strong>.',
            input: 'textarea', inputLabel: 'Alasan takeout (wajib)', inputPlaceholder: 'Tulis alasan...',
            inputValidator: function (v) { return !v && 'Alasan wajib diisi.'; },
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Takeout', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) todoSubmitPost('/budget-request/' + crId + '/detail/' + detailId + '/takeout', [['notes', r.value]]);
        });
    });

    /* ── COST REGISTER — APPROVE / KEMBALIKAN KE DRAFT (endpoint halaman Cost Register) ── */
    $(document).on('click', '.td-reg-approve', function () {
        var id = $(this).data('id'), label = $(this).data('label') || '';
        Swal.fire({
            title: 'Approve Cost Register?',
            html: '<strong>' + esc(label) + '</strong><br>Setelah disetujui, Treasury bisa memasukkannya ke batch pembayaran.',
            input: 'textarea', inputLabel: 'Catatan (opsional)', inputAttributes: { maxlength: 500 },
            icon: 'question', showCancelButton: true, confirmButtonColor: 'var(--accent)', cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Ya, Approve', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) todoSubmitPost('{{ url('/cost-register') }}/' + id + '/approve', [['notes', r.value || '']]);
        });
    });

    $(document).on('click', '.td-reg-revise', function () {
        var id = $(this).data('id'), label = $(this).data('label') || '';
        Swal.fire({
            title: 'Kembalikan ke Draft?',
            html: '<strong>' + esc(label) + '</strong><br>Finance merevisi lalu mengajukan ulang.',
            input: 'textarea', inputLabel: 'Alasan (wajib)', inputPlaceholder: 'Tulis alasan...', inputAttributes: { maxlength: 500 },
            inputValidator: function (v) { return !(v || '').trim() && 'Alasan wajib diisi.'; },
            icon: 'warning', showCancelButton: true, confirmButtonColor: 'var(--danger)', cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Kembalikan', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) todoSubmitPost('{{ url('/cost-register') }}/' + id + '/revise', [['notes', r.value]]);
        });
    });

    $(document).on('click', '.td-restore-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail');
        Swal.fire({
            title: 'Kembalikan item?', text: 'Item akan ikut diproses lagi dan nilai CR bertambah.',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#42627d', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Kembalikan', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) todoSubmitPost('/budget-request/' + crId + '/detail/' + detailId + '/restore');
        });
    });

    /* ── PENAMPIL LAMPIRAN — pemicu klik kartu/tombol lampiran (komponen bersama) ── */
    (function () {
        // Penampil lampiran = komponen bersama (public/js/tesys-evidence-stage.js).
        function openViewer(daftar, mulai) {
            TesysEvidenceStage.open((daftar || []).map(function (a) {
                return { url: a.full, name: a.name, is_image: a.is_image, is_pdf: a.is_pdf };
            }), mulai);
        }

        // Klik kartu lampiran (Request, Settlement): seluruh lampiran satu grup
        // masuk carousel; gambar jadi slide, file lain kartu logo + nama.
        $(document).on('click', '.cr-att-card', function () {
            var daftar = (window.__crAttGroups && window.__crAttGroups[$(this).data('group')]) || [];
            openViewer(daftar.map(function (a) {
                return { full: '/storage/' + a.path, name: a.original_name, is_image: !!a.is_image, is_pdf: !!a.is_pdf };
            }), parseInt($(this).data('idx'), 10) || 0);
        });

        /* Semua lampiran satu baris masuk ke carousel dengan urutan seperti saat
           diunggah. Sebelumnya file non-gambar disaring keluar, sehingga PDF
           tidak pernah muncul di baris yang juga punya gambar. */
        function bukaLampiran(atts) {
            atts = atts || [];
            if (!atts.length) return;

            openViewer(atts.map(function (a) {
                return {
                    full:     '/storage/' + a.path,
                    name:     a.original_name,
                    is_image: !!a.is_image,
                    is_pdf:   !!a.is_pdf
                };
            }), 0);
        }

        // Tombol View lampiran per item (kolom Aksi tab Request)
        $(document).on('click', '.btn-view-detail-att', function () {
            var id = $(this).data('detail');
            bukaLampiran((window.__crDetailAtt && window.__crDetailAtt[id]) || []);
        });

        // Tombol View lampiran per item (kolom Aksi tab Settlement)
        $(document).on('click', '.btn-view-real-att', function () {
            bukaLampiran((window.__crRealAtt && window.__crRealAtt[$(this).data('row')]) || []);
        });

        /* Buka/tutup grup Chart of Account di tab Request & Settlement. */
        $(document).on('click', '.td-grp-row', function () {
            var $sub = $(this).closest('tbody').find('.' + $(this).data('target'));
            var expanding = $sub.first().is(':hidden');
            $sub.toggle(expanding);
            $(this).find('.td-grp-caret').toggleClass('rotated', expanding);
        });
        /* Satu tombol Expand/Collapse All: label & ikonnya berganti mengikuti
           keadaan (sama dengan drawer Cost Request). */
        $(document).on('click', '.td-grp-toggle-all', function () {
            var $btn   = $(this);
            var expand = !$btn.data('expanded');
            var $body  = $('#' + $btn.data('body'));
            $body.find('.td-grp-row').each(function () {
                $body.find('.' + $(this).data('target')).toggle(expand);
                $(this).find('.td-grp-caret').toggleClass('rotated', expand);
            });
            $btn.data('expanded', expand ? 1 : 0)
                .html(expand
                    ? '<i class="fas fa-angle-double-up mr-1"></i>Collapse All'
                    : '<i class="fas fa-angle-double-down mr-1"></i>Expand All');
        });

    })();

});
</script>
@endpush
