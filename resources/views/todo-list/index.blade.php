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

<div class="content" id="todoApp">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Todo List Request</h1>
                <div style="color:var(--text-faint);font-size:13px;margin-top:2px;">Task Cost Request, Budgeting, Treasury, Invoice, dan reminder Project Monitoring yang menunggu tindakan Anda</div>
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
        <div class="row">
            <div class="col-6 col-md-4 mb-3">
                <div class="card shadow-sm mb-0 h-100" style="border-radius:var(--radius-lg);border-left:4px solid var(--accent);">
                    <div class="card-body py-3 px-3">
                        <div style="color:var(--text-faint);font-size:11px;text-transform:uppercase;font-weight:700;">My Pending Tasks</div>
                        <div style="font-size:26px;font-weight:700;color:var(--text);margin-top:2px;">{{ $totalPending }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 mb-3">
                <div class="card shadow-sm mb-0 h-100" style="border-radius:var(--radius-lg);border-left:4px solid var(--danger);">
                    <div class="card-body py-3 px-3">
                        <div style="color:var(--text-faint);font-size:11px;text-transform:uppercase;font-weight:700;">Max Aging</div>
                        <div style="font-size:26px;font-weight:700;color:var(--danger);margin-top:2px;">{{ $maxAging }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <div class="card shadow-sm mb-0 h-100" style="border-radius:var(--radius-lg);border-left:4px solid var(--success);">
                    <div class="card-body py-3 px-3">
                        <div style="color:var(--text-faint);font-size:11px;text-transform:uppercase;font-weight:700;">Completed Today</div>
                        <div style="font-size:26px;font-weight:700;color:var(--success);margin-top:2px;">{{ $completedToday }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="card shadow-sm mb-0" style="border-radius:var(--radius-lg);">
            <div class="card-body py-3 px-3">
                <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
                    <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
                        <div style="position:relative;width:190px;">
                            <i class="fas fa-search" style="position:absolute;left:10px;top:9px;color:var(--text-faint);font-size:12px;"></i>
                            <input type="text" id="todoSearch" class="form-control form-control-sm" placeholder="Cari CR..." style="padding-left:28px;">
                        </div>
                        <div id="todoRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                        <button aria-label="Clear Filter" type="button" id="btnTodoClearFilter" class="btn btn-outline-secondary btn-sm" title="Clear Filter" style="display:none;">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                    <div class="d-flex flex-wrap align-items-center ml-auto" style="gap:8px;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#mdlSummaryTask">
                            <i class="fas fa-chart-pie mr-1"></i> Summary Task
                        </button>
                        <a href="{{ url('/budget-request') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Daftar Cost Request
                        </a>
                        <button aria-label="Refresh" type="button" class="btn btn-outline-secondary btn-sm" id="btnTodoRefresh" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="{{ route('todo-list.export') }}" id="btnExportTodo" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if(!$hasAnySection)
        <div class="text-center card shadow-sm mt-3" style="border-radius:var(--radius-lg);padding:60px 20px;">
            <div style="font-size:48px;color:var(--text-faint);"><i class="fas fa-tasks"></i></div>
            <div style="font-size:16px;font-weight:700;color:var(--text);margin-top:12px;">Belum ada task untuk role Anda</div>
            <div style="font-size:13px;color:var(--text-faint);margin-top:4px;">Role Anda saat ini tidak memiliki tahapan approval di modul manapun.</div>
        </div>
        @else

            @if($totalPending === 0 && $milestoneOverdue->isEmpty() && empty($comingSoonRoles))
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Alasan Ditolak</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Batch Treasury</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($managerRejected as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? ''));
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                @endphp
                                <tr class="todo-row" {{ $poAttrs($cr->budgetPlan?->project, $cr->budgetPlan?->costCenter?->name, $cr->amount) }} data-pm="{{ strtoupper($cr->pm_internal_name) }}" data-po="{{ $cr->po_label }}" data-search="{{ $crSearch }}">
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($managerPaid as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? ''));
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                @endphp
                                <tr class="todo-row" {{ $poAttrs($cr->budgetPlan?->project, $cr->budgetPlan?->costCenter?->name, $cr->amount) }} data-pm="{{ strtoupper($cr->pm_internal_name) }}" data-po="{{ $cr->po_label }}" data-search="{{ $crSearch }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 150) }}</td>
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Refund</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
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
                                <tr class="{{ $indukBarisCr }} todo-row" style="display:none;"
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col">Code</th>
                                    <th scope="col">Deskripsi</th>
                                    <th scope="col" class="text-nowrap">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap">Request</th>
                                    <th scope="col" class="text-right text-nowrap">Settlement</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap">Expense Claim</th>
                                    <th scope="col" class="text-center text-nowrap">Aging</th>
                                    <th scope="col" class="text-center text-nowrap">Aksi</th>
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
                                <tr class="todo-row" data-po-key="{{ $poGroup['po'] }}" data-po-label="{{ $poGroup['po'] }}" data-po-sub="{{ $poGroup['customer'] . (($poGroup['pm'] ?? '-') !== '-' ? ' - ' . $poGroup['pm'] : '') }}" data-po-amount="{{ $cr->expense_claim }}"
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Refund</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Expense Claim</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
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
                                <tr class="{{ $indukBarisCr }} todo-row" style="display:none;"
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:1%;">Second Approval</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($secondPaymentGroups as $poi => $poGroup)
                                @foreach($poGroup['plans'] as $pi => $group)
                                @foreach($group['items'] as $cr)
                                @php $gi = $poi . '-' . $pi; @endphp
                                <tr class="todo-row" {{ $poAttrs($cr->budgetPlan?->project, $cr->budgetPlan?->costCenter?->name, $cr->pending_payment?->amount ?? 0) }} data-search="{{ strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? '')) }}">
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Code</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stage['data'] as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
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

                                            <button aria-label="Detail" type="button" class="ts-action-primary is-neutral todo-bp-detail" data-id="{{ $bp->id }}" title="Detail Budget">
                                                <i class="fas fa-info-circle" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                            </button>
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

            {{-- BUDGETING — JATUH TEMPO PLAN FINISH, MANAGER AJUKAN CLOSING --}}
            @if($budgetingClosingDue->isNotEmpty())
            <div id="tl-bp-closing-due" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-flag-checkered mr-1"></i> Budgeting — Ajukan Closing (Plan Finish terlewati)
                        <span class="badge badge-secondary ml-1">{{ $budgetingClosingDue->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Code</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Plan Finish</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetingClosingDue as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td>{{ $bpPoLabel($bp) }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $bp->amount_formatted }}</strong></td>
                                    <td class="text-center text-nowrap text-danger">{{ $bp->plan_finish?->format('d-m-Y') ?? '-' }}</td>
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
                                            <button aria-label="Detail" type="button" class="ts-action-primary is-neutral todo-bp-detail" data-id="{{ $bp->id }}" title="Detail Budget">
                                                <i class="fas fa-info-circle" aria-hidden="true"></i><span class="ts-al">Detail</span>
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">Code</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">PO</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
                                    <th scope="col">Alasan Ditolak</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetingRejected as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bpPoLabel($bp)) }}">
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
                                            <button aria-label="Detail" type="button" class="ts-action-primary is-neutral todo-bp-detail" data-id="{{ $bp->id }}" title="Detail Budget">
                                                <i class="fas fa-info-circle" aria-hidden="true"></i><span class="ts-al">Detail</span>
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
                                    <th scope="col" style="width:1%;">
                                        <input type="checkbox" id="tlBatchCheckAll" title="Pilih semua">
                                    </th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Dikelompokkan per PURCHASE ORDER, sama seperti worklist
                                     approval (lihat _worklist_section.blade.php), supaya satu
                                     batch bisa disusun per PO tanpa memungut CR satu-satu dari
                                     daftar panjang.

                                     Berbeda dari worklist, baris CR di sini dirender TERBUKA:
                                     tlBatchRefresh() hanya menghitung baris yang :visible, jadi
                                     kalau grupnya tertutup, "pilih semua" dan hitungan nominal
                                     akan berbohong soal apa yang benar-benar ikut terbatch. --}}
                                @foreach($treasuryBatchGroups as $poi => $poGroup)
                                <tr class="todo-po-row" data-target="tlbatch-po-{{ $poi }}">
                                    <td>
                                        <input type="checkbox" class="tl-batch-check-group"
                                               data-group="tlbatch-po-{{ $poi }}" title="Pilih semua CR pada PO ini">
                                    </td>
                                    <td colspan="3">
                                        <i class="fas fa-chevron-right todo-caret rotated mr-2"></i>
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
                                <tr class="todo-row tlbatch-po-{{ $poi }}" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
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
                                        <i class="fas fa-chevron-right todo-caret rotated mr-2"></i>
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
                                <tr class="todo-row tlbatch-second" data-po="{{ $sp->po_label }}" data-search="{{ $spSearch }}">
                                    <td>
                                        <input type="checkbox" class="tl-batch-check" data-kind="second"
                                               value="{{ $sp->id }}" data-amount="{{ $sp->amount }}">
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $sp->budget_request_id }}">{{ $sp->cr_code }}</button>
                                        <span class="badge badge-warning ml-1" style="font-size:9px;">2nd</span>
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
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
                                <tr class="todo-row" data-search="{{ strtolower($batch->code . ' ' . $batch->description) }}">
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
                                <tr class="todo-row" data-search="{{ strtolower($batch->code . ' ' . $batch->description) }}">
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">No. PO</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:1%;">Sisa Ditagih</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($collectionWaitingInvoice as $poNumber)
                                @php $poAgingClass = $poNumber->aging_days >= 3 ? 'text-danger' : ($poNumber->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" {{ $poAttrs($poNumber->project, null, $poNumber->remainingValue()) }} data-search="{{ strtolower($poNumber->po_number . ' ' . ($poNumber->project?->name ?? '') . ' ' . ($poNumber->project?->customer?->name ?? '')) }}">
                                    <td class="text-nowrap">{{ $poNumber->po_number }} <span class="badge badge-{{ $poNumber->status_color }}" style="font-size:9px;">{{ $poNumber->status_label }}</span></td>
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">PO</th>
                                    <th scope="col">Scope of Work / Customer</th>
                                    <th scope="col" class="text-nowrap">Manager</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Nilai PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">No. PO</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($poSubmitted as $po)
                                @php $poAgingClass = $po->aging_days >= 3 ? 'text-danger' : ($po->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($po->code . ' ' . $po->name . ' ' . ($po->customer?->name ?? '') . ' ' . ($po->manager?->name ?? '')) }}">
                                    <td class="text-nowrap">
                                        <a href="{{ route('project.index') }}?open={{ $po->id }}" class="cr-number-btn">{{ $po->code }}</a>
                                    </td>
                                    <td>{{ $po->name }} <span class="text-muted">({{ $po->customer?->name ?? '-' }})</span></td>
                                    {{-- Username, bukan nama lengkap: kolom ini cuma penanda
                                         "punya siapa", dan nama lengkap membuat barisnya melebar
                                         tanpa menambah informasi. --}}
                                    <td class="text-nowrap">{{ $po->manager?->username ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $po->value_formatted }}</strong></td>
                                    {{-- financeApprove() menolak "Active" selama No. PO masih kosong, jadi
                                         jumlahnya ditampilkan di sini: Finance tahu sebelum membuka apakah
                                         PO ini bisa langsung Active atau baru bisa Waiting PO. --}}
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
                                            <a href="{{ route('project.index') }}?open={{ $po->id }}"
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Invoice</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceVerify as $inv)
                                @php $invAgingClass = $inv->aging_days >= 3 ? 'text-danger' : ($inv->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" {{ $poAttrs($inv->po, null, $inv->billed_amount) }} data-search="{{ strtolower($inv->code . ' ' . ($inv->po?->name ?? '') . ' ' . ($inv->po?->customer?->name ?? '')) }}">
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
                                    <th scope="col" style="width:1%;"><input type="checkbox" class="todo-check-all" aria-label="Pilih semua"></th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Invoice</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" data-po-amount class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceApprove as $inv)
                                @php $invAgingClass = $inv->aging_days >= 3 ? 'text-danger' : ($inv->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" {{ $poAttrs($inv->po, null, $inv->billed_amount) }} data-search="{{ strtolower($inv->code . ' ' . ($inv->po?->name ?? '') . ' ' . ($inv->po?->customer?->name ?? '')) }}">
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

            {{-- PROJECT MONITORING — MILESTONE TERTUNDA (reminder, bukan approval — lihat catatan di TodoListController::index()) --}}
            @if($milestoneOverdue->isNotEmpty())
            <div id="tl-milestone" class="card card-secondary card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-bell mr-1"></i> Project Monitoring — Milestone Tertunda
                        <span class="badge badge-secondary ml-1">{{ $milestoneOverdue->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table todo-po-group table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">PO</th>
                                    <th scope="col">Milestone</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Target Selesai</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Terlambat</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($milestoneOverdue as $detail)
                                <tr class="todo-row" {{ $poAttrs($detail->project) }} data-search="{{ strtolower(($detail->project?->name ?? '') . ' ' . ($detail->stage?->name ?? '')) }}">
                                    <td class="text-nowrap">{{ $detail->project?->name ?? '-' }} <span class="text-muted">({{ $detail->project?->customer?->name ?? '-' }})</span></td>
                                    <td>{{ $detail->stage?->name ?? '-' }} <span class="badge badge-secondary" style="font-size:9px;">{{ str_replace('_',' ', $detail->status) }}</span></td>
                                    <td class="text-center text-nowrap">{{ $detail->plan_end?->format('d M Y') ?? '-' }}</td>
                                    <td class="text-center text-nowrap text-danger"><strong>{{ $detail->overdue_label }}</strong></td>
                                    <td class="text-center text-nowrap">
                                        <div class="action-buttons ts-actions">
                                            <a href="{{ route('project-monitoring.show', $detail->project_id) }}"
                                               class="ts-action-primary is-neutral" title="Update">
                                                <i class="fas fa-binoculars" aria-hidden="true"></i><span class="ts-al">Pantau</span>
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
                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
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
                @include('todo-list._summary_task_list')
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

{{-- ============================== DETAIL DRAWER ============================== --}}
<div id="todoDrawerOverlay" class="todo-drawer-overlay"></div>
<div id="todoDrawer" class="todo-drawer">
    <div class="todo-drawer-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div id="todoDrwCode" class="text-white" style="font-size:15px;font-weight:700;">CR-0000</div>
                <div id="todoDrwStatusWrap" style="margin-top:6px;"></div>
            </div>
            <button type="button" id="todoDrwClose" class="close text-white"><span>&times;</span></button>
        </div>
    </div>
    <div class="todo-drawer-body" style="padding:0;display:flex;flex-direction:column;">
        <ul class="nav nav-tabs" style="border-bottom:2px solid var(--border);padding:0 22px;flex-shrink:0;">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#todoPaneInfo" style="font-size:12px;font-weight:600;padding:10px 12px;"><i class="fas fa-info-circle mr-1"></i>Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#todoPaneRequest" style="font-size:12px;font-weight:600;padding:10px 12px;"><i class="fas fa-list-ul mr-1"></i>Request</a></li>
            <li class="nav-item" id="todoTabRealisasiLi" style="display:none;"><a class="nav-link" data-toggle="tab" href="#todoPaneRealisasi" style="font-size:12px;font-weight:600;padding:10px 12px;"><i class="fas fa-receipt mr-1"></i>Settlement</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#todoPaneLog" style="font-size:12px;font-weight:600;padding:10px 12px;"><i class="fas fa-history mr-1"></i>Log</a></li>
        </ul>
        <div class="tab-content" style="padding:20px 22px;overflow-y:auto;flex:1;">
            <div class="tab-pane fade show active" id="todoPaneInfo"></div>
            <div class="tab-pane fade" id="todoPaneRequest"></div>
            <div class="tab-pane fade" id="todoPaneRealisasi"></div>
            <div class="tab-pane fade" id="todoPaneLog"></div>
        </div>
        {{-- Bar aksi workflow — isinya dibangun dari workflow_actions milik
             detail-json, jadi tombol yang tampil persis sama dengan yang
             diizinkan server untuk user & status ini. Disembunyikan saat CR
             tidak punya aksi apa pun bagi user ini. --}}
        <div id="todoDrwActions" class="todo-drawer-actions" style="display:none;"></div>
    </div>
</div>

{{-- IMAGE VIEWER (carousel + zoom, ala WhatsApp) — sama seperti di budget-request/index --}}
<div id="imgViewerOverlay">
    <button aria-label="Close" type="button" id="imgViewerClose" title="Close"><i class="fas fa-times"></i></button>
    <div id="imgViewerName"></div>
    <div id="imgViewerStage">
        <button aria-label="Previous" type="button" class="imgViewerNav" id="imgViewerPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
        <img id="imgViewerImg" src="" alt="">
        <button aria-label="Next" type="button" class="imgViewerNav" id="imgViewerNext" title="Next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div id="imgViewerCounter"></div>
</div>

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
#todoApp { overflow-x: hidden; }

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

/* Detail drawer */
.todo-drawer-overlay { display:none;position:fixed;inset:0;background:rgba(27,27,34,0.45);z-index:1050; }
.todo-drawer {
    display:none;position:fixed;top:0;right:0;bottom:0;width:50vw;max-width:92vw;min-width:440px;background:var(--surface);
    z-index:1051;box-shadow:-8px 0 24px rgba(0,0,0,0.15);flex-direction:column;
}
.todo-drawer-header { background:var(--charcoal);padding:18px 22px;border-bottom:3px solid var(--accent);flex-shrink:0; }
.todo-drawer-body { padding:20px 22px;overflow-y:auto;flex:1; }
.todo-drawer-body .nav-tabs .nav-link { color:var(--text-faint);border:none;border-bottom:2px solid transparent; }
.todo-drawer-body .nav-tabs .nav-link.active { color:var(--text);border-bottom-color:var(--accent);background:transparent; }
/* Bar aksi drawer — menempel di dasar panel, tidak ikut tergulir bersama isi
   tab, supaya tombol Approve/Reject tetap terjangkau pada CR berisi banyak
   rincian. */
/* Riwayat pengajuan beneficiary — subtotal + header dibekukan */
.todo-benhist-wrap { max-height:300px; overflow:auto; border:1px solid var(--border); border-radius:var(--radius-md); }
.todo-benhist-table { border-collapse:separate; border-spacing:0; width:100%; }
.todo-benhist-table th, .todo-benhist-table td { border-bottom:1px solid var(--border); padding:6px 10px; vertical-align:middle; }
.todo-benhist-table thead th { position:sticky; z-index:2; }
/* Semua kolom selebar isinya; Description mengambil sisa lebar. */
.todo-benhist-table th, .todo-benhist-table td { width:1%; white-space:nowrap; }
.todo-benhist-table .todo-benhist-desc { width:auto; white-space:normal; }
.todo-benhist-sub th { top:0; background:var(--surface-3); font-weight:700; color:var(--text); }
.todo-benhist-head th { top:30px; background:var(--surface-2); font-size:10.5px; letter-spacing:.04em; text-transform:uppercase; color:var(--text-faint); cursor:pointer; user-select:none; white-space:nowrap; }
.todo-benhist-head th:hover { color:var(--text); }
.todo-drawer-actions {
    flex-shrink:0;padding:12px 22px;background:var(--surface-2);border-top:1px solid var(--border);
    display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;
}
.todo-drawer-actions .btn { font-size:12px;font-weight:600;padding:6px 16px; }
/* Drawer duduk di z-index 1051 — di ATAS modal Bootstrap bawaan (1050).
   Modal konfirmasi yang dipanggil DARI drawer karena itu perlu dinaikkan,
   berikut backdrop-nya, kalau tidak ia muncul di belakang panel. */
#mdlDrwAction { z-index:1080; }
body.todo-drawer-open .modal-backdrop { z-index:1070; }
.todo-drw-row { margin-bottom:14px; }
/* Chip Chart of Account pada tab Info drawer — bentuknya mengikuti badge
   status yang sudah dipakai di tab Log: pil lembut, bukan kotak tabel. */
.todo-coa-chips { display:flex;flex-wrap:wrap;gap:6px;margin-top:2px; }
.todo-coa-chip {
    display:inline-flex;align-items:baseline;gap:6px;
    background:rgba(200,39,46,0.10);color:var(--accent);
    border:1px solid rgba(200,39,46,0.22);border-radius:999px;
    padding:3px 10px;font-size:11.5px;font-weight:700;line-height:1.3;
}
.todo-drw-label { font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-faint);font-weight:700;margin-bottom:3px; }

/* Grup per Chart of Account di tab Request & Settlement drawer (pola sama
   dengan tab Budget di Budget Plan). */
tr.td-grp-row { cursor:pointer; }
tr.td-grp-row, tr.td-grp-row td { background:var(--info-bg) !important; }
tr.td-grp-row:hover, tr.td-grp-row:hover td { background:var(--surface-3) !important; }
.td-grp-caret { font-size:10px; color:var(--text-faint); transition:transform .15s ease; display:inline-block; }
.td-grp-caret.rotated { transform:rotate(90deg); }
td.td-grp-indent { padding-left:28px !important; }
.todo-drw-value { font-size:13px;color:var(--text); }
.todo-timeline { list-style:none;margin:0;padding:0; }
.todo-timeline li { position:relative;padding-left:24px;padding-bottom:16px;font-size:12px;color:var(--text-faint); }
.todo-timeline li:last-child { padding-bottom:0; }
.todo-timeline li::before { content:'';position:absolute;left:4px;top:3px;width:9px;height:9px;border-radius:50%;background:var(--surface-3); }
.todo-timeline li::after { content:'';position:absolute;left:8px;top:14px;bottom:0;width:1px;background:var(--surface-3); }
.todo-timeline li:last-child::after { display:none; }
.todo-timeline li.done { color:var(--success); }
.todo-timeline li.done::before { background:var(--success); }
.todo-timeline li.current { color:var(--text);font-weight:700; }
.todo-timeline li.current::before { background:var(--accent);box-shadow:0 0 0 3px rgba(200,39,46,0.25); }

/* Grid lampiran (Supporting Documents) — sama seperti tab Request/Realization di Cost Request */
.cr-att-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:10px; margin-top:8px; }
.cr-att-card { border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; background:var(--surface); }
.cr-att-thumb { width:100%; height:90px; object-fit:cover; display:block; cursor:pointer; background:var(--surface-2); }
.cr-att-icon { width:100%; height:90px; display:flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-faint); font-size:26px; }
.cr-att-name { font-size:10.5px; padding:5px 6px; color:var(--text); word-break:break-word; border-top:1px solid var(--border); }

/* Image viewer (carousel + zoom, ala WhatsApp) — sama seperti di budget-request/index */
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
        var pilih = $(this).is(':checked');
        $('.' + $(this).data('group')).find('.tl-batch-check')
            .filter(function () { return $(this).closest('tr').is(':visible'); })
            .prop('checked', pilih);
        tlBatchRefresh();
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

            // Total baris = jumlah sel modul yang sedang TERLIHAT saja.
            var subtotal = 0;
            $selModul.filter('[data-count]').each(function () {
                var n = parseInt($(this).attr('data-count'), 10) || 0;
                subtotal += n;
                var m = $(this).data('module');
                totalPerModul[m] = (totalPerModul[m] || 0) + n;
            });

            grand += subtotal;
            $tr.find('[data-total-cell]')
                .text(subtotal)
                .toggleClass('summary-total-pos', subtotal > 0)
                .toggleClass('summary-total-zero', subtotal <= 0);
        });

        $tbl.find('[data-foot-module]').each(function () {
            $(this).text(totalPerModul[$(this).data('module')] || 0);
        });
        $tbl.find('[data-foot-grand]').text(grand);

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
        var $section = $btn.closest('.todo-worklist');
        var expanding = $btn.data('state') !== 'expanded';

        // Dua lapis (PO -> Budget Plan), jadi keduanya ikut dibuka/ditutup —
        // membuka lapis Budget Plan saja tidak akan terlihat selama induk PO-nya
        // masih tertutup.
        $section.find('.todo-po-row, .todo-group-row').each(function () {
            var target = $(this).data('target');
            $('.' + target).toggle(expanding);
            $(this).find('.todo-caret').toggleClass('rotated', expanding);
        });
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
        $section.find('.todo-check').prop('checked', $(this).is(':checked'));
        updateBulkBar($section);
    });

    /* ── WORKLIST — checkbox di header grup: pilih semua CR dalam grup itu ── */
    $(document).on('click', '.todo-check-group', function (e) {
        e.stopPropagation();
    });
    $(document).on('change', '.todo-check-group', function () {
        var $section = $(this).closest('.todo-worklist');
        groupLeaves($(this).data('group')).prop('checked', $(this).is(':checked'));
        updateBulkBar($section);
    });

    $(document).on('click', '.todo-bulk-approve', function () {
        var $section = $(this).closest('.todo-worklist');
        var key = $section.data('section');
        var ids = $section.find('.todo-check:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length) return;
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
        if (!ids.length) return;
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

        var offset = $btn.offset();
        var ddWidth = $dd.outerWidth();
        var viewportRight = $(window).scrollLeft() + $(window).width();
        var left = offset.left;
        if (left + ddWidth > viewportRight - 10) left = offset.left + $btn.outerWidth() - ddWidth;
        $dd.css({ top: offset.top + $btn.outerHeight() + 4, left: Math.max(10, left) });

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

    function applyTodoFilters() {
        var q = ($('#todoSearch').val() || '').toLowerCase().trim();
        var activeFields = Object.keys(todoFilterState).filter(function (k) { return todoFilterState[k].size > 0; });
        var filtering = !!(q || activeFields.length);

        $('#btnTodoClearFilter').toggle(filtering);

        if (!filtering) {
            $('.todo-row').removeClass('todo-filtered-out');
            /* Filter dilepas. Kartu section dan lapis PO dikembalikan tampil;
               lapis Budget Plan & baris CR di dalam section GROUPED dikembalikan
               tertutup.

               Kenapa ditutup, bukan dibiarkan apa adanya: selama pencarian tadi,
               induk dari setiap baris yang cocok sengaja dipaksa terbuka. Kalau
               keadaan itu ditinggalkan begitu saja, melepas filter menyisakan
               pohon setengah terbuka yang tidak pernah diminta siapa pun. Dan
               tanpa .todo-po-row di daftar show() ini, lapis PO tidak pernah
               kembali sama sekali — daftarnya tampak kosong. */
            $('.todo-worklist, .todo-flat-section, .todo-po-row').show();

            $('.todo-worklist').each(function () {
                var $section = $(this);
                if (!$section.find('.todo-po-row').length) {
                    // Section tanpa lapis PO (pola lama) — perilaku lamanya
                    // dipertahankan: header grup tampil, isinya tidak disentuh.
                    $section.find('.todo-group-row').show();
                    return;
                }
                $section.find('.todo-group-row, .todo-row').hide();
                $section.find('.todo-caret').removeClass('rotated');
            });

            // Baris di tabel flat yang sudah ter-grouping PO kembali tertutup
            // di bawah induknya; sisanya (tanpa lapis PO) tetap tampil.
            $('.todo-flat-section').each(function () {
                var $sec = $(this);
                if ($sec.find('.todo-po-row').length) {
                    $sec.find('.todo-row').hide();
                    $sec.find('.todo-caret').removeClass('rotated');
                } else {
                    $sec.find('.todo-row').show();
                }
            });

            $('#todoNoMatch').hide();
            return;
        }

        var anyVisible = false;
        $('.todo-row').each(function () {
            var $row = $(this);
            var match = true;
            if (q && ($row.data('search') || '').toString().indexOf(q) === -1) match = false;
            activeFields.forEach(function (field) {
                var val = ($row.data(field) || '').toString();
                if (!todoFilterState[field].has(val)) match = false;
            });
            $row.toggle(match).toggleClass('todo-filtered-out', !match);
            if (match) anyVisible = true;
        });

        // Header Budget Plan disembunyikan kalau semua CR anaknya tersaring habis.
        $('.todo-group-row').each(function () {
            var target = $(this).data('target');
            var $children = $('.' + target);
            var visible = $children.filter(':visible').length > 0;
            $(this).toggle(visible || $children.length === 0);
        });

        // Lalu header PO (MN-03) — dinilai SETELAH header Budget Plan, karena
        // yang menentukan adalah ada-tidaknya budget plan yang masih tampil.
        $('.todo-po-row').each(function () {
            var target = $(this).data('target');
            var $plans = $('.' + target);
            $(this).toggle($plans.filter(':visible').length > 0 || $plans.length === 0);
        });

        // Card section (grouped maupun flat) ikut disembunyikan kalau kosong total.
        $('.todo-worklist').each(function () {
            var hasVisible = $(this).find('.todo-row:visible').length > 0;
            $(this).toggle(hasVisible);
        });
        $('.todo-flat-section').each(function () {
            var hasVisible = $(this).find('.todo-row:visible').length > 0;
            $(this).toggle(hasVisible);
        });

        $('#todoNoMatch').toggle(!anyVisible);
        $('#todoEmptyState').hide();
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

            var ids = [];
            $tampil.each(function () {
                var id = $(this).find('[data-id]').first().data('id');
                if (id && ids.indexOf(id) === -1) ids.push(id);
            });
            if (!ids.length) return;

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

    $('#btnTodoRefresh').on('click', function () { location.reload(); });

    /* ============================== DETAIL DRAWER ============================== */
    var todoStatusColors = {
        DRAFT:'secondary', SUBMITTED:'warning', CHECKED:'info', VERIFIED:'primary',
        APPROVED:'success', QUEUED:'warning', PAID:'success', REALIZED:'primary',
        CLOSED:'dark', REJECTED:'danger', CANCELLED:'dark'
    };
    var todoTimelineSteps = ['DRAFT','SUBMITTED','CHECKED','VERIFIED','APPROVED','QUEUED','PAID','REALIZED','CLOSED'];
    var todoTimelineLabels = {
        DRAFT:'Draft', SUBMITTED:'Submitted', CHECKED:'Checked', VERIFIED:'Verified',
        APPROVED:'Approved', QUEUED:'Process', PAID:'Paid', REALIZED:'Settle', CLOSED:'Closed'
    };

    function todoDrwRow(label, value) {
        if (!value) return '';
        return '<div class="todo-drw-row"><div class="todo-drw-label">' + label + '</div><div class="todo-drw-value">' + value + '</div></div>';
    }

    // Grid thumbnail lampiran (gambar = klik buka carousel, non-gambar = ikon + link) —
    // pola sama persis dengan tab Request/Realization di halaman Cost Request.
    function renderAttGrid(atts) {
        if (!atts || !atts.length) return '<p class="text-muted py-2 mb-0" style="font-size:12px;">Belum ada lampiran.</p>';
        var html = '<div class="cr-att-grid">';
        atts.forEach(function (a) {
            var url = '/storage/' + a.path;
            if (a.is_image) {
                html += '<div class="cr-att-card">' +
                    '<img loading="lazy" src="' + url + '" class="cr-att-thumb" data-full="' + url + '" data-name="' + esc(a.original_name) + '">' +
                    '<div class="cr-att-name">' + esc(a.original_name) + '</div>' +
                '</div>';
            } else {
                var icon = a.is_pdf ? 'fa-file-pdf' : 'fa-file-alt';
                html += '<div class="cr-att-card">' +
                    '<a href="' + url + '" target="_blank" rel="noopener" class="cr-att-icon"><i class="fas ' + icon + '"></i></a>' +
                    '<div class="cr-att-name">' + esc(a.original_name) + '</div>' +
                '</div>';
            }
        });
        html += '</div>';
        return html;
    }

    function openCrDrawer(id) {
        $('#todoDrawerOverlay, #todoDrawer').css('display', 'flex').hide().fadeIn(150);
        $('#todoPaneInfo, #todoPaneRequest, #todoPaneRealisasi, #todoPaneLog').html('<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</div>');
        $('.todo-drawer-body .nav-link[href="#todoPaneInfo"]').tab('show');
        $('#todoTabRealisasiLi').hide();
        $('#todoDrwCode').text('CR-...');
        $('#todoDrwStatusWrap').html('');
        $('#todoDrwActions').hide().empty();
        $('body').addClass('todo-drawer-open');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#todoDrwCode').text(d.code);
            $('#todoDrwStatusWrap').html('<span class="badge badge-' + (todoStatusColors[d.status] || 'secondary') + ' px-2 py-1">' + (d.status_label || d.status) + '</span>');

            /* ── TAB INFO ── */
            // "PO & Budget Reference" = "{PO code} - {nama Budget Plan}" — nama Budget
            // Plan diambil dari d.budget_plan ("[kode] deskripsi"), kode bracket-nya dibuang.
            var bpName = (d.budget_plan || '').replace(/^\[[^\]]*\]\s*/, '');
            var poBudgetRef = (d.code_reference || '-') + ' - ' + (bpName || '-');

            var info = '';

            /* Chart of Account yang dipotong CR ini — nama akunnya saja, tanpa
               keterangan baris anggaran: yang dicari di sini adalah pos mana
               yang dipakai, rinciannya sudah ada di tab Request & modal View
               Budget. Akun yang sama digabung jadi satu chip. */
            var coaChips = [], coaSeen = {};
            (d.details || []).forEach(function (it) {
                var akun = it.account || '-';
                if (coaSeen[akun]) return;
                coaSeen[akun] = true;
                coaChips.push('<span class="todo-coa-chip">' + esc(akun) + '</span>');
            });
            /* Tanpa label: chip-nya sudah menerangkan dirinya sendiri, dan
               label "Chart of Account" di atasnya hanya menambah satu baris
               teks sebelum isi drawer yang sebenarnya. */
            if (coaChips.length) {
                info += '<div class="todo-coa-chips" style="margin-bottom:14px;">' + coaChips.join('') + '</div>';
            }

            info += todoDrwRow('Description', esc(d.description));
            info += todoDrwRow('Request Type', esc(d.type));
            info += todoDrwRow('PO & Budget Reference', esc(poBudgetRef));
            info += todoDrwRow('Beneficiary', esc(d.beneficiary) + (d.bank ? ' <span class="text-muted">(' + esc(d.bank) + (d.account_number ? ' — ' + esc(d.account_number) : '') + ')</span>' : ''));
            info += todoDrwRow('Amount', '<strong style="font-size:15px;color:var(--text);">' + esc(d.amount_formatted) + '</strong>');
            info += todoDrwRow('Requester', esc(d.applicant));
            info += todoDrwRow('Request Date', esc(d.created_at));
            if (d.notes) info += todoDrwRow('Notes', esc(d.notes));

            /* Riwayat pengajuan beneficiary yang sama — kolom Cost sudah
               dihitung server (Cash Advance memakai settlement, jatuh balik ke
               nilai pengajuan saat settlement masih 0). */
            var hist = d.beneficiary_history || [];
            info += '<div class="todo-drw-row" style="margin-top:18px;">' +
                '<div class="todo-drw-label">Riwayat Pengajuan Beneficiary Ini di PO Ini</div>';
            if (hist.length) {
                // Tabel dibangun ulang oleh renderBenHist() setiap kali diurutkan.
                window.__benHist = { rows: hist.slice(), key: 'paid_at_raw', dir: 'desc' };
                info += '<div class="todo-benhist-wrap" id="todoBenHist"></div>';
            } else {
                window.__benHist = null;
                info += '<div class="text-muted" style="font-size:12px;">Belum ada pengajuan lain yang sudah dibayar untuk beneficiary ini.</div>';
            }
            info += '</div>';

            $('#todoPaneInfo').html(info);
            renderBenHist();

            /* ── BAR AKSI WORKFLOW ── */
            renderDrawerActions(d);

            /* -- TAB REQUEST (rincian item pengajuan + attachment) --
               Barisnya dikelompokkan per Chart of Account seperti tab Budget
               di Budget Plan: baris grup (klik untuk buka/tutup) + sub-item. */
            var reqHtml = '';
            var canModerate = !!d.can_moderate_items;
            window.__crDetailAtt = {};
            var anyAtt = (d.details || []).some(function (x) { return x.attachments && x.attachments.length; });
            var showAksi = canModerate || anyAtt;
            if (d.details && d.details.length) {
                var reqGroups = groupByAccount(d.details);
                reqHtml += '<div class="d-flex justify-content-end mb-2">' +
                    '<button type="button" class="btn btn-xs btn-outline-secondary td-grp-expand" data-body="todoReqBody"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>&nbsp;' +
                    '<button type="button" class="btn btn-xs btn-outline-secondary td-grp-collapse" data-body="todoReqBody"><i class="fas fa-angle-double-up mr-1"></i>Collapse All</button>' +
                    '</div>';
                reqHtml += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                    '<thead class="bg-light"><tr>' +
                        '<th scope="col">Chart of Account</th>' +
                        '<th scope="col" class="text-right text-nowrap">Qty</th><th scope="col" class="text-right text-nowrap">Total</th>' +
                        (showAksi ? '<th scope="col" class="text-center text-nowrap" style="width:1%;">Aksi</th>' : '') +
                    '</tr></thead><tbody id="todoReqBody">';
                var reqTotal = 0, reqQty = 0;
                reqGroups.forEach(function (grp, gi) {
                    var gClass = 'td-req-grp-' + gi;
                    var gTotal = 0, gQty = 0;
                    grp.items.forEach(function (it) {
                        if (it.is_takeout) return;
                        gTotal += parseFloat(it.total) || 0;
                        gQty   += parseFloat(it.qty) || 0;
                    });
                    reqTotal += gTotal; reqQty += gQty;

                    reqHtml += '<tr class="td-grp-row" data-target="' + gClass + '">' +
                        '<td style="font-weight:700;">' +
                            '<i class="fas fa-chevron-right mr-2 td-grp-caret"></i>' +
                            esc(grp.account) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + grp.items.length + '</span>' +
                        '</td>' +
                        '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(gQty.toLocaleString('id-ID')) + '</td>' +
                        '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(Math.round(gTotal).toLocaleString('id-ID')) + '</td>' +
                        (showAksi ? '<td></td>' : '') +
                    '</tr>';

                    grp.items.forEach(function (item) {
                        var isTakeout = !!item.is_takeout;
                        var strike = isTakeout ? 'text-decoration:line-through;color:var(--text-faint);' : '';
                        var badge = isTakeout
                            ? ' <span class="badge badge-warning" style="font-size:8px;" title="' + escAttr(item.takeout_notes || '') + '">Takeout &middot; ' + esc(item.takeout_stage_label || '-') + '</span>'
                            : '';

                        var atts = item.attachments || [];
                        window.__crDetailAtt[item.id] = atts;
                        var attBadge = atts.length
                            ? ' <span class="badge badge-light border text-muted" style="font-size:8px;"><i class="fas fa-paperclip mr-1"></i>' + atts.length + '</span>'
                            : '';

                        var tdBtnStyle = 'white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;';
                        var actInner = '';
                        // Dua tombol sekaligus bikin kolom Aksi melebar dan menekan
                        // kolom deskripsi - kalau keduanya muncul, pakai ikon saja.
                        var iconOnly = atts.length && canModerate;
                        if (atts.length) {
                            actInner += '<button type="button" class="btn btn-info btn-view-detail-att mr-1" style="' + tdBtnStyle + '" data-detail="' + item.id + '" title="Lihat lampiran"><i class="fas fa-eye"></i>' + (iconOnly ? '' : ' View') + '</button>';
                        }
                        if (canModerate) {
                            actInner += isTakeout
                                ? '<button type="button" class="btn btn-outline-secondary td-restore-detail" style="' + tdBtnStyle + '" data-cr="' + d.id + '" data-detail="' + item.id + '" title="Kembalikan"><i class="fas fa-undo"></i>' + (iconOnly ? '' : ' Restore') + '</button>'
                                : '<button type="button" class="btn btn-outline-danger td-takeout-detail" style="' + tdBtnStyle + '" data-cr="' + d.id + '" data-detail="' + item.id + '" data-desc="' + escAttr(item.description || '') + '" title="Takeout"><i class="fas fa-cut"></i>' + (iconOnly ? '' : ' Takeout') + '</button>';
                        }
                        var act = showAksi ? '<td class="text-center text-nowrap">' + actInner + '</td>' : '';

                        reqHtml += '<tr class="' + gClass + '" style="display:none;">' +
                            '<td class="td-grp-indent" style="' + strike + '">' + esc(stripAccount(item.description, grp.account)) + badge + attBadge +
                                (item.notes ? '<br><small class="text-muted" style="text-decoration:none;">' + esc(item.notes) + '</small>' : '') +
                                (isTakeout && item.takeout_notes ? '<br><small class="text-muted" style="text-decoration:none;">Alasan: ' + esc(item.takeout_notes) + '</small>' : '') +
                            '</td>' +
                            '<td class="text-right text-nowrap" style="' + strike + '">' + esc(item.qty) + '</td>' +
                            '<td class="text-right text-nowrap" style="' + strike + '"><strong>' + esc(item.total_formatted) + '</strong></td>' +
                            act +
                        '</tr>';
                    });
                });
                reqHtml += '</tbody>' +
                    '<tfoot><tr style="background:var(--surface-2);">' +
                        '<th class="text-right">Grand Total</th>' +
                        '<th class="text-right text-nowrap">' + esc(reqQty.toLocaleString('id-ID')) + '</th>' +
                        '<th class="text-right text-nowrap">' + esc(Math.round(reqTotal).toLocaleString('id-ID')) + '</th>' +
                        (showAksi ? '<th></th>' : '') +
                    '</tr></tfoot>' +
                    '</table></div>';
            } else {
                reqHtml = '<p class="text-muted py-2 mb-0" style="font-size:12px;">Belum ada rincian item.</p>';
            }
            reqHtml += '<div class="todo-drw-row" style="margin-top:14px;"><div class="todo-drw-label">ATTACHMENTS</div>' + renderAttGrid(d.attachments) + '</div>';
            $('#todoPaneRequest').html(reqHtml);

            /* ── TAB REALISASI (rincian realisasi + refund + bukti) — hanya
               muncul kalau CR ini sudah punya data realisasi (status REALIZED/
               CLOSED), memakai field yang sudah dikirim showJson() ── */
            var hasRealisasi = (d.realizations && d.realizations.length) || (d.realization_attachments && d.realization_attachments.length);
            $('#todoTabRealisasiLi').toggle(!!hasRealisasi);
            if (hasRealisasi) {
                var realHtml = '';
                // Lampiran per item tidak lagi digelar inline di kolom Deskripsi -
                // dipindah ke kolom Aksi (tombol View) seperti tab Request.
                window.__crRealAtt = {};
                var anyRealAtt = (d.realizations || []).some(function (x) { return x.attachments && x.attachments.length; });
                if (d.realizations && d.realizations.length) {
                    // Pengelompokan per Chart of Account, sama dengan tab Request.
                    var realGroups = groupByAccount(d.realizations);
                    var realIdx = 0;
                    realHtml += '<div class="d-flex justify-content-end mb-2">' +
                        '<button type="button" class="btn btn-xs btn-outline-secondary td-grp-expand" data-body="todoRealBody"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>&nbsp;' +
                        '<button type="button" class="btn btn-xs btn-outline-secondary td-grp-collapse" data-body="todoRealBody"><i class="fas fa-angle-double-up mr-1"></i>Collapse All</button>' +
                        '</div>';
                    realHtml += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                        '<thead class="bg-light"><tr>' +
                            '<th scope="col">Chart of Account</th>' +
                            '<th scope="col" class="text-right text-nowrap">Nilai CR</th><th scope="col" class="text-right text-nowrap">Settlement</th>' +
                            (anyRealAtt ? '<th scope="col" class="text-center text-nowrap" style="width:1%;">Aksi</th>' : '') +
                        '</tr></thead><tbody id="todoRealBody">';
                    realGroups.forEach(function (grp, gi) {
                        var gClass = 'td-real-grp-' + gi;
                        var gCr = 0, gSettle = 0;
                        grp.items.forEach(function (r) {
                            gCr     += parseFloat(r.total_cr) || 0;
                            gSettle += parseFloat(r.actual) || 0;
                        });

                        realHtml += '<tr class="td-grp-row" data-target="' + gClass + '">' +
                            '<td style="font-weight:700;">' +
                                '<i class="fas fa-chevron-right mr-2 td-grp-caret"></i>' +
                                esc(grp.account) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + grp.items.length + '</span>' +
                            '</td>' +
                            '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(Math.round(gCr).toLocaleString('id-ID')) + '</td>' +
                            '<td class="text-right text-nowrap" style="font-weight:700;">' + esc(Math.round(gSettle).toLocaleString('id-ID')) + '</td>' +
                            (anyRealAtt ? '<td></td>' : '') +
                        '</tr>';

                        grp.items.forEach(function (r) {
                            var ri = realIdx++;
                            var itemAtts = r.attachments || [];
                            window.__crRealAtt[ri] = itemAtts;
                            var attBadge = itemAtts.length
                                ? ' <span class="badge badge-light border text-muted" style="font-size:8px;"><i class="fas fa-paperclip mr-1"></i>' + itemAtts.length + '</span>'
                                : '';
                            realHtml += '<tr class="' + gClass + '" style="display:none;">' +
                                '<td class="td-grp-indent">' + esc(stripAccount(r.cr_item, grp.account)) + attBadge +
                                    (r.notes ? '<br><small class="text-muted">' + esc(r.notes) + '</small>' : '') +
                                    // Alasan perubahan Description Request -> Settle (kolom Note di form Settlement).
                                    (r.change_note ? '<br><small class="text-muted" style="font-style:italic;"><i class="fas fa-sticky-note mr-1"></i>' + esc(r.change_note) + '</small>' : '') +
                                '</td>' +
                                '<td class="text-right text-nowrap">' + esc(r.total_cr_formatted) + '</td>' +
                                '<td class="text-right text-nowrap"><strong>' + esc(r.actual_formatted) + '</strong></td>' +
                                (anyRealAtt
                                    ? '<td class="text-center text-nowrap">' + (itemAtts.length
                                        ? '<button type="button" class="btn btn-info btn-view-real-att" style="white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;" data-row="' + ri + '" title="Lihat lampiran (' + itemAtts.length + ')"><i class="fas fa-eye"></i></button>'
                                        : '<span class="text-muted">-</span>') + '</td>'
                                    : '') +
                            '</tr>';
                        });
                    });
                    realHtml += '</tbody><tfoot><tr style="background:var(--surface-2);">' +
                        '<th class="text-right">Total</th>' +
                        '<th class="text-right">' + esc(d.amount_formatted) + '</th>' +
                        '<th class="text-right">' + esc(d.total_realization_formatted) + '</th>' +
                        (anyRealAtt ? '<th></th>' : '') +
                        '</tr></tfoot></table></div>';
                } else {
                    realHtml += '<p class="text-muted py-2 mb-0" style="font-size:12px;">Belum ada rincian realisasi.</p>';
                }
                /* Settlement yang MELEBIHI pengajuan bukan refund — itu kekurangan
                   bayar yang diselesaikan lewat Second Payment (istilah yang sudah
                   dipakai modul ini). Menamainya "Refund" dengan angka 0 menutupi
                   bahwa masih ada kewajiban yang belum dibayar. */
                var lebihBayar = parseInt(d.settlement_overage, 10) || 0;
                if (lebihBayar > 0) {
                    realHtml += '<div class="todo-drw-row" style="margin-top:14px;"><div class="todo-drw-label">Second Approval (Settlement − Pengajuan)</div>' +
                        '<div class="todo-drw-value" style="font-weight:700;color:var(--warning);">' + esc(d.settlement_overage_formatted) + '</div></div>';
                } else {
                    var refundColor = d.refund > 0 ? 'var(--success)' : 'var(--text)';
                    realHtml += '<div class="todo-drw-row" style="margin-top:14px;"><div class="todo-drw-label">Refund (Pengajuan − Settlement)</div>' +
                        '<div class="todo-drw-value" style="font-weight:700;color:' + refundColor + ';">' + esc(d.refund_formatted) + '</div></div>';
                }
                realHtml += '<div class="todo-drw-row" style="margin-top:14px;"><div class="todo-drw-label">ATTACHMENTS (GLOBAL)</div>' + renderAttGrid(d.realization_attachments) + '</div>';
                $('#todoPaneRealisasi').html(realHtml);
            }

            /* ── TAB LOG (approval history + timeline) ── */
            var logHtml = '';
            if (d.logs && d.logs.length) {
                var hist = d.logs.map(function (l) {
                    return '<div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:11.5px;">' +
                        '<div><span class="badge badge-' + (l.action_color || 'secondary') + '" style="font-size:9px;">' + esc(l.action_label || ((l.status_from_label || l.status_from) + ' → ' + (l.status_to_label || l.status_to))) + '</span> ' +
                        '<span class="text-muted">' + esc(l.date) + '</span></div>' +
                        '<div class="text-muted" style="margin-top:2px;">' + esc(l.actor || 'System') + (l.notes ? ' — ' + esc(l.notes) : '') + '</div>' +
                        '</div>';
                }).join('');
                logHtml += '<div class="todo-drw-row"><div class="todo-drw-label">Approval History</div>' + hist + '</div>';
            } else {
                logHtml += '<div class="todo-drw-row"><div class="todo-drw-label">Approval History</div><div class="text-muted" style="font-size:12px;">Belum ada riwayat.</div></div>';
            }

            logHtml += '<div class="todo-drw-row"><div class="todo-drw-label">Timeline</div>';
            if (d.status === 'REJECTED' || d.status === 'CANCELLED') {
                var reasonLog = (d.logs || []).find(function (l) { return l.status_to === d.status; });
                logHtml += '<div class="alert alert-' + (d.status === 'REJECTED' ? 'danger' : 'dark') + ' py-2 px-3" style="font-size:12px;">' +
                    '<i class="fas fa-exclamation-triangle mr-1"></i> CR ini <strong>' + d.status + '</strong>' +
                    (reasonLog && reasonLog.notes ? ': ' + esc(reasonLog.notes) : '') + '</div>';
            } else {
                var curIdx = todoTimelineSteps.indexOf(d.status);
                logHtml += '<ul class="todo-timeline">';
                todoTimelineSteps.forEach(function (step, i) {
                    var cls = i < curIdx ? 'done' : (i === curIdx ? 'current' : '');
                    logHtml += '<li class="' + cls + '">' + todoTimelineLabels[step] + '</li>';
                });
                logHtml += '</ul>';
            }
            logHtml += '</div>';
            $('#todoPaneLog').html(logHtml);
        }).fail(function () {
            $('#todoPaneInfo, #todoPaneRequest, #todoPaneLog').html('<div class="text-center text-danger py-5">Gagal memuat detail CR.</div>');
        });
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

    /* Nama akun sudah jadi judul grup - buang prefiksnya dari deskripsi baris
       supaya tidak terbaca dua kali ("Akomodasi - Akomodasi tim lapangan"). */
    function stripAccount(desc, acc) {
        desc = (desc == null ? '' : String(desc)).trim();
        acc  = (acc == null ? '' : String(acc)).trim();
        if (acc && desc.toLowerCase().indexOf(acc.toLowerCase()) === 0) {
            var rest = desc.slice(acc.length).replace(/^\s*[-\u2013\u2014]\s*/, '').trim();
            if (rest) return rest;
        }
        return desc || '-';
    }

    function esc(s) { return $(document.createElement('span')).text(s == null ? '' : s).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }

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

    /* ── RIWAYAT PENGAJUAN BENEFICIARY (drawer tab Info) ──
       Bisa diurutkan per kolom. Baris subtotal + header dibekukan (sticky) di
       atas area scroll-nya sendiri, jadi total tetap terlihat saat daftar
       panjang digulir. */
    var benHistCols = [
        { key: 'description',  label: 'Description',  type: 'text', cls: 'todo-benhist-desc' },
        { key: 'status_label', label: 'Status',       type: 'text', cls: 'text-nowrap text-center' },
        { key: 'cost',         label: 'Cost',         type: 'num',  cls: 'text-nowrap text-right' },
        { key: 'paid_at_raw',  label: 'Tanggal Paid', type: 'text', cls: 'text-nowrap' }
    ];

    function renderBenHist() {
        var st = window.__benHist;
        var $wrap = $('#todoBenHist');
        if (!st || !$wrap.length) return;

        var rows = st.rows.slice().sort(function (a, b) {
            var col = benHistCols.filter(function (c) { return c.key === st.key; })[0];
            var x = a[st.key], y = b[st.key];
            var r;
            if (col && col.type === 'num') r = (parseFloat(x) || 0) - (parseFloat(y) || 0);
            else r = String(x == null ? '' : x).localeCompare(String(y == null ? '' : y), 'id', { numeric: true });
            return st.dir === 'asc' ? r : -r;
        });

        var total = rows.reduce(function (t, h) { return t + (parseFloat(h.cost) || 0); }, 0);

        var head = '<tr class="todo-benhist-sub">' +
                '<th colspan="2" class="text-right">Subtotal (' + rows.length + ' CR)</th>' +
                '<th class="text-right text-nowrap">' + Math.round(total).toLocaleString('id-ID') + '</th>' +
                '<th colspan="2"></th>' +
            '</tr><tr class="todo-benhist-head">';
        benHistCols.forEach(function (c) {
            var aktif = st.key === c.key;
            var ikon = aktif ? (st.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
            head += '<th scope="col" class="todo-benhist-sort ' + c.cls + '" data-key="' + c.key + '" role="button" tabindex="0"' +
                ' aria-sort="' + (aktif ? (st.dir === 'asc' ? 'ascending' : 'descending') : 'none') + '">' +
                c.label + ' <i class="fas ' + ikon + '" style="opacity:' + (aktif ? '1' : '.35') + ';font-size:10px;"></i></th>';
        });
        head += '<th scope="col" class="text-center text-nowrap">Action</th></tr>';

        var body = rows.map(function (h) {
            return '<tr>' +
                '<td class="todo-benhist-desc">' + esc(h.description || '-') + '</td>' +
                '<td class="text-center text-nowrap"><span class="badge badge-' + esc(h.status_color || 'secondary') + '" style="font-size:9.5px;">' + esc(h.status_label || h.status || '-') + '</span></td>' +
                '<td class="text-right text-nowrap"><strong>' + esc(h.cost_formatted) + '</strong></td>' +
                '<td class="text-nowrap">' + esc(h.paid_at) + '</td>' +
                // Buka halaman Cost Request dengan modal detail CR ini langsung terbuka (?open=).
                '<td class="text-center text-nowrap"><a href="{{ url('/budget-request') }}?open=' + encodeURIComponent(h.id) + '"' +
                    ' target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" style="padding:1px 8px;font-size:10.5px;"' +
                    ' title="Lihat ' + escAttr(h.code) + ' di halaman Cost Request" aria-label="Lihat ' + escAttr(h.code) + '"><i class="fas fa-eye"></i></a></td>' +
            '</tr>';
        }).join('');

        $wrap.html('<table class="table table-sm mb-0 todo-benhist-table" style="font-size:11.5px;">' +
            '<thead>' + head + '</thead><tbody>' + body + '</tbody></table>');

        // Header kolom menempel tepat di bawah baris subtotal, berapa pun tingginya.
        var h1 = $wrap.find('.todo-benhist-sub').outerHeight() || 0;
        $wrap.find('.todo-benhist-head th').css('top', h1 + 'px');
    }

    $(document).on('click keydown', '.todo-benhist-sort', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        var st = window.__benHist;
        if (!st) return;
        var key = $(this).data('key');
        st.dir = (st.key === key && st.dir === 'desc') ? 'asc' : (st.key === key ? 'desc' : (key === 'cost' || key === 'paid_at_raw' ? 'desc' : 'asc'));
        st.key = key;
        renderBenHist();
    });

    function renderDrawerActions(d) {
        var list = d.workflow_actions || [];
        todoDrwActions = {};
        var html = drawerSelectButton();
        if (!list.length && !html) { $('#todoDrwActions').hide().empty(); return; }

        list.forEach(function (a) {
            todoDrwActions[a.key] = a;
            html += '<button type="button" class="btn btn-' + esc(a.variant) + ' todo-drw-action" data-key="' + escAttr(a.key) + '">' +
                '<i class="fas ' + esc(a.icon) + ' mr-1"></i>' + esc(a.label) + '</button>';
        });
        $('#todoDrwActions').html(html).css('display', 'flex');
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

    $(document).on('click', '.todo-open-drawer', function () {
        $todoDrwCheck = $(this).closest('tr').find('.todo-check').first();
        openCrDrawer($(this).data('id'));
    });

    // Pilih/batal pilih baris asal lalu tutup drawer, supaya user bisa lanjut
    // memilih baris lain dan memproses sekaligus lewat bar multi-select.
    $(document).on('click', '.todo-drw-select', function () {
        if (!$todoDrwCheck.length) return;
        $todoDrwCheck.prop('checked', !$todoDrwCheck.is(':checked')).trigger('change');
        $('#todoDrawerOverlay, #todoDrawer').fadeOut(150);
        $('body').removeClass('todo-drawer-open');
    });
    $('#todoDrwClose, #todoDrawerOverlay').on('click', function () {
        $('#todoDrawerOverlay, #todoDrawer').fadeOut(150);
        $('body').removeClass('todo-drawer-open');
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

    /* ── IMAGE VIEWER — carousel + zoom (ala WhatsApp), untuk Supporting Documents ── */
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

        $(document).on('click', '.cr-att-thumb', function () {
            var $group = $(this).closest('.cr-att-grid');
            var groupImages = $group.find('.cr-att-thumb').map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $group.find('.cr-att-thumb').index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        // Tombol View lampiran per item (kolom Aksi tab Request)
        $(document).on('click', '.btn-view-detail-att', function () {
            var id   = $(this).data('detail');
            var atts = (window.__crDetailAtt && window.__crDetailAtt[id]) || [];
            var imgs = atts.filter(function (a) { return a.is_image; })
                           .map(function (a) { return { full: '/storage/' + a.path, name: a.original_name }; });
            if (imgs.length) {
                openViewer(imgs, 0);
            } else if (atts.length) {
                window.open('/storage/' + atts[0].path, '_blank', 'noopener');
            }
        });

        // Tombol View lampiran per item (kolom Aksi tab Settlement)
        $(document).on('click', '.btn-view-real-att', function () {
            var atts = (window.__crRealAtt && window.__crRealAtt[$(this).data('row')]) || [];
            var imgs = atts.filter(function (a) { return a.is_image; })
                           .map(function (a) { return { full: '/storage/' + a.path, name: a.original_name }; });
            if (imgs.length) {
                openViewer(imgs, 0);
            } else if (atts.length) {
                window.open('/storage/' + atts[0].path, '_blank', 'noopener');
            }
        });

        /* Buka/tutup grup Chart of Account di tab Request & Settlement. */
        $(document).on('click', '.td-grp-row', function () {
            var $sub = $(this).closest('tbody').find('.' + $(this).data('target'));
            var expanding = $sub.first().is(':hidden');
            $sub.toggle(expanding);
            $(this).find('.td-grp-caret').toggleClass('rotated', expanding);
        });
        $(document).on('click', '.td-grp-expand, .td-grp-collapse', function () {
            var expand = $(this).hasClass('td-grp-expand');
            var $body  = $('#' + $(this).data('body'));
            $body.find('.td-grp-row').each(function () {
                $body.find('.' + $(this).data('target')).toggle(expand);
                $(this).find('.td-grp-caret').toggleClass('rotated', expand);
            });
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

});
</script>
@endpush
