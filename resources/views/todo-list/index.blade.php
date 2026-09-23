@extends('layouts.app')

@section('title', 'Todo List Request')

@section('content')

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
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Alasan Ditolak</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
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
                                <tr class="todo-row" data-pm="{{ strtoupper($cr->pm_internal_name) }}" data-po="{{ $cr->po_label }}" data-search="{{ $crSearch }}">
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
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
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
                                <tr class="todo-row" data-pm="{{ strtoupper($cr->pm_internal_name) }}" data-po="{{ $cr->po_label }}" data-search="{{ $crSearch }}">
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
                                    <td colspan="2"></td>
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
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-right text-nowrap {{ $refundClass }}"><strong>{{ $cr->proposed_refund_formatted }}</strong>
                                        @if($cr->proposed_refund < 0)<span class="badge badge-warning ml-1" style="font-size:8px;">Second Payment</span>@endif
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
                                    <th colspan="3" class="text-right">Subtotal</th>
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
            @endif
            @endforeach

            {{-- SETTLEMENT — CEO CLOSE (SETTLE_VERIFIED), diblokir kalau masih ada Second Payment PENDING --}}
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
                                <i class="fas fa-lock mr-1"></i>Close
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
                                </tr>
                                @endif
                                @foreach($group['items'] as $cr)
                                @php
                                    $crSearch = strtolower($cr->code . ' ' . $cr->description . ' '
                                        . ($cr->beneficiary?->nickname ?? '') . ' ' . $poGroup['po']);
                                    $crAging = $cr->aging_days;
                                    $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                                    $refundClass = $cr->proposed_refund < 0 ? 'text-danger' : ($cr->proposed_refund > 0 ? 'text-success' : 'text-muted');
                                @endphp
                                <tr class="{{ $indukBarisCr }} todo-row" style="display:none;"
                                    data-pm="{{ $group['pm'] }}" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
                                    {{-- CR yang Second Payment-nya masih PENDING memang tidak bisa
                                         di-Close, jadi tidak diberi checkbox sama sekali daripada
                                         ikut terpilih lalu gagal diam-diam saat bulk. --}}
                                    <td>
                                        @unless($cr->pending_second_payment)
                                        <input type="checkbox" class="todo-check" value="{{ $cr->id }}" data-amount="{{ $cr->amount }}" aria-label="Pilih {{ $cr->code }}">
                                        @endunless
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($cr->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                                    <td class="text-right text-nowrap"><strong>{{ $cr->amount_formatted }}</strong></td>
                                    <td class="text-right text-nowrap {{ $refundClass }}"><strong>{{ $cr->proposed_refund_formatted }}</strong></td>
                                    <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                                    <td class="text-center text-nowrap">
                                        @if($cr->pending_second_payment)
                                        <span class="badge badge-warning" style="font-size:9px;" title="Second Payment {{ $cr->pending_second_payment->amount_formatted }} masih PENDING">
                                            <i class="fas fa-hourglass-half mr-1"></i>Second Payment Pending
                                        </span>
                                        @else
                                        <div class="action-buttons ts-actions">
                                            <button type="button" class="ts-action-primary btn-ceo-close-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Close">
                                                <i class="fas fa-lock" aria-hidden="true"></i><span class="ts-al">Close</span>
                                            </button>

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

            {{-- SECOND PAYMENT — selisih Settlement yang belum dibayar.
                 Informatif saja: pelunasannya lewat batch Treasury
                 (Daily Transaction), bukan ditandai dari halaman ini. --}}
            @if($secondPaymentPending->isNotEmpty())
            <div id="tl-second-payment" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Second Payment — Menunggu Pembayaran
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
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Second Payment</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($secondPaymentPending as $cr)
                                <tr class="todo-row" data-search="{{ strtolower($cr->code . ' ' . $cr->description . ' ' . ($cr->beneficiary?->nickname ?? '')) }}">
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
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

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
            <div id="tl-{{ $stage['key'] }}" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-coins mr-1"></i> {{ $stage['title'] }}
                        <span class="badge badge-secondary ml-1">{{ $stage['data']->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Code</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Sumber Dana</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stage['data'] as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bp->source_name) }}">
                                    {{-- Todo #4 — kode membuka rincian Budget Plan di
                                         tempat, sehingga approver tidak perlu pindah ke
                                         halaman Budgeting hanya untuk melihat isinya
                                         sebelum memutuskan. --}}
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $bp->source_name }}</td>
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
                                                <button type="button" class="dropdown-item todo-bp-detail"
                                                        data-id="{{ $bp->id }}">
                                                    <i class="fas fa-chart-pie"></i>Lihat Rincian
                                                </button>
                                                <div class="dropdown-divider"></div>
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
                                    <th scope="col" class="text-nowrap" style="width:1%;">Sumber Dana</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col">Alasan Ditolak</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetingRejected as $bp)
                                @php $bpAgingClass = $bp->aging_days >= 3 ? 'text-danger' : ($bp->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($bp->code . ' ' . $bp->description . ' ' . $bp->source_name) }}">
                                    {{-- Todo #4 — kode membuka rincian Budget Plan di
                                         tempat, sehingga approver tidak perlu pindah ke
                                         halaman Budgeting hanya untuk melihat isinya
                                         sebelum memutuskan. --}}
                                    <td class="text-nowrap">
                                        <button type="button" class="cr-number-btn todo-bp-detail"
                                                data-id="{{ $bp->id }}">{{ $bp->code }}</button>
                                    </td>
                                    <td>{{ Str::limit($bp->description, 120) }}</td>
                                    <td class="text-nowrap">{{ $bp->source_name }}</td>
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
            @if($treasuryBatchCreate->isNotEmpty())
            <div id="tl-batch-create" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-layer-group mr-1"></i> Treasury — Buat Batch Pembayaran
                        <span class="badge badge-secondary ml-1">{{ $treasuryBatchCreate->count() }}</span>
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
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="4" class="text-right">Subtotal</th>
                                    <th class="text-right">{{ number_format($treasuryBatchCreate->sum('amount'), 0, ',', '.') }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($treasuryProcess->isNotEmpty())
            <div id="tl-batch-process" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-money-check-alt mr-1"></i> Treasury — Menunggu Proses
                        <span class="badge badge-secondary ml-1">{{ $treasuryProcess->count() }}</span>
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
                                @foreach($treasuryProcess as $batch)
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
                                    <th colspan="5" class="text-right">Subtotal</th>
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
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">No. PO</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Sisa Ditagih</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($collectionWaitingInvoice as $poNumber)
                                @php $poAgingClass = $poNumber->aging_days >= 3 ? 'text-danger' : ($poNumber->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($poNumber->po_number . ' ' . ($poNumber->project?->name ?? '') . ' ' . ($poNumber->project?->customer?->name ?? '')) }}">
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
            <div id="tl-invoice-verify" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice mr-1"></i> Invoice — Menunggu Verifikasi
                        <span class="badge badge-secondary ml-1">{{ $invoiceVerify->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Invoice</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceVerify as $inv)
                                @php $invAgingClass = $inv->aging_days >= 3 ? 'text-danger' : ($inv->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($inv->code . ' ' . ($inv->po?->name ?? '') . ' ' . ($inv->po?->customer?->name ?? '')) }}">
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
            <div id="tl-invoice-approve" class="card card-warning card-outline shadow-sm mt-3 todo-flat-section">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Invoice — Menunggu Approval
                        <span class="badge badge-secondary ml-1">{{ $invoiceApprove->count() }}</span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">Invoice</th>
                                    <th scope="col">PO / Customer</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceApprove as $inv)
                                @php $invAgingClass = $inv->aging_days >= 3 ? 'text-danger' : ($inv->aging_days >= 1 ? 'rv-nob-item' : 'text-muted'); @endphp
                                <tr class="todo-row" data-search="{{ strtolower($inv->code . ' ' . ($inv->po?->name ?? '') . ' ' . ($inv->po?->customer?->name ?? '')) }}">
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
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
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
                                <tr class="todo-row" data-search="{{ strtolower(($detail->project?->name ?? '') . ' ' . ($detail->stage?->name ?? '')) }}">
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
                    <div class="row mb-3" id="todoBpKpi"></div>

                    <h6 class="font-weight-bold" style="font-size:12px;">Rincian Anggaran per Chart of Account</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:12px;min-width:620px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col">Chart of Account</th>
                                    <th scope="col">Keterangan</th>
                                    <th scope="col" class="text-right text-nowrap">Anggaran</th>
                                    <th scope="col" class="text-right text-nowrap">Terpakai</th>
                                    <th scope="col" class="text-right text-nowrap">Sisa</th>
                                </tr>
                            </thead>
                            <tbody id="todoBpDetails"></tbody>
                        </table>
                    </div>

                    <div id="todoBpNotesWrap" class="mt-3" style="display:none;">
                        <h6 class="font-weight-bold" style="font-size:12px;">Catatan</h6>
                        <p id="todoBpNotes" class="text-muted mb-0" style="font-size:12px;"></p>
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
.todo-drw-row { margin-bottom:14px; }
.todo-drw-label { font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-faint);font-weight:700;margin-bottom:3px; }
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
        $('#tlBatchIds').html($dipilih.map(function () {
            return '<input type="hidden" name="cr_ids[]" value="' + $(this).val() + '">';
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

    $(document).on('click', '.todo-bp-detail', function (e) {
        e.stopPropagation();

        var id = $(this).data('id');

        $('#todoBpLoading').show();
        $('#todoBpBody').hide();
        $('#todoBpTitle').text('Rincian Budget Plan');
        $('#todoBpSubtitle').text('');
        $('#todoBpOpenPage').attr('href', '{{ url("/budget-plan") }}?open=' + id);
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

            /* Nama kuncinya mengikuti payload showJson() apa adanya:
               `total` = nilai anggaran baris (amount = harga satuan, bukan
               nilai baris), `deskripsi`, `used`, `remaining`. Menebak nama lain
               akan menghasilkan kolom kosong tanpa error apa pun. */
            var baris = (d.details || []).map(function (x) {
                var sisaBaris = parseInt(x.remaining, 10) || 0;
                return '<tr>' +
                    '<td>' + (x.account || '-') + '</td>' +
                    '<td>' + (x.deskripsi || '-') +
                        (x.qty ? ' <span class="text-muted" style="font-size:10px;">(' + x.qty + ' ' + (x.unit || '') + ')</span>' : '') +
                    '</td>' +
                    '<td class="text-right text-nowrap">' + todoBpRp(x.total) + '</td>' +
                    '<td class="text-right text-nowrap">' + todoBpRp(x.used) + '</td>' +
                    '<td class="text-right text-nowrap" style="color:var(--' + (sisaBaris < 0 ? 'danger' : 'text') + ');">' +
                        todoBpRp(sisaBaris) + '</td>' +
                '</tr>';
            }).join('');

            $('#todoBpDetails').html(baris || '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada rincian anggaran.</td></tr>');

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
    });

    /* ── WORKLIST — tombol View Budget ── */
    $(document).on('click', '.todo-view-budget', function (e) {
        e.stopPropagation();
        $($(this).data('target')).modal('show');
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
            var group = $(this).data('group');
            var $leaves = $('.' + group).find('.todo-check');
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
        var group = $(this).data('group');
        var $section = $(this).closest('.todo-worklist');
        $('.' + group).find('.todo-check').prop('checked', $(this).is(':checked'));
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
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya. Second Payment yang belum dibayar ikut dibatalkan.');
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

    function closeTodoFilterDropdowns() { $('.todo-filter-dropdown').remove(); }

    function openTodoFilterDropdown($btn, field, label) {
        closeTodoFilterDropdowns();
        var current = todoFilterState[field] || new Set();

        var uniqueRaw = [], seen = {};
        $('.todo-row').each(function () {
            var raw = ($(this).data(field) || '').toString();
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

            // Baris CR di section flat tidak punya induk — harus tetap tampil.
            $('.todo-flat-section .todo-row').show();

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
            $row.toggle(match);
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
            var $semua = $('.todo-row');
            var $tampil = $semua.filter(':visible');
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
                    '<img src="' + url + '" class="cr-att-thumb" data-full="' + url + '" data-name="' + esc(a.original_name) + '">' +
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

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#todoDrwCode').text(d.code);
            $('#todoDrwStatusWrap').html('<span class="badge badge-' + (todoStatusColors[d.status] || 'secondary') + ' px-2 py-1">' + (d.status_label || d.status) + '</span>');

            /* ── TAB INFO ── */
            // "PO & Budget Reference" = "{PO code} - {nama Budget Plan}" — nama Budget
            // Plan diambil dari d.budget_plan ("[kode] deskripsi"), kode bracket-nya dibuang.
            var bpName = (d.budget_plan || '').replace(/^\[[^\]]*\]\s*/, '');
            var poBudgetRef = (d.code_reference || '-') + ' - ' + (bpName || '-');

            var info = '';
            info += todoDrwRow('Description', esc(d.description));
            info += todoDrwRow('Request Type', esc(d.type));
            info += todoDrwRow('PO & Budget Reference', esc(poBudgetRef));
            info += todoDrwRow('Beneficiary', esc(d.beneficiary) + (d.bank ? ' <span class="text-muted">(' + esc(d.bank) + (d.account_number ? ' — ' + esc(d.account_number) : '') + ')</span>' : ''));
            info += todoDrwRow('Amount', '<strong style="font-size:15px;color:var(--text);">' + esc(d.amount_formatted) + '</strong>');
            info += todoDrwRow('Requester', esc(d.applicant));
            info += todoDrwRow('Request Date', esc(d.created_at));
            if (d.notes) info += todoDrwRow('Notes', esc(d.notes));
            $('#todoPaneInfo').html(info);

            /* ── TAB REQUEST (rincian item pengajuan + attachment) ── */
            var reqHtml = '';
            var canModerate = !!d.can_moderate_items;
            window.__crDetailAtt = {};
            var anyAtt = (d.details || []).some(function (x) { return x.attachments && x.attachments.length; });
            var showAksi = canModerate || anyAtt;
            if (d.details && d.details.length) {
                reqHtml += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                    '<thead class="bg-light"><tr>' +
                        '<th scope="col">Deskripsi</th>' +
                        '<th scope="col" class="text-right text-nowrap">Qty</th><th scope="col" class="text-right text-nowrap">Total</th>' +
                        (showAksi ? '<th scope="col" class="text-center text-nowrap" style="width:1%;">Aksi</th>' : '') +
                    '</tr></thead><tbody>';
                d.details.forEach(function (item) {
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
                    if (atts.length) {
                        actInner += '<button type="button" class="btn btn-info btn-view-detail-att mr-1" style="' + tdBtnStyle + '" data-detail="' + item.id + '" title="Lihat lampiran"><i class="fas fa-eye"></i> View</button>';
                    }
                    if (canModerate) {
                        actInner += isTakeout
                            ? '<button type="button" class="btn btn-outline-secondary td-restore-detail" style="' + tdBtnStyle + '" data-cr="' + d.id + '" data-detail="' + item.id + '" title="Kembalikan"><i class="fas fa-undo"></i> Restore</button>'
                            : '<button type="button" class="btn btn-outline-danger td-takeout-detail" style="' + tdBtnStyle + '" data-cr="' + d.id + '" data-detail="' + item.id + '" data-desc="' + escAttr(item.description || '') + '" title="Takeout"><i class="fas fa-cut"></i> Takeout</button>';
                    }
                    var act = showAksi ? '<td class="text-center text-nowrap">' + actInner + '</td>' : '';

                    reqHtml += '<tr>' +
                        '<td style="' + strike + '">' + esc(item.description || '-') + badge + attBadge +
                            (item.notes ? '<br><small class="text-muted" style="text-decoration:none;">' + esc(item.notes) + '</small>' : '') +
                            (isTakeout && item.takeout_notes ? '<br><small class="text-muted" style="text-decoration:none;">Alasan: ' + esc(item.takeout_notes) + '</small>' : '') +
                        '</td>' +
                        '<td class="text-right text-nowrap" style="' + strike + '">' + esc(item.qty) + '</td>' +
                        '<td class="text-right text-nowrap" style="' + strike + '"><strong>' + esc(item.total_formatted) + '</strong></td>' +
                        act +
                    '</tr>';
                });
                reqHtml += '</tbody></table></div>';
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
                if (d.realizations && d.realizations.length) {
                    realHtml += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                        '<thead class="bg-light"><tr>' +
                            '<th scope="col">Deskripsi</th>' +
                            '<th scope="col" class="text-right text-nowrap">Nilai CR</th><th scope="col" class="text-right text-nowrap">Settlement</th>' +
                        '</tr></thead><tbody>';
                    d.realizations.forEach(function (r) {
                        var itemAtts = r.attachments || [];
                        var attBadge = itemAtts.length
                            ? ' <span class="badge badge-light border text-muted" style="font-size:8px;"><i class="fas fa-paperclip mr-1"></i>' + itemAtts.length + '</span>'
                            : '';
                        realHtml += '<tr>' +
                            '<td>' + esc(r.cr_item || '-') + attBadge +
                                (r.notes ? '<br><small class="text-muted">' + esc(r.notes) + '</small>' : '') +
                                (itemAtts.length ? '<div class="mt-1">' + renderAttGrid(itemAtts) + '</div>' : '') +
                            '</td>' +
                            '<td class="text-right text-nowrap">' + esc(r.total_cr_formatted) + '</td>' +
                            '<td class="text-right text-nowrap"><strong>' + esc(r.actual_formatted) + '</strong></td>' +
                        '</tr>';
                    });
                    realHtml += '</tbody><tfoot><tr style="background:var(--surface-2);">' +
                        '<th class="text-right">Total</th>' +
                        '<th class="text-right">' + esc(d.amount_formatted) + '</th>' +
                        '<th class="text-right">' + esc(d.total_realization_formatted) + '</th>' +
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
                    realHtml += '<div class="todo-drw-row" style="margin-top:14px;"><div class="todo-drw-label">Second Payment (Settlement − Pengajuan)</div>' +
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

    function esc(s) { return $(document.createElement('span')).text(s == null ? '' : s).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }

    $(document).on('click', '.todo-open-drawer', function () {
        openCrDrawer($(this).data('id'));
    });
    $('#todoDrwClose, #todoDrawerOverlay').on('click', function () {
        $('#todoDrawerOverlay, #todoDrawer').fadeOut(150);
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

        function renderViewer() {
            var item = images[idx];
            if (!item) return;
            resetZoom();
            stageImg.src = item.full;
            $('#imgViewerName').text(item.name || '');
            counter.textContent = (idx + 1) + ' / ' + images.length;
            $('#imgViewerPrev, #imgViewerNext').toggle(images.length > 1);
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

        function showNext() { if (images.length) { idx = (idx + 1) % images.length; renderViewer(); } }
        function showPrev() { if (images.length) { idx = (idx - 1 + images.length) % images.length; renderViewer(); } }

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

        $('#imgViewerClose').on('click', closeViewer);
        $('#imgViewerNext').on('click', showNext);
        $('#imgViewerPrev').on('click', showPrev);
        $(overlay).on('click', function (e) { if (e.target === overlay) closeViewer(); });

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
    })();

});
</script>
@endpush
